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