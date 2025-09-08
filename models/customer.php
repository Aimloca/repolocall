<?php

class Customer extends Model {

	const table='CUSTOMER';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('CUSTOMER', 'id', $primary_key_value);
	}

	function SetLanguage($lang) {
		if(!in_array($lang, LANGUAGES)) return false;
		$this->language=array_search($lang, LANGUAGES);
		$save=DB::Update("UPDATE {$this->table} SET language={$this->language} WHERE id={$this->id};");
		return [ 'status' => $save ? true : false, 'message' => $save ? 'OK' : 'Error' ];
	}

	public static function GetList($sql='', $class='') {
		// Check user type
		if(!Session::IsAdmin()) return [];
		$sql="SELECT * FROM CUSTOMER ORDER BY CASE WHEN id=-1 THEN 0 ELSE 1 END ASC,  email;";
		return parent::GetList($sql, $class);
	}

	public static function GetAnonymous() {
		$customer=new Customer;
		if(!$customer->Load(['id'=>-1])) $customer->CreateFromArray([
			'id' => -1,
			'name' => Strings::Get('retail_customer'),
			'email' => 'retail_customer@' . DOMAIN,
			'pass' => 'retail_customer@' . DOMAIN,
			'phone' => '-',
			'language' => Strings::GetLanguageIndex(),
			'activated' => 1,
			'active' => 1,
			'date_created' => date('Y-m-d H:i:s'),
		]);
		Account::SetPriviledges($customer);
		return $customer;
	}

	public static function Register($email, $pass, $name, $phone) {
		// Check email
		$email=trim(strtolower($email));
		if(empty($email)) return new Response(false, Strings::Get('error_no_username'));
		// Validate email
		if(!ValidateEmail($email)) return new Response(false, Strings::Get('invalid_email_address'));
		// Check pass
		if(empty($pass)) return new Response(false, Strings::Get('error_no_password'));
		// Check name
		$name=trim($name);
		if(empty($name)) return new Response(false, Strings::Get('error_no_name'));
		// Check phone
		$phone=Strings::KeepOnlyNumbers($phone);
		if(empty($phone)) return new Response(false, Strings::Get('error_no_phone'));
		if(strlen($phone)!=10 && strlen($phone)!=12) return new Response(false, Strings::Get('error_invalid_phone'));
		if(strlen($phone)==10) $phone="30{$phone}";
		// Check if email exists in other customer
		$model=new Customer;
		if($model->Load(['email'=>$email])) return new Response(false, Strings::Get('error_customer_already_exists'));
		$model=new Customer;
		$model->email=$email;
		$model->pass=$pass;
		$model->name=$name;
		$model->phone=$phone;
		$model->activated=0;
		$model->language=Strings::GetLanguageIndex();
		$save=$model->Save();
		if($save['status']) {
			// Get mail subject
			$mail_subject=Strings::Get('mail_subject_customer_registration');

			// Fix mail body
			$mail_body=Strings::GetForm('customer.register');
			$mail_body=str_replace('#LINK_URL#', $_SERVER['PROTOCOL'] . BASE_URL . '?eus=' . urlencode(Strings::EncryptUrlSegment('customer/activate/?un=' . $email . '&ui=' . $model->id)), $mail_body);

			// Send email
			$email_result=SendEmail($email, $mail_subject, $mail_body);
		}
		return new Response($save['status'], Strings::Get($save['status'] ? 'customer_registered_successfully' : 'error_registation_failed'));
	}

	public static function LoginAnonymous() {
		Session::Set('customer', Customer::GetAnonymous());
	}

	public static function NotifyWaitersToPay($order, $by_cash) {
		// Check order
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_string($order)) $order=@json_decode($order);
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_array($order)) $order=json_decode(json_encode($order));
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(empty($order->tables_ids)) return new Response(false, Strings::Get('error_no_order_tables'));
		if(empty($order->products)) return new Response(false, Strings::Get('error_no_products'));
		$server_order=new Order;
		if(!empty($order->id) && substr($order->id, 0, 1)!='-' && !$server_order->Load(['id' => $order->id])) return new Response(false, Strings::Get('error_no_order_with_this_id'));
		$waiters_ids=[];
		$server_order->GetData();
		$occasion_hash=md5(time() . "#{$server_order->id}#{$server_order->tables_ids_str}#{$server_order->waiters_ids_str}");
		// Loop through waiters and users with customer notification
		$users_to_notify=array_merge($server_order->waiters, $server_order->GetUsersWithCustomerNotification());
		foreach($users_to_notify as $waiter) {
			if(!in_array($waiter->id, $waiters_ids)) {
				$models_data=[$server_order, $waiter]; foreach($server_order->tables as $table) $models_data[]=$table;
				$waiters_ids[]=$waiter->id;
				$notification=new Notification;
				$notification->from_company_customer_id=Session::CustomerId();
				$notification->to_user_id=$waiter->id;
				$notification->date_sent=date('Y-m-d H:i:s');
				$notification->occasion_hash=$occasion_hash;
				foreach(LANGUAGES as $lang) {
					$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get($by_cash ? 'notification_pay_by_cash_title' : 'notification_pay_by_card_title', $lang), $models_data);
					$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get($by_cash ? 'notification_pay_by_cash_message' : 'notification_pay_by_card_message', $lang), $models_data);
				}
				$notification->buttons=Notification::GetGotItButtonArrayJson(LANGUAGES[$waiter->language]);
				$notification->Save();
			}
		}
		if(empty($waiters_ids)) return new Response(false, Strings::Get('error_no_waiter_for_this_order'));
		return new Response(true, Strings::Get('waiter_notification_for_pay_by_cash_is_sent'), $waiters_ids);
	}

	public static function CallWaiter($table_id, $waiter_id) {
		// Check table
		if(empty($table_id)) return new Response(false, Strings::Get('error_no_table'));
		$table=new Table;
		if(!$table->Load(['id'=>$table_id])) return new Response(false, Strings::Get('error_invalid_table'));
		$table->GetData();
		$occasion_hash=md5(time() . "#{$table->waiters_ids_str}");
		$recipients_ids=[];
		// Check waiter
		if(!empty($waiter_id)) {
			$waiter=new User;
			if(!$waiter->Load(['id' => $waiter_id])) return new Response(false, Strings::Get('error_invalid_waiter'));
			if($waiter->position!=3) return new Response(false, Strings::Get('error_invalid_waiter'));
			if($waiter->company_id!=$table->company_id) return new Response(false, Strings::Get('error_invalid_waiter'));
			// Create notification
			$notification=new Notification;
			$notification->from_company_customer_id=Session::CustomerId();
			$notification->to_user_id=$waiter->id;
			$notification->date_sent=date('Y-m-d H:i:s');
			$notification->occasion_hash=$occasion_hash;
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_title', $lang), [$table, $waiter]);
				$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_message', $lang), [$table, $waiter]);
			}
			$notification->buttons=Notification::GetGotItButtonArrayJson(LANGUAGES[$waiter->language]);
			$notification->Save();
			$recipients_ids[]=$waiter->id;
		}

		// Check table waiters
		if(!empty($table->waiters)) foreach($table->waiters as $user) {
			if(in_array($user->id, $recipients_ids)) continue;
			// Create notification
			$notification=new Notification;
			$notification->from_company_customer_id=Session::CustomerId();
			$notification->to_user_id=$user->id;
			$notification->date_sent=date('Y-m-d H:i:s');
			$notification->occasion_hash=$occasion_hash;
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_title', $lang), [$table, $user]);
				$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_message', $lang), [$table, $user]);
			}
			$notification->buttons=Notification::GetGotItButtonArrayJson(LANGUAGES[$user->language]);
			$notification->Save();
			$recipients_ids[]=$user->id;
		}

		// Check if there are users with customer notification enabled
		if($table->users_with_customer_notification) foreach($table->users_with_customer_notification as $user) {
			if(in_array($user->id, $recipients_ids)) continue;
			// Create notification
			$notification=new Notification;
			$notification->from_company_customer_id=Session::CustomerId();
			$notification->to_user_id=$user->id;
			$notification->date_sent=date('Y-m-d H:i:s');
			$notification->occasion_hash=$occasion_hash;
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_title', $lang), [$table, $user]);
				$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_call_waiter_message', $lang), [$table, $user]);
			}
			$notification->buttons=Notification::GetGotItButtonArrayJson(LANGUAGES[$user->language]);
			$notification->Save();
			$recipients_ids[]=$user->id;
		}
		return new Response(!empty($recipients_ids), empty($recipients_ids) ? Strings::Get('error_waiter_cannot_be_notified') : Strings::Get('waiter_is_notified'), $recipients_ids);
	}

	public static function GetSubmittedOrder($order_id) {
		// Check order
		if(empty($order_id)) return new Response(false, Strings::Get('error_no_order_id'));
		if(!is_numeric($order_id)) return new Response(false, Strings::Get('error_invalid_order_id'));
		if($order_id<=0) return new Response(false, Strings::Get('error_invalid_order_id'));
		$order=new Order;
		if(!$order->Load(['id'=>$order_id])) return new Response(false, Strings::Get('error_invalid_order'));
		$order->GetData();
		return new Response(true, 'OK', $order);
	}

	public static function AdditionalOrder($order) {
		// Check order
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_string($order)) $order=@json_decode($order);
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_array($order)) $order=json_decode(json_encode($order));
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(empty($order->tables_ids)) return new Response(false, Strings::Get('error_no_order_tables'));
		if(empty($order->products)) return new Response(false, Strings::Get('error_no_products'));
		$server_order=new Order;
		if(!empty($order->id) && substr($order->id, 0, 1)!='-') {
			if(!$server_order->Load(['id' => $order->id])) return new Response(false, Strings::Get('error_no_order_with_this_id'));
			if($server_order->completed) $server_order=new Order;
		}

		$server_order->customer_id=Session::CustomerId();
		$server_order->GetTables();
		$server_order->customer_order=1;
		if(empty($order->company_id) && empty($server_order->company_id)) return new Response(false, Strings::Get('error_no_company_is_set'));
		if(empty($server_order->id)) {
			$server_order->company_id=$order->company_id;
			$server_order->tables_ids=$order->tables_ids;
			$server_order->session_id=Session::IsCustomer() ? Session::CustomerId() : Session::AccountId();
			if(empty($server_order->waiter_id) && !empty($server_order->tables_ids)) {
				$tables_ids=is_string($server_order->tables_ids) ? $server_order->tables_ids : implode(',', $server_order->tables_ids);
				if($rows=DB::Query("SELECT user_id FROM WAITER_TABLES WHERE table_id IN ({$tables_ids});")) {
					if(count($rows)==1) $server_order->waiter_id=$rows[0]['user_id'];
				}
			}
		}
		$products_to_order=[];
		foreach($order->products as $product_index=>$product) if(!isset($product->ordered) || !$product->ordered) $products_to_order[]=$product;
		if(empty($products_to_order)) return new Response(false, Strings::Get('error_no_products'));

		try {
			// Begin transaction
			DB::BeginTransaction();
			$save=$server_order->Save();
			if(!$save['status']) throw new Exception(empty($save['message']) ? Strings::Get('error_saving_order') : $save['message']);
			$result=$server_order->SetTablesIds($order->tables_ids);
			if(!$result->status) throw new Exception($result->message);
			foreach($products_to_order as $product_index=>$product) {
				$product->ordered=1;
				$result=$server_order->AddProduct($product);
				if(!$result->status) throw new Exception($result->message);
			}
			$server_order->GetAmountsAndSave();
			$server_order->RecalculateProductsAmount();

			// Commit transaction
			DB::CommitTransaction();
			$server_order->Load(['id'=>$server_order->id]);
			$server_order->GetData();

			$products_to_order_str='';
			foreach($products_to_order as $product_index=>$product) {
				$product_model=new Product;
				if(!$product_model->Load(['id'=>$product->product_id])) continue;
				$products_to_order_str.="\n{$product->quantity} {$product->unit->name} {$product_model->name_gr}";
			}

			// Notify waiters and users with customer notification
			$waiters_ids=[];
			$occasion_hash=md5(time() . "#{$server_order->id}#{$server_order->tables_ids_str}#{$server_order->waiters_ids_str}");
			// Loop through waiters and users with customer notification
			$users_to_notify=array_merge($server_order->waiters, $server_order->GetUsersWithCustomerNotification());
			foreach($users_to_notify as $waiter) {
				if(!in_array($waiter->id, $waiters_ids)) {
					$models_data=[$server_order, $waiter]; foreach($server_order->tables as $table) $models_data[]=$table;
					$waiters_ids[]=$waiter->id;
					$notification=new Notification;
					$notification->from_company_customer_id=Session::CustomerId();
					$notification->to_user_id=$waiter->id;
					$notification->date_sent=date('Y-m-d H:i:s');
					$notification->occasion_hash=$occasion_hash;
					foreach(LANGUAGES as $lang) {
						$notification_title=str_replace('#PRODUCTS#', $products_to_order_str, Strings::Get('notification_user_additional_order_title', $lang));
						$notification_message=str_replace('#PRODUCTS#', $products_to_order_str, Strings::Get('notification_user_additional_order_message', $lang));
						$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields($notification_title, $models_data);
						$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields($notification_message, $models_data);
					}
					//$notification->buttons=Notification::GetGotItButtonArrayJson(LANGUAGES[$waiter->language]);
					$notification->Save();
				}
			}

			return new Response(true, 'OK', $server_order);
		} catch(Exception $e) {
			DB::RollBackTransaction();
			return new Response(false, $e->getMessage());
		}
	}

	public static function RefreshOrdered($order_id) {
		// Check order id
		if(empty($order_id)) return new Response(false, Strings::Get('error_no_order'));
		$order=new Order;
		if(!$order->Load(['id' => $order_id])) return new Response(false, Strings::Get('error_no_order_with_this_id'));
		$order->GetData();
		return new Response(true, 'OK', $order);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Customer::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);
		} else if($action=='register') {
			return Customer::Register(GetRequest('un'), GetRequest('ps'), GetRequest('name'), GetRequest('phone'));
		} else if($action=='additional_order') {
			return Customer::AdditionalOrder(GetRequest('order'));
		} else if($action=='refresh_ordered') {
			return Customer::RefreshOrdered(GetRequest('id'));
		} else if($action=='notify_pay_by_cash') {
			return Customer::NotifyWaitersToPay(GetRequest('order'), true);
		} else if($action=='notify_pay_by_card') {
			return Customer::NotifyWaitersToPay(GetRequest('order'), false);
		} else if($action=='call_waiter') {
			return Customer::CallWaiter(GetRequest('table_id'), GetRequest('waiter_id'));
		} else if($action=='get_submitted_order') {
			return Customer::GetSubmittedOrder(GetRequest('id'));
		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'CUSTOMER',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin(),
				'allow_import' => Session::IsAdmin(),
		]);
	}
}