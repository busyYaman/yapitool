<?php
include_once "general_api.php";

class _Item
{
	private $interpreter_instance		= NULL;
	public $m_id						= NULL;
	public $spec_id						= NULL;
	public $picked						= FALSE;
	public $error_record				= NULL;
	public $cvs_lineno					= 0;
	public $csv_field					= NULL;

	public function __construct(&$interpreter_instance, $cvs_lineno, $csv_field, $m_id=NULL, $spec_ids=NULL, $selected_spec=-1)
	{
		$this->interpreter_instance		= $interpreter_instance;
		$this->m_id						= $m_id;
		$this->spec_ids					= $spec_ids;
		$this->selected_spec			= $selected_spec;
		$this->picked					= FALSE;
		$this->error_record				= array();
		$this->cvs_lineno				= $cvs_lineno;
		$this->csv_field				= $csv_field;
	}

	public function clear_error()
	{
		$this->error_record				= array();
	}

	public function have_error()
	{
		return (count($this->error_record) > 0) ? TRUE : FALSE;
	}

	public function append_error($msg)
	{
		$this->error_record[]			= $msg;
	}

	public function get_selected_spec_id()
	{
		if(($this->selected_spec > 0) && !empty($this->spec_ids) && (count($this->spec_ids) >= $this->selected_spec))
		{
			$aux = $this->spec_ids[($this->selected_spec - 1)];
			return empty($aux) ? NULL : $aux;
		}
		return NULL;
	}

	public function set_category($category_id)
	{
		$this->csv_field[0] = $category_id;
	}

	public function set_m_id($m_id)
	{
		// $this->m_id = $m_id;	// fully outsourcing to interpreter
		$this->interpreter_instance->set_item_m_id($this, $m_id);
	}

	public function set_spec_id($selected_spec, $spec_ids)
	{
		$this->interpreter_instance->set_item_spec_id($this, $selected_spec, $spec_ids);
	}
}

class _Interpreter
{
	protected static $CSV_ENCODING		= array('UTF-8', 'BIG-5');

	public $last_error_message			= '';
	public $csv_header					= array();

	private $input_folder				= '.';
	private $input_encoding				= 'UTF-8';

	protected $csvpp_inventory_index	= array();	// CSV parsing plan (inventory)
	protected $csvpp_attribute_index	= array();	// CSV parsing plan (attribute)
	protected $csvpp_mid_index			= 0;
	protected $csvpp_specid_index		= 0;

	protected $column_check				= array();
	protected $payment_type				= array();
	protected $shipping_type			= array();
	protected $all_category				= array();

	public function __construct(&$column_check, &$payment_type, &$shipping_type)
	{
		$this->column_check				= $column_check;
		$this->payment_type				= $payment_type;
		$this->shipping_type			= $shipping_type;
	}

	public function set_all_category(&$all_category)
	{
		$this->all_category				= $all_category;
	}

	public function get_m_id_index($shift_value=0)
	{
		return ($shift_value + $this->csvpp_mid_index);
	}

	public function get_spec_id_index($shift_value=0)
	{
		return ($shift_value + $this->csvpp_specid_index);
	}

	public function get_inventory_index($shift_value=0)
	{
		if(0 == $shift_value)
		{ return $this->csvpp_inventory_index; }
		$result = array();
		foreach($this->csvpp_inventory_index as $v) {
			$result[] = ($shift_value + $v);
		}
		return $result;
	}

	public function get_spec_names(&$item)
	{
		$specname1 = $item->csv_field[2];		// 規格1名稱
		$specname2 = $item->csv_field[3];		// 規格2名稱
		$specname1 = empty($specname1) ? NULL : $specname1;
		$specname2 = empty($specname2) ? NULL : $specname2;
		return array($specname1, $specname2);
	}

