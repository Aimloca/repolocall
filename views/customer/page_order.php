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

// Get table
$table=Session::SelectedTable();
if($table) $table->GetData();

// Get submitted order
$submitted_order=$table->order;

// Create new order
$new_order=new Order;
$new_order->CreateDefaults([
	'id' => $submitted_order && $submitted_order->id ? $submitted_order->id : -floor(microtime(true) * 1000),
	'company_id' => $company->id,
	'company' => $company,
	'tables_ids' => $submitted_order && $submitted_order->tables_ids ? $submitted_order->tables_ids : [ $table->id ],
	'tables' => $submitted_order && $submitted_order->tables ? $submitted_order->tables : [ $table ],
]);

//if(isAdamIp()) diep($order);
// Print header
PrintPageHead(Strings::Get('visitor_page_title_order'));

?>
	<style>
		#page_content { padding: 5px 0; }

		.group_container { margin-bottom: 40px; color: #333; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 1px 2px gray; overflow: hidden; }
		.group_header { padding: 5px; font-size: large; font-weight: bold; color: #333; border-bottom: 1px solid #ddd; }
		.group_list { display: block; }

		.order_header_row { display: flex; flex-direction: row; gap: 2px; padding: 5px; overflow: hidden; }
		.order_header_row div { font-weight: bold; font-size: x-small; text-align: center; overflow: hidden; }
		.order_header_texts { flex: 1; }
		.order_header_quantity { width: 40px; }
		.order_header_price { width: 60px; }
		.order_header_amount { width: 60px; text-align: right !important; }

		.order_product_row { display: flex; flex-direction: row; gap: 2px; border-top: 1px solid #ddd; overflow: hidden; }
		.order_product_texts { display: flex; flex-direction: column; justify-content: start; flex: 1; padding: 5px; overflow: hidden; }
		.order_product_title { display: block; font-weight: bold; overflow: hidden; }
		.order_product_main_price { display: inline-block; padding: 0 2px; vertical-align: middle; font-size: x-small; font-weight: normal; color: white; background-color: darkblue; border-radius: 4px; overflow: hidden; }
		.order_product_specs { display: block; overflow: hidden; }
		.order_product_spec { display: inline-block; overflow: hidden; }
		.order_product_spec_price { display: inline-block; padding: 0 2px; vertical-align: middle; font-size: x-small; color: white; background-color: black; border-radius: 4px; overflow: hidden; }
		.order_product_comment { display: block; color: red; font-size: small; overflow: hidden; }
		.order_product_quantity_container { display: flex; flex-direction: column; justify-content: start; width: 40px; padding: 5px; overflow: hidden; }
		.order_product_quantity { font-weight: bold; text-align: center; overflow: hidden; }
		.order_product_quantity_unit { font-size: small; text-align: center; overflow: hidden; }
		.order_product_price_container { display: flex; flex-direction: column; justify-content: start; width: 60px; padding: 5px; overflow: hidden; }
		.order_product_price { display: block; font-weight: bold; text-align: center; overflow: hidden; }
		.order_product_price_unit { font-size: small; text-align: center; overflow: hidden; }
		.order_product_amount { display: flex; flex-direction: column; justify-content: start; width: 60px; padding: 5px; color: #333; font-weight: bold; text-align: right; overflow: hidden; }
		.order_product_icon { width: 40px; height: 40px; margin: 5px; border-radius: 5px; background-size: cover; overflow: hidden; }

		.order_footer_row { display: flex; flex-direction: row; gap: 2px; align-items: center; padding: 5px; overflow: hidden; }
		.order_footer_row div { font-weight: bold; text-align: right;  overflow: hidden; }
		.order_footer_button { display: block; padding: 5px 10px; text-align: center; color: white; border-radius: 4px; cursor: pointer; }
		.order_footer_totals { flex: 1; }
		.order_footer_amount { width: 60px; }

		#unordered_container.group_container { border: 1px solid var(--bill_container_group_headerborder) ; }
		#unordered_container .group_header { background-color: var(--bill_container_group_headerbg); }
		#unordered_container .order_header_row { background-color: white; }
		#unordered_container .order_product_row { background-color: white; }
		#unordered_container .order_footer_row { color: #333; background-color: var(--bill_container_group_headerbg); }

		#ordered_container.group_container { border: 1px solid var(--ordered_containerorder_footer_rowbd); }
		#ordered_container .group_header { color: white; background-color: var(--ordered_containerorder_footer_row); }
		#ordered_container .order_header_row { background-color: #ededed; }
		#ordered_container .order_product_row { background-color: #ededed; }
		#ordered_container .order_footer_row { color: white; background-color: var(--ordered_containerorder_footer_row); }
		#ordered_header_refresh { width: 30px; height: 30px; cursor: pointer; }
		#order_footer_cancel_order_items { border: 1px solid white; background-color: darkred; }

		#order_send_products {   font-weight: bold; position: fixed; display: none; left: 0; right: 0; bottom: 0; padding: 10px; color: black; background-color: var( --quantity_order-bg) ; border: 1px solid var( --quantity_order-border) ; border-radius: 8px 8px 0 0; text-align: center; cursor: pointer; }
		#no_products_in_order { position: fixed; left: 0; right: 0; top: 50%; transform: translateY(-50%); font-size: x-large; color: #333; text-align: center; }

	</style>
	<body>
		<? $selected_menu='order'; include 'view_header.php' ?>
		<div id="page_content">
			<div id="unordered_container" class="group_container">
				<div id="unordered_header" class="group_header"><?=Strings::Get('additional_order')?></div>
				<div id="unordered_list" class="group_list"></div>
			</div>
			<div id="ordered_container" class="group_container">
				<div id="ordered_header" class="group_header flex_horizontal"><div style="flex: 1"><?=Strings::Get('submitted_order')?></div><img id="ordered_header_refresh" src="<?=IMAGES_URL?>refresh_light.png" /></div>
				<div id="ordered_list" class="group_list"></div>
			</div>
			<div id="order_send_products"><?=Strings::Get('order_send_products')?></div>
		</div>
		<script>
			const company=new Company(<?=json_encode($company)?>);
			const table=new Table(<?=json_encode($table)?>);
			const submitted_order=new Order(<?=json_encode($submitted_order)?>);
			const new_order=new Order(sessionStorage.getItem('order') ? JSON.parse(sessionStorage.getItem('order')) : <?=json_encode($new_order)?>);
			var timeout_refresh_submitted_order=setTimeout(GetSubmittedOrder, 20000);
			
			FillProducts();
			new_order.RefreshBadges();

			function GetSubmittedOrder() {
				clearTimeout(timeout_refresh_submitted_order);
				Post('<?=API_URL?>',
					{ controller: 'customer', action: 'get_submitted_order', id: submitted_order.id },
					function(response) {
						if(response && response.status && response.data) submitted_order.Reconstruct(response.data);
						FillProducts();
					}
				);
				timeout_refresh_submitted_order=setTimeout(GetSubmittedOrder, 20000);
			}
			
			function FillProducts() {
				const list_header='\
					<div class="order_header_row">\
						<div class="order_header_texts"><?=Strings::Get('order_product')?></div>\
						<div class="order_header_quantity"><?=Strings::Get('order_product_quantity')?></div>\
						<div class="order_header_price"><?=Strings::Get('order_product_price')?></div>\
						<div class="order_header_amount"><?=Strings::Get('order_product_amount')?></div>\
					</div>\
				';
				const list_footer='\
					<div class="order_footer_row">\
						#CANCEL_PRODUCTS#\
						<div class="order_footer_totals"><?=Strings::Get('order_total')?>:</div>\
						<div class="order_footer_amount">#TOTAL_AMOUNT#&euro;</div>\
					</div>\
				';

				// Build unordered HTML
				var html_unordered='', total_amount_unordered=0;
				if(new_order.products && new_order.products.length) for(let product of new_order.products) {
					// Build specs
					var specs=''; if(product.specs && product.specs.length) for(let spec of product.specs) specs+=(specs=='' ? '' : ', ') + '<div class="order_product_spec">' + spec.name + (parseFloat(spec.price)==0 ? '' : ' <div class="order_product_spec_price">' + StrFloat(spec.price) + '&euro;</div>') + '</div>';
					// Build quantity options
					var quantity_options=''; for(var i=0;i<=9;i++) quantity_options+='<option value="' + i + '" ' + (i==product.quantity ? 'selected="selected"' : '') + '>' + i + '</option>\n';
					// Build product row
					html_unordered+='\
						<div class="order_product_row" product_id="' + product.product_id + '" product_row_id="' + product.row_id + '">\
							<div class="order_product_icon" style="background-image: url(' + (product.product.icon==1 ? '<?=ImagesDataUrl()?>PRODUCT.icon.' + product.product_id : '<?=ImagesUrl()?>product_placeholder.png') + '); ' + (product.product.icon==1 ? '' : 'border: 1px solid #eee;') + '"></div>\
							<div class="order_product_texts">\
							<div class="order_product_title">' + product.product.name + (parseFloat(product.price_specs)>0 ? ' <div class="order_product_main_price">' + StrFloat(product.price) + '&euro;</div>' : '') + '</div>\
								<div class="order_product_specs">' + specs + '</div>\
								<div class="order_product_comment">' + product.comment + '</div>\
							</div>\
							<div class="order_product_quantity_container">\
								<select class="order_product_quantity">' + quantity_options + '</select>\
								<div class="order_product_quantity_unit">' + product.unit_name + '</div>\
							</div>\
							<div class="order_product_price_container">\
								<div class="order_product_price">' + StrFloat(product.price_total) + '&euro;</div>\
								<div class="order_product_price_unit">/' + (product.unit_quantity==1 ? '' : product.unit_quantity + ' ') + product.unit_name + '</div>\
							</div>\
							<div class="order_product_amount">' + StrFloat(product.amount) + '&euro;</div>\
						</div>\
					';
					total_amount_unordered+=parseFloat(product.amount);
				}
				// Fill unordered
				if(html_unordered=='') {
					$('#unordered_container').hide();
					$('#order_send_products').hide();
				} else {
					$('#unordered_container').show();
					$('#unordered_list').html(list_header + html_unordered + list_footer.replace('#CANCEL_PRODUCTS#', '').replace('#TOTAL_AMOUNT#', StrFloat(total_amount_unordered)));
					$('#order_send_products').show();
				}

				// Build ordered HTML
				var html_ordered='', total_amount_ordered=0;
				if(submitted_order.products && submitted_order.products.length) for(let product of submitted_order.products) {
					// Build specs
					var specs=''; if(product.specs && product.specs.length) for(let spec of product.specs) specs+=(specs=='' ? '' : ', ') + '<div class="order_product_spec">' + spec.name + (parseFloat(spec.price)==0 ? '' : ' <div class="order_product_spec_price">' + StrFloat(spec.price) + '&euro;</div>') + '</div>';
					// Build product row
					html_ordered+='\
						<div class="order_product_row" product_id="' + product.product_id + '" product_row_id="' + product.row_id + '">\
							<div class="order_product_icon" style="background-image: url(' + (product.product.icon==1 ? '<?=ImagesDataUrl()?>PRODUCT.icon.' + product.product_id : '<?=ImagesUrl()?>product_placeholder.png') + '); ' + (product.product.icon==1 ? '' : 'border: 1px solid #eee;') + '"></div>\
							<div class="order_product_texts">\
								<div class="order_product_title">' + product.product.name + (parseFloat(product.price_specs)>0 ? ' <div class="order_product_main_price">' + StrFloat(product.price) + '&euro;</div>' : '') + '</div>\
								<div class="order_product_specs">' + specs + '</div>\
								<div class="order_product_comment">' + product.comment + '</div>\
							</div>\
							<div class="order_product_quantity_container">\
								<div class="order_product_quantity">' + product.quantity + '</div>\
								<div class="order_product_quantity_unit">' + product.unit_name + '</div>\
							</div>\
							<div class="order_product_price_container">\
								<div class="order_product_price">' + StrFloat(product.price_total) + '&euro;</div>\
								<div class="order_product_price_unit">/' + (product.unit_quantity==1 ? '' : product.unit_quantity + ' ') + product.unit_name + '</div>\
							</div>\
							<div class="order_product_amount">' + StrFloat(product.amount) + '&euro;</div>\
						</div>\
					';
					total_amount_ordered+=parseFloat(product.amount);
				}
				// Fill ordered
				if(html_ordered=='') {
					$('#ordered_container').hide();
				} else {
					$('#ordered_container').show();
					$('#ordered_list').html(list_header + html_ordered + list_footer.replace('#CANCEL_PRODUCTS#', '<div id="order_footer_cancel_order_items" class="order_footer_button"><span class="glyphicon glyphicon-trash"></span> <?=Strings::Get('cancel_items')?></div>').replace('#TOTAL_AMOUNT#', StrFloat(total_amount_ordered)));
				}

				// Show no products if orders are empty
				if(html_ordered=='' && html_unordered=='') $('#page_content').html('<div id="no_products_in_order"><?=Strings::Get('order_no_products_in_order')?></div>');
			}

			$(document).ready(function(){

				// View product
				$('#page_content').on('click', '.order_product_icon', function(e){
					e.preventDefault(); e.stopPropagation();
					const product_id=$(this).closest('.order_product_row').attr('product_id');
					if(product_id==undefined || product_id=='') return;
					window.location='<?=BaseUrl()?>product/view/?id=' + product_id;
				});

				// View product
				$('#unordered_list').on('change', '.order_product_quantity', function(e){
					e.preventDefault(); e.stopPropagation();
					const product_row_id=$(this).closest('.order_product_row').attr('product_row_id');
					if(product_row_id==undefined || product_row_id=='') return;
					const product_from_order=new_order.GetProductByRowId(product_row_id);
					if(!product_from_order) return;
					const product=new OrderProduct(product_from_order);
					if($(this).val()=='0' && !confirm('<?=Strings::Get('order_remove_item_confirm')?>\n' + product.product.name)) {
						$(this).val(parseFloat(product.quantity));
						return;
					}
					if($(this).val()=='0') {
						new_order.RemoveProduct(product);
					} else {
						product.quantity=parseFloat($(this).val());
						new_order.UpdateProduct(product);
					}
					FillProducts();
				});

				// Send products
				$('#order_send_products').click(function(e){
					e.preventDefault(); e.stopPropagation();
					ShowLoader();
					const order_additional=new Order(new_order);
					order_additional.MakeAdditional();

					Post('<?=API_URL?>',
						{ controller: 'customer', action: 'additional_order', order: JSON.stringify(order_additional) },
						function(response) {
							HideLoader();
							if(response==undefined || response==null || response.status==undefined) {
								alert('<?=Strings::Get('error_invalid_server_response')?>');
							} else if(response.status) {
								if(response.data) {
									new_order.id=response.data.id;
									new_order.products.length=0;
									new_order.Reconstruct(new_order);
									new_order.Save();
									window.location.reload();
								}
							} else {
								ShowModal('<?=Strings::Get('additional_order')?>', response.message ?? '<?=Strings::Get('error_sending_additional_order')?>');
							}
						}
					);
				});

				// Refresh ordered
				$('#ordered_header_refresh').click(function(e){
					e.preventDefault(); e.stopPropagation();
					GetSubmittedOrder();
				});

				// Cancel order items
				$('#ordered_container').on('click', '#order_footer_cancel_order_items', function(e){
					e.stopPropagation(); e.preventDefault();
					$('#view_order_products_to_be_canceled_container').remove();
					window.order=submitted_order;
					Post('<?=API_URL?>',
						{ controller: 'order', action: 'order_products_to_be_canceled_view', id: submitted_order.id },
						function(response) {
							HideLoader();
							if(response==undefined || response==null || response.status==undefined) {
								alert('<?=Strings::Get('error_invalid_server_response')?>');
							} else if(response.status) {
								if(response.data) {
									$('body').append(response.data);
								}
							} else {
								ShowModal('<?=Strings::Get('error_loading_product_list')?>', response.message ?? '<?=Strings::Get('error_loading_product_list')?>');
							}
						}
					);
				});
			});
		</script>
	</body>
</html><? exit;