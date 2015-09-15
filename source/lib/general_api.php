<?php

class CategoryNode
{
	public $category_id					= 0;
	public $is_leaf						= FALSE;
	public $name_text					= NULL;
	public $path_text					= NULL;
	public $child_node					= array();
	public $parent_node					= NULL;

	public function __construct($category_id, $is_leaf, $name_text, $path_text, $attribute_text_set)
	{
		$this->category_id				= $category_id;
		$this->is_leaf					= $is_leaf;
		$this->name_text				= $name_text;
		$this->path_text				= $path_text;
		$this->attribute_text_set		= $attribute_text_set;
		$this->child_node				= $is_leaf ? NULL : array();
	}

	public function add_child($node)
	{
		if($this->is_leaf)
		{ return; }
		$this->child_node[$node->category_id]	= $node;
		$node->parent_node						= $this;
	}
}

class GeneralAPI
{
	private $api_file					= 'config/api.ini';
	private $account_conf_file			= 'config/auction_account.ini';
	private $field_conf_file			= 'config/field.ini';
	private $errormessage_conf_file		= 'config/error_message.ini';

	private $category_tree_cachefile	= 'cache/category-tree.json';

	private $account_info				= array();
	private $yid						= NULL;
	private $partner_id					= NULL;
	private $partner_secret				= NULL;
	private $seller_id					= NULL;
	private $request_token				= NULL;
	private $auth_token					= NULL;
	private $api_info					= array();

	protected $api_server				= NULL;
	protected $yp_auth_host				= NULL;
	protected $create_api_server		= NULL;
	protected $update_api_server		= NULL;

	private $errormessage_map			= array();

	private $token_timestamp			= NULL;
	protected $csv_check				= NULL;
	public $last_status					= array();


	public function __construct()
	{
		// 從設定檔讀取拍賣帳號設定, 並寫入 $account_info
		$this->account_info				= $this->read_auction_account_from_file();
		// 從設定檔讀取拍賣API設定, 並寫入 $api_info
		$this->api_info					= $this->read_api_from_file();
		$this->errormessage_map			= parse_ini_file($this->errormessage_conf_file, true);
		$this->read_cvs_check_config();
	}

	protected function update_last_status($code, $message, $error_detail=NULL)
	{
		$this->last_status['Code']		= $code;
		$this->last_status['Message']	= $message;
		$this->last_status['Detail']	= $error_detail;
	}

	public function translate_error_code($code, $message)
	{
		return $this->get_field_value_from_array_w_default($this->errormessage_map, $code, $message);
	}

	public function translate_field_name($field_name)
	{
		return $this->get_field_value_from_array_w_default($this->errormessage_map, $field_name, $field_name);
	}

	public function get_request_auth()
	{
		$post_data						= array(
											'partner_id' => $this->partner_id,
										);
		$url							=  "{$this->yp_auth_host}/YPAuth/v1/request_auth";
		$json_result					= $this->curl_connect($url, $post_data, 'post');
		$result							= json_decode($json_result, true);

		$success						= ($result['Status']['Code'] === 0)
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$this->request_token			= $result['ResponseData']['RequestToken'];

		return $this->request_token;
	}

