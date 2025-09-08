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