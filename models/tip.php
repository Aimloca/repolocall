<?php

class Tip extends Model {

	const table='TIP';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('TIP', 'id', $primary_key_value);
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

	public static function GetList($company_id='', $class='') {

		// Check user type
		if(Session::IsAdmin()) {
			$where="company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id));
		} else if(Session::IsUser()) {
			$where="company_id=" . DB::Quote(Session::User()->company_id);
		} else if(!empty($company_id)){ // Get company specific tips for customer
			$where="company_id=" . DB::Quote($company_id);
		} else { // No company id is set
			return [];
		}
		$sql="
			SELECT *,
				(CASE WHEN type=0 THEN " . DB::Quote(Strings::Get('tip_type_amount')) . " ELSE " . DB::Quote(Strings::Get('tip_type_percent')) . " END) AS type_str
			FROM TIP
			WHERE {$where} ORDER BY type, value;
		";
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Tip::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Tip;
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