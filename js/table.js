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