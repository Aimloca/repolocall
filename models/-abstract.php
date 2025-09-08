<?php

#[\AllowDynamicProperties]
class Model extends stdClass{

	public $table;
	public $controller;
	public $primary_key;
	public $primary_key_value;
	public $predefined_db_fields_values=[];
	public static $db_fields;

	function __construct($table='', $primary_key='', $primary_key_value='') {
		$class=get_class($this);
		$this->table=$table;
		$this->primary_key=$primary_key;
		$this->primary_key_value=$primary_key_value;
		$this->controller=strtolower(get_class($this));


		if(!isset($class::$db_fields) && !empty(DB_TABLES) && !empty(DB_TABLES[$this->table]) && !empty(DB_TABLES[$this->table]['fields'])) $class::$db_fields=DB_TABLES[$this->table]['fields'];
		if($class::$db_fields) foreach($class::$db_fields as $field_name=>$field) $this->$field_name=is_null($field['default']) ? null : $field['default'];
	}

	function GetTable() {
		return $this->table;
	}

	function GetPrimaryKey() {
		return $this->primary_key;
	}

	function GetPrimaryKeyId() {
		return $this->primary_key_value;
	}

	function Load($filters='') {
		if(empty($this->primary_key_value) && empty($filters)) return false;
		$load=DB::LoadModel($this, $filters);
		if($load) {
			$pk=$this->primary_key;
			$this->primary_key_value=$this->$pk;
		}
		return $load ? $this : false;
	}

	function Save() {
		$this->FixNullFields();
		$save=DB::SaveModel($this);
		if($save['status']) {
			foreach(DB_TABLES[$this->table]['image_fields'] as $field) {
				if(empty($this->$field) && file_exists(IMAGES_DATA_PATH . "{$this->table}.{$field}.{$this->id}")) @unlink(IMAGES_DATA_PATH . "{$this->table}.{$field}.{$this->id}");
			}
		}
		return $save;
	}

	function Insert() {
		$this->FixNullFields();
		return DB::InsertModel($this);
	}

	function Delete() {
		return DB::DeleteModel($this);
	}

	function CopyFrom($model) {
		foreach(get_object_vars($model) as $k=>$v) $this->$k=$v;
		return $this;
	}

	function SetDefaultValues() {
		DB::SetDefaultValues($this);
	}

	function GetDBFields() {
		$class=get_class($this);
		if(empty($class::$db_fields) && !empty(DB_TABLES) && !empty(DB_TABLES[$this->table]) && !empty(DB_TABLES[$this->table]['fields'])) $class::$db_fields=DB_TABLES[$this->table]['fields'];
		if(empty($class::$db_fields) && !isset(DB_TABLES[$this->table])) throw new Exception("No db fields found for model with table {$this->table}");
		return $class::$db_fields;
	}

	function CreateFromRequest($allow_non_db_fields=false) {
		$this->GetDBFields();
		foreach($_REQUEST as $k=>$v) {
			if($allow_non_db_fields) {
				$this->$k=$v;
			} else if(!isset($this::$db_fields[$k])) {
				continue;
			} else {
				$this->$k=strval($v==DB_NULL_STRING) ? null : (strpos(DB_TABLES[$this->table]['fields'][$k]['type'], 'datetime')!==false ? str_replace(['t', 'T'], ' ', $v) : $v);
			}
		}
		return $this;
	}

	function CreateFromArray($array, $keep_only_db_fields=false) {
		$this->GetDBFields();
		foreach($array as $k=>$v) {
			if($keep_only_db_fields && !isset($this::$db_fields[$k])) continue;
			$this->$k=$v;
		}
		if($keep_only_db_fields) $this->KeepOnlyDBFields();
		return $this;
	}

	function FixNullFields() {
		$this->GetDBFields();
		foreach($this::$db_fields as $k=>$field_data) {
			if(!isset($this->$k)) continue;
			$v=str_replace(DB_NULL_STRING, '', strval($this->$k));
			if(trim($v)=='') $this->$k=null;
		}
		return $this;
	}

