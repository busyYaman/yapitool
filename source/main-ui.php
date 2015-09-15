#!/usr/bin/env php5
<?php
require_once('ui/bootframe.php');
include_once('lib/auction_creator.php');



class AuctionBridge
{
	const AUTO_SWITCH	= 0;
	const CREATE_ONLY	= 1;
	const UPDATE_ONLY	= 2;
	const ACCOUNT_CONFIG_PATH	= 'config/auction_account.ini';

	private $last_error_message = null;

	public $top_level_category = null;
	public $all_category = null;

	public function __construct()
	{
		$this->auction_creator = new AuctionCreator();
		$this->top_level_category = null;
		$this->all_category = null;
		$this->load_basic_data();
	}

	public function load_auth_config()
	{
		$auth_info = parse_ini_file(self::ACCOUNT_CONFIG_PATH, true);
		$result = array();
		$valid = TRUE;
		foreach(array('yid', 'partner_id', 'partner_secret', 'seller_id') as $k) {
			$v = isset($auth_info[$k]) ? $auth_info[$k] : '';
			$v = trim($v);
			$result[$k] = $v;
			if(empty($v))
			{ $valid = FALSE; }
		}
		return array($result, $valid);
	}

	public function save_auth_config($auth_cfg)
	{
		$this->clear_error();
		$c = ";\n; This is for user account mapping\n;\n; Sample :\n;\n; yid = my_user_account\n; partner_id = 12345\n; partner_secret = secret\n; seller_id = 1111\n;\n\n";
		foreach(array('yid', 'partner_id', 'partner_secret', 'seller_id') as $k) {
			$v = trim($auth_cfg[$k]);
			$c = $c.$k.' = '.addslashes($v)."\n";
		}
		$c = $c."\n";
		if(FALSE === file_put_contents(self::ACCOUNT_CONFIG_PATH, $c))
		{
			$this->add_error_message('寫入認證資訊檔 ('.self::ACCOUNT_CONFIG_PATH.') 時失敗。');
			return FALSE;
		}
		return TRUE;
	}

	public function clear_error()
	{
		$this->last_error_message = null;
	}

	public function get_last_error()
	{
		return $this->last_error_message;
	}

	protected function add_error_message($msg)
	{
		$this->last_error_message = $msg;
	}

	public function do_auth()
	{
		$a = $this->auction_creator;
		$res = $a->get_request_auth();
		if($res === FALSE)
		{
			echo "get_request_auth error";
			$this->add_error_message('傳送認證要求到伺服器失敗。');
			return FALSE;
		}
		$res = $a->get_auth_token();
		if($res === FALSE)
		{
			echo "get_auth_token error";
			$this->add_error_message('取得認證資訊識別碼 (Token) 失敗。');
			return FALSE;
		}
		return TRUE;
	}

	private function load_basic_data()
	{
		if(!$this->do_auth())
		{ return FALSE; }
		$res = $this->auction_creator->get_category_tree();
		if($res === FALSE)
		{
			echo "get_category_tree error";
			$this->add_error_message('取得分類資訊樹狀結構失敗。 (端點: get_category_tree)');
			return FALSE;
		}
		list($this->top_level_category, $this->all_category) = $res;
	}

	public function load_csv_file($filepath)
	{
		return $this->auction_creator->load_file($filepath);
	}

	public function upload_auction_item(&$datafile_interpreter, &$item, $is_verify, $run_mode=self::AUTO_SWITCH)
	{
		if(self::AUTO_SWITCH == $run_mode)
		{ $run_mode = empty($item->m_id) ? self::CREATE_ONLY : self::UPDATE_ONLY; }
		$this->auction_creator->keep_token_in_time();
		$datafile_interpreter->set_all_category($this->all_category);
		if(self::CREATE_ONLY == $run_mode)
		{
			return $this->auction_creator->create_auction_item($datafile_interpreter, $item, $is_verify);
		}
		else
		{
			return $this->auction_creator->update_auction_item($datafile_interpreter, $item, $is_verify);
		}
		return FALSE;
	}

	public function close()
	{
	}
}


class YahooAuctionUIApp extends wxApp
{
	private $bridge_object = null;

	public function closeEditor() {
		$this->bootframe->closeEditor();
	}

	function OnInit() {
		$this->bootframe = new BootFrame($this);
		$this->bootframe->setAuctionBridge(null);
		$this->bootframe->Show();
		$this->bridge_object = new AuctionBridge();
		$this->bootframe->setAuctionBridge($this->bridge_object);
		return 0;
	}

	function OnExit()
	{
		if(!is_null($this->bridge_object))
		{
			$this->bridge_object->close();
			$this->bridge_object = null;
		}
		return 0;
	}
}

$app = new YahooAuctionUIApp();
wxApp::SetInstance($app);
wxEntry();
?>
