<?php

class Admin extends Model {

	const table='ADMIN';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('ADMIN', DB_TABLES['ADMIN']['primary_key'], $primary_key_value);
	}

	function Save() {
		if($this->pass!='' && strlen($this->pass)<8) return [ 'status' => false, 'message' => Strings::Get('error_password_atlease_8_chars')];
		return parent::Save();
	}

	function SetLanguage($lang) {
		if(!in_array($lang, LANGUAGES)) return false;
		$this->language=array_search($lang, LANGUAGES);
		$save=DB::Update("UPDATE {$this->table} SET language={$this->language} WHERE id={$this->id};");
		return [ 'status' => $save ? true : false, 'message' => $save ? 'OK' : 'Error' ];
	}

	public static function GetList($sql='', $class='') {
		$sql="SELECT * FROM ADMIN ORDER BY name;";
		return parent::GetList($sql, $class);
	}

	public static function UpdateStrings($strings) {
		if(empty($strings)) return new Response(false, Strings::Get('error_empty_strings_data'));
		$json=@json_decode($strings);
		if(empty($json)) return new Response(false, Strings::Get('error_invalid_strings_data'), $strings);
		$sql='';
		foreach($json as $id=>$string) {
			if($id=='') continue;
			$upd_fields=''; foreach(LANGUAGES as $lang) if(isset($string->$lang)) $upd_fields.=($upd_fields=='' ? '' : ', ') . $lang . '=' . DB::Quote($string->$lang);
			$sql.="UPDATE STRINGS SET {$upd_fields}, position=NULL WHERE id=" . DB::Quote($id) . ";\n";
		}
		if(empty($sql)) return new Response(false, Strings::Get('error_no_strings_to_update'));
		DB::Query($sql);
		return new Response(true, 'OK');
	}

	public static function DeleteAllOrders(){
		$sql='
			DELETE FROM ORDER_TABLE;
			DELETE FROM ORDER_PRODUCT_SPEC;
			DELETE FROM ORDER_PRODUCT;
			DELETE FROM ORDERS;
		';
		DB::Query($sql);
		return new Response(true, 'OK');
	}

	public static function DeleteEmptyOrders(){
		$sql='
			DELETE FROM ORDER_TABLE WHERE order_id IN (SELECT id FROM ORDERS WHERE id NOT IN (SELECT order_id FROM ORDER_PRODUCT));
			DELETE FROM ORDERS WHERE id NOT IN (SELECT order_id FROM ORDER_PRODUCT);
		';
		DB::Query($sql);
		return new Response(true, 'OK');
	}

	public static function FixPaymentsProductsRowsIds(){
		if($rows=DB::Query("SELECT id, products FROM PAYMENT;")) foreach($rows as $row) {
			$products=json_decode($row['products']);
			$order_products_rows_ids='';
			if($products) foreach($products as $product) $order_products_rows_ids.=($order_products_rows_ids=='' ? '#' : '') . $product->row_id . '#';
			DB::Update("UPDATE PAYMENT SET order_products_rows_ids=" . DB::Quote($order_products_rows_ids) . " WHERE id=" . DB::Quote($row['id']));
		}
		return new Response(true, 'OK');
	}

	public static function RecalculatePaymentsProductsAmount(){
		if($rows=DB::Query('SELECT id FROM PAYMENT;')) {
			foreach($rows as $row) {
				$payment=new Payment;
				$payment->Load(['id'=>$row['id']]);
				$payment->RecalculateProductsAmount();
			}
			return new Response(true, 'OK', count($rows));
		} else {
			return new Response(false, 'No payments found');
		}
	}

	public static function RecalculatePaymentsOrderProductsRowsIds(){
		if($rows=DB::Query('SELECT id FROM PAYMENT;')) {
			foreach($rows as $row) {
				$payment=new Payment;
				$payment->Load(['id'=>$row['id']]);
				$payment->RecalculateOrderProductsRowsIds();
			}
			return new Response(true, 'OK', count($rows));
		} else {
			return new Response(false, 'No payments found');
		}
	}

	public static function RecalculateOrdersProductsAmount(){
		if($rows=DB::Query('SELECT id FROM ORDERS;')) {
			foreach($rows as $row) {
				$order=new Order;
				$order->Load(['id'=>$row['id']]);
				$order->RecalculateProductsAmount();
			}
			return new Response(true, 'OK', count($rows));
		} else {
			return new Response(false, 'No orders found');
		}
	}

	public static function CreateAutogen() {
		// Check admin
		if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));

		$errors=[]; $files=[];
		if(!AutogenDBTables()) $errors[]='AutogenDBTables';
		if(!AutogenIncludes()) $errors[]='AutogenIncludes';
		if(!AutogenJsConstants(GetRequest('obfuscate')=='1')) $errors[]='AutogenJsConstants';
		if(!AutogenJsTables()) $errors[]='AutogenJsTables';
		if(!AutogenJsStrings()) $errors[]='AutogenJsStrings';
		if(!AutogenJsModels(GetRequest('obfuscate')=='1')) $errors[]='AutogenJsModels';
		foreach(scandir(AUTOGEN_PATH) as $file) if(substr($file, -4)=='.php') $files[$file]=round(filesize(AUTOGEN_PATH . $file)/1024, 2) . 'Kb';
		foreach(scandir(JS_PATH) as $file) if(substr($file, 0, 8)=='autogen_' && substr($file, -3)=='.js') $files[$file]=round(filesize(JS_PATH . $file)/1024, 2) . 'Kb';
		return new Response(empty($errors), Strings::Get('autogen_files_created'), [ 'files' => $files, 'errors' => $errors ]);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Admin::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='strings_update') {
			return Admin::UpdateStrings(GetRequest('data'));

		} else if($action=='delete_all_orders') {
			return Admin::DeleteAllOrders();

		} else if($action=='delete_empty_orders') {
			return Admin::DeleteEmptyOrders();

		} else if($action=='recalculate_payments_products_amount') {
			return Admin::RecalculatePaymentsProductsAmount();

		} else if($action=='recalculate_payments_order_products_rows_ids') {
			return Admin::RecalculatePaymentsOrderProductsRowsIds();

		} else if($action=='recalculate_orders_products_amount') {
			return Admin::RecalculateOrdersProductsAmount();

		} else if($action=='fix_payments_order_products_rows_ids') {
			return Admin::FixPaymentsProductsRowsIds();

		} else if($action=='create_autogen') {
			return Admin::CreateAutogen();

		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'ADMIN',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin(),
				'allow_delete' => Session::IsAdmin(),
				'allow_import' => Session::IsAdmin(),
		]);
	}
}