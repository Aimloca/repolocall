<?php

#[\AllowDynamicProperties]
class DB {

	private static $instance=null;
	public static $affected_rows=0;
	public static $active_transaction=false;

	private $engine=DB_ENGINE;
	private $server=DB_SERVER;
	private $port=DB_PORT;
	private $database=DB_NAME;
	private $user=DB_USER;
	private $pass=DB_PASS;
	private $conn=null;

	function __construct() {
		try {
			$this->conn=new PDO($this->engine . ':host=' . $this->server . ';port=' . $this->port . ';dbname=' . $this->database, $this->user, $this->pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch(PDOException $e) {
			ErrorLogger::Write('Connection failed: ' . $e->getMessage());
		}
		DB::$instance=$this;
	}

	public static function GetRawConnection() {
		if(empty(DB::$instance)) return null;
		if(DB_ENGINE=='pgsql') {
			if(!$db=pg_pconnect("dbname=" . DB::$instance->database . " host=" . DB::$instance->server . " port=" . DB::$instance->port . " user=" . DB::$instance->user . " password='" . DB::$instance->pass . "'")) return null;
		} else if(DB_ENGINE=='mysql') {
			if(!$db=mysqli_connect("dbname=" . DB::$instance->database . " host=" . DB::$instance->server . " port=" . DB::$instance->port . " user=" . DB::$instance->user . " password='" . DB::$instance->pass . "'")) return null;
		}
		return $db;
	}

	public static function GetLastError() {
		if(empty(DB::$instance)) return null;
		return DB::$instance->conn->errorInfo();
	}

	public static function BeginTransaction() {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('BeginTransaction: No connection.');
			return false;
		}
		try {
			if(DB::$active_transaction) return true;
			return DB::$active_transaction=DB::$instance->conn->beginTransaction();
		} catch(Exception $e) {
			ErrorLogger::Write('BeginTransaction failed: ' . $e->getMessage());
			return false;
		}
	}

	public static function CommitTransaction() {
		if(empty(DB::$instance) || empty(DB::$instance->conn)) {
			ErrorLogger::Write('CommitTransaction: No connection.');
			return false;
		}
		try {
			DB::$active_transaction=false;
			return DB::$instance->conn->commit();
		} catch(Exception $e) {
			ErrorLogger::Write('CommitTransaction failed: ' . $e->getMessage());
			DB::$active_transaction=false;
			return false;
		}
	}

	public static function RollBackTransaction() {
		if(empty(DB::$instance) || empty(DB::$instance->conn)) {
			ErrorLogger::Write('RollBackTransaction: No connection.');
			return false;
		}
		try {
			DB::$active_transaction=false;
			return DB::$instance->conn->rollBack();
		} catch(Exception $e) {
			ErrorLogger::Write('RollBackTransaction failed: ' . $e->getMessage());
			DB::$active_transaction=false;
			return false;
		}
	}

	public static function GetNextId($table) {
		if(DB_ENGINE=='pgsql') return DB::Query("SELECT Auto_increment FROM information_schema.tables WHERE table_name='$table' AND table_schema='" . DB_NAME . "';")[0]['Auto_increment'];
		else if(DB_ENGINE=='mysql') return DB::Query("SELECT LAST_INSERT_ID();")[0]['LAST_INSERT_ID()'];
		else return false;
	}

	public static function GetAffected() {
		return DB::$affected_rows;
	}

	public static function Insert($sql, $allow_zero_affected=false, $get_last_inserted_id=false) {
		return DB::InsertUpdate($sql, $allow_zero_affected, $get_last_inserted_id);
	}

	public static function Update($sql, $allow_zero_affected=false) {
		return DB::InsertUpdate($sql, $allow_zero_affected, false);
	}

	private static function InsertUpdate($sql, $allow_zero_affected=false, $get_last_inserted_id=false) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return false;
		}
		try {

			DB::$affected_rows=DB::$instance->conn->exec($sql);
			if($get_last_inserted_id) return DB::GetLastInsertedId();
			return DB::$affected_rows>($allow_zero_affected ? -1 : 0);
		} catch(Exception $e) {
			ErrorLogger::Write('InsertUpdate failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage());
			DB::$affected_rows=0;
			return false;
		}
	}

