<?php
#[\AllowDynamicProperties]
class Response {
		
	function __construct($status, $message=null, $data=null, $extra=null) {
		$this->Create($status, $message, $data, $extra);
		$backtrace='';
		foreach(debug_backtrace() as $b) if($b['file']!=__FILE__ && strpos($b['file'], 'functions.php')===false) { $backtrace=$b; break; }
		$this->location=str_replace(APP_PATH, '', $backtrace['file']) . ':' . $backtrace['line'];
	}
	
	public function Create($status, $message, $data=null, $extra=null) {
		if(is_array($status) || is_object($status)) {
			$index=0;
			foreach($status as $k=>$v) {
				if($k=='status') $this->status=$v;
				else if($k=='message') $this->message=$v;
				else if($k=='data') $this->data=$v;
				else if($k=='extra') $this->extra=$v;
				else if($index==0) $this->status=$v;
				else if($index==1) $this->message=$v;
				else if($index==2) $this->data=$v;
				else if($index==3) $this->extra=$v;
				$index++;
			}
		} else {
			$this->status=$status;
			$this->message=$message;
			$this->data=$data;
			$this->extra=$extra;			
		}
		return $this;
	}
	
	public function GetStatus() {
		return $this->status;
	}
	public function SetStatus($status) {
		$this->status=$status;
		return $this;
	}
	
	public function GetMessage() {
		return $this->message;
	}
	public function SetMessage($message) {
		$this->message=$message;
		return $this;
	}
	
	public function GetData() {
		return $this->data;
	}
	public function SetData($data) {
		$this->data=$data;
		return $this;
	}
	
	public function GetExtra() {
		return $this->extra;
	}
	public function SetExtra($extra) {
		$this->extra=$extra;
		return $this;
	}
	
	public function ToJson($echo=0) {
		return ToJson($this, $echo);
	}
}