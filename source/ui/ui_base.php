<?php

/*
* PHP code generated with wxFormBuilder (version Jun  5 2014)
* http://www.wxformbuilder.org/
*
* PLEASE DO "NOT" EDIT THIS FILE!
*/

/*
 * Class BootFrameUI
 */

class BootFrameUI extends wxFrame {
	
	function __construct( $parent=null ){
		parent::__construct ( $parent, wxID_ANY, "Yahoo 拍賣上架工具", wxDefaultPosition, new wxSize( 320,240 ), wxDEFAULT_FRAME_STYLE|wxTAB_TRAVERSAL );
		
		$this->SetSizeHints( wxDefaultSize, wxDefaultSize );
		
		$layoutBoxSizer = new wxBoxSizer( wxVERTICAL );
		
		$this->btnOpenTokenEditor = new wxButton( $this, wxID_ANY, "編輯認證資訊 (Authorization Setup)", wxDefaultPosition, wxDefaultSize, 0 );
		$layoutBoxSizer->Add( $this->btnOpenTokenEditor, 0, wxALL|wxALIGN_RIGHT, 5 );
		
		$this->btnTriggerUpload = new wxButton( $this, wxID_ANY, "大量上傳 (Upload)", wxDefaultPosition, wxDefaultSize, 0 );
		$layoutBoxSizer->Add( $this->btnTriggerUpload, 1, wxALL|wxEXPAND|wxALIGN_CENTER_HORIZONTAL, 5 );
		
		$this->btnTriggerModify = new wxButton( $this, wxID_ANY, "大量修改 (Modify)", wxDefaultPosition, wxDefaultSize, 0 );
		$layoutBoxSizer->Add( $this->btnTriggerModify, 1, wxALL|wxEXPAND|wxALIGN_CENTER_HORIZONTAL, 5 );
		
		
		$this->SetSizer( $layoutBoxSizer );
		$this->Layout();
		
		$this->Centre( wxBOTH );
		
		// Connect Events
		$this->btnOpenTokenEditor->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doTokenSetup") );
		$this->btnTriggerUpload->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doUpload") );
		$this->btnTriggerModify->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doModify") );
	}
	
	
	function __destruct( ){
	}
	
	
	// Virtual event handlers, overide them in your derived class
	function doTokenSetup( $event ){
		$event->Skip();
	}
	
	function doUpload( $event ){
		$event->Skip();
	}
	
	function doModify( $event ){
		$event->Skip();
	}
	
}

/*
 * Class AuctionEditorUI
 */

class AuctionEditorUI extends wxFrame {
	
