<?php

class Room extends Model {

	const table='ROOM';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('ROOM', 'id', $primary_key_value);
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
		/*
		// Check included-excluded tables
		if($save && $save['status'] && !empty($this->id) && (isset($this->included_ids) || isset($this->excluded_ids)) {
			$sql='';
			if(!empty(json_decode($this->included_ids))) foreach(json_decode($this->included_ids) as $i=>$id) {
				$sql.="UPDATE TABLES SET room_id=" . DB::Quote($this->id) . " WHERE id=" . DB::Quote($id) . ";";
			}
			if(!empty(json_decode($this->excluded_ids))) foreach(json_decode($this->excluded_ids) as $i=>$id) {
				$sql.="UPDATE TABLES SET room_id=0 WHERE id=" . DB::Quote($id) . ";";
			}
			$sql="DELETE FROM ROOM_TABLE WHERE room_id=" . DB::Quote($this->id) . ";\n" . ($sql=='' ? '' : "INSERT INTO ROOM_TABLE (room_id, table_id) VALUES {$sql}'");
diep($sql);
			DB::Query($sql);
		}
		*/
		return $save;
	}

	function GetTables() {
		$this->tables=[];
		$sql="SELECT * FROM TABLES WHERE room_id=" . DB::Quote($this->id) . " ORDER BY sorting;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$table=new Table;
			$this->tables[]=$table->CreateFromArray($row);
		}
		return $this->tables;
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT *, (SELECT COUNT(id) FROM TABLES WHERE room_id=ROOM.id) AS tables_count FROM ROOM WHERE company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) . " ORDER BY name;";
		} else if(Session::IsUser()) {
			$sql="SELECT *, (SELECT COUNT(id) FROM TABLES WHERE room_id=ROOM.id) AS tables_count FROM ROOM WHERE company_id=" . DB::Quote(Session::User()->company_id) . " ORDER BY name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public function FixSorting($changed_table_id=0) {
		$update_sql='';
		$sorting=0;
		$sql="
			SELECT id, sorting
			FROM TABLES
			WHERE room_id=" . DB::Quote($this->id) . "
			ORDER BY sorting
				" . ($changed_table_id ? ", CASE WHEN id=" . DB::Quote($changed_table_id) . " THEN 0 ELSE 1 END ASC" : "") . ";
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			$sorting++;
			$update_sql.="UPDATE TABLES SET sorting=" . DB::Quote($sorting) . " WHERE id=" . DB::Quote($row['id']) . ";\n";
		}
		if($update_sql) {
			$result=DB::Update($update_sql, true);
			return new Response($result, $result ? 'OK' : str_replace(['#ROOM.id#', '#ROOM.name#'], [$this->id, $this->name], Strings::Get('update_room_table_sorting_failed')), $update_sql);
		}
		return new Response(false, Strings::Get('room_has_no_tables'));
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Room::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Room;
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