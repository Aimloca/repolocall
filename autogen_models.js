/* AppClass model - Start */
class AppClass {
	constructor(params={}) {
		this.Reconstruct(params);
	}

	GetTable() {
		return '';
	}

	SetDefaults() {
		if(!Empty(this.GetTable()) && DB_TABLES[this.GetTable()]) {
			for(let [field, field_data] of Object.entries(DB_TABLES[this.GetTable()].fields)) {
				if(field_data.default!=null) {
					this[field]=field_data.type=='number' || field_data.type=='check' ? (field_data.number_type=='float' ? parseFloat(field_data.default) : parseInt(field_data.default)) : field_data.default;
				}
			}
		}
	}

	Reconstruct(source) {
		const from_what=source==undefined ? this : source;
		this.Update({...from_what});
	}

	Update(params={}) {
		this.SetDefaults();
		if(params!=undefined && params!=null && params!='') {
			if(typeof params==='object' || params instanceof Object) for(let [key, value] of Object.entries(params)) this[key]=value;
			else if(typeof params==='array' || params instanceof Array) for(let key of params) this[key]=params[key];
		}
		this.FixNumerics();
		this.FixLanguageFields();
	}

	RemoveAppFields() {
		const remove=['table', 'controller', 'primary_key', 'primary_key_value', 'predefined_db_fields_values' ];
		for(let field in remove) if(this[field]) delete this[field];
	}

	FixNumerics() {
        FixNumerics(this);
	}

    FixLanguageFields() {
        var cookie_lang=$.cookie('lang');
		if(!cookie_lang || ['en', 'gr', 'ru'].indexOf(cookie_lang)<0) {
			cookie_lang='en';
			$.cookie('lang', cookie_lang, { expires: 7 });
		}
		const cur_lang=cookie_lang;
        const fixed_keys=[];
        for(let [key, value] of Object.entries(this)) {
            if(key.endsWith('_en') || key.endsWith('_gr') || key.endsWith('_ru')) {
                const key_without_lang=key.substring(0, key.length-3);
                if(fixed_keys.indexOf(key_without_lang)<0) {
                    if(Empty(this[key_without_lang]) && !Empty(this[key_without_lang + '_' + cur_lang])) this[key_without_lang]=this[key_without_lang + '_' + cur_lang];
                    fixed_keys.push(key_without_lang);
                }
            }
        }
    }

}
/* AppClass model - End */


/* Company model - Start */
class Company extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'COMPANY';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* Manage tables */
		this.ManageTables();

		/* Manage menu */
		this.ManageMenu();
	}

	ManageTables() {
		this.tables_ids=[];
		this.tables_ids_str='';
		const tmp=[];
		if(!Empty(this.tables)) for(let table of this.tables) {
			const model=new Table(table);
			model.table_id=model.id;
			tmp.push(model);
			this.tables_ids.push(model.id);
		}
		this.tables_ids_str=Empty(this.tables_ids) ? '' : '#' + this.tables_ids.join('#') + '#';
	}

	ManageMenu() {
		/* Get categories */
		const tmp_categories=[];
		this.categories_ids=[];
		if(this.categories && this.categories.length) for(let category of this.categories) {
			const model=new ProductCategory(category);
			tmp_categories.push(model);
			this.categories_ids.push(model.id);
		}
		this.categories=tmp_categories;
		this.categories_ids_str=Empty(this.categories_ids) ? '' : '#' + this.categories_ids.join('#') + '#';
	}
}
/* Product model - End */