	function __construct( $parent=null ){
		parent::__construct ( $parent, wxID_ANY, wxEmptyString, wxDefaultPosition, new wxSize( 500,300 ), wxDEFAULT_FRAME_STYLE|wxTAB_TRAVERSAL );
		
		$this->SetSizeHints( wxDefaultSize, wxDefaultSize );
		
		$layoutBoxSizer = new wxBoxSizer( wxVERTICAL );
		
		$panelBatchMetaSelector = new wxBoxSizer( wxHORIZONTAL );
		
		$this->label_CategorySelect = new wxStaticText( $this, wxID_ANY, "Category", wxDefaultPosition, wxDefaultSize, 0 );
		$this->label_CategorySelect->Wrap( -1 );
		$panelBatchMetaSelector->Add( $this->label_CategorySelect, 0, wxALL, 5 );
		
		$CategorySelectorChoices = array();
		$this->CategorySelector = new wxComboBox( $this, wxID_ANY, "Category", wxDefaultPosition, wxDefaultSize, $CategorySelectorChoices, wxCB_READONLY );
		$this->CategorySelector->SetMinSize( new wxSize( 200,-1 ) );
		
		$panelBatchMetaSelector->Add( $this->CategorySelector, 1, wxALL, 5 );
		
		$this->btnApplyCategory = new wxButton( $this, wxID_ANY, "套用 (Apply)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelBatchMetaSelector->Add( $this->btnApplyCategory, 0, wxALL, 5 );
		
		$this->btnClearCategory = new wxButton( $this, wxID_ANY, "重置 (Clear)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelBatchMetaSelector->Add( $this->btnClearCategory, 0, wxALL, 5 );
		
		
		$layoutBoxSizer->Add( $panelBatchMetaSelector, 0, wxEXPAND, 5 );
		
		$panelActionTrigger = new wxBoxSizer( wxHORIZONTAL );
		
		$this->btnExportTrigger = new wxButton( $this, wxID_ANY, "匯出 (Export)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelActionTrigger->Add( $this->btnExportTrigger, 0, wxALL, 5 );
		
		$this->btnImportTrigger = new wxButton( $this, wxID_ANY, "匯入 (Import)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelActionTrigger->Add( $this->btnImportTrigger, 0, wxALL, 5 );
		
		
		$layoutBoxSizer->Add( $panelActionTrigger, 0, wxALIGN_RIGHT, 5 );
		
		$this->panelContent = new wxNotebook( $this, wxID_ANY, wxDefaultPosition, wxDefaultSize, 0 );
		$this->panelAllContent = new wxPanel( $this->panelContent, wxID_ANY, wxDefaultPosition, wxDefaultSize, wxTAB_TRAVERSAL );
		$this->panelContent->AddPage( $this->panelAllContent, "所有項目", false );
		$this->panelSuccessContent = new wxPanel( $this->panelContent, wxID_ANY, wxDefaultPosition, wxDefaultSize, wxTAB_TRAVERSAL );
		$this->panelContent->AddPage( $this->panelSuccessContent, "成功", false );
		$this->panelFailedContent = new wxPanel( $this->panelContent, wxID_ANY, wxDefaultPosition, wxDefaultSize, wxTAB_TRAVERSAL );
		$this->panelContent->AddPage( $this->panelFailedContent, "失敗", false );
		
		$layoutBoxSizer->Add( $this->panelContent, 1, wxEXPAND | wxALL, 5 );
		
		$panelUploadAction = new wxBoxSizer( wxHORIZONTAL );
		
		$this->btnVerifyTrigger = new wxButton( $this, wxID_ANY, "驗證 (Verify)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelUploadAction->Add( $this->btnVerifyTrigger, 0, wxALL, 5 );
		
		$this->btnPublishTrigger = new wxButton( $this, wxID_ANY, "發佈 (Publish)", wxDefaultPosition, wxDefaultSize, 0 );
		$panelUploadAction->Add( $this->btnPublishTrigger, 0, wxALL, 5 );
		
		
		$layoutBoxSizer->Add( $panelUploadAction, 0, wxALIGN_RIGHT, 5 );
		
		
		$this->SetSizer( $layoutBoxSizer );
		$this->Layout();
		
		$this->Centre( wxBOTH );
		
		// Connect Events
		$this->Connect( wxEVT_CLOSE_WINDOW, array($this, "onWindowClose") );
		$this->CategorySelector->Connect( wxEVT_COMMAND_COMBOBOX_SELECTED, array($this, "onPickedCategoryChanged") );
		$this->btnApplyCategory->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doApplyCategory") );
		$this->btnClearCategory->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doResetCategory") );
		$this->btnExportTrigger->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doDataExport") );
		$this->btnImportTrigger->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doDataImport") );
		$this->panelContent->Connect( wxEVT_COMMAND_NOTEBOOK_PAGE_CHANGED, array($this, "doContentPageChange") );
		$this->btnVerifyTrigger->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doVerify") );
		$this->btnPublishTrigger->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doPublish") );
	}
	
	
	function __destruct( ){
	}
	
	
	// Virtual event handlers, overide them in your derived class
	function onWindowClose( $event ){
		$event->Skip();
	}
	
	function onPickedCategoryChanged( $event ){
		$event->Skip();
	}
	
	function doApplyCategory( $event ){
		$event->Skip();
	}
	
	function doResetCategory( $event ){
		$event->Skip();
	}
	
	function doDataExport( $event ){
		$event->Skip();
	}
	
	function doDataImport( $event ){
		$event->Skip();
	}
	
	function doContentPageChange( $event ){
		$event->Skip();
	}
	
	function doVerify( $event ){
		$event->Skip();
	}
	
	function doPublish( $event ){
		$event->Skip();
	}
	
}

/*
 * Class TokenEditorUI
 */

class TokenEditorUI extends wxFrame {
	
	function __construct( $parent=null ){
		parent::__construct ( $parent, wxID_ANY, "認證資訊編輯", wxDefaultPosition, new wxSize( 300,200 ), wxDEFAULT_FRAME_STYLE|wxTAB_TRAVERSAL );
		
		$this->SetSizeHints( wxDefaultSize, wxDefaultSize );
		
		$fgSizer2 = new wxFlexGridSizer( 0, 2, 0, 0 );
		$fgSizer2->AddGrowableCol( 1 );
		$fgSizer2->AddGrowableRow( 4 );
		$fgSizer2->SetFlexibleDirection( wxBOTH );
		$fgSizer2->SetNonFlexibleGrowMode( wxFLEX_GROWMODE_SPECIFIED );
		
		$this->m_staticText7 = new wxStaticText( $this, wxID_ANY, "YID", wxDefaultPosition, wxDefaultSize, 0 );
		$this->m_staticText7->Wrap( -1 );
		$fgSizer2->Add( $this->m_staticText7, 0, wxALL|wxALIGN_RIGHT, 5 );
		
		$this->text_YID = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_YID, 1, wxALL|wxEXPAND, 5 );
		
		$this->m_staticText8 = new wxStaticText( $this, wxID_ANY, "Partner ID", wxDefaultPosition, wxDefaultSize, 0 );
		$this->m_staticText8->Wrap( -1 );
		$fgSizer2->Add( $this->m_staticText8, 0, wxALL|wxALIGN_RIGHT, 5 );
		
		$this->text_PartnerID = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_PartnerID, 1, wxALL|wxEXPAND, 5 );
		
		$this->m_staticText9 = new wxStaticText( $this, wxID_ANY, "Partner Secret", wxDefaultPosition, wxDefaultSize, 0 );
		$this->m_staticText9->Wrap( -1 );
		$fgSizer2->Add( $this->m_staticText9, 0, wxALL|wxALIGN_RIGHT, 5 );
		
		$this->text_PartnerSecret = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_PartnerSecret, 1, wxALL|wxEXPAND, 5 );
		
		$this->m_staticText10 = new wxStaticText( $this, wxID_ANY, "Seller ID", wxDefaultPosition, wxDefaultSize, 0 );
		$this->m_staticText10->Wrap( -1 );
		$fgSizer2->Add( $this->m_staticText10, 0, wxALL|wxALIGN_RIGHT, 5 );
		
		$this->text_SellerID = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_SellerID, 1, wxALL|wxEXPAND, 5 );
		
		
		$fgSizer2->Add( 0, 0, 1, wxEXPAND, 5, null );
		
		
		$fgSizer2->Add( 0, 0, 1, wxEXPAND, 5, null );
		
		
		$fgSizer2->Add( 0, 0, 1, wxEXPAND, 5, null );
		
		$this->btn_Save = new wxButton( $this, wxID_ANY, "確定儲存 (Save)", wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->btn_Save, 0, wxALL|wxALIGN_RIGHT|wxALIGN_BOTTOM, 5 );
		
		
		$this->SetSizer( $fgSizer2 );
		$this->Layout();
		
		$this->Centre( wxBOTH );
		
		// Connect Events
		$this->Connect( wxEVT_CLOSE_WINDOW, array($this, "onWindowClose") );
		$this->btn_Save->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "doTokenSave") );
	}
	
	
	function __destruct( ){
	}
	
	
	// Virtual event handlers, overide them in your derived class
	function onWindowClose( $event ){
		$event->Skip();
	}
	
	function doTokenSave( $event ){
		$event->Skip();
	}
	
}

