<?php

class DayEnd extends Model {

	const table='DAY_END';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('DAY_END', 'id', $primary_key_value);
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

	public static function GetLast($company_id='') {
		if(Session::IsUser()) $company_id=Session::User()->company_id;
		if(empty($company_id)) return null;
		$rows=DB::Query("SELECT day_end FROM DAY_END WHERE company_id=" . DB::Quote($company_id) . " AND day_end<" . DB::Quote(date('Y-m-d H:i:s')) . " ORDER BY day_end DESC LIMIT 1;");
		if($rows) return $rows[0]['day_end'];
		return null;
	}

	public static function GetList($company_id='', $class='') {

		// Check user type
		if(Session::IsAdmin()) {
			$where="company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id));
		} else if(Session::IsUser()) {
			$where="company_id=" . DB::Quote(Session::User()->company_id);
		} else { // No company id is set
			return [];
		}
		$sql="SELECT *, DATE_FORMAT(day_end, '%d/%m/%Y %H:%i') AS day_end_fixed FROM DAY_END WHERE {$where} ORDER BY day_end_fixed;";
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=DayEnd::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new DayEnd;
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