	public function get_inventory_fields(&$item, $idx, $loose_rule=FALSE)
	{
		$v = strval($item->csv_field[$idx]);
		if(empty($v))
		{ return FALSE; }
		$aux = explode(';', $v);
		if(8 == count($aux))
		{
			list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img) = $aux;
			$spec1_id = NULL;
			$spec2_id = NULL;
		}
		elseif(11 == count($aux))
		{
			list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $_SEP, $spec1_id, $spec2_id) = $aux;
		}
		else
		{
			if($loose_rule)
			{
				if(count($aux) > 8)
				{ $aux = array_slice($aux, 0, 8); }
				return array_pad($aux, 10, NULL);
			}
			$item->append_error('庫存型號欄位數量不正確 (CSV 欄: '.(1+$idx).', 解析後欄位數: '.count($aux).')');
			return FALSE;
		}
		return array($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $spec1_id, $spec2_id);
	}

	public function set_inventory_fields(&$item, $idx, $spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $spec1_id=NULL, $spec2_id=NULL)
	{
		if(empty($spec1_id))
		{ $spec1_id = ''; }
		if(empty($spec2_id))
		{ $spec2_id = ''; }
		if(empty($spec1) && empty($spec2) && empty($stock) && empty($custom_id1) && empty($custom_id2) && empty($barcode) && empty($small_img) && empty($big_img) && empty($spec1_id) && empty($spec2_id))
		{ $val = ''; }
		else
		{
			if(empty($spec1_id) && empty($spec2_id))
			{ $aux = array($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img); }
			else
			{ $aux = array($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, 'Yahoo-SpecID', $spec1_id, $spec2_id); }
			$val = implode(';', $aux);
		}
		$item->csv_field[$idx] = $val;
		return $val;
	}

	public function is_inventory_column($q_index, $shift_value=0)
	{
		$q_index = $q_index - $shift_value;
		return (FALSE === array_search($q_index, $this->csvpp_inventory_index)) ? FALSE : TRUE;
	}

	public function is_product_image_column($q_index)
	{
		return (($q_index < 43) || ($q_index > 51)) ? FALSE : TRUE;
	}

	public function get_input_folder_path()
	{
		return $this->input_folder;
	}

	public function get_image_path($filename)
	{
		if(file_exists($filename))
		{ return $filename; }
		foreach(array('pic', 'photo', '.') as $folder) {
			foreach(array('', '.jpg', '.jpeg', '.png', '.gif') as $fext) {
				$tgt = $this->input_folder.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$filename.$fext;
				$p = realpath($tgt);
				if($p)
				{ return $p; }
			}
		}
		return FALSE;
	}

	private function _generate_csv_parse_plan_remaininfo($k_shift, &$remain_header)
	{
		foreach($remain_header as $k => $v) {
			$h_idx = $k + $k_shift;
			// inventory
			if('庫存型號' == $v)
			{
				$this->csvpp_inventory_index[] = $h_idx;
				continue;
			}
			// attribute
			$aux = explode('-', $v, 2);
			if((2 == count($aux)) && ('分類屬性' == $aux[0]))
			{
				$attr_name = $aux[1];
				$this->csvpp_attribute_index[$attr_name] = $h_idx;
				continue;
			}
			// merchandise ID
			if('Yahoo拍賣商品識別碼' == $v)
			{
				$this->csvpp_mid_index = $h_idx;
				continue;
			}
			elseif('Yahoo商品規格識別碼' == $v)
			{
				$this->csvpp_specid_index = $h_idx;
				continue;
			}
		}
	}

	private function generate_csv_parse_plan(&$header)
	{
		$l = count($header);
		if($l < 53)
		{
			$this->last_error_message = 'CSV 標頭與預設標頭不符合 (欄位數量)';
			return FALSE;
		}
		if (array_slice($header, 0, 53) !== array_slice($this->column_check['column_name'], 0, 53))
		{
			$this->last_error_message = 'CSV 標頭與預設標頭不符合 (基本資料欄位不符合)';
			return FALSE;
		}
		$this->csvpp_inventory_index	= array();
		$this->csvpp_attribute_index	= array();
		$this->csvpp_mid_index			= 0;
		$this->csvpp_specid_index		= 0;
		if(52 < $l)	// 庫存型號 (52th field) 在固定與動態標頭都存在
		{
			$remain_header = array_slice($header, 52, $l - 52);
			$this->_generate_csv_parse_plan_remaininfo(52, $remain_header);
		}
		if(0 == $this->csvpp_mid_index)
		{
			$this->csvpp_mid_index		= count($header);
			$header[]					= 'Yahoo拍賣商品識別碼';
		}
		if(0 == $this->csvpp_specid_index)
		{
			$this->csvpp_specid_index	= count($header);
			$header[]					= 'Yahoo商品規格識別碼';
		}
		return TRUE;
	}

	private function load_file_content($filepath)
	{
		$source				= file_get_contents($filepath);
		$source_encoding	= mb_detect_encoding($source, static::$CSV_ENCODING);

		if(($source_encoding !== FALSE) && ($source_encoding !== 'UTF-8'))
		{
			$source = mb_convert_encoding($source, 'UTF-8', $source_encoding);
			$this->input_encoding = $source_encoding;
		}
		else
		{
			$this->input_encoding = 'UTF-8';
		}

		$lines = preg_split("/\\r\\n|\\n/", $source);
		$l = count($lines);
		if($l < 2)
		{
			$this->last_error_message = 'CSV 檔案中沒有資料';
			return NULL;
		}
		return $lines;
	}

	public function import_file($filepath)
	{
		$lines = $this->load_file_content($filepath);
		if(is_null($lines))
		{ return NULL; }
		$this->input_folder = dirname($filepath);
		// get header and parsing plan
		$header = str_getcsv($lines[0]);
		if(!$this->generate_csv_parse_plan($header))
		{ return NULL; /* last_error_message had set inside the generate method */ }
		$this->csv_header	= $header;
		$expect_field_len	= count($header);
		// get auction items
		$auction_item = array();
		foreach($lines as $k => $line) {
			if(0 == $k)
			{ continue; }
			$line_number	= $k + 1;
			$line = trim($line);
			if((0 == strlen($line)) || (';' == $line[0]))
			{ continue; }
			$csv_field		= str_getcsv($line);
			$csv_field		= array_pad($csv_field, $expect_field_len, null);
			$m_id			= $csv_field[$this->csvpp_mid_index];
			$spec_id_text	= $csv_field[$this->csvpp_specid_index];
			$selected_spec	= $csv_field[1];
			$spec_ids		= explode(';', $spec_id_text);
			$spec_ids		= array_pad($spec_ids, 2, NULL);
			if('1' == $selected_spec)
			{ $selected_spec = 1; }
			elseif('2' == $selected_spec)
			{ $selected_spec = 2; }
			else
			{ $selected_spec = -1; }
			$auction_item[]	= new _Item($this, $line_number, $csv_field, $m_id, $spec_ids, $selected_spec);
		}
		return $auction_item;
	}

	protected function convert_fields_to_inputenc($fields)
	{
		if('UTF-8' == $this->input_encoding)
		{ return $fields; }
		$r = array();
		foreach($fields as $v) {
			$w = mb_convert_encoding($v, $this->input_encoding, 'UTF-8');
			$r[] = $w;
		}
		return $r;
	}

	public function export_file($filepath, &$datalist, &$wrote_line)
	{
		$fp = fopen($filepath, 'w');
		if(!$fp)
		{
			return -1;
		}
		fputcsv($fp, $this->convert_fields_to_inputenc($this->csv_header));
		$wrote_line = 0;
		foreach($datalist as $d) {
			if($d->picked)
			{
				fputcsv($fp, $this->convert_fields_to_inputenc($d->csv_field));
				$wrote_line++;
			}
		}
		fclose($fp);
		return 0;
	}

	public function set_item_m_id(&$item, $m_id)
	{
		$item->csv_field[$this->csvpp_mid_index]	= $m_id;
		$item->m_id									= $m_id;
	}

	public function set_item_spec_id(&$item, $selected_spec, $spec_ids)
	{
		if(2 > count($spec_ids))
		{ return; }
		$spec_id_text = implode(';', $spec_ids);
		$item->csv_field[$this->csvpp_specid_index]	= $spec_id_text;
		$item->selected_spec						= $selected_spec;
		$item->spec_ids								= $spec_ids;
	}

	protected function parse_to_api_basic_info(&$apidata, &$csv_field, &$CategoryId)
	{
		// $csv_field = $item->csv_field;
		$CategoryId						= $csv_field[0];		// 商品分類Id (required)
		// $items[$i][2~4] are for multi Spec. setting
		$Title							= $csv_field[5];		// 標題名稱 (required)
		$ShortDescription				= $csv_field[6];		// 商品簡述
		$Description					= $csv_field[7];		// 詳細介紹 (required)
		$OriginalPrice					= $csv_field[8];		// 定價
		$SalePrice						= $csv_field[9];		// 促銷價 (required)
		$ListingDurationDays			= $csv_field[10];		// 刊登天數 (required)
		$Location						= $csv_field[11];		// 商品所在地 (required)
		$UseState						= $csv_field[12];		// 商品新舊 (required)
		$BuyerRatingThreshold			= $csv_field[30];		// 最低評價限制
		$NegativeRatingThreshold		= $csv_field[31];		// 負評價數
		$MaxBuyQuantity					= $csv_field[32];		// 最高購買數量
		// -- put into structure
		$apidata['CategoryId']			= $CategoryId;
		//$apidata['Attributes']			= $Attributes;
		//$apidata['SpecType']			= $SpecType;
		//$apidata['SpecName1']			= $SpecName1;
		//$apidata['SpecName2']			= $SpecName2;
		//$apidata['InventoryInfo']		= $InventoryInfo;
		$apidata['Title']				= $Title;
		$apidata['ShortDescription']	= $ShortDescription;
		$apidata['Description']			= $Description;
		$apidata['OriginalPrice']		= $OriginalPrice;
		$apidata['SalePrice']			= $SalePrice;
		$apidata['ListingDurationDays']	= $ListingDurationDays;
		$apidata['Location']			= $Location;
		$apidata['UseState']			= $UseState;
		//$apidata['PaymentType']			= $PaymentType;
		//$apidata['ApplyStoreShippingRule']	= $ApplyStoreShippingRule;
		//$apidata['ShippingRule']		= $ShippingRule;
		$apidata['BuyerRatingThreshold']	= $BuyerRatingThreshold;
		$apidata['NegativeRatingThreshold']	= $NegativeRatingThreshold;
		$apidata['MaxBuyQuantity']		= $MaxBuyQuantity;
		//$apidata['SingleUnitPromotion']	= $SingleUnitPromotion;
		//$apidata['IsPreOrder']			= $IsPreOrder;
		//$apidata['PreOrderType']		= $PreOrderType;
		//$apidata['PreOrderDate']		= $PreOrderDate;
		//$apidata['PreOrderDays']		= $PreOrderDays;
	}

	protected function parse_to_api_spec_info(&$apidata, &$csv_field, &$spec_image_type_code, &$spec_names)
	{
		// $csv_field = $item->csv_field;
		$SpecType						= $csv_field[1];		// 規格種類 (required)
		// $items[$i][2~4] are for multi Spec. setting
		if(($SpecType == '1') || ($SpecType == '2'))
		{
			$SPEC_IMGTYPE_MAP = array('規格1' => 1, '規格2' => 2);
			$SpecName1					= $csv_field[2];		// 規格1名稱
			$SpecName2					= $csv_field[3];		// 規格2名稱
			$SpecImageType				= $csv_field[4];		// 規格圖採用規格1或規格2
			$spec_image_type_code		= isset($SPEC_IMGTYPE_MAP[$SpecImageType]) ? $SPEC_IMGTYPE_MAP[$SpecImageType] : -1;
		}
		else
		{
			$SpecName1					= '';
			$SpecName2					= '';
			$SpecImageType				= '';
			$spec_image_type_code		= -2;
		}
		$spec_names[0] = $SpecName1;
		$spec_names[1] = $SpecName2;
		// -- put into structure
		$apidata['SpecType']			= $SpecType;
		$apidata['SpecName1']			= $SpecName1;
		$apidata['SpecName2']			= $SpecName2;
	}

	protected function parse_to_api_payment_type(&$apidata, &$csv_field)
	{
		$PaymentType					= array(".pctc");		// 實體ATM、網路ATM、Famiport、輕鬆付帳戶餘額 (default)
		for($j = 13; $j < 20; $j++) {
			$v = trim(strval($csv_field[$j]));
			if(strtolower($v) == 'yes')
			{
				$payment_str = $this->csv_header[$j];
				$PaymentType[] = $this->payment_type[$payment_str];
			}
		}
		// -- put into structure
		$apidata['PaymentType']			= $PaymentType;
	}

	protected function parse_to_api_shipping_rule(&$apidata, &$csv_field)
	{
		$ApplyStoreShippingRule			= (strtolower($csv_field[20]) == "true") ? TRUE : FALSE;	// 是否套用賣家預設運送方式 (required)
		// $items[$i][21~29] are for Shipping rule setting
		$ShippingRule = array();
		for($k = 21; $k < 30; $k++) {
			$v = trim(strval($csv_field[$k]));
			if('' == $v)
			{ continue; }
			$shipping_str = $this->csv_header[$k];
			$shipping = array(
						'ShippingType'	=> $this->shipping_type[$shipping_str],
						'Fee'			=> $v);
			$ShippingRule[] = $shipping;
		}
		// -- put into structure
		$apidata['ApplyStoreShippingRule']	= $ApplyStoreShippingRule;
		$apidata['ShippingRule']		= $ShippingRule;
	}

	protected function parse_to_api_single_unit_promote(&$apidata, &$csv_field)
	{
		$SingleUnitPromotion = array();
		for($m = 33; $m < 39; $m+=2) {
			$quantity	= trim(strval($csv_field[0+$m]));
			$price		= trim(strval($csv_field[1+$m]));
			if(('' != $quantity) || ('' != $price))
			{
				$SingleUnitPromotion[] = array(
							'Quantity'	=> $quantity,
							'Price'		=> $price);
			}
		}
		// -- put into structure
		if(count($SingleUnitPromotion) > 0)
		{
			$apidata['SingleUnitPromotion']	= $SingleUnitPromotion;
		}
	}

	protected function parse_to_api_preorder(&$apidata, &$csv_field)
	{
		$IsPreOrder						= ('true' == strtolower(strval($csv_field[39]))) ? TRUE : FALSE;
		$PreOrderType					= $csv_field[40];		// 預購類型
		$PreOrderDate					= $csv_field[41];		// 指定預購出貨日期
		$PreOrderDays					= $csv_field[42];		// 指定預購出貨工作天
		// -- put into structure
		$apidata['IsPreOrder']			= $IsPreOrder;
		$apidata['PreOrderType']		= $PreOrderType;
		$apidata['PreOrderDate']		= $PreOrderDate;
		$apidata['PreOrderDays']		= $PreOrderDays;
	}

	protected function parse_api_structure_attributes($category_id, &$apidata, &$csv_field)
	{
		$Attributes = array();
		$cat_obj = $this->all_category[$category_id];
		if(!empty($cat_obj))
		{
			foreach($cat_obj->attribute_text_set as $attr_name) {
				$idx = @$this->csvpp_attribute_index[$attr_name];
				if(empty($idx))
				{ continue; }
				$attr_value = trim(strval(@$csv_field[$idx]));
				if(empty($attr_value))
				{ continue; }
				$aux = explode(';', $attr_value);
				if(count($aux) > 1)
				{
					if('' == end($aux))
					{ array_pop($aux); }
					$attr_value = $aux;
				}
				$Attributes[] = array('Name' => $attr_name, 'Value' => $attr_value);
			}
		}
		// -- put into structure
		//robert hack test  $apidata['Attributes']			= $Attributes;
		if(empty($Attributes))
		{
			$apidata['Attributes']			= '';
		}
		else
		{
			$apidata['Attributes']			= $Attributes;
		}
	}

	protected function parse_api_structure_invspec_image($fieldidx, $spec_value, $spec_id, $small_img, $big_img, &$spec_image, &$item)
	{
		if($small_img)
		{
			$orig_filename = $small_img;
			$small_img = $this->get_image_path($small_img);
			if(!$small_img)
			{
				$item->append_error('無法找到規格小圖圖檔 (CSV 欄: '.(1+$fieldidx).', 給定圖檔名稱: '.$orig_filename.')');
			}
		}
		if($big_img)
		{
			$orig_filename = $big_img;
			$big_img = $this->get_image_path($big_img);
			if(!$big_img)
			{
				$item->append_error('無法找到規格大圖圖檔 (CSV 欄: '.(1+$fieldidx).', 給定圖檔名稱: '.$orig_filename.')');
			}
		}
		if(empty($small_img) && empty($big_img))
		{ return; }
		$spec_image[$spec_value] = array(
					'id'	=> $spec_id,
					'value'	=> $spec_value,
					'small'	=> $small_img,
					'big'	=> $big_img);
	}

	protected function parse_api_structure_inventory_info($spec_image_type_code, &$apidata, &$spec_image, &$item)
	{
		$InventoryInfo = array();
		foreach($this->csvpp_inventory_index as $idx) {
			$aux = $this->get_inventory_fields($item, $idx);
			if(FALSE === $aux)
			{ continue; }
			list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $spec1_id, $spec2_id) = $aux;

			// InventoryInfo
			$specdata = array();
			if(!empty($spec1))
			{ $specdata['SpecValue1']	= $spec1; }
			if(!empty($spec2))
			{ $specdata['SpecValue2']	= $spec2; }
			if(!empty($stock))
			{ $specdata['Stock']			= $stock; }
			if(!empty($custom_id1))
			{ $specdata['CustomId1']		= $custom_id1; }
			if(!empty($custom_id2))
			{ $specdata['CustomId2']		= $custom_id2; }
			if(!empty($barcode))
			{ $specdata['barcode']			= $barcode; }
			if(!empty($spec1_id))
			{ $specdata['SpecValueId1']	= $spec1_id; }
			if(!empty($spec2_id))
			{ $specdata['SpecValueId2']	= $spec2_id; }
			if(empty($specdata))
			{ continue; }
			$InventoryInfo[] = $specdata;
			// for SetSpecImage mapping
			if((1 == $spec_image_type_code) || (2 == $spec_image_type_code))
			{
				$spec_value = (1 == $spec_image_type_code) ? $spec1 : $spec2;
				$spec_id = (1 == $spec_image_type_code) ? $spec1_id : $spec2_id;
				$this->parse_api_structure_invspec_image($idx, $spec_value, $spec_id, $small_img, $big_img, $spec_image, $item);
			}
		}
		// -- put into API data structure
		//$apidata['InventoryInfo']		= $InventoryInfo;
                if(empty($InventoryInfo))
                {
                        $apidata['InventoryInfo']                  = '';
                }
                else
                {
			$apidata['InventoryInfo']		= $InventoryInfo;
                }

	}

	protected function parse_api_item_image(&$item_image, &$item)
	{
		$index							= 1;
		for($i=43; $i<52; $i++) {
			$v = trim(strval($item->csv_field[$i]));
			if(0 == strlen($v))
			{ continue; }
			$orig_filename = $v;

            	        if (strpos($v,"http") === 0) {
            		    $image_name = basename($v);
            		    file_put_contents("./photo/" . $image_name, file_get_contents($v));
            		    $v = $image_name;
                        }

			$v = $this->get_image_path("./photo/" . $image_name);
			if(!$v)
			{
				$item->append_error('無法找到圖檔 (CSV 欄: '.(1+$i).', 給定圖檔名稱: '.$orig_filename.')');
				continue;
			}
			$k							= "ImageFile.{$index}";
			$item_image[$k]				= '@'.$v;
			$index++;
		}
	}

	public function get_api_item_structure(&$item, $is_verify=FALSE)
	{
		$apidata	= array();
		$item_image	= array();
		$spec_image	= array();
		$spec_image_type_code = -9;
		$spec_names	= array();
		// parsing
		$category_id = '';
		$this->parse_to_api_basic_info($apidata, $item->csv_field, $category_id);
		$this->parse_to_api_spec_info($apidata, $item->csv_field, $spec_image_type_code, $spec_names);
		$this->parse_to_api_payment_type($apidata, $item->csv_field);
		$this->parse_to_api_shipping_rule($apidata, $item->csv_field);
		$this->parse_to_api_single_unit_promote($apidata, $item->csv_field);
		$this->parse_to_api_preorder($apidata, $item->csv_field);
		$this->parse_api_structure_attributes($category_id, $apidata, $item->csv_field);
		$this->parse_api_structure_inventory_info($spec_image_type_code, $apidata, $spec_image, $item);
		$apidata['Verify']				= $is_verify;
		$this->parse_api_item_image($item_image, $item);
		return array($apidata, $item_image, $spec_image, $spec_image_type_code, $spec_names);
	}

	public function get_api_spec_image_structure(&$spec_id_list_1, &$spec_id_list_2, &$item, &$spec_image, $spec_image_type_code)
	{
		$spec_id = $item->get_selected_spec_id();
		if(empty($spec_id))
		{
			$item->append_error('指定的規格下沒有規格識別代碼。');
			return NULL;
		}
		$apidata	= array();
		if(1 == $spec_image_type_code)
		{ $spec_value_id_list = $spec_id_list_1; }
		elseif(2 == $spec_image_type_code)
		{ $spec_value_id_list = $spec_id_list_2; }
		else
		{ return NULL; }
		$apidata['MerchandiseId']		= $item->m_id;
		$apidata['SpecId']				= $spec_id;
		$spec_index = 1;
		foreach($spec_image as $spec_value => $spec_imgprofile) {
			$v_id = array_search($spec_value, $spec_value_id_list);
			if(empty($v_id))
			{ $v_id = empty($spec_imgprofile['id']) ? NULL : $spec_imgprofile['id']; }
			else
			{ $spec_imgprofile['id'] = $v_id; }
			if(empty($v_id))
			{
				echo "ERR: Cannot reach SpecValueID for [Spec.{$spec_index}].\n";
				continue;
			}
			$apidata["SpecValueId.{$spec_index}"]		= $v_id;
			if($spec_imgprofile['small'])
			{ $apidata["SmallImage.{$spec_index}"]		= '@'.$spec_imgprofile['small']; }
			if($spec_imgprofile['big'])
			{ $apidata["BigImage.{$spec_index}"]		= '@'.$spec_imgprofile['big']; }
			$spec_index++;
		}
		return (1 == $spec_index) ? NULL : $apidata;
	}

	public function update_inventory_info_spec_id(&$item, &$spec_id_list_1, &$spec_id_list_2)
	{
		foreach($this->csvpp_inventory_index as $idx) {
			$v = strval($item->csv_field[$idx]);
			if(empty($v))
			{ continue; }
			$aux = explode(';', $v);
			if(8 == count($aux))
			{
				list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img) = $aux;
			}
			elseif(11 == count($aux))
			{
				list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $_SEP, $spec1_id, $spec2_id) = $aux;
			}
			else
			{
				echo "ERR: count=".count($aux)."\n";
				continue;
			}
			$spec1_id = empty($spec1) ? NULL : array_search($spec1, $spec_id_list_1);
			$spec2_id = empty($spec2) ? NULL : array_search($spec2, $spec_id_list_2);
			$this->set_inventory_fields($item, $idx, $spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $spec1_id, $spec2_id);
		}
	}
}

