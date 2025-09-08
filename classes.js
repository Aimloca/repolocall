class Company extends AppClass {

	constructor(params={}) {
		super(params);

		this.categories_flat=[];
		if(this.categories!=undefined && this.categories.length) {
			for(let sub of this.categories) {
				if(sub.active!='1' || sub.visible!='1') continue;
				this.categories_flat=this.categories_flat.concat(sub);
				this.categories_flat=this.categories_flat.concat(this.GetCategorySubcategories(sub));
			}
		}

		this.products=[];
		if(this.categories!=undefined && this.categories.length) {
			for(let sub of this.categories) {
				if(sub.active!='1' || sub.visible!='1') continue;
				this.products=this.products.concat(this.GetCategoryProducts(sub));
			}
		}
	}

	GetCategorySubcategories(category) {
		var subcategories=[];
		if(category.subcategories!=undefined && category.subcategories.length) for(let sub of category.subcategories) {
			if(sub.active!='1' || sub.visible!='1') continue;
			subcategories=subcategories.concat(sub);
			subcategories=subcategories.concat(this.GetCategorySubcategories(sub));
		}
		return subcategories;
	}

	GetCategoryProducts(category) {
		var products=[];
		if(category.products!=undefined && category.products.length) for(let product of category.products) {
			if(product.saleable!='1' || product.visible!='1') continue;
			products.push(product);
		}
		if(category.subcategories!=undefined && category.subcategories.length) for(let sub of category.subcategories) {
			if(sub.active!='1' || sub.visible!='1') continue;
			products=products.concat(this.GetCategoryProducts(sub));
		}
		return products;
	}

	GetCategoryById(id, parent_category='') {
		var found=null;
		if(parent_category=='') {
			if(this.categories!=undefined && this.categories.length) {
				for(let category of this.categories) {
					if(category.id==id) return category;
					found=this.GetCategoryById(id, category);
					if(found!=null) return found;
				}
			}
		} else {
			if(parent_category.id==id) return parent_category;
			if(parent_category.categories!=undefined && parent_category.categories.length) {
				for(let category of parent_category.categories) {
					if(category.id==id) return category;
					found=this.GetCategoryById(id, category);
					if(found!=null) return found;
				}
			}
		}
		return null;
	}

	GetProductById(product_id) {
		if(product_id==undefined || product_id=='' || product_id==null || !this.products || this.products.length==0) return null;
		for(let product of this.products) if(product.id==product_id) return product;
		return null;
	}
}

class Product extends AppClass {

	GetSpecById(spec_id) {
		if(this.specs && this.specs.length) for(let spec of this.specs) if(spec.id==spec_id) return spec;
		return null;
	}
}

class OrderProduct extends AppClass {
	constructor(params={}) {
		super(params);
		if(!params.keep_all_fields) {
			var keep_only_fields=[];
			for(const [field_name, field] of Object.entries(DB_TABLES.PRODUCT.fields)) keep_only_fields.push(field_name);
			for(const [field_name, field] of Object.entries(DB_TABLES.ORDER_PRODUCT.fields)) if(keep_only_fields.indexOf(field_name)<0) keep_only_fields.push(field_name);
			for(const [field_name, field] of Object.entries(DB_TABLES.UNIT.fields)) if(keep_only_fields.indexOf(field_name)<0) keep_only_fields.push(field_name);
			keep_only_fields=keep_only_fields.concat(['row_id', 'order_id', 'product_name', 'name', 'description', 'unit_name', 'specs', 'specs_ids', 'specs_ids_str', 'unit_quantity', 'unit_is_integer', 'icon']);
			for(let [key, value] of Object.entries(this)) if(keep_only_fields.indexOf(key)<0) delete this[key];
		}
		if(this.row_id==undefined || this.row_id==null || this.row_id=='') this.row_id='-' + new Date().valueOf();
		if((this.company_id==undefined || this.company_id==null || this.company_id=='') && this.product && this.product.company_id) this.company_id=this.product.company_id;
		if(this.sent==undefined || this.sent==null || this.sent=='') this.sent=false;
		if(this.delivered==undefined || this.delivered==null || this.delivered=='') this.delivered=false;
		if(this.paid==undefined || this.paid==null || this.paid=='') this.paid=false;
		if(this.unit_quantity!=undefined) this.unit_quantity=parseFloat(this.unit_quantity);
		if(this.unit_is_integer!=undefined) this.unit_is_integer=parseInt(this.unit_is_integer);
		this.CalculatePrices();
		this.RemoveAppFields();
	}

	CalculatePrices() {
		if(this.quantity==undefined || this.quantity==null || this.quantity=='') this.quantity=1; else this.quantity=parseFloat(this.quantity);
		if(this.discount==undefined || this.discount==null || this.discount=='') this.discount=0; else this.discount=parseFloat(this.discount);
		this.price_specs=0; if(this.specs && this.specs.length) for(let spec of this.specs) this.price_specs+=parseFloat(spec.price);
		this.price_total=parseFloat(this.price) + parseFloat(this.price_specs);
		this.amount=Round(parseFloat(this.price_total) * parseFloat(this.quantity) * ((100 - parseFloat(this.discount)) / 100), 2);
	}
}

