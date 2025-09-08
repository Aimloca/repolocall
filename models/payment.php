<?php
class Payment extends Model {
    /*
    Payments are made using EveryPay API
    https://docs.everypay.gr/accept-payments/payform-integration/

    Test cards
    4556390755719395	Visa
    5217925525906273	MasterCard
    4556940988073158	Visa
    4908440000000003	Visa
    */

    const table='PAYMENT';
	public static $db_fields;
    public $merchant_id, $merchant_name;
    public $public_key, $private_key, $api_key;
    public $debug=true;
    public $log=EVERY_PAY_LOG;

	function __construct($primary_key_value='') {
		parent::__construct('PAYMENT', 'id', $primary_key_value);
        $this->logo='https://cdn-icons-png.flaticon.com/512/10830/10830623.png';
        $this->extra_script='';
	}

    function Load($filters='') {
        $load=parent::Load($filters);
        if($load) $this->debug=1;
		return $load ? $this : false;
	}

    function Save() {
        $check_criticals=$this->CheckCriticals();
        if(!$check_criticals['status']) return $check_criticals;

        return parent::Save();
    }

    function Log($data) {
        if($data=='' || !$this->log) return;
        file_put_contents(LOG_PATH . 'payments/' . (empty($this->order_id) ? 'NO_ORDER_ID' : $this->order_id) . '.log', $data . (substr($data, -1)=="\n" ? "" : "\n"), FILE_APPEND);
    }

    function GetData() {

    }

    function GetOrder() {
        if(!isset($this->order) && !empty($this->order_id)) {
            $this->order=new Order;
            if(!$this->order->Load(['id' => $this->order_id])) {
                $this->order=null;
            } else {
                $this->order->GetData();
            }
        }
        return $this->order;
    }

    function SetCompany($company_id) {
        $tmp=$this->company_id;
        $this->company_id=$company_id;
        $check=$this->CheckGateway();
        if(!$check->status) $this->company_id=$tmp;
        return $check;
    }

    function CheckGateway() {
        if(empty($this->company_id)) return new Response(false, Strings::Get('no_company_id_for_payment'));
        $company=new Company;
        if(!$company->Load(['id'=>$this->company_id])) return new Response(false, Strings::Get('payment_company_not_found'));

        // Check payment type and gateway
        if($this->type==0) {
            if($this->gateway!=0) return new Response(false, Strings::Get('payment_is_cash_but_gateway_is_set'));
        } else {
            // Check company payment gateway
            if($company->payment_gateway==0) return new Response(false, Strings::Get('company_does_not_have_payment_gateway'));
            if($this->gateway!=$company->payment_gateway) return new Response(false, Strings::Get('company_payment_gateway_mismatch'));
            $company->GetParameters();
            if($this instanceof EveryPayPayment) {
                $this->debug=!isset($company->parameters->every_pay_debug) || !empty($company->parameters->every_pay_debug);
                $this->public_key=$this->debug ? (isset($company->parameters->every_pay_public_key_debug) ? $company->parameters->every_pay_public_key_debug : '') : (isset($company->parameters->every_pay_public_key) ? $company->parameters->every_pay_public_key : '');
                if(empty($this->public_key)) return new Response(false, Strings::Get('company_every_pay_public_key_is_not_set'));
                $this->private_key=$this->debug ? (isset($company->parameters->every_pay_private_key_debug) ? $company->parameters->every_pay_private_key_debug : '') : (isset($company->parameters->every_pay_private_key) ? $company->parameters->every_pay_private_key : '');
                if(empty($this->private_key)) return new Response(false, Strings::Get('company_every_pay_private_key_is_not_set'));
                $this->extra_script='<script src="' . ($this->debug ? EVERY_PAY_JS_SCRIPT_DEBUG : EVERY_PAY_JS_SCRIPT). '"></script>';
            } else if($this instanceof VivaPayment) {
                $this->debug=!isset($company->parameters->viva_debug) || !empty($company->parameters->viva_debug);
                $this->api_key=$this->debug ? (isset($company->parameters->viva_api_key_debug) ? $company->parameters->viva_api_key_debug : '') : (isset($company->parameters->viva_api_key) ? $company->parameters->viva_api_key : '');
                if(empty($this->api_key)) return new Response(false, Strings::Get('company_viva_api_key_is_not_set'));
                $this->merchant_id=$this->debug ? (isset($company->parameters->viva_merchant_id_debug) ? $company->parameters->viva_merchant_id_debug : '') : (isset($company->parameters->viva_merchant_id) ? $company->parameters->viva_merchant_id : '');
                if(empty($this->merchant_id)) return new Response(false, Strings::Get('company_viva_merchant_id_is_not_set'));
                $this->logo='https://demo.vivapayments.com/Content/img/logo_wallet-dark.svg';
                $this->extra_script='';
            }
        }
        return new Response(true, 'OK');
    }