class AuctionCreator extends GeneralAPI
{
	public function __construct()
	{
		parent::__construct();
	}

	public function load_file($filepath)
	{
		$datafile_interpreter	= new _Interpreter(
												$this->csv_check['column_check'],
												$this->csv_check['payment_type'],
												$this->csv_check['shipping_type']);
		$auction_item			= $datafile_interpreter->import_file($filepath);
		if(is_null($auction_item))
		{ return array(NULL, $datafile_interpreter); }
		return array($auction_item, $datafile_interpreter);
	}

	protected function get_detail_item_error(&$detailerr, &$item, $prefix=NULL)
	{
		if(empty($detailerr))
		{ return; }
		if(isset($detailerr['Code']) || isset($detailerr['Message']))
		{
			$msgprefix	= empty($prefix) ? '' : $prefix.': ';
			$errcode	= empty($detailerr['Code']) ? 'N/A' : $detailerr['Code'];
			$errmsg		= empty($detailerr['Message']) ? '(錯誤無文字說明)' : $detailerr['Message'];
			$errmsg		= $this->translate_error_code($errcode, $errmsg);
			$item->append_error($msgprefix.$errmsg.' (錯誤碼: '.$errcode.')');
		}
		else
		{
			foreach($detailerr as $k => $v) {
				$pfxk = is_numeric($k) ? '' : $k;
				if(!empty($pfxk))
				{ $pfxk = $this->translate_field_name($pfxk); }
				if(empty($prefix) || empty($pfxk))
				{ $nextprefix = strval($prefix).$pfxk; }
				else
				{ $nextprefix = implode('/', array(strval($prefix), $pfxk)); }
				if(is_array($v))
				{
					$this->get_detail_item_error($v, $item, $nextprefix);
				}
				else
				{
					$errmsg = empty($v) ? '-N/A-' : $v;
					$msgprefix = empty($nextprefix) ? '' : $nextprefix.': ';
					$item->append_error($msgprefix.$errmsg);
				}
			}
		}
	}

