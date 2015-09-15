<?php
require_once('ui_base.php');
require_once('filepicker.php');

class InventoryEditor extends InventoryEditorUI {
	protected static $SYS_ENCODING = array('UTF-8', 'BIG-5');

	function __construct(&$auctioneditor, &$interpreter_instance, &$target_item, $field_idx, $spec1_name, $spec2_name, $parent=null) {
		parent::__construct($parent);
		$this->auctioneditor = $auctioneditor;
		$this->interpreter_instance = $interpreter_instance;
		$this->target_item = $target_item;
		$this->field_idx = $field_idx;

		$this->result_value = FALSE;

		$aux = $this->interpreter_instance->get_inventory_fields($target_item, $field_idx, TRUE);
		list($spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $spec1_id, $spec2_id) = $aux;

		$this->spec1_id = $spec1_id;
		$this->spec2_id = $spec2_id;

		if(empty($spec1_name))
		{
			$this->text_SpecValue1->Enable(FALSE);
		}
		else
		{
			$this->lbl_SpecValue1->SetLabelText('規格 1 ('.$spec1_name.')');
			$this->text_SpecValue1->SetValue(strval($spec1));
		}
		if(empty($spec2_name))
		{
			$this->text_SpecValue2->Enable(FALSE);
		}
		else
		{
			$this->lbl_SpecValue2->SetLabelText('規格 2 ('.$spec2_name.')');
			$this->text_SpecValue2->SetValue(strval($spec2));
		}
		$this->text_Stock->SetValue(strval($stock));
		$this->text_CustomId1->SetValue(strval($custom_id1));
		$this->text_CustomId2->SetValue(strval($custom_id2));
		$this->text_Barcode->SetValue(strval($barcode));
		$this->text_PicSmall->SetValue(strval($small_img));
		$this->text_PicLarge->SetValue(strval($big_img));

		$this->last_picsmall_tstamp = 0;
		$this->last_piclarge_tstamp = 0;
	}

	protected static function ConvertUIFeedbackString($val)
	{
		if(0 == strlen($val))
		{ return $val; }
		$v_enc	= mb_detect_encoding($val, static::$SYS_ENCODING);
		if(($v_enc !== FALSE) && ($v_enc !== 'UTF-8'))
		{ $val = mb_convert_encoding($val, 'UTF-8', $v_enc); }
		return $val;
	}

	public function GetValue() {
		return $this->result_value;
	}

	protected function open_image_file_picker($img_path)
	{
		$img_path = empty($img_path) ? NULL : $this->interpreter_instance->get_image_path($img_path);
		$f_dialog = new FilePicker($this, $this->interpreter_instance->get_input_folder_path(), $img_path);
		if(wxID_OK != $f_dialog->ShowModal())
		{ return FALSE; }
		$sel_path = $f_dialog->GetValue();
		if(empty($sel_path))
		{ return FALSE; }
		return $sel_path;
	}

	function onWindowClose($event) {
		$event->Skip();
	}

	function on_FocusPicSmall($event) {
		if(2 > (time() - $this->last_picsmall_tstamp))
		{ return; }
		$img_path = $this->text_PicSmall->GetValue();
		$img_path = $this->open_image_file_picker($img_path);
		if(FALSE !== $img_path)
		{
			$this->text_PicSmall->SetValue(static::ConvertUIFeedbackString($img_path));
		}
		$this->last_picsmall_tstamp = time();
	}

	function on_FocusPicLarge($event) {
		if(2 > (time() - $this->last_piclarge_tstamp))
		{ return; }
		$img_path = $this->text_PicLarge->GetValue();
		$img_path = $this->open_image_file_picker($img_path);
		if(FALSE !== $img_path)
		{
			$this->text_PicLarge->SetValue(static::ConvertUIFeedbackString($img_path));
		}
		$this->last_piclarge_tstamp = time();
	}

	function do_Save($event) {
		$spec1		= $this->text_SpecValue1->GetValue();
		$spec2		= $this->text_SpecValue2->GetValue();
		$stock		= $this->text_Stock->GetValue();
		$custom_id1	= $this->text_CustomId1->GetValue();
		$custom_id2	= $this->text_CustomId2->GetValue();
		$barcode	= $this->text_Barcode->GetValue();
		$small_img	= $this->text_PicSmall->GetValue();
		$big_img	= $this->text_PicLarge->GetValue();

		$spec1		= static::ConvertUIFeedbackString($spec1);
		$spec2		= static::ConvertUIFeedbackString($spec2);
		$stock		= static::ConvertUIFeedbackString($stock);
		$custom_id1	= static::ConvertUIFeedbackString($custom_id1);
		$custom_id2	= static::ConvertUIFeedbackString($custom_id2);
		$barcode	= static::ConvertUIFeedbackString($barcode);
		$small_img	= static::ConvertUIFeedbackString($small_img);
		$big_img	= static::ConvertUIFeedbackString($big_img);

		$this->result_value = $this->interpreter_instance->set_inventory_fields($this->target_item, $this->field_idx,
					$spec1, $spec2, $stock, $custom_id1, $custom_id2, $barcode, $small_img, $big_img, $this->spec1_id, $this->spec2_id);

		$retcode = (FALSE === $this->result_value) ? wxID_NO : wxID_OK;
		$this->EndModal($retcode);
	}
}


?>