/*
 * Class InventoryEditorUI
 */

class InventoryEditorUI extends wxDialog {
	
	function __construct( $parent=null ){
		parent::__construct( $parent, wxID_ANY, "型號值與庫存編輯", wxDefaultPosition, new wxSize( 360,310 ), wxDEFAULT_DIALOG_STYLE );
		
		$this->SetSizeHints( wxDefaultSize, wxDefaultSize );
		
		$fgSizer2 = new wxFlexGridSizer( 0, 2, 0, 0 );
		$fgSizer2->AddGrowableCol( 1 );
		$fgSizer2->AddGrowableRow( 8 );
		$fgSizer2->SetFlexibleDirection( wxBOTH );
		$fgSizer2->SetNonFlexibleGrowMode( wxFLEX_GROWMODE_SPECIFIED );
		
		$this->lbl_SpecValue1 = new wxStaticText( $this, wxID_ANY, "規格 1", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_SpecValue1->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_SpecValue1, 0, wxALL, 5 );
		
		$this->text_SpecValue1 = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_SpecValue1, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_SpecValue2 = new wxStaticText( $this, wxID_ANY, "規格 2", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_SpecValue2->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_SpecValue2, 0, wxALL, 5 );
		
		$this->text_SpecValue2 = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_SpecValue2, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_Stock = new wxStaticText( $this, wxID_ANY, "庫存量", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_Stock->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_Stock, 0, wxALL, 5 );
		
		$this->text_Stock = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_Stock, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_CustomId1 = new wxStaticText( $this, wxID_ANY, "貨號 1", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_CustomId1->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_CustomId1, 0, wxALL, 5 );
		
		$this->text_CustomId1 = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_CustomId1, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_CustomId2 = new wxStaticText( $this, wxID_ANY, "貨號 2", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_CustomId2->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_CustomId2, 0, wxALL, 5 );
		
		$this->text_CustomId2 = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_CustomId2, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_Barcode = new wxStaticText( $this, wxID_ANY, "商品條碼", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_Barcode->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_Barcode, 0, wxALL, 5 );
		
		$this->text_Barcode = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_Barcode, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_PicSmall = new wxStaticText( $this, wxID_ANY, "規格小圖", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_PicSmall->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_PicSmall, 0, wxALL, 5 );
		
		$this->text_PicSmall = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_PicSmall, 1, wxALL|wxEXPAND, 5 );
		