    function CheckCriticals() {
        // Check company
		if(empty($this->company_id)) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo company id.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_no_company') ];
        }
        $company=new Company; if(!$company->Load(['id' => $this->company_id])) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nInvalid company.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_invalid_company') ];
        }

        // Check order
		if(empty($this->order_id)) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo order id.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_no_order') ];
        }
        $order=new Order; if(!$order->Load(['id' => $this->order_id])) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nInvalid order.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_invalid_order') ];
        }

        // Check customer
		if(empty($this->customer_id)) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo customer.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_no_customer') ];
        }
        $customer=new CompanyCustomer; if(!$customer->Load(['id' => $this->customer_id])) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nInvalid customer.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_invalid_customer') ];
        }

        // Check amount
        if(empty($this->amount)) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo amount.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_no_amount') ];
        }

        // Check products
        if(empty($this->products)) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo products.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_no_products') ];
        }
        if(is_string($this->products) && empty(@json_decode($this->products))) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nInvalid products.\n" . print_r($this, true) . "\n\n");
            return [ 'status' => false, 'message' => Strings::Get('error_invalid_products') ];
        }

        // Set values
        $this->company=$company;
        $this->order=$order;
        $this->customer=$customer;
        $this->products_arr=is_string($this->products) ? json_decode($this->products) : $this->products;

        return [ 'status' => true, 'message' => 'OK' ];
    }

    function FixAmountForPayment() {
        return $this->amount * 100;
    }

	function GetProductsRowsIds() {
		$this->products_rows_ids='';
		$tmp_products=is_string($this->products) ? json_decode($this->products) : $this->products;
        if($tmp_products) foreach($tmp_products as $product_index=>$product) $this->products_rows_ids=($this->products_rows_ids=='' ? '#' : '') . $product->row_id . '#';
		return $this->products_rows_ids;
	}

    function GetProductLinesQuantities() {
		$this->rows_ids_qnt=[];
		$this->rows_ids_qnt_str='';
        $tmp_products=is_string($this->products) ? json_decode($this->products) : $this->products;
        foreach($tmp_products as $product_index=>$product) {
            $this->rows_ids_qnt[]=[ 'row_id'=>$product->row_id, 'quantity' => $product->quantity, 'amount' => $product->amount ];
            $this->rows_ids_qnt_str.=($this->rows_ids_qnt_str=='' ? '' : ',') . $product->row_id . ':' . $product->quantity;
        }
        $this->rows_ids_qnt=json_encode($this->rows_ids_qnt);
        return $this->rows_ids_qnt;
    }

    function RecalculateProductsAmount() {
        $this->products_amount=0;
        if(empty($this->products)) return $this->products_amount;
        $json_products=is_string($this->products) ? json_decode($this->products) : $this->products;
        if(empty($json_products)) return $this->products_amount;
        foreach($json_products as $product_index=>$product) $this->products_amount+=$product->amount;
        if(!empty($this->id)) DB::Update("UPDATE PAYMENT SET products_amount=" . DB::Quote($this->products_amount) . " WHERE id=" . DB::Quote($this->id) . ";");
        return $this->products_amount;
    }

    function RecalculateOrderProductsRowsIds() {
        $this->order_products_rows_ids='';
        if(empty($this->products)) return $this->order_products_rows_ids;
        $json_products=is_string($this->products) ? json_decode($this->products) : $this->products;
        if(empty($json_products)) return $this->order_products_rows_ids;
        foreach($json_products as $product_index=>$product) $this->order_products_rows_ids.=($this->order_products_rows_ids=='' ? '#' : '') . $product->primary_key_value . '#';
        if(!empty($this->id)) DB::Update("UPDATE PAYMENT SET order_products_rows_ids=" . DB::Quote($this->order_products_rows_ids) . " WHERE id=" . DB::Quote($this->id) . ";");
        return $this->order_products_rows_ids;
    }

    function GetForm($success_redirect='/user/home') {
        return new Response(false, Strings::Get('error_getting_form_for_payment_submodel_is_not_set'));
    }

    function Charge() {
        return new Response(false, Strings::Get('error_charging_payment_submodel_is_not_set'));
    }

    public static function _Log($filename, $data) {
        if($data=='') return;
        file_put_contents(LOG_PATH . 'payments/' . (empty($filename) ? 'NO_ORDER_ID' : $filename) . '.log', $data . (substr($data, -1)=="\n" ? "" : "\n"), FILE_APPEND);
    }

    public static function Create($order) {
        // Delete previous uncompleted payments
        if(Session::IsCustomer()) DB::Query('DELETE FROM PAYMENT WHERE session_id=' . DB::Quote(session_id()) . ' AND completed=0 AND ((uuid IS NULL AND token IS NULL) OR charge_response IS NULL);');

		// Check order
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_string($order)) $order=@json_decode($order);
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(is_array($order)) $order=json_decode(json_encode($order));
		if(empty($order)) return new Response(false, Strings::Get('error_no_order'));
		if(empty($order->id)) return new Response(false, Strings::Get('error_no_order_id'));
		if(substr($order->id, 0, 1)=='-') return new Response(false, Strings::Get('error_invalid_order_id'));
		if(!is_numeric($order->id)) return new Response(false, Strings::Get('error_invalid_order_id'));
		if(empty($order->tables_ids)) return new Response(false, Strings::Get('error_no_order_tables'));
		if(empty($order->products)) return new Response(false, Strings::Get('error_no_products'));
		if(empty($order->product_lines)) return new Response(false, Strings::Get('error_no_product_lines'));
        //$tmp=new Order; $tmp->CopyFrom($order); $order=$tmp; $tmp=false;
        //$order->GetProductLines();
