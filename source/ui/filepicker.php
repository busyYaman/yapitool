<?php

class FilePicker extends  wxFileDialog {
	function __construct(&$parent, $base_folder, $picked_filepath=NULL) {
		parent::__construct($parent, '選取影像檔案...', $base_folder, wxEmptyString, 'JPEG 檔案 (*.jpg)|*.jpg|JPEG 檔案 (*.jpeg)|*.jpg|PNG 檔案 (*.png)|*.png');
		$this->base_folder = $base_folder;
		if(empty($picked_filepath))
		{
			foreach(array('photo', 'pic') as $folder) {
				$tgt = $base_folder.DIRECTORY_SEPARATOR.$folder;
				if(is_dir($tgt))
				{
					$this->SetDirectory($tgt);
					break;
				}
			}
		}
		else
		{
			$folder_path = dirname($picked_filepath);
			$picked_filename = basename($picked_filepath);
			$this->SetDirectory($folder_path);
			$this->SetFilename($picked_filename);
		}
	}

	public function GetValue()
	{
		$p = $this->GetPath();
		if(empty($p))
		{ return FALSE; }

		$basepath = explode(DIRECTORY_SEPARATOR, realpath($this->base_folder));
		$targetpath = explode(DIRECTORY_SEPARATOR, realpath($p));
		$relpath = array();

		$i = 0;
		while(isset($basepath[$i]) && isset($targetpath[$i])) {
			if($basepath[$i] != $targetpath[$i])
			{ break; }
			$i++;
		}
		$j = count($basepath) - 1;
		while($i <= $j) {
			if(!empty($basepath[$j]))
			{ $relpath[] = '..'; }
			$j--;
		}
		while(isset($targetpath[$i])) {
			if(!empty($targetpath[$i]))
			{ $relpath[] = $targetpath[$i]; }
			$i++;
		}

		if(('photo' == $relpath[0]) || ('pic' == $relpath[0]))
		{ array_shift($relpath); }
		$this->SetDirectory($this->base_folder);
		return implode(DIRECTORY_SEPARATOR, $relpath);
	}
}

?>
