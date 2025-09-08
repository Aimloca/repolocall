<?php
class VivaPayment extends Payment {
    /*
    https://demo.vivapayments.com/selfcare/en/home
    https://demo-accounts.vivapayments.com/Account/Login
    6934726470 Vivmobmental!1
    Merchant ID: b2bdc0b6-60c2-4371-8325-39c77fa57c1b
    API Key: -YxMcu

    The amount requested in cents (amount in euros x 100). The amount must always be greater than 30 cents (which is the minimum amount you can charge with Viva Payments).
	If you want to create a payment for ?100.37, you need to pass the value 10037*

	Demo card 5239 2907 0000 0101   111
	Demo card 6759 6498 2643 8453   111
    Demo card 3762 060000 00009     1111
    Demo card erroneous 3762 0600 0000 025  1111
    Demo card erroneous 5012 8899 1154 1119  111

	Demo panel https://demo.vivapayments.com/selfcare/en-gb/sources/paymentsources?sourceTypeId=ae0e2b35-df43-4729-bd6d-a89a86ce3640

	https://developer.vivapayments.com/online-checkouts/native-checkout/
	https://github.com/VivaPayments/API/blob/cc95dd31a000d3d61ceafdd91d6c46a7a1b2b1f1/NativeCheckout/PHP/web/index.php
	https://github.com/VivaPayments/API/blob/cc95dd31a000d3d61ceafdd91d6c46a7a1b2b1f1/NativeCheckout/PHP/lib/NativeCheckoutClass.php


    For website/app creation
    Sales -> Online payments -> Websites/Apps -> Add Website/App
    Source Id: iServeMe
    Source name: iServeMe
    Protocol: HTTPS
    Domain name: panel.iserveme.com
    Success url: payment/result/?
    Failure url: payment/result/?
    Advanced configuration -> Language: l
    */

    const table='PAYMENT';
	public static $db_fields;
    private static $error_codes=[
        '400' => 'Ο αριθμός της κάρτας δεν είναι έγκυρος',
        '401' => 'Έλεγξτε την ημερομηνία λήξεως της κάρτας σας',
        '422' => 'PaymentsNoBinRouteFound',
    ];

	function __construct($primary_key_value='') {
		parent::__construct('PAYMENT', 'id', $primary_key_value);
        $this->logo='https://demo.vivapayments.com/Content/img/logo_wallet-dark.svg';
        $this->extra_script='';
	}

    function Load($filters='') {
        $load=parent::Load($filters);
        if($load) {
            // Read debug
            $this->debug=1;
            // Check if company id is set
            if($this->company_id) {
                // Get company parameters
                $sql="SELECT * FROM PARAMETERS WHERE company_id=" . DB::Quote($this->company_id) . "LIMIT 1;";
                if($row=DB::Query($sql)) {
                    $this->debug=$row[0]['viva_debug']==1;
                    $this->api_key=$row[0][$this->debug ? 'viva_api_key_debug' : 'viva_api_key'];
                    $this->merchant_id=$row[0][$this->debug ? 'viva_merchant_id_debug' : 'viva_merchant_id'];
                }
            }
        }
		return $load ? $this : false;
	}

    function Request($url, $post_fields='', $headers='', $valid_empty_response=false) {

        $ret=[ 'url' => $url, 'params' => $post_fields ];

        $json_post_fields=json_encode($post_fields);
        $http_headers=array('Content-Type: application/json', 'Content-Length: ' . strlen($json_post_fields));
        if(!empty($headers)) $http_headers=array_merge($http_headers, $headers);

        // Call api
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post_fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->merchant_id . ':' . $this->api_key);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        //if(VIVA_PAYMENT_DEBUG_MODE) curl_setopt($ch, CURL_SSLVERSION_TLSv1, 1);
        curl_setopt( $ch, CURLOPT_SSLVERSION, 6);
        $response=curl_exec($ch);
        curl_close($ch);

