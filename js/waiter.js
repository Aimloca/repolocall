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