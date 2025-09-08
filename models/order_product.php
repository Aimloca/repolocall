<?php

class OrderProduct extends Model {

	const table='ORDER_PRODUCT';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('ORDER_PRODUCT', 'id', $primary_key_value);
		$this->product=null;
		$this->name='';
		$this->unit=null;
		$this->unit_name='';
		$this->specs=[];
		$this->specs_ids=[];
		$this->specs_ids_str='';
		$this->specs_str='';
		$this->price_total=0;
		$this->amount=0;
		$this->icon=0;
		$this->hash='';
		$this->hash_str='';
		$this->vat_category_id='';
		$this->vat_category_name='';
		$this->vat_percent=0;
		if(!isset($this->ordered)) $this->ordered=0;
	}

	function GetData() {
		if(empty($this->row_id)) return false;

		// Reset specs price
		$this->price_specs=0;

		// Get product
		$sql='SELECT *, name_' . Strings::GetLanguage() . ' AS name FROM PRODUCT WHERE id=' . DB::Quote($this->product_id) . ' LIMIT 1;';
		if($rows=DB::Query($sql)) {
			$this->product=$rows[0];
			$this->name=$rows[0]['name'];
			$this->icon=$rows[0]['icon'];
		}

		// Get vat percent
		if(!empty($this->product) && !empty($this->product['vat_category_id'])) {
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
		$sql='
			SELECT U.*, PU.quantity AS unit_quantity, U.name_' . Strings::GetLanguage() . ' AS name
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
		}

		// Get specs
		$this->specs=[];
		$this->specs_ids=[];
		$this->specs_str='';
		$sql='
			SELECT S.name_' . Strings::GetLanguage() . ' AS name, PS.*, PS.price AS order_spec_price
			FROM ORDER_PRODUCT_SPEC AS PS
			INNER JOIN SPEC AS S ON PS.product_spec_id=S.id
			WHERE PS.order_product_row_id=' . DB::Quote($this->row_id) . ' AND PS.order_product_id=' . DB::Quote($this->product_id) . '
			ORDER BY S.name_' . Strings::GetLanguage() . ';
		';
		if($rows=DB::Query($sql)) {
			foreach($rows as $row) {
				$row['row_id']=$row['id'];
				$row['id']=$row['product_spec_id'];
				$row['spec_id']=$row['product_spec_id'];
				$row['price']=$row['order_spec_price'];
				$this->specs[]=$row;
				if(!in_array($row['product_spec_id'], $this->specs_ids)) {
					$this->specs_ids[]=$row['product_spec_id'];
					$this->specs_str.=($this->specs_str=='' ? '' : ', ') . $row['name'];
				}
				$this->price_specs+=$row['price'];
			}
		}
		$this->price_total=$this->price + $this->price_specs;
		$this->amount=round($this->price_total * $this->quantity * ((100 - $this->discount) / 100), 2);
		asort($this->specs_ids);
		$this->specs_ids_str=empty($this->specs_ids) ? '' : '#' . implode('#', $this->specs_ids) . '#';

		$this->GetHash();
		return $this;
	}

	function GetNameAndSpecs($lang='') {
		if(!in_array($lang, LANGUAGES)) $lang=Strings::GetLanguage();
		if(empty($this->product_id)) return '';
		// Get product name
		$sql="SELECT name_{$lang} AS name FROM PRODUCT WHERE id=" . DB::Quote($this->product_id) . " LIMIT 1;";
		if(!$rows=DB::Query($sql)) return '';
		$out=$rows[0]['name'];
		// Get product specs
		$specs='';
		$sql="
			SELECT SPEC.name_{$lang} AS name
			FROM ORDER_PRODUCT_SPEC
			INNER JOIN SPEC ON ORDER_PRODUCT_SPEC.order_id=" . DB::Quote($this->order_id) . " AND ORDER_PRODUCT_SPEC.product_spec_id=SPEC.id
			WHERE ORDER_PRODUCT_SPEC.order_product_row_id=" . DB::Quote($this->id) . "
			ORDER BY name;
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) $specs.=($specs=='' ? '' : ', ') . $row['name'];
		if($specs) $out.=" [{$specs}]";
		return $out;
	}

	function GetHash() {
		$this->hash_str="{$this->product_id}#{$this->unit_id}#{$this->specs_ids_str}";
		return $this->hash=md5($this->hash_str);
	}

	public function GetAmount() {
		$this->price_specs=0;
		if($this->specs) foreach($this->specs as $spec_index=>$spec) $this->price_specs+=is_object($spec) ? $spec->price : $spec['price'];
		$this->price_total=$this->price + $this->price_specs;
		$this->amount=round($this->price_total * $this->quantity * ((100 - $this->discount) / 100), 2);
		$this->net_amount=round($this->amount / (1 + $this->vat_percent / 100), 2);
		$this->vat_amount=$this->amount - $this->net_amount;
		return $this->amount;
	}

	public static function HandleApi($id, $action) {
		return false;
	}

}