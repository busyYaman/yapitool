<?php
require_once('ui_base.php');
require_once('inventoryeditor.php');



class AuctionEditor extends AuctionEditorUI {
	protected static $SYS_ENCODING = array('UTF-8', 'BIG-5');

	private $COLOR_SUCCESS	= NULL;
	private $COLOR_FAIL		= NULL;

	function __construct($appinstance, $auction_bridge, $editmode, $parent=null) {
		parent::__construct($parent);
		$this->COLOR_SUCCESS = new wxColour(200, 255, 200);
		$this->COLOR_FAIL = new wxColour(255, 200, 200);

		$this->appinstance = $appinstance;
		$this->auction_bridge = $auction_bridge;
		$this->editmode = $editmode;
		$this->category_data = array();
		$this->setUIEditMode($editmode);
		$this->resetCategorySelector();

		$this->auction_item_list = NULL;
		$this->auction_item_successed = NULL;
		$this->auction_item_failed = NULL;
		$this->interpreter_instance = NULL;

		$this->publish_count = 0;
		$this->need_notice_category_leaf = TRUE;

		list($this->bSizer_AllContent, $this->dataviewAllContent)
										= $this->createDataGridWidget($this->panelAllContent);
		list($this->bSizer_SuccessContent, $this->dataviewSuccessContent)
										= $this->createDataGridWidget($this->panelSuccessContent);
		list($this->bSizer_FailedContent, $this->dataviewFailedContent)
										= $this->createDataGridWidget($this->panelFailedContent);
	}

	private function createDataGridWidget(&$panel)
	{
		$sizer = new wxBoxSizer(wxVERTICAL);
		$widget = new wxStaticText($panel, wxID_ANY, "(No Data)", wxDefaultPosition, wxDefaultSize, 0);
		$sizer->Add($widget, 1, wxALL|wxEXPAND, 5);
		$panel->SetSizer($sizer);
		$panel->Layout();
		$sizer->Fit($panel);
		return array($sizer, $widget);
	}

	protected function setUIEditMode($editmode) {
		if(0 == $editmode)
		{
			$this->SetTitle('Yahoo 拍賣上傳');
			$this->btnPublishTrigger->SetLabel('上傳 (Upload)');
		}
		else
		{
			$this->SetTitle('Yahoo 拍賣修改');
			$this->btnPublishTrigger->SetLabel('修改 (Modify)');
		}
	}

	protected function appendCategoryItem($item_set)
	{
		$cid_list = array_keys($item_set);
		sort($cid_list);
		foreach($cid_list as $cid) {
			$this->category_data[] = $item_set[$cid];
		}
	}

	protected function syncCategoryDataToUI($selection_pos=0)
	{
		$item = array();
		foreach($this->category_data as $v) {
			$item[] = (is_null($v) ? '(Category)' : "({$v->category_id}) {$v->path_text}");
		}
		$this->CategorySelector->SetSelection(wxNOT_FOUND);
		$this->CategorySelector->Set($item);
		$this->CategorySelector->SetSelection($selection_pos);
	}

	protected function resetCategorySelector()
	{
		$this->category_data = array();
		$this->category_data[] = null;
		$this->appendCategoryItem($this->auction_bridge->top_level_category);
		$this->syncCategoryDataToUI();
		$this->btnApplyCategory->Enable(FALSE);
	}

	protected function getSelectedCategory()
	{
		$idx = $this->CategorySelector->GetSelection();
		if($idx == wxNOT_FOUND)
		{ return null; }
		$result = @$this->category_data[$idx];
		return ($result ? $result : null);
	}

	protected function getCurrentDataTable()
	{
		$pg = $this->panelContent->GetSelection();
		if(0 == $pg)
		{
			return array($this->panelAllContent, $this->bSizer_AllContent, $this->dataviewAllContent, $this->auction_item_list);
		}
		elseif(1 == $pg)
		{
			return array($this->panelSuccessContent, $this->bSizer_SuccessContent, $this->dataviewSuccessContent, $this->auction_item_successed);
		}
		elseif(2 == $pg)
		{
			return array($this->panelFailedContent, $this->bSizer_FailedContent, $this->dataviewFailedContent, $this->auction_item_failed);
		}
		return NULL;
	}

