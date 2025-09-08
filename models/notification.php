<?php

class Notification extends Model {

	const table='NOTIFICATION';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('NOTIFICATION', 'id', $primary_key_value);
		$this->title='';
		$this->message='';
	}

	function Save() {
		if(empty($this->from_session) && session_status()!==PHP_SESSION_NONE) $this->from_session=session_id();
		if(empty($this->date_sent)) $this->date_sent=date('Y-m-d H:i:s');
		$save=parent::Save();
		return $save;
	}

	function Delete() {
		if(Session::IsAdmin()) return DB::DeleteModel($this);
		$this->date_deleted=date('Y-m-d H:i:s');
		$save=$this->Save();
		return [ 'status' => $save['status'], 'message' => $save['status'] ? Strings::Get('Record deleted') : $save['message'] ];
	}

	function FixDates() {
		$this->date_created_str=Strings::FixDateAgo($this->date_created);
		$this->date_sent_str=Strings::FixDateAgo($this->date_sent);
		$this->date_read_str=Strings::FixDateAgo($this->date_read);
		$this->date_actioned_str=Strings::FixDateAgo($this->date_actioned);
		$this->date_deleted_str=Strings::FixDateAgo($this->date_deleted);
	}

	function Actioned($button_index='') {
		// Check deleted
		if(!empty($this->date_deleted)) return new Response(false, Strings::Get('error_notification_is_marked_as_deleted'));
		// Check actioned
		if(!empty($this->date_actioned)) return new Response(false, Strings::Get('error_notification_is_marked_as_actioned'));
		// Set read date
		if(empty($this->date_read)) $this->date_read=date('Y-m-d H:i:s');
		// Set action date
		if(empty($this->date_actioned)) $this->date_actioned=date('Y-m-d H:i:s');
		$button_actioned=false;
		// Get buttons json
		if(!empty($this->buttons) && $button_index!='' && is_numeric($button_index)) {
			$buttons=json_decode($this->buttons);
			if(!empty($buttons) && isset($buttons[$button_index])) {
				$buttons[$button_index]->actioned=1;
				$this->buttons=json_encode($buttons);
				$button_actioned=true;
			}
		}
		// Save
		$save=$this->Save();
		if($save['status']) {
			if($button_actioned && !empty($this->occasion_hash)) Notification::MarkOccasion($this->occasion_hash, false, false, true, $this->id);
			return new Response(true, Strings::Get('data_saved'));
		} else {
			return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
		}
	}

	public static function GetGotItButton($lang) {
		return [
			'text' => Strings::Get('ok_got_it', $lang),
			'action' => "
				if($(button).hasClass('actioned')) return;
				$('#view_notifications_list').css('filter', 'blur(5px)');
				Post('" . API_URL . "', { controller: 'notification', action: 'actioned', id: $(button).attr('notification_id'), button_index: $(button).attr('button_index') }, GetNotifications);
			"
		];
	}

	public static function GetGotItButtonArray($lang) {
		return [ Notification::GetGotItButton($lang) ];
	}

	public static function GetGotItButtonArrayJson($lang) {
		return json_encode(Notification::GetGotItButtonArray($lang));
	}

	public static function GetList($sql='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="
				SELECT A.*, A.title_" . Strings::GetLanguage() . " AS title, A.message_" . Strings::GetLanguage() . " AS message FROM (
					SELECT *, type='IN' FROM NOTIFICATION WHERE to_admin_id=" . DB::Quote(Session::AdminId()) . "
					UNION ALL
					SELECT *, type='OUT' FROM NOTIFICATION WHERE from_admin_id=" . DB::Quote(Session::AdminId()) . "
				) AS A
				ORDER BY A.date_created DESC, A.title, A.message;
			";
		} else if(Session::IsUser()) {
			$sql="
				SELECT A.*, A.title_" . Strings::GetLanguage() . " AS title, A.message_" . Strings::GetLanguage() . " AS message FROM (
					SELECT *, type='IN' FROM NOTIFICATION WHERE to_user_id=" . DB::Quote(Session::UserId()) . "
					UNION ALL
					SELECT *, type='OUT' FROM NOTIFICATION WHERE from_user_id=" . DB::Quote(Session::UserId()) . "
				) AS A
				ORDER BY A.date_created DESC, A.title, A.message;
			";
		} else if(Session::IsCustomer()) {
			$sql="
				SELECT A.*, A.title_" . Strings::GetLanguage() . " AS title, A.message_" . Strings::GetLanguage() . " AS message FROM (
					SELECT *, type='IN' FROM NOTIFICATION WHERE to_customer_id=" . DB::Quote(Session::CustomerId()) . "
					UNION ALL
					SELECT *, type='OUT' FROM NOTIFICATION WHERE from_customer_id=" . DB::Quote(Session::CustomerId()) . "
				) AS A
				ORDER BY A.date_created DESC, A.title, A.message;
			";
		} else {
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function GetListAdmin($company_id='') {
		if(Session::IsShopManager()) $company_id=Session::User()->company_id;
		if(empty($company_id)) {
			$sql="
				SELECT NOTIFICATION.*, title_" . Strings::GetLanguage() . " AS title, message_" . Strings::GetLanguage() . " AS message, USER.name AS to_user_name
				FROM NOTIFICATION
				LEFT JOIN USER ON NOTIFICATION.to_user_id=USER.id
				ORDER BY NOTIFICATION.date_created DESC, title, message;
			";
		} else {
			$sql="
				SELECT NOTIFICATION.*, title_" . Strings::GetLanguage() . " AS title, message_" . Strings::GetLanguage() . " AS message, USER.name AS to_user_name
				FROM NOTIFICATION
				LEFT JOIN USER ON NOTIFICATION.to_user_id=USER.id
				WHERE (
					from_company_id=" . DB::Quote($company_id) . "
					OR to_company_id=" . DB::Quote($company_id) . "
					OR from_user_id IN (SELECT id FROM USER WHERE company_id=" . DB::Quote($company_id) . ")
					OR to_user_id IN (SELECT id FROM USER WHERE company_id=" . DB::Quote($company_id) . ")
				) " . (Session::IsAdmin() ? "" : " AND date_deleted IS NULL ") . "
				ORDER BY NOTIFICATION.date_created DESC, title, message;
			";
		}
		return parent::GetList($sql, '');
	}

	public static function MarkOccasion($occasion_hash, $read, $actioned, $deleted, $exclude_id=0) {
		if(empty($occasion_hash)) return false;
		$result=false;
		if($read) $result=DB::Update("UPDATE NOTIFICATION SET date_read=" . DB::Quote(date('Y-m-d H:i:s')) . " WHERE date_read IS NULL AND occasion_hash=" . DB::Quote($occasion_hash) . " " . (empty($exclude_id) ? "" : "AND id!=" . DB::Quote($exclude_id)) . ";");
		if($actioned) $result=DB::Update("UPDATE NOTIFICATION SET date_actioned=" . DB::Quote(date('Y-m-d H:i:s')) . " WHERE date_actioned IS NULL AND occasion_hash=" . DB::Quote($occasion_hash) . " " . (empty($exclude_id) ? "" : "AND id!=" . DB::Quote($exclude_id)) . ";");
		if($deleted) $result=DB::Update("UPDATE NOTIFICATION SET date_deleted=" . DB::Quote(date('Y-m-d H:i:s')) . " WHERE date_deleted IS NULL AND occasion_hash=" . DB::Quote($occasion_hash) . " " . (empty($exclude_id) ? "" : "AND id!=" . DB::Quote($exclude_id)) . ";");
		return $result;
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Notification::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);
		} else if($action=='list_admin') {
			if(!Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			$rows=Notification::GetListAdmin(Session::IsShopManager() ? Session::User()->company_id : GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='read') {
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_notification_id_is_missing'));
			// Load from db
			$model=new Notification;
			// Get from database
			if(!$model->Load(['id'=>$id])) return new Response(false, Strings::Get('error_notification_not_found'));
			// Set read date
			$model->date_read=date('Y-m-d H:i:s');
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='delete') {
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_notification_id_is_missing'));
			// Load from db
			$model=new Notification;
			// Get from database
			if(!$model->Load(['id'=>$id])) return new Response(false, Strings::Get('error_notification_not_found'));
			// Set delete date
			$model->date_deleted=date('Y-m-d H:i:s');
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_deleted'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_deleted') . PHP_EOL . $save['message']);
			}

		} else if($action=='actioned') {
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_notification_id_is_missing'));
			// Load from db
			$model=new Notification;
			// Get from database
			if(!$model->Load(['id'=>$id])) return new Response(false, Strings::Get('error_notification_not_found'));
			return $model->Actioned(GetRequest('button_index'));

		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'NOTIFICATION',
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}