<?php

class ErrorLogger extends AbstractLogger {
	
	public static $instance=null;
	
	public function __construct() {
		ErrorLogger::$instance=new AbstractLogger('errors.txt', '');
	}
	
	public static function Write($error) {
		if(empty(ErrorLogger::$instance)) new ErrorLogger;
		ErrorLogger::$instance->Add($error . GetBackTrace());
	}
	
	public static function Clear() {
		if(empty(ErrorLogger::$instance)) new ErrorLogger;
		ErrorLogger::$instance->_Clear();
	}
	
	public static function ClearWrite($error) {
		ErrorLogger::Clear();
		ErrorLogger::Write($error);
	}
	
	public static function View() {
		if(empty(ErrorLogger::$instance)) new ErrorLogger;
		diep(file_get_contents(ErrorLogger::$instance->file_path));
	}
	
	public static function Get() {
		if(empty(ErrorLogger::$instance)) new ErrorLogger;
		return file_get_contents(ErrorLogger::$instance->file_path);
	}
	
}