	protected function countSelectedItem(&$datalist, &$widget=NULL)
	{
		if(is_null($datalist))
		{
			if(!is_null($widget))
			{
				$msgbox = new wxMessageDialog($this, '目前頁籤的資料集是空的', '資料讀取失敗');
				$msgbox->ShowModal();
			}
			return -1;
		}
		$c = 0;
		foreach($datalist as $d) {
			if($d->picked)
			{ $c++; }
		}
		if((0 == $c) && (!is_null($widget)))
		{
			$msgbox = new wxMessageDialog($this, '目前頁籤的資料都沒有被選取，要選取全部資料進行操作嗎？', '選取資料', wxYES_NO);
			if(wxID_YES == $msgbox->ShowModal())
			{
				static::SetAllAuctionItemPick($datalist, $widget, TRUE);
				$c = count($datalist);
			}
		}
		return $c;
	}

	protected function openImageFilePicker($img_path)
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

	protected static function CreateGridAttributeForPicker()
	{
		$picker_attr = new wxGridCellAttr();
		$picker_attr->SetEditor(new wxGridCellBoolEditor());
		$picker_attr->SetRenderer(new wxGridCellBoolRenderer());
		$picker_attr->SetReadOnly(FALSE);
		return $picker_attr;
	}

	protected static function CreateGridAttributeForText()
	{
		$text_attr = new wxGridCellAttr();
		$text_attr->SetEditor(new wxGridCellTextEditor(1024));
		$text_attr->SetRenderer(new wxGridCellStringRenderer());
		$text_attr->SetReadOnly(TRUE);
		return $text_attr;
	}

	protected static function SetupGridHeader(&$widget, &$header)
	{
		$widget->SetColAttr(0, static::CreateGridAttributeForPicker());
		$widget->SetColLabelValue(0, '-');
		foreach($header as $k => $v) {
			$c = $k + 1;
			$widget->SetColAttr($c, static::CreateGridAttributeForText());
			$widget->SetColLabelValue($c, $v);
		}
	}

