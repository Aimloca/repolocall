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