/* Order model - Start */
class Order extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'ORDERS';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* if order id is empty, get microtime as id */
		if(Empty(this.id)) { this.id=parseInt('-' + new Date().valueOf()); }

		/* Manage customer */
		this.customer=Empty(this.customer) ? null : new AppClass(this.customer);

		/* Manage tables */
		this.ManageTables();

		/* Manage waiters */
		this.ManageWaiters();

		/* Manage products */
		this.ManageProducts();

		/* Manage payments */
		this.ManagePayments();

		/* Manage amounts */
		this.ManageAmounts();
	}

	ManageTables() {
		const tmp=[];
		this.tables_ids=[];
		if(this.tables && this.tables.length) for(let table of this.tables) {
			const model=new Table(table);
			if(this.tables_ids.indexOf(model.table_id)<0) {
				tmp.push(model);
				this.tables_ids.push(model.table_id);
			}
		}
		this.tables=tmp;
		this.tables_ids_str=Empty(this.tables_ids) ? '' : '#' + this.tables_ids.join('#') + '#';
	}

	ManageWaiters() {
		/* this.waiter_id=null; */
		this.waiters_ids='';
		if(this.waiters && this.waiters.length) for(let waiter of this.waiters) {
			const model=new Waiter(waiter);
			if(this.waiters_ids.indexOf('#' + model.id + '#')<0) this.waiters_ids+=(this.waiters_ids=='' ? '#' : '') + model.id + '#';
		}
	}

	ManageProducts() {
		const tmp=[];
		if(this.products && this.products.length) for(let product of this.products) {
			const model=new OrderProduct(product);
			tmp.push(model);
		}
		this.products=tmp;
	}

	ManagePayments() {
		tmp=[];
		if(this.payments && this.payments.length) for(let payment of this.payments) {
			const model=new AppClass(payment);
			tmp.push(model);
		}
		this.payments=tmp;
	}

    ManageAmounts() {
		this.total_amount=0;
		this.products_amount=0;
		this.products_net_amount=0;
		this.products_vat_amount=0;
		this.paid_amount=0;

		for(let product of this.products) {
			product.price_specs=0;
			if(!Empty(product.specs)) for(let spec of product.specs) product.price_specs+=parseFloat(spec.price);
			product.amount=Round(((parseFloat(product.price) + parseFloat(product.price_specs)) * parseFloat(product.quantity)) * ((100 - parseFloat(product.discount)) / 100), 2);
			product.net_amount=Round(product.amount / (1 + parseFloat(product.vat_percent) / 100), 2);
			product.vat_amount=Round(product.amount - product.net_amount, 2);
			this.products_amount+=parseFloat(product.amount);
			this.products_net_amount+=product.net_amount;
			this.products_vat_amount+=product.vat_amount;
			if(product.paid) this.paid_amount+=parseFloat(product.amount);
		}

		/*
		this.paid_amount=0;
		for(let payment of this.payments) if(payment.completed) this.paid_amount+=parseFloat(payment.amount);
		*/

		this.total_amount=this.products_amount + parseFloat(this.tip_amount);
		this.unpaid_amount=Math.max(0, this.total_amount - this.paid_amount);
	}

	SetBasics(company, customer, tables) {
		return;
		this.company_id=company.id;
		this.company=company;
		this.customer_id=customer.id;
		this.customer=customer;
		this.tables=tables;
		this.tables_ids=[]; if(!Empty(this.tables)) for(let table of this.tables) this.tables_ids.push(table.id);
		this.tables_ids_str=Empty(this.tables_ids) ? '' : '#' + this.tables_ids.join('#') + '#';
	}

	UpdatePaid(api_url, on_complete) {
		const that=this;
		Post(api_url,
			{ controller: 'order', action: 'get_paid', id: that.id },
			function(response) {
				if(response && response.status && response.data) {
					for(let order_product of response.data) {
						for(let this_order_product of that.products) {
							if(this_order_product.row_id==order_product.row_id) {
								this_order_product.paid=parseFloat(order_product.paid);
								this_order_product.paid_amount=parseFloat(order_product.paid_amount);
								this_order_product.paid_quantity=parseFloat(order_product.paid_quantity);
								break;
							}
						}
					}
				}
				if(on_complete) on_complete(response && response.status);
			}
		);
	}

	UpdateProducts(order_products) {
		if(!order_products) return;
		this.products=[];
		for(let order_product of order_products) {
            if(order_product.unit_is_integer) {
                var index=-1;
                for(var q=1;q<=order_product.quantity;q++) {
                    index++;
                    const p=new OrderProduct({...order_product});
                    p.quantity=1;
                    p.row_index=index;
                    p.amount=Round(((parseFloat(p.price) + parseFloat(p.price_specs)) * parseFloat(p.quantity)) * ((100 - parseFloat(p.discount)) / 100), 2);
                    this.products.push(p);
                }
            } else {
                const p=new OrderProduct({...order_product});
                this.products.push(p);
            }
        }
        this.GetAmounts();
	}

	GetProductLines() {
		this.product_lines=[];
		for(let product of this.products) {
            if(product.unit_is_integer) {
                var index=-1;
                for(var q=1;q<=product.quantity;q++) {
                    index++;
                    const p=new OrderProduct({...product});
                    p.quantity=1;
                    p.row_index=index;
                    p.amount=Round(((parseFloat(p.price) + parseFloat(p.price_specs)) * parseFloat(p.quantity)) * ((100 - parseFloat(p.discount)) / 100), 2);
                    this.product_lines.push(p);
                }
            } else {
                const p=new OrderProduct({...product});
                this.product_lines.push(p);
            }
        }
		return this.product_lines;
	}

	Compact() {
		/* Manage company */
		if(this.company) delete this.company;
		/* Manage customer */
		if(this.customer) delete this.customer;
		/* Manage products and specs */
		const product_fields_db=[]; for(let [field, field_data] of Object.entries(DB_TABLES['ORDER_PRODUCT'].fields)) product_fields_db.push(field);
		const product_fields_to_keep=product_fields_db.concat(['ordered', 'selected', 'specs', 'specs_ids', 'specs_ids_str' ]);
		const spec_fields_db=[]; for(let [field, field_data] of Object.entries(DB_TABLES['ORDER_PRODUCT_SPEC'].fields)) spec_fields_db.push(field);
		const spec_fields_to_keep=spec_fields_db.concat([ 'id', 'spec_id', 'order_id', 'product_id' ]);
		if(this.products) for(let order_product of this.products) {
			for(let [field, value] of Object.entries(order_product)) {
				if(product_fields_to_keep.indexOf(field)<0) delete order_product[field];
				if(field=='specs') for(let spec of order_product.specs) {
					for(let [spec_field, spec_value] of Object.entries(spec)) {
						if(spec_fields_to_keep.indexOf(spec_field)<0) delete spec[spec_field];
					}
				}
			}
		}
		/* Manage tables */
		const table_fields_to_keep=[]; for(let [field, field_data] of Object.entries(DB_TABLES['ORDER_PRODUCT_SPEC'].fields)) table_fields_to_keep.push(field);
		if(this.tables) for(let table of this.tables) {
			for(let [field, value] of Object.entries(table)) {
				if(table_fields_to_keep.indexOf(field)<0) delete table[field];
			}
		}
	}

	CompactProductLines() {
		/* Manage products and specs */
		const product_fields_db=[]; for(let [field, field_data] of Object.entries(DB_TABLES['ORDER_PRODUCT'].fields)) product_fields_db.push(field);
		const product_fields_to_keep=product_fields_db.concat(['ordered', 'selected', 'specs', 'specs_ids', 'specs_ids_str' ]);
		const spec_fields_db=[]; for(let [field, field_data] of Object.entries(DB_TABLES['ORDER_PRODUCT_SPEC'].fields)) spec_fields_db.push(field);
		const spec_fields_to_keep=spec_fields_db.concat([ 'id', 'spec_id', 'order_id', 'product_id' ]);
		if(this.products) for(let order_product of this.products) {
			for(let [field, value] of Object.entries(order_product)) {
				if(product_fields_to_keep.indexOf(field)<0) delete order_product[field];
				if(field=='specs') for(let spec of order_product.specs) {
					for(let [spec_field, spec_value] of Object.entries(spec)) {
						if(spec_fields_to_keep.indexOf(spec_field)<0) delete spec[spec_field];
					}
				}
			}
		}
	}

	MakeAdditional() {
		this.Compact();
		const products=[];
		if(this.products) for(let order_product of this.products) if(!order_product.ordered) products.push(order_product);
		this.order_product=products;
	}

	ReadyToPay() {
		/* Remove unselected products */
		this.RemoveUnselectedProducts();
		/* Get product lines */
		this.GetProductLines(true);
	}

	GetProductLines(only_unpaid=true) {
		this.product_lines=[];
		this.unique_products_rows_ids=[];
		var row_index=-1;
		for(let product of this.products) {
			/* Check if product is paid */
			if(only_unpaid && product.paid) continue;
			/* Check if product quantity is integer */
			if(product.unit_is_integer) {
				for(var q=1;q<=product.quantity;q++) {
					row_index++;
					const product_line=new OrderProduct(product);
					product_line.quantity=1;
					product_line.selected=1;
					product_line.row_index=row_index;
					product_line.ManageAmounts();
					this.product_lines.push(product_line);
					if(this.unique_products_rows_ids.indexOf(product_line.row_id)<0) this.unique_products_rows_ids.push(product_line.row_id);
				}
			} else {
				row_index++;
				const product_line=new OrderProduct(product);
				product_line.row_index=row_index;
				product_line.selected=1;
				product_line.to_pay=1;
				product_line.ManageAmounts();
				this.product_lines.push(product_line);
				if(this.unique_products_rows_ids.indexOf(product_line.row_id)<0) this.unique_products_rows_ids.push(product_line.row_id);
			}
		}
		this.unique_products_rows_ids_str=this.unique_products_rows_ids.join(',');
		return this.product_lines;
	}

	RemoveUnselectedProducts() {
		/* Remove unselected products */
		for(var i=this.products.length-1;i>=0;i--) if(!this.products[i].selected) this.products.splice(i, 1);
	}

	Save() {
		this.Reconstruct();
		this.source='session';
		sessionStorage.setItem('order', JSON.stringify(this));
	}

	RefreshBadges() {
		this.FixNumerics();
		var unordered_products=0, unpaid_products=0;
		if(this.products && this.products.length) for(let order_product of this.products) {
			if(!order_product.ordered) unordered_products++;
			if(order_product.ordered && !order_product.paid) {
				if(order_product.unit_is_integer)
					unpaid_products+=order_product.quantity-order_product.paid_quantity;
				else
					unpaid_products++;
			}
		}
		if($('#header_order_badge').length) {
			$('#header_order_badge').text(unordered_products);
			if(unordered_products>0) $('#header_order_badge').show(); else $('#header_order_badge').hide();
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
		for(let order_product of this.products) if(order_product.product_id==product_id) return order_product;
		return false;
	}

	GetUnorderedQuantity() {
		var unordered=0;
		if(this.products && this.products.length) for(let order_product of this.products) if(!order_product.ordered) unordered+=order_product.quantity;
		return unordered;
	}

	GetProductQuantity(product) {
		const ret={ quantity_to_order: 0, quantity_ordered: 0, quantity_delivered: 0, quantity_paid: 0, quantity_total: 0, multiple_entries: false };
		var product_id='';
		if(typeof product==='string' || product instanceof String) product_id=product;
		else if(typeof product==='object' || product instanceof Object) product_id=product.id;
		else if(typeof product==='array' || product instanceof Array) product_id=product[id];
		if(product_id=='') return ret;
		if(this.products==undefined || this.products.length==0) return ret;
		var product_found=false;
		for(let order_product of this.products) {
			if(order_product.product_id==product_id) {
				ret.quantity_total+=order_product.quantity;
				if(order_product.ordered) ret.quantity_ordered+=order_product.quantity; else ret.quantity_to_order+=order_product.quantity;
				if(order_product.delivered) ret.quantity_delivered+=order_product.quantity;
				if(order_product.paid) ret.quantity_paid+=order_product.quantity;
				if(product_found) ret.multiple_entries=1;
				product_found=1;
			}
		}
		return ret;
	}

	GetProductByRowId(row_id) {
		if(!this.products || this.products.length==0) return false;
		for(let order_product of this.products) if(order_product.row_id==row_id) return order_product;
		return false;
	}

	GetProductIndexByRowId(row_id) {
		if(!this.products || this.products.length==0) return false;
		for(var i=0;i<this.products.length;i++) if(this.products[i].row_id==row_id) return i;
		return false;
	}

	UpdateProduct(order_product) {
		if(!(order_product instanceof OrderProduct)) { alert('Invalid product'); return false; }
		if(!order_product.row_id) { alert('Invalid product row_id'); return false; }
		/* Loop through order products */
		for(var i=0;i<this.products.length;i++) {
			/* Match row id */
			if(order_product.row_id==this.products[i].row_id) {
				if(order_product.quantity<=0) {
					this.products.splice(i, 1);
				} else {
					order_product.Reconstruct();
					this.products[i]=order_product;
				}

				this.Reconstruct();
				this.Save();
				this.RefreshBadges();
			}
		}
	}

	AddProduct(order_product) {
		if(!(order_product instanceof OrderProduct)) { alert('Invalid product'); return false; }
		this.products.push(order_product);
		this.Reconstruct();
		this.Save();
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
/* Order model - End */


/* Order product model - Start */
class OrderProduct extends AppClass {

	constructor(params={}) {
		super(params);
		if(!this.ordered) this.ordered=0;
		if(!this.selected) this.selected=0;
	}

	GetTable() {
		return 'ORDER_PRODUCT';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

        /* if order id is empty, get microtime as id */
		if(Empty(this.id)) { this.id=parseInt('-' + new Date().valueOf() + (Empty(this.index) ? (Math.random() * 1000).toFixed(0) : this.index)); }

		/* Manage product */
		if(!Empty(this.product)) this.product=new Product(this.product);

		/* Manage unit */
		this.ManageUnit();

		/* Manage specs */
		this.ManageSpecs();

		/* Manage amounts */
		this.ManageAmounts();

		/* Get hash */
		this.GetHash();
	}

	ManageUnit() {
		if(Empty(this.unit)) return;
		this.unit=new AppClass(this.unit);
		this.unit_id=this.unit.id;
		this.unit_name=this.unit.name;
		this.unit_is_integer=this.unit.is_integer;
		this.unit_price=this.unit.price;
	}

	ManageSpecs() {
		const tmp=[];
		this.specs_ids=[];
		if(this.specs && this.specs.length) for(let spec of this.specs) {
			const model=new AppClass(spec);
			if(this.specs_ids.indexOf(model.spec_id)<0) {
				tmp.push(model);
				this.specs_ids.push(model.spec_id);
			}
		}
		this.specs=tmp;
		this.specs.sort((a,b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0));
		this.specs_ids_str=Empty(this.specs_ids) ? '' : '#' + this.specs_ids.join('#') + '#';
	}

    ManageAmounts() {
		/* Calculate specs price */
		this.price_specs=0;
		for(let spec of this.specs) this.price_specs+=spec.price;

		/* Calculate total price */
		this.price_total=this.price + this.price_specs;

		/* Calculate amount */
		this.amount=Round(((this.price + this.price_specs) * this.quantity) * ((100 - this.discount) / 100), 2);

		/* Calculate net amount */
		this.net_amount=Round(this.amount / (1 + parseFloat(this.vat_percent) / 100), 2);

		/* Calculate vat amount */
		this.vat_amount=Round(this.amount - this.net_amount, 2);
	}

	GetHash() {
		this.hash_str=this.product_id + '#' + this.unit_id + '#' + this.specs_ids_str;
		this.hash=MD5(this.hash_str);
		return this.hash;
	}

}
/* Order product model - End */


/* Product model - Start */
class Product extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'PRODUCT';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* Set product_id property */
		this.product_id=Empty(this.id) ? null : this.id;

		/* Manage units */
		this.ManageUnits();

		/* Manage specs */
		this.ManageSpecs();

		/* Manage amounts */
		this.ManageAmounts();
	}

	ManageUnits() {
		this.basic_unit=null;
		this.basic_unit_id=null;
		this.basic_unit_name='';
		this.basic_unit_is_integer=0;
		this.basic_unit_quantity=0;
		this.basic_unit_price=0;
		this.units_ids=[];
		this.units_ids_str='';

		const tmp=[];
		if(!Empty(this.units)) for(let unit of this.units) {
			const model=new AppClass(unit);
			tmp.push(model);
			this.units_ids.push(model.id);
			if(this.basic_unit==null) {
				this.basic_unit=model;
				this.basic_unit_id=model.id;
				this.basic_unit_name=model.name;
				this.basic_unit_is_integer=model.is_integer;
				this.basic_unit_quantity=model.quantity;
				this.basic_unit_price=model.price;
			}
		}
		this.units_ids_str=Empty(this.units_ids) ? '' : '#' + this.units_ids.join('#') + '#';
	}

	ManageSpecs() {
		const tmp=[];
		this.specs_ids=[];
		if(this.specs && this.specs.length) for(let spec of this.specs) {
			const model=new AppClass(spec);
			if(this.specs_ids.indexOf(model.spec_id)<0) {
				tmp.push(model);
				this.specs_ids.push(model.spec_id);
			}
		}
		this.specs=tmp;
		this.specs_ids_str=Empty(this.specs_ids) ? '' : '#' + this.specs_ids.join('#') + '#';
	}

    ManageAmounts() {
		/* Calculate specs price */
		this.price_specs=0;
		for(let spec of this.specs) this.price_specs+=spec.price;

		/* Calculate total price */
		this.price_total=this.price + this.price_specs;
	}

	GetSpecById(id) {
		if(Empty(id) || Empty(this.specs)) return false;
		for(let spec of this.specs) if(spec.id==id) return spec;
		return false;
	}

}
/* Product model - End */


