<?php

class SaleSeries extends Model {

	const table='SALE_SERIES';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('SALE_SERIES', 'id', $primary_key_value);
		$this->name='';
	}

	function Save() {
		$record=null;
		// Check if its new record
		if(empty($this->id)) {
			// Only admins and shop managers are allowed to add
			if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set shops manager's company id
			if(Session::IsShopManager()) $this->company_id=Session::User()->company_id;
		}
		$save=parent::Save();
		if($save['status']) {
			// If this is default series, uncheck others
			if($this->is_default) DB::Update("UPDATE SALE_SERIES SET is_default=0 WHERE company_id=" . DB::Quote($this->company_id) . " AND id!=" . $this->id . ";");
		}
		return $save;
	}

	public static function GetDefault($company_id) {
		$sql="SELECT * FROM SALE_SERIES WHERE company_id=" . DB::Quote(Session::IsUser() ? Session::User()->company_id : $company_id) . " AND is_default=1 LIMIT 1;";
		if($rows=DB::Query($sql)) {
			$series=new SaleSeries;
			$series->CreateFromArray($rows[0]);
			return $series;
		}
		return false;
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT * FROM SALE_SERIES WHERE company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) . "  ORDER BY is_default DESC, name ASC;";
		} else if(Session::IsUser()) {
			$sql="SELECT * FROM SALE_SERIES WHERE company_id=" . DB::Quote(Session::User()->company_id) . "  ORDER BY is_default DESC, name ASC;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=SaleSeries::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new SaleSeries;
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