	protected function get_last_error_message($msg)
	{
		$err_code	= trim(strval(@$this->last_status['Code']));
		$err_msg	= trim(strval(@$this->last_status['Message']));
		if(0 == strlen($err_code))
		{ $err_code = '?'; }
		if(0 == strlen($err_msg))
		{ $err_msg = '-N/A-'; }
		$err_msg = $this->translate_error_code($err_code, $err_msg);
		return $msg.' (錯誤碼: '.$err_code.', 說明: '.$err_msg.')';
	}

	protected function upload_auction_images(&$datafile_interpreter, &$item, &$spec_id_list_1, &$spec_id_list_2, &$item_image, &$spec_image, $spec_image_type_code)
	{
		if(!empty($item_image))
		{
			$item_image['MerchandiseId']		= $item->m_id;
			$result								= $this->set_image($item_image);
			if($result === FALSE)
			{
				$item->append_error($this->get_last_error_message('上傳商品影像時失敗。'));
				$this->get_detail_item_error($this->last_status['Detail'], $item);
				return FALSE;
			}
		}

		if(!empty($spec_image))
		{
			$specimg_apidata = $datafile_interpreter->get_api_spec_image_structure($spec_id_list_1, $spec_id_list_2, $item, $spec_image, $spec_image_type_code);
			if($specimg_apidata)
			{
				$result							= $this->set_spec_image($specimg_apidata);
				if(FALSE === $result)
				{
					$item->append_error($this->get_last_error_message('上傳規格影像時失敗。'));
					$this->get_detail_item_error($this->last_status['Detail'], $item);
					return FALSE;
				}
			}
			else
			{
				$item->append_error('無法產生上傳規格影像所需的 API 結構體供呼叫之用。');
				return FALSE;
			}
		}
		return TRUE;
	}

