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