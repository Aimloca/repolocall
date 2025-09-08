<?

// DEBUGGING
	function cmnt(...$var) { echo('<!-- ' . print_r([ 'position' => debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line'], 'data' => $var], true) . ' -->'); }
	function diep(...$var) { die(print_r([ 'position' => debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line'], 'data' => $var], true)); }
	function diej(...$var) { die(json_encode([ 'position' => debug_backtrace()[0]['file'] . ':' . debug_backtrace()[0]['line'], 'data' => $var], JSON_PRETTY_PRINT)); }
	function diejj(...$var) { die(json_encode($var, JSON_PRETTY_PRINT)); }
	function diepp($var) { echo '<pre>'; diep($var); }
	function Dump($object) { return print_r($object); }
	function DumpX($object) { die(print_r($object, true)); }
	function DumpPre($object) { return '<pre>' . print_r($object, true) . '</pre>'; }
	function DumpPreX($object) { die('<pre>' . print_r($object, true) . '</pre>'); }
	function ViewErrorLog() { ErrorLogger::View(); }
	function AddToLog($text) { file_put_contents(LOG_PATH . 'log.txt', date('Y-m-d H:i:s') . ":{$text}\n", FILE_APPEND); }
	function AddToFile($file, $text) { file_put_contents($file, $text . (!empty($text) && substr($text, -1)!="\n" ? "\n" : ""), FILE_APPEND); }
	function AddToRequests($text) { AddToFile(REQUESTS_LOG_FILE, date('Y-m-d H:i:s') . ":{$text}\n", FILE_APPEND); }
	function GetBackTrace() { $e=new Exception(); $trace = explode("\n", $e->getTraceAsString()); $trace = array_reverse($trace); array_shift($trace);  array_pop($trace);  $length = count($trace); $result = ''; for ($i = 0; $i < $length; $i++) { $result.="\n\t\t " . ($i + 1)  . ' ' . substr($trace[$i], strpos($trace[$i], ' '));  } return "\t" . $result; }
	function IsAdam() { return Session::User() && Session::User()->user_name=='adamioan'; }
	function IsAdamIp() { return in_array(GetClientIp(), ['37.6.99.184']); }
	function IsMentalIp() { return in_array(GetClientIp(), ['83.235.189.212']); }
	function AdamLog($text) { AddToFile(LOG_PATH . 'adam.txt', is_string($text) ? $text : print_r($text, true)); }

// NETWORK
	function BaseUrl() { return (FORCE_HTTPS ? 'https' : GetProtocol()) . ':' . BASE_URL; }
	function ImagesUrl() { return (FORCE_HTTPS ? 'https' : GetProtocol()) . ':' . IMAGES_URL; }
	function ImagesDataUrl() { return (FORCE_HTTPS ? 'https' : GetProtocol()) . ':' . IMAGES_DATA_URL; }
	function ValidateEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
	function Redirect($url='') {  if(empty($url))  header('Location: ' . BaseUrl()); else  header('Location: ' . $url); exit; }
	function GetProtocol($url='') { return IsHttps($url) ? 'https' : 'http'; }
	function IsHttps($url) { if(empty($url)) return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443; else return StartsWith(strtolower($url), 'https'); }
	function GetClientIp() { return isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) ? (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']) : ''; }
	function AdminCommand($command) {
		if(substr($command, -1)!="\n") $command.="\n";
		if(!$socket=socket_create(AF_INET, SOCK_STREAM, 0)) return new Response(false, 'Cannot create socket');
		if(!$result=socket_connect($socket, '10.1.0.250', 9000)) return new Response(false, 'Could not connect to admin');
		if(!socket_write($socket, $command, strlen($command))) return new Response(false, 'Could not send data to admin');
		if(!$result=socket_read($socket, 1024)) return new Response(false, 'Could not read admin response');
		socket_close($socket);
		AddToLog("Command: {$command} - Response: {$result}");
		if(empty($result)) return new Response(false, 'Admin sent empty response');
		if(substr($result, -1)=="\n") $result=substr($result, 0, strlen($result)-1);
		if(substr($result, -1)=="\r") $result=substr($result, 0, strlen($result)-1);
		$result=ToUtfR($result);
		return new Response(true, 'OK', $result);
	}

	// Shutdown function
function ShutdownFunction() {
	// Check fatal error
	CheckFatalError();
	// Check execution time
	CheckExecutionTime();
}

// Check execution time
function CheckExecutionTime() {
	global $argv;
	if(empty($_SERVER['REQUEST_TIME_FLOAT'])) return;
	$duration=round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
	if($duration>MAX_EXECUTION_TIME_ERROR) {
		$source=isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : print_r(debug_backtrace()[0], true);
		$script=isset($_SERVER['REQUEST_URI']) ? "$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" : (isset($argv[0]) ? $argv[0] : (isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : 'UNKNOWN'));
		@file_put_contents(LOG_PATH . '/slow_scripts.txt', getmypid() . "\t$source\t$script\tStarted: " . date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME_FLOAT']) . " - Duration: $duration\n", FILE_APPEND);
	}
}

// Check fatal error
function CheckFatalError() {
	$fatal_errors = [
        E_ERROR,
        E_USER_ERROR,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_PARSE,
        E_RECOVERABLE_ERROR
    ];
	$error=error_get_last();
    if($error!==NULL && in_array($error['type'], $fatal_errors)) {
		$fatal=[
			'file' => $error['file'],
			'line' => $error['line'],
			'type' => $error['type'],
			'message' => $error['message'],
			'trace' => debug_backtrace(),
		];
		SendEmail('adamioan@gmail.com', 'Order & Pay Fatal Error', "<pre>ERROR: " . print_r($fatal, true) . "<br />\nREQUEST: " . print_r($_REQUEST, true) . "<br />\n<br />\n</pre>");
		@file_put_contents(LOG_PATH . 'errors.txt', date('Y-m-d H:i:s') . ': ' . print_r($fatal, true), FILE_APPEND);
    }
}

register_shutdown_function('ShutdownFunction');

// CUSTOM TABLE
	function CreateCustomTable($var_name, $element_id, $page_controller='', $params=[]) {
		$can_add=!empty($params['can_add']);
		$can_edit=!empty($params['can_edit']);
		$can_delete=!empty($params['can_delete']);
		$ret="{$var_name}=new CustomTable(" .
							"'{$element_id}', " .
							"'\"custom_table_strings\"', " .
							"'" . BASE_URL . "', " .
							"'" . API_URL . "', " .
							"'{$page_controller}', " .
							($can_add ? 'true' : 'false') . ", " .
							($can_delete ? 'true' : 'false') .
						");
		";
		if(!empty($params['below_title'])) $ret.="{$var_name}.BelowTitle('" . addslashes($params['below_title']) . "');\n";
		return $ret;
	}

// AUTOGEN
	function AutogenIncludes() {
		$app_models='';

		$out='<? ' . PHP_EOL . '// This file is autogenerated. Any changes will be overwritten.' . PHP_EOL . PHP_EOL;

		// Set directories
		$directories=[
			'HELPERS_PATH' => HELPERS_PATH,
			'MODELS_PATH' => MODELS_PATH,
			'CONTROLLERS_PATH' => CONTROLLERS_PATH,
			'VIEWS_PATH' => VIEWS_PATH
		];

		// Search in directories
		foreach($directories as $dir_name=>$dir_path) {
			$out.="// {$dir_name}\n";
			$resources=glob($dir_path . '*.php');
			foreach($resources as $resource) {
				$tmp=explode('/', $resource);
				$resource=$tmp[count($tmp)-1];
				if($dir_name=='VIEWS_PATH' && substr($resource, 0, 5)!='view_') continue;
				$out.="include_once {$dir_name} . '{$resource}';\n";
				if($dir_name=='MODELS_PATH') $app_models.=($app_models=='' ? '' : ', ') . "'" . str_replace('.php', '', $resource) . "'";
			}
			$out.="\n";
		}

		if(!empty($app_models)) $out.="\n\ndefine('APP_MODELS', [{$app_models}]);\n";

		// Create file
		if(!file_put_contents(AUTOGEN_PATH . 'includes.php', $out)) {
			ErrorLogger::Write('Cannot create autogen includes ' . AUTOGEN_PATH . 'includes.php');
			return false;
		}
		return true;
	}

	function SortPeriodByStartTime($a, $b) {
		return strcmp($a->from, $b->from);
	}

	function AutogenJsStrings() {
		$rows=DB::Query('SELECT * FROM STRINGS ORDER BY id;');
		if(!$rows) return;
		$strings=[];
		foreach($rows as $row) {
			$row_id=$row['id'];
			unset($row['id']);
			unset($row['date_created']);
			$strings[$row_id]=$row;
		}
		$out='const APP_STRINGS=' . json_encode($strings, JSON_PRETTY_PRINT) . ';';
		if(strlen($out)<20) {
			ErrorLogger::Write('SaveStrings error. Cannot encode strings to JSON');
			return false;
		}
		if(!file_put_contents(JS_PATH . 'autogen_strings.js', $out)) {
			ErrorLogger::Write('SaveStrings error. Cannot write to ' . JS_PATH . 'autogen_strings.js');
			return false;
		}
		return true;
	}

	function AutogenJsTables() {
		$out="const DB_TABLES=" . json_encode(DB_TABLES) . ";\n";
		$prefixes=['order_', 'product_', 'spec_', 'sale_document'];
		$all_fields=[];
		$boolean_fields=['has_products', 'has_subcategories', 'ordered'];
		$int_fields=['row_id', 'table_id', 'order_id'];
		$float_fields=['unit_quantity', 'order_spec_price'];
		// Add lower table names plus _id into int fields
		foreach(DB_TABLES as $table_name=>$table) if(!in_array(strtolower($table_name) . '_id', $int_fields)) $int_fields[]=strtolower($table_name) . '_id';
		// Loop through tables
		foreach(DB_TABLES as $table_name=>$table) {
			foreach($table['fields'] as $field_name=>$field) {
				if(!in_array($field_name, $all_fields)) $all_fields[]=$field_name;
				if($field['int_type']=='tinyint' && !in_array($field_name, $boolean_fields)) {
					$boolean_fields[]=$field_name;
					foreach($prefixes as $pre) if(strpos($field_name, $pre)===false && !in_array("{$pre}{$field_name}", $all_fields) && !in_array("{$pre}{$field_name}", $int_fields)) $int_fields[]="{$pre}{$field_name}";
				} else if($field['number_type']=='int' && !in_array($field_name, $int_fields)) {
					$int_fields[]=$field_name;
					foreach($prefixes as $pre) if(strpos($field_name, $pre)===false && !in_array("{$pre}{$field_name}", $all_fields) && !in_array("{$pre}{$field_name}", $int_fields)) $int_fields[]="{$pre}{$field_name}";
				} else if($field['number_type']=='float' && !in_array($field_name, $float_fields)) {
					$float_fields[]=$field_name;
					foreach($prefixes as $pre) if(strpos($field_name, $pre)===false && !in_array("{$pre}{$field_name}", $all_fields) && !in_array("{$pre}{$field_name}", $float_fields)) $float_fields[]="{$pre}{$field_name}";
				}
			}
		}
		$out.="const BOOLEAN_FIELDS=" . json_encode($boolean_fields) . ";\n";
		$out.="const INT_FIELDS=" . json_encode($int_fields) . ";\n";
		$out.="const FLOAT_FIELDS=" . json_encode($float_fields) . ";\n";
		if(!file_put_contents(JS_PATH . 'autogen_db_tables.js', $out)) {
			ErrorLogger::Write('AutogenJsTables error. Cannot write to ' . JS_PATH . 'autogen_db_tables.js');
			return false;
		}
		return true;
	}

	function AutogenJsModels($obfuscate=false) {
		$out='';
		$models_js=glob(APP_PATH . 'js/*.js');
		if($models_js) foreach($models_js as $model_js) {
			$source=file_get_contents($model_js);
			foreach(explode("\n", $source) as $line_no=>$line) if(strpos($line, '//')!==false) {
				ErrorLogger::Write("AutogenJsModels error. File {$model_js} contains comments (//) in line " . ($line_no+1) . " and will cause error on obfuscation");
				return false;
			}
			$out.="{$source}\n\n\n";
		}
		if($obfuscate) {
			include_once HELPERS_PATH . 'obfuscator.php';
			$out=JSObfuscator::ObfuscateJS($out);
		}
		if(!file_put_contents(JS_PATH . 'autogen_models.js', $out)) {
			ErrorLogger::Write('AutogenJsModels error. Cannot write to ' . JS_PATH . 'autogen_models.js');
			return false;
		}
		return true;
	}

	function AutogenJsConstants($obfuscate=false) {
		$exclude_constants=['MAIL_USER'];
		$out='';
		foreach(get_defined_constants(true)['user'] as $key=>$value) {
			if(substr($key, 0, 3)=='DB_') continue;
			if(substr($key, 0, 5)=='AADE_') continue;
			if(substr($key, 0, 10)=='EVERY_PAY_') continue;
			if(substr($key, -4)=='_KEY') continue;
			if(substr($key, -5)=='_PATH') continue;
			if(substr($key, -5)=='_FILE') continue;
			if(in_array($key, $exclude_constants)) continue;
			$out_value=is_array($value) ? json_encode($value) : "'{$value}'";
			$out.="window.{$key}={$out_value};\n";
		}
		if($obfuscate) {
			include_once HELPERS_PATH . 'obfuscator.php';
			$out=JSObfuscator::ObfuscateJS($out);
		}
		if(!file_put_contents(JS_PATH . 'autogen_constants.js', $out)) {
			ErrorLogger::Write('AutogenJsModels error. Cannot write to ' . JS_PATH . 'autogen_constants.js');
			return false;
		}
		return true;
	}

	function AutogenDBTables() {
		$default_value_override=[
			'DEPARTMENT.form_header' => file_exists(FORMS_PATH . 'department.form/header.html') ? file_get_contents(FORMS_PATH . 'department.form/header.html') : '',
			'DEPARTMENT.form_products' => file_exists(FORMS_PATH . 'department.form/products.html') ? file_get_contents(FORMS_PATH . 'department.form/products.html') : '',
			'SALE_SERIES.form_header' => file_exists(FORMS_PATH . 'sale_series.form/header.html') ? file_get_contents(FORMS_PATH . 'sale_series.form/header.html') : '',
			'SALE_SERIES.form_products' => file_exists(FORMS_PATH . 'sale_series.form/products.html') ? file_get_contents(FORMS_PATH . 'sale_series.form/products.html') : '',
			'SALE_SERIES.form_footer' => file_exists(FORMS_PATH . 'sale_series.form/footer.html') ? file_get_contents(FORMS_PATH . 'sale_series.form/footer.html') : '',
		];

		$tables_counter=0;
		$fields_counter=0;
		$out='<? ' . PHP_EOL . '// This file is autogenerated. Any changes will be overwritten.' . PHP_EOL . PHP_EOL . 'define(\'DB_TABLES\', [ ';
		if(DB_ENGINE=='mysql') {

			// Get database schema
			$res=DB::Query("SELECT table_name FROM information_schema.tables WHERE table_schema ='" . DB_NAME . "' ORDER BY table_name;");
			if(empty($res)) die('Cannot get database information schema.');

			// Loop through tables
			$tables=[];
			foreach($res as $row_table) {
				$multilang_fields=[];
				$image_fields=[];
				$tables_counter++;
				$table=$row_table['table_name'];
				$out.=	"\n\t'{$table}' => [\n" .
						"\t\t'id' => '{$table}',\n" .
						"\t\t'name' => '{$table}',\n" .
						"\t\t'name_singular' => '{$table}',\n";

				// Get primary key
				$sql="SHOW KEYS FROM {$table} WHERE Key_name='PRIMARY';";
				$primary=DB::Query($sql);
				$primary_key=$primary ? $primary[0]['Column_name'] : '';
				$out.="\t\t'primary_key' => '{$primary_key}',\n";
				$out.="\t\t'fields' => [\n";
				// Get fields
				$fields=DB::Query("SHOW FULL COLUMNS FROM {$table};");
				if($fields) foreach($fields as $field) {
					$fields_counter++;
					$field['number_type']='';
					$field['int_type']='';
					if(in_array($field['Field'], ['email', 'contact_email'])) $field['data_type']='email';
					else if(in_array($field['Field'], ['phone', 'contact_phone', 'mobile', 'contact_mobile', 'fax', 'contact_phone'])) $field['data_type']='phone';
					else if($field['Field']=='pass') $field['data_type']='password';
					else if(strpos($field['Type'], 'varchar')!==false) $field['data_type']='text_limited';
					else if(strpos($field['Type'], 'text')!==false) $field['data_type']='text';
					else if(strpos($field['Type'], 'datetime')!==false) $field['data_type']='datetime';
					else if(strpos($field['Type'], 'timestamp')!==false) $field['data_type']='datetime';
					else if(strpos($field['Type'], 'time')!==false) $field['data_type']='time';
					else if(strpos($field['Type'], 'tinyint')!==false) { $field['data_type']=Strings::StartsWith($field['Field'], ['image', 'icon', 'logo', 'picture']) ? 'image' : 'check'; $field['number_type']='int'; $field['int_type']='tinyint'; }
					else if(strpos($field['Type'], 'int')!==false) { $field['data_type']='number'; $field['number_type']='int'; $field['int_type']='int'; }
					else if(strpos($field['Type'], 'decimal')!==false) { $field['data_type']='number'; $field['number_type']='float'; }
					else $field['data_type']='unknown';
					if(in_array(substr($field['Field'], -3), LANGUAGES_EXTENSIONS)) $multilang_fields[]=$field['Field'];
					if($field['data_type']=='image') { $image_fields[]=$field['Field']; $field['number_type']='int'; }
					$out.="\t\t\t'{$field['Field']}' => [ 'id' => '{$field['Field']}', 'name_en' => '" . Strings::Get("{$table}.{$field['Field']}", 'en'). "', 'name_gr' => '" . Strings::Get("{$table}.{$field['Field']}", 'gr'). "', 'name_ru' => '" . Strings::Get("{$table}.{$field['Field']}", 'ru'). "', 'type' => '{$field['data_type']}', 'number_type' => '{$field['number_type']}', 'int_type' => '{$field['int_type']}', 'nullable' => " . ($field['Null']=='YES' ? '1' : '0') . ", 'default' => " . (isset($default_value_override["{$table}.{$field['Field']}"]) ? "'" . str_replace("'", "\'", $default_value_override["{$table}.{$field['Field']}"]) . "'" : (is_null($field['Default']) ? 'null' : ($field['Default']=='current_timestamp()' ? "''" : "'" . addslashes($field['Default']) . "'"))) . ", 'is_primary' => " . ($primary_key==$field['Field'] ? 1 : 0) . ", 'max_length' => " . ($field['data_type']=='text_limited' ? Strings::KeepOnlyNumbers(explode('(', $field['Type'])[1]) : '0') . ", 'multilanguage' => " . (in_array(substr($field['Field'], -3), LANGUAGES_EXTENSIONS) ? 1 : 0) . " ],\n";
				}
				$out.="\t\t],\n\t\t'multilang_fields' => [\n";
				if($multilang_fields) foreach($multilang_fields as $field) $out.="\t\t\t'{$field}',\n";
				$out.="\t\t],\n\t\t'image_fields' => [\n";
				if($image_fields) foreach($image_fields as $field) $out.="\t\t\t'{$field}',\n";
				$out.="\t\t],\n\t],\n";
			}
			$out.="]);\n";
		}
		if(!file_put_contents(AUTOGEN_PATH . 'db_tables.php', $out)) {
			ErrorLogger::Write('Cannot create autogen db tables ' . AUTOGEN_PATH . 'db_tables.php');
			return false;
		}
		return true;
	}

// JSON
	function DieJsonResponse($status, $message='', $data=null, $extra=null) { DieToJson(new Response($status, $message, $data, $extra), true); }
	function DieToJson($object) { if(!headers_sent()) header('Content-Type: application/json; charset=utf-8'); ToJson($object, true); exit; }
	function ToJson($object, $echo=0) { $json=json_encode($object, JSON_UNESCAPED_UNICODE); if(!empty($object) && empty($json)) return GetInvalidData($object);		 if($echo==1) echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); return $json; }
	function GetInvalidData($d, $collection='', $exit=false) { if(is_array($d)) foreach($d as $k => $v) GetInvalidData($v, $d); else if(is_object($d)) foreach ($d as $k => $v)  GetInvalidData($v, $d); else if(!empty($d) && empty(json_encode($d, JSON_UNESCAPED_UNICODE)))  if($exit) diep(ToJson(new Response(0, 'Invalid data in ' . ForceUTF8($d) . PHP_EOL . ForceUTF8(print_r($collection, true)), true), true)); else  return ToJson(new Response(0, 'Invalid data in ' . ForceUTF8($d) . PHP_EOL . ForceUTF8(print_r($collection, true)), true), true); return ''; }

// PAGES
	function PrintPageHead($title='', $meta_link='', $css_link='', $java_link='', $css_script='', $java_script='') {
		echo GetPageHead($title, $meta_link, $css_link, $java_link, $css_script, $java_script);
	}

	function GetPageHead($title='', $meta_link='', $css_link='', $java_link='', $css_script='', $java_script='') {
		$html='<!DOCTYPE html>
			<html lang="gr">
			<head>
			  <title>' . APP_NAME . (empty($title) ? '' : ' - ' . $title) . '</title>
			  <meta charset="utf-8" />
			  <meta name="viewport" content="width=device-width, initial-scale=1" />
			  <!--###META_LINK###-->
			  <link rel="SHORTCUT ICON" href="' . IMAGES_URL . 'favicon.ico" />
			  <!-- Bootstrap CSS -->
			  <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
			  <!-- DataTables CSS -->
			  <link rel="stylesheet" type="text/css" href="' . CSS_URL . 'jquery-ui.1.14.0.css" />
			  <link rel="stylesheet" type="text/css" href="' . CSS_URL . 'jquery.dataTables.min.css" />
			  <link rel="stylesheet" type="text/css" href="' . CSS_URL . 'buttons.dataTables.min.css" />
			  <link rel="stylesheet" type="text/css" href="' . CSS_URL . 'chosen.min.css" />
			  <!-- App CSS -->
			  <link rel="stylesheet" href="' . CSS_URL . 'style.css" />
			  <link rel="stylesheet" href="' . CSS_URL . 'custom_table.css" />
			  <!-- Additional CSS link -->
			  <!--###CSS_LINK###-->
			  <!-- Additional CSS script -->
			  <!--###CSS_SCRIPT###-->

			  <!-- JQuery JS -->
			  <!--<script src="' . JS_URL . 'jquery-3.3.1.js"></script>-->
			  <script src="' . JS_URL . 'jquery-3.7.1.min.js"></script>
			  <script src="' . JS_URL . 'jquery-ui.1.14.0.min.js"></script>
			  <script src="' . JS_URL . 'jquery.cookie.min.js"></script>
			  <!-- Bootstrap JS -->
			  <script src="' . JS_URL . 'bootstrap.min.js"></script>
			  <script src="' . JS_URL . 'jspdf.min.js"></script>
			  <script src="' . JS_URL . 'html2canvas.min.js"></script>
			  <script src="' . JS_URL . 'chosen.jquery.min.js"></script>
			  <!-- App JS -->
			  ' . (file_exists(BASE_PATH . '/assets/js/autogen_constants.js') ? '<script src="' . JS_URL . 'autogen_constants.js"></script>' : '') . '
			  ' . (file_exists(BASE_PATH . '/assets/js/autogen_strings.js') ? '<script src="' . JS_URL . 'autogen_strings.js"></script>' : '') . '
			  ' . (file_exists(BASE_PATH . '/assets/js/autogen_db_tables.js') ? '<script src="' . JS_URL . 'autogen_db_tables.js"></script>' : '') . '
			  ' . (file_exists(BASE_PATH . '/assets/js/autogen_models.js') ? '<script src="' . JS_URL . 'autogen_models.js"></script>' : '') . '
			  <script src="' . JS_URL . 'scripts.js"></script>
			  <script src="' . JS_URL . 'custom_table.js"></script>
			  <!-- Additional JS link -->
			  <!--###JAVA_LINK###-->
			  <!-- Additional JS script -->
			  <!--###JAVA_SCRIPT###-->
              <script src="' . JS_URL . 'tinymce/js/tinymce/tinymce.min.js"></script>
              <link href="' . JS_URL . 'froala-editor/css/froala_editor.pkgd.min.css" rel="stylesheet" type="text/css" />
              <link rel="stylesheet" href="' . JS_URL . 'trumbowyg/dist/ui/trumbowyg.min.css">
              <script src="' . JS_URL . 'trumbowyg/dist/trumbowyg.min.js"></script>
			</head>';

		$html=str_replace('<title>###PAGE_TITLE###</title>', '<title>' . $title . '</title>', $html);
		$html=str_replace('<!--###META_LINK###-->', $meta_link, $html);
		$html=str_replace('<!--###CSS_LINK###-->', $css_link, $html);
		$html=str_replace('<!--###JAVA_LINK###-->', $java_link, $html);
		$html=str_replace('<!--###CSS_SCRIPT###-->', $css_script, $html);
		$html=str_replace('<!--###JAVA_SCRIPT###-->', $java_script, $html);
		return $html;
	}

	function PrintPageFooter($script='') {
		echo GetPageFooter($script);
	}

	function GetPageFooter($script='') {
		$html='	<footer class="container-fluid text-center navbar-fixed-bottom">
					<p style="margin: 0px;">&copy; ' . date('Y') . ' <a href="http://www.iserveme.com" target="_blank" style="color: white; text-decoration: none;">IServeMe.com</a> // powered by <a href="http://www.mentalit.gr" target="_blank" style="color: white; text-decoration: none;">Mental Informatics</a></p>
				</footer>' . $script . '</body></html>';
		return $html;
	}

	function GetPageTopMenu($selected_menu='', $script='') {
		if(!Session::IsLoggedIn()) return '';
		if(Session::IsAdmin() && file_exists(VIEWS_PATH . 'admin/widget_top_menu.php'))
			include VIEWS_PATH . 'admin/widget_top_menu.php';
		else if(Session::IsShopManager() && file_exists(VIEWS_PATH . 'shop_manager/widget_top_menu.php'))
			include VIEWS_PATH . 'shop_manager/widget_top_menu.php';
		else if(Session::IsWaiter() && file_exists(VIEWS_PATH . 'waiter/widget_top_menu.php'))
			include VIEWS_PATH . 'waiter/widget_top_menu.php';

		return '';

		$exclamation_badge='<span style="color: red; font-size: small;" class="glyphicon glyphicon-info-sign"></span>';

		$html='
			<nav class="navbar navbar-inverse">
				<div class="container-fluid">
					<div class="navbar-header">
						<button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#top_navbar">
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
						</button>
						<a class="navbar-brand" href="' . BaseUrl() . '"><img id="top_navbar_logo" src="' . IMAGES_URL . 'app_logo.png" height="30" /></a>
					</div>
					<div class="collapse navbar-collapse" id="top_navbar">
						<ul class="nav navbar-nav">
							<li ' . ($selected_menu=='' || $selected_menu=='home' ? 'class="active"' : '') . '><a href="' . BaseUrl() . '">' . Strings::Get('menu_home') . '</a></li>
		';
		if(Session::IsAdmin()) {
			$errors_log_size=GetFileSize(ERRORS_LOG_FILE);
			$error_log_changed=$errors_log_size>Session::Get('errors_log_size');
			$html.='
							<li class="dropdown ' . ($selected_menu=='entities' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_entities') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'admin/list">' . Strings::Get('menu_admins') . '</a>
									<a href="' . BaseUrl() . 'company/list">' . Strings::Get('menu_companies') . '</a>
									<a href="' . BaseUrl() . 'department/list">' . Strings::Get('menu_departments') . '</a>
									<a href="' . BaseUrl() . 'user/list">' . Strings::Get('menu_users') . '</a>
									<a href="' . BaseUrl() . 'order/list">' . Strings::Get('menu_orders') . '</a>
									<a href="' . BaseUrl() . 'product_category/list">' . Strings::Get('menu_products_categories') . '</a>
									<a href="' . BaseUrl() . 'product/list">' . Strings::Get('menu_products') . '</a>
									<a href="' . BaseUrl() . 'device/list">' . Strings::Get('menu_devices') . '</a>
									<a href="' . BaseUrl() . 'room/list">' . Strings::Get('menu_rooms') . '</a>
									<a href="' . BaseUrl() . 'table/list">' . Strings::Get('menu_tables') . '</a>
									<a href="' . BaseUrl() . 'spec/list">' . Strings::Get('menu_specs') . '</a>
									<a href="' . BaseUrl() . 'unit/list">' . Strings::Get('menu_units') . '</a>
									<a href="' . BaseUrl() . 'company_customer/list">' . Strings::Get('menu_company_customers') . '</a>
									<a href="' . BaseUrl() . 'customer/list">' . Strings::Get('menu_customers') . '</a>
								</div>
							</li>
							<li class="dropdown ' . ($selected_menu=='series_documents' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_series_documents') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'buy_series/list">' . Strings::Get('menu_buy_series') . '</a>
									<a href="' . BaseUrl() . 'buy_document/list">' . Strings::Get('menu_buy_documents') . '</a>
									<a href="' . BaseUrl() . 'sale_series/list">' . Strings::Get('menu_sale_series') . '</a>
									<a href="' . BaseUrl() . 'sale_document/list">' . Strings::Get('menu_sale_documents') . '</a>
									<a href="' . BaseUrl() . 'stock_transaction/list">' . Strings::Get('menu_stock_transactions') . '</a>
								</div>
							</li>
							<li class="dropdown ' . ($selected_menu=='reports' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_reports') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'report/orders">' . Strings::Get('menu_reports_orders') . '</a>
								</div>
							</li>
							<li class="dropdown ' . ($selected_menu=='maintenance' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_maintenance') . ($error_log_changed ? $exclamation_badge : '') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'report/errors_log">' . Strings::Get('menu_maintenance_error_log') . ' (' . $errors_log_size . ')' . ($error_log_changed ? $exclamation_badge : '') . '</a>
									<a href="' . BaseUrl() . 'admin/strings">' . Strings::Get('menu_admin_strings') . '</a>
								</div>
							</li>
						</ul>
			';
		} else if(Session::IsShopManager()) {
			$html.='
							<li class="dropdown ' . ($selected_menu=='entities' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_entities') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'company/edit">' . Strings::Get('menu_company') . '</a>
									<a href="' . BaseUrl() . 'department/list">' . Strings::Get('menu_departments') . '</a>
									<a href="' . BaseUrl() . 'user/list">' . Strings::Get('menu_users') . '</a>
									<a href="' . BaseUrl() . 'product_category/list">' . Strings::Get('menu_products_categories') . '</a>
									<a href="' . BaseUrl() . 'product/list">' . Strings::Get('menu_products') . '</a>
									<a href="' . BaseUrl() . 'table/list">' . Strings::Get('menu_tables') . '</a>
									<a href="' . BaseUrl() . 'spec/list">' . Strings::Get('menu_specs') . '</a>
									<a href="' . BaseUrl() . 'unit/list">' . Strings::Get('menu_units') . '</a>
									<a href="' . BaseUrl() . 'company_customer/list">' . Strings::Get('menu_company_customers') . '</a>
								</div>
							</li>
							<li ' . ($selected_menu=='' || $selected_menu=='orders' ? 'class="active"' : '') . '><a href="' . BaseUrl() . 'order/list">' . Strings::Get('menu_orders') . '</a></li>
							<li class="dropdown ' . ($selected_menu=='reports' ? 'active' : '') . '">
								<a href="#">' . Strings::Get('menu_reports') . '</a>
								<div class="dropdown-content">
									<a href="' . BaseUrl() . 'report/orders">' . Strings::Get('menu_reports_orders') . '</a>
								</div>
							</li>
						</ul>
			';
		} else if(Session::IsWaiter()) {
			$html.='
							<li ' . ($selected_menu=='' || $selected_menu=='tables' ? 'class="active"' : '') . '><a href="' . BaseUrl() . 'table/list">' . Strings::Get('menu_tables') . '</a></li>
							<li ' . ($selected_menu=='' || $selected_menu=='orders' ? 'class="active"' : '') . '><a href="' . BaseUrl() . 'order/list">' . Strings::Get('menu_orders') . '</a></li>
						</ul>
			';
		}

		$html.='
						<ul class="nav navbar-nav navbar-right">
							<li id="top_menu_user_logout" title="' . Strings::Get('menu_logout') . '"><span style="color: white">' . Html_Entities(Session::Account()->name) . '</span><img id="top_menu_user_logo" src="' . (isset(Session::Account()->icon) && Session::User()->icon ? IMAGES_DATA_URL . 'USER.icon.' . Session::UserId() : IMAGES_URL . 'user_icon_white.png') . '" /></li>
						</ul>
					</div>
				</div>
			</nav>

			<div id="alerts_top"></div>
			<!--<img id="page_backdrop" src="<?=IMAGES_URL?>backdrop.jpg" />-->
<img id="page_backdrop"/>
			<script>
				$(document).ready(function() {

					$(".navbar a").click(function(){ window.stop(); });

					$("#top_menu_user_logout").click(function(){
						if(!confirm("' . Session::Account()->name . '\n' . Strings::Get('menu_logout_message') . '")) return;
						window.location="' . BASE_URL . '?/account/logout";
					});
				});
			</script>
			' . $script .'
		';
		return $html;
	}

	function PrintPageTopMenu($selected_menu='', $script='') {
		echo GetPageTopMenu($selected_menu, $script);
	}

	function GetModelPage($params=[]) {
		if(empty($params)) diep(['GetModelPage called without params', debug_backtrace()]);
		if(empty($params['model'])) diep(['GetModelPage called without model', debug_backtrace()]);
		$model=$params['model'];
		$page_table=$model->table;
		$page_controller=$model->controller;
		$script=isset($params['script']) ? $params['script'] : '';

		$id=$model->primary_key_value;
		if($model->primary_key_value) $model->Load();
		$title=DB_TABLES[$page_table]['name_singular'] . ' - ' . (empty($id) ? 'Προσθήκη' : (isset($model->name) ? $model->name : (isset($model->date) ? $model->date : '')));

		// Create edit view
		$edit_view=new Edit($title, $page_table, $id, API_URL . "controller={$page_controller}&action=edit", BASE_URL . "{$page_controller}/list", '');
		if(!empty($params['set_data'])) $edit_view->SetData($params['set_data']);
		if(!empty($params['custom_fields'])) $edit_view->SetCustomFields($params['custom_fields']);
		if(!empty($params['invisible_fields'])) $edit_view->SetInvisibleFields($params['invisible_fields']);
		if(!empty($params['locked_fields'])) $edit_view->SetLockedFields($params['locked_fields']);
		if(!empty($params['hidden_fields'])) $edit_view->SetHiddenFields($params['hidden_fields']);
		if(!empty($params['extra_buttons'])) $edit_view->SetExtraButtons($params['extra_buttons']);
		if(!empty($params['save_link'])) $edit_view->SetSaveLink($params['save_link']);
		if(!empty($params['cancel_link'])) $edit_view->SetCancelLink($params['cancel_link']);
		if(!empty($params['lock_all_fields']) && $params['lock_all_fields']) $edit_view->LockAllFields();

		$lock_fields=false;

		// Check permissions
		$lock_fields= (Session::IsAdmin() && ((!empty($model->id) && !in_array($page_controller, CONTROLLERS_ADMIN_CAN_EDIT)) || (empty($model->id) && !in_array($page_controller, CONTROLLERS_ADMIN_CAN_ADD))))
					  ||
					  (!Session::IsAdmin() && ((!empty($model->id) && !in_array($page_controller, CONTROLLERS_USER_CAN_EDIT)) || (empty($model->id) && !in_array($page_controller, CONTROLLERS_USER_CAN_ADD))));
		if($lock_fields) $edit_view->LockAllFields();

		//Get page head
		$html=GetPageHead($title) . '
			<body>
				' . GetPageTopMenu($page_table) . '
				<div class="container-fluid text-center">
				  <div class="row content">
					<div class="app-page-content">
						' . $edit_view->GetHtml() . '
					</div>
				  </div>
				</div>
				'  . GetPageFooter($script) . '
		';
		return $html;
	}

	function PrintModelPage($params=[]) {
		echo GetModelPage($params);
	}

	function GetListPage($params=[]) {
		if(empty($params)) diep(['GetListPage called without params', debug_backtrace()]);
		if(empty($params['model'])) diep(['GetListPage called without model', debug_backtrace()]);
		$model=$params['model'];
		$page_table=$model->table;
		$page_controller=$model->controller;
		$fields=isset($params['fields']) ? $params['fields'] : [];
		$script=isset($params['script']) ? $params['script'] : '';

		if(empty($fields)) {
			foreach(DB_TABLES[$page_table]['fields'] as $field_name=>$field_data) {
				$f=$field_data;
				$f['width']=$f['id']=='id' ? '50' : '*';
				$fields[]=$f;
			}
		}

		$html=GetPageHead(DB_TABLES[$page_table]['name']) . '
			<body>
				' . GetPageTopMenu($page_table) . '
				<div class="container-fluid text-center">
				  <div class="row content">
					<div class="app-page-content">
						' . (empty($params['above_list']) ? '' : $params['above_list']) . '
						<div id="custom_table_div"></div>
					</div>
				  </div>
				</div>
				<script type="text/javascript">
					var custom_table;
					$( document ).ready(function() {
						' . CreateCustomTable('custom_table', 'custom_table_div', $page_controller) . '
						custom_table.Title("' . DB_TABLES[$page_table]['name'] . '");
						' . (empty($params['below_title']) ? '' : 'custom_table.BelowTitle("' . addslashes($params['below_title']) . '");') . '
						custom_table.SetFields([
		';

		foreach($fields as $field) $html.=str_repeat("\t", 7) . "{ name: '{$field['id']}', header: '" . Strings::Get("{$page_table}:{$field['id']}") . "', type: '{$field['type']}', width: '{$field['width']}' },\n";

		$html.='
						]);
						custom_table.SetTopButtons([
						' . ((Session::IsAdmin() && in_array($page_controller, CONTROLLERS_ADMIN_CAN_ADD)) || (!Session::IsAdmin() && in_array($page_controller, CONTROLLERS_USER_CAN_ADD)) ? '{ name: "add", icon: "plus-sign", text: "Νέο", url: "' . BASE_URL . '?/' . $page_controller . '/edit" },' : '') . '
						' . ((Session::IsAdmin() && in_array($page_controller, CONTROLLERS_ADMIN_CAN_EDIT)) || (!Session::IsAdmin() && in_array($page_controller, CONTROLLERS_USER_CAN_EDIT)) ? '{ name: "mass_delete", icon: "trash", text: "Μαζική διαγραφή", action: "custom_table.MassDelete();" },' : '') . '
						]);
						custom_table.Draw();
					});
				</script>
				'  . GetPageFooter($script) . '
		';
		return $html;
	}

	function PrintListPage($params=[]) {
		echo GetListPage($params=[]);
	}

	function IntToBase32($number) {
		return base_convert($number, 10, 32);
	}

	function Base32ToInt($number) {
		return base_convert($number, 32, 10);
	}

	function GetRequest($param) {
		return isset($_REQUEST[$param]) ? $_REQUEST[$param] : '';
	}

	function ShowResponseAlert($response) {
		echo '<script>alert(\'Error code: ' . $response->code . '\n' . $response->message . '\');</script>';
	}

	function GetFileSize($file) {
		if(!file_exists($file)) return '0 bytes';
		$filesize=filesize($file);
		if($filesize>1024 * 1024 * 1024)
			return round($filesize / (1024 * 1024 * 1024), 2) . ' Gb';
		else if($filesize>1024 * 1024)
			return round($filesize / (1024 * 1024), 2) . ' Mb';
		else if($filesize>1024)
			return round($filesize / 1024, 2) . ' Kb';
		else
			return $filesize . ' bytes';
	}

	function ValidateReferrer($referrer='', $exit=true) {
		$host='';

		if(empty($referrer))
			$referrer=trim(isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '');
		if(empty($referrer))
			$referrer=trim(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		if(empty($referrer))
			$referrer=trim(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');

		$error=false;
		if(empty($referrer)) {
			$error=true;
		} else {
			$host=parse_url($referrer, PHP_URL_HOST);
			$error=empty($host) || ($host!=str_replace('/', '', BASE_URL) && !in_array($host, ALLOWED_REFERRERS()));
			if($error) $error=$referrer!=str_replace('/', '', BASE_URL) && !in_array($referrer, ALLOWED_REFERRERS());
		}
		if($error) {
			$log=new AbstractLogger('security', '');
			$log->Add("INVALID REFERRER: [$referrer] [$host]\n  Request: " . print_r($_REQUEST, true));
			if($exit) {
				header('HTTP/1.1 401 Unauthorized');
				header('Content-Type: application/json; charset=utf-8');
				ToJson(new Response(401, "Unauthorized\nReferrer: $referrer\nHost: $host", true), true);
				exit;
			}
			return false;
		}

		header('Access-Control-Allow-Origin: ' . $referrer);
		header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
		header('Access-Control-Max-Age: 1000');
		header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

		return true;
	}

// STRINGS
	function ToUtfR($d) { if(is_array($d)) { $dd=[]; foreach($d as $k => $v) $dd[ToUtfR($k)] = ToUtfR($v); return $dd; } else if(is_object($d)) { $dd=new stdClass; foreach($d as $k => $v) { $field=ToUtfR($k); $dd->$field=ToUtfR($v); } return $dd; } else if(is_string($d)) { return iconv('ISO-8859-7', 'UTF-8', $d); return utf8_encode($d); } return $d; }
	function ForceUTF8($d) { if(is_array($d)) foreach ($d as $k => $v) $d[$k] = ForceUTF8($v); else if(is_object($d)) foreach ($d as $k => $v)  $d->$k = ForceUTF8($v); else  return utf8_encode($d); return $d; }
	function ArrayToStrings($array) { $out=""; foreach($array as $v) $out.=($out=='' ? '' : ', ') . "'$v'"; return $out; }
	function StartsWith($text, $search) { if(strlen($text)<strlen($search)) return false; return substr($text, 0, strlen($search)) === $search; }
	function EndsWith($text, $search) { $length = strlen($search); if($length==0) return true; return substr($text, -$length) === $search; }
	function Contains($haystack, $needle) { return strpos($haystack, $needle)!==FALSE; }
	function SanitizeForFile($input) { return mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $input); }
	function FixPathSeparator($inputed) { return str_replace('/', DIRECTORY_SEPARATOR, $inputed); }
	function ToUTFEntity($inputed) { return html_entity_decode($inputed, ENT_COMPAT, 'UTF-8'); }
	function ToUTF($inputed) { return iconv('ISO-8859-7', 'UTF-8//INGORE', $inputed); }
	function ToISO($inputed) { return iconv('UTF-8', 'ISO-8859-7//INGORE', $inputed); }
	function EncryptCredentials($input) { $out=''; for($i=0;$i<strlen($input);$i++) { $o=strpos(CREDS_ORIGINAL, substr($input, $i, 1)); $out.=$o===false ? substr($input, $i, 1) : substr(CREDS_ENCRYPTED, $o, 1); } return $out; }
	function DecryptCredentials($input) { $out=''; for($i=0;$i<strlen($input);$i++) { $o=strpos(CREDS_ENCRYPTED, substr($input, $i, 1)); $out.=$o===false ? substr($input, $i, 1) : substr(CREDS_ORIGINAL, $o, 1); } return $out; }
	function ConvertGreekToLatin($inputed) {
		$what=['α', 'ά', 'α', 'α', 'β', 'γ', 'δ', 'ε', 'έ', 'ζ', 'η', 'ή', 'θ', 'ι', 'ί', 'ϊ', 'ΐ', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'ό', 'π', 'ρ', 'σ', 'ς', 'τ', 'υ', 'ύ', 'ϋ', 'ΰ', 'φ', 'χ', 'ψ', 'ω', 'ώ', 'Α', 'Ά', 'Α', 'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Έ', 'Ζ', 'Η', 'Ή', 'Θ', 'Ι', 'Ί', 'Ϊ', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ', 'Ο', 'Ό', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Ύ', 'Ϋ', 'Φ', 'Χ', 'Ψ', 'Ω', 'Ώ'];
		$with=['a', 'a', 'a', 'a', 'b', 'g', 'd', 'e', 'e', 'z', 'h', 'h', 'u', 'i', 'i', 'i', 'i', 'k', 'l', 'm', 'n', 'j', 'o', 'o', 'p', 'r', 's', 's', 't', 'y', 'y', 'y', 'y', 'f', 'x', 'c', 'v', 'v', 'A', 'A', 'A', 'A', 'B', 'G', 'D', 'E', 'E', 'Z', 'H', 'H', 'U', 'I', 'I', 'I', 'K', 'L', 'M', 'N', 'J', 'O', 'O', 'P', 'R', 'S', 'T', 'Y', 'Y', 'Y', 'F', 'X', 'C', 'V', 'V'];
		return str_replace($what, $with, $inputed);

		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("ά", "a", $inputed);
		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("β", "b", $inputed);
		$inputed=str_replace("γ", "g", $inputed);
		$inputed=str_replace("δ", "d", $inputed);
		$inputed=str_replace("ε", "e", $inputed);
		$inputed=str_replace("έ", "e", $inputed);
		$inputed=str_replace("ζ", "z", $inputed);
		$inputed=str_replace("η", "h", $inputed);
		$inputed=str_replace("ή", "h", $inputed);
		$inputed=str_replace("θ", "u", $inputed);
		$inputed=str_replace("ι", "i", $inputed);
		$inputed=str_replace("ί", "i", $inputed);
		$inputed=str_replace("ϊ", "i", $inputed);
		$inputed=str_replace("ΐ", "i", $inputed);
		$inputed=str_replace("κ", "k", $inputed);
		$inputed=str_replace("λ", "l", $inputed);
		$inputed=str_replace("μ", "m", $inputed);
		$inputed=str_replace("ν", "n", $inputed);
		$inputed=str_replace("ξ", "j", $inputed);
		$inputed=str_replace("ο", "o", $inputed);
		$inputed=str_replace("ό", "o", $inputed);
		$inputed=str_replace("π", "p", $inputed);
		$inputed=str_replace("ρ", "r", $inputed);
		$inputed=str_replace("σ", "s", $inputed);
		$inputed=str_replace("ς", "s", $inputed);
		$inputed=str_replace("τ", "t", $inputed);
		$inputed=str_replace("υ", "y", $inputed);
		$inputed=str_replace("ύ", "y", $inputed);
		$inputed=str_replace("ϋ", "y", $inputed);
		$inputed=str_replace("ΰ", "y", $inputed);
		$inputed=str_replace("φ", "f", $inputed);
		$inputed=str_replace("χ", "x", $inputed);
		$inputed=str_replace("ψ", "c", $inputed);
		$inputed=str_replace("ω", "v", $inputed);
		$inputed=str_replace("ώ", "v", $inputed);

		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Ά", "A", $inputed);
		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Β", "B", $inputed);
		$inputed=str_replace("Γ", "G", $inputed);
		$inputed=str_replace("Δ", "D", $inputed);
		$inputed=str_replace("Ε", "E", $inputed);
		$inputed=str_replace("Έ", "E", $inputed);
		$inputed=str_replace("Ζ", "Z", $inputed);
		$inputed=str_replace("Η", "H", $inputed);
		$inputed=str_replace("Ή", "H", $inputed);
		$inputed=str_replace("Θ", "U", $inputed);
		$inputed=str_replace("Ι", "I", $inputed);
		$inputed=str_replace("Ί", "I", $inputed);
		$inputed=str_replace("Ϊ", "I", $inputed);
		$inputed=str_replace("Κ", "K", $inputed);
		$inputed=str_replace("Λ", "L", $inputed);
		$inputed=str_replace("Μ", "M", $inputed);
		$inputed=str_replace("Ν", "N", $inputed);
		$inputed=str_replace("Ξ", "J", $inputed);
		$inputed=str_replace("Ο", "O", $inputed);
		$inputed=str_replace("Ό", "O", $inputed);
		$inputed=str_replace("Π", "P", $inputed);
		$inputed=str_replace("Ρ", "R", $inputed);
		$inputed=str_replace("Σ", "S", $inputed);
		$inputed=str_replace("Τ", "T", $inputed);
		$inputed=str_replace("Υ", "Y", $inputed);
		$inputed=str_replace("Ύ", "Y", $inputed);
		$inputed=str_replace("Ϋ", "Y", $inputed);
		$inputed=str_replace("Φ", "F", $inputed);
		$inputed=str_replace("Χ", "X", $inputed);
		$inputed=str_replace("Ψ", "C", $inputed);
		$inputed=str_replace("Ω", "V", $inputed);
		$inputed=str_replace("Ώ", "V", $inputed);
		return $inputed;
	}
	function ConvertLatinToGreek($inputed) {
		$what=['a', 'b', 'c', 'd', 'e', 'f', 'h', 'g', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'H', 'G', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
		$with=['α', 'β', 'κ', 'δ', 'ε', 'φ', 'η', 'γ', 'ι', 'ξ', 'κ', 'λ', 'μ', 'ν', 'ο', 'π', 'κ', 'ρ', 'σ', 'τ', 'υ', 'β', 'ω', 'χ', 'υ', 'ζ', 'Α', 'Β', 'Κ', 'Δ', 'Ε', 'Φ', 'Η', 'Γ', 'Ι', 'Ξ', 'Κ', 'Λ', 'Μ', 'Ν', 'Ο', 'Π', 'Κ', 'Ρ', 'Σ', 'Τ', 'Υ', 'Β', 'Ω', 'Χ', 'Υ', 'Ζ'];
		return str_replace($what, $with, $inputed);

		$inputed=str_replace("a", "α", $inputed);
		$inputed=str_replace("b", "β", $inputed);
		$inputed=str_replace("c", "κ", $inputed);
		$inputed=str_replace("d", "δ", $inputed);
		$inputed=str_replace("e", "ε", $inputed);
		$inputed=str_replace("f", "φ", $inputed);
		$inputed=str_replace("h", "η", $inputed);
		$inputed=str_replace("g", "γ", $inputed);
		$inputed=str_replace("i", "ι", $inputed);
		$inputed=str_replace("j", "ξ", $inputed);
		$inputed=str_replace("k", "κ", $inputed);
		$inputed=str_replace("l", "λ", $inputed);
		$inputed=str_replace("m", "μ", $inputed);
		$inputed=str_replace("n", "ν", $inputed);
		$inputed=str_replace("o", "ο", $inputed);
		$inputed=str_replace("p", "π", $inputed);
		$inputed=str_replace("q", "κ", $inputed);
		$inputed=str_replace("r", "ρ", $inputed);
		$inputed=str_replace("s", "σ", $inputed);
		$inputed=str_replace("t", "τ", $inputed);
		$inputed=str_replace("u", "υ", $inputed);
		$inputed=str_replace("v", "β", $inputed);
		$inputed=str_replace("w", "ω", $inputed);
		$inputed=str_replace("x", "χ", $inputed);
		$inputed=str_replace("y", "υ", $inputed);
		$inputed=str_replace("z", "ζ", $inputed);

		$inputed=str_replace("A", "Α", $inputed);
		$inputed=str_replace("B", "Β", $inputed);
		$inputed=str_replace("C", "Κ", $inputed);
		$inputed=str_replace("D", "Δ", $inputed);
		$inputed=str_replace("E", "Ε", $inputed);
		$inputed=str_replace("F", "Φ", $inputed);
		$inputed=str_replace("H", "Η", $inputed);
		$inputed=str_replace("G", "Γ", $inputed);
		$inputed=str_replace("I", "Ι", $inputed);
		$inputed=str_replace("J", "Ξ", $inputed);
		$inputed=str_replace("K", "Κ", $inputed);
		$inputed=str_replace("L", "Λ", $inputed);
		$inputed=str_replace("M", "Μ", $inputed);
		$inputed=str_replace("N", "Ν", $inputed);
		$inputed=str_replace("O", "Ο", $inputed);
		$inputed=str_replace("P", "Π", $inputed);
		$inputed=str_replace("Q", "Κ", $inputed);
		$inputed=str_replace("R", "Ρ", $inputed);
		$inputed=str_replace("S", "Σ", $inputed);
		$inputed=str_replace("T", "Τ", $inputed);
		$inputed=str_replace("U", "Υ", $inputed);
		$inputed=str_replace("V", "Β", $inputed);
		$inputed=str_replace("W", "Ω", $inputed);
		$inputed=str_replace("X", "Χ", $inputed);
		$inputed=str_replace("Y", "Υ", $inputed);
		$inputed=str_replace("Z", "Ζ", $inputed);
		return $inputed;
	}
	function RemoveDoubleSpace($input) { if(empty($input)) return $input; while(strpos($input, '  ')!==false) $input=str_replace('  ', ' ', $input); return trim($input); }
	function StripTagsWithSpace($input) { if(empty($input)) return $input; $input=strip_tags(str_replace('<', ' <', str_replace('>', '> ', $input))); return RemoveDoubleSpace($input); }
	function GetRandomString($length=10, $characters='_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') { $characters_length = strlen($characters); $random_string = ''; for ($i = 0; $i < $length; $i++) $random_string .= $characters[rand(0, $characters_length - 1)]; return $random_string; }

	function GetElementByFieldValue($collection, $field, $value) {
		if(empty($collection) || empty($field)) return false;
		foreach($collection as $element) {
			if(is_array($element) && $element[$field]==$value) return $element;
			else if(is_object($element) && $element->$field==$value) return $element;
			else break;
		}
		return false;
	}

	function ExcelGetColumnAddress($num) {
		$numeric = ($num - 1) % 26;
		$letter = chr(65 + $numeric);
		$num2 = intval(($num - 1) / 26);
		return $num2 > 0 ? ExcelGetColumnAddress($num2) . $letter : $letter;
	}

	function DownloadExcelTable($table_html, $filename='report.xls') {
		header('Content-type: application/excel');
		header('Content-Disposition: attachment; filename=' . $filename);
		$data='<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Sheet 1</x:Name>
                    <x:WorksheetOptions>
                        <x:Print>
                            <x:ValidPrinterInfo/>
                        </x:Print>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>

<body>
   ' . $table_html . '
</body></html>';
		die($data);
	}

	function SaveBase64Image($id, $date, $image, $filename='') {
		$img_base64=str_replace(' ', '+', $image);
		$image_data=base64_decode($img_base64);
		if(empty($image_data)) return new Response(false, 'Invalid base64 image');
		$image_dir=EVENTS_IMAGES_PATH . substr($date, 0, 10);
		if(!file_exists($image_dir) && !mkdir($image_dir)) return new Response(false, 'Cannot create image directory');
		$file=$image_dir . DIRECTORY_SEPARATOR . (empty($filename) ? $id : $filename);
		if(file_put_contents($file, $image_data)===false) return new Response(false, 'Cannot save image');
		return new Response(true, 'OK', $file);
	}

	function MoveUploadedImage($id, $date, $image, $filename='') {
		$image_dir=EVENTS_IMAGES_PATH . substr($date, 0, 10);
		if(!file_exists($image_dir) && !mkdir($image_dir)) return new Response(false, 'Cannot create image directory');
		$file=$image_dir . DIRECTORY_SEPARATOR . (empty($filename) ? $id : $filename);
		if(!move_uploaded_file($image['tmp_name'], $file)) return new Response(false, 'Cannot save image');
		return new Response(true, 'OK', $file);
	}

	function DisconnectFromDB() {
		if(!empty(DB::$instance)) {
			DB::$instance->conn=null;
			DB::$instance=null;
		}
	}

	function SendEmail($to, $subject, $message, $attachment_file='') {
		$res=mail($to, $subject, $message, "Content-type: text/html; charset=utf-8\r\nFrom: " . MAIL_USER);
		return [ 'status' => $res, 'message' => $res ? Strings::Get('Failed to send email') : 'OK' ];
	}

	function GetPrintableHtml($content, $models=[], $replacements=[]) {
		$out=$content;
		// Loop through models
		if(!empty($models)) foreach($models as $model) {
			// Check if model has table
			if(empty($model) || !($model instanceOf Model) || !isset($model->table) || empty($model->table)) continue;
			// Loop through fields
			foreach($model as $k=>$v) {
				// Check if value is string or numeric
				if(!is_string($v) && !is_numeric($v) && $v!='') continue;
				if(is_null($v)) $v='';
				// Check if this is existing DB field
				if(isset(DB_TABLES[$model->table]['fields'][$k]) && DB_TABLES[$model->table]['fields'][$k]['number_type']=='float') { // Float
					$out=str_replace("#{$model->table}.{$k}#", Strings::FormatAmount($v), $out);
				} else if(isset(DB_TABLES[$model->table]['fields'][$k]) && DB_TABLES[$model->table]['fields'][$k]['number_type']=='int') { // Int
					$out=str_replace("#{$model->table}.{$k}#", $v, $out);
				} else if(isset(DB_TABLES[$model->table]['fields'][$k]) && DB_TABLES[$model->table]['fields'][$k]['number_type']=='tiny_int') { // Check
					$out=str_replace("#{$model->table}.{$k}#", $v==1 ? '&#128505;' : '&#9744;', $out);
				} else { // Other
					$out=str_replace("#{$model->table}.{$k}#", $v, $out);
				}
			}
		}

		// Loop through replacements
		if(!empty($replacements)) foreach($replacements as $r) foreach($r as $k=>$v) {
			// Check if value is string or numeric
			if(!is_string($v) && !is_numeric($v) && $v!='') continue;
			// Make replacements
			$out=str_replace("#{$k}#", $v, $out);
		}
		return $out;
	}

	function Html_Entities($string) {
		return $string=='' || $string==null ? '' : htmlentities($string);
	}
