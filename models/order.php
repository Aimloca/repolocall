<?php

class Order extends Model {

	const table='ORDERS';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('ORDERS', 'id', $primary_key_value);
		$this->customer=null;
		$this->customer_name='';
		$this->tables=[];
		$this->tables_ids=[];
		$this->tables_ids_str='';
		$this->first_table_name='';
		$this->products=[];
		$this->products_ids=[];
		$this->products_ids_str='';
		$this->products_rows_ids=[];
		$this->products_rows_ids_str='';
		$this->payments=[];
		$this->payments_ids=[];
		$this->payments_ids_str='';
		$this->waiters=[];
		$this->waiters_ids='';
		$this->waiters_ids_str='';
	}

	function CreateDefaults($params=[]) {
		$this->session_id=Session::AccountId();

		if(Session::IsUser()) {
			// Set company
			$this->company_id=Session::User()->company_id;
			$company=new Company;
			if(!$company->Load(['id' => $this->company_id])) return [ 'status' => false, 'message' => Strings::Get('error_company_not_found') ];
			$this->company=$company;
		}

		// Set customer
		$customer=Customer::GetAnonymous();
		$this->customer_id=$customer->id;
		$this->customer_name=$customer->name;

		// Update with params
		if($params) foreach($params as $key=>$value) $this->$key=$value;
	}

	function Save() {

		// Reset id if needed
		if(!empty($this->id) && substr($this->id, 0, 1)=='-') $this->id='';

		// Check session
		if(empty($this->session_id)) $this->session_id=Session::AccountId();

		// Check company
		if(empty($this->company_id)) return [ 'status' => false, 'message' => Strings::Get('error_no_company') ];
		$company=new Company;
		if(!$company->Load(['id' => $this->company_id])) return [ 'status' => false, 'message' => Strings::Get('error_company_not_found') ];

		// Check customer
		if(empty($this->customer_id)) return [ 'status' => false, 'message' => Strings::Get('error_no_customer') ];
		if($this->customer_id>0) {
			$customer=new CompanyCustomer;
			if(!$customer->Load(['id' => $this->customer_id])) return [ 'status' => false, 'message' => Strings::Get('error_customer_not_found') ];
		} else {
			$customer=Customer::GetAnonymous();
			$this->customer_id=$customer->id;
		}

		// Check tables
		$this->tables_ids_str='';
		if(isset($this->tables_ids)) {
			if(empty($this->tables_ids)) return [ 'status' => false, 'message' => Strings::Get('error_no_tables_ids') ];
			$tmp_tables_ids=is_string($this->tables_ids) ? explode('#', $this->tables_ids) : $this->tables_ids;
			$this->tables_ids=[]; $this->tables=[];
			foreach($tmp_tables_ids as $table_index=>$table_id) {
				if(empty($table_id)) continue;
				$table=new Table;
				if(!$table->Load(['id' => $table_id])) return [ 'status' => false, 'message' => Strings::Get('error_invalid_table') ];
				$this->tables[]=$table;
				$this->tables_ids[]=$table->id;
			}
			$this->tables_ids_str=empty($this->tables_ids) ? '' : '#' . implode('#', $this->tables_ids) . '#';
		} else if(isset($this->tables)) {
			if(empty($this->tables)) return [ 'status' => false, 'message' => Strings::Get('error_no_tables') ];
			$this->tables_ids=[];
			foreach($this->tables as $table_index=>$table) {
				$table=new Table;
				if(!$table->Load(['id' => $table->id])) return [ 'status' => false, 'message' => Strings::Get('error_invalid_table') ];
				$this->tables_ids[]=$table->id;
			}
		}
		$this->tables_ids_str=empty($this->tables_ids) ? '' : '#' . implode('#', $this->tables_ids) . '#';
		$this->first_table_name=empty($this->tables) ? '' : $this->tables[0]->name;

		$is_new_order=empty($this->id);

		// Save
		$save=parent::Save();
		if($save['status']) {
			// Update tables
			$this->SetTablesIds($this->tables_ids);
			// Get products
			$this->GetProducts();
			// Get amounts
			$this->GetAmounts();
			// Notify other waiters for new order
			if($is_new_order && Session::IsWaiter()) $this->NotifyWaitersForNewOrder();
			// Set tables state to OCCUPIED if order is not completed
			if($this->id && !$this->completed) DB::Query('UPDATE TABLES SET occupied=1 WHERE id IN (SELECT table_id FROM ORDER_TABLE WHERE order_id=' . DB::Quote($this->id) . ')');
		}
		return $save;
	}

	public function Delete() {
		// Reset id if needed
		if(empty($this->id)) return [ 'status' => false, 'message' => Strings::Get('error_missing_order_id') ];

		// Search if there are any payments for this order
		$sql="SELECT id FROM PAYMENT WHERE order_id=" . DB::Quote($this->id) . " LIMIT 1;";
		if($rows=DB::Query($sql)) return [ 'status' => false, 'message' => Strings::Get('error_cannot_delete_order_payment_exists') ];

		try {
			// Begin transaction
			DB::BeginTransaction();

			// Delete order products specs
			$sql="DELETE FROM ORDER_PRODUCT_SPEC WHERE order_id=" . DB::Quote($this->id) . ";";
			DB::Query($sql);

			// Delete order products
			$sql='DELETE FROM ORDER_PRODUCT WHERE order_id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);

			// Delete order tables
			$sql='DELETE FROM ORDER_TABLE WHERE order_id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);

			// Delete order
			$sql='DELETE FROM ORDERS WHERE id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);

			// Commit transaction
			DB::CommitTransaction();
			return new Response(true, 'OK');
		} catch(Exception $e) {
			DB::RollBackTransaction();
			return new Response(false, $e->getMessage());
		}
	}

	function GetNetAndVatAmount() {
		$total_net_amount=0; $total_vat_amount=0;
		if(!empty($this->products)) foreach($this->products as $product) {
			$product_net_amount=$product->amount / ((100 + $product->vat_percent ) / 100);
			$total_net_amount+=$product_net_amount;
			$total_vat_amount+=$product->amount - $product_net_amount;
		}
		return [ 'net_amount' => round($total_net_amount, 2), 'vat_amount' => round($total_vat_amount, 2) ];
	}

	public function MergeOrders($merge_orders_ids) {
		if(empty($this->id)) return false;
		if(empty($merge_orders_ids)) return false;
		// Check orders to merge
		$update_sql='';
		// Get tables with columns that contain order_id (Warning: only with MySQL engine)
		$sql="SELECT DISTINCT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%order_id%' AND TABLE_SCHEMA='" . DB_NAME . "';";
		if($table_columns=DB::Query($sql)) foreach($table_columns as $tc) {
			// Build update sql
			$update_sql.="UPDATE {$tc['TABLE_NAME']} SET {$tc['COLUMN_NAME']}=" . DB::Quote($this->id) . " WHERE {$tc['COLUMN_NAME']} IN ({$merge_orders_ids});\n";
		}
		if($update_sql) $update_sql.="DELETE FROM ORDERS WHERE id IN ({$merge_orders_ids});\n";
		if($update_sql) {
			$update=DB::Update($update_sql);
			$this->RecalculateProductsAmount();
			return $update;
		}
		return false;
	}

	public function GetData() {
		$this->GetCustomer();
		$this->GetTables();
		$this->GetWaiters();
		$this->GetProducts();
		$this->GetAmounts();
		$this->GetPayments();
		$this->GetDocuments();
		$this->GetCommission();
		return $this;
	}

	public function GetCommission() {
		$this->company_commission=0;
		$this->company_salesman_commission=0;
		if(!$this->completed || !empty($this->date_canceled)) return $this;
		if(!$rows=DB::Query("SELECT commission, salesman_commission, extra_charge FROM COMPANY WHERE id=" . DB::Quote($this->company_id) . " LIMIT 1;")) return $this;
		if($rows[0]['extra_charge'] || $this->customer_order) {
			$this->company_commission=$this->products_net_amount * ($rows[0]['commission'] / 100);
			$this->company_salesman_commission=$this->products_net_amount * ($rows[0]['commission'] / 100) * ($rows[0]['salesman_commission'] / 100);
		}
		return $this;
	}

	public function GetTables() {
		$this->tables=[];
		$this->tables_ids=[];
		$this->tables_ids_str='';
		$this->tables_names='';
		$this->rooms=[];
		$this->rooms_ids=[];
		$this->rooms_ids_str='';
		$this->rooms_names='';
		if(!empty($this->id)) {
			$sql="SELECT id, table_id FROM ORDER_TABLE WHERE order_id=" . DB::Quote($this->id) . " ORDER BY table_id;";
			if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
				$table=new Table;
				if(!$table->Load(['id' => $row['table_id']])) continue;
				if(!in_array($table->id, $this->tables_ids)) {
					$table->row_id=$row['id'];
					$this->tables[]=$table;
					$this->tables_ids[]=$table->id;
					$this->tables_names.=($this->tables_names=='' ? '' : ' + ') . $table->name;
					// Check room
					if(!in_array($table->room_id, $this->rooms_ids)) {
						$room=new Room;
						if($room->Load(['id' => $table->room_id])) {
							$this->rooms[]=$room;
							$this->rooms_ids[]=$room->id;
							$this->rooms_names.=($this->rooms_names=='' ? '' : ' + ') . $room->name;
						}
					}
				}
			}
		}
		$this->tables_ids_str=empty($this->tables_ids) ? '' : '#' . implode('#', $this->tables_ids) . '#';
		$this->first_table_name=empty($this->tables) ? '' : $this->tables[0]->name;
		$this->rooms_ids_str=empty($this->rooms_ids) ? '' : '#' . implode('#', $this->rooms_ids) . '#';
		$this->first_room_name=empty($this->rooms) ? '' : $this->rooms[0]->name;
		return $this->tables;
	}

	public function GetWaiters() {
		$this->waiters=[];
		$this->waiters_ids=[];
		$this->waiters_names='';
		try {
			// Get main waiter
			if(!empty($this->waiter_id)) {
				$waiter=new User;
				if($waiter->Load(['id'=>$this->waiter_id])) {
					unset($waiter->email);
					unset($waiter->pass);
					$waiter->GetTables();
					$waiters[]=$waiter;
					$this->waiters_ids[]=$waiter->id;
					$this->waiters_names.=($this->waiters_names=='' ? '' : ', ' . $waiter->name);
					$this->main_waiter=$waiter;
					$this->main_waiter_name=$waiter->name;
				} else {
					$this->waiter_id='';
				}
			}
			// Get tables
			if(empty($this->tables)) $this->GetTables();
			if(empty($this->tables)) throw new Exception("No tables are set for this order");
			// Loop through tables and get waiters
			foreach($this->tables as $table) {
				$waiters=$table->GetWaiters();
				foreach($waiters as $waiter) if(!in_array($waiter->id, $this->waiters_ids)) {
					$this->waiters[]=$waiter;
					$this->waiters_ids[]=$waiter->id;
					$this->waiters_names.=($this->waiters_names=='' ? '' : ', ' . $waiter->name);
				}
			}
		} catch(Exception $e) {}

		/*
		if(empty($this->waiter_id) && count($this->waiters)==1) {
			$this->waiter_id=$this->waiters[0]->id;
			$this->main_waiter=$this->waiters[0];
			$this->main_waiter_name=$this->waiters[0]->name;
		}
		*/
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

	public function GetCustomer() {
		$this->customer=new CompanyCustomer;
		if(!$this->customer->Load(['id' => $this->customer_id])) {
			$this->customer=null;
			$this->customer_name='';
		} else {
			$this->customer_name=$this->customer->name;
		}
		return $this->customer;
	}

	public function GetCompany() {
		$this->company=new Company;
		if(!$this->company->Load(['id' => $this->company_id])) {
			$this->company=null;
			$this->company_name='';
		} else {
			$this->company_name=$this->company->name;
		}
		return $this->company;
	}

	public function GetDocuments() {
		$this->documents=[];
		$this->documents_ids=[];
		$this->documents_ids_str='';
		if(!empty($this->id)) {
			$sql="SELECT id FROM SALE_DOCUMENT WHERE relative_order_id=" . DB::Quote($this->id) . " ORDER BY date;";
			if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
				$doc=new SaleDocument;
				$doc->CreateFromArray($row);
				if(!in_array($doc->id, $this->documents_ids)) {
					$this->documents[]=$doc;
					$this->documents_ids[]=$doc->id;
				}
			}
		}
		$this->documents_ids_str=empty($this->documents_ids) ? '' : '#' . implode('#', $this->documents_ids) . '#';
		$this->has_documents=!empty($this->documents);
		return $this->documents;
	}

	public function GetProducts() {
		$this->products=[];
		$this->products_ids=[];
		$this->products_rows_ids=[];
		$this->products_sent=[];
		$this->products_prepared=[];
		$this->products_delivered=[];
		$this->products_paid=[];
		$this->products_unpaid=[];
		$this->total_products_to_be_sent=0;
		$this->total_products_to_be_prepared=0;
		$this->total_products_to_be_delivered=0;
		$this->total_products_to_be_paid=0;
		$this->total_amount_to_be_paid=0;

		if(!empty($this->id)) {
			$sql="
				SELECT ORDER_PRODUCT.id AS row_id, ORDER_PRODUCT.product_id, PRODUCT.name_" . Strings::GetLanguage() . " AS name
				FROM ORDER_PRODUCT
				INNER JOIN PRODUCT ON ORDER_PRODUCT.product_id=PRODUCT.id
				WHERE ORDER_PRODUCT.order_id=" . DB::Quote($this->id) . " AND ORDER_PRODUCT.date_canceled IS NULL
				ORDER BY ORDER_PRODUCT.sent, ORDER_PRODUCT.prepared, ORDER_PRODUCT.delivered, ORDER_PRODUCT.paid, PRODUCT.name_" . Strings::GetLanguage() . ", ORDER_PRODUCT.id;
			";
			if($rows=DB::Query($sql)) {
				foreach($rows as $row_index=>$row) {
					$product=new OrderProduct;
					$product->Load(['id' => $row['row_id']]);
					$product->row_id=$row['row_id'];
					$product->id=$row['product_id'];
					$product->ordered=1;
					$product->GetData();
					$this->products[]=$product;
					$this->products_ids[]=$product->id;
					$this->products_rows_ids[]=$product->row_id;
					if($product->sent) $this->products_sent[]=$product;
					if($product->prepared) $this->products_prepared[]=$product;
					if($product->delivered) $this->products_delivered[]=$product;
					if($product->paid) $this->products_paid[]=$product; else $this->products_unpaid[]=$product;

					// Fix product to be...
					if(!$product->sent) $this->total_products_to_be_sent++;
					if($product->sent && !$product->prepared) $this->total_products_to_be_prepared++;
					if($product->prepared && !$product->delivered) $this->total_products_to_be_delivered++;
					if(!$product->paid) { $this->total_products_to_be_paid++; $this->total_amount_to_be_paid+=$product->amount; }
				}
			}
		}
		// Fix products ids
		$this->products_ids_str=empty($this->products_ids) ? '' : '#' . implode('#', $this->products_ids) . '#';
		// Fix products rows ids
		$this->products_rows_ids_str=empty($this->products_rows_ids) ? '' : '#' . implode('#', $this->products_rows_ids) . '#';
		// Fix sent products ids and string
		$this->products_sent_ids=[]; foreach($this->products_sent as $p) if(!in_array($p->id, $this->products_sent_ids)) $this->products_sent_ids[]=$p->id; $this->products_sent_ids_str=empty($this->products_sent_ids) ? '' : '#' . implode('#', $this->products_sent_ids) . '#';
		// Fix prepared products ids and string
		$this->products_prepared_ids=[]; foreach($this->products_prepared as $p) if(!in_array($p->id, $this->products_prepared_ids)) $this->products_prepared_ids[]=$p->id; $this->products_prepared_ids_str=empty($this->products_prepared_ids) ? '' : '#' . implode('#', $this->products_prepared_ids) . '#';
		// Fix delivered products ids and string
		$this->products_delivered_ids=[]; foreach($this->products_delivered as $p) if(!in_array($p->id, $this->products_delivered_ids)) $this->products_delivered_ids[]=$p->id; $this->products_delivered_ids_str=empty($this->products_delivered_ids) ? '' : '#' . implode('#', $this->products_delivered_ids) . '#';
		// Fix paid products ids and string
		$this->products_paid_ids=[]; foreach($this->products_paid as $p) if(!in_array($p->id, $this->products_paid_ids)) $this->products_paid_ids[]=$p->id; $this->products_paid_ids_str=empty($this->products_paid_ids) ? '' : '#' . implode('#', $this->products_paid_ids) . '#';
		// Fix unpaid products ids and string
		$this->products_unpaid_ids=[]; foreach($this->products_unpaid as $p) if(!in_array($p->id, $this->products_unpaid_ids)) $this->products_unpaid_ids[]=$p->id; $this->products_unpaid_ids_str=empty($this->products_unpaid_ids) ? '' : '#' . implode('#', $this->products_unpaid_ids) . '#';
		// Count sent products
		$this->total_products_sent=count($this->products_sent);
		// Count prepared products
		$this->total_products_prepared=count($this->products_prepared);
		// Count delivered products
		$this->total_products_delivered=count($this->products_delivered);
		// Count paid products
		$this->total_products_paid=count($this->products_paid);
		// Count unpaid products
		$this->total_products_unpaid=count($this->products_unpaid);
		return $this->products;
	}

	function GetPayments($only_completed=true) {
		$this->payments=[];
		$this->payments_ids=[];
		if(!empty($this->id)) {
			$sql="SELECT * FROM PAYMENT WHERE order_id=" . DB::Quote($this->id) . " " . ($only_completed ? " AND completed=1 " : "") . " ORDER BY date_created;";
			if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
				$payment=new Payment;
				$payment->CreateFromArray($row);
				$this->payments[]=$payment;
				$this->payments_ids[]=$payment->id;
			}
		}
		$this->payments_ids_str=empty($this->payments_ids) ? '' : '#' . implode('#', $this->payments_ids) . '#';
		return $this->payments;
	}

	function UpdatePaid($payment) {

		if(empty($payment)) return new Response(false, Strings::Get('error_no_payment'));
		if(empty($payment->rows_ids_qnt)) return new Response(false, Strings::Get('error_no_payment_rows_ids_qnt'));
		$rows_ids_qnt=is_string($payment->rows_ids_qnt) ? json_decode($payment->rows_ids_qnt) : $payment->rows_ids_qnt;

		if(!isset($this->products) || empty($this->products)) $this->GetProducts();
		if(empty($this->products)) return new Response(false, Strings::Get('error_order_has_no_products'));

		$rows_to_update=[];
		foreach($rows_ids_qnt as $r_index=>$product_row) {
			foreach($this->products as $p_index=>$product) {
				$fixed_row_id="_{$product->row_id}";
				if($product->row_id==$product_row->row_id) {
					if(!$product->paid) {
						if(empty($rows_to_update[$fixed_row_id])) $rows_to_update[$fixed_row_id]=[ 'paid_amount' => $product->paid_amount, 'paid_quantity' => $product->paid_quantity, 'paid' => $product->paid ];
						$rows_to_update[$fixed_row_id]['paid_amount']+=$product_row->amount;
						$rows_to_update[$fixed_row_id]['paid_quantity']+=$product_row->quantity;
						$rows_to_update[$fixed_row_id]['paid']=$rows_to_update[$fixed_row_id]['paid_amount']>=$product->amount;
					}
				}
			}
		}
		if(empty($rows_to_update)) return new Response(false, Strings::Get('error_no_rows_to_update'), [ 'rows_ids_qnt' => $rows_ids_qnt, 'order_products' => $this->products ]);
		$sql='';
		foreach($rows_to_update as $row_id=>$pay_data) {
			$sql.='
				UPDATE ORDER_PRODUCT SET
					paid_amount=' . DB::Quote($pay_data['paid_amount']) . ',
					paid_quantity=' . DB::Quote($pay_data['paid_quantity']) . ',
					paid=' . ($pay_data['paid'] ? '1' : '0') . '
				WHERE id=' . DB::Quote(Strings::KeepOnlyNumbers($row_id)) . ';
			';
		}
		$update=DB::Update($sql);
		// If order products are updated, update order amounts
		if($update) $update=$this->UpdateAmounts();
		if($update && $payment->completed && $payment->type==1 && $payment->gateway>0) {
			// Get shop managers and baristas
			if(empty($this->company)) $this->GetCompany();
			if(empty($this->tables)) $this->GetTables();
			if(empty($this->waiters_ids)) $this->GetWaiters();
			$notification_users_ids=$this->waiters_ids;
			$managers_and_baristas=array_merge($this->company->GetShopManagers(), $this->company->GetBaristas());
			foreach($managers_and_baristas as $m) $notification_users_ids[]=$m->id;
			foreach($notification_users_ids as $user_id) {
				$notification=new Notification;
				$notification->from_customer_id=$payment->customer_id;
				$notification->to_user_id=$user_id;
				$notification->date_sent=date('Y-m-d H:i:s');
				foreach(LANGUAGES as $lang) {
					$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('customer_paid_with_card_title', $lang), array_merge($this->tables, [ $this, $payment ]));
					$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('customer_paid_with_card_message', $lang), array_merge($this->tables, [ $this, $payment ]));
				}
				$notification->Save();
			}
		}
		return new Response($update, $update ? 'OK' : Strings::Get('error_updating_paid_order_rows'));
	}

	function GetProductLines($only_unpaid=true) {
		$this->product_lines=[];
		$this->unique_products_rows_ids=[];
		$row_index=-1;
		if($this->products) foreach($this->products as $product_index=>$product) {
			// Check if product is paid
			if($only_unpaid && $product->paid) continue;
			// Check if product quantity is integer
			if($product->unit_is_integer) {
				for($q=1;$q<=$product->quantity;$q++) {
					$row_index++;
					$product_line=new OrderProduct;
					$product_line->CopyFrom($product);
					$product_line->quantity=1;
					$product_line->selected=1;
					$product_line->row_index=$row_index;
					$product_line->GetAmount();
					$this->product_lines[]=$product_line;
					if(!in_array($product_line->row_id, $this->unique_products_rows_ids)) $this->unique_products_rows_ids[]=$product_line->row_id;
				}
			} else {
				$row_index++;
				$product_line=new OrderProduct;
				$product_line->CopyFrom($product);
				$product_line->row_index=$row_index;
				$product_line->selected=1;
				$product_line->to_pay=1;
				$product_line->GetAmount();
				$this->product_lines[]=$product_line;
				if(!in_array($product_line->row_id, $this->unique_products_rows_ids)) $this->unique_products_rows_ids[]=$product_line->row_id;
			}
		}
		$this->unique_products_rows_ids_str=implode(',', $this->unique_products_rows_ids);
		return $this->product_lines;
	}

	function GetAmountsAndSave($get_products_from_db=false) {
		$this->GetAmounts($get_products_from_db);
		DB::Update('UPDATE ORDERS SET total_amount=' . DB::Quote($this->total_amount) . ', paid_amount=' . DB::Quote($this->paid_amount) . ', tip_amount=' . DB::Quote($this->tip_amount) . ' WHERE id=' . DB::Quote($this->id));
	}

	function GetAmounts($get_products_from_db=false) {
		$this->products_amount=0;
		$this->products_net_amount=0;
		$this->products_vat_amount=0;
		$this->paid_amount=0;
		if($get_products_from_db || empty($this->products)) $this->GetProducts();
		if(!empty($this->products)) foreach($this->products as $product) {
			$this->products_amount+=$product->amount;
			$this->products_net_amount+=$product->net_amount;
			$this->products_vat_amount+=$product->vat_amount;
			if($product->paid) $this->paid_amount+=$product->amount;
		}
		$this->total_amount=$this->products_amount + $this->tip_amount;
		$this->unpaid_amount=max(0, $this->products_amount - $this->paid_amount);
	}

	function GetTime() {
		$this->time=empty($this->date_created) ? '00:00' : substr($this->date_created, 11, 5);
		return $this->time;
	}

	function RecalculateProductsAmount() {
		// Update order product vat percent where is missing
		$sql="
			UPDATE ORDER_PRODUCT SET vat_percent=(
				SELECT percent FROM VAT_CATEGORY WHERE id IN (
					SELECT vat_category_id FROM PRODUCT WHERE id=ORDER_PRODUCT.product_id
				)
			) WHERE vat_percent=0 AND order_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		// Update order product net amount
		$sql="
			UPDATE ORDER_PRODUCT
			SET net_amount=amount / (1 + vat_percent / 100)
			WHERE order_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		// Update order product vat amount
		$sql="
			UPDATE ORDER_PRODUCT
			SET vat_amount=amount - net_amount
			WHERE order_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		// Update order product paid amount
		$sql="
			UPDATE ORDER_PRODUCT
			SET paid_amount=amount, paid_quantity=quantity
			WHERE order_id=" . DB::Quote($this->id) . " AND paid=1;
		";
		DB::Update($sql, true);

		return $this->UpdateAmounts();
	}

	function UpdateAmounts() {
		// Update products, paid and tips amount
		$sql="
			UPDATE ORDERS SET
				products_amount=(SELECT SUM(amount) FROM ORDER_PRODUCT WHERE order_id=" . DB::Quote($this->id) . " AND date_canceled IS NULL),
				products_net_amount=(SELECT SUM(net_amount) FROM ORDER_PRODUCT WHERE order_id=" . DB::Quote($this->id) . " AND date_canceled IS NULL),
				products_vat_amount=(SELECT SUM(vat_amount) FROM ORDER_PRODUCT WHERE order_id=" . DB::Quote($this->id) . " AND date_canceled IS NULL),
				tip_amount=COALESCE((SELECT SUM(tip_amount) FROM PAYMENT WHERE completed=1 AND order_id=" . DB::Quote($this->id) . "), 0),
				paid_amount=COALESCE((SELECT SUM(paid_amount) FROM ORDER_PRODUCT WHERE order_id=" . DB::Quote($this->id) . " AND date_canceled IS NULL), 0)
			WHERE id=" . DB::Quote($this->id) . ";
		";
		$update=DB::Update($sql, true);
		if(!$update) return $update;

		// Update order total amount
		$sql="UPDATE ORDERS SET total_amount=products_amount + tip_amount WHERE id=" . DB::Quote($this->id) . ";";
		return DB::Update($sql, true);
	}

	function SetTablesIds($tables_ids) {
		if(empty($this->id)) return new Response(false, Strings::Get('error_no_order_id'));
		if(empty($tables_ids)) return new Response(false, Strings::Get('error_empty_tables'));
		if(is_string($tables_ids)) $tables_ids=explode(',', $tables_ids);
		if(empty($tables_ids)) return new Response(false, Strings::Get('error_empty_tables'));
		$sql='';
		foreach($tables_ids as $table_index=>$table_id) $sql.=($sql=='' ? '' : ',') . '(' . DB::Quote($this->id) . ',' . DB::Quote($table_id) . ')';
		if($sql) {
			$sql_delete="DELETE FROM ORDER_TABLE WHERE order_id=" . DB::Quote($this->id) . ";";
			if(!DB::Update($sql_delete, true)) return new Response(false, Strings::Get('error_deleting_tables'), $sql_delete);
			$sql="INSERT INTO ORDER_TABLE (order_id, table_id) VALUES {$sql};";
			if(!DB::Insert($sql)) return new Response(false, Strings::Get('error_inserting_tables'), $sql);
		}
		return new Response(true, 'OK');
	}

	function Complete() {
		if(empty($this->id) || $this->id<0) return new Response(false, Strings::Get('error_no_order_id'));
		if($this->completed) return new Response(false, Strings::Get('error_order_is_already_completed'));
		// Begin transaction
		DB::BeginTransaction();
		// Free order tables
		if(empty($this->tables_ids)) $this->GetTables();
		if(!empty($this->tables_ids)) {
			$sql="UPDATE TABLES SET occupied=0 WHERE id IN ('" . implode("', '", $this->tables_ids) . "');";
			$complete=DB::Update($sql);
			if(!$complete) {
				DB::RollBackTransaction();
				return new Response(false, Strings::Get('error_completing_order'), "Cannot update tables.\n$sql");
			}
		}
		// Update completed
		$sql="UPDATE {$this->table} SET completed=1 WHERE id=" . DB::Quote($this->id) . ";";
		$complete=DB::Update($sql);
		if(!$complete) {
			DB::RollBackTransaction();
			return new Response(false, Strings::Get('error_completing_order'), "Cannot update order.\n$sql");
		}
		$this->RecalculateProductsAmount();
		DB::CommitTransaction();
		return new Response(true, 'OK');
	}

	function NotifyShopManagersAndBaristas($title_string_id, $message_string_id) {
		if(empty($this->customer)) $this->GetCustomer();
		if(empty($this->company)) $this->GetCompany();
		if(empty($this->tables)) $this->GetTables();
		$managers_and_baristas=array_merge($this->company->GetShopManagers(), $this->company->GetBaristas());
		$managers_and_baristas_ids=[]; foreach($managers_and_baristas as $m) $managers_and_baristas_ids[]=$m->id;
		foreach($managers_and_baristas_ids as $user_id) {
			$notification=new Notification;
			$notification->from_session=session_id();
			$notification->from_admin_id=Session::IsAdmin() ? Session::Admin()->id : null;
			$notification->from_user_id=Session::IsUser() ? Session::User()->id : null;
			$notification->to_user_id=$user_id;
			$notification->date_sent=date('Y-m-d H:i:s');
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('title_string_id', $lang), [ $this, $this->customer, $this->company, $this->tables ]);
				$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(Strings::Get('message_string_id', $lang), [ $this, $this->customer, $this->company, $this->tables ]);
			}
			$notification->Save();
		}
	}

	function Cancel() {
		if(empty($this->id) || $this->id<0) return new Response(false, Strings::Get('error_no_order_id'));
		if(!empty($this->date_canceled)) return new Response(false, Strings::Get('error_order_is_already_canceled'));
		$this->GetData();
		$this->GetCompany();
		// Check if order has documents
		if(!empty($documents)) return new Response(false, Strings::Get('error_order_has_documents'));
		// Check if order has products
		if(!empty($this->products_rows_ids)) { // Has products
			// Cancel all products
			$cancel_products=$this->CancelProducts(implode(',', $this->products_rows_ids));
			if(!$cancel_products->status) return $cancel_products;
		}
		$this->RecalculateProductsAmount();
		$this->date_canceled=date('Y-m-d H:i:s');
		$save=$this->Save();
		if($save && $save['status']) {
			// Notify shop managers and baristas
			$this->NotifyShopManagersAndBaristas('order_canceled', empty($this->payments) ? 'order_canceled_without_refunds' : 'order_canceled_with_refunds');
			return new Response(true, Strings::Get('order_canceled'));
		}
		return new Response(false, Strings::Get('error_cancelling_order'));
	}

	function Cancel_old() {
		if(empty($this->id) || $this->id<0) return new Response(false, Strings::Get('error_no_order_id'));
		if(!empty($this->date_canceled)) return new Response(false, Strings::Get('error_order_is_already_canceled'));
		return new Response(false, "Feature is not ready yet");
		$this->GetData();
		$this->GetCompany();
		// Check if order has documents
		if(!empty($documents)) return new Response(false, Strings::Get('error_order_has_documents'));
		$cancel_order=true;
		// Check if order has products
		if(!empty($products)) {


			/*
			// Check if has delivered products
			if(!empty($this->products_delivered)) {
				// Check if has delivered unpaid products
				foreach($this->products_delivered as $product) {
					if(!$product->paid) return new Response(false, Strings::Get('error_order_has_unpaid_delivered_products'));
				}
			}
			*/
			$managers_and_baristas=array_merge($this->company->GetShopManagers(), $this->company->GetBaristas());
			$managers_and_baristas_ids=[]; foreach($managers_and_baristas as $m) $managers_and_baristas_ids[]=$m->id;
			$notification_per_user=[];

			// Loop through products
			foreach($products as &$product) {
			// Check if product is paid
				if($product->paid) { // Paid
					$cancel_order=false;
					// Check payment type
					if($rows=DB::Query("SELECT type, holder_user_id FROM PAYMENT WHERE completed=1 AND order_id=" . DB::Quote($this->id) . " AND order_products_rows_ids LIKE '%#" . $product->row_id . "#%' LIMIT 1;")) {
						// Check type
						if($rows[0]['type']==0) { // Cash
							if(!isset($notification_per_user['user_' . $rows[0]['holder_user_id']])) $notification_per_user['user_' . $rows[0]['holder_user_id']]=[ 'return_cash' => 0, 'return_card' => 0, 'return_cash_from_user' => [] ];
							$notification_per_user['user_' . $rows[0]['holder_user_id']]['return_cash']+=$product->amount;
							if(!in_array($rows[0]['holder_user_id'], $managers_and_baristas_ids)) foreach($managers_and_baristas_ids as $m) {
								if(!isset($notification_per_user['user_' . $m])) $notification_per_user['user_' . $m]=[ 'return_cash' => 0, 'return_card' => 0, 'return_cash_from_user' => [] ];
								if(!isset($notification_per_user['user_' . $m]['return_cash_from_user']['user_' . $rows[0]['holder_user_id']])) $notification_per_user['user_' . $m]['return_cash_from_user']['user_' . $rows[0]['holder_user_id']]=0;
								$notification_per_user['user_' . $m]['return_cash_from_user']['user_' . $rows[0]['holder_user_id']]+=$product->amount;
							}
						} else { // Card
							foreach($managers_and_baristas_ids as $m) {
								if(!isset($notification_per_user['user_' . $m])) $notification_per_user['user_' . $m]=[ 'return_cash' => 0, 'return_card' => 0, 'return_cash_from_user' => [] ];
								$notification_per_user['user_' . $m]['return_cash']+=$product->amount;
								$notification_per_user['user_' . $m]['return_card']+=$product->amount;
							}
						}
					}
				} else { // Not paid
					// Check if its delivered
					if($product->delivered) return new Response(false, Strings::Get('error_order_has_unpaid_delivered_products'));
				}
			}
			if(!empty($notification_per_user)) foreach($notification_per_user as $u=>$d) {
				$user_id=str_replace('user_', '', $u);
				$is_manager_or_barista=in_array($user_id, $managers_and_baristas_ids);
				$total_cash=$u['return_cash'];

				$notification=new Notification;
				$notification->from_session=session_id();
				$notification->from_admin_id=Session::IsAdmin() ? Session::Admin()->id : null;
				$notification->from_user_id=Session::IsUser() ? Session::User()->id : null;
				$notification->to_user_id=$user_id;
				$notification->date_sent=date('Y-m-d H:i:s');
				foreach(LANGUAGES as $lang) {
					$notification->{"title_{$lang}"}=Strings::Get('order_canceled', $lang);
					if($d['return_cash']) $notification->{"message_{$lang}"}=str_replace('#CASH#', $d['return_cash'] . '&euro;', Strings::Get('order_canceled_return_cash', $lang));
					$other_cash='';
					if(!empty($d['return_cash_from_user'])) foreach($d['return_cash_from_user'] as $u1=>$d1) {
						$user_id1=str_replace('user_', '', $u1);
						$other_cash.="\n" . str_replace('#CASH#', $d1 . '&euro;', Strings::Get('order_canceled_other_return_cash', $lang));
					}
					if($other_cash) $notification->{"message_{$lang}"}.=Strings::Get('following_users_must_return_cash', $lang) . $other_cash;
				}
				if($d['return_card']) {
					$notification->buttons=json_encode([
						[
							'text' => Strings::Get('cash_returned_cancel_order', $lang),
							'action' => "
								$('#view_notifications_list').css('filter', 'blur(5px)');
								Post('" . API_URL . "', { controller: 'order', action: 'do_cancel', order_id: " . $this->id . ", notification_id: $(button).attr('notification_id'), button_index: $(button).attr('button_index') }, GetNotifications);
							"
						]
					]);
				}
				$notification->Save();
			}
		}
		if($cancel_order) {
			$this->date_canceled=date('Y-m-d H:i:s');
			$save=$this->Save();
			return new Response($save['status'], $save['status'] ? 'OK' : $save['message'], $save['status'] ? $this : null);
		}
		return new Response(false, Strings::Get('error_cancelling_order'));
	}

	function AddProduct($product) {
		if(empty($this->id)) new Response(false, Strings::Get('error_no_order_id'));
		if(empty($product)) return new Response(false, Strings::Get('error_empty_product'));
		if(!is_object($product)) return new Response(false, Strings::Get('error_invalid_product'));

		// Check unit
		if(empty($product->unit_id)) return new Response(false, Strings::Get('error_empty_product_unit'));
		$unit=new Unit;
		if(!$unit->Load(['id' => $product->unit_id])) return new Response(false, Strings::Get('error_product_unit_not_found'));
		$product->unit_is_integer=$unit->is_integer;
		$product->unit=$unit;

		// Check if unit is integer to split product lines
		if($product->unit_is_integer && $product->quantity>1) {
			for($q=1;$q<=$product->quantity;$q++) {
				$sql='INSERT INTO ORDER_PRODUCT (order_id, product_id, unit_id, price, price_specs, quantity, discount, amount, comment, sent, prepared, delivered, paid) VALUES (
					' . DB::Quote($this->id) . ',
					' . DB::Quote($product->product_id) . ',
					' . DB::Quote($product->unit_id) . ',
					' . DB::Quote($product->price) . ',
					' . DB::Quote($product->price_specs) . ',
					1,
					' . DB::Quote($product->discount) . ',
					' . DB::Quote($product->amount) . ',
					' . DB::Quote($product->comment) . ',
					' . DB::Quote($product->sent ? '1' : '0') . ',
					' . DB::Quote($product->prepared ? '1' : '0') . ',
					' . DB::Quote($product->delivered ? '1' : '0') . ',
					' . DB::Quote($product->paid ? '1' : '0') . '
				);';
				// Insert product row
				if(!$product_row_id=DB::Insert($sql, false, true)) return new Response(false, Strings::Get('error_inserting_order_product') . "\n$sql" . print_r(DB::GetLastError(), true));

				// Get product specs
				if(!empty($product->specs)) {
					$sql='';
					foreach($product->specs as $spec) {
						$sql.=($sql=='' ? '' : ",\n") . '(
							' . DB::Quote($this->id) . ',
							' . DB::Quote($product_row_id) . ',
							' . DB::Quote($spec->product_id) . ',
							' . DB::Quote($spec->spec_id) . ',
							' . DB::Quote($spec->price) . '
							)
						';
					}
					$sql="INSERT INTO ORDER_PRODUCT_SPEC (order_id, order_product_row_id, order_product_id, product_spec_id, price) VALUES {$sql};";
					if(!DB::Insert($sql)) return new Response(false, Strings::Get('error_inserting_order_products_specs') . "\n$sql");
				}
			}
		} else {
			$sql='INSERT INTO ORDER_PRODUCT (order_id, product_id, unit_id, price, price_specs, quantity, discount, amount, comment, sent, prepared, delivered, paid) VALUES (
				' . DB::Quote($this->id) . ',
				' . DB::Quote($product->product_id) . ',
				' . DB::Quote($product->unit_id) . ',
				' . DB::Quote($product->price) . ',
				' . DB::Quote($product->price_specs) . ',
				' . DB::Quote($product->quantity) . ',
				' . DB::Quote($product->discount) . ',
				' . DB::Quote($product->amount) . ',
				' . DB::Quote($product->comment) . ',
				' . DB::Quote($product->sent ? '1' : '0') . ',
				' . DB::Quote($product->prepared ? '1' : '0') . ',
				' . DB::Quote($product->delivered ? '1' : '0') . ',
				' . DB::Quote($product->paid ? '1' : '0') . '
			)';
			// Insert product row
			if(!$product_row_id=DB::Insert($sql, false, true)) return new Response(false, Strings::Get('error_inserting_order_product') . "\n$sql" . print_r(DB::GetLastError(), true));

			// Get product specs
			if(!empty($product->specs)) {
				$sql='';
				foreach($product->specs as $spec) {
					$sql.=($sql=='' ? '' : ",\n") . '(
						' . DB::Quote($this->id) . ',
						' . DB::Quote($product_row_id) . ',
						' . DB::Quote($spec->product_id) . ',
						' . DB::Quote($spec->spec_id) . ',
						' . DB::Quote($spec->price) . '
						)
					';
				}
				$sql="INSERT INTO ORDER_PRODUCT_SPEC (order_id, order_product_row_id, order_product_id, product_spec_id, price) VALUES {$sql};";
				if(!DB::Insert($sql)) return new Response(false, Strings::Get('error_inserting_order_products_specs'), $sql);
			}
		}
		return new Response(true, 'OK');
	}

	function UpdateProducts($products) {
		if(empty($this->id) || $this->id<0) new Response(false, Strings::Get('error_no_order_id'));
		if(is_string($products)) $products=@json_decode($products);
		if($products=='') return new Response(false, Strings::Get('error_invalid_products_data'));

		try {
			// Begin transaction
			DB::BeginTransaction();

			// Delete specs rows
			$sql='DELETE FROM ORDER_PRODUCT_SPEC WHERE order_id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);

			// Delete products rows
			$sql='DELETE FROM ORDER_PRODUCT WHERE order_id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);

			foreach($products as $index=>$product) {
				if(empty($product->company_id) && !empty($product->product->company_id)) $product->company_id=$product->product->company_id;
				// Check company id
				if($product->company_id!=$this->company_id) throw new Exception(Strings::Get('error_product_company_mismatch'));
				for($i=$product->unit_is_integer ? 1 : $product->quantity;$i<=$product->quantity;$i++) {
					// Insert product row
					$sql='
						INSERT INTO ORDER_PRODUCT (order_id, product_id, unit_id, price, price_specs, quantity, discount, amount, comment, sent, prepared, delivered, paid, date_printed, date_canceled) VALUES (
						' . DB::Quote($this->id) . ',
						' . DB::Quote($product->product_id) . ',
						' . DB::Quote($product->unit_id) . ',
						' . DB::Quote($product->price) . ',
						' . DB::Quote($product->price_specs) . ',
						' . DB::Quote($product->unit_is_integer ? 1 : $product->quantity) . ',
						' . DB::Quote($product->discount) . ',
						' . DB::Quote(round(($product->price + $product->price_specs) * ($product->unit_is_integer ? 1 : $product->quantity) * ((100 - $product->discount) / 100), 2)) . ',
						' . DB::Quote($product->comment) . ',
						' . DB::Quote($product->sent) . ',
						' . DB::Quote($product->prepared) . ',
						' . DB::Quote($product->delivered) . ',
						' . DB::Quote($product->paid) . ',
						' . (empty($product->date_printed) ? 'NULL' : DB::Quote($product->date_printed)) . ',
						' . (empty($product->date_canceled) ? 'NULL' : DB::Quote($product->date_canceled)) . '
						);
					';
					//if(IsAdamIp()) diep($sql);
					if(!$product_row_id=DB::Insert($sql, false, true)) throw new Exception(Strings::Get('error_inserting_order_products'));
					// Get product specs
					if(!empty($product->specs)) {
						$sql='';
						foreach($product->specs as $spec) {
							$sql.=($sql=='' ? '' : ",\n") . '(
								' . DB::Quote($this->id) . ',
								' . DB::Quote($product_row_id) . ',
								' . DB::Quote($product->product_id) . ',
								' . DB::Quote($spec->spec_id) . ',
								' . DB::Quote($spec->price) . '
								)
							';
						}
						$sql="INSERT INTO ORDER_PRODUCT_SPEC (order_id, order_product_row_id, order_product_id, product_spec_id, price) VALUES {$sql};";
						if(!DB::Insert($sql)) throw new Exception(Strings::Get('error_inserting_order_products_specs') . "\n" . $sql . "\n" . print_r(DB::GetLastError(), true));
					}
				}
			}
			//$this->GetData();
			$this->Save();
			$this->GetAmountsAndSave();
			DB::CommitTransaction();
			$this->GetData();
			return new Response(true, 'OK', $this);
		} catch(Exception $e) {
			DB::RollBackTransaction();
			return new Response(false, $e->getMessage());
		}
	}

	function CreatePaymentByUser($user_id, $method, $products_rows_ids, $tip_amount, $tip_user_id) {
		// Get order data
		$this->GetData();
		// Get company
		if(empty($this->company_id)) return new Response(false, Strings::Get('error_missing_company_id'));
		// Check user id
		if(empty($user_id)) return new Response(false, Strings::Get('error_missing_user_id'));
		// Load user
		$user=new User;
		if(!$user->Load(['id' => $user_id])) return new Response(false, Strings::Get('error_user_not_found'));
		// Check user and company
		if($this->company_id!=$user->company_id) return new Response(false, Strings::Get('error_user_company_mismatch'));
		// Check company
        $company=new Company;
        if(!$company->Load(['id' => $this->company_id])) return new Response(false, Strings::Get('error_company_not_found'));
		// Check products_rows_ids
		if(empty($products_rows_ids)) return new Response(false, Strings::Get('error_missing_products_rows_ids'));
		if(is_string($products_rows_ids)) $products_rows_ids=explode(',', $products_rows_ids);
		// Check method
		if(!in_array($method, ['cash', 'card'])) return new Response(false, Strings::Get('error_invalid_payment_method'));

		// Get tip amount
		$tip_amount=empty($tip_amount) || !is_numeric($tip_amount) ? 0 : floatval($tip_amount);
		if($tip_amount<=0) $tip_user_id='';
		if($tip_user_id) {
			// Check user
			$tip_user=new User;
			if(!$tip_user->Load(['id'=>$tip_user_id])) return new Response(false, Strings::Get('error_invalid_tip_user_id'));
			if($tip_user->company_id!=$this->company_id) return new Response(false, Strings::Get('error_invalid_tip_user_id'));
		}

		// Initialize payment data
		$payment_products=[];
		$payment_rows_ids_qnt=[];
		$payment_amount=0;
		$payment_products_amount=0;

		// Check products
		foreach($products_rows_ids as $row_id) {
			if(empty($row_id)) continue;
			$order_product=null;
			foreach($this->products as $product_index=>$product) {
				if($product->row_id==$row_id) {
					$order_product=$product;
					if($product->paid) return new Response(false, str_replace('#PRODUCT#', $product->product->name, Strings::Get('error_product_is_already_paid')));
					$product->paid=1;
					$payment_products[]=$product;
					$payment_amount+=$product->amount;
					$payment_products_amount+=$product->amount;
					$payment_rows_ids_qnt[]=[ 'row_id' => $product->row_id, 'quantity' => $product->quantity, 'amount' => $product->amount ];
				}
			}
			if($order_product==null) return new Response(false, str_replace('#ID#', $row_id, Strings::Get('error_no_product_row_found_with_id')));
		}

		// Create payment
		$payment=new Payment;
		$payment->company_id=$this->company_id;
		$payment->order_id=$this->id;
		$payment->customer_id=$this->customer_id;
		$payment->user_id=$user_id;
		$payment->type=$method=='card' ? 1 : 0;
		$payment->gateway=$payment->type==0 ? 0 : $company->payment_gateway;
		$payment->amount=$payment_amount + $tip_amount;
		$payment->products_amount=$payment_products_amount;
		$payment->tip_amount=$tip_amount;
		$payment->tip_user_id=$tip_user_id;
		$payment->order_products_rows_ids='#' . implode('#', $products_rows_ids) . '#';
		$payment->products=json_encode($payment_products);
		$payment->session_id=session_id();
		$payment->completed=1;
		$payment->holder_user_id=$user_id;
		$save=$payment->Save();

		// Check save status
		if($save['status']) {
			// Update paid order products
			$sql="UPDATE ORDER_PRODUCT SET paid=1, paid_amount=amount, paid_quantity=quantity WHERE id IN ('" . implode("', '", $products_rows_ids) . "') AND order_id=" . DB::Quote($this->id) . ";";
			$update=DB::Update($sql);
			$this->GetAmountsAndSave(true);
			$this->RecalculateProductsAmount();
			return new Response($update, $update ? 'OK' : Strings::Get('error_updating_paid_order_products'), $update ? $payment : null);
		} else {
			return new Response(false, $save['message']);
		}
	}

	function SendProductsToDepartment($rows_ids, $department_id) {
		// Check rows ids
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		// Load department
		$department=new Department;
		if(!$department->Load(['id' => $department_id])) return new Response(false, Strings::Get('error_department_not_found'));
		if($department->company_id!=$this->company_id) return new Response(false, Strings::Get('error_department_company_mismatch'));

		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return new Response(false, Strings::Get('error_order_product_not_found'));
			// Add order product
			$order_products[]=$order_product;
		}
		if(empty($order_products)) return new Response(false, Strings::Get('error_no_products_found'));

		$sql="UPDATE ORDER_PRODUCT SET sent=1, sent_to_department_id=" . DB::Quote($department_id) . " WHERE id IN ('" . implode("', '", $rows_ids) . "') AND order_id=" . DB::Quote($this->id) . ";";
		DB::Update($sql);

		$data='';
		if(!empty($department->form_header) || !empty($department->form_products)) $data=Strings::CreateEncryptedLink(BASE_URL . 'order/print_to_department/?id=' . $this->id . '&department_id=' . $department_id . '&products_rows_ids=' . implode(",", $rows_ids));
		return new Response(true, 'OK', $data);

	}


	//Created by Jim
	public static function  AutoSendProductsToDepartment($products, $company_id){
		// Check rows ids
		if(empty($products)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

		// Loop through rows ids
		foreach($products as $product){
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $product['order_product_row_id']])) return new Response(false, Strings::Get('error_order_product_not_found'));
			$result = DB::Query('SELECT PC.department_id FROM `PRODUCT` P inner join PRODUCT_CATEGORY PC on P.category_id = PC.id where P.id='.$product['product_id']);
			$department_id = $result[0]['department_id'];

			// Load department
			$department=new Department;
			if(!$department->Load(['id' => $department_id])) return new Response(false, Strings::Get('error_department_not_found'));
			if($department->company_id!=$company_id) return new Response(false, Strings::Get('error_department_company_mismatch'));
			$sql="UPDATE ORDER_PRODUCT SET sent=1, sent_to_department_id=" . DB::Quote($department_id) . " WHERE id=".$product['order_product_row_id'];
			DB::Update($sql);
		}
		return new Response(true, 'OK');
	}
	
	//Created by Jim
	public static function  ResendProductsToDepartment($order_id){
		$sql="UPDATE ORDER_PRODUCT SET date_printed=null  WHERE order_id=".$order_id;
		DB::Update($sql);
	}
	


	function MarkProductsPrepared($rows_ids) {
		// Check rows ids
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return new Response(false, Strings::Get('error_order_product_not_found'));
			// Add order product
			$order_products[]=$order_product;
		}
		if(empty($order_products)) return new Response(false, Strings::Get('error_no_products_found'));

		$sql="UPDATE ORDER_PRODUCT SET prepared=1 WHERE id IN ('" . implode("', '", $rows_ids) . "') AND order_id=" . DB::Quote($this->id) . ";";
		$update=DB::Update($sql);
		return new Response($update ? true : false, $update ? 'OK' : Strings::Get('error_marking_products_as_prepared'));
	}

	function MarkProductsDelivered($rows_ids) {
		// Check rows ids
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return new Response(false, Strings::Get('error_order_product_not_found'));
			// Add order product
			$order_products[]=$order_product;
		}
		if(empty($order_products)) return new Response(false, Strings::Get('error_no_products_found'));

		$sql="UPDATE ORDER_PRODUCT SET delivered=1 WHERE id IN ('" . implode("', '", $rows_ids) . "') AND order_id=" . DB::Quote($this->id) . ";";
		$update=DB::Update($sql);
		return new Response($update ? true : false, $update ? 'OK' : Strings::Get('error_marking_products_as_delivered'));
	}

	function CancelProducts($rows_ids) {
		// Check rows ids
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return new Response(false, Strings::Get('error_order_product_not_found'));
			$order_product->order_product_row_id=$row_id;
			// Add order product
			$order_products["_{$row_id}"]=$order_product;
		}
		if(empty($order_products)) return new Response(false, Strings::Get('error_no_products_found'));

		$this->GetData();
		$this->company=new Company; $this->company->Load(['id' => $this->company_id]);
		$this->company->GetUsers();

		$replace_models=[ $this, $this->company, $this->customer ];
		if($this->tables) foreach($this->tables as $model) $replace_models[]=$model;
		if($this->products) foreach($this->products as $model) $replace_models[]=$model;
		if($this->payments) foreach($this->payments as $model) $replace_models[]=$model;
		if($this->documents) foreach($this->documents as $model) $replace_models[]=$model;

		DB::BeginTransaction();

		$refund=0;
		$paid_order_products=[];

		// Notify users
		$user_notification=[];
		foreach($order_products as $order_product_row_id=>$order_product) {
			// Get products name and specs
			$name_and_specs=[];
			foreach(LANGUAGES as $lang) $name_and_specs[$lang]=$order_product->GetNameAndSpecs($lang);
			// Check if product is sent and not prepared
			if($order_product->sent && $order_product->sent_to_department_id && !$order_product->prepared) {
				// Create notification for department
				$department=new Department;
				if(!$department->Load(['id' => $order_product->sent_to_department_id])) continue;
				$users=$department->GetUsers();
				foreach($users as $user) {
					if(Session::IsUser() && $user->id==Session::User()->id) continue;
					$final_replace_models=array_merge($replace_models, [$user]);

					if(!isset($user_notification[$user->id])) {
						$user_notification[$user->id]=[];
						$user_notification[$user->id]['lang']=$user->language<count(LANGUAGES) ? LANGUAGES[$user->language] : DEFAULT_LANG;
						foreach(LANGUAGES as $lang) {
							$user_notification[$user->id]["title_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_cancel_order_items_title', $lang), $final_replace_models);
							$user_notification[$user->id]["message_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_cancel_order_items_message', $lang), $final_replace_models) . ":\n";
							$user_notification[$user->id]["items_{$lang}"]='';
						}
					}
					foreach(LANGUAGES as $lang) {
						$user_notification[$user->id]["items_{$lang}"].=($user_notification[$user->id]["items_{$lang}"]=='' ? '' : str_repeat('_', 30) . "\n") .
							Strings::Get('item', $lang) . ': ' . $name_and_specs[$lang] . "\n" .
							Strings::Get('quantity', $lang) . ': ' . $order_product->quantity . "\n" .
							($order_product->comment=='' ? '' : Strings::Get('comment', $lang) . ': ' . $order_product->comment . "\n");
					}
				}
			}

			// Create notification for managers and baristas
			$users=array_merge($this->company->shop_managers, $this->company->baristas);
			foreach($users as $user) {
				if(Session::IsUser() && $user->id==Session::User()->id) continue;
				$final_replace_models=array_merge($replace_models, [$user]);

				if(!isset($user_notification[$user->id])) {
					$user_notification[$user->id]=[];
					$user_notification[$user->id]['lang']=$user->language<count(LANGUAGES) ? LANGUAGES[$user->language] : DEFAULT_LANG;
					foreach(LANGUAGES as $lang) {
						$user_notification[$user->id]["title_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_cancel_order_items_title', $lang), $final_replace_models);
						$user_notification[$user->id]["message_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_cancel_order_items_message', $lang), $final_replace_models) . ":\n";
						$user_notification[$user->id]["items_{$lang}"]='';
					}
				}
				foreach(LANGUAGES as $lang) {
					$user_notification[$user->id]["items_{$lang}"].=($user_notification[$user->id]["items_{$lang}"]=='' ? '' : str_repeat('_', 30) . "\n") .
						Strings::Get('item', $lang) . ': ' . $name_and_specs[$lang] . "\n" .
						Strings::Get('quantity', $lang) . ': ' . $order_product->quantity . "\n" .
						($order_product->comment=='' ? '' : Strings::Get('comment', $lang) . ': ' . $order_product->comment . "\n");
				}
			}

			// Check if product is paid
			if($order_product->paid) {
				$refund+=$order_product->amount;
				$paid_order_products["_{$order_product_row_id}"]=$order_product;
			}
		}

		if($user_notification) foreach($user_notification as $user_id=>$udn) {
			$notification=new Notification;
			$notification->from_session=session_id();
			$notification->from_admin_id=Session::IsAdmin() ? Session::Admin()->id : null;
			$notification->from_user_id=Session::IsUser() ? Session::User()->id : null;
			$notification->to_user_id=$user_id;
			$notification->date_sent=date('Y-m-d H:i:s');
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=$udn["title_{$lang}"];
				$notification->{"message_{$lang}"}=$udn["message_{$lang}"] . $udn["items_{$lang}"];
				if($refund>0) $notification->{"message_{$lang}"}.=str_replace('#AMOUNT#', $refund, Strings::Get('order_product_cancel_total_refund', $lang));
			}
			$notification->buttons=json_encode([
				Notification::GetGotItButton($udn['lang']),
				[
					'text' => Strings::Get('view_order', $lang),
					'action' => "
						$('#view_notifications_list').css('filter', 'blur(5px)');
						window.location='" . Strings::CreateEncryptedLink(BASE_URL . 'order/edit/?id=' . $this->id) . "';
					"
				]
			]);
			$notification->Save();
		}

		// Create refund payments for each row
		$payments_insert='';
		foreach($paid_order_products as $row_id=>$order_product) {
			$order_product->quantity=-$order_product->quantity;
			$order_product->amount=-$order_product->amount;
			$order_product->vat_amount=-$order_product->vat_amount;
			$order_product->net_amount=-$order_product->net_amount;
			$row_id=Strings::KeepOnlyNumbers($row_id);
			$sql="SELECT * FROM PAYMENT WHERE order_id=" . DB::Quote($this->id) . " AND completed=1 AND order_products_rows_ids LIKE '%#{$row_id}#%';";
			if($payments_rows=DB::Query($sql)) foreach($payments_rows as $row) {
				$payments_insert.=($payments_insert=='' ? '' : ", \n") . '( ' .
					DB::Quote($row['company_id']) . ', ' .
					DB::Quote($row['order_id']) . ', ' .
					DB::Quote($row['customer_id']) . ', ' .
					DB::Quote(Session::UserId()) . ', ' .
					'0, ' .
					'0, ' .
					$order_product->amount . ", " .
					DB::Quote("#{$row_id}#") . ', ' .
					DB::Quote(json_encode($order_product)) . ', ' .
					$order_product->amount . ', ' .
					DB::Quote(session_id()) . ', ' .
					'1, ' .
					DB::Quote(Session::UserId()) . ', ' .
					"'Canceled item'" .
				")";
			}
		}
		if($payments_insert) {
			$sql="INSERT INTO PAYMENT (company_id, order_id, customer_id, user_id, type, gateway, amount, order_products_rows_ids, products, products_amount, session_id, completed, holder_user_id, status) VALUES {$payments_insert};";
			$insert=DB::Insert($sql, false, true);
			if(!$insert) {
				DB::CommitTransaction();
				return new Response(false, Strings::Get('error_creating_refunds') . "\n" . print_r(DB::GetLastError(), $sql));
			}
		}

		// Delete products rows
		$sql="UPDATE ORDER_PRODUCT SET date_canceled=" . DB::Quote(date('Y-m-d H:i:s')) . " WHERE order_id=" . DB::Quote($this->id) . " AND id IN ('" . implode("', '", $rows_ids) . "');";
		$update=DB::Update($sql);

		if($update===false) DB::RollBackTransaction(); else DB::CommitTransaction();
		return new Response($update!==false, $update!==false ? 'OK' : Strings::Get('error_canceling_order_products') . "\n" . print_r(DB::GetLastError(), true));
	}

	function CancelProductsRequest($rows_ids) {
		// Check rows ids
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

		$update_to_be_canceled='';
		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return new Response(false, Strings::Get('error_order_product_not_found'));
			// Add order product
			$order_products[]=$order_product;
			// Build $update_to_be_canceled sql
			$update_to_be_canceled.=($update_to_be_canceled=='' ? '' : ', ') . DB::Quote($row_id);
		}
		if(empty($order_products)) return new Response(false, Strings::Get('error_no_products_found'));

		if($update_to_be_canceled) DB::Update("UPDATE ORDER_PRODUCT SET to_be_canceled=1 WHERE id IN ({$update_to_be_canceled});");

		$this->GetData();
		$this->company=new Company; $this->company->Load(['id' => $this->company_id]);
		$this->company->GetUsers();

		$replace_models=[ $this, $this->company, $this->customer ];
		if($this->tables) foreach($this->tables as $model) $replace_models[]=$model;
		if($this->products) foreach($this->products as $model) $replace_models[]=$model;
		if($this->payments) foreach($this->payments as $model) $replace_models[]=$model;
		if($this->documents) foreach($this->documents as $model) $replace_models[]=$model;

		// Notify waiters
		$user_notification=[];
		foreach($order_products as $order_product) {
			// Get products name and specs
			$name_and_specs=[];
			foreach(LANGUAGES as $lang) $name_and_specs[$lang]=$order_product->GetNameAndSpecs($lang);

			// Create notification for waiters and shop managers
			$users=array_merge($this->company->waiters, $this->company->shop_managers);
			foreach($users as $user) {
				if(Session::IsUser() && $user->id==Session::User()->id) continue;
				$final_replace_models=array_merge($replace_models, [$user]);

				if(!isset($user_notification[$user->id])) {
					$user_notification[$user->id]=[];
					$user_notification[$user->id]['lang']=$user->language<count(LANGUAGES) ? LANGUAGES[$user->language] : DEFAULT_LANG;
					$user_notification[$user->id]['position']=$user->position;
					foreach(LANGUAGES as $lang) {
						$user_notification[$user->id]["title_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_request_cancel_order_items_title', $lang), $final_replace_models);
						$user_notification[$user->id]["message_{$lang}"]=Strings::ReplaceModelsFields(Strings::Get('notification_request_cancel_order_items_message', $lang), $final_replace_models) . ":\n";
						$user_notification[$user->id]["items_{$lang}"]='';
					}
				}
				foreach(LANGUAGES as $lang) {
					$user_notification[$user->id]["items_{$lang}"].=($user_notification[$user->id]["items_{$lang}"]=='' ? '' : str_repeat('_', 30) . "\n") .
						Strings::Get('item', $lang) . ': ' . $name_and_specs[$lang] . "\n" .
						Strings::Get('quantity', $lang) . ': ' . $order_product->quantity . "\n" .
						($order_product->comment=='' ? '' : Strings::Get('comment', $lang) . ': ' . $order_product->comment . "\n");
				}
			}
		}

		if($user_notification) foreach($user_notification as $user_id=>$udn) {
			$notification=new Notification;
			$notification->from_session=session_id();
			$notification->from_admin_id=Session::IsAdmin() ? Session::Admin()->id : null;
			$notification->from_user_id=Session::IsUser() ? Session::User()->id : null;
			$notification->from_company_customer_id=Session::IsCustomer() ? Session::Customer()->id : null;
			$notification->to_user_id=$user_id;
			$notification->date_sent=date('Y-m-d H:i:s');
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=$udn["title_{$lang}"];
				$notification->{"message_{$lang}"}=$udn["message_{$lang}"] . $udn["items_{$lang}"];
			}
			$notification->buttons=json_encode([
				Notification::GetGotItButton($udn['lang']),
				[
					'text' => Strings::Get('view_order', $lang),
					'action' => "
						$('#view_notifications_list').css('filter', 'blur(5px)');
						window.location='" . Strings::CreateEncryptedLink(BASE_URL . 'order/edit/?id=' . $this->id) . "';
					"
				]
			]);
			$notification->Save();
		}
		return new Response(true, Strings::Get('cancel_request_sent'));
	}

	function GetPrintProductsToDepartmentHtml($rows_ids, $department_id, $replacements=[]) {
		// Check rows ids
		if(empty($rows_ids)) return Strings::Get('error_missing_order_products_rows_ids');
		if(is_string($rows_ids)) $rows_ids=explode(',', $rows_ids);
		if(empty($rows_ids)) return Strings::Get('error_missing_order_products_rows_ids');
		// Load department
		$department=new Department;
		if(!$department->Load(['id' => $department_id])) return Strings::Get('error_department_not_found');
		if($department->company_id!=$this->company_id) return Strings::Get('error_department_company_mismatch');
		if(empty($department->form_header) && empty($department->form_products)) return Strings::Get('error_department_does_not_have_form');

		$order_products=[];
		// Loop through rows ids
		foreach($rows_ids as $row_id) {
			// Get order product row
			$order_product=new OrderProduct;
			if(!$order_product->Load(['id' => $row_id])) return Strings::Get('error_order_product_not_found');
			$order_product->row_id=$row_id;
			// Add order product
			$order_products[]=$order_product;
		}
		if(empty($order_products)) return Strings::Get('error_no_products_found');
		$basic_models=[];
		// Get order data
		$this->GetData();
		// Add order to basic models
		$basic_models[]=$this;
		// Get company
		$company=new Company;
		$company->Load(['id' => $this->company_id]);
		// Add company to basic models
		$basic_models[]=$company;
		// Add department to basic models
		$basic_models[]=$department;
		// Add tables to basic models
		if(!empty($this->tables)) foreach($this->tables as $table) $basic_models[]=$table;

		$form='';

		// Loop through order products
		foreach($order_products as $order_product) {
			$models=$basic_models;
			// Check if department has form products
			if(!empty($department->form_products)) {
				// Get order product data
				$order_product->GetData();
				// Add product row to models
				$models[]=$order_product;
				// Convert array to object
				if(!empty($order_product->product) && is_array($order_product->product)) { $tmp=new Product; $tmp->CreateFromArray($order_product->product); $order_product->product=$tmp; $tmp=null; }
				// Get product data
				$order_product->product->GetData();
				// Add product to models
				$models[]=$order_product->product;
				// Add unit to models
				if(!empty($order_product->unit)) {
					$unit=new Unit;
					$unit->CreateFromArray($order_product->unit);
					$models[]=$unit;
				}
				// Build form
				$form.=GetPrintableHtml($department->form_products, $models, $replacements);
			}
		}
		// Add form header
		if(!empty($form) && !empty($department->form_header)) $form=GetPrintableHtml($department->form_header, $models, $replacements) . $form;
		// Check if form contains HTML tag
		if(strpos($form, '</html>')===false) $form='<html>' . $form . '</html>';
		// Check if form contains head tag
		if(strpos($form, '</head>')===false) $form=str_replace('<html>', '<html><head><title>' . APP_NAME . ' - Department order</title></head>', $form);
		// Check if form contains body tag
		if(strpos($form, '</body>')===false) $form=str_replace(['</head>', '</html>'], ['</head><body margin="0" padding="0">', '</body></html>'], $form);
		// Check if form contains script tag
		if(strpos($form, 'window.print();')===false) $form=str_replace('</body>', '<script>document.addEventListener("DOMContentLoaded", function(event) { window.print(); });</script></body>', $form);
		return $form;
	}

	public function NotifyWaitersForNewOrder() {
		// Get waiters if needed
		if(!isset($this->waiters) || empty($this->waiters)) $this->GetWaiters();
		if(!isset($this->waiters) || empty($this->waiters)) return false;
		if(!isset($this->tables) || empty($this->tables)) $this->GetTables();
		if(!isset($this->tables) || empty($this->tables)) return false;

		$this_waiter_name=Session::IsWaiter() ? Session::User()->name : '';

		$occasion_hash=md5(time() . "#{$this->id}{$this->waiters_ids_str}");
		// Loop through waiters
		foreach($this->waiters as $waiter) {
			if(Session::UserId()==$waiter->id) continue;

			$models_data=[$this, $waiter];
			if(!empty($this->tables)) foreach($this->tables as $table) $models_data[]=$table;
			$notification=new Notification;
			$notification->from_company_customer_id=$this->customer_id;
			$notification->to_user_id=$waiter->id;
			$notification->date_sent=date('Y-m-d H:i:s');
			$notification->occasion_hash=$occasion_hash;
			foreach(LANGUAGES as $lang) {
				$notification->{"title_{$lang}"}=Strings::ReplaceModelsFields(str_replace('#THIS_WAITER_NAME#', $this_waiter_name, Strings::Get('notification_order_in_your_table_title', $lang)), $models_data);
				$notification->{"message_{$lang}"}=Strings::ReplaceModelsFields(str_replace('#THIS_WAITER_NAME#', $this_waiter_name, Strings::Get('notification_order_in_your_table_message', $lang)), $models_data);
			}
			$notification->Save();
		}
		return true;
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		$sql="
			SELECT ORDERS.*, COMPANY_CUSTOMER.name AS customer_name, IFNULL(USER.name, '') AS waiter_name, IF(ORDERS.date_canceled IS NULL, 0, 1) AS canceled
			FROM ORDERS
			LEFT JOIN COMPANY_CUSTOMER ON ORDERS.customer_id=COMPANY_CUSTOMER.id
			LEFT JOIN USER ON ORDERS.waiter_id=USER.id
		";
		if(Session::IsAdmin()) {
			$sql.=" WHERE ORDERS.company_id" . (empty($company_id) ? '>0' : '=' . DB::Quote($company_id)) . " ";
		} else if(Session::IsUser()) {
			$sql.=" WHERE ORDERS.company_id=" . DB::Quote(Session::User()->company_id) . " ";
		} else { // Only admins and company managers are allowed to get list
			return [];
		}
		$sql.=" ORDER BY ORDERS.completed ASC;";
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Order::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='get_data') {
			if(!Session::IsAdmin()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order
			if(empty($id)) return new Response(false, Strings::Get('error_no_order_id'));
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			return new Response(true, 'OK', $order->GetData());

		} else if($action=='update_products') {
			// Check order
			if(empty($id)) return new Response(false, Strings::Get('error_no_order_id'));
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check products data
			$products_str=GetRequest('data');
			if(empty($products_str)) return new Response(false, Strings::Get('error_no_products_data'));
			$products=@json_decode($products_str);
			if($products=='') return new Response(false, Strings::Get('error_invalid_products_data'));
			// Update products
			return $order->UpdateProducts($products);

		} else if($action=='get_paid') {
			// Check order
			if(empty($id)) return new Response(false, Strings::Get('error_no_order_id'));
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Get data
			$order->GetData();
			return new Response(true, 'OK', $order->products);

		} else if($action=='language') {
			// Check language
			$lang=GetRequest('lang');
			if(!in_array($lang, LANGUAGES)) return new Response(false, Strings::Get('error_invalid_language'));
			// Check order
			$order_json=GetRequest('order');
			if(empty($order_json)) return new Response(false, Strings::Get('error_no_order'));
			$order=@json_decode($order_json);
			if(empty($order)) return new Response(false, Strings::Get('error_invalid_order'), $order_json);

			$collections=[];
			if(!empty($order->products)) $collections[]=$order->products;
			if(!empty($order->product_lines)) $collections[]=$order->product_lines;
			if(!empty($order->products_sent)) $collections[]=$order->products_sent;
			if(!empty($order->products_prepared)) $collections[]=$order->products_prepared;
			if(!empty($order->products_delivered)) $collections[]=$order->products_delivered;
			if(!empty($order->products_paid)) $collections[]=$order->products_paid;

			// Loop through collections
			foreach($collections as $collection) {
				foreach($collection as &$order_product) {
					$product_id=isset($order_product->product_id) ? $order_product->product_id : $order_product->id;
					// Fix product
					$product=new Product;
					if(!$product->Load(['id'=>$product_id])) continue;
					$order_product->name=$product->{"name_{$lang}"};
					$order_product->description=$product->{"description_{$lang}"};
					if(!empty($order_product->product)) {
						$order_product->product->name=$product->{"name_{$lang}"};
						$order_product->product->description=$product->{"description_{$lang}"};
					}
					// Fix unit
					if(!empty($order_product->unit_id)) {
						$unit=new Unit;
						if($unit->Load(['id'=>$order_product->unit_id])) $order_product->unit_name=$unit->{"name_{$lang}"};
					}
					if(!empty($order_product->unit)) {
						$unit=new Unit;
						if($unit->Load(['id'=>$order_product->unit->id])) $order_product->unit->name=$unit->{"name_{$lang}"};
					}

					// Loop through specs
					if($order_product->specs) foreach($order_product->specs as &$order_spec) {
						$spec=new Spec;
						if(!$spec->Load(['id'=>$order_spec->id])) continue;
						$order_spec->name=$spec->{"name_{$lang}"};
					}
				}
			}
			return new Response(true, 'OK', $order);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin()) return new Response(false, Strings::Get('error_insufficient_rights'));
			else if(!empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			else if(!empty($id) && !Session::IsAdmin() && Session::User()->company_id!=GetRequest('company_id')) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Order;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update from request
			$model->CreateFromRequest();
			// Save
			$save=$model->Save();
			if($save['status']) {
				$model->GetAmountsAndSave();
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='user_edit') {
			// Check permissions
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check passed order
			$order_json_str=GetRequest('order');
			if(empty($order_json_str)) return new Response(false, Strings::Get('error_invalid_order'));
			$order_json=@json_decode($order_json_str);
			if(empty($order_json)) return new Response(false, Strings::Get('error_invalid_order'), $order_json_str);
			// Check company
			if(Session::IsUser() && Session::User()->company_id!=$order_json->company_id) return new Response(false, Strings::Get('error_company_mismatch'));
			// Create order for passed json
			$order=new Order;
			$order->CreateFromArray($order_json);
			$save=$order->Save();
			if($save['status']) {
				$update_products=$order->UpdateProducts($order_json->products);
				if($update_products->status) {
					// Merge other orders is needed
					if(!empty($order->merge_orders_ids)) {
						$order->MergeOrders($order->merge_orders_ids);
						$order->GetProducts();
					}
					$order->GetAmountsAndSave(true);
					$order->RecalculateProductsAmount();
					return new Response(true, Strings::Get('data_saved'), $order);
				} else {
					return new Response(false, $update_products->message);
				}
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='user_paid') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check company id
			$company_id=GetRequest('company_id');
			if(empty($company_id)) return new Response(false, Strings::Get('error_missing_company_id'));
			// Load company
			$company=new Company;
			if(!$company->Load(['id' => $company_id])) return new Response(false, Strings::Get('error_company_not_found'));
			// Check order and company
			if($company_id!=$order->company_id) return new Response(false, Strings::Get('error_order_company_mismatch'));
			// Create payment
			return $order->CreatePaymentByUser(GetRequest('user_id'), GetRequest('method'), GetRequest('products_rows_ids'), GetRequest('tip_amount'), GetRequest('tip_user_id'));

		} else if($action=='complete') {
			// Check permissions
			if(!Session::Account()->can_complete_order) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check user company id
			if(Session::IsUser() && Session::User()->company_id!=$order->company_id) return new Response(false, Strings::Get('error_order_company_mismatch'));
			// Complete order
			return $order->Complete();

		} else if($action=='cancel') {
			// Check permissions
			if(!Session::Account()->can_cancel_order) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check user company id
			if(Session::IsUser() && Session::User()->company_id!=$order->company_id) return new Response(false, Strings::Get('error_order_company_mismatch'));
			// Cancel order
			return $order->Cancel();

		} else if($action=='send_to_department') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Load company
			$company=new Company;
			if(!$company->Load(['id' => $order->company_id])) return new Response(false, Strings::Get('error_company_not_found'));
			// Get department
			$department_id=GetRequest('department_id');
			// Load department
			$department=new Department;
			if(!$department->Load(['id' => $department_id])) return new Response(false, Strings::Get('error_department_not_found'));
			if($department->company_id!=$order->company_id) return new Response(false, Strings::Get('error_department_company_mismatch'));
			// Get order product rows
			$rows=GetRequest('products_rows_ids');
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			$rows=explode(',', $rows);
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));

			// Send products to department
			return $order->SendProductsToDepartment($rows, $department_id);

		} else if($action=='mark_products_prepared') {
			// Check permissions
			if(!Session::Account()->can_complete_order) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check user company id
			if(Session::IsUser() && Session::User()->company_id!=$order->company_id) return new Response(false, Strings::Get('error_order_company_mismatch'));
			// Get order product rows
			$rows=GetRequest('products_rows_ids');
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			$rows=explode(',', $rows);
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			// Mark products as prepared
			return $order->MarkProductsPrepared($rows);

		} else if($action=='mark_products_delivered') {
			// Check permissions
			if(!Session::Account()->can_complete_order) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Check user company id
			if(Session::IsUser() && Session::User()->company_id!=$order->company_id) return new Response(false, Strings::Get('error_order_company_mismatch'));
			// Get order product rows
			$rows=GetRequest('products_rows_ids');
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			$rows=explode(',', $rows);
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			// Mark products as delivered
			return $order->MarkProductsDelivered($rows);

		} else if($action=='cancel_products') {
			// Check permissions
			if(!Session::IsLoggedIn()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Load order
			$order=new Order;
			if(!$order->Load(['id' => $id])) return new Response(false, Strings::Get('error_order_not_found'));
			// Load company
			$company=new Company;
			if(!$company->Load(['id' => $order->company_id])) return new Response(false, Strings::Get('error_company_not_found'));
			if(Session::IsUser() && Session::User()->company_id!=$order->company_id) return new Response(false, Strings::Get('error_company_id_mismatch'));
			// Get order product rows
			$rows=GetRequest('products_rows_ids');
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			$rows=explode(',', $rows);
			if(empty($rows)) return new Response(false, Strings::Get('error_missing_order_products_rows_ids'));
			// Cancel products
			return Session::IsCustomer() ? $order->CancelProductsRequest($rows) : $order->CancelProducts($rows);

		} else if($action=='order_pay_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			// Check company id
			$company_id=GetRequest('company_id');
			if(empty($company_id)) return new Response(false, Strings::Get('error_missing_company_id'));
			ob_start();
			include VIEWS_PATH . 'widget_order_pay.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='order_products_to_be_sent_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));

			$auto_sent=GetRequest('auto_sent');
			if($auto_sent){
				$products=GetRequest('products');
				$company_id=GetRequest('company_id');
				if(empty($products)) return new Response(false, Strings::Get('error_no_products_in_order'));
				$res=Order::AutoSendProductsToDepartment($products, $company_id);
				return new Response(true, $res);
			}else{
				ob_start();
				include VIEWS_PATH . 'widget_order_products_to_be_sent.php';
				$data=ob_get_clean();
				return new Response(true, 'OK', $data);
			}
		}else if($action=='order_products_to_be_sent_view_again'){
			$order_id = GetRequest('order_id');
			Order::ResendProductsToDepartment($order_id);
			return new Response(true, 'OK','OK');
		} else if($action=='order_products_to_be_prepared_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			ob_start();
			include VIEWS_PATH . 'widget_order_products_to_be_prepared.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='order_products_to_be_delivered_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			ob_start();
			include VIEWS_PATH . 'widget_order_products_to_be_delivered.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='order_products_to_be_canceled_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			ob_start();
			include VIEWS_PATH . 'widget_order_products_to_be_canceled.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='order_waiters_view') {
			// Check order id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_order_id'));
			ob_start();
			include VIEWS_PATH . 'widget_order_waiters.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='prnsrv_order'){
			
			$company_id = GetRequest('company_id');
			if(empty($company_id)) return new Response(true, 'OK', '1');
				
				$sql_order = 'select O.id, U.name waiter_name, T.name name_table, D.printable_id from ORDERS O 
				inner join ORDER_PRODUCT OP on O.id = OP.order_id
				left join PRODUCT P on OP.product_id = P.id
				left join PRODUCT_CATEGORY PC on P.category_id = PC.id
				left join DEPARTMENT D on PC.department_id = D.id
				left join USER U on O.waiter_id = U.id
				inner join ORDER_TABLE OT on O.id=OT.order_id
				inner join TABLES T on OT.table_id = T.id
				where 
					O.company_id ='.$company_id.' 
					and O.completed = 0
					and O.date_canceled is null
					and (OP.sent = 1 and OP.date_printed is null)
					and D.printable_id is not null
				group by O.id, D.printable_id
				having count(OP.id) > 0
				order by O.id
				limit 1;';
				

			$order_details=DB::Query($sql_order);
			if(!$order_details) return new Response(true, 'OK', '2');
			
			$sql_order_product = 'select OP.id, P.name_gr, OP.comment, JSON_ARRAYAGG(S.name_gr) spec, D.printable_id from ORDERS O 
						inner join ORDER_PRODUCT OP on O.id = OP.order_id 
						inner join PRODUCT P on OP.product_id = P.id 
						left join ORDER_PRODUCT_SPEC as OPS on OP.id = OPS.order_product_row_id	
						left join SPEC S on OPS.product_spec_id = S.id
						inner join PRODUCT_CATEGORY PC on PC.id= P.category_id
						inner join DEPARTMENT D on D.id = PC.department_id
						where 
							O.id = '.$order_details[0]["id"].' 
							and O.completed = 0 
							and O.date_canceled is null 
							and (OP.sent = 1 and OP.date_printed is null) 
						group by OP.id 
						order by O.id;';
		
			$resalt=DB::Query($sql_order_product);
			if(!$resalt) return new Response(true, 'OK', '3');

			$products = array();
			foreach($resalt as $r){
				if($r['spec'] == "[null]"){
					$specs = json_decode("[]",true);
				}else{
					$specs = json_decode($r['spec'],true);
				}
				array_push($products,[ "id" => $r['id'], "name" => $r['name_gr'], "specs" => $specs , "comment" => $r['comment'], "printable_id" => $r['printable_id'] ]);
			}

			$order = ["order_id" => $order_details[0]['id'], "waiter" => $order_details[0]['waiter_name'], "table" => $order_details[0]['name_table'], "products" =>  $products];
			return new Response(true, 'OK', $order);
				
		} else if($action == 'prnsrv_order_product_update'){
			$product_ids = GetRequest('product_ids');
			$product_ids = json_decode($product_ids);
			$sql = '';
			foreach($product_ids as $id){
				$sql .= 'update ORDER_PRODUCT OP set OP.date_printed=NOW() where id='.$id.";\n";
			}
			$resalt=DB::Query($sql);
			return new Response(true, 'OK', 'ok');
			
		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'ORDERS',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}