	function KeepOnlyDBFields() {
		$this->GetDBFields();
		foreach($this as $k=>$v) if($k!='db_fields' && !isset($this::$db_fields[$k])) unset($this->$k);
		return $this;
	}

	function FillPredefinedFields() {
		$this->GetDBFields();
		foreach($this::$db_fields as $field_name=>$field_data) {
			if(!isset($this->$field_name) || (empty($this->$field_name) && strval($this->$field_name)!='0')) $this->$field_name=$field_data['default'];
		}
		return $this;
	}

	function CreateFromJson($json) {
		$object=is_string($json) ? json_decode($json) : $json;
		if(!$object) return $this;
		$properties=get_object_vars($object);
		if(!$properties) return $this;
		foreach($properties as $property=>$value) {
			if(in_array($property, array('id', 'db_fields'))) continue;
			if(in_array($property, $this::$db_fields)) $this->$property=$value;
		}
		return $this;
	}

	function ToJson() {
		return ToJson($this);
	}

	function GetLanguageFields() {
		$fields=[];
		foreach($this as $field=>$value) if(in_array(substr($field, -3), LANGUAGES_IN_DB_EXTENSIONS)) $fields[]=$field;
		return $fields;
	}

	function GetActiveLanguageFields() {
		$fields=[];
		foreach($this as $field=>$value) if(in_array(substr($field, -3), LANGUAGES_EXTENSIONS)) $fields[]=$field;
		return $fields;
	}

	function GetInactiveLanguageFields() {
		$fields=[];
		foreach($this as $field=>$value) if(in_array(substr($field, -3), LANGUAGES_IN_DB_EXTENSIONS) && !in_array(substr($field, -3), LANGUAGES_EXTENSIONS)) $fields[]=$field;
		return $fields;
	}

	public static function GetById($id, $class) {
		if(empty($id) || empty($class)) return false;
		if(!isset($class::$table)) return false;
		$model=new $class;
		$rows=DB::Query("SELECT * FROM " . $class::$table . " WHERE id=" . DB::Quote($id) . ";");
		if($rows) $model->CreateFromArray($rows[0]); else return false;
		return $model;
	}

	public static function GetList($sql_or_where_array='', $class='') {
		$cur_lang=Strings::GetLanguage();
		$alias='';
		$where='';
		if(empty($sql_or_where_array)) {
			if(empty($class)) return [];
			if(!isset($class::$table) || empty(DB_TABLES) || empty(DB_TABLES[$class::$table]) || empty(DB_TABLES[$class::$table]['fields'])) return [];
			if(!empty(DB_TABLES[$class::$table]['multilang_fields'])) {
				foreach(DB_TABLES[$class::$table]['multilang_fields'] as $field) {
					if(substr($field, -3)==$cur_lang) $alias.=", {$field} AS " . substr($field, 0, strlen($field)-3) . " ";
				}
			}
			$sql="SELECT * {$alias} FROM " . $class::$table . " {$where} ;";
		} else if(is_string($sql_or_where_array)) {
			$sql=$sql_or_where_array;
		} else {
			// Fix where
			if(!empty($sql_or_where_array) && is_array($sql_or_where_array)) {
				foreach($sql_or_where_array as $k=>$v) $where.=($where=='' ? '' : ' AND ') . " {$k}=" . DB::Quote($v) . " ";
				if(!empty($where)) $where=" WHERE {$where} ";
			}
			// Fix multilang fields
			$alias='';
			if(isset($class::$table) && !empty(DB_TABLES) && !empty(DB_TABLES[$class::$table]) && !empty(DB_TABLES[$class::$table]['multilang_fields'])) {
				foreach(DB_TABLES[$class::$table]['multilang_fields'] as $field) {
					if(substr($field, -3)==$cur_lang) $alias.=", {$field} AS " . substr($field, 0, strlen($field)-3) . " ";
				}
			}
			$sql="SELECT * {$alias} FROM " . $class::$table . " {$where};";
		}
		// Make query
		$rows=DB::Query($sql_or_where_array);
		// Unset password
		if(!empty($rows)) foreach($rows as &$row) if(isset($row['pass'])) unset($row['pass']);
		// If class is not set, return array
		if(empty($class)) return $rows;
		// Create array of class
		$ret=[];
		if($rows) foreach($rows as $row) {
			$c=new $class;
			$c->CreateFromArray($row);
			$ret[]=$c;
		}
		return $ret;
	}

