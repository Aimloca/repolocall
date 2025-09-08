<?php

class Product extends Model {

	const table='PRODUCT';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('PRODUCT', 'id', $primary_key_value);
		$this->name='';
		$this->vat_category_name='';
		$this->vat_percent=0;
	}

	function Delete() {
		DB::BeginTransaction();
		try {
			$sql="DELETE FROM PRODUCT_UNIT WHERE product_id=" . DB::Quote($this->id) . ";";
			if(!DB::Update($sql, true)) throw new Exception(Strings::Get('error_deleting_record'));
			$sql="DELETE FROM PRODUCT_SPEC WHERE product_id=" . DB::Quote($this->id) . ";";
			if(!DB::Update($sql, true)) throw new Exception(Strings::Get('error_deleting_record'));
			$sql="DELETE FROM PRODUCT_COMPOSITION WHERE product_id=" . DB::Quote($this->id) . ";";
			if(!DB::Update($sql, true)) throw new Exception(Strings::Get('error_deleting_record'));
			$del=parent::Delete();
			if(!$del['status']) throw new Exception($del['message']);
			DB::CommitTransaction();
			return $del;
		} catch(Exception $e) {
			DB::RollBackTransaction();
			return [ 'status' => false, 'message' => $e->getMessage() ];
		}
	}

	function GetData() {
		// Get name
		$tmp="name_" . Strings::GetLanguage();
		$this->name=$this->$tmp;

		// Get category
		$category=new ProductCategory;
		$category->Load(['id' => $this->category_id]);
		$this->category_name=$category->name;
		$this->basic_category_name=empty($category->basic_category_name) ? $this->category_name : $category->basic_category_name;
		$this->path=(empty($category->path) ? '' : $category->path . PRODUCT_CATEGORIES_VIEW_DELIMITER) . $this->category_name;

		// Get specs
		$this->specs=[];
		$sql='
			SELECT S.company_id, S.name_' . Strings::GetLanguage() . ' AS name, PS.*
			FROM PRODUCT_SPEC AS PS
			INNER JOIN SPEC AS S ON PS.spec_id=S.id
			WHERE PS.product_id=' . DB::Quote($this->id) . '
			ORDER BY PS.sequence;
		';
		if($rows=DB::Query($sql)) foreach($rows as $index=>$row) { $row['sequence']=$index; $this->specs[]=$row; }

		// Get units
		$this->basic_unit_id=null;
		$this->basic_unit_price=0;
		$this->basic_unit_quantity=1;
		$this->basic_unit_is_integer=1;
		$this->units=[];
		$sql='
			SELECT U.company_id, U.name_' . Strings::GetLanguage() . ' AS name, U.is_integer, PU.*
			FROM PRODUCT_UNIT AS PU
			INNER JOIN UNIT AS U ON PU.unit_id=U.id
			WHERE PU.product_id=' . DB::Quote($this->id) . '
			ORDER BY PU.sequence;
		';
		if($rows=DB::Query($sql)) foreach($rows as $index=>$row) {
			$row['sequence']=$index;
			$row['row_id']=$row['id'];
			$row['id']=$row['unit_id'];
			$this->units[]=$row;
			if($this->basic_unit_price<=0) {
				$this->basic_unit_id=$row['unit_id'];
				$this->basic_unit_price=$row['price'];
				$this->basic_unit_quantity=$row['quantity'];
				$this->basic_unit_is_integer=$row['is_integer'];
			}
		}

		// Get composition
		$this->composition=[];
		$sql='
			SELECT PC.component_id, PC.unit_id, PC.quantity, P.name_' . Strings::GetLanguage() . ' AS product_name, U.name_' . Strings::GetLanguage() . ' AS unit_name
			FROM PRODUCT_COMPOSITION AS PC
			INNER JOIN PRODUCT AS P ON PC.component_id=P.id
			INNER JOIN UNIT AS U ON PC.unit_id=U.id
			WHERE PC.product_id=' . DB::Quote($this->id) . '
			ORDER BY product_name;
		';
		if($rows=DB::Query($sql)) foreach($rows as $index=>$row) $this->composition[]=$row;

		// Get vat percent
		$this->vat_percent=0;
		$this->vat_category_name='';
		$sql='SELECT name, percent FROM VAT_CATEGORY WHERE id=' . DB::Quote($this->vat_category_id) . ';';
		if($rows=DB::Query($sql)) {
			$this->vat_percent=$rows[0]['percent'];
			$this->vat_category_name=$rows[0]['name'];
		}
	}

	function Save() {
		$record=null;
		// Check if its new record
		if(empty($this->id)) {
			// Only admins and shop managers are allowed to add table
			if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set shops manager's company id
			if(Session::IsShopManager()) $this->company_id=Session::User()->company_id;
		}

		// Check category
		if($this->category_id==-1) return [ 'status' => false, 'message' => Strings::Get('error_product_cannot_be_added_to_root_category') ];
		$category=new ProductCategory;
		if(!$category->Load(['id'=>$this->category_id])) return [ 'status' => false, 'message' => Strings::Get('error_invalid_category') ];
		if($category->company_id!=$this->company_id) return [ 'status' => false, 'message' => Strings::Get('error_invalid_category') . "{$category->company_id}!={$this->company_id}"];
		foreach(LANGUAGES_EXTENSIONS as $ext) $this->{"path{$ext}"}=$category->{"path{$ext}"} . ($category->{"path{$ext}"}=='' ? '' : PRODUCT_CATEGORIES_VIEW_DELIMITER) . $category->{"name{$ext}"};

		DB::BeginTransaction();

		// Save
		$save=parent::Save();

		// Add units
		if($save['status'] && !empty($this->id)) {
			// Check if product units are updated
			if(isset($this->json_units) && $this->json_units!='') {
				// Check variable type
				if(is_string($this->json_units)) $this->json_units=@json_decode($this->json_units);
				if($this->json_units=='') return [ 'status' => false, 'message' => Strings::Get('error_invalid_product_units') ];
				$sql_units="DELETE FROM PRODUCT_UNIT WHERE product_id=" . DB::Quote($this->id) . ";\n";
				$this->basic_unit_id=null;
				$this->basic_unit_price=0;
				$this->basic_unit_quantity=1;
				if(!empty($this->json_units)) {
					$sql_units_values='';
					foreach($this->json_units as $index=>$unit) {
						if($unit->company_id!=$this->company_id) return [ 'status' => false, 'message' => Strings::Get('error_unit_company_mismatch') ];
						$sql_units_values.=($sql_units_values=='' ? '' : ', ') . "(" . DB::Quote($this->id) . ", " . DB::Quote($unit->unit_id) . ", " . DB::Quote($unit->quantity) . ", " . DB::Quote($unit->price) . ", " . DB::Quote($unit->saleable ? '1' : '0') . ", " . DB::Quote($index) . ")";
						if(empty($this->basic_unit_id) || $this->basic_unit_price<=0) {
							$this->basic_unit_id=$unit->unit_id;
							$this->basic_unit_price=$unit->price;
							$this->basic_unit_quantity=$unit->quantity;
						}
					}
					$sql_units.="INSERT INTO PRODUCT_UNIT (product_id, unit_id, quantity, price, saleable, sequence) VALUES {$sql_units_values};\n";
				}
				$sql_units.="UPDATE PRODUCT SET basic_unit_id=" . (empty($this->basic_unit_id) ? 'NULL' : $this->basic_unit_id) . ", basic_unit_price=" . $this->basic_unit_price . ", basic_unit_quantity=" . $this->basic_unit_quantity . " WHERE id=" . DB::Quote($this->id) . ";\n";
				// Delete stored units and insert new
				DB::Query($sql_units);
			} else {
				// Check if product has units
				if(empty($this->basic_unit_id)) {
					DB::RollBackTransaction();
					return [ 'status' => false, 'message' => Strings::Get('error_product_has_no_basic_unit') ];
				}
				// Check if product has units
				if(!DB::Query("SELECT id FROM PRODUCT_UNIT WHERE product_id=" . DB::Quote($this->id) . " LIMIT 1")) {
					DB::RollBackTransaction();
					return [ 'status' => false, 'message' => Strings::Get('error_product_has_no_units') ];
				}
			}
		}

		// Add specs
		if($save['status'] && !empty($this->id) && isset($this->json_specs) && $this->json_specs!='') {
			// Check variable type
			if(is_string($this->json_specs)) $this->json_specs=@json_decode($this->json_specs);
			if($this->json_specs=='') return [ 'status' => false, 'message' => Strings::Get('error_invalid_product_specs') ];
			$sql_specs="DELETE FROM PRODUCT_SPEC WHERE product_id=" . DB::Quote($this->id) . ";\n";
			if(!empty($this->json_specs)) {
				$sql_specs_values='';
				foreach($this->json_specs as $index=>$spec) {
					if($spec->company_id!=$this->company_id) return [ 'status' => false, 'message' => Strings::Get('error_spec_company_mismatch') ];
					$sql_specs_values.=($sql_specs_values=='' ? '' : ', ') . "(" . DB::Quote($this->id) . ", " . DB::Quote($spec->spec_id) . ", " . DB::Quote($spec->price) . ", " . DB::Quote($index) . ")";
				}
				$sql_specs.="INSERT INTO PRODUCT_SPEC (product_id, spec_id, price, sequence) VALUES {$sql_specs_values};\n";
			}
			// Delete stored specs and insert new
			DB::Query($sql_specs);
		}

		// Add composition
		if($save['status'] && !empty($this->id) && isset($this->json_composition) && $this->json_composition!='') {
			// Check variable type
			if(is_string($this->json_composition)) $this->json_composition=@json_decode($this->json_composition);
			if($this->json_composition=='') return [ 'status' => false, 'message' => Strings::Get('error_invalid_product_composition') ];
			$sql_composition="DELETE FROM PRODUCT_COMPOSITION WHERE product_id=" . DB::Quote($this->id) . ";\n";
			if(!empty($this->json_composition)) {
				$sql_composition_values='';
				foreach($this->json_composition as $index=>$compoment) {
					$sql_composition_values.=($sql_composition_values=='' ? '' : ', ') . "(" . DB::Quote($this->id) . ", " . DB::Quote($compoment->component_id) . ", " . DB::Quote($compoment->unit_id) . ", " . DB::Quote($compoment->quantity) . ")";
				}
				$sql_composition.="INSERT INTO PRODUCT_COMPOSITION (product_id, component_id, unit_id, quantity) VALUES {$sql_composition_values};\n";
			}
			// Delete stored composition and insert new
			DB::Query($sql_composition);
		}

		// Update paths
		if($save['status']) Product::UpdateProductsPaths();

		// Fix sorting
		if($save['status']) $category->FixProductsSorting($this->id);

		if($save['status']) DB::CommitTransaction(); else DB::RollBackTransaction();
		return $save;
	}

	public static function GetList($passed_params='', $class='') {
		$params=$passed_params=='' ? [] : (is_string($passed_params) ? $params['company_id']=$passed_params : $passed_params);
		$where='';

		// Check user type
		if(Session::IsAdmin()) {
			if(!empty($params['company_id'])) $where.=($where=='' ? '' : ' AND ') . "company_id=" . DB::Quote($params['company_id']);
			if(!empty($params['category_id'])) $where.=($where=='' ? '' : ' AND ') . "category_id=" . DB::Quote($params['category_id']);
			if($where) $where="WHERE {$where}";
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name, path_" . Strings::GetLanguage() . " AS path FROM PRODUCT {$where} ORDER BY name;";
		} else if(Session::IsUser()) {
			$where.=($where=='' ? '' : ' AND ') . "company_id=" . DB::Quote(Session::User()->company_id);
			if(!empty($params['category_id'])) $where.=($where=='' ? '' : ' AND ') . "category_id=" . DB::Quote($params['category_id']);
			if($where) $where="WHERE {$where}";
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name, path_" . Strings::GetLanguage() . " AS path FROM PRODUCT {$where} ORDER BY name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function FixAllSortings() {
		return ProductCategory::FixAllSortings();
	}

	public static function UpdateProductsPaths() {
		$sql="SELECT id, name" . implode(', name', LANGUAGES_EXTENSIONS) . ", path" . implode(', path', LANGUAGES_EXTENSIONS) . " FROM PRODUCT_CATEGORY;";
		$rows=DB::Query($sql);
		$sql_update="";
		if($rows) foreach($rows as $row) {
			$sql='';
			foreach(LANGUAGES_EXTENSIONS as $ext) $sql.=($sql=='' ? '' : ', ') . "path{$ext}=" . DB::Quote(($row["path{$ext}"]=='' ? '' : $row["path{$ext}"] . PRODUCT_CATEGORIES_VIEW_DELIMITER) . $row["name{$ext}"]);
			$sql_update.="UPDATE PRODUCT SET {$sql} WHERE category_id=" . DB::Quote($row['id']) . ";\n";
		}
		return DB::Update($sql_update, true);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=Product::GetList(['company_id' => GetRequest('company_id'), 'category_id' => GetRequest('category_id')]);
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new Product;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update from request
			$model->CreateFromRequest();
			if(GetRequest('json_units')) $model->json_units=GetRequest('json_units');
			if(GetRequest('json_specs')) $model->json_specs=GetRequest('json_specs');
			if(GetRequest('json_composition')) $model->json_composition=GetRequest('json_composition');
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
				'table' => 'PRODUCT',
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}