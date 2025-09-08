<?php

class VatCategory extends Model {

	const table='VAT_CATEGORY';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('VAT_CATEGORY', 'id', $primary_key_value);
		$this->name='';
	}

	function Save() {
		// Check if its new record
		if(empty($this->id)) {
			// Only admins and shop managers are allowed to add
			if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set shops manager's company id
			if(Session::IsShopManager()) $this->company_id=Session::User()->company_id;
		}
		$save=parent::Save();
		return $save;
	}

	/* public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT * FROM VAT_CATEGORY WHERE company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) . " ORDER BY name;";
		} else if(Session::IsUser()) {
			$sql="SELECT * FROM VAT_CATEGORY WHERE company_id=" . DB::Quote(Session::User()->company_id) . " ORDER BY name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	} */
	
	
	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT * FROM VAT_CATEGORY ORDER BY name;";
		} else if(Session::IsUser()) {
			$sql="SELECT * FROM VAT_CATEGORY WHERE ORDER BY name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}
	

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			//$rows=VatCategory::GetList(GetRequest('company_id'));
			$rows=VatCategory::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new VatCategory;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update from request
			$model->CreateFromRequest(true);
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