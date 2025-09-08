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
$company->GetMenu();
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

// Print header
PrintPageHead(Strings::Get('visitor_page_title_menu'));

?>
	<style>
		#page_content { position: fixed; top: 70px; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; gap: 5px; overflow: hidden; }
		#top_categories { display: block; width: 100%; white-space: nowrap; overflow: auto hidden; }
		.top_category { display: inline-block; margin-right: 10px; padding: 5px 10px; font-size: large; color: black; background-color: white; background-size: 30px 100%; background-repeat: no-repeat; border: 1px solid #eee; border-radius: 10px; cursor: pointer; overflow: hidden; transition: 0.3s; }
		.top_category:hover { background-color: #ccc; }
		.top_category.has_icon { padding-left: 40px; }
		.top_category.selected { color: white; background-color: black; color: white; }

		#page_list { flex: 1; padding: 5px; overflow: hidden auto;}

		.list_category { display: block; margin-bottom: 10px; overflow: hidden; }
		.list_category_name_contanier { margin: 0 5px; overflow: hidden; }
		.list_category_name_contanier.list_category_name_with_image { height: 70px; border-radius: 4px; background-position: right; background-size: cover; background-repeat: no-repeat; }
		.list_category_name { display: block; padding: 5px; color: black; font-size: large; font-weight: bold; border-bottom: 1px solid #ccc;  }
		.list_category.root_parent .list_category_name.root_parent { background-color: #e0e0e0; border-radius: 4px; }
		.list_category_name.list_category_name_with_image { height: 70px; background-image: linear-gradient(to right, white, transparent); text-shadow: 0px 0px 5px white; border-bottom: none; }
		.list_category_image { display: block; padding: 5px; color: black; font-size: large; font-weight: bold; border-bottom: 1px solid #ccc; }
		.list_category_products { overflow: hidden; }
		.list_category_product { display: flex; flex-direction: row; width: 100%; height: 130px; padding: 15px; border-bottom: 1px solid #eee; overflow: hidden; }
		.list_category_product_left { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
		.list_category_product_name { display: block; font-weight: bold;  overflow: hidden; }
		.list_category_product_description { flex: 1; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; margin: 5px 0; overflow: hidden; }
		.list_category_product_price { display: block; font-size: large; overflow: hidden; }
		.list_category_product_right { display: block; position: relative; width: 112px; height: 100%; overflow: hidden; }
		.list_category_product_icon { display: block; position: absolute; left: 0; top: 0; width: 100%; height: 100%; border-radius: 5px; background-size: cover; overflow: hidden; }
		.list_category_product_icon.invisible { opacity: 0; }
		.list_category_product_button { display: block; position: absolute; bottom: 5px; width: 32px; height: 32px; color: rgb(112, 112, 112); background-color: rgb(244, 244, 244); border-radius: 4px; font-size: 2em; line-height: 1em; text-align: center; cursor: pointer; overflow: hidden; }
		.list_category_product_remove { left: 5px; }
		.list_category_product_add { right: 5px; }
		.list_category_product_quantity { display: block; position: absolute; left: 40px; bottom: 5px; width: calc( 100% - 80px); height: 32px; line-height: 32px; color: #333; background-color: white; border-radius: 4px; text-align: center; overflow: hidden; }

		#menu_view_order { display: none; width: 100%; padding: 10px; color: black; background-color: var(--quantity_order-bg) ; border: 1px solid var(--quantity_order-border) ; border-radius: 8px 8px 0 0; text-align: center; cursor: pointer;  font-weight: bold;}
	</style>
	<body>
		<? $selected_menu='menu'; include 'view_header.php' ?>
		<div id="page_content">
			<div id="top_categories"></div>
			<div id="page_list"></div>
			<div id="menu_view_order"><?=Strings::Get('menu_view_order')?></div>
		</div>
		<script>
			const company=new Company(<?=json_encode($company)?>);
			const table=new Table(<?=json_encode($table)?>);
			const submitted_order=new Order(<?=json_encode($submitted_order)?>);
			const new_order=new Order(sessionStorage.getItem('order') ? JSON.parse(sessionStorage.getItem('order')) : <?=json_encode($new_order)?>);
			const list_categories_positions={};
			var scroll_by_click=false;

			FillCategories();
			RefreshViewOrder();
			new_order.RefreshBadges();

			function FillCategories() {
				// Build page top categories HTML
				var html='';
				for(let category of company.categories) {
					if(!category.has_products) continue;
					html+='<div class="top_category ' + (category.icon=='1' ? 'has_icon' : '') + '" category_id="' + category.id + '" ' + (category.icon=='1' ? 'style="background-image: url(<?=IMAGES_DATA_URL?>PRODUCT_CATEGORY.icon.' + category.id + ');"' : '') + '>' + category.name + '</div>\n';
				}
				$('#top_categories').html(html);

				// Build list categories and products HTML
				html='';
				for(let category of company.categories) {
					html+=FillListCategory(category);
				}
				$('#page_list').html(html);
			}

			function FillListCategory(category) {
				if(!category.has_products) return '';
				var html='\
					<div class="list_category ' + (category.parent_id==-1 ? 'root_parent' : '')  + '" category_id="' + category.id + '">\
						<div class="list_category_name_contanier ' + (category.image==1 ? 'list_category_name_with_image' : '') + '" ' + (category.image==1 ? 'style="background-image: url(<?=IMAGES_DATA_URL?>PRODUCT_CATEGORY.image.' + category.id + ');"' : '') + '>\
							<div class="list_category_name ' + (category.parent_id==-1 ? 'root_parent' : '')  + ' ' + (category.image==1 ? 'list_category_name_with_image' : '') + '" category_id="' + category.id + '">' + category.name + '</div>\
						</div>\
						<div class="list_category_products">\
				';
				for(let product of category.products) {
					const product_quantities=new_order.GetProductQuantity(product.id);
					html+='\
							<div class="list_category_product" product_id="' + product.id + '">\
								<div class="list_category_product_left">\
									<div class="list_category_product_name">' + product.name + '</div>\
									<div class="list_category_product_description">' + product.description + '</div>\
									<div class="list_category_product_price">' + product.basic_unit_price + '&euro;<small>/' + (product.basic_unit_quantity==1 ? '' : product.basic_unit_quantity + ' ') + product.unit_name + '</small></div>\
								</div>\
								<div class="list_category_product_right">\
									<div class="list_category_product_icon ' + (product.icon==1 ? '' : 'invisible') + '" style="background-image: url(' + (product.icon==1 ? '<?=ImagesDataUrl()?>PRODUCT.icon.' + product.id : '<?=ImagesUrl()?>product_placeholder.png') + '); ' + (product.icon==1 ? '' : 'border: 1px solid #eee;') + '"></div>\
									<div class="list_category_product_remove list_category_product_button" style="display: none;">-</div>\
									<div class="list_category_product_quantity" ' + (product_quantities.quantity_to_order==0 ? 'style="display: none;"' : '') + '>' + product_quantities.quantity_to_order + '</div>\
									<div class="list_category_product_add list_category_product_button" style="display: none;">+</div>\
								</div>\
							</div>\
					';
				}
				html+='	</div>';
				if(category.subcategories) for(let subcategory of category.subcategories) html+=FillListCategory(subcategory);
				html+='</div>';
				return html;
			}

			function RefreshViewOrder() {
				if(new_order.GetUnorderedQuantity()) $('#menu_view_order').show(); else $('#menu_view_order').hide();
			}

			function MeasureCategories() {
				var last_id='', last_top=0;
				$('.list_category[category_id]').each(function(){
					const category_id=$(this).attr('category_id');
					if(Empty(category_id)) return;
					const top=$(this).position().top;
					if(list_categories_positions['category_' + last_id]) list_categories_positions['category_' + last_id].top_end=top-1;
					list_categories_positions['category_' + category_id]={ name: $('.list_category_name', this).text(), top_start: top, top_end: top + $(this).height() };
					last_top=top+1;
					last_id=category_id;
				});
			}

			function StoreLastScrollTop() {
				$.cookie('menu_scroll_top', $('#page_list').scrollTop(), { expires: 1 });
			}

			$(document).ready(function(){

				MeasureCategories();

				$('#page_list').scrollTop(<?=isset($_COOKIE) && isset($_COOKIE['menu_scroll_top']) ? $_COOKIE['menu_scroll_top'] : '0'?>);

				// View category
				$('#top_categories').on('click', '.top_category', function(e){
					e.preventDefault(); e.stopPropagation();
					const category_id=$(this).attr('category_id');
					if(category_id==undefined || category_id=='') return;
					elm_top=$('.list_category[category_id="' + category_id + '"]');
					if(elm_top.length) {
						$('.top_category').removeClass('selected');
						$(this).addClass('selected');
						scroll_by_click=true;
						$('#page_list').stop().animate({ scrollTop: list_categories_positions['category_' + category_id].top_start - 50 }, 500, function() { scroll_by_click=false; });
					}
				});

				// View product
				$('#page_list').on('click', '.list_category_product', function(e){
					e.preventDefault(); e.stopPropagation();
					const product_id=$(this).attr('product_id');
					if(product_id==undefined || product_id=='') return;
					StoreLastScrollTop();
					window.location='<?=BaseUrl()?>product/view/?id=' + product_id;
				});

				// View order
				$('#menu_view_order').click(function(e){
					e.preventDefault(); e.stopPropagation();
					window.location='<?=BaseUrl()?>order/view';
				});

				// List scrolled
				var last_category_id='';
				$('#page_list').scroll(function(){
					if(scroll_by_click) return;
					const scroll_top=$(this).scrollTop();
					var category_id='';
					for(let [key, category_tops] of Object.entries(list_categories_positions)) if(scroll_top>=category_tops.top_start-10 && scroll_top<=category_tops.top_end-10) { category_id=key.replace('category_', ''); break; }
					if(category_id!='' && last_category_id!=category_id) {
						elm_top=$('.top_category[category_id="' + category_id + '"]');
						if(elm_top.length) {
							$('.top_category').removeClass('selected');
							$(elm_top[0]).addClass('selected');
							$('#top_categories').stop().animate({ scrollLeft: $(elm_top[0]).position().left }, 100);
						}
					}
					last_category_id=category_id;
				});

			});
		</script>
	</body>
</html><? exit;