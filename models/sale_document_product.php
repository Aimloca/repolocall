<?php

class SaleDocumentProduct extends Model {

	const table='SALE_DOCUMENT_PRODUCT';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('SALE_DOCUMENT_PRODUCT', 'id', $primary_key_value);
		$this->product=null;
		$this->name='';
		$this->unit=null;
		$this->unit_name='';
		$this->specs=[];
		$this->specs_ids=[];
		$this->specs_ids_str='';
		$this->price_specs=0;
		$this->price_total=0;
		$this->amount=0;
		$this->vat=0;
		$this->icon=0;
		$this->comment='';
		$this->hash='';
		$this->hash_str='';
		$this->vat_category_id='';
		$this->vat_category_name='';
		$this->vat_percent=0;
	}

	function GetData() {

		// Get product
		$sql='SELECT *, name_' . Strings::GetLanguage() . ' AS name FROM PRODUCT WHERE id=' . DB::Quote($this->product_id) . ' LIMIT 1;';
		if($rows=DB::Query($sql)) {
			$this->product=$rows[0];
			$this->name=$rows[0]['name'];
			$this->icon=$rows[0]['icon'];
		}

		// Get vat percent
		if(empty($this->vat_percent) && !empty($this->product) && !empty($this->product['vat_category_id'])) {
			$sql='SELECT id, name, percent FROM VAT_CATEGORY WHERE id=' . DB::Quote($this->product['vat_category_id']) . ';';
			if($rows=DB::Query($sql)) {
				$this->vat_category_id=$rows[0]['id'];
				$this->vat_category_name=$rows[0]['name'];
				$this->vat_percent=$rows[0]['percent'];
			}
		}

		// Get unit quantity
		$this->unit=null;
		$this->unit_name='';
		$this->unit_quantity=0;
		$this->unit_is_integer=0;
		$this->unit_price=0;
		$sql='
			SELECT U.*, PU.quantity AS unit_quantity, PU.price AS unit_price, U.name_' . Strings::GetLanguage() . ' AS name
			FROM UNIT AS U
			INNER JOIN PRODUCT_UNIT AS PU ON U.id=PU.unit_id AND PU.product_id=' . DB::Quote($this->product_id) . '
			WHERE U.id=' . DB::Quote($this->unit_id) . '
			LIMIT 1;
		';
		if($rows=DB::Query($sql)) {
			$this->unit=$rows[0];
			$this->unit_name=$rows[0]['name'];
			$this->unit_quantity=$rows[0]['unit_quantity'];
			$this->unit_is_integer=$rows[0]['is_integer'];
			$this->unit['price']=$rows[0]['unit_price'];
			$this->unit_price=$rows[0]['unit_price'];
		}

		// Reset specs
		$this->specs=[];
		$this->specs_ids=[];
		$this->price_specs=0;

		// Get specs
		if(empty($this->specs_json)) $this->specs_json=[];
		if(is_string($this->specs_json)) $this->specs_json=json_decode($this->specs_json);
		foreach($this->specs_json as $spec) {
			if(in_array($spec->id, $this->specs_ids)) continue;
			$this->specs[]=$spec;
			$this->specs_ids[]=$spec->id;
			$this->price_specs+=$spec->price;
		}
		$this->specs_ids_str=empty($this->specs_ids) ? '' : '#' . implode('#', $this->specs_ids) . '#';

		// Calculate prices
		$unit_arr=empty($this->unit) ? null : json_decode(json_encode($this->unit), 1);
		$this->price_product=empty($unit_arr) || empty($unit_arr['price']) ? 0 : $unit_arr['price'];
		$this->price=$this->price_product;
		$this->price_total=$this->price_product + $this->price_specs;
		$this->amount=round($this->price_total * $this->quantity * ((100 - $this->discount) / 100), 2);
		$this->vat=$this->vat_percent==0 ? 0 : round($this->vat_percent * $this->amount / 100, 2);
		asort($this->specs_ids);

		$this->GetHash();
		return $this;
	}

	function GetHash() {
		$this->hash_str="{$this->product_id}#{$this->unit_id}#{$this->specs_ids_str}";
		return $this->hash=md5($this->hash_str);
	}

	function GetAmount() {
		$this->price_product=empty($this->unit) || empty($this->unit->price) ? 0 : $this->unit->price;
		$this->price_specs=0;
		if($this->specs) foreach($this->specs as $spec_index=>$spec) $this->price_specs+=is_object($spec) ? $spec->price : $spec['price'];
		$this->price=$this->price_product + $this->price_specs;
		$this->price_total=$this->price;
		$this->amount=round($this->price_total * $this->quantity * ((100 - $this->discount) / 100), 2);
		return $this->amount;
	}

	function Save() {
		if(!isset($this->specs))
			$this->specs='[]';
		else if(!is_string($this->specs))
			$this->specs=json_encode($this->specs);

		$save=parent::Save();
		if($save['status']) {

		}
		return $save;
	}

	public static function GetList($sale_document_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT * FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id" . (empty($sale_document_id) ? '>0' : '=' . DB::Quote($sale_document_id)) . ";";
		} else if(Session::IsUser()) {
			$sql="SELECT * FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id=" . DB::Quote(Session::User()->sale_document_id) . ";";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=SaleDocumentProduct::GetList(GetRequest('sale_document_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::Account()->can_edit_document) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new SaleDocumentProduct;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			// Update from request
			$model->CreateFromRequest();
			// Save
			$save=$model->Save();
			if($save['status']) {
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => self::class::table,
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}