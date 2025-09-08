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