	protected static function SetupGridValue(&$widget, &$header, &$datalist)
	{
		$l = count($header);
		foreach($datalist as $row => $d) {
			$widget->SetRowLabelValue($row, "{$d->cvs_lineno}");
			$widget->SetCellValue($row, 0, $d->picked);
			for($col = 0; $col < $l; $col++) {
				$c = $col + 1;
				$widget->SetCellValue($row, $c, $d->csv_field[$col]);
			}
		}
		$widget->AutoSize();
		$widget->Fit();
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

	protected function reloadGrid(&$panel, &$sizer, &$origional_widget, &$datalist)
	{
		$header		= $this->interpreter_instance->csv_header;
		// prepare UI widget
		$widget = new wxGrid($panel, wxID_ANY, wxDefaultPosition, wxDefaultSize, 0);
		$widget->CreateGrid(count($datalist), 1 + count($header), wxGrid::wxGridSelectRows);
		// setup content
		static::SetupGridHeader($widget, $header);
		static::SetupGridValue($widget, $header, $datalist);
		// put widget to container
		$sizer->Replace($origional_widget, $widget);
		$origional_widget->Destroy();
		$origional_widget = $widget;
		$panel->Layout();
		$sizer->Fit($panel);
		return $widget;
	}

	protected function clearGrid(&$panel, &$sizer, &$origional_widget)
	{
		$widget = new wxStaticText($panel, wxID_ANY, "(No Data)", wxDefaultPosition, wxDefaultSize, 0 );
		$sizer->Replace($origional_widget, $widget);
		$origional_widget->Destroy();
		$origional_widget = $widget;
		$panel->Layout();
		$sizer->Fit($panel);
		return $widget;
	}

	protected function reloadMainGrid()
	{
		$this->dataviewAllContent = $this->reloadGrid($this->panelAllContent, $this->bSizer_AllContent, $this->dataviewAllContent, $this->auction_item_list);
		$this->dataviewSuccessContent = $this->clearGrid($this->panelSuccessContent, $this->bSizer_SuccessContent, $this->dataviewSuccessContent);
		$this->dataviewFailedContent = $this->clearGrid($this->panelFailedContent, $this->bSizer_FailedContent, $this->dataviewFailedContent);
		$this->panelContent->SetSelection(0);
		$this->Layout();
		$this->dataviewAllContent->Connect( wxEVT_GRID_CELL_LEFT_CLICK, array($this, "onAuctionItemClicked") );
		$this->dataviewAllContent->Connect( wxEVT_GRID_LABEL_LEFT_CLICK, array($this, "onAuctionAllClicked") );
		return $this->dataviewAllContent;
	}

	protected function reloadResultGrid()
	{
		if(empty($this->auction_item_successed))
		{ $this->dataviewSuccessContent = $this->clearGrid($this->panelSuccessContent, $this->bSizer_SuccessContent, $this->dataviewSuccessContent); }
		else
		{
			$this->dataviewSuccessContent = $this->reloadGrid($this->panelSuccessContent, $this->bSizer_SuccessContent, $this->dataviewSuccessContent, $this->auction_item_successed);
			$this->dataviewSuccessContent->Connect( wxEVT_GRID_CELL_LEFT_CLICK, array($this, "onSuccessedAuctionItemClicked") );
			$this->dataviewSuccessContent->Connect( wxEVT_GRID_LABEL_LEFT_CLICK, array($this, "onSuccessedAuctionAllClicked") );
		}
		if(empty($this->auction_item_failed))
		{ $this->dataviewFailedContent = $this->clearGrid($this->panelFailedContent, $this->bSizer_FailedContent, $this->dataviewFailedContent); }
		else
		{
			$this->dataviewFailedContent = $this->reloadGrid($this->panelFailedContent, $this->bSizer_FailedContent, $this->dataviewFailedContent, $this->auction_item_failed);
			$this->dataviewFailedContent->Connect( wxEVT_GRID_CELL_LEFT_CLICK, array($this, "onFailedAuctionItemClicked") );
			$this->dataviewFailedContent->Connect( wxEVT_GRID_LABEL_LEFT_CLICK, array($this, "onFailedAuctionAllClicked") );
		}
		$this->Layout();
	}

	protected function updateMainGridErrorStatus(&$widget)
	{
		$m_id_index = $this->interpreter_instance->get_m_id_index(1);
		$spec_id_index = $this->interpreter_instance->get_spec_id_index(1);
		$inventory_index = $this->interpreter_instance->get_inventory_index(0);
		//$widget = $this->dataviewAllContent;
		foreach($this->auction_item_list as $row => $d) {
			if(!$d->picked)
			{ continue; }
			if($d->have_error())
			{
				$this->dataviewAllContent->SetCellBackgroundColour($row, 1, $this->COLOR_FAIL);
			}
			else
			{
				$widget->SetCellValue($row, $m_id_index, strval($d->m_id));
				$widget->SetCellValue($row, $spec_id_index, static::ConvertSpecIdDisplayText($d->spec_ids));
				foreach($inventory_index as $iidx) {
					$widget->SetCellValue($row, 1 + $iidx, $d->csv_field[$iidx]);
				}
				$this->dataviewAllContent->SetCellBackgroundColour($row, 1, $this->COLOR_SUCCESS);
			}
		}
	}

	protected function loadCSVFile($filepath)
	{
		list($auction_item_list, $interpreter_instance) = $this->auction_bridge->load_csv_file($filepath);
		if(is_null($auction_item_list))
		{
			$reason = empty($interpreter_instance->last_error_message) ? '' : ': '.$interpreter_instance->last_error_message;
			$msgbox = new wxMessageDialog($this, '載入 CSV 失敗 (檔案路徑: '.$filepath.')'.$reason, '檔案讀取失敗');
			$msgbox->ShowModal();
			return;
		}
		$this->auction_item_list	= $auction_item_list;
		$this->interpreter_instance	= $interpreter_instance;
		$this->dataviewAllContent = $this->reloadMainGrid();
		// wxEVT_GRID_CELL_CHANGE
	}

	protected function saveCSVFile($selected_path, &$datalist)
	{
		$header = $this->interpreter_instance->csv_header;
		if(empty($header))
		{ return; }
		$wrote_line = -1;
		$retcode = $this->interpreter_instance->export_file($selected_path, $datalist, $wrote_line);
		if(0 == $retcode)
		{
			$msgbox = new wxMessageDialog($this, '寫入 CSV 完成，寫入記錄 '.$wrote_line.' 筆。', '檔案儲存完成');
		}
		elseif(-1 == $retcode)
		{
			$msgbox = new wxMessageDialog($this, '寫入 CSV 失敗，檔案無法開啟 (檔案路徑: '.$selected_path.')', '檔案儲存失敗');
		}
		else
		{
			$msgbox = new wxMessageDialog($this, '寫入 CSV 失敗 (檔案路徑: '.$selected_path.', 錯誤代碼: '.$retcode.')', '檔案儲存失敗');
		}
		$msgbox->ShowModal();
	}

	protected static function ConvertSpecIdDisplayText($spec_ids)
	{
		if(empty($spec_ids) || !is_array($spec_ids))
		{ return ''; }
		return implode(';', $spec_ids);
	}

	protected static function ToggleAuctionItemPick(&$datalist, &$containerwidget, $row)
	{
		if( ($row < 0) || ($row >= count($datalist)) )
		{ return NULL; }
		$d = $datalist[$row];
		$d->picked = !$d->picked;
		$containerwidget->SetCellValue($row, 0, $d->picked);
		return $d->picked;
	}

	protected static function SetAllAuctionItemPick(&$datalist, &$containerwidget, $pick)
	{
		if(empty($datalist))
		{ return; }
		foreach($datalist as $row => $d) {
			$d->picked = $pick;
			$containerwidget->SetCellValue($row, 0, $pick);
		}
	}

	protected static function ToggleAllAuctionItemPick(&$datalist, &$containerwidget)
	{
		$d = $datalist[0];
		$pick = !$d->picked;
		static::SetAllAuctionItemPick($datalist, $containerwidget, $pick);
		return $pick;
	}

	protected static function ReloadAuctionItemPickStatus(&$datalist, &$containerwidget)
	{
		if(empty($datalist))
		{ return; }
		foreach($datalist as $row => $d) {
			$containerwidget->SetCellValue($row, 0, $d->picked);
		}
	}

	protected function showErrorOfItem(&$datalist, $row)
	{
		if( ($row < 0) || ($row >= count($datalist)) )
		{ return; }
		$d = $datalist[$row];
		if($d->have_error())
		{
			$msgcontent = implode(PHP_EOL, $d->error_record);
			$msgbox = new wxMessageDialog($this, $msgcontent, '資料上傳失敗');
			$msgbox->ShowModal();
		}
		/*
		else
		{
			$msgbox = new wxMessageDialog($this, '拍賣商品代碼: '.$d->m_id, '資料上傳成功');
			$msgbox->ShowModal();
			return;
		}
		*/
	}

	protected function loadFieldToEditor(&$datalist, &$containerwidget, $row, $col)
	{
		if( ($row < 0) || ($row >= count($datalist)) )
		{ return; }
		$f = $col - 1;
		if($f == $this->interpreter_instance->get_spec_id_index())
		{ echo "Spec-Id should not be edit.\n"; return; }
		$header = $this->interpreter_instance->csv_header;
		$d = $datalist[$row];
		if($f == $this->interpreter_instance->get_m_id_index())
		{
			$m_id = urlencode(trim($d->m_id));
			if(empty($m_id))
			{ echo "M-ID empty.\n"; return; }
			$msgbox = new wxMessageDialog($this, '即將在瀏覽器中開啟商品頁面，編號: '.strval($m_id)."。\n請確認是否開啟？", '在瀏覽器中開啟商品頁面', wxYES_NO|wxCANCEL);
			if(wxID_YES == $msgbox->ShowModal())
			{
				$target_url = "https://tw.bid.yahoo.com/item/{$m_id}";
				wxLaunchDefaultBrowser($target_url);
			}
			return;
		}
		elseif($this->interpreter_instance->is_inventory_column($f))
		{
			list($specname1, $specname2) = $this->interpreter_instance->get_spec_names($d);
			$edtbox = new InventoryEditor($this, $this->interpreter_instance, $d, $f, $specname1, $specname2, $this);
			if(wxID_OK != $edtbox->ShowModal())
			{ return; }
			$val = $edtbox->GetValue();
		}
		elseif($this->interpreter_instance->is_product_image_column($f))
		{
			$val = $d->csv_field[$f];
			$val = $this->openImageFilePicker($val);
			if(FALSE === $val)
			{ return; }
		}
		else
		{
			$val = $d->csv_field[$f];
			$inputbox = new wxTextEntryDialog($this, '修改欄位值「'.$header[$f].'」', '修改欄位', $val);
			if(wxID_OK != $inputbox->ShowModal())
			{ return; }
			$val = $inputbox->GetValue();
			$val = static::ConvertUIFeedbackString($val);
			$d->csv_field[$f] = $val;
		}
		$containerwidget->SetCellValue($row, $col, $val);
	}

	protected function syncAuctionItemPickStatus()
	{
		static::ReloadAuctionItemPickStatus($this->auction_item_list, $this->dataviewAllContent);
		static::ReloadAuctionItemPickStatus($this->auction_item_successed, $this->dataviewSuccessContent);
		static::ReloadAuctionItemPickStatus($this->auction_item_failed, $this->dataviewFailedContent);
	}

	protected function performUpload($is_verify)
	{
		list($panel, $sizer, $widget, $datalist) = $this->getCurrentDataTable();
		$selected_count = $this->countSelectedItem($datalist, $widget);
		if(0 >= $selected_count)
		{ return; }
		//$upload_mode = ($this->publish_count > 0) ? 0 /* AuctionBridge::AUTO_SWITCH */ : (
		//			(0 == $this->editmode) ? 1 /* AuctionBridge::CREATE_ONLY */ : 2 /* AuctionBridge::UPDATE_ONLY */
		//		);
		$upload_mode = (0 == $this->editmode) ? 1 /* AuctionBridge::CREATE_ONLY */ : 2 /* AuctionBridge::UPDATE_ONLY */;
		$m_id_index = 1 + $this->interpreter_instance->get_m_id_index();
		$item_successed = array();
		$item_failed = array();
		foreach($datalist as $row => $d) {
			if(!$d->picked)
			{ continue; }
			$result = $this->auction_bridge->upload_auction_item($this->interpreter_instance, $d, $is_verify, $upload_mode);
			$resultcolor = ($result) ? $this->COLOR_SUCCESS : $this->COLOR_FAIL;
			$widget->SetCellBackgroundColour($row, 1, $resultcolor);
			if($d->have_error())
			{
				$item_failed[] = $d;
			}
			else
			{
				$widget->SetCellValue($row, $m_id_index, $d->m_id);
				$item_successed[] = $d;
			}
		}
		$this->publish_count++;
		// update other part of UI
		$this->auction_item_successed = empty($item_successed) ? NULL : $item_successed;
		$this->auction_item_failed = empty($item_failed) ? NULL : $item_failed;
		$this->reloadResultGrid();
		$this->syncAuctionItemPickStatus();
		$this->updateMainGridErrorStatus($widget);
		$msgbox = new wxMessageDialog($this, '商品傳送完成: 成功 '.count($item_successed).' 筆，失敗 '.count($item_failed).' 筆。', '商品傳送完成');
		$msgbox->ShowModal();
	}

	protected function clickAuctionItem(&$event, &$datalist, &$widget)
	{
		if(is_null($datalist))
		{ return; }
		$row = $event->GetRow();
		$col = $event->GetCol();
		if(1 == $col)
		{
			$this->showErrorOfItem($datalist, $row);
		}
		elseif(0 == $col)
		{
			static::ToggleAuctionItemPick($datalist, $widget, $row);
		}
		else
		{
			$this->loadFieldToEditor($datalist, $widget, $row, $col);
		}
		$widget->SetGridCursor($row, 1);
	}

	protected function clickAllAuctionItem(&$event, &$datalist, &$widget)
	{
		if(is_null($datalist))
		{ return; }
		$col = $event->GetCol();
		if(0 == $col)
		{
			static::ToggleAllAuctionItemPick($datalist, $widget);
		}
	}

	// *** Handlers for AuctionEditorUI events.

	function onWindowClose( $event ) {
		$appinstance = $this->appinstance;
		$this->appinstance = null;
		$appinstance->closeEditor();
	}

	function onPickedCategoryChanged( $event ) {
		$o = $this->getSelectedCategory();
		if(!$o)
		{ return; }
		elseif($o->is_leaf)
		{
			$this->btnApplyCategory->Enable(TRUE);
			return;
		}
		$this->btnApplyCategory->Enable(FALSE);
		$this->category_data = array();
		$this->category_data[] = $o->parent_node;
		$this->category_data[] = $o;
		$this->appendCategoryItem($o->child_node);
		$this->syncCategoryDataToUI(1);
		if($this->need_notice_category_leaf)
		{
			$this->need_notice_category_leaf = FALSE;
			$msgbox = new wxMessageDialog($this, '您選擇的這個類別下面還有子類別，清單已隨著您的選擇為您更新。'."\n".'必須要選到最末端的類別才可以套用。', '類別選擇');
			$msgbox->ShowModal();
		}
	}

	function doApplyCategory( $event ) {
		$o = $this->getSelectedCategory();
		if(!$o)
		{ return; }
		list($panel, $sizer, $widget, $datalist) = $this->getCurrentDataTable();
		$selected_count = $this->countSelectedItem($datalist, $widget);
		if(0 >= $selected_count)
		{ return; }
		echo "set category to {$selected_count} items.\n";
		$updated_cat_id = $o->category_id;
		foreach($datalist as $row => $d) {
			if(!$d->picked)
			{ continue; }
			$d->set_category($updated_cat_id);
			$widget->SetCellValue($row, 1, $d->csv_field[0]);
		}
	}

	function doResetCategory( $event ) {
		$this->resetCategorySelector();
	}

	function doDataExport( $event ) {
		list($panel, $sizer, $widget, $datalist) = $this->getCurrentDataTable();
		$selected_count = $this->countSelectedItem($datalist, $widget);
		if(0 >= $selected_count)
		{ return; }
		$dialog = new wxFileDialog($this, '儲存檔案...', '', '', 'CSV 檔案 (*.csv)|*.csv', wxFD_SAVE);
		if(wxID_OK != $dialog->ShowModal())
		{ return; }
		$selected_path = $dialog->GetPath();
		if(empty($selected_path))
		{ return; }
		$this->saveCSVFile($selected_path, $datalist);
	}

	function doDataImport( $event ) {
		$dialog = new wxFileDialog($this, '開啟檔案...', '', '', 'CSV 檔案 (*.csv)|*.csv', wxFD_OPEN|wxFD_FILE_MUST_EXIST);
		if(wxID_OK != $dialog->ShowModal())
		{ return; }
		$selected_path = $dialog->GetPath();
		$selected_folder = dirname($selected_path);
		echo "select: {$selected_path} at {$selected_folder}.\n";
		$this->loadCSVFile($selected_path);
	}

	function onAuctionItemClicked( $event ) {
		$this->clickAuctionItem($event, $this->auction_item_list, $this->dataviewAllContent);
	}
	function onAuctionAllClicked( $event ) {
		$this->clickAllAuctionItem($event, $this->auction_item_list, $this->dataviewAllContent);
	}

	function onSuccessedAuctionItemClicked( $event ) {
		$this->clickAuctionItem($event, $this->auction_item_successed, $this->dataviewSuccessContent);
	}
	function onSuccessedAuctionAllClicked( $event ) {
		$this->clickAllAuctionItem($event, $this->auction_item_successed, $this->dataviewSuccessContent);
	}

	function onFailedAuctionItemClicked( $event ) {
		$this->clickAuctionItem($event, $this->auction_item_failed, $this->dataviewFailedContent);
	}
	function onFailedAuctionAllClicked( $event ) {
		$this->clickAllAuctionItem($event, $this->auction_item_failed, $this->dataviewFailedContent);
	}

	function doContentPageChange( $event ) {
		$pg = $this->panelContent->GetSelection();
		if(0 == $pg)
		{
			static::ReloadAuctionItemPickStatus($this->auction_item_list, $this->dataviewAllContent);
		}
		elseif(1 == $pg)
		{
			static::ReloadAuctionItemPickStatus($this->auction_item_successed, $this->dataviewSuccessContent);
		}
		elseif(2 == $pg)
		{
			static::ReloadAuctionItemPickStatus($this->auction_item_failed, $this->dataviewFailedContent);
		}
	}

	function doVerify( $event ) {
		$this->performUpload(TRUE);
	}

	function doPublish( $event ) {
		$this->performUpload(FALSE);
	}

	function __destruct() {
		$this->appinstance = null;
		parent::__destruct();
	}
}

?>
