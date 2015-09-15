<?php
require_once('ui_base.php');
require_once('auctioneditor.php');
require_once('tokeneditor.php');

class BootFrame extends BootFrameUI {
	function __construct($appinstance, $parent=null) {
		parent::__construct($parent);
		$this->appinstance = $appinstance;
		$this->editor = null;
		$this->auction_bridge = null;
	}

	public function openEditor($editmode) {
		if(!is_null($this->editor))
		{
			echo "Editor existed !\n";
			return;
		}
		$this->disableButtons();
		$this->editor = new AuctionEditor($this->appinstance, $this->auction_bridge, $editmode, $this);
		$this->editor->Show();
		$this->Hide();
	}

	public function closeEditor() {
		if(is_null($this->editor))
		{
			echo "Editor not found !\n";
			return;
		}
		$this->editor->Hide();
		$this->editor->Destroy();
		$this->editor = null;
		$this->Show();
		$this->enableButtons();
	}

	public function setAuctionBridge($auction_bridge)
	{
		$this->auction_bridge = $auction_bridge;
		if(is_null($this->auction_bridge))
		{
			$this->SetTitle('(正在初始化中...) Yahoo 拍賣上架工具');
			$this->disableButtons();
		}
		else
		{
			$err_msgcontent = $this->auction_bridge->get_last_error();
			if(is_null($err_msgcontent))
			{
				$this->SetTitle('Yahoo 拍賣上架工具');
				$this->enableButtons();
			}
			else
			{
				$this->SetTitle('(初始化失敗) Yahoo 拍賣上架工具');
				$msgbox = new wxMessageDialog($this, $err_msgcontent, '初始化失敗');
				$msgbox->ShowModal();
			}
		}
	}

	function disableButtons() {
		$this->btnTriggerUpload->Disable();
		$this->btnTriggerModify->Disable();
	}

	function enableButtons() {
		if(!is_null($this->auction_bridge))
		{
			list($auth_cfg, $auth_valid) = $this->auction_bridge->load_auth_config();
			if(!$auth_valid)
			{
				$this->disableButtons();
				return;
			}
		}
		$this->btnTriggerUpload->Enable();
		$this->btnTriggerModify->Enable();
	}

	// Handlers for BootFrameUI events.
	function doTokenSetup($event) {
		if(!is_null($this->editor))
		{
			echo "Other Editor existed !\n";
			return;
		}
		$this->disableButtons();
		$this->editor = new TokenEditor($this->appinstance, $this->auction_bridge, $this);
		$this->editor->Show();
		$this->Hide();
	}

	function doUpload( $event ) {
		$this->openEditor(0);
	}

	function doModify( $event ) {
		$this->openEditor(1);
	}
}

?>