	public static function Query($sql, $fetch_type=PDO::FETCH_ASSOC) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return false;
		}
		try {
			$data=array();
			foreach(DB::$instance->conn->query($sql, $fetch_type) as $row)
				$data[]=$row;
			return $data;
		} catch(Exception $e) {
			ErrorLogger::Write('Query failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage(). PHP_EOL . print_r($e, true));
			return false;
		}
	}

	public static function Count($sql) {
		$q=self::Query($sql);
		return $q ? count($q) : 0;
	}

	public static function GetTable($table, $where='', $order_by='', $exclude_fields='') {
		if(empty($exclude_fields)) {
			$exclusion='';
		} else if(is_array($exclude_fields)) {
			$exclusion='';
			foreach($exclude_fields as $field)
				$exclusion.=", NULL AS $field";
		} else {
			$exclusion=", NULL AS $exclude_fields";
		}
		return DB::Query("SELECT * $exclusion FROM $table $where $order_by;");
	}

	public static function GetTableWithoutFields($table, $where='', $order_by='') {
		return DB::Query('SELECT * FROM ' . $table . ' ' . $where . ' ' . $order_by . ';');
	}

	public static function Quote($text) {
		if(strval($text)=='') return "''";
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Quote failed. No connection.');
			return false;
		}
		try {
			return DB::$instance->conn->quote($text);
		} catch(Exception $e) {
			ErrorLogger::Write('Quote failed: ' . $e->getMessage());
			return false;
		}
	}

	public static function GetLastInsertedId() {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return false;
		}
		return DB::$instance->conn->lastInsertId();
	}

	public static function LoadModel(&$model, $filters) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return false;
		}
		$sql='';
		try {
			$where='';
			if(empty($filters)) $filters=[ $model->primary_key => $model->primary_key_value ];
			//if(is_string($filters)) $filters=[ $model->primary_key => $filters ];
			if(!is_array($filters)) $filters=[ $model->primary_key => $filters ];
			if(is_array($filters)) foreach($filters as $fk=>$fv) if($fk!='') $where.=($where=='' ? '' : ' AND ') . $fk . '=' . DB::Quote($fv);
			$cur_language=Strings::GetLanguage();
			$record_found=false;
			$sql="SELECT * FROM " . $model->table . " WHERE {$where} LIMIT 1;";
			foreach(DB::$instance->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
				$record_found=true;
				foreach($row as $k => $v) {
					$model->$k=$v;
					if(substr($k, -3)=="_{$cur_language}") {
						$field_without_language=substr($k, 0, strlen($k)-3);
						$model->$field_without_language=$v;
					}
				}
			}
			if(!$record_found) return false;
			if(!$record_found) {
				$model->GetDBFields();
				$model->FillPredefinedFields();
			}
			return true;
		} catch(Exception $e) {
			ErrorLogger::Write('LoadModel failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage());
			return false;
		}
	}

	public static function DeleteModel(&$model) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return false;
		}
		$sql="DELETE FROM " . $model->table . " WHERE " . $model->primary_key . "='" . $model->primary_key_value . "'; ";
		try {
			$ret=DB::$instance->conn->query($sql, PDO::FETCH_ASSOC);
			if($ret) {
				return [ 'status' => true, 'message' => Strings::Get('Record deleted') ];
			} else {
				ErrorLogger::Write('DeleteModel failed: ' . PHP_EOL . $sql . PHP_EOL);
				return [ 'status' => false, 'message' => Strings::Get('Deletion failed') ];
			}
		} catch(Exception $e) {
			ErrorLogger::Write('DeleteModel failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage());
			return [ 'status' => false, 'message' => Strings::Get('Deletion failed') . PHP_EOL . $e->getMessage() ];
		}
	}

	public static function SaveModel(&$model) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return [ 'status' => false, 'message' => Strings::Get('No connection to database') ];
		}
		$sql='';
		try {
			if(empty($model->primary_key_value)) { // INSERT
				$fields=[];
				foreach($model->GetDBFields() as $field_name=>$field_data) {
					if($field_data['is_primary']) continue;
					$fields[]=$field_name;
				}
				$model->FillPredefinedFields();
				$sql_fields=''; $sql_values='';
				foreach($fields as $field) {
					if($field!=$model->primary_key && property_exists($model, $field)) {
						if($field=='date_created') continue;
						$sql_fields.=(empty($sql_fields) ? '' : ', ') . $field;
						$sql_values.=(empty($sql_values) ? '' : ', ') . (strval($model->$field)=='' || is_null($model->$field) || strval($model->$field)==DB_NULL_STRING ? 'NULL' : ($field=='pass' ? DB::Quote(Strings::EncryptPass($model->$field)) : DB::Quote($model->$field)));
					}
				}
				if(empty($sql_values)) return [ 'status' => false, 'message' => Strings::Get('No fields to update') ];
				$sql="INSERT INTO " . $model->table . " (" . $sql_fields . ") VALUES (" . $sql_values . ");";
				$ret=DB::$instance->conn->query($sql, PDO::FETCH_ASSOC);
				if($ret) {
					$model->primary_key_value=DB::GetLastInsertedId();
					$pk=$model->primary_key;
					$model->$pk=$model->primary_key_value;

					return [ 'status' => true, 'message' => 'OK' ];
				} else {
					ErrorLogger::Write('SaveModel error. SQL:' . PHP_EOL . $sql . PHP_EOL . DB::GetLastError());
					return [ 'status' => false, 'message' => 'Save model error', $sql, DB::GetLastError() ];
				}
			} else { // UPDATE
				$fields=[];
				foreach($model->GetDBFields() as $field_name=>$field_data) {
					if($field_data['is_primary']) continue;
					$fields[]=$field_name;
				}
				$sql_fields='';
				foreach($fields as $field_name) {
					$sql_fields.=($sql_fields=='' ? '' : ', ') . $field_name . '=' . (strval($model->$field_name)=='' || is_null($model->$field_name) || strval($model->$field_name)==DB_NULL_STRING ? 'NULL' : ($field_name=='pass' ? DB::Quote(Strings::EncryptPass($model->$field_name)) : DB::Quote($model->$field_name)));
				}
				$sql="UPDATE {$model->table} SET {$sql_fields} WHERE {$model->primary_key}=" . DB::Quote($model->primary_key_value) . ";";
				$ret=DB::$instance->conn->query($sql, PDO::FETCH_ASSOC);
				return [ 'status' => $ret ? true : false, 'message' => $ret ? $ret : Strings::Get('Update record failed') ];
			}
		} catch(Exception $e) {
			ErrorLogger::Write('SaveModel failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage());
			return [ 'status' => false, 'message' => Strings::Get('Update record failed') . PHP_EOL . $e->getMessage() ];
		}
	}

	public static function InsertModel(&$model) {
		if(empty(DB::$instance)) new DB;
		if(empty(DB::$instance->conn)) {
			ErrorLogger::Write('Cannot query database. No connection.');
			return [ 'status' => false, 'message' => Strings::Get('No connection to database') ];
		}

		$sql='';
		try {
			$fields=[];
			foreach($model->GetDBFields() as $field_name=>$field_data) $fields[]=$field_name;
			$model->FillPredefinedFields();
			$sql_fields=''; $sql_values='';
			foreach($fields as $field) {
				if(property_exists($model, $field)) {
					$sql_fields.=(empty($sql_fields) ? '' : ', ') . $field;
					$sql_values.=(empty($sql_values) ? '' : ', ') . (strval($model->$field)=='' || is_null($model->$field) || strval($model->$field)==DB_NULL_STRING ? 'NULL' : DB::Quote($model->$field));
				}
			}
			if(empty($sql_values)) return [ 'status' => false, 'message' => Strings::Get('No fields to update') ];
			$sql="INSERT INTO " . $model->table . " (" . $sql_fields . ") VALUES (" . $sql_values . ") RETURNING {$model->primary_key}; ";
			foreach(DB::$instance->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
				$model->primary_key_value=$row[$model->primary_key];
				$pk=$model->primary_key;
				$model->$pk=$model->primary_key_value;
			}
			return [ 'status' => true, 'message' => 'OK' ];
		} catch(Exception $e) {
			ErrorLogger::Write('InsertModel failed: ' . PHP_EOL . $sql . PHP_EOL . $e->getMessage());
			return [ 'status' => false, 'message' => Strings::Get('Insertion failed') . PHP_EOL . $e->getMessage() ];
		}
	}

}