class Table extends AppClass {

}

class Order extends AppClass {

	constructor(params={}) {
		super(params);
		if(!this.id) { this.id='-' + document.cookie.match(/PHPSESSID=[^;]+/); this.id=this.id.replace('PHPSESSID=', ''); }
		var order_products=[];
		// Convert products to order products
		if(this.products && this.products.length) for(let product of this.products) {
			const order_product=new OrderProduct(product);
			if(product.row_id) order_product.row_id=product.row_id;
			order_product.order_id=this.id;
			order_product.company_id=this.company_id;
			order_products.push(order_product);
		}
		this.products=order_products;
		this.GetAmounts();
		this.RefreshBadges();
	}

	SetBasics(company, customer, tables) {
		this.company_id=company.id;
		this.company=company;
		this.customer_id=customer.id;
		this.customer=customer;
		this.tables=tables;
		this.tables_ids=tables.join(',');
	}

	UpdatePaid(api_url, on_complete) {
		const that=this;
		Post(api_url,
			{ controller: 'order', action: 'get_paid', id: that.id },
			function(response) {
				if(response && response.status && response.data) {
					for(let product of response.data) {
						for(let this_product of that.products) {
							if(this_product.row_id==product.row_id) {
								this_product.paid=parseFloat(product.paid);
								this_product.paid_amount=parseFloat(product.paid_amount);
								this_product.paid_quantity=parseFloat(product.paid_quantity);
								break;
							}
						}
					}
				}
				if(on_complete) on_complete(response && response.status);
			}
		);
	}

	UpdateProducts(products) {
		if(!products) return;
		this.products=[];
		for(let product of products) this.products.push(new OrderProduct(product));
	}

	Compact() {
		// Manage company
		if(this.company) delete this.company;
		// Manage customer
		if(this.customer) delete this.customer;
		// Manage products and specs
		const product_fields_to_keep=[ 'id', 'product_id', 'unit_id', 'price', 'price_specs', 'quantity', 'discount', 'amount', 'comment', 'sent', 'delivered', 'paid', 'specs', 'specs_ids', 'specs_ids_str' ];
		const spec_fields_to_keep=[ 'id', 'spec_id', 'order_id', 'product_id', 'order_product_row_id', 'order_product_id', 'product_spec_id', 'price' ];
		if(this.products) for(let product of this.products) {
			for(let [field, value] of Object.entries(product)) {
				if(product_fields_to_keep.indexOf(field)<0) delete product[field];
				if(field=='specs') for(let spec of product.specs) {
					for(let [spec_field, spec_value] of Object.entries(spec)) {
						if(spec_fields_to_keep.indexOf(spec_field)<0) delete spec[spec_field];
					}
				}
			}
		}
		// Manage tables
		const table_fields_to_keep=[ 'id', 'company_id', 'room_id', 'reserved', 'occupied' ];
		if(this.tables) for(let table of this.tables) {
			for(let [field, value] of Object.entries(table)) {
				if(table_fields_to_keep.indexOf(field)<0) delete table[field];
			}
		}
	}

	CompactProductLines() {
		// Manage products and specs
		const product_fields_to_keep=[ 'id', 'row_id', 'product_id', 'unit_id', 'price', 'price_specs', 'quantity', 'discount', 'amount', 'comment', 'sent', 'delivered', 'paid', 'specs', 'specs_ids', 'specs_ids_str', 'selected' ];
		const spec_fields_to_keep=[ 'id', 'spec_id', 'order_id', 'product_id', 'order_product_row_id', 'order_product_id', 'product_spec_id', 'price' ];
		if(this.product_lines) for(let product of this.product_lines) {
			for(let [field, value] of Object.entries(product)) {
				if(product_fields_to_keep.indexOf(field)<0) delete product[field];
				if(field=='specs') for(let spec of product.specs) {
					for(let [spec_field, spec_value] of Object.entries(spec)) {
						if(spec_fields_to_keep.indexOf(spec_field)<0) delete spec[spec_field];
					}
				}
			}
		}
	}

	MakeProductsLines(only_unpaid=true) {
		this.product_lines=[];
		var row_index=-1;
		for(let product of this.products) {
			// Check if product is paid
			if(only_unpaid && (product.paid==true || product.paid==1 || product.paid=='1')) continue;
			// Check if product quantity is integer
			if(product.unit_is_integer) {
				for(var q=1;q<=product.quantity;q++) {
					row_index++;
					const product_line=new OrderProduct(product);
					product_line.quantity=1;
					product_line.selected=true;
					product_line.row_index=row_index;
					product_line.CalculatePrices();
					this.product_lines.push(product_line);
				}
			} else {
				row_index++;
				const product_line=new OrderProduct(product);
				product_line.row_index=row_index;
				product_line.to_pay=1;
				product_line.CalculatePrices();
				this.product_lines.push(product_line);
			}
		}
	}

