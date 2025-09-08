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
if(isset($_REQUEST['lang']) || empty(Session::Get('selected_company_menu_timestamp')) || time()-Session::Get('selected_company_menu_timestamp')>600) {
	$company->GetMenu();
	Session::Set('selected_company_menu_timestamp', time());
}
$fields_to_remove=['email', 'pass'];
foreach($fields_to_remove as $field) if(isset($company->$field)) unset($company->$field);

// Build tips options
$company->GetTips();
$tips_options='<option value="0" tip_type="0" selected="selected">' . Strings::Get('tip_without') . '</option>'. PHP_EOL;
foreach($company->tips as $tip) $tips_options.='<option value="' . $tip->value . '" tip_type="' . $tip->type . '">' . Strings::FormatAmount($tip->value) . ($tip->type==0 ? '' : '%') . '</option>'. PHP_EOL;
$tips_options.='<option value="other" tip_type="0">' . Strings::Get('tip_other') . '</option>' . PHP_EOL;

// Get table
$table=Session::SelectedTable();
if($table) $table->GetData();

// Get submitted order
$submitted_order=$table->order;
$tip_user_id=$table && $table->waiters ? $table->waiters[0]->id : null;

// Create new order
$new_order=new Order;
$new_order->CreateDefaults([
	'id' => $submitted_order && $submitted_order->id ? $submitted_order->id : -floor(microtime(true) * 1000),
	'company_id' => $company->id,
	'company' => $company,
	'tables_ids' => $submitted_order && $submitted_order->tables_ids ? $submitted_order->tables_ids : [ $table->id ],
	'tables' => $submitted_order && $submitted_order->tables ? $submitted_order->tables : [ $table ],
]);

// Print header
PrintPageHead(Strings::Get('visitor_page_title_bill'));

