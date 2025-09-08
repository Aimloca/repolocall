<?php

class User extends Model {

	const table='USER';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('USER', DB_TABLES['USER']['primary_key'], $primary_key_value);
	}

	function Save() {
		// Check if password is encrypted
		if(!empty($this->pass) && Strings::DecryptPass($this->pass)!==false) $this->pass=Strings::DecryptPass($this->pass);
		// Validate password
		if($this->pass!='' && strlen($this->pass)<8) return [ 'status' => false, 'message' => Strings::Get('error_password_atlease_8_chars')];
		$save=parent::Save();
		if($save['status']) {
			// If user is not waiter delete tables
			if($this->position!=3) {
				DB::Query("DELETE FROM WAITER_TABLES WHERE user_id=" . DB::Quote($this->id));
			}

			// If user is waiter and json_tables is set, update tables
			if($this->position==3 && isset($this->json_tables)) {
				DB::Query("DELETE FROM WAITER_TABLES WHERE user_id=" . DB::Quote($this->id));
				$sql='';
				$tables=json_decode($this->json_tables);
				if($tables) foreach($tables as $waiter_table) {
					$table=new Table;
					if(!$table->Load(['id'=>$waiter_table->id])) continue;
					$sql.=($sql=='' ? '' : ", \n") . "(" . DB::Quote($this->id) . ", " . DB::Quote($waiter_table->id) . ")";
				}
				if($sql) DB::Insert("INSERT INTO WAITER_TABLES (user_id, table_id) VALUES {$sql};");
			}
		}
		return $save;
	}

	function SetLanguage($lang) {
		if(!in_array($lang, LANGUAGES)) return false;
		$this->language=array_search($lang, LANGUAGES);
		$save=DB::Update("UPDATE {$this->table} SET language={$this->language} WHERE id={$this->id};");
		return [ 'status' => $save ? true : false, 'message' => $save ? 'OK' : 'Error' ];
		return $save['status'];
	}

	function GetHomeData() {
		$this->GetCompany();
		$this->GetTables(true);
		$this->GetRooms(true);
		$this->GetCash();
		$this->GetCharts();
		$this->GetWarnings();
		return $this;
	}

	function GetCharts() {
		$this->charts=[ 'pie' => [], 'line' => [] ];
		if(!Session::IsShopManager()) return $this->charts;

		// Get company
		$this->GetCompany();

		// Get orders
		$this->company->GetOrders();

		// Build chart total net and vat amount
		$str_net_amount=Strings::Get('net_amount');
		$str_vat_amount=Strings::Get('vat_amount');
		$chart=[
			'id' => 'net_and_vat_amount',
			'title' => Strings::Get('net_and_vat_amount'),
			'data' => [ $str_net_amount => 0, $str_vat_amount => 0, ],
			'legent' => '',
		];
		if($this->company->orders) foreach($this->company->orders as $order) {
			$amounts=$order->GetNetAndVatAmount();
			$chart['data'][$str_net_amount]+=$amounts['net_amount'];
			$chart['data'][$str_vat_amount]+=$amounts['vat_amount'];
		}
		$chart['legent']=Strings::FormatAmount($chart['data'][$str_net_amount]) . '&euro; / ' . Strings::FormatAmount($chart['data'][$str_vat_amount]) . '&euro;';
		$this->charts['pie'][]=$chart;

		// ----------------------------------------------

		// Build chart total net amount per waiter
		$chart=[
			'id' => 'net_amount_per_waiter',
			'title' => Strings::Get('net_amount_per_waiter'),
			'data' => [],
			'legent' => '',
		];
		$total=0;
		if($this->company->orders) foreach($this->company->orders as $order) {
			$waiter_name=empty($order->main_waiter) ? Strings::Get('no_waiter') : $order->main_waiter->name;
			if(!isset($chart['data'][$waiter_name])) $chart['data'][$waiter_name]=0;
			$amount=$order->GetNetAndVatAmount()['net_amount'];
			$chart['data'][$waiter_name]+=$amount;
			$total+=$amount;
		}
		$chart['legent']=Strings::FormatAmount($total) . '&euro;';
		$this->charts['pie'][]=$chart;

		// ----------------------------------------------

		// Build chart total amount per payment method
		$string_cash=Strings::Get('cash');
		$string_card=Strings::Get('card');
		$chart=[
			'id' => 'amount_per_payment_method',
			'title' => Strings::Get('amount_per_payment_method'),
			'data' => [ $string_cash => 0, $string_card => 0 ],
			'legent' => '',
		];
		$total=0;
		if($this->company->orders) foreach($this->company->orders as $order) {
			foreach($order->GetPayments() as $payment) {
				$chart['data'][$payment->type==0 ? $string_cash : $string_card]+=$payment->amount;
				$total+=$payment->amount;
			}
		}
		$chart['legent']=Strings::FormatAmount($total) . '&euro;';
		$this->charts['pie'][]=$chart;

		// ----------------------------------------------

		// Build chart total net amount per day
		$day_names=[ Strings::Get('sun'), Strings::Get('mon'), Strings::Get('tue'), Strings::Get('wed'), Strings::Get('thu'), Strings::Get('fri'), Strings::Get('sat'), ];
		$chart=[
			'id' => 'net_amount_per_day',
			'title' => Strings::Get('net_amount_per_day'),
			'data' => [],
			'legent' => '',
		];
		$total=0;
		$date_from=date('Y-m-d 00:00:00', strtotime('-1 week'));
		$date_tmp=$date_from;
		while($date_tmp<date('Y-m-d 23:59:59')) {
			$day=$day_names[date('w', strtotime($date_tmp))] . " " . date('d/m', strtotime($date_tmp));
			$chart['data'][$day]=0;
			$date_tmp=date('Y-m-d 00:00:00', strtotime($date_tmp . ' +1 day'));
		}
		if($orders=$this->company->GetOrders($date_from)) foreach($orders as $order) {
			$day=$day_names[date('w', strtotime($order->date_created))] . " " . date('d/m', strtotime($order->date_created));
			$amount=$order->GetNetAndVatAmount()['net_amount'];
			$chart['data'][$day]+=$amount;
			$total+=$amount;
		}
		$chart['legent']=Strings::FormatAmount($total) . '&euro;';
		$this->charts['line'][]=$chart;

		return $this->charts;
	}

	function GetRooms($full_data=false) {
		$this->rooms=[];
		if($this->position==3) {
			$sql="
				SELECT DISTINCT ROOM.id, ROOM.sorting
				FROM TABLES
				INNER JOIN ROOM ON TABLES.room_id=ROOM.id
				WHERE TABLES.id IN (SELECT table_id FROM WAITER_TABLES WHERE user_id=" . DB::Quote($this->id) . ")
				ORDER BY ROOM.sorting;
			";
		} else {
			$sql="
				SELECT DISTINCT ROOM.id, ROOM.sorting
				FROM TABLES
				INNER JOIN ROOM ON TABLES.room_id=ROOM.id
				WHERE TABLES.company_id=" . DB::Quote($this->company_id) . "
				ORDER BY ROOM.sorting;
			";
		}
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row) {
			$room=new Room;
			$room->Load(['id' => $row['id']]);
			$room->tables=[];
			$sql="
				SELECT *
				FROM TABLES
				WHERE 	room_id=" . DB::Quote($room->id) .
					($this->position==3 ? " AND id IN (SELECT table_id FROM WAITER_TABLES WHERE user_id=" . DB::Quote($this->id) . ")" : "") . "
				ORDER BY sorting;
			";
			if($rows_tables=DB::Query($sql)) foreach($rows_tables as $rows_table) {
				$table=new Table;
				$table->CreateFromArray($rows_table);
				if($full_data) $table->GetData();
				$room->tables[]=$table;
			}
			$this->rooms[]=$room;
		}
		return $this->rooms;
	}

	function GetCompany($full_data=false) {
		$this->company=null;
		if(in_array($this->company_id, [ '-1', '0' ])) return $this->company;
		$company=new Company;
		if($company->Load(['id'=>$this->company_id])) $this->company=$company;
		return $this->company;
	}

	function GetWarnings() {
		$this->warnings=[];
		// Check position
		if($this->position!=0) return $this->warnings;
		// Check company
		if(!isset($this->company) || empty($this->company)) $this->GetCompany();
		if(empty($this->company)) {
			$this->warnings[]=[ 'title' => Strings::Get('user_has_no_company') ];
			return $this->warnings;
		}
		$this->warnings=$this->company->GetWarnings();
		return $this->warnings;
	}

	function GetTables($full_data=false) {
		$this->tables=[];
		$this->tables_ids=[];
		if($this->position==3) {
			$sql="
				SELECT TABLES.*, ROOM.name AS room_name, ROOM.sorting AS room_sorting
				FROM TABLES
				INNER JOIN ROOM ON TABLES.room_id=ROOM.id
				WHERE TABLES.id IN (SELECT table_id FROM WAITER_TABLES WHERE user_id=" . DB::Quote($this->id) . ")
				ORDER BY ROOM.sorting, TABLES.sorting;
			";
		} else {
			$sql="
				SELECT TABLES.*, ROOM.name AS room_name, ROOM.sorting AS room_sorting
				FROM TABLES
				INNER JOIN ROOM ON TABLES.room_id=ROOM.id
				WHERE TABLES.company_id=" . DB::Quote($this->company_id) . "
				ORDER BY ROOM.sorting, TABLES.sorting;
			";
		}
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row_index=>$row) {
			$table=new Table;
			$table->CreateFromArray($row);
			if($full_data) $table->GetData();
			$this->tables[]=$table;
			$this->tables_ids[]=$table->id;
		}
		$this->tables_ids_str=empty($this->tables_ids) ? '' : '#' . implode('#', $this->tables_ids) . '#';
		return $this->tables;
	}

	function GetCash($all_users=false) {
		$this->products_amount=0;
		$this->tip_amount=0;
		$this->cash_amount=0;
		$this->cash_payments=[];
		$this->cash_payments_ids=[];
		$this->cash_payments_ids_str='';
		if(!Session::IsUser()) return;

		// Get last day end
		$last_day_end=DayEnd::GetLast();

		// Get payments from last day end till now
		$sql="
			SELECT * FROM PAYMENT
			WHERE 	type=0
				AND completed=1
				" . (empty($last_day_end) ? "" : "AND date_created>=" . DB::Quote($last_day_end)) . "
				AND " . ($all_users ? "company_id=" . DB::Quote(Session::User()->company_id) : "holder_user_id=" . DB::Quote(Session::UserId())) . "
			ORDER BY order_id, date_created;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			// Get payment
			$payment=new Payment;
			$payment->CreateFromArray($row);
			// Get order
			$order=new Order;
			if(!$order->Load(['id' => $row['order_id']])) continue;
			$order->GetData();
			$payment->first_table_id=empty($order->tables) ? 'ID' : $order->tables[0]->id;
			$payment->first_table_name=empty($order->tables) ? 'TABLE' : $order->tables[0]->name;
			$payment->order=$order;
			// If tip user id is not this user id, set tip to 0 and amount to products amount
			if($payment->tip_user_id!=$this->id) {
				$payment->tip_amount=0;
				$payment->amount=$payment->products_amount;
			}
			$this->products_amount+=$payment->products_amount;
			$this->tip_amount+=$payment->tip_amount;
			$this->cash_amount+=$payment->amount;
			$this->cash_payments[]=$payment;
			if(!in_array($payment->id, $this->cash_payments_ids)) $this->cash_payments_ids[]=$payment->id;
		}
		$this->cash_payments_ids_str=empty($this->cash_payments_ids) ? '' : '#' . implode('#', $this->cash_payments_ids) . '#';
	}

	function GetOtherCashList() {
		// Get last day end
		$last_day_end=DayEnd::GetLast();

		$out=[];
		$sql="
			SELECT PAYMENT.holder_user_id, USER.name AS user_name,
				SUM(PAYMENT.amount) as total_amount,
				SUM(PAYMENT.tip_amount) as total_tip_amount,
				SUM(PAYMENT.products_amount) as total_products_amount
			FROM PAYMENT
			INNER JOIN USER ON PAYMENT.holder_user_id=USER.id
			WHERE 	PAYMENT.company_id=" . DB::Quote($this->company_id) . "
				AND PAYMENT.type=0
				AND PAYMENT.completed=1
				AND PAYMENT.holder_user_id IS NOT NULL
				AND PAYMENT.holder_user_id!=" . DB::Quote($this->id) . "
				" . (empty($last_day_end) ? "" : "AND PAYMENT.date_created>=" . DB::Quote($last_day_end)) . "
			GROUP BY PAYMENT.holder_user_id
			ORDER BY user_name;
		";
		if($rows=DB::Query($sql)) {
			foreach($rows as $row) {
				if($row['total_amount']==0) continue;
				$payment=new Payment;
				$payment->CreateFromArray($row);
				$out[]=$payment;
			}
		}
		return $out;
	}

	function GetCashFromUser($user_id) {
		if(empty($user_id)) return new Response(false, Strings::Get('error_empty_user_id'));
		if($user_id==$this->id) return new Response(false, Strings::Get('error_cannot_get_cash_from_yourself'));
		$from_user=new User;
		if(!$from_user->Load(['id' => $user_id])) return new Response(false, Strings::Get('error_user_not_found'));
		if($from_user->company_id!=$this->company_id) return new Response(false, Strings::Get('error_user_company_mismatch'));

		// Get total transfered cash amount
		$transfered_amount=0;
		$sql="SELECT SUM(products_amount) AS transfered_amount FROM PAYMENT WHERE holder_user_id=" . DB::Quote($user_id) . " AND type=0 AND completed=1;";
		if($rows=DB::Query($sql)) $transfered_amount=$rows[0]['transfered_amount'];

		// Change payments holder user id
		$sql="UPDATE PAYMENT SET holder_user_id=" . DB::Quote($this->id) . " WHERE holder_user_id=" . DB::Quote($user_id) . " AND type=0 AND completed=1;";
		if(DB::Update($sql)) {
			return new Response(true, str_replace('#AMOUNT#', Strings::FormatAmount($transfered_amount), Strings::Get('cash_amount_transfered_successfully')));
		} else {
			return new Response(false, Strings::Get('error_while_transfering_cash'));
		}
	}

	function CloseRegister() {
		if($this->position!=0) return new Response(false, Strings::Get('error_insufficient_rights'));
		// Get last day end
		$last_day_end=DayEnd::GetLast();
		// Get other cash list
		$other_cash_list=$this->GetOtherCashList();
		$other_cash_amount=0;
		$other_cast_list_text='';
		foreach($other_cash_list as $l) {
			$other_cash_amount+=$l->total_amount;
			$other_cast_list_text=$l->user_name . ': ' . Strings::FormatAmount($l->total_amount) . '€' . PHP_EOL;
		}
		if(!empty($other_cast_list_text)) return new Response(false, Strings::Get('error_users_have_money') . PHP_EOL . $other_cast_list_text);

		/*
		// Set holder_user_id of payments to user id
		$sql="
			UPDATE PAYMENT SET holder_user_id=" . DB::Quote($this->id) . "
			WHERE 	company_id=" . DB::Quote($this->company_id) . "
				AND type=0
				AND completed=1
				" . (empty($last_day_end) ? "" : "AND date_created>=" . DB::Quote($last_day_end)) . ";
		";
		if(DB::Update($sql)) {
			return new Response(true, 'OK');
		} else {
			return new Response(false, Strings::Get('error_while_updating_payments'));
		}
		*/

		// Create day end
		$day_end=new DayEnd;
		$day_end->company_id=$this->company_id;
		$day_end->day_end=date('Y-m-d H:i:s');
		$save=$day_end->Save();
		if($save['status']) {
			return new Response(true, 'OK');
		} else {
			return new Response(false, Strings::Get('error_closing_register'));
		}
	}

	public static function GetList($sql='', $class='') {
		$sql_position="
			CASE
				WHEN position=0 THEN " . DB::Quote('shop_manager') . "
				WHEN position=1 THEN " . DB::Quote('barista') . "
				WHEN position=2 THEN " . DB::Quote('preparation') . "
				ELSE " . DB::Quote('waiter') . "
			END AS position_title
		";
		$where='';
		if(!empty($sql) && is_array($sql)) {
			if(!empty($sql['company_id'])) $where.=($where=='' ? '' : ' AND ') . ' company_id=' . DB::Quote($sql['company_id']) . ' ';
			if(!empty($sql['user_position'])) $where.=($where=='' ? '' : ' AND ') . ' position=' . DB::Quote($sql['user_position']) . ' ';
		}
		if(!empty($where)) $where=" WHERE {$where} ";
		if(Session::IsAdmin()) {
			$sql="SELECT *, {$sql_position} FROM USER {$where} ORDER BY email;";
		} else if(Session::IsUser()) {
			if(empty(Session::User()->company_id)) {
				return [];
			} else if(empty(Session::User()->position==0)) {
				$sql="SELECT *, {$sql_position} FROM USER {$where} ORDER BY email;";
			} else if(empty(Session::User()->position==1)) {
				$sql="SELECT *, {$sql_position} FROM USER {$where} ORDER BY email;";
			} else {
				return [];
			}
		} else {
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function GetPositions() {
		//$positions=[ '0' => Strings::Get('shop_manager'), '1' => Strings::Get('barista'), '2' => Strings::Get('preparation'), '3' => Strings::Get('waiter') ];
		$positions=[ '0' => Strings::Get('shop_manager'), '3' => Strings::Get('waiter') ];
		if(Session::IsAdmin()) {
			return $positions;
		} else if(Session::IsShopManager()) {
			return $positions;
		} else if(Session::IsUser()) {
			return [ Session::User()->position => $positions[Session::User()->position] ];
		} else {
			return [];
		}
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$company_id=Session::IsAdmin() ? GetRequest('company_id') : (Session::IsUser() ? Session::User()->company_id : '');
			$user_position=GetRequest('user_position');
			$rows=User::GetList([ 'company_id' => $company_id, 'user_position' => $user_position ]);
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			if(!Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new User;
			// Get from database
			$model->Load(['id'=>$id]);
			// Update from request
			$model->CreateFromRequest(true);
			// Check company
			if(!empty($id) && !Session::IsAdmin() && Session::User()->company_id!=$model->company_id) return new Response(false, Strings::Get('error_user_company_mismatch'));
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='home_data') {
			if(!Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			return new Response(true, 'OK', Session::User()->GetHomeData());

		} else if($action=='user_cash_list') {
			if(!Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			return new Response(true, 'OK', Session::User()->GetOtherCashList());

		} else if($action=='get_cash_from_user') {
			if(!Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			return Session::User()->GetCashFromUser(GetRequest('user_id'));

		} else if($action=='close_register') {
			if(!Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			return Session::User()->CloseRegister();

		} else if($action=='get_rooms') {
			if(Session::IsAdmin()) return new Response(true, 'OK', []);
			if(!Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			return Session::User()->GetRooms(true);

		} else if($action=='i_will_serve_it') {
			if(!Session::IsWaiter()) return new Response(false, Strings::Get('error_insufficient_rights'));
			$order_id=GetRequest('order_id');
			if(empty($order_id)) return new Response(false, Strings::Get('error_missing_order_id'));
			$order=new Order;
			if(!$order->Load(['id' => $order_id])) return new Response(false, Strings::Get('error_order_not_found'));
			if(!empty($order->waiter_id) && $order->waiter_id!=Session::UserId()) return new Response(false, Strings::Get('error_order_is_been_served_by_other_waiter'));
			$update=DB::Update("UPDATE ORDERS SET waiter_id=" . DB::Quote(Session::UserId()) . " WHERE id=" . DB::Quote($order_id) . ";");
			if(!$update) return new Response(false, Strings::Get('error_cannot_update_order'));

			$notification_id=GetRequest('notification_id');
			if(!empty($notification_id)) {
				$notification=new Notification;
				if($notification->Load(['id'=>$notification_id])) return $notification->Actioned(GetRequest('button_index'));
			}
			return new Response(true, 'OK');

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