<?php

class Table extends Model {

	const table='TABLES';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('TABLES', 'id', $primary_key_value);
		$this->waiters=[];
		$this->waiters_ids=[];
		$this->users_with_customer_notification=[];
		$this->users_with_customer_notification_ids=[];
		$this->room_name='';
		$this->room=null;
	}

	function Save() {
		$record=null;
		// Check if its new record
		if(empty($this->id)) {
			// Only admins and company managers are allowed to add table
			if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set shops manager's company id
			if(Session::IsShopManager()) $this->company_id=Session::User()->company_id;
			// Set temporary qr_code if needed
			if(empty($this->qr_code)) $this->qr_code='0';
		}
		$save=parent::Save();
		if($save && $save['status']) {
			// Check qr code
			if(empty($this->qr_code)) $this->RenewQRCode();
			// Fix sorting
			$room=new Room; if($room->Load(['id'=>$this->room_id])) $room->FixSorting($this->id);
		}
		return $save;
	}

	function GetData() {
		$this->GetRoom();
		$this->GetWaiters();
		$this->GetUsersWithCustomerNotification();
		$this->GetOrder(true);
		return $this;
	}

	function GetOrder($full_data=false) {
		$this->order_id=null;
		$this->order=null;
		$sql="
			SELECT *
			FROM ORDERS
			WHERE 	id IN (SELECT order_id FROM ORDER_TABLE WHERE table_id=" . DB::Quote($this->id) . ")
					AND company_id=" . DB::Quote($this->company_id) . "
					AND completed=0
			ORDER BY date_created
			LIMIT 1;
		";
//if($this->id==51) diep($sql);
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row_index=>$row) {
			$this->order_id=$row['id'];
			$this->order=new Order;
			$this->order->CreateFromArray($row);
			if($full_data) $this->order->GetData();
		}
		return $this->order;
	}

	function GetOrderId() {
		$this->order_id=null;
		$sql="
			SELECT id
			FROM ORDERS
			WHERE 	id IN (SELECT order_id FROM ORDER_TABLE WHERE table_id=" . DB::Quote($this->id) . ")
					AND company_id=" . DB::Quote($this->company_id) . "
					AND completed=0
			ORDER BY date_created
			LIMIT 1;
		";
		if($rows=DB::Query($sql)) $this->order_id=$rows[0]['id'];
		return $this->order_id;
	}

	function GetRoom() {
		$room=new Room;
		if($room->Load(['id' => $this->room_id])) {
			$this->room=$room;
			$this->room_name=$room->name;
		} else {
			$this->room=null;
			$this->room_name='';
		}
		return $this->room;
	}

	function GetWaiters() {
		$this->waiters=[];
		$this->waiters_ids=[];
		$this->waiters_ids_str='';
		$sql="SELECT * FROM USER WHERE id IN (SELECT user_id FROM WAITER_TABLES WHERE table_id=" . DB::Quote($this->id) . ") ORDER BY name;";
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row_index=>$row) {
			$waiter=new User;
			$waiter->CreateFromArray($row);
			unset($waiter->email);
			unset($waiter->pass);
			$this->waiters[]=$waiter;
			$this->waiters_ids[]=$waiter->id;
		}
		$this->waiters_ids_str=empty($this->waiters_ids) ? '' : '#' . implode('#', $this->waiters_ids) . '#';
		return $this->waiters;
	}

	function GetUsersWithCustomerNotification() {
		$this->users_with_customer_notification=[];
		$this->users_with_customer_notification_ids=[];
		$sql="
			SELECT * FROM USER
			WHERE 	active=1
				AND position IN (0, 1)
				AND customer_notification=1
				AND company_id=" . DB::Quote($this->company_id) . "
			ORDER BY name;
		";
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row_index=>$row) {
			$user=new User;
			$user->CreateFromArray($row);
			unset($user->email);
			unset($user->pass);
			$this->users_with_customer_notification[]=$user;
			$this->users_with_customer_notification_ids[]=$user->id;
		}
		return $this->users_with_customer_notification;
	}

	function RenewQRCode() {
		$this->qr_code=Strings::CreateEncryptedLink(BaseUrl() . 'table/scanned/?id=' . $this->id . '&t=' . time());
		$this->Save();
	}

	public static function CheckScanned($id='') {
		if(empty($id)) $id=GetRequest('id');
		if(empty($id)) return new Response(false, Strings::Get('error_no_table_id'));
		// Load table from db
		$table=new Table;
		if(!$table->Load(['id'=>$id])) return new Response(false, Strings::Get('error_table_not_found'));
		if(empty($table->company_id)) return new Response(false, Strings::Get('error_table_not_found'));
		// Load company from db
		$company=new Company;
		if(!$company->Load(['id'=>$table->company_id])) return new Response(false, Strings::Get('error_table_company_not_found'));
		if(!$company->active) return new Response(false, Strings::Get('error_table_company_is_not_active'));
		if(!Session::IsCustomer()) Customer::LoginAnonymous();
		Session::Set('selected_table', $table);
		Session::Set('selected_company', $company);
		Session::Set('selected_company_menu', $company->GetMenu());
		Session::Set('selected_company_menu_timestamp', time());
		$session_order=Session::Get('selected_order');
		$is_new_order=false;

		if(empty($session_order)) {
			$order=null;
			// Search if there is incompleted order
			$sql="	SELECT ORDERS.id
					FROM ORDERS
					INNER JOIN ORDER_TABLE ON ORDERS.id=ORDER_TABLE.order_id
					WHERE 	ORDERS.company_id= " . DB::Quote($table->company_id) . "
						AND ORDER_TABLE.table_id=" . DB::Quote($table->id) . "
						AND ORDERS.completed=0
					ORDER BY ORDERS.date_created DESC
					LIMIT 1;
			";
			if($rows=DB::Query($sql)) {
				$order=new Order;
				if($order->Load(['id' => $rows[0]['id']])) {
					$order->session_id=Session::AccountId();
					$order->source='db';
				} else {
					$order=null;
				}
			}
		} else {
			// Get order from session
			$order=$session_order;
			$order->source='session';
		}
		// Check order
		if(!$order) {
			$order=new Order;
			$order->session_id=Session::AccountId();
			$order->company_id=$company->id;
			$order->customer_id=Session::Customer()->id;
			$order->source='new';
			$order->Save();
			$is_new_order=true;
		}

		// Check if selected table exists in order tables
		if(!in_array($table->id, $order->tables_ids)) {
			$order->tables_ids[]=$table->id;
			$order->tables[]=$table;
			$order->Save();
		}
		$order->GetData();
		Session::Set('selected_order', $order);
		if($is_new_order && empty($order->waiter_id)) {
			// Notify waiters and users with customer notification
			$waiters_ids=[];
			$occasion_hash=md5(time() . "#{$order->id}#{$order->tables_ids_str}#{$order->waiters_ids_str}");
			// Loop through waiters and users with customer notification
			$users_to_notify=array_merge($order->waiters, $order->GetUsersWithCustomerNotification());
			foreach($users_to_notify as $waiter) {
				if(!in_array($waiter->id, $waiters_ids)) {
					$models_data=[$order, $waiter]; foreach($order->tables as $t) $models_data[]=$t;
					$waiters_ids[]=$waiter->id;
					$notification=new Notification;
					$notification->from_company_customer_id=Session::CustomerId();
					$notification->to_user_id=$waiter->id;
					$notification->date_sent=date('Y-m-d H:i:s');
					$notification->occasion_hash=$occasion_hash;
					foreach(LANGUAGES as $lang) {
						$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_customer_scanned_table_title', $lang), $models_data);
						$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('notification_customer_scanned_table_message', $lang), $models_data);
					}
					if($waiter->position==3) $notification->buttons=json_encode([
						[
							'text' => Strings::Get('i_will_serve_it', Strings::GetLanguageByIndex($waiter->language)),
							'action' => "
								if($(button).hasClass('actioned')) return;
								$('#view_notifications_list').css('filter', 'blur(5px)');
								Post('" . API_URL . "', { controller: 'user', action: 'i_will_serve_it', order_id: " . $order->id . ", notification_id: $(button).attr('notification_id'), button_index: $(button).attr('button_index') }, GetNotifications);
							"
						]
					]);
					$notification_save=$notification->Save();
				}
			}
		}


		return new Response(true, Strings::Get('table_selected'), [ 'table_id' => $table->id, 'company_id' => $company->id, 'order_id' => $order->id, 'redirect' => BASE_URL ]);
	}

	public static function GetList($company_id='', $class='') {
		$sql="
			SELECT TABLES.*, ROOM.name AS room_name
			FROM TABLES
			LEFT JOIN ROOM ON TABLES.room_id=ROOM.id AND TABLES.company_id=ROOM.company_id
			WHERE TABLES.company_id" . (Session::IsAdmin() ? (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) : '=' . DB::Quote(Session::User()->company_id)) . "
			ORDER BY TABLES.name;
		";
		return parent::GetList($sql, $class);
	}

	public static function FixAllSortings() {
		if(!$rooms=DB::Query('SELECT * FROM ROOM;')) return new Response(false, Strings::Get('no_rooms_found'));
		$error_messages=''; $error_sqls='';
		foreach($rooms as $room_db) {
			$room=new Room;
			$room->CreateFromArray($room_db);
			$response=$room->FixSorting();
			if(!$response->status) {
				$error_messages.=$response->message . "\n";
				$error_sqls.=$response->data . "\n";
			}
		}
		return new Response($error_messages=='', $error_messages=='' ? 'OK' : $error_messages, $error_sqls);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Table::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='scanned') {
			return Table::CheckScanned($id);

		} else if($action=='occupied') {
			// Check table id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			// Check value
			$value=trim(isset($_REQUEST['v']) ? $_REQUEST['v'] : '');
			if($value=='') return new Response(false, Strings::Get('error_missing_param'));
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Table;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!$load) return new Response(false, Strings::Get('error_table_not_found'));
			if(!Session::IsAdmin() && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update occupied
			$model->occupied=$value=='1' ? '1' : '0';
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='reserved') {
			// Check table id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			// Check value
			$value=trim(isset($_REQUEST['v']) ? $_REQUEST['v'] : '');
			if($value=='') return new Response(false, Strings::Get('error_missing_param'));
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Table;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!$load) return new Response(false, Strings::Get('error_table_not_found'));
			if(!Session::IsAdmin() && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update reserved
			$model->reserved=$value=='1' ? '1' : '0';
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='completed') {
			// Check table id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			// Check value
			$value=trim(isset($_REQUEST['v']) ? $_REQUEST['v'] : '');
			if($value=='') return new Response(false, Strings::Get('error_missing_param'));
			// Check permissions
			if(empty($id)) return new Response(false, Strings::Get('error_missing_id'));
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Table;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!$load) return new Response(false, Strings::Get('error_table_not_found'));
			if(!Session::IsAdmin() && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			$model->GetOrder();
			if(empty($model->order_id)) return new Response(false, Strings::Get('error_table_does_not_have_order'));
			// Update order
			$update=DB::Update("UPDATE ORDERS SET completed=1 WHERE id=" . DB::Quote($model->order_id) . ";");
			$model->reserved=$value=='1' ? '1' : '0';
			if($update) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_order_cannot_be_completed'));
			}

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Table;
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
				'table' => 'TABLES',
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}