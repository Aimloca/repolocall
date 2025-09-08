<?php
class EveryPayPayment extends Payment {
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

	function __construct($primary_key_value='') {
		parent::__construct('PAYMENT', 'id', $primary_key_value);
        $this->logo='https://docs.everypay.gr/everypay.png';
        $this->extra_script='<script src="' . ($this->debug ? EVERY_PAY_JS_SCRIPT_DEBUG : EVERY_PAY_JS_SCRIPT). '"></script>';
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
                    $this->debug=$row[0]['every_pay_debug']==1;
                    $this->public_key=$row[0][$this->debug ? 'every_pay_public_key_debug' : 'every_pay_public_key'];
                    $this->private_key=$row[0][$this->debug ? 'every_pay_private_key_debug' : 'every_pay_private_key'];
                }
            }
        }
		return $load ? $this : false;
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

        $html='
            <!-- OrderCode:' . $this->uuid . ' -->
            <div id="pay-form"></div>
            <style>
                #result_box { display: inline-block; margin-top: 30px; padding: 30px; color: white; background-color: red; box-shadow: 0 1px 2px gray; border-radius: 10px; overflow: hidden; }
                #result_box img { width: 200px; }
                #result_box div { padding: 5px; text-align: center; font-size: large; font-weight: bold; }
                #result_box.success { background-color: #F9F9F9 !important; color: #333 !important; }
            </style>
            <script>
                const initial_url=window.location.href;
                window.history.replaceState({}, "' . Strings::Get('visitor_page_title_payment') . '", "' . BaseUrl() . 'bill/view/");

                function PayformResponse(r) {
                    if(r.response==="success") {
                        Post("' . Strings::CreateEncryptedLink(BaseUrl() . 'index.php?api=1&controller=payment&action=charge&id=' . $this->id . '&order_id=' . $this->order_id) . '&gateway=1",
                            { token: r.token,  uuid: r.uuid },
                            function(response) {
                                HideLoader();
                                console.log(response); // This is a response from your backend. Handle it as you wish.
                                if(response==undefined || response==null || response.status==undefined) {
                                    $("#page_content").append("<div id=\"result_box\"><div>' . Strings::Get('error_invalid_server_response') . '</div></div>");
                                } else if(response.status) {
                                    $("#page_content").append("<div id=\"result_box\" class=\"success\"><img src=\"' . IMAGES_URL . 'payment_success.gif\" /><div>' . Strings::Get('payment_success') . '</div></div>");
                                    setTimeout(function(){ Redirect("' . $success_redirect . '") }, 3000);
                                } else {
                                    $("#page_content").append("<div id=\"result_box\"><div>" + (response.message ?? "' . Strings::Get('error_order_payment_failed') . '") + "</div></div>");
                                }
                            },
                            function(jqXHR, textStatus, errorThrown) {
                                console.log(jqXHR, textStatus, errorThrown);
                                $("#page_content").append("<div id=\"result_box\"><div>" + textStatus + "</div></div>");
                            }
                        );
                    } else if(!r.onLoad) {
                        ShowModal("' . Strings::Get('order_payment') . '", "' . Strings::Get('error_order_payment_failed') . '" + (r.msg.message ? "<br />" + r.msg.message : ""));
                    }
                }
                everypay.payform({ pk: "' . $this->public_key . '", amount: ' . $this->FixAmountForPayment() . ', locale: "' . Strings::GetLanguage() . '", txnType: "tds", }, PayformResponse);
            </script>
        ';
        $this->form_response=$html;
        $this->Save();
        $this->Log(date('Y-m-d H:i:s') . ":" . GetClientIp() . ":Customer id:" . Session::CustomerId() . "\nPAYMENT FORM CREATED\n" . print_r($html, true) . "\n\n");
        return new Response(true, 'ΟΚ', $html);
    }

    function Charge() {
        $this->charge_response=null;
        $this->error=null;

        try {
            // Check criticals
            $check_criticals=$this->CheckCriticals();
            if(!$check_criticals['status']) throw new Exception($check_criticals['message']);

            // Check UUID
            if(empty($this->uuid)) throw new Exception(Strings::Get('error_no_payment_uuid'));

            // Check token
            if(empty($this->token)) throw new Exception(Strings::Get('error_no_payment_token'));

            // Check products
            if(empty($this->products)) throw new Exception(Strings::Get('error_no_products'));

            // Check products rows ids quantities
            if(empty($this->rows_ids_qnt)) throw new Exception(Strings::Get('error_no_rows_ids_qnt'));

            // Prepare request
            $pk=$this->private_key;
            $post=[
                'token' => $this->token,
                'amount' => $this->FixAmountForPayment(),
                'description' => "Company: {$this->company_id} Order: {$this->order_id} - Customer: {$this->customer_id}",
            ];

            $curl=curl_init($this->debug ? 'https://sandbox-api.everypay.gr/payments' : 'https://api.everypay.gr/payments');
            curl_setopt($curl, CURLOPT_USERPWD, "{$pk}:");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $this->charge_response=curl_exec($curl);
            curl_close($curl);

            // Check response
            if(empty($this->charge_response)) throw new Exception(Strings::Get('error_empty_payment_gateway_response'));

            // Parse json response
            $json_response=@json_decode($this->charge_response);
            // Check json
            if(empty($json_response)) throw new Exception(Strings::Get('error_invalid_payment_gateway_response'));
            if(!empty($json_response->error)) throw new Exception(isset($json_response->error->message) ? $json_response->error->message : Strings::Get('error_payment_failed'));

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