/* Product category model - Start */
class ProductCategory extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'PRODUCT_CATEGORY';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* Set product_id property */
		this.category_id=Empty(this.id) ? null : this.id;

		/* Manage products */
		this.ManageProducts();

		/* Manage subcategories */
		this.ManageSubcategories();
	}

	ManageProducts() {
		const tmp_products=[];
		this.products_ids=[];
		if(this.products && this.products.length) for(let product of this.products) {
			const p=new Product(product);
			tmp_products.push(p);
			this.products_ids.push(p.id);
		}
		this.products_ids_str=Empty(this.products_ids) ? '' : '#' + this.products_ids.join('#') + '#';
	}

	ManageSubcategories() {
		const tmp_subcategories=[];
		this.subcategories_ids=[];
		if(this.subcategories && this.subcategories.length) for(let subcategory of this.subcategories) {
			const s=new ProductCategory(subcategory);
			tmp_subcategories.push(s);
			this.subcategories_ids.push(s.id);
		}
		this.subcategories_ids_str=Empty(this.subcategories_ids) ? '' : '#' + this.subcategories_ids.join('#') + '#';;
	}

}
/* Product category model - End */


/* SaleDocument model - Start */
class SaleDocument extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'SALE_DOCUMENT';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* if order id is empty, get microtime as id */
		if(Empty(this.id)) { this.id=parseInt('-' + new Date().valueOf()); }

		/* Fix date */
		if(!Empty(this.date)) this.date=this.date.substr(0, 10);

		/* Manage customer */
		this.customer=Empty(this.customer) ? null : new AppClass(this.customer);

		/* Manage products */
		this.ManageProducts();

		/* Manage payments */
		this.ManagePayments();

		/* Manage amounts */
		this.ManageAmounts();
	}

	ManageProducts() {
		const hashes={};
		const tmp=[];
		if(this.products && this.products.length) for(let product of this.products) {
			const model=new SaleDocumentProduct(product);
			if(hashes[model.hash]) {
				hashes[model.hash].quantity+=model.quantity;
				hashes[model.hash].ManageAmounts();
			} else {
				hashes[model.hash]=model;
			}
		}
		this.products=[];
		for(let [hash, product] of Object.entries(hashes)) this.products.push(product);
		this.products.sort((a,b) => (a.name_specs > b.name_specs) ? 1 : ((b.name_specs > a.name_specs) ? -1 : 0));
	}

	ManagePayments() {
		tmp=[];
		if(this.payments && this.payments.length) for(let payment of this.payments) {
			const model=new AppClass(payment);
			tmp.push(model);
		}
		this.payments=tmp;
	}

    ManageAmounts() {
		this.total_amount=0;

		for(let product of this.products) {
			product.amount=Round((parseFloat(product.price) * parseFloat(product.quantity)) * ((100 - parseFloat(product.discount)) / 100), 2);
			this.total_amount+=parseFloat(product.amount);
		}

		this.paid_amount=0;
		for(let payment of this.payments) this.paid_amount+=parseFloat(payment.amount);
		this.unpaid_amount=Math.max(0, this.total_amount - this.paid_amount);
	}

	UpdateProducts(products) {
		if(!products) return;
		this.products=[];
		for(let product of products) {
            if(product.unit_is_integer) {
                var index=-1;
                for(var q=1;q<=product.quantity;q++) {
                    index++;
                    const p=new SaleDocumentProduct({...product});
                    p.quantity=1;
                    p.row_index=index;
                    p.amount=Round((parseFloat(p.price) * parseFloat(p.quantity)) * ((100 - parseFloat(p.discount)) / 100), 2);
                    this.products.push(p);
                }
            } else {
                const p=new SaleDocumentProduct({...product});
                this.products.push(p);
            }
        }
        this.GetAmounts();
	}

	HasProduct(product) {
		var product_id='';
		if(typeof product==='string' || product instanceof String) product_id=product;
		else if(typeof product==='object' || product instanceof Object) product_id=product.id;
		else if(typeof product==='array' || product instanceof Array) product_id=product[id];
		if(product_id=='') return false;
		if(this.products==undefined || this.products.length==0) return false;
		for(let this_product of this.products) if(this_product.product_id==product_id) return this_product;
		return false;
	}

	GetProductQuantity(product) {
		const ret={ quantity_to_order: 0, quantity_ordered: 0, quantity_delivered: 0, quantity_paid: 0, quantity_total: 0, multiple_entries: false };
		var product_id='';
		if(typeof product==='string' || product instanceof String) product_id=product;
		else if(typeof product==='object' || product instanceof Object) product_id=product.id;
		else if(typeof product==='array' || product instanceof Array) product_id=product[id];
		if(product_id=='') return ret;
		if(this.products==undefined || this.products.length==0) return ret;
		var product_found=false;
		for(let this_product of this.products) {
			if(this_product.product_id==product_id) {
				ret.quantity_total+=this_product.quantity;
				if(product_found) ret.multiple_entries=1;
				product_found=1;
			}
		}
		return ret;
	}

	GetProductByRowId(row_id) {
		if(!this.products || this.products.length==0) return false;
		for(let this_product of this.products) if(this_product.row_id==row_id) return this_product;
		return false;
	}

	GetProductIndexByRowId(row_id) {
		if(!this.products || this.products.length==0) return false;
		for(var i=0;i<this.products.length;i++) if(this.products[i].row_id==row_id) return i;
		return false;
	}

	UpdateProduct(document_product) {
		if(!(document_product instanceof SaleDocumentProduct)) { alert('Invalid product'); return false; }
		if(!document_product.row_id) { alert('Invalid product row_id'); return false; }
		/* Loop through order products */
		for(var i=0;i<this.products.length;i++) {
			/* Match row id */
			if(document_product.row_id==this.products[i].row_id) {
				if(document_product.quantity<=0) {
					this.products.splice(i, 1);
				} else {
					document_product.Reconstruct();
					this.products[i]=document_product;
				}
				this.Reconstruct();
			}
		}
	}

	AddProduct(document_product) {
		if(!(document_product instanceof SaleDocumentProduct)) { alert('Invalid product'); return false; }
		this.products.push(document_product);
		this.Reconstruct();
		return true;
	}

	RemoveProduct(document_product) {
		if(!(document_product instanceof SaleDocumentProduct)) { alert('Invalid product'); return false; }
		if(!document_product.row_id) { alert('Invalid product row_id'); return false; }
		for(var i=0;i<this.products.length;i++) {
			if(document_product.row_id==this.products[i].row_id) {
				this.products.splice(i, 1);
			}
		}
		return true;
	}
}
/* SaleDocument model - End */