//diep($order->product_lines);
		// Get server order
		$server_order=new Order;
		if(!$server_order->Load(['id' => $order->id])) {
			Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo order found in passed order data.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
			return new Response(false, Strings::Get('error_no_order_with_this_id'));
		}
		$server_order->customer_id=Session::CustomerId();
		$server_order->tables=$server_order->GetTables();
		$server_order->GetProducts();
		$server_order->GetProductLines();
		if(empty($order->company_id) && empty($server_order->company_id)) {
			Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo company is set in passed order data.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
			return new Response(false, Strings::Get('error_no_company_is_set'));
		}
        // Check company
        $company=new Company;
        if(!$company->Load(['id' => $order->company_id])) {
            Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nCompany id {$order->company_id} not found.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
			return new Response(false, Strings::Get('error_company_not_found'));
		}

        // Check gateway
        if($company->payment_gateway==0) {
            Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nCompany id {$order->company_id} not found.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
			return new Response(false, Strings::Get('error_company_does_not_have_payment_gateway'));
        } else {
            $gateway_error='';
            $company->GetParameters();
            if($company->payment_gateway==1) { // Every pay
				if(empty($company->parameters->every_pay_debug)) {
					if(empty($company->parameters->every_pay_public_key)) $gateway_error=Strings::Get('every_pay_public_key_is_not_set');
					else if(empty($company->parameters->every_pay_private_key)) $gateway_error=Strings::Get('every_pay_private_key_is_not_set');
				} else {
					if(empty($company->parameters->every_pay_public_key_debug)) $gateway_error=Strings::Get('every_pay_public_key_debug_is_not_set');
					else if(empty($company->parameters->every_pay_private_key_debug)) $gateway_error=Strings::Get('every_pay_private_key_debug_is_not_set');
				}
			} else if($company->payment_gateway==2) { // Viva
				if(empty($company->parameters->viva_debug)) {
					if(empty($company->parameters->viva_merchant_id)) $gateway_error=Strings::Get('viva_merchant_id_is_not_set');
					else if(empty($company->parameters->viva_api_key)) $gateway_error=Strings::Get('viva_api_key_is_not_set');
				} else {
					if(empty($company->parameters->viva_merchant_id_debug)) $gateway_error=Strings::Get('viva_merchant_id_debug_is_not_set');
					else if(empty($company->parameters->viva_api_key_debug)) $gateway_error=Strings::Get('viva_api_debug_is_not_set');
				}
			}
            if($gateway_error) {
                Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\n{$gateway_error}.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
			    return new Response(false, $gateway_error);
            }
        }

        // Manage product lines
        $json_product_lines=is_string($order->product_lines) ? @json_encode($order->product_lines) : $order->product_lines;
        if(empty($json_product_lines)) {
            Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nNo product lines.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
            return new Response(false, Strings::Get('error_no_product_lines'));
        }
        foreach($json_product_lines as $line_index=>$product_line) {
            if(!in_array($product_line->row_id, $server_order->unique_products_rows_ids)) {
                Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nProduct line row id not found in order.\nOrder:\n" . json_encode($order, JSON_PRETTY_PRINT) . "\n\n");
                return new Response(false, Strings::Get('error_invalid_product_lines') . " {$product_line->row_id}<br /><pre>" . print_r($server_order->unique_products_rows_ids, true) . "</pre>");
            }
        }


		// Create payment
		$payment=new Payment;
		$payment->company_id=$order->company_id;
		$payment->order_id=$server_order->id;
		$payment->customer_id=$order->customer_id;
		$payment->session_id=session_id();
		$payment->type=$company->payment_gateway==0 ? 0 : 1;
		$payment->gateway=$company->payment_gateway;
        $payment->products_amount=0; foreach($order->product_lines as $product_index=>$product) if($product->selected) $payment->products_amount+=$product->amount;
		$payment->tip_amount=$order->tip_amount;
		$payment->amount=$payment->products_amount + $order->tip_amount;
		$payment->tip_user_id=$order->tip_user_id;
		$payment->products=json_encode($order->product_lines);
		$payment->products_arr=$order->products;
		$payment->GetProductsRowsIds();
		$payment->GetProductLinesQuantities();
		$payment_save=$payment->Save();
		if($payment_save['status']) {
			Payment::_Log($order->id, date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPAYMENT CREATED - id={$payment->id}\n" . print_r($payment, true) . "\n");
			return new Response(true, 'OK', Strings::CreateEncryptedLink(BaseUrl() . 'order/pay/?&id=' . $payment->id . '&order_id=' . $payment->order_id . '&rows_ids_qnt=' . $payment->rows_ids_qnt));
		} else {
			Payment::_Log($order->id, "\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPayment save failed.\nSave:\n" . print_r($payment_save, true) . "\n\n");
			return new Response($payment_save);
		}
	}

	public static function GetList($sql='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
            $where="";
		} else if(Session::IsShopManager()) {
            $where="WHERE PAYMENT.company_id=" . DB::Quote(Session::User()->company_id);
		} else if(Session::IsUser()) {
            $where="WHERE PAYMENT.holder_user_id=" . DB::Quote(Session::UserId());
		} else { // Only admin and users are allowed to get list
			return [];
		}
        $sql="
            SELECT PAYMENT.id, PAYMENT.date_created, PAYMENT.order_id, IF(PAYMENT.type=0, '" . Strings::Get('cash') . "', '" . Strings::Get('card') . "') AS type, PAYMENT.amount, PAYMENT.products_amount, PAYMENT.tip_amount, PAYMENT.completed,
                COMPANY_CUSTOMER.name AS customer_name, IFNULL(USER.name, '-') AS holder_name
            FROM PAYMENT
            LEFT JOIN COMPANY_CUSTOMER ON PAYMENT.customer_id=COMPANY_CUSTOMER.id
            LEFT JOIN USER ON PAYMENT.holder_user_id=USER.id
            {$where};
        ";
        $rows=DB::Query($sql);
        if(empty($rows)) {
            $rows=[];
        } else {
            foreach($rows as &$row) {
                if($t=DB::Query("SELECT GROUP_CONCAT(name SEPARATOR ', ') AS table_name FROM TABLES WHERE id IN (SELECT table_id FROM ORDER_TABLE WHERE order_id=" . DB::Quote($row['order_id']) . " ORDER BY table_id);"))
                    $row['table_name']=$t[0]['table_name'];
                else
                    $row['table_name']='';
            }
        }

        return $rows;
	}

	public static function GetByUuid($uuid) {
        if(empty($uuid)) return null;
        $sql='SELECT id, gateway FROM PAYMENT WHERE uuid=' . DB::Quote($uuid) . ' ORDER BY date_created DESC LIMIT 1;';
        if(!$row=DB::Query($sql)) return null;
        if($row[0]['gateway']==1) $payment=new EveryPayPayment; else if($row[0]['gateway']==2) $payment=new VivaPayment; else $payment=new Payment;
        return $payment->Load(['id'=>$row[0]['id']]);
    }

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Payment::GetList();
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);
		} else if($action=='create') {
            return Payment::Create(GetRequest('order'));
		} else if($action=='charge') {
            // Check if payment id is set
            if(empty($id)) { // No payment id
                $uuid=GetRequest('payment_uuid');
                if(empty($uuid)) return new Response(false, Strings::Get('error_no_payment'));
                // Get payment by UUID
                $payment=Payment::GetByUuid($uuid);
                // Check payment
                if(empty($payment)) return new Response(false, Strings::Get('error_no_payment'));
            } else {
                $tmp_payment=new Payment;
                // Load payment by id
                if(!$tmp_payment->Load(['id'=>$id])) return new Response(false, Strings::Get('error_payment_not_found'));
                // Create appropriate payment model according to gateway
                if($tmp_payment->gateway==1) {
                    $payment=new EveryPayPayment;
                    $payment->Load(['id'=>$id]);
                } else if($tmp_payment->gateway==2) {
                    $payment=new VivaPayment;
                    $payment->Load(['id'=>$id]);
                } else {
                    $payment=$tmp_payment;
                }
            }
            $payment->gateway_order_code=GetRequest('gateway_order_code');
            $payment->gateway_transaction_id=GetRequest('gateway_transaction_id');
            $payment->gateway_language=GetRequest('gateway_language');
            if(GetRequest('token')) $payment->token=GetRequest('token');
            if(GetRequest('uuid')) $payment->uuid=GetRequest('uuid');
			return $payment->Charge();
        } else if($action=='result') {
            return Payment::HandleApi($id, 'charge');
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