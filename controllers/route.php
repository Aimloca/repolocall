<?php

class Route {

	public static function Guide($url='') {

		if(empty($url)) {
			$url='http';
			if(FORCE_HTTPS || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on')) $url.= 's';
			$url.=':';
			$_SERVER['PROTOCOL']=$url;
			$url.='//' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		} else if(!StartsWith(strtolower($url), 'http')) {
			$_SERVER['PROTOCOL']=(FORCE_HTTPS || !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https:' : 'http:';
			$url=$_SERVER['PROTOCOL'] . '//' . $url;
		} else {
			$_SERVER['PROTOCOL']=(FORCE_HTTPS ? 'https' : explode(':', $url)[0]) . ':';
		}

		$url_without_base=str_replace(['http:' . BASE_URL . 'index.php?', 'https:' . BASE_URL . 'index.php?'], '', $url);
		$url_decoded=Strings::DecryptUrlSegment($url_without_base);

		// Check decoded
		if(!empty($url_decoded)) {
			$segs_q=explode('?', $url_decoded);
			$url_decoded_params='';
			for($i=1;$i<count($segs_q);$i++)
				$url_decoded_params.=(empty($url_decoded_params) ? '' : '?') . $segs_q[$i];
			parse_str($url_decoded_params, $request);
			foreach($request as $p=>$v)
				$_REQUEST[$p]=$v;
			return Route::Guide($url_decoded);
		}

		if(Contains($url, '?')) {
			$segs_q=explode('?', $url);
			$url_decoded_params='';
			for($i=1;$i<count($segs_q);$i++)
				$url_decoded_params.=(empty($url_decoded_params) ? '' : '?') . $segs_q[$i];
			parse_str($url_decoded_params, $request);
			$redirect='';

			foreach($request as $p=>$v) $_REQUEST[$p]=$v;
			if(!empty($redirect)) return Route::Guide($redirect);
		}

		// Set language
		if(isset($_REQUEST['lang']) && in_array($_REQUEST['lang'], LANGUAGES)) {
			Strings::SetLanguage($_REQUEST['lang']);
			if(Session::Account()) Session::Account()->SetLanguage($_REQUEST['lang']);
		}

		$route=isset($_REQUEST['api']) && $_REQUEST['api']==1 ? 'api' : (isset($_REQUEST['controller']) ? $_REQUEST['controller'] : '');
		$action=isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		if(empty($route) || empty($action)) {
			$segs=explode('/', $url);
			foreach($segs as $index=>$seg) {
				$seg_lower=strtolower($seg);
				if(empty($seg_lower)) {
					continue;
				} else if(empty($route)) {
					if(in_array($seg_lower, CONTROLLERS))
						$route=$seg_lower;
					continue;
				} else if(empty($action)) {
					$action=$seg_lower;
				}
			}
		}

		// Check api route
		if($route=='api') {
			return Api::Parse();
		} else {
			// Error page
			if($route=='error' && $action=='show')
				include VIEWS_PATH . 'page_error.php';

			// Scan table
			else if($route=='table' && $action=='scan')
				include VIEWS_PATH . 'customer/page_scan_table.php';
			// Table scanned
			else if($route=='table' && $action=='scanned') {
				$result=Table::CheckScanned();
				if($result->status) {
					Redirect();
				} else {
					$error=$result->message;
					include VIEWS_PATH . 'page_error.php';
				}

			// Check if user is logged in
			} else if($action!='login' && !Session::IsLoggedIn()) include VIEWS_PATH . 'page_login.php';
			else if($action=='logout') { Session::Destroy(); Redirect(); }

			// Home page
			else if($action=='home') include VIEWS_PATH . 'page_home.php';

			// Admin pages
			else if($route=='admin') {
				if($action=='list') include VIEWS_PATH . 'page_admins.php';
				else if($action=='edit') include VIEWS_PATH . 'page_admin.php';
				else if($action=='strings') include VIEWS_PATH . 'page_strings.php';
				else if($action=='create_autogen') {
					$autogen=Admin::CreateAutogen();
					die($autogen->status ? '<pre>' . json_encode($autogen->data, JSON_PRETTY_PRINT) . '</pre>' : $autogen->message);
				}

			// List pages
			} else if($action=='list') {
				if($route=='buy_document') include VIEWS_PATH . 'page_buy_documents.php';
				else if($route=='buy_series') include VIEWS_PATH . 'page_buy_series.php';
				else if($route=='company') include VIEWS_PATH . 'page_companies.php';
				else if($route=='company_customer') include VIEWS_PATH . 'page_company_customers.php';
				else if($route=='customer') include VIEWS_PATH . 'page_customers.php';
				else if($route=='day_end') include VIEWS_PATH . 'page_day_ends.php';
				else if($route=='department') include VIEWS_PATH . 'page_departments.php';
				else if($route=='notification') include VIEWS_PATH . 'page_notifications.php';
				else if($route=='order') include VIEWS_PATH . 'page_orders.php';
				else if($route=='payment') include VIEWS_PATH . 'page_payments.php';
				else if($route=='product') include VIEWS_PATH . 'page_products.php';
				else if($route=='product_category') include VIEWS_PATH . 'page_product_categories.php';
				else if($route=='room') include VIEWS_PATH . 'page_rooms.php';
				else if($route=='sale_document') include VIEWS_PATH . 'page_sale_documents.php';
				else if($route=='sale_series') include VIEWS_PATH . 'page_sale_series.php';
				else if($route=='spec') include VIEWS_PATH . 'page_specs.php';
				else if($route=='table') include VIEWS_PATH . 'page_tables.php';
				else if($route=='tip') include VIEWS_PATH . 'page_tips.php';
				else if($route=='unit') include VIEWS_PATH . 'page_units.php';
				else if($route=='user') include VIEWS_PATH . 'page_users.php';
				else if($route=='vat_category') include VIEWS_PATH . 'page_vat_categories.php';

			// Model pages EDIT
			} else if($action=='edit') {
				if($route=='buy_document') include VIEWS_PATH . 'page_buy_document.php';
				else if($route=='buy_series') include VIEWS_PATH . 'page_buy_serie.php';
				else if($route=='company') include VIEWS_PATH . 'page_company.php';
				else if($route=='company_customer') include VIEWS_PATH . 'page_company_customer.php';
				else if($route=='customer') include VIEWS_PATH . 'page_customer.php';
				else if($route=='day_end') include VIEWS_PATH . 'page_day_end.php';
				else if($route=='department') include VIEWS_PATH . 'page_department.php';
				else if($route=='device') include VIEWS_PATH . 'page_device.php';
				else if($route=='notification') include VIEWS_PATH . 'page_notification.php';
				else if($route=='order') include VIEWS_PATH . 'page_order.php';
				else if($route=='payment') include VIEWS_PATH . 'page_payment.php';
				else if($route=='product') include VIEWS_PATH . 'page_product.php';
				else if($route=='product_category') include VIEWS_PATH . 'page_product_category.php';
				else if($route=='room') include VIEWS_PATH . 'page_room.php';
				else if($route=='sale_document') include VIEWS_PATH . 'page_sale_document.php';
				else if($route=='sale_series') include VIEWS_PATH . 'page_sale_serie.php';
				else if($route=='spec') include VIEWS_PATH . 'page_spec.php';
				else if($route=='string') include VIEWS_PATH . 'page_strings.php';
				else if($route=='string_missing') include VIEWS_PATH . 'page_strings_missing.php';
				else if($route=='table') include VIEWS_PATH . 'page_table.php';
				else if($route=='tip') include VIEWS_PATH . 'page_tip.php';
				else if($route=='unit') include VIEWS_PATH . 'page_unit.php';
				else if($route=='user') include VIEWS_PATH . 'page_user.php';
				else if($route=='working_hours') include VIEWS_PATH . 'page_working_hours.php';
				else if($route=='vat_category') include VIEWS_PATH . 'page_vat_category.php';

			// Sale document
			} else if($route=='sale_document') {
				if($action=='create_from_order') include VIEWS_PATH . 'page_sale_document.php';
				else if($action=='print') include VIEWS_PATH . 'page_print_sale_document.php';

			// Reports pages
			} else if($route=='report') {
				if($action=='errors_log') include VIEWS_PATH . 'page_log.php';
				else if($action=='parameters') include VIEWS_PATH . 'page_parameters.php';
				else if($action=='commissions') include VIEWS_PATH . 'page_report_commission.php';
				else if($action=='orders') include VIEWS_PATH . 'page_report_orders.php';

			// Payment
			} else if($route=='payment' && $action=='result') {
				if(empty($_REQUEST['payment_uuid'])) $_REQUEST['payment_uuid']=GetRequest('s');
				if(empty($_REQUEST['gateway_transaction_id'])) $_REQUEST['gateway_transaction_id']=GetRequest('t');
				if(empty($_REQUEST['gateway_language'])) $_REQUEST['gateway_language']=GetRequest('l');
				include VIEWS_PATH . 'page_payment_result.php';

			// Admin pages
			} else if(Session::IsAdmin()) {
				if($route=='order' && $action=='print_to_department') include VIEWS_PATH . 'page_print_to_department.php';

			// User pages
			} else if(Session::IsUser()) {
				if($route=='user' && $action=='cash') include VIEWS_PATH . 'page_cash.php';
				else if($route=='order' && $action=='print_to_department') include VIEWS_PATH . 'page_print_to_department.php';

			// Customer pages
			} else if(Session::IsCustomer()) {
				if($route=='menu' && $action=='view') include VIEWS_PATH . 'customer/page_menu.php';
				else if($route=='order' && $action=='view') include VIEWS_PATH . 'customer/page_order.php';
				else if($route=='order' && $action=='pay') include VIEWS_PATH . 'customer/page_payment.php';
				else if($route=='bill' && $action=='view') include VIEWS_PATH . 'customer/page_bill.php';
				else if($route=='product' && $action=='view') include VIEWS_PATH . 'customer/page_product.php';
				else if($route=='') {
					if(Session::Get('selected_table'))
						include VIEWS_PATH . 'customer/page_menu.php';
					else
						include VIEWS_PATH . 'customer/page_scan_table.php';
				}
			}

			/*
			// Generic models
			if(in_array($route, APP_MODELS)) { // MODEL
				$model=new $route(GetRequest('id'));
				// Check action
				if($action=='list') {
					die(GetListPage([ 'model' => $model ]));
				} else if($action=='edit') {
					die(GetModelPage([ 'model' => $model ]));
				}
			}
			*/

if(!empty($_REQUEST) && !empty($_REQUEST['lang'])) { include(VIEWS_PATH . 'page_home.php'); return; }
if(!empty($_REQUEST)) diep(['route' => $route, 'action' => $action, 'url' => $url, 'request' => $_REQUEST]);
			include(VIEWS_PATH . 'page_home.php');
		}

	}
}