	public function create_auction_item(&$datafile_interpreter, &$item, $is_verify=FALSE)
	{
		$item->clear_error();
		list($apidata, $item_image, $spec_image, $spec_image_type_code, $spec_names) = $datafile_interpreter->get_api_item_structure($item, $is_verify);
		if($item->have_error())
		{ return FALSE; }
		// print_r($apidata);
		// echo "========\n";
		$submit_result				= $this->create_buynow($apidata);
		print_r($submit_result);
		if($submit_result === FALSE)
		{
			$item->append_error($this->get_last_error_message('傳送到網路時失敗。'));
			$this->get_detail_item_error($this->last_status['Detail'], $item);
			return FALSE;
		}
		elseif(is_null($submit_result) && (!$is_verify))
		{
			$item->append_error('伺服器傳回空白結果。');
			return FALSE;
		}
		if($is_verify)
		{ return TRUE; }
		$m_id				= $submit_result['MerchandiseId'];
		$item->set_m_id($m_id);

		$spec_name_id_list = isset($submit_result['SpecNameIdList']) ? $submit_result['SpecNameIdList'] : NULL;
		if(!empty($spec_name_id_list))
		{
			$spec_ids = array();
			foreach($spec_names as $k => $n) {
				$spec_ids[$k] = empty($n) ? '' : array_search($n, $spec_name_id_list);
			}
			$item->set_spec_id($spec_image_type_code, $spec_ids);
		}

		$spec_id_list_1 = isset($submit_result['SpecValueIdList1']) ? $submit_result['SpecValueIdList1'] : NULL;
		if(empty($spec_id_list_1))
		{ $spec_id_list_1 = array(); }
		$spec_id_list_2 = isset($submit_result['SpecValueIdList2']) ? $submit_result['SpecValueIdList2'] : NULL;
		if(empty($spec_id_list_2))
		{ $spec_id_list_2 = array(); }
		$datafile_interpreter->update_inventory_info_spec_id($item, $spec_id_list_1, $spec_id_list_2);

		if(!$this->upload_auction_images($datafile_interpreter, $item, $spec_id_list_1, $spec_id_list_2, $item_image, $spec_image, $spec_image_type_code))
		{ return FALSE; }

		return TRUE;
	}

