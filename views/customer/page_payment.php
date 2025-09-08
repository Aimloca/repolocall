<?
if(!Session::IsCustomer()) Redirect();
if(empty(Session::SelectedTable()) && Session::CustomerId()<=0) { Session::Remove('customer'); Redirect(); }

// Get customer
$customer=clone Session::Customer();
$customer->KeepOnlyDBFields();
unset($customer->pass);

// Get company
$company=Session::SelectedCompany();
// Check last menu update
if(empty(Session::Get('selected_company_menu_timestamp')) || time()-Session::Get('selected_company_menu_timestamp')>600) {
	$company->GetMenu();
	Session::Set('selected_company_menu_timestamp', time());
}
$fields_to_remove=['email', 'pass'];
foreach($fields_to_remove as $field) if(isset($company->$field)) unset($company->$field);

// Check company gateway
if($company->payment_gateway==0) { $error=Strings::Get('error_company_payment_gateway_is_not_set'); include VIEWS_PATH . 'page_error.php';	}

// Get table
$table=Session::SelectedTable();
if($table) $table->GetData();

// Get passed payment id
$payment_id=GetRequest('id');
if(empty($payment_id)) { $error=Strings::Get('error_no_payment_id'); include VIEWS_PATH . 'page_error.php';	}

// Load payment
$tmp_payment=new Payment;
if(!$tmp_payment->Load(['id'=>$payment_id])) { $error=Strings::Get('error_payment_not_found'); include VIEWS_PATH . 'page_error.php'; }
if($tmp_payment->gateway==1) {
	$payment=new EveryPayPayment;
	$payment->Load(['id'=>$payment_id]);
} else if($tmp_payment->gateway==2) {
	$payment=new VivaPayment;
	$payment->Load(['id'=>$payment_id]);
} else {
	$payment=$tmp_payment;
}

// Check payment type
if($payment->type==0) { $error=Strings::Get('error_payment_is_cash'); include VIEWS_PATH . 'page_error.php'; }

// Check payment gateway
if($payment->gateway==0) { $error=Strings::Get('error_payment_gateway_is_cash'); include VIEWS_PATH . 'page_error.php';	}

// Get passed order rows ids and quantities
$payment->rows_ids_qnt=GetRequest('rows_ids_qnt');
if(empty($payment->rows_ids_qnt)) { $error=Strings::Get('error_no_order_rows_ids'); include VIEWS_PATH . 'page_error.php'; }

// Get payment form
$form=$payment->GetForm();
if(!$form->status) { $error=$form->message; include VIEWS_PATH . 'page_error.php'; }

// Print header
PrintPageHead(Strings::Get('visitor_page_title_payment'), '', '', $payment->extra_script);

?>
	<style>
		#page_content { padding: 5px 0; text-align: center; }
		#go_back { display: block; width: 50px; height: 50px; padding: 5px; cursor: pointer; }
		#gateway_logo { margin: auto; width: 400px; }
		#gateway_form_loading { display: inline-block; color: #333; margin: 10px auto; }
		#gateway_form_loading img { width: 100px; }
		#pay-form { margin: auto; }
	</style>
	<body>
		<div id="page_content">
			<img id="go_back" src="<?=IMAGES_URL?>previous_black.png" />
			<img id="gateway_logo" src="<?=$payment->logo?>" /><br />
			<div id="gateway_form_loading">
				<img src="<?=IMAGES_URL?>loading_small.gif" /><br />
				<?=Strings::Get('payment_gateway_loading')?>
			</div>
            <?=$form->data?>
		</div>
		<script>
			const interval=setInterval(function() {
				if($('#pay-form iframe').length>0) {
					clearInterval(interval);
					$('#gateway_form_loading').hide();
				}
			}, 500);
			$(document).ready(function(){
				$('#go_back').click(function(){
					window.history.back();
				});
			});
		</script>
	</body>
</html><? exit;