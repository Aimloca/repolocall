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