/* SaleDocument product model - Start */
class SaleDocumentProduct extends AppClass {

	constructor(params={}) {
		super(params);
	}

	GetTable() {
		return 'SALE_DOCUMENT_PRODUCT';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

        /* if document id is empty, get microtime as id */
		if(Empty(this.id)) { this.id=parseInt('-' + new Date().valueOf() + (Empty(this.index) ? (Math.random() * 1000).toFixed(0) : this.index)); }

		/* Manage product */
		if(!Empty(this.product)) this.product=new Product(this.product);

		/* Manage unit */
		this.ManageUnit();

		/* Manage specs */
		this.ManageSpecs();

		/* Manage amounts */
		this.ManageAmounts();

		/* Get hash */
		this.GetHash();
	}

	ManageUnit() {
		if(Empty(this.unit)) return;
		this.unit=new AppClass(this.unit);
		this.unit_id=this.unit.id;
		this.unit_name=this.unit.name;
		this.unit_is_integer=this.unit.is_integer;
		this.unit_price=this.unit.price;
	}

	ManageSpecs() {
		const tmp=[];
		this.specs_ids=[];
		if(!Empty(this.specs_json) && IsString(this.specs_json)) {
			this.specs=JSON.parse(this.specs_json);
			this.specs_json='';
		}
		if(this.specs && this.specs.length) for(let spec of this.specs) {
			const model=new AppClass(spec);
			if(this.specs_ids.indexOf(model.id)<0) {
				tmp.push(model);
				this.specs_ids.push(model.id);
			}
		}
		this.specs=tmp;
		this.specs.sort((a,b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0));
		this.specs_ids_str=Empty(this.specs_ids) ? '' : '#' + this.specs_ids.join('#') + '#';
		this.specs_json=JSON.stringify(this.specs);
		this.name_specs=this.product.name;
		for(let spec of this.specs) this.name_specs+=' + ' + spec.name;
	}