	public static function abstractHandleApi($parameters=''){
		if(empty($parameters)) return new Response(false, Strings::Get('error_parameters_are_missing'));
		if(!is_array($parameters)) return new Response(false, Strings::Get('error_invalid_parameters'));
		if(empty($parameters['class'])) return new Response(false, Strings::Get('error_class_is_missing'));
		if(empty($parameters['action'])) return new Response(false, Strings::Get('error_action_is_missing'));
		if(!isset($parameters['allow_list'])) $parameters['allow_list']=false;
		if(!isset($parameters['allow_edit'])) $parameters['allow_edit']=false;
		if(!isset($parameters['allow_delete'])) $parameters['allow_delete']=false;
		if(!isset($parameters['allow_import'])) $parameters['allow_import']=false;
		// Check action
		if($parameters['action']=='list') {
			// Check permissions
			if(!$parameters['allow_list']) return new Response(false, Strings::Get('error_insufficient_rights'));
			$parameters['list_fields']=Model::GetListFieldsNames($parameters['class']);
			return new Response(true, '', DB::GetTableFields(empty($parameters['table']) ? strtoupper($parameters['class']) : $parameters['table'], isset($parameters['list_fields']) ? $parameters['list_fields'] : ''));

		} else if($parameters['action']=='edit') {
			// Check permissions
			if(!$parameters['allow_edit']) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new $parameters['class'];
			// Get from database
			$model->Load($parameters['id']);
			// Update from request
			$model->CreateFromRequest();
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($parameters['action']=='delete') {
			// Check permissions
			if(!$parameters['allow_delete']) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Initialize
			$model=new $parameters['class'];
			// Get from database
			$model->Load([$model->primary_key => $parameters['id']]);
			// Check model
			if(!$model) new Response(false, Strings::Get('error_invalid_data') . ' ' . $parameters['id'] . PHP_EOL);
			// Delete
			$delete=$model->Delete();
			return new Response($delete);

		} else if($parameters['action']=='mass_delete') {
			// Check permissions
			if(!$parameters['allow_delete']) return new Response(false, Strings::Get('error_insufficient_rights'));
			if(!Session::IsAdmin()) {
				if(!Session::IsShopManager() || $parameters['class']!='Notification') return new Response(false, Strings::Get('error_insufficient_rights'));
			}

			$ids=GetRequest('ids');
			if(empty($ids)) return new Response(false, Strings::Get('no_records_selected_for_deletion'));

			$ids=explode(',', $ids);
			$success='';
			$errors='';
			foreach($ids as $id) {
				// Initialize model
				$model=new $parameters['class'];
				// Get from database
				if(!$model->Load(['id' => $id])) {
					$errors.=Strings::Get('record_with_id_not_found') . ": {$id}\n";
				} else if(!$model) {
					$errors.=Strings::Get('record_with_id_not_found') . ": {$id}\n";
				} else {
					$resp=$model->Delete();
					if($resp['status'])
						$success.="{$resp['message']} {$id}\n";
					else
						$errors.="{$resp['message']} {$id}\n";
				}
			}
			return new Response(true, (empty($errors) ? '' : Strings::Get('errors') . ":\n{$errors}") . (empty($success) ? '' : Strings::Get('deletions') . ":\n{$success}"));

		} else {
			return new Response(false, Strings::Get('error_api_action_not_found'), true);
		}
	}

}