	public function get_auth_token()
	{
		$signature						= hash_hmac('sha1', $this->request_token, $this->partner_secret);
		$post_data						= array(
											'partner_id'		=> $this->partner_id,
											'request_token'		=> $this->request_token,
											'signature'			=> $signature,
										);

		$url							= "{$this->yp_auth_host}/YPAuth/v1/get_token";
		$json							= $this->curl_connect($url, $post_data, 'post');
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === '00')
										&& ($result['Status']['Message'] === 'Success');

		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$this->auth_token				= $result['ResponseData']['AuthorizeToken'];
		$this->token_timestamp			= microtime(true);
		return $this->auth_token;
	}

	public function keep_token_in_time()
	{
		$current_timestamp				= microtime(true);
		$refetch_time					= $this->csv_check['basic']['refetch_time'];
		$time_diff						= $current_timestamp - $this->token_timestamp;

		if ($time_diff >= $refetch_time)
		{
			echo "re-auth\n";
			$this->get_request_auth();
			$this->get_auth_token();
		}
	}

	protected static function convert_category_node($cat_id, $e)
	{
		$level_v			= intval(@$e['Level']);
		$parent_id			= intval(@$e['ParentId']);
		$is_leaf			= (@$e['LeafNode']) ? TRUE : FALSE;
		$name_text			= @$e['Name'];
		$path_text			= @$e['Path'];
		$attribute_text_set	= array();
		$aux = @$e['Attributes'];
		if(is_array($aux))
		{
			foreach($aux as $v) {
				$attribute_text = @$v['Name'];
				if(!empty($attribute_text))
				{
					$attribute_text_set[] = $attribute_text;
				}
			}
		}
		$o = new CategoryNode($cat_id, $is_leaf, $name_text, $path_text, $attribute_text_set);
		return array($level_v, $parent_id, $o);
	}

	protected static function convert_category_tree($result_data)
	{
		$toplevel_nodes					= array();
		$every_nodes					= array();
		$cat_ids						= array_keys($result_data);
		sort($cat_ids);
		$lost_nodes = array();
		foreach($cat_ids as $cid) {
			if(0 == $cid)
			{ continue; }
			$e = @$result_data[$cid];
			if(empty($e))
			{ continue; }
			list($level_v, $parent_id, $o)	= static::convert_category_node($cid, $e);
			if(1 == $level_v)
			{
				$toplevel_nodes[$cid]	= $o;
			}
			else
			{
				$t	= @$every_nodes[$parent_id];
				if($t)
				{ $t->add_child($o); }
				else
				{ $lost_nodes[] = array($parent_id, $o); }
			}
			$every_nodes[$cid] = $o;
		}
		$attempt = 8;
		while(($attempt > 0) && (count($lost_nodes) > 0)) {
			$lostagain_nodes = array();
			foreach($lost_nodes as $v) {
				list($parent_id, $o) = $v;
				$t	= @$every_nodes[$parent_id];
				if($t)
				{ $t->add_child($o); }
				else
				{ $lostagain_nodes[] = $v; }
			}
			$lost_nodes = $lostagain_nodes;
		}
		return array($toplevel_nodes, $every_nodes);
	}

	public function get_category_tree()
	{
		$result_data					= NULL;
		$json							= NULL;
		$load_from_cache				= file_exists($this->category_tree_cachefile);
										//&& (3600 > (time() - filemtime($this->category_tree_cachefile)));
		$load_from_network				= FALSE;
		if($load_from_cache)
		{
			$json						= file_get_contents($this->category_tree_cachefile);
		}
		if(empty($json))
		{
			$url						= "{$this->api_server}/v2/Category/GetTree?token={$this->auth_token}";
			$json						= $this->curl_connect($url, NULL, FALSE);
			$load_from_cache			= FALSE;
			$load_from_network			= TRUE;
		}
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] == 200)
										&& ($result['Status']['Message'] == 'Success');
		if(!$success)
		{
			print_r($result);
			return FALSE;
		}
		if($load_from_network)
		{
			file_put_contents($this->category_tree_cachefile, $json);
		}

		$result_data					= $result['ResponseData'];
		return static::convert_category_tree($result_data);
	}

	public function get_detail($catList)
	{
		$result_data					= NULL;
		$url							= "{$this->api_server}/v2/Category/GetDetail/{$catList}?token={$this->auth_token}";
		$json							= $this->curl_connect($url);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === '200')
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$result_data					= $result['ResponseData'];
		return $result_data;
	}

	public function get_children($catList)
	{
		$result_data					= NULL;
		$url							= "{$this->api_server}/v2/Category/GetChildren/{$catList}?token={$this->authToken}";
		$json							= $this->curl_connect($url);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === '200')
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$result_data					= $result['ResponseData'];
		return $result_data;
	}

	public function get_location()
	{
		$result_data					= NULL;
		$url							= "{$this->api_server}/v2/Merchandise/GetLocation?seller_id={$this->seller_id}&token={$this->auth_token}";
		$json							= $this->curl_connect($url);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === '200')
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$result_data					= $result['ResponseData'];
		return $result_data;
	}

	public function get_location_by_name($location)
	{
		$result_data					= NULL;
		$data							= array(
											"Name" => $location
										);
		$url							= "{$this->api_server}/v2/Merchandise/GetLocationByName?seller_id={$this->seller_id}&token={$this->auth_token}";
		$json							= json_encode($data);

		$header							= array();
		$httpHeader[]					= 'Content-type: application/json';
		$httpHeader[]					= 'Content-length: ' . strlen($json);
		$json							= $this->curl_connect($url, $json, 'post', $header);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === '200')
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
			return FALSE;
		}

		$result_data					= $result['ResponseData'];
		return $result_data;
	}

	public function create_buynow($data)
	{
		$result_data					= NULL;
		$url							= "{$this->create_api_server}/v2/Merchandise/CreateBuyNow?seller_id={$this->seller_id}&token={$this->auth_token}";
		$json							= json_encode($data);

		$header							= array();
		$header[]						= 'Content-type: application/json';
		$header[]						= 'Content-length: ' . strlen($json);

		$json							= $this->curl_connect($url, $json, 'post', $header);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === 200)
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			print_r($result);
			$resultstatus = $result['Status'];
			$this->update_last_status($resultstatus['Code'], $resultstatus['Message'],
						isset($resultstatus['ErrorList']) ? $resultstatus['ErrorList'] : NULL);
			return FALSE;
		}
		else
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
		}

		return (!empty($result['ResponseData'])) ?
			$result['ResponseData']:
			NULL;
	}

	public function update_buynow($data)
	{
		$result_data					= NULL;
		$url							= "{$this->update_api_server}/v2/Merchandise/UpdateBuyNow?seller_id={$this->seller_id}&token={$this->auth_token}";
		$json							= json_encode($data);

		$header							= array();
		$header[]						= 'Content-type: application/json';
		$header[]						= 'Content-length: ' . strlen($json);

		$json							= $this->curl_connect($url, $json, 'post', $header);
		$result							= json_decode($json, true);

		$success						= ($result['Status']['Code'] === 200)
										&& ($result['Status']['Message'] === 'Success');
		if(!$success)
		{
			print_r($result);
			$resultstatus = $result['Status'];
			$this->update_last_status($resultstatus['Code'], $resultstatus['Message'],
						isset($resultstatus['ErrorList']) ? $resultstatus['ErrorList'] : NULL);
			return FALSE;
		}
		else
		{
			$this->update_last_status($result['Status']['Code'], $result['Status']['Message']);
		}

		return (!empty($result['ResponseData'])) ?
			$result['ResponseData']:
			NULL;
	}

	public function set_image($image)
	{
		$result_data					= NULL;
		$image['Purge']					= FALSE;
		$url							= "{$this->api_server}/v2/Merchandise/SetImage?seller_id={$this->seller_id}&token={$this->auth_token}";

		$header							= array();
		$header[]						= 'Content-type: multipart/form-data';
		$json							= $this->curl_connect($url, $image, 'post', $header);
		$result							= json_decode($json, TRUE);

		$success						= ($result['Status']['Code'] === 200)
										&& ($result['Status']['Message'] === 'Success');
		if(isset($result['Status']['ErrorList']) && is_array($result['Status']['ErrorList']))
		{ $success = FALSE; }
		$this->update_last_status($result['Status']['Code'], $result['Status']['Message'],
					isset($result['Status']['ErrorList']) ? $result['Status']['ErrorList'] : NULL);
		print_r($result);
		if(!$success)
		{
			return FALSE;
		}

		$result_data					= $result['ResponseData'];
		return $result_data;
	}

	public function set_spec_image($spec_image)
	{
		$result_data					= NULL;
		$spec_image['Purge']			= FALSE;
		$url							= "{$this->api_server}/v2/Merchandise/SetSpecImage?seller_id={$this->seller_id}&token={$this->auth_token}";

		$header							= array();
		$header[]						= 'Content-type: multipart/form-data';
		$json							= $this->curl_connect($url, $spec_image, 'post', $header);
		$result							= json_decode($json, TRUE);

		$success						= ($result['Status']['Code'] === 200)
										&& ($result['Status']['Message'] === 'Success');
		if(isset($result['Status']['ErrorList']) && is_array($result['Status']['ErrorList']))
		{ $success = FALSE; }
		$this->update_last_status($result['Status']['Code'], $result['Status']['Message'],
					isset($result['Status']['ErrorList']) ? $result['Status']['ErrorList'] : NULL);
		if(!$success)
		{
			print_r($result);
			return FALSE;
		}

		$result_data					= isset($result['ResponseData']) ? $result['ResponseData'] : $result['Status'];
		return $result_data;
	}

	protected function get_field_value_from_array($arr, $field_name)
	{
		if(!array_key_exists($field_name, $arr))
		{
			echo "$field_name not found in config";
			return FALSE;
		}
		$field_value					= $arr[$field_name];
		return $field_value;
	}

	protected function get_field_value_from_array_w_default($arr, $field_name, $default_value)
	{
		if(!array_key_exists($field_name, $arr))
		{
			echo "$field_name not found in config";
			return $default_value;
		}
		$field_value					= $arr[$field_name];
		return $field_value;
	}

	private function curl_connect($url, $data=NULL, $method='get', $header=NULL)
	{
		$ch								= curl_init();
		$res							= curl_setopt($ch, CURLOPT_URL, $url);
		$res							= curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$res							= curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

		if($method=='post')
		{
			$res						= curl_setopt($ch, CURLOPT_POST, true);
			if($data!==NULL)
				$res					= curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		if($header!==NULL)
			$res						= curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		$res							= curl_setopt($ch, CURLOPT_HEADER, false);
		$res							= curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output							= curl_exec($ch);
		echo "$url\n";
		var_dump(curl_error($ch));
		$res							= curl_close($ch);

		return $output;
	}

	private function read_api_from_file()
	{
		$this->api_info					= parse_ini_file($this->api_file, true);

		// 從 api_info 取出 yp_auth_host
		$this->yp_auth_host				= $this->get_field_value_from_array($this->api_info, 'yp_auth_host');
		$this->api_server				= $this->get_field_value_from_array($this->api_info, 'api_server');
		$this->create_api_server		= $this->get_field_value_from_array($this->api_info, 'create_api_server');
		$this->update_api_server		= $this->get_field_value_from_array($this->api_info, 'update_api_server');

		return $this->api_info;
	}

	private function read_auction_account_from_file()
	{
		$this->account_info				= parse_ini_file($this->account_conf_file, true);

		// 從 account_info 取出 yid, partner_id, partner_secret
		$this->yid						= $this->get_field_value_from_array($this->account_info, 'yid');
		$this->partner_id				= $this->get_field_value_from_array($this->account_info, 'partner_id');
		$this->partner_secret			= $this->get_field_value_from_array($this->account_info, 'partner_secret');
		$this->seller_id				= $this->get_field_value_from_array($this->account_info, 'seller_id');

		return $this->account_info;
	}

	private function read_cvs_check_config()
	{
		$this->csv_check				= parse_ini_file($this->field_conf_file, TRUE);
		return $this->csv_check;
	}
};
