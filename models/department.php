<?php

class Department extends Model {

	const table='DEPARTMENT';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('DEPARTMENT', 'id', $primary_key_value);
		$this->name='';
	}

	function Save() {
		$record=null;
		// Check if its new record
		if(empty($this->id)) {
			// Only admins and shop managers are allowed to add department
			if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set shops manager's company id
			if(Session::IsShopManager()) $this->company_id=Session::User()->company_id;
		}
		$save=parent::Save();
		return $save;
	}

	function GetUsers($only_active=true) {
		$this->users=[];
		$sql="SELECT * FROM USER WHERE position=2 AND department_id=" . DB::Quote($this->id) . " " . ($only_active ? 'AND active=1' : '') . " ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$this->users[]=$user;
		}
		return $this->users;
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name FROM DEPARTMENT WHERE company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) . " ORDER BY name;";
		} else if(Session::IsUser()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name FROM DEPARTMENT WHERE company_id=" . DB::Quote(Session::User()->company_id) . " ORDER BY name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Department::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));

			// Load from db
			$model=new Department;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update from request
			$model->CreateFromRequest();
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => self::class::table,
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}