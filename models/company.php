<?php

class Company extends Model {

	const table='COMPANY';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('COMPANY', 'id', $primary_key_value);
		$this->name='';
	}

	function Save() {
		// Check if its new record
		if(empty($this->id)) {
			if(!Session::IsAdmin()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Check if email is set
			if(!empty($this->email)) {
				// Search database for same email
				$rows=DB::Query("SELECT id FROM " . $this::table . " WHERE email=" . DB::Quote($this->email) . " LIMIT 1;");
				if($rows) return [ 'status' => false, 'message' => Strings::Get('error_email_already_exists') ];
			}
		}
		return parent::Save();
	}

	public function GetDepartments() {
		$this->departments=[];
		$sql="SELECT * FROM DEPARTMENT WHERE company_id=" . DB::Quote($this->id) . " ORDER BY name_" . Strings::GetLanguage() . ";";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$department=new Department;
			$department->CreateFromArray($row);
			$department->name=$row['name_' . Strings::GetLanguage()];
			$this->departments[]=$department;
		}
		return $this->departments;
	}

	public function GetTables() {
		$this->tables=[];
		$sql="
			SELECT TABLES.id, ROOM.name AS room_name, ROOM.sorting AS room_sorting
			FROM TABLES
			INNER JOIN ROOM ON TABLES.room_id=ROOM.id
			WHERE TABLES.company_id=" . DB::Quote($this->id) . "
			ORDER BY TABLES.sorting;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$table=new Table;
			if(!$table->Load(['id' => $row['id']])) continue;
			$table->room_name=$row['room_name'];
			$this->tables[]=$table;
		}
		return $this->tables;
	}

	public function GetMenu() {
		$field_name='name_' . Strings::GetLanguage();
		$this->categories=[];
		$sql="SELECT id FROM PRODUCT_CATEGORY WHERE company_id=" . DB::Quote($this->id) . " AND parent_id=-1 AND visible=1 ORDER BY sorting, name_" . Strings::GetLanguage() . ";";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$category=new ProductCategory;
			if(!$category->Load(['id' => $row['id']])) continue;
			$category->name=$category->$field_name;
			$category->GetProducts();
			$category->GetSubcategories(true, true);
			$this->categories[]=$category;
		}
		return $this->categories;
	}

	public function GetBasicCategories() {
		$this->basic_categories=[];
		$sql="SELECT id, path_" . Strings::GetLanguage() . " as path FROM PRODUCT_CATEGORY WHERE company_id=" . DB::Quote($this->id) . " AND parent_id=-1 AND visible=1 ORDER BY sorting, path;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$category=new ProductCategory;
			if(!$category->Load(['id' => $row['id']])) continue;
			if($category->HasProducts()) $this->basic_categories[]=$category;
		}
		return $this->basic_categories;
	}

	public function GetOrders($date_from='', $date_to='') {
		$this->orders=[];
		// If date from is not set, get last day end
		if(empty($date_from) && $rows=DB::Query("SELECT day_end FROM DAY_END WHERE company_id=" . DB::Quote($this->id) . " AND day_end<" . DB::Quote(date('Y-m-d H:i:s')) . " ORDER BY day_end DESC LIMIT 1;")) {
			$date_from=$rows[0]['day_end'];
		}
		if(empty($date_to)) $date_to=date('Y-m-d 23:59:59');
		// Get orders
		$sql="
			SELECT *
			FROM ORDERS
			WHERE 	company_id=" . DB::Quote($this->id)
				. ($date_from ? " AND date_created>=" . DB::Quote($date_from) : "") . "
				AND date_created<=" . DB::Quote($date_to) . "
			ORDER BY date_created;
		";

		if($rows=DB::Query($sql)) foreach($rows as $row) {
			$order=new Order;
			$order->CreateFromArray($row);
			$order->GetData();
			$this->orders[]=$order;
		}
		return $this->orders;
	}

	public function GetProducts() {
		$this->products=[];
		$sql="
			SELECT id, name_" . Strings::GetLanguage() . " as name
			FROM PRODUCT
			WHERE 	company_id=" . DB::Quote($this->id) . "
				AND visible=1
				AND category_id IN (SELECT id FROM PRODUCT_CATEGORY WHERE company_id=" . DB::Quote($this->id) . " AND visible=1 AND active=1)
			ORDER BY sorting;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$product=new Product;
			if(!$product->Load(['id' => $row['id']])) continue;
			$product->name=$row['name'];
			$product->GetData();
			$this->products[]=$product;
		}
		return $this->products;
	}

	public function GetCustomers() {
		$this->customers=[];
		$sql="SELECT id, name FROM COMPANY_CUSTOMER WHERE company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$customer=new CompanyCustomer;
			$customer->CreateFromArray($row);
			$this->customers[]=$customer;
		}
		return $this->customers;
	}

	public function GetUsers() {
		$this->users=[];
		$this->shop_managers=[];
		$this->baristas=[];
		$this->preparators=[];
		$this->waiters=[];
		$sql="SELECT * FROM USER WHERE company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$this->users[]=$user;
			switch($user->position) {
				case 0: $this->shop_managers[]=$user; break;
				case 1: $this->baristas[]=$user; break;
				case 2: $this->preparators[]=$user; break;
				case 3: $this->waiters[]=$user; break;
			}
		}
		return $this->users;
	}

	public function GetShopManagers() {
		$this->shop_managers=[];
		$sql="SELECT * FROM USER WHERE position=0 AND company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$this->shop_managers[]=$user;
		}
		return $this->shop_managers;
	}

	public function GetBaristas() {
		$this->baristas=[];
		$sql="SELECT * FROM USER WHERE position=1 AND company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$this->baristas[]=$user;
		}
		return $this->baristas;
	}

	public function GetPreparators() {
		$this->preparators=[];
		$sql="SELECT * FROM USER WHERE position=2 AND company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$this->preparators[]=$user;
		}
		return $this->preparators;
	}

	public function GetWaiters() {
		$this->waiters=[];
		$sql="SELECT * FROM USER WHERE position=3 AND company_id=" . DB::Quote($this->id) . " AND active=1 ORDER BY name;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			if(isset($row['pass'])) unset($row['pass']);
			$user=new User;
			$user->CreateFromArray($row);
			$user->GetTables();
			$this->waiters[]=$user;
		}
		return $this->waiters;
	}

	function GetParameters() {
		$this->parameters=new Model('PARAMETERS', 'id');
		$this->parameters->Load(['company_id'=>$this->id]);
		$this->parameters->company_id=$this->id;
		return $this->parameters;
	}

	function SetParameters($parameters) {
		$params=new Model('PARAMETERS', 'id');
		$params->Load(['company_id'=>$this->id]);
		$params->company_id=$this->id;
		if(!empty($parameters)) foreach($parameters as $k=>$v) if(!in_array($k, ['id', 'company_id'])) $params->$k=$v;
		$save=$params->Save();
		if($save['status'] && Session::IsUser() && Session::User()->company_id==$this->id && isset(Session::User()->company)) Session::User()->company->parameters=$params;
		return $save;
	}

	function GetWarnings() {
		$this->warnings=[];
		// Check products without basic unit
		$sql="SELECT id, name_" . Strings::GetLanguage() . " AS title FROM PRODUCT WHERE company_id=" . DB::Quote($this->id) . " AND basic_unit_id IS NULL ORDER BY title;";
//diep($sql);
		if($rows=DB::Query($sql)) foreach($rows as $row) $this->warnings[]=[ 'title' => str_replace('#TITLE#', $row['title'], Strings::Get('warning_product_has_no_basic_unit')), 'link' => BASE_URL . 'product/edit/?id=' . $row['id'] ];
		// Check products with no record on PRODUCT_UNIT table
		$sql="SELECT id, name_" . Strings::GetLanguage() . " AS title FROM PRODUCT WHERE company_id=" . DB::Quote($this->id) . " AND id NOT IN (SELECT product_id FROM PRODUCT_UNIT) ORDER BY title;";
		if($rows=DB::Query($sql)) foreach($rows as $row) $this->warnings[]=[ 'title' => str_replace('#TITLE#', $row['title'], Strings::Get('warning_product_has_no_units')), 'link' => BASE_URL . 'product/edit/?id=' . $row['id'] ];
		// Check products with invalid unit on PRODUCT_UNIT table
		$sql="
			SELECT PRODUCT.id, PRODUCT.name_" . Strings::GetLanguage() . " AS title
			FROM PRODUCT_UNIT
			INNER JOIN PRODUCT ON PRODUCT_UNIT.product_id=PRODUCT.id
			LEFT JOIN UNIT ON PRODUCT_UNIT.unit_id=UNIT.id
			WHERE PRODUCT.company_id=" . DB::Quote($this->id) . " AND UNIT.id IS NULL
			ORDER BY title;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) $this->warnings[]=[ 'title' => str_replace('#TITLE#', $row['title'], Strings::Get('warning_product_has_invalid_unit')), 'link' => BASE_URL . 'product/edit/?id=' . $row['id'] ];
		// Check payment gateway
		if($this->payment_gateway!=0) {
			// Get parameters
			$this->GetParameters();
			// Check payment gateway type
			if($this->payment_gateway==1) { // Every pay
				if(empty($this->parameters->every_pay_debug)) {
					if(empty($this->parameters->every_pay_public_key)) $this->warnings[]=[ 'title' => Strings::Get('every_pay_public_key_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
					if(empty($this->parameters->every_pay_private_key)) $this->warnings[]=[ 'title' => Strings::Get('every_pay_private_key_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
				} else {
					if(empty($this->parameters->every_pay_public_key_debug)) $this->warnings[]=[ 'title' => Strings::Get('every_pay_public_key_debug_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
					if(empty($this->parameters->every_pay_private_key_debug)) $this->warnings[]=[ 'title' => Strings::Get('every_pay_private_key_debug_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
				}
			} else if($this->payment_gateway==2) { // Viva
				if(empty($this->parameters->viva_debug)) {
					if(empty($this->parameters->viva_merchant_id)) $this->warnings[]=[ 'title' => Strings::Get('viva_merchant_id_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
					if(empty($this->parameters->viva_api_key)) $this->warnings[]=[ 'title' => Strings::Get('viva_api_key_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
				} else {
					if(empty($this->parameters->viva_merchant_id_debug)) $this->warnings[]=[ 'title' => Strings::Get('viva_merchant_id_debug_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
					if(empty($this->parameters->viva_api_key_debug)) $this->warnings[]=[ 'title' => Strings::Get('viva_api_debug_is_not_set'), 'link' => BASE_URL . 'report/parameters' ];
				}
			}
		}
		return $this->warnings;
	}

	function HasGateway() {
		if($this->payment_gateway==0) return false;
		if(!isset($this->parameters)) $this->GetParameters();
		if($this->payment_gateway==1) { // Every pay
			if($this->parameters->every_pay_debug) {
				return !empty($this->parameters->every_pay_public_key_debug) && !empty($this->parameters->every_pay_private_key_debug);
			} else {
				return !empty($this->parameters->every_pay_public_key) && !empty($this->parameters->every_pay_private_key);
			}
		} else if($this->payment_gateway==2) { // Viva
			if($this->parameters->viva_debug) {
				return !empty($this->parameters->viva_merchant_id_debug) && !empty($this->parameters->viva_api_key_debug);
			} else {
				return !empty($this->parameters->viva_merchant_id) && !empty($this->parameters->viva_api_key);
			}
		}
		return false;
	}

	function GetWorkingHours() {
		foreach($this as $k=>$v) if(substr($k, -6)=='_start' || substr($k, -4)=='_end') $this->$k=null;
		if(empty($this->id)) return $this;
		$sql="SELECT * FROM WORKING_HOURS WHERE company_id=" . DB::Quote($this->id) . " LIMIT 1;";
		if($rows=DB::Query($sql)) foreach($rows[0] as $k=>$v) $this->$k=$v;
		return $this;
	}

	function GetWorkingHoursToday() {
		$working_hours=[ 'day_start' => date('Y-m-d 00:00:00'), 'day_end' => date('Y-m-d 23:59:59') ];
		if(empty($this->id)) return $working_hours;
		$sql="SELECT " . strtolower(date('l')) . "_start AS day_start, " . strtolower(date('l')) . "_end AS day_end FROM WORKING_HOURS WHERE company_id=" . DB::Quote($this->id) . " LIMIT 1;";
		if($rows=DB::Query($sql)) $working_hours=[ 'day_start' => date('Y-m-d ' . (empty($rows[0]['day_start']) ? '00:00:00' : $rows[0]['day_start'])), 'day_end' => date('Y-m-d ' . (empty($rows[0]['day_end']) ? '23:59:59' : $rows[0]['day_end'])) ];
		return $working_hours;
	}

	function GetTips() {
		$this->tips=[];
		$sql="SELECT * FROM TIP WHERE company_id=" . DB::Quote($this->id) . " ORDER BY type, value;";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			$tip=new Tip;
			$tip->CreateFromArray($row);
			$this->tips[]=$tip;
		}
		return $this->tips;
	}

	function GetUsersWithCustomerNotification() {
		$this->users_with_customer_notification=[];
		$this->users_with_customer_notification_ids=[];
		$sql="
			SELECT * FROM USER
			WHERE 	active=1
				AND position IN (0, 1)
				AND customer_notification=1
				AND company_id=" . DB::Quote($this->id) . "
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


	function GetTodayOrders($all=false) {
		$date_from=DayEnd::GetLast();

		$out=[];
		$sql="
			SELECT id
			FROM ORDERS
			WHERE 	company_id=" . DB::Quote($this->id) . "
				AND date_created>=" . DB::Quote($date_from) . "
				" . ($all ? '' : " AND completed=0 ") . "
				" . (Session::IsWaiter() ? " AND id IN (SELECT order_id FROM ORDER_TABLE where table_id IN (SELECT table_id FROM WAITER_TABLES WHERE user_id=" . DB::Quote(Session::User()->id) . ")) " : '') . "
			ORDER BY date_created DESC;
		";
//diep($sql);
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			$order=new Order;
			$order->Load(['id' => $row['id']]);
			$order->GetData();
			$out[]=$order;
		}
		return $out;
	}

	function GetCommissions($date_from='', $date_to='') {
		$this->total_amount=0;
		$this->total_net_amount=0;
		$this->total_vat_amount=0;
		$this->total_commission=0;
		$this->total_salesman_commission=0;

		// Check dates
		if(empty($date_to)) $date_to=date('Y-m-d 23:59:59');
		if(empty($date_from)) $date_from=date('Y-m-01 00:00:00', strtotime($date_to));

		// Get orders commisions for period
		$sql="
			SELECT 	customer_order,
					SUM(total_amount) AS sum_total_amount,
				   	SUM(products_net_amount) AS sum_products_net_amount,
				   	SUM(products_vat_amount) AS sum_products_vat_amount
			FROM ORDERS
			WHERE 	company_id=" . DB::Quote($this->id) . "
				AND completed=1
				AND date_canceled IS NULL
				AND date_created>='" . $date_from . " 00:00:00'
				AND date_created<='" . $date_to . " 23:59:59'
			GROUP BY customer_order;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			if($row['customer_order'] || $this->extra_charge) {
				$this->total_commission+=$row['sum_products_net_amount'] * ($this->commission  / 100);
				$this->total_salesman_commission+=$row['sum_products_net_amount'] * ($this->commission  / 100) * ($this->salesman_commission / 100);
			}
		}
		return $this->total_commission;
	}

	function GetRooms($full_data=false) {
		$this->rooms=[];
		$sql="
			SELECT DISTINCT ROOM.id, ROOM.sorting
			FROM TABLES
			INNER JOIN ROOM ON TABLES.room_id=ROOM.id
			WHERE TABLES.company_id=" . DB::Quote($this->company_id) . "
			ORDER BY ROOM.sorting;
		";
		$rows=DB::Query($sql);
		if($rows) foreach($rows as $row) {
			$room=new Room;
			$room->Load(['id' => $row['id']]);
			$room->tables=[];
			$sql="
				SELECT *
				FROM TABLES
				WHERE 	room_id=" . DB::Quote($this->id) . "
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

	public static function GetList($sql='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name FROM COMPANY ORDER BY name;";
		} else if(Session::IsUser()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name FROM COMPANY WHERE id=" . DB::Quote(Session::User()->company_id) . " LIMIT 1;";
		} else { // Only admins and company managers are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function CreateDefault() {
		// Get appropriate strings
		$administration_en=Strings::Get('administration', 'en');
		$administration_gr=Strings::Get('administration', 'gr');
		$administration_ru=Strings::Get('administration', 'ru');
		// Create default company
		$sql="
			INSERT INTO COMPANY (
				id, name_en, address_en, city_en, region_en,
				name_gr, address_gr, city_gr, region_gr,
				name_ru, address_ru, city_ru, region_ru,
				tax_number, tax_office, phone, fax, email, contact_email, contact_phone, contact_name
			) VALUES (
				'-1', " . DB::Quote($administration_en) . ", " . DB::Quote($administration_en) . ", " . DB::Quote($administration_en) . ", " . DB::Quote($administration_en) . ",
				" . DB::Quote($administration_gr) . ", " . DB::Quote($administration_gr) . ", " . DB::Quote($administration_gr) . ", " . DB::Quote($administration_gr) . ",
				" . DB::Quote($administration_ru) . ", " . DB::Quote($administration_ru) . ", " . DB::Quote($administration_ru) . ", " . DB::Quote($administration_ru) . ",
				'1', " . DB::Quote($administration_en) . ", '1', '1', " . DB::Quote('info@' . DOMAIN) . ", " . DB::Quote('info@' . DOMAIN) . ", '1', " . DB::Quote($administration_en) . "
			);
		";
		return DB::Insert($sql);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Company::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='product_list_view') {
			// Check id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_company_id'));
			ob_start();
			include VIEWS_PATH . 'widget_company_product_list.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='product_view') {
			// Check id
			if(empty($id)) return new Response(false, Strings::Get('error_missing_product_id'));
			// Check company id
			$company_id=GetRequest('company_id');
			if(empty($company_id)) return new Response(false, Strings::Get('error_missing_company_id'));
			ob_start();
			include VIEWS_PATH . 'widget_company_product.php';
			$data=ob_get_clean();
			return new Response(true, 'OK', $data);

		} else if($action=='parameters') {
			// Check permissions
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check company id
			$company_id=GetRequest('company_id');
			if(empty($company_id)) return new Response(false, Strings::Get('error_missing_company_id'));
			// Check session
			if(Session::IsUser() && Session::User()->company_id!=$company_id) return new Response(false, Strings::Get('error_user_company_mismatch'));
			$company=new Company;
			if(!$company->Load(['id'=>$company_id])) return new Response(false, Strings::Get('error_company_not_found'));
			// Get stored parameters
			$parameters=$company->GetParameters();
			// Update from request
			$parameters->CreateFromRequest();
			$save=$company->SetParameters($parameters);
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin()) return new Response(false, Strings::Get('error_insufficient_rights'));
			else if(!empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			else if(!empty($id) && !Session::IsAdmin() && Session::User()->company_id!=$id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Company;
			// Get from database
			$model->Load(['id'=>$id]);
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
				'table' => 'COMPANY',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}