	MakeAdditional() {
		this.Compact();
		const products=[];
		if(this.products) for(let product of this.products) if(!product.sent) products.push(product);
		this.products=products;
	}

	ReadyToPay() {
		this.Compact();
		this.CompactProductLines();
		var tmp=[];
		if(this.product_lines) for(let product of this.product_lines) if(product.selected) tmp.push(product);
		this.product_lines=tmp;
	}

	Save() {
		this.FixNumerics();
		this.GetAmounts();
		sessionStorage.setItem('order', JSON.stringify(this));
	}

	RefreshBadges() {
		this.FixNumerics();
		var unsent_products=0, unpaid_products=0;
		if(this.products && this.products.length) for(let product of this.products) {
			if(!product.sent) unsent_products++;
			if(product.sent && !product.paid) {
				if(product.unit_is_integer)
					unpaid_products+=product.quantity-product.paid_quantity;
				else
					unpaid_products++;
			}
		}
		if($('#header_order_badge').length) {
			$('#header_order_badge').text(unsent_products);
			if(unsent_products>0) $('#header_order_badge').show(); else $('#header_order_badge').hide();
		}
		if($('#header_bill_badge').length) {
			$('#header_bill_badge').text(unpaid_products);
			if(unpaid_products>0) $('#header_bill_badge').show(); else $('#header_bill_badge').hide();
		}
	}

	HasProduct(product) {
		var product_id='';
		if(typeof product==='string' || product instanceof String) product_id=product;
		else if(typeof product==='object' || product instanceof Object) product_id=product.id;
		else if(typeof product==='array' || product instanceof Array) product_id=product[id];
		if(product_id=='') return false;
		if(this.products==undefined || this.products.length==0) return false;
		for(let product of this.products) if(product.id==product_id) return product;
		return false;
	}

	GetUnsentQuantity() {
		var unsent=0;
		if(this.products && this.products.length) for(let product of this.products) if(!product.sent) unsent+=product.quantity;
		return unsent;
	}

	GetProductQuantity(product) {
		const ret={ quantity_to_sent: 0, quantity_sent: 0, quantity_delivered: 0, quantity_paid: 0, quantity_total: 0, multiple_entries: false };
		var product_id='';
		if(typeof product==='string' || product instanceof String) product_id=product;
		else if(typeof product==='object' || product instanceof Object) product_id=product.id;
		else if(typeof product==='array' || product instanceof Array) product_id=product[id];
		if(product_id=='') return ret;
		if(this.products==undefined || this.products.length==0) return ret;
		var product_found=false;
		for(let product of this.products) {
			if(product.id==product_id) {
				ret.quantity_total+=product.quantity;
				if(product.sent) ret.quantity_sent+=product.quantity; else ret.quantity_to_sent+=product.quantity;
				if(product.delivered) ret.quantity_delivered+=product.quantity;
				if(product.paid) ret.quantity_paid+=product.quantity;
				if(product_found) ret.multiple_entries=true;
				product_found=true;
			}
		}
		return ret;
	}

	GetAmounts() {
		this.total_amount=0;
		this.paid_amount=0;
		for(let product of this.products) {
			product.price_specs=0;
			if(product.specs!=undefined && product.specs.length>0) for(let spec of product.specs) product.price_specs+=parseFloat(spec.price);
			product.amount=Round(((parseFloat(product.price) + parseFloat(product.price_specs)) * parseFloat(product.quantity)) * ((100 - parseFloat(product.discount)) / 100), 2);
			this.total_amount+=parseFloat(product.amount);
			if(product.paid) this.paid_amount+=parseFloat(product.amount);
		}
	}

	GetProductByRowId(row_id) {
		if(!this.products || this.products.length==0) return false;
		for(let product of this.products) if(product.row_id==row_id) return product;
		return false;
	}

	UpdateProduct(order_product) {
		if(!(order_product instanceof OrderProduct)) { alert('Invalid product'); return false; }
		if(!order_product.row_id) { alert('Invalid product row_id'); return false; }
		for(var i=0;i<this.products.length;i++) {
			if(order_product.row_id==this.products[i].row_id) {
				order_product.CalculatePrices();
				this.products[i]=order_product;
				this.Save();
				this.RefreshBadges();
			}
		}
	}

	AddProduct(order_product) {
		if(!(order_product instanceof OrderProduct)) { alert('Invalid product'); return false; }
		this.products.push(order_product);
		this.Save();
		//ShowToast(GetString('product_added_to_cart'));
		this.RefreshBadges();
		return true;
	}

	RemoveProduct(order_product) {
		if(!(order_product instanceof OrderProduct)) { alert('Invalid product'); return false; }
		if(!order_product.row_id) { alert('Invalid product row_id'); return false; }
		for(var i=0;i<this.products.length;i++) {
			if(order_product.row_id==this.products[i].row_id) {
				this.products.splice(i, 1);
				this.Save();
				ShowToast(GetString('product_removed_to_cart'));
				this.RefreshBadges();
			}
		}
		return true;
	}
}