        $this->Log(date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nREQUEST $url\nParams: " . print_r($post_fields, true) . "\nResponse: $response\n\n");
        $ret['response']=$response;

        // Check response
        if($valid_empty_response==false && $response==false) return new Response(false, 'Could not connect to Viva server, please contact the administrator', $ret);

        // Check allowed empty response
        if($valid_empty_response && $response==false) return new Response(true, 'Transaction completed', $ret);

        // Get response json
        $json=json_decode($response);
        // Check json response
        if(!$json) {
            return new Response(false, "Viva server sent an error response. {$response}", $ret);
        } else if(isset($json->ErrorText) && !empty($json->ErrorText)) {
            $error_message=isset($json->ErrorCode) && isset(self::$error_codes[$json->ErrorCode]) ? self::$error_codes[$json->ErrorCode] : $json->ErrorText;
            return new Response(false, $error_message, $ret);
        }
        $ret['json']=$json;
        return new Response(true, 'OK', $ret);
    }

    function GetForm($success_redirect='/user/home') {
        // Check criticals
        $check_criticals=$this->CheckCriticals();
        if(!$check_criticals['status']) return new Response($check_criticals);

        // Check gateway
        $check_gateway=$this->CheckGateway();
        if(!$check_gateway->status) return $check_gateway;

        // Save payment
        $save=$this->Save();
        if(!$save['status']) {
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPayment save failed.\n" . print_r($save, true) . "\n\n");
            return new Response($save);
        }

        $tags="payment_id={$this->id}&order_id={$this->order_id}";

        // Create post fields
        $post_fields=[
            'SourceCode' 			=> APP_NAME,
            'Tags' 					=> $tags,
            'PaymentTimeOut' 		=> 86400,
            'RequestLang' 			=> 'el-GR',
            'AllowRecurring' 		=> false,
            'MerchantTrns' 			=> $tags,
            'CustomerTrns' 			=> $this->debug ? 'Test card: 5239290700000101 111 - Error card: 5012889911541119 111' : 'Order ' . $this->order_id,
            'Amount' 				=> $this->debug ? 10 : $this->FixAmountForPayment(),
            'disableCash' 			=> true,
            'disablePayAtHome'		=> true,
            'disableWallet'			=> false,
            'IsPreAuth'				=> 1,
        ];

        $api_url=$this->debug ? 'https://demo.vivapayments.com' : 'https://www.vivapayments.com';

        // Call api
        $request_response=$this->Request($api_url . '/api/Orders', $post_fields);

        // Check response
        if(!$request_response->status) return $request_response;

        // Check order code
        $json=$request_response->data['json'];
        if(!isset($json->OrderCode) || empty($json->OrderCode)) return new Response(false, 'Order code is missing from Viva server response. ' . $request_response->data['response']);

        $this->form_response=json_encode($json);
        $this->form_url=$api_url . '/web/checkout?ref=' . $json->OrderCode;
        $this->uuid=$json->OrderCode;
        $html='<script>window.location="' . $this->form_url . '";</script>';

        $this->Save();
        $this->Log(date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPAYMENT FORM CREATED\n" . print_r($html, true) . "\n\n");
        return new Response(true, 'ΟΚ', $html);
    }

    function Charge() {
        $this->charge_response=null;
        $this->error=null;

        try {
            // Get order
            $this->GetOrder();

            // Check criticals
            $check_criticals=$this->CheckCriticals();
            if(!$check_criticals['status']) throw new Exception($check_criticals['message']);

            // Check UUID
            if(empty($this->uuid)) throw new Exception(Strings::Get('error_no_payment_uuid'));

            // Check token
            if(empty($this->token)) $this->token=isset($this->gateway_transaction_id) ? $this->gateway_transaction_id : '';
            if(empty($this->token)) throw new Exception(Strings::Get('error_no_payment_token'));

            // Check products
            if(empty($this->products)) throw new Exception(Strings::Get('error_no_products'));

            // Check products rows ids quantities
            if(empty($this->rows_ids_qnt)) throw new Exception(Strings::Get('error_no_rows_ids_qnt'));

            $api_url=$this->debug ? 'https://demo.vivapayments.com' : 'https://www.vivapayments.com';

			// Call api
			$ch = curl_init("{$api_url}/api/transactions/{$this->token}");
			curl_setopt($ch, CURLOPT_HTTPGET, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
			curl_setopt($ch, CURLOPT_USERPWD, $this->merchant_id . ':' . $this->api_key);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			//curl_setopt($ch, CURL_SSLVERSION_TLSv1, 1);
			curl_setopt( $ch, CURLOPT_SSLVERSION, 6);
		    $response=curl_exec($ch);
		    curl_close($ch);

            $this->Log(date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nConfirmTransaction\nThis: " . print_r($this, true) . "\nResponse: $response\n\nRequest: " . print_r($_REQUEST, true) . "\n\n");

			// Check response
			if($response==false) throw new Exception('Could not connect to Viva server, please contact the administrator');
            $this->charge_response=$response;
			// Get response json
			$json=json_decode($response);
			// Check json response
			if(!$json) throw new Exception('Viva server sent an error response. ' . $response);
			else if(isset($json->ErrorText) && !empty($json->ErrorText)) throw new Exception($json->ErrorText);
			else if(!isset($json->Transactions) || empty($json->Transactions)) throw new Exception('Transactions is missing from Viva server response. ' . $response);
			else if(count($json->Transactions)<=0) throw new Exception('No transactions found.');

            // Save
			$this->Save();

			$found=false;
			$this->error='';
			$this->status='Unknown';
			$this->completed=0;
            $has_error=false;
			foreach($json->Transactions as $t) {
				if($t->TransactionId==$this->token) {
					$found=true;
					switch($t->StatusId) {
						case 'E': 	$this->status='The transaction was not completed because of an error'; $has_error=true; break;
						case 'A': 	$this->status='The transaction is in progress'; $has_error=false; break;
						case 'M': 	$this->status='The cardholder has disputed the transaction with the issuing Bank'; $has_error=true; break;
						case 'MA': 	$this->status='Dispute Awaiting Response'; $has_error=true; break;
						case 'MI': 	$this->status='Dispute in Progress'; $has_error=true; break;
						case 'ML': 	$this->status='A disputed transaction has been refunded (Dispute Lost)'; $has_error=true; break;
						case 'MW': 	$this->status='Dispute Won'; $has_error=false; break;
						case 'MS': 	$this->status='Suspected Dispute'; $has_error=true; break;
						case 'X': 	$this->status='The transaction was canceled by the merchant'; $has_error=true; break;
						case 'R': 	$this->status='The transaction has been fully or partially refunded'; $has_error=true; break;
						case 'F': 	$this->status='The transaction has been completed successfully;'; $has_error=false; break;
						case 'C': 	$this->status='The transaction has been captured;'; $has_error=false; break;
						default: 	$this->status='Unknown'; $has_error=true;
					}
                    if($has_error) $this->error=$this->status;
                    $this->status.="\n\n" . str_replace("&", "\n", $t->MerchantTrns);
				}
				if($found) break;
			}
            if(!$found) {
                $this->status='Unknown';
                $this->error='Transaction id not found';
            }
            $this->completed=empty($this->error) ? 1 : 0;
			$this->Save();

            if(!empty($this->error)) throw new Exception($this->error);

            // No error
            $this->completed=1;

            // Update order paid products
            $update_result=$this->order->UpdatePaid($this);
            if(!$update_result->status) {
                $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\n{$this->error}\n" . print_r($update_result, true) . "\n\n");
                throw new Exception($update_result->message);
            }

            // Log
            $this->Log(date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPayment charged successfully\n{$this->charge_response}\n\n");
        } catch(Exception $e) {
            $this->error=$e->getMessage();
            $this->Log("\n\n########## ERROR ##########\n" . date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\n{$this->error}\n{$this->charge_response}\n\n");
        }

        // Save payment
        $this->Save();

        return new Response(empty($this->error), empty($this->error) ? 'OK' : $this->error);
    }

}