	public function update_auction_item(&$datafile_interpreter, &$item, $is_verify=FALSE)
	{
		$item->clear_error();
		list($apidata, $item_image, $spec_image, $spec_image_type_code, $spec_names) = $datafile_interpreter->get_api_item_structure($item, $is_verify);
		if(empty($item->m_id))
		{ $item->append_error('Yahoo拍賣商品識別代碼是空的。'); }
		if($item->have_error())
		{ return FALSE; }
		$apidata['MerchandiseId'] = $item->m_id;
		print_r($apidata);
		$submit_result				= $this->update_buynow($apidata);
		if($submit_result === FALSE)
		{
			$item->append_error($this->get_last_error_message('傳送到網路時失敗。'));
			$this->get_detail_item_error($this->last_status['Detail'], $item);
			return FALSE;
		}
		elseif(is_null($submit_result) && (!$is_verify))
		{
			$item->append_error('伺服器傳回空白結果。');
			return FALSE;
		}
		if($is_verify)
		{ return TRUE; }

		$spec_id_list_dummy = array();
		if(!$this->upload_auction_images($datafile_interpreter, $item, $spec_id_list_dummy, $spec_id_list_dummy, $item_image, $spec_image, $spec_image_type_code))
		{ return FALSE; }

		return TRUE;
	}
};