?>
	<style>
		#page_content { padding: 5px 0; }

		.group_container { margin-bottom: 20px; color: #333; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 1px 2px gray; overflow: hidden; }
		.group_header { padding: 5px; font-size: large; font-weight: bold; color: #333; border-bottom: 1px solid #ddd; text-align: center; }
		.group_list { display: block; }

		.order_header_row { display: flex; flex-direction: row; gap: 2px; padding: 5px; overflow: hidden; }
		.order_header_row div { font-weight: bold; font-size: x-small; text-align: center; overflow: hidden; }
		.order_header_refresh { width: 30px; height: 30px; cursor: pointer; }
        .order_header_check { padding: 5px; overflow: hidden; }
		.order_header_texts { flex: 1; }
		.order_header_quantity { width: 60px; }
		.order_header_price { width: 60px; }
		.order_header_amount { width: 60px; text-align: right !important; }

		.order_product_row { display: flex; flex-direction: row; gap: 2px; padding: 5px; border-top: 1px solid #ddd; overflow: hidden; }
		.order_product_check { display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
		.order_product_texts { display: flex; flex-direction: column; justify-content: center; flex: 1; padding: 5px; overflow: hidden; }
		.order_product_title { display: block; font-weight: bold; overflow: hidden; }
		.order_product_main_price { display: inline-block; padding: 0 2px; vertical-align: middle; font-size: x-small; font-weight: normal; color: white; background-color: darkblue; border-radius: 4px; overflow: hidden; }
		.order_product_specs { display: block; overflow: hidden; }
		.order_product_spec { display: inline-block; overflow: hidden; }
		.order_product_spec_price { display: inline-block; padding: 0 2px; vertical-align: middle; font-size: x-small; color: white; background-color: black; border-radius: 4px; overflow: hidden; }
		.order_product_comment { display: block; color: red; font-size: small; overflow: hidden; }
		.order_product_quantity { display: flex; flex-direction: column; justify-content: center; width: 60px; text-align: right; overflow: hidden; }
		.order_product_price { display: flex; flex-direction: column; justify-content: center; width: 60px; text-align: right; overflow: hidden; }
		.order_product_amount { display: flex; flex-direction: column; justify-content: center; width: 60px; padding: 5px; color: #333; font-weight: bold; text-align: right; overflow: hidden; }

		.order_footer_row { display: flex; flex-direction: row; gap: 2px; padding: 5px; overflow: hidden; }
		.order_footer_row div { flex: 1; font-weight: bold; text-align: right;  overflow: hidden; }
		.order_footer_selected_count { text-align: left; }

		#bill_container { display: none; }
		#bill_container.group_container { border: 1px solid var(--bill_container_group_headerborder); }
		#bill_container .group_header { background-color: var(--bill_container_group_headerbg); }
		#bill_container .order_header_row { background-color: white; }
		#bill_container .order_product_row { background-color: white; }
		#bill_container .order_footer_row { color: #333; background-color: var(--bill_container_group_headerbg); }

		#order_pay_bill { font-weight: bold;position: fixed; display: none; left: 0; right: 0; bottom: 0; padding: 10px; color: black; background-color: var( --quantity_order-bg); border: 1px solid var( --quantity_order-border); border-radius: 8px 8px 0 0; text-align: center; cursor: pointer; }
		#no_product_to_pay { position: fixed; left: 0; right: 0; top: 50%; transform: translateY(-50%); font-size: x-large; color: #333; text-align: center; }

		#payment_method_container { display: none; position: fixed; left: 0; top: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.3); }
		#payment_method_box { position: fixed; left: 50%; top: 50%; transform: translateX(-50%) translateY(-50%); padding: 20px; background-color:white; border-radius: 10px; box-shadow: 0 3px 3px #555; overflow: hidden; }
		#payment_method_title { height: 30px; margin-top: 0; font-weight: bold; color: black; background-color: unset; line-height: 30px; cursor: unset; overflow: hidden; }
		.payment_method_button { border-radius:10px; width: 200px; height: 45px; margin-top: 10px; color: white; background-color: var(--payment_method_button); text-align: center; line-height: 45px; cursor: pointer; overflow: hidden; }

		#tip_title { height: unset; margin-top: 0; font-weight: bold; color: black; background-color: unset; line-height: 30px; cursor: unset; overflow: hidden; }
		#tip_combo { padding: 5px 10px; border-radius: 5px; }
		#tip_amount_container { display: none; margin-top: 8px; text-align: center; }
		#tip_amount { width: 80px; padding: 5px 10px; border-radius: 5px; }
		#tip_delimiter { height: 1px; margin-top: 10px; background-color: black; }
	</style>
	<body>
		<? $selected_menu='bill'; include 'view_header.php' ?>
		<div id="page_content">
			<div id="bill_container" class="group_container">
				<div id="bill_header" class="group_header flex_horizontal"><div class="order_header_refresh"></div><div style="flex: 1"><?=Strings::Get('order_payment')?></div><img class="order_header_refresh" src="<?=IMAGES_URL?>refresh_dark.png" /></div>
				<div id="bill_list" class="group_list">
                    <div class="order_header_row">
                        <input class="order_header_check" type="checkbox" checked="checked" />
						<div class="order_header_texts"><?=Strings::Get('order_product')?></div>
						<div class="order_header_quantity"><?=Strings::Get('order_product_quantity')?></div>
						<div class="order_header_price"><?=Strings::Get('order_product_price')?></div>
						<div class="order_header_amount"><?=Strings::Get('order_product_amount')?></div>
					</div>
                    <div class="order_list_data"></div>
                    <div class="order_footer_row">
						<div class="order_footer_selected_count"><?=Strings::Get('order_total_selected')?>: #SELECTED_COUNT#</div>
						<div class="order_footer_total_amount"><?=Strings::Get('order_total_amount')?>: #TOTAL_AMOUNT#&euro;</div>
						<div class="order_footer_selected_amount"><?=Strings::Get('order_selected_amount')?>: #SELECTED_AMOUNT#&euro;</div>
					</div>
                </div>
			</div>
			<div id="order_pay_bill"><?=Strings::Get('order_pay_bill')?></div>
			<div id="payment_method_container">
				<div id="payment_method_box">
					<div id="tip_title" class="payment_method_button">
						<?=Strings::Get('tip')?>:
						<select id="tip_combo"><?=$tips_options?></select>
					</div>
					<div id="tip_amount_container">
						<input id="tip_amount" type="number" min="0" max="1000" step="0.1" value="0" />
					</div>
					<div id="tip_delimiter"></div>
					<div id="payment_method_title" class="payment_method_button"><?=Strings::Get('payment_method')?></div>
					<div id="payment_method_cash_to_waiter" class="payment_method_button"><?=Strings::Get('payment_method_cash_to_waiter')?></div>
					<div id="payment_method_card_to_waiter" class="payment_method_button"><?=Strings::Get('payment_method_card_to_waiter')?></div>
					<? if($company->HasGateway()) { ?>
					<div id="payment_method_card_via_mobile" class="payment_method_button"><?=Strings::Get('payment_method_card_via_mobile')?></div>
					<? } ?>
				</div>
			</div>
		</div>
		<script>
			const company=new Company(<?=json_encode($company)?>);
			const table=new Table(<?=json_encode($table)?>);
			const submitted_order=new Order(<?=json_encode($submitted_order)?>);
			const new_order=new Order(sessionStorage.getItem('order') ? JSON.parse(sessionStorage.getItem('order')) : <?=json_encode($new_order)?>);

			if(submitted_order && submitted_order.products && submitted_order.products.length>0) for(let product of submitted_order.products) product.selected=1;

			submitted_order.UpdatePaid('<?=API_URL?>', function(){
				submitted_order.RefreshBadges();
				FillProducts();
			});

			function FillProducts() {
				const unpaid_lines=[];
				if(submitted_order.products) for(let product_line of submitted_order.products) {
					if(!product_line.paid) unpaid_lines.push(product_line);
				}
				if(Empty(unpaid_lines)) {
                    $('#page_content').html('<div id="no_products_to_pay"><?=Strings::Get('order_no_products_to_pay')?></div>');
                    $('#order_pay_bill').hide();
                    return;
                }

				const list_header='\
					<div class="order_header_row">\
                        <input class="order_header_check" type="checkbox" checked="checked" />\
						<div class="order_header_texts"><?=Strings::Get('order_product')?></div>\
						<div class="order_header_quantity"><?=Strings::Get('order_product_quantity')?></div>\
						<div class="order_header_price"><?=Strings::Get('order_product_price')?></div>\
						<div class="order_header_amount"><?=Strings::Get('order_product_amount')?></div>\
					</div>\
				';
				const list_footer='\
					<div class="order_footer_row">\
						<div class="order_footer_selected_count"><?=Strings::Get('order_total_selected')?>: #SELECTED_COUNT#</div>\
						<div class="order_footer_total_amount"><?=Strings::Get('order_total_amount')?>: #TOTAL_AMOUNT#&euro;</div>\
						<div class="order_footer_selected_amount"><?=Strings::Get('order_selected_amount')?>: #SELECTED_AMOUNT#&euro;</div>\
					</div>\
				';

				var list_products='', row_index=-1, selected_count=0, total_amount=0, total_amount_selected=0;

				// Build HTML
				for(let product of submitted_order.products) {
					if(product.paid) continue;
                    row_index++;
					// Build specs
					var specs=''; if(product.specs && product.specs.length) for(let spec of product.specs) specs+=(specs=='' ? '' : ', ') + '<div class="order_product_spec">' + spec.name + (parseFloat(spec.price)==0 ? '' : ' <div class="order_product_spec_price">' + StrFloat(spec.price) + '&euro;</div>') + '</div>';
                    // Build product row
                    list_products+='\
                        <div class="order_product_row" product_id="' + product.id + '" product_row_id="' + product.row_id + '" product_row_index="' + row_index + '">\
                            <input class="order_product_check" type="checkbox" ' + (product.selected ? 'checked="checked"' : '') + ' />\
                            <div class="order_product_texts">\
                                <div class="order_product_title">' + product.name + (parseFloat(product.price_specs)>0 ? ' <div class="order_product_main_price">' + StrFloat(product.price) + '&euro;</div>' : '') + '</div>\
                                <div class="order_product_specs">' + specs + '</div>\
                                <div class="order_product_comment">' + product.comment + '</div>\
                            </div>\
                            <div class="order_product_quantity">' + product.quantity + '</div>\
                            <div class="order_product_price">' + StrFloat(product.price + product.price_specs) + '&euro;</div>\
                            <div class="order_product_amount">' + StrFloat(product.amount) + '&euro;</div>\
                        </div>\
                    ';
                    if(product.selected) {
                        selected_count++;
                        total_amount_selected+=parseFloat(product.amount);
                    }
                    total_amount+=parseFloat(product.amount);
				}
                $('#bill_container').show();
                $('.order_list_data').html(list_products);
                RefreshFooter();
			}

            function RefreshFooter(){
                var selected_count=0, total_amount=0, total_amount_selected=0;
                for(let product of submitted_order.products) {
					if(product.paid) continue;
                    if(product.selected) {
                        selected_count++;
                        total_amount_selected+=parseFloat(product.amount);
                    }
                    total_amount+=parseFloat(product.amount);
                }
                $('.order_footer_selected_count').html('<?=Strings::Get('order_total_selected')?>: ' + selected_count);
                $('.order_footer_total_amount').html('<?=Strings::Get('order_total_amount')?>: ' + StrFloat(total_amount) + '&euro;');
                $('.order_footer_selected_amount').html('<?=Strings::Get('order_selected_amount')?>: ' + StrFloat(total_amount_selected) + '&euro;');
                if(total_amount_selected>0) $('#order_pay_bill').show(); else $('#order_pay_bill').hide();
                return total_amount_selected;
            }

			function PayByCardViaMobile() {
				if(RefreshFooter()==0) return;
				const order_pay=new Order();
				order_pay.Reconstruct(submitted_order);
				order_pay.ReadyToPay();
				if(order_pay.products.length==0) return;
				order_pay.tip_amount=0; try { order_pay.tip_amount=parseFloat($('#tip_amount').val()); } catch(e) {}
				order_pay.tip_user_id=<?=empty($tip_user_id) ? 'null' : $tip_user_id?>;

				ShowLoader();
				Post('<?=API_URL?>',
					{ controller: 'payment', action: 'create', order: JSON.stringify(order_pay), waiters_ids: JSON.stringify(table.waiters_ids) },
					function(response) {
						HideLoader();
						if(response==undefined || response==null || response.status==undefined) {
							alert('<?=Strings::Get('error_invalid_server_response')?>');
						} else if(response.status) {
							if(response.data)
								window.location.href=response.data;
							else
								ShowModal('<?=Strings::Get('pay_order')?>', '<?=Strings::Get('error_creating_order_payment')?>');

						} else {
							ShowModal('<?=Strings::Get('pay_order')?>', response.message ?? '<?=Strings::Get('error_creating_order_payment')?>');
						}
						$('#payment_method_container').hide();
					}
				);
			}

			function PayToWaiter(by_cash) {
				if(RefreshFooter()==0) return;
				const order_pay=new Order();
				order_pay.Reconstruct(submitted_order);
				order_pay.ReadyToPay();
				if(order_pay.products.length==0) return;
				order_pay.tip_amount=0; try { order_pay.tip_amount=parseFloat($('#tip_amount').val()); } catch(e) {}
				order_pay.tip_user_id=<?=empty($tip_user_id) ? 'null' : $tip_user_id?>;

				ShowLoader();
				Post('<?=API_URL?>',
					{ controller: 'customer', action: by_cash ? 'notify_pay_by_cash' : 'notify_pay_by_card', order: JSON.stringify(order_pay), waiters_ids: JSON.stringify(table.waiters_ids) },
					function(response) {
						HideLoader();
						if(response==undefined || response==null || response.status==undefined) {
							alert('<?=Strings::Get('error_invalid_server_response')?>');
						} else {
							ShowModal('<?=Strings::Get('pay_order')?>', response.message ?? '<?=Strings::Get('error_creating_order_payment')?>');
						}
						$('#payment_method_container').hide();
					}
				);
			}

			$(document).ready(function(){
				$('.order_header_refresh').click(function(e){
					e.preventDefault(); e.stopPropagation();
					ShowLoader();
					window.location.reload();
				});

                $('#bill_container').on('click', '.order_product_check', function(e){
                    e.stopPropagation();
                    const product_id=$(this).closest('.order_product_row').attr('product_id');
                    const product_row_id=$(this).closest('.order_product_row').attr('product_row_id');
                    if(product_id==undefined || product_row_id==undefined) return;
					for(let product of submitted_order.products) if(product.row_id==product_row_id) { product.selected=$(this).is(':checked') ? 1 : 0; break; }
                    RefreshFooter();
                });

                $('#bill_container').on('click', '.order_header_check', function(e){
                    e.stopPropagation();
                    for(let product of submitted_order.products) product.selected=this.checked ? 1 : 0;
                    FillProducts();
                });

                $('#bill_container').on('click', '.order_product_row', function(e){
                    e.stopPropagation();
                    $('input[type="checkbox"]', this).click();
                });

				// Pay products
				$('#order_pay_bill').click(function(e){
					e.preventDefault(); e.stopPropagation();
					const selected_amount=RefreshFooter();
                    if(selected_amount==0) return;

					// Fix tips with percentage texts
					$('body #payment_method_container #tip_combo option[tip_type="1"]').each(function(){
						$(this).text($(this).val() + '% = ' + StrFloat(parseFloat($(this).val() * selected_amount / 100)) + '€');
					});
					$('body #payment_method_container #tip_combo').change();

					$('#payment_method_container').show();
				});

				$('#payment_method_cash_to_waiter').click(function(e){
					e.preventDefault(); e.stopPropagation();
                    PayToWaiter(true);
				});

				$('#payment_method_card_to_waiter').click(function(e){
					e.preventDefault(); e.stopPropagation();
                    PayToWaiter(false);
				});

				$('#payment_method_card_via_mobile').click(function(e){
					e.preventDefault(); e.stopPropagation();
                    PayByCardViaMobile();
				});

				$('#tip_combo').change(function(e){
					if($(this).val()=='other') {
						$('#tip_amount_container').show();
						$('#tip_amount').focus();
					} else {
						// If tip option is percentage, the tip amount is selected items total amount * percent, else is the option amount
						const tip_value=$('option:selected', this).attr('tip_type')=='1' ? RefreshFooter() * parseFloat($(this).val()) / 100 : parseFloat($(this).val());
						$('#tip_amount_container').hide();
						$('#tip_amount').val(tip_value);
					}
				});

			});
		</script>
	</body>
</html><? exit;