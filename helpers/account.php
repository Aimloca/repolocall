<?
class Account {

	public static function Login($email='', $pass='') {
		// Check email
		if(empty($email)) return new Response(false, Strings::Get("Username is empty"));
		// Validate email
		if(!ValidateEmail($email)) return new Response(false, Strings::Get("Invalid username. Must be email"));
		// Check pass
		if(empty($pass)) return new Response(false, Strings::Get("Password is empty"));
		// Encrypt pass
		$pass=Strings::EncryptPass($pass);
		// Clear session
		Session::Remove('admin');
		Session::Remove('user');
		Session::Remove('customer');
		// Search in admins
		$rows=DB::Query("SELECT id FROM ADMIN WHERE email=" . DB::Quote($email) . " AND pass=" . DB::Quote($pass) . " LIMIT 1;");
		if($rows) {
			$u=new Admin;
			$u->Load($rows[0]['id']);
			if(!empty($u->id)) {
				Account::SetPriviledges($u);
				Session::Add('admin', $u);
				Strings::SetLanguage($u->language);
				// Create default company and company customer if needed
				CompanyCustomer::GetDefault();
				return new Response(true, Strings::Get("Login success"), $_SESSION['admin']);
			}
		}
		// Search in users
		$rows=DB::Query("SELECT id FROM USER WHERE email=" . DB::Quote($email) . " AND pass=" . DB::Quote($pass) . " LIMIT 1;");
		if($rows) {
			$u=new User;
			$u->Load($rows[0]['id']);
			if(!empty($u->id)) {
				if(!$u->active) return new Response(false, Strings::Get('error_user_is_inactive'));
				Account::SetPriviledges($u);
				Session::Add('user', $u);
				Strings::SetLanguage($u->language);
				return new Response(true, Strings::Get("Login success"), $_SESSION['user']);
			}
		}
		// Search in customers
		$rows=DB::Query("SELECT id FROM CUSTOMER WHERE email=" . DB::Quote($email) . " AND pass=" . DB::Quote($pass) . " LIMIT 1;");
		if($rows) {
			$u=new Customer;
			$u->Load($rows[0]['id']);
			if(!empty($u->id)) {
				if(!$u->active) return new Response(false, Strings::Get('error_customer_is_inactive'));
				Account::SetPriviledges($u);
				Session::Add('customer', $u);
				Strings::SetLanguage($u->language);
				return new Response(true, Strings::Get("Login success"), $_SESSION['customer']);
			}
		}
		return new Response(false, Strings::Get("Invalid login credentials"));
	}

	public static function Logout() {
		Session::Destroy();
		return new Response(true, 'OK');
	}

	public static function SetPriviledges($model) {
		if(get_class($model)=='Admin') {
			$model->can_create_order=1;
			$model->can_view_order=1;
			$model->can_edit_order=1;
			$model->can_send_products_to_departments=1;
			$model->can_mark_products_prepared=1;
			$model->can_mark_products_delivered=1;
			$model->can_make_payment=1;
			$model->can_be_paid=1;
			$model->can_create_document=1;
			$model->can_view_document=1;
			$model->can_edit_document=1;
			$model->can_cancel_order_items=1;
			$model->can_cancel_order=1;
			$model->can_complete_order=1;
			$model->can_set_waiters=1;
		} else if(get_class($model)=='User') { // User position: 0=Shop manager, 1=Barista, 2=Preparation, 3=Waiter
			$model->can_create_order=in_array($model->position, [0, 1, 3]);
			$model->can_view_order=1;
			$model->can_edit_order=in_array($model->position, [0, 1, 3]);
			$model->can_send_products_to_departments=in_array($model->position, [0, 1, 3]);
			$model->can_mark_products_prepared=1;
			$model->can_mark_products_delivered=1;
			$model->can_make_payment=in_array($model->position, [0, 1, 3]);
			$model->can_be_paid=in_array($model->position, [0, 1, 3]);
			$model->can_create_document=in_array($model->position, [0, 1]);
			$model->can_view_document=in_array($model->position, [0, 1]);
			$model->can_edit_document=in_array($model->position, [0, 1]);
			$model->can_cancel_order=in_array($model->position, [0, 1]);
			$model->can_cancel_order_items=1;
			$model->can_complete_order=in_array($model->position, [0, 1, 3]);
			$model->can_set_waiters=in_array($model->position, [0, 1]);
		} else {
			$model->can_create_order=0;
			$model->can_view_order=0;
			$model->can_edit_order=0;
			$model->can_send_products_to_departments=0;
			$model->can_mark_products_prepared=0;
			$model->can_mark_products_delivered=0;
			$model->can_make_payment=0;
			$model->can_be_paid=0;
			$model->can_create_document=0;
			$model->can_view_document=0;
			$model->can_edit_document=0;
			$model->can_cancel_order=0;
			$model->can_cancel_order_items=0;
			$model->can_complete_order=0;
			$model->can_set_waiters=0;
		}
	}

	public static function Forgot($username='') {

		// Check email
		if(empty($username)) return new Response(false, Strings::Get('error_no_username'));

		// Validate email
		if(!ValidateEmail($username)) return new Response(false, Strings::Get('invalid_email_address'));

		// Search in admins with email
		$m=new Admin;
		if(!$m->Load(['email'=>$username])) {
			// Search in users with email
			$m=new User;
			if($m->Load(['email'=>$username])) {
				// User found
				if(!$m->active) return new Response(false, Strings::Get('error_user_is_inactive'));
			} else {
				// Search in customers with email
				$m=new Customer;
				if($m->Load(['email'=>$username])) {
					// Customer found
					if(!$m->active) return new Response(false, Strings::Get('error_customer_is_inactive'));
				} else {
					// No user or customer found
					return new Response(false, str_replace('#EMAIL#', $username, Strings::Get('error_no_account_found_with_email_#EMAIL#')));
				}
			}
		}

		if(empty($m->id)) return new Response(false, str_replace('#EMAIL#', $username, Strings::Get('no_user_found_with_email_address_#EMAIL#')));

		// Get mail subject
		$mail_subject=Strings::Get('mail_subject_password_reset_request');

		// Fix mail body
		$mail_body=Strings::GetForm('account.forgot');
		$mail_body=str_replace('#LINK_URL#', $_SERVER['PROTOCOL'] . BASE_URL . '?eus=' . urlencode(Strings::EncryptUrlSegment('account/change/?un=' . $username . '&ui=' . $m->id)), $mail_body);

		// Send email
		$email_result=SendEmail($username, $mail_subject, $mail_body);
		if($email_result['status']) {
			return new Response(true, Strings::Get('password_reset_request_email_sent_successfully'));
		} else {
			return new Response(false, Strings::Get('password_reset_request_email_not_sent') . ' ' . error_get_last());
		}
	}

	public static function GetNotifications($only_unread=false) {
		$notifications=[];
		if(!Session::IsLoggedIn()) return new Response(false, 'error_insufficient_rights');
		else if(Session::IsAdmin()) $model_field='to_admin_id=' . Session::AdminId();
		else if(Session::IsUser()) $model_field='to_user_id=' . Session::UserId();
		else if(Session::IsCustomer()) $model_field=(Session::SelectedTable() ? 'to_company_customer_id=' : 'to_customer_id=') . Session::CustomerId();
		else return $notifications;
		$sql="
			SELECT *, title_" . Strings::GetLanguage() . " AS title, message_" . Strings::GetLanguage() . " AS message
			FROM NOTIFICATION
			WHERE 	({$model_field} OR to_session=" . DB::Quote(session_id()) . ")
				AND date_deleted IS NULL
				AND visible=1
				" . ($only_unread ? 'AND date_read IS NULL' : '') . "
			ORDER BY date_sent DESC;
		";
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row) {
			$notification=new Notification;
			$notification->CreateFromArray($row);
			$notification->FixDates();
			$notifications[]=$notification;
		}
		return new Response(true, empty($notifications) ? Strings::Get('no_notifications_found') : str_replace('#COUNT#', count($notifications), Strings::Get('found_#COUNT#_notifications')), $notifications);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='login') {
			return Account::Login(trim(GetRequest('un')), GetRequest('ps'));
		} else if($action=='logout') {
			return Account::Logout();
		} else if($action=='forgot') {
			return Account::Forgot(trim(GetRequest('un')));
		} else if($action=='keep_alive') {
			return new Response(true, 'OK');
		} else if($action=='notifications') {
			return Account::GetNotifications(false);
		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'USER',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}