	ManageAmounts() {

		/* Calculate specs prices */
		this.price_specs=0;
		if(this.specs) for(let spec of this.specs) this.price_specs+=spec.price;

		/* Calculate price */
		this.price=this.unit_price + this.price_specs;

		/* Calculate total price */
		this.price_total=this.price;

		/* Calculate amount */
		this.amount=Round((this.price * this.quantity) * ((100 - this.discount) / 100), 2);
	}

	GetHash() {
		this.hash_str=this.product_id + '#' + this.unit_id + '#' + this.specs_ids_str;
		this.hash=MD5(this.hash_str);
		return this.hash;
	}

}
/* SaleDocument product model - End */


/* Table model - Start */
class Table extends AppClass {

	constructor(params={}) {
		super(params);
		this.table_id=this.id;
	}

	GetTable() {
		return 'TABLES';
	}

	Reconstruct(source) {
		super.Reconstruct(source);

		/* Manage waiters */
		this.ManageWaiters();
	}

	ManageWaiters() {
		const tmp=[];
		this.waiters_ids=[];
		if(this.waiters && this.waiters.length) for(let waiter of this.waiters) {
			const w=new AppClass(waiter);
			w.waiter_id=w.id;
			tmp.push(w);
			this.waiters_ids.push(w.waiter_id);
		}
		this.waiters=tmp;
		this.waiters_ids_str=Empty(this.waiters_ids) ? '' : '#' + this.waiters_ids.join('#') + '#';
	}
}
/* Table model - End */


/* Waiter model - Start */
class Waiter extends AppClass {

	constructor(params={}) {
		super(params);
		this.waiter_id=this.id;
	}

	GetWaiter() {
		return 'Waiters';
	}

	Reconstruct(source) {
		super.Reconstruct(source);
	}
}
/* Waiter model - End */