		$this->lbl_PicLarge = new wxStaticText( $this, wxID_ANY, "規格大圖", wxDefaultPosition, wxDefaultSize, 0 );
		$this->lbl_PicLarge->Wrap( -1 );
		$fgSizer2->Add( $this->lbl_PicLarge, 0, wxALL, 5 );
		
		$this->text_PicLarge = new wxTextCtrl( $this, wxID_ANY, wxEmptyString, wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->text_PicLarge, 1, wxALL|wxEXPAND, 5 );
		
		
		$fgSizer2->Add( 0, 0, 1, wxEXPAND, 5, null );
		
		$this->btn_Save = new wxButton( $this, wxID_ANY, "確定 (OK)", wxDefaultPosition, wxDefaultSize, 0 );
		$fgSizer2->Add( $this->btn_Save, 0, wxALL|wxALIGN_RIGHT|wxALIGN_BOTTOM, 5 );
		
		
		$this->SetSizer( $fgSizer2 );
		$this->Layout();
		
		$this->Centre( wxBOTH );
		
		// Connect Events
		$this->Connect( wxEVT_CLOSE_WINDOW, array($this, "onWindowClose") );
		$this->text_PicSmall->Connect( wxEVT_LEFT_DCLICK, array($this, "on_FocusPicSmall") );
		$this->text_PicSmall->Connect( wxEVT_SET_FOCUS, array($this, "on_FocusPicSmall") );
		$this->text_PicLarge->Connect( wxEVT_LEFT_DCLICK, array($this, "on_FocusPicLarge") );
		$this->text_PicLarge->Connect( wxEVT_SET_FOCUS, array($this, "on_FocusPicLarge") );
		$this->btn_Save->Connect( wxEVT_COMMAND_BUTTON_CLICKED, array($this, "do_Save") );
	}
	
	
	function __destruct( ){
	}
	
	
	// Virtual event handlers, overide them in your derived class
	function onWindowClose( $event ){
		$event->Skip();
	}
	
	function on_FocusPicSmall( $event ){
		$event->Skip();
	}
	
	
	function on_FocusPicLarge( $event ){
		$event->Skip();
	}
	
	
	function do_Save( $event ){
		$event->Skip();
	}
	
}

?>
