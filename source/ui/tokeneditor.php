<?php
require_once('ui_base.php');

class TokenEditor extends TokenEditorUI {
	function __construct($appinstance, $auction_bridge, $parent=null) {
		parent::__construct($parent);
		$this->appinstance = $appinstance;
		$this->auction_bridge = $auction_bridge;
		$this->load_auth_config();
	}

	protected function load_auth_config()
	{
		list($auth_cfg, $auth_valid) = $this->auction_bridge->load_auth_config();
		$this->text_YID->SetValue($auth_cfg['yid']);
		$this->text_PartnerID->SetValue($auth_cfg['partner_id']);
		$this->text_PartnerSecret->SetValue($auth_cfg['partner_secret']);
		$this->text_SellerID->SetValue($auth_cfg['seller_id']);
	}

	protected function save_auth_config()
	{
		$auth_cfg = array();
		$auth_cfg['yid'] = $this->text_YID->GetValue();
		$auth_cfg['partner_id'] = $this->text_PartnerID->GetValue();
		$auth_cfg['partner_secret'] = $this->text_PartnerSecret->GetValue();
		$auth_cfg['seller_id'] = $this->text_SellerID->GetValue();
		return $this->auction_bridge->save_auth_config($auth_cfg);
	}

	function onWindowClose( $event ) {
		$appinstance = $this->appinstance;
		$this->appinstance = null;
		$appinstance->closeEditor();
	}

	function doTokenSave($event) {
		if($this->save_auth_config())
		{
			$msgbox = new wxMessageDialog($this, '認證資訊寫出完成。', '儲存認證資訊');
		}
		else
		{
			$t = $this->auction_bridge->get_last_error();
			if(empty($t))
			{ $t = '未知的錯誤'; }
			$msgbox = new wxMessageDialog($this, '認證資訊寫出失敗: '.$t, '儲存認證資訊');
		}
		$msgbox->ShowModal();
	}
}

?>
