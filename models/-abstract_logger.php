<?php
#[\AllowDynamicProperties]
class AbstractLogger {
	
	public $file_name;
	public $file_path;
	public $extras;
	
	public static $instance=null;
	
	function __construct($file_name, $extras='') {
		$this->file_name=$file_name;
		$this->file_path=LOG_PATH . $this->file_name;
		$this->extras=$extras;
		if(!file_exists($this->file_path)) $this->Add('File created');
	}
	
	public function SetExtras($extras) {
		$this->extras=$extras;
		return $this;
	}
	
	public function Add($data) {
		if(!file_put_contents($this->file_path, date('Y/m/d H:i:s') . ':' . (empty($this->extras) ? '' : $this->extras . ':') . $data . PHP_EOL, FILE_APPEND | LOCK_EX))
			die('Cannot write log ' . $this->file_path);
		return $this;
	}
	
	public function _Clear() {
		unlink($this->file_path);
	}
	
}