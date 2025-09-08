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

// Get product
$product_id=GetRequest('id');
if(empty($product_id)) Redirect();
$product=new Product;
if(!$product->Load(['id' => $product_id])) Redirect();
if(!$product->visible || !$product->saleable) Redirect();
$product->GetData();

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

// Print header
PrintPageHead($product->name);

?>
	<style>
		body { background-color: rgb(244, 244, 244); }
		#page_content { position: relative; display: block; color: #333; overflow: hidden; }
		#page_back { position: fixed; right: 10px; top: 10px; width: 38px; height: 38px; padding: 10px; background-color: white; box-shadow: 0 1px 2px gray; border-radius: 100px; z-index: 10; }

		.hidden_product_image { display: none; }
		#product_images { position: relative; display: block; overflow: hidden; }
		#product_images img { position: absolute;  left: 0; top: 0; width: 100%; height: 200px; object-fit: cover; scale: 1; opacity: 0; transition: opacity 3s linear, scale 7s linear; }
		.product_image_current { opacity: 1 !important; scale: 1.1 !important;}
		.product_image_previous { opacity: 0 !important; scale: 1.1 !important;}

		#product_list { position: relative; display: block; padding: 10px; overflow: hidden; }

		#product_specs_list_header { padding: 5px 0; font-weight: bold; }
		.product_spec_row { display: flex; flex-direction: row; gap: 10px; margin-bottom: 10px; padding: 10px; background-color: white; border-radius: 4px; cursor: pointer; overflow: hidden; }
		.product_spec_name { flex: 1; }
		.product_spec_price { font-weight: bold; text-align: right; }

		#product_header { position: fixed; left: 0; right: 0; padding: 20px; background-color: white; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; box-shadow: 0 1px 2px gray; overflow: hidden; }
		#product_name { padding-bottom: 5px; font-size: large; font-weight: bold; }
		#product_description { text-align: justify; }
		#product_price_row { display: flex; flex-direction: row; padding-top: 15px; }
		#product_price { font-size: large; font-weight: bold; }
		#product_price_per_unit { font-size: small; font-weight: bold; line-height: 2.2em; }
		#product_select_unit_label { display: none; flex: 1; font-size: normal; text-align: right; line-height: 2em; }
		#product_unit { display: none; margin-left: 5px; color: #333; font-weight: bold; cursor: pointer; }

		#product_comment_label { margin-top: 20px; padding: 5px 0; color: #333; font-weight: bold; }
		#product_comment { width: 100%; padding: 10px; color: #333; background-color: white; border: none; border-radius: 4px; overflow: hidden; }

		#product_footer { position: fixed; display: flex; flex-direction: row; gap: 5px; left: -15px; right: -15px; bottom: 0; padding: 20px; background-color: white; overflow: hidden; }
		#product_footer div { display: block; width: 11px; height: 32px; color: rgb(112, 112, 112); background-color: rgb(244, 244, 244); border-radius: 4px; font-size: 2em; line-height: 1em; text-align: center; /*overflow: hidden;*/ }
		#quantity_text { width: 65px !important; padding-top: 7px !important; font-weight: bold !important; background-color: transparent !important; font-size: medium !important; color: black !important; }
		#quantity_order { flex: 1; width: unset !important; padding-top: 5px !important; color: white !important; background-color: #303030 !important; border: 1px solid var(--quantity_order-border) !important; font-size: medium !important; }

		#product_added_to_order { display: none; position: fixed; left: 0; right: 0; bottom: 0; padding: 20px; font-size: x-large; color: white; background-color: orange; text-align: center; overflow: hidden; }
	</style>
	<body>
		<div id="page_content">
			<div id="product_images"></div>
			<div id="product_list">
				<div id="product_specs_list"></div>
				<div id="product_comment_label"><?=Strings::Get('product_comment')?></div>
				<textarea id="product_comment"></textarea>
			</div>
			<div id="product_header">
				<div id="product_name"></div>
				<div id="product_description"></div>
				<div id="product_price_row">
					<div id="product_price"></div>
					<div id="product_price_per_unit"></div>
					<div id="product_select_unit_label"><?=Strings::Get('select_unit')?>: </div>
					<select id="product_unit"></select>
				</div>
			</div>
			<div id="product_footer">
				<div id="quantity_remove">-</div>
				<div id="quantity_text"></div>
				<div id="quantity_add">+</div>
				<div id="quantity_order"><?=Strings::Get('add_to_order')?></div>
			</div>
			<div id="product_added_to_order" onclick="return false;"><?=Strings::Get('product_added_to_order')?> <span class="glyphicon glyphicon-ok"></span></div>
			<img id="page_back" src="<?=IMAGES_URL?>close.png" onclick="history.back();" />
		</div>
		<script>
			const company=new Company(<?=json_encode($company)?>);
			const table=new Table(<?=json_encode($table)?>);
			const product=new Product(<?=json_encode($product)?>);
			const submitted_order=new Order(<?=json_encode($submitted_order)?>);
			const new_order=new Order(sessionStorage.getItem('order') ? JSON.parse(sessionStorage.getItem('order')) : <?=json_encode($new_order)?>);

			var selected_unit=product.units.length>0 ? product.units[0] : null;
			var header_top, header_height, current_index=0;
			product.quantity=1;

			LoadProductImages();
			FillData();
			RefreshQuantity();
			WindowScrolled();

			function RefreshQuantity() {
				$('#quantity_text').text(product.quantity + (selected_unit ? ' ' + selected_unit.name : ''));
			}

			function LoadProductImages(){
				product.loaded_images=0;
				product.images=[];
				if(product.image=='1') product.images.push('<?=IMAGES_DATA_URL?>PRODUCT.image.' + product.id);
				if(product.image1=='1') product.images.push('<?=IMAGES_DATA_URL?>PRODUCT.image1.' + product.id);
				if(product.image2=='1') product.images.push('<?=IMAGES_DATA_URL?>PRODUCT.image2.' + product.id);

				// Fill temporary images
				if(product.images.length) {
					$('#product_images').css('height', '200px');
					var html='';
					for(var i=0;i<product.images.length;i++) html+='<img class="hidden_product_image" src="' + product.images[i] + '" onload="ProductImageLoaded();" />\n';
					$('body').append(html);
					$('#product_images').html('<img src="<?=IMAGES_URL?>loading_small.gif" style="opacity: 1;" />');
				} else {
					$('#product_images').css('height', '0px');
				}
			}

			function ProductImageLoaded(){
				product.loaded_images++;
				if(product.loaded_images==product.images.length) {
					$('.hidden_product_image').remove();
					$('#product_images').html('<img class="product_image product_image_current" src="' + product.images[0] + '" />');
					if(product.images.length==1) return;
					var current_index=0;
					setInterval(function() {
						current_index++; if(current_index>=product.images.length) current_index=0;
						$('.product_image').removeClass('product_image_current').removeClass('product_image_previous');
						$('.product_image:last-child').addClass('product_image_previous');
						$('#product_images').append('<img class="product_image" src="' + product.images[current_index] + '" />');
						if($('.product_image').length>=3) $('.product_image:first-child').remove();
						setTimeout(function() { $('.product_image:last-child').addClass('product_image_current'); }, 500);
					}, 7000);
				}
			}

			function FillData() {
				var html;

				// Fill product header
				$('#product_name').text(product.name);
				$('#product_description').text(product.description);
				$('#product_price').html(selected_unit ? selected_unit.price + '&euro;' : '');
				$('#product_price_per_unit').html(selected_unit ? '/' + (selected_unit.quantity==1 ? '' : selected_unit.quantity) + selected_unit.name : '');
				$('#quantity_text').text(product.quantity);

				// Fill product units
				html='';
				if(product.units.length>1) {
					for(let unit of product.units) {
						if(unit.saleable!='1') continue;
						html+='<option value="' + unit.id + '" ' + (selected_unit && selected_unit.id==unit.id ? 'selected="selected"' : '') + '>' + unit.name + '</option>\n';
					}
				}
				if(html!='') {
					$('#product_unit').html(html).show();
					$('#product_select_unit_label').show();
				}

				// Fill product specs
				html='';
				if(product.specs.length) {
					for(let spec of product.specs) {
						html+='\
							<div class="product_spec_row">\
								<input type="checkbox" class="product_spec_check" spec_id="' + spec.id + '" spec_price="' + spec.price + '">\
								<div class="product_spec_name">' + spec.name + '</div>\
								<div class="product_spec_price">' + (spec.price>0 ? spec.price + '&euro;' : '') + '</div>\
							</div>\n';
					}
				}
				if(html!='') $('#product_specs_list').html('<div id="product_specs_list_header"><?=Strings::Get('select_specs')?></div>\n' + html);

				// Fix heights
				header_height=$('#product_header').outerHeight();
				header_top=$('#product_images').outerHeight();
				$('#product_header').css('top', header_top + 'px');
				$('#product_list').css('margin-top', (header_height + 10) + 'px');
				$('#product_list').css('margin-bottom', ($('#product_footer').outerHeight() + 10) + 'px');
			}

			function WindowScrolled() {
				const body_scroll=$(window).scrollTop();
				const header_new_top=Math.max(0, header_top - body_scroll);
				$('#product_header').css('top', header_new_top + 'px');
			}

			$(document).ready(function(){

				$('#quantity_remove').click(function(e){
					e.preventDefault(); e.stopPropagation();
					product.quantity-=selected_unit.is_integer ? 1 : 0.1;
					if(product.quantity<=0) product.quantity=selected_unit.is_integer ? 1 : 0.1;
					RefreshQuantity();
				});

				$('#quantity_add').click(function(e){
					e.preventDefault(); e.stopPropagation();
					product.quantity+=selected_unit.is_integer ? 1 : 0.1;
					if(product.quantity>99) product.quantity=99;
					RefreshQuantity();
				});

				$('#quantity_order').click(function(e){
					e.preventDefault(); e.stopPropagation();
					if(product.quantity<=0) return;

					// Get selected specs
					const specs=[], specs_ids=[];
					$('#product_specs_list .product_spec_check').each(function(){
						if($(this).is(':checked')) {
							const spec_id=$(this).attr('spec_id');
							const spec_price=$(this).attr('spec_price');
							if(spec_id==undefined || spec_id=='') return;
							const spec=product.GetSpecById(spec_id);
							if(spec) {
								specs.push(spec);
								specs_ids.push(spec_id);
							}
						}
					});
					specs_ids.sort();

					var added_to_order=false;
					var row_index=-1;

					// Create order line(s)
					for(var i=selected_unit.is_integer ? 1 : product.quantity;i<=product.quantity;i++) {
						row_index++;
						const order_product=new OrderProduct();
						order_product.order_id=new_order.id;
						order_product.product=new Product({...product});
						order_product.product_id=product.id;
						order_product.unit=new AppClass({...selected_unit});
						order_product.unit_id=selected_unit.id;
						order_product.unit_price=selected_unit.price;
						order_product.unit_name=selected_unit.name;
						order_product.unit_quantity=selected_unit.quantity;
						order_product.unit_is_integer=selected_unit.is_integer;
						order_product.price=selected_unit.price;
						order_product.quantity=selected_unit.is_integer ? 1 : product.quantity;
						order_product.comment=$('#product_comment').val();
						order_product.row_id=-parseInt(new Date().valueOf() + row_index);

						// Manage specs
						order_product.specs=specs;
						order_product.specs_ids=specs_ids;
						order_product.specs_ids_str=specs_ids.join(',');

						// Reconstruct
						order_product.Reconstruct();

						// Add product to order
						added_to_order=new_order.AddProduct(order_product);
					}

					if(added_to_order) {
						$('#product_added_to_order').css('min-height', $('#product_footer').height() + 'px').fadeIn('slow', function() {
							$('#product_added_to_order'). fadeOut('slow');
							window.location=BASE_URL;
						});
					}

					/*
					// Create order_product from product
					const order_product=new OrderProduct(product);
					order_product.amount=0;
					order_product.comment=$('#product_comment').val();
					order_product.delivered=false;
					order_product.discount=0;
					order_product.order_id=order.id;
					order_product.paid=false;
					order_product.price=selected_unit.price;
					order_product.price_specs=0;
					order_product.price_total=0;
					order_product.product_id=product.id;
					order_product.sent=false;
					order_product.specs=[];
					order_product.specs_ids=[];
					order_product.specs_ids_str='';
					order_product.unit=selected_unit;
					order_product.unit_id=selected_unit.id;
					order_product.unit_name=selected_unit.name;
					order_product.FixNumerics();

					// Add specs
					$('#product_specs_list .product_spec_check').each(function(){
						if($(this).is(':checked')) {
							const spec_id=$(this).attr('spec_id');
							const spec_price=$(this).attr('spec_price');
							if(spec_id==undefined || spec_id=='') return;
							const spec=product.GetSpecById(spec_id);
							if(spec) {
								order_product.price_specs+=parseFloat(spec.price);
								order_product.specs.push(spec);
								order_product.specs_ids.push(spec_id);
							}
						}
					});
					order_product.specs_ids.sort();
					order_product.specs_ids_str='#' + order_product.specs_ids.join('#') + '#';
					order_product.amount=Math.round((order_product.price + order_product.price_specs) * order_product.quantity * ((100 - order_product.discount) / 100), 2);
					if(order.AddProduct(order_product)) {
						$('#product_added_to_order').css('min-height', $('#product_footer').height() + 'px').fadeIn('slow', function() {
							setTimeout(function() { $('#product_added_to_order'). fadeOut('slow'); }, 3000);
						});
					}
					*/

					product.quantity=1;
					product.comment='';
					FillData();
					$('#product_comment').val('');
				});

				$('#product_specs_list').on('click', '.product_spec_name', function(){
					const check=$(this).closest('.product_spec_row').find('.product_spec_check')[0];
					const is_checked=check.checked;
        			$(check).prop('checked', !is_checked);
				});

				$('#product_unit').change(function(){
					const unit_id=$(this).val();
					for(let unit of product.units) {
						if(unit.id==unit_id) {
							selected_unit=unit;
							$('#product_price').html(selected_unit ? selected_unit.price + '&euro;' : '');
							$('#product_price_per_unit').html(selected_unit ? '/' + (selected_unit.quantity==1 ? '' : selected_unit.quantity) + selected_unit.name : '');
							RefreshQuantity();
							return;
						}
					}
				});

				$(window).scroll(WindowScrolled);
			});
		</script>
	</body>
</html><? exit;