<?php

class ProductCategory extends Model {

	const table='PRODUCT_CATEGORY';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('PRODUCT_CATEGORY', 'id', $primary_key_value);
		$this->name='';
		$this->basic_category_name='';
		$this->description='';
		$this->subcategories=[];
	}

	function Load($filters='') {
		$load=parent::Load($filters);
		if($load) {
			$field_name='name_' . Strings::GetLanguage();
			$field_description='description_' . Strings::GetLanguage();
			$field_path='path_' . Strings::GetLanguage();
			$this->name=$this->$field_name;
			$this->description=$this->$field_description;
			$segs=empty($this->$field_path) ? [] : explode(PRODUCT_CATEGORIES_VIEW_DELIMITER, $this->$field_path);
			$this->basic_category_name=count($segs)>1 ? $segs[1] : '';
		}
		return $load;
	}

	function Save() {
		if(!Session::IsAdmin() && !Session::IsShopManager()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
		// Check company
		if(empty($this->company_id)) return [ 'status' => false, 'message' => Strings::Get('error_company_id_is_missing') ];
		if(Session::IsShopManager() && $this->company_id!=Session::User()->company_id) return [ 'status' => false, 'message' => Strings::Get('error_company_mismatch') ];
		// Fix path
		$this->FixPath();
		$save=parent::Save();
		if($save && $save['status']) {
			// Fix sorting
			$category=new ProductCategory; if($category->Load(['id'=>$this->parent_id])) $category->FixSorting($this->id);
		}
		return parent::Save();
	}

	function Delete() {
		if(empty($this->id)) return [ 'status' => true, 'message' => Strings::Get('error_cannot_delete_root_category') ];
		return parent::Delete();
	}

	function HasProducts() {
		$this->has_products=0;
		// Check if there are products in category
		$sql="SELECT id FROM PRODUCT WHERE category_id=" . DB::Quote($this->id) . " AND visible=1 AND saleable=1 LIMIT 1;";
		if($rows=DB::Query($sql)) {
			$this->has_products=1;
		} else {
			// Check if there are products in subcategories
			$sql="SELECT id FROM PRODUCT_CATEGORY WHERE parent_id=" . DB::Quote($this->id) . " AND visible=1 AND active=1;";
			if($rows=DB::Query($sql)) foreach($rows as $row) {
				$category=new ProductCategory;
				$category->CreateFromArray($row);
				$this->has_products=$category->HasProducts();
				if($this->has_products) break;
			}
		}
		return $this->has_products;
	}

	function FixPath() {
		foreach(LANGUAGES_EXTENSIONS as $index=>$ext) $this->{"path{$ext}"}='';
		if(empty($this->parent_id)) return;
		if($this->parent_id==-1) {
			foreach(LANGUAGES_EXTENSIONS as $index=>$ext) $this->{"path{$ext}"}='';
			$this->path_ids='';
		} else {
			$category=new ProductCategory;
			if(!$category->Load(['id'=>$this->parent_id])) return;
			foreach(LANGUAGES_EXTENSIONS as $index=>$ext) {
				$this->{"path{$ext}"}=($category->{"path{$ext}"}=='' ? '' : $category->{"path{$ext}"} . PRODUCT_CATEGORIES_VIEW_DELIMITER) . $category->{"name{$ext}"};
			}
			$this->path_ids=(empty($category->path_ids) ? '#' : $category->path_ids) . $category->id . '#';
		}
	}

	function GetSubcategories($get_products=false, $whole_tree=false, $get_not_visible=false) {
		$field_name='name_' . Strings::GetLanguage();
		$this->subcategories=[];
		$sql='SELECT id FROM PRODUCT_CATEGORY WHERE parent_id!=id AND parent_id=' . DB::Quote($this->id) . ($get_not_visible ? '' : ' AND visible=1 ') . ' ORDER BY sorting;';
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$category=new ProductCategory;
			if(!$category->Load(['id' => $row['id']])) continue;
			$category->name=$category->$field_name;
			if($get_products) $category->GetProducts();
			if($whole_tree) {
				$category->GetSubcategories($get_products, $whole_tree);
				if($category->has_products) {
					$this->has_products=true;
					$this->subcategories[]=$category;
				}
			} else {
				$this->subcategories[]=$category;
			}
		}
		$this->has_subcategories=count($this->subcategories)>0;
		return $this->subcategories;
	}

	function GetProducts($get_not_saleable=false, $get_not_visible=false) {
		$this->products=[];

		$sql='
			SELECT PRODUCT.id, PRODUCT.name_' . Strings::GetLanguage() . ' AS product_name, UNIT.name_' . Strings::GetLanguage() . ' AS unit_name
			FROM PRODUCT
			LEFT JOIN UNIT ON PRODUCT.basic_unit_id=UNIT.id
			WHERE 	PRODUCT.category_id=' . DB::Quote($this->id) . '
				AND PRODUCT.basic_unit_price>0 '
				. ($get_not_saleable ? '' : ' AND PRODUCT.saleable=1 ')
				. ($get_not_visible ? '' : ' AND PRODUCT.visible=1 ') . '
			ORDER BY PRODUCT.sorting, PRODUCT.name_' . Strings::GetLanguage() . ';
		';
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$product=new Product;
			if(!$product->Load(['id' => $row['id']])) continue;
			$product->name=$row['product_name'];
			$product->unit_name=$row['unit_name'];
			$this->products[]=$product;
		}
		$this->has_products=!empty($this->has_products) || count($this->products)>0;
		return $this->products;
	}

	public function FixSorting($changed_category_id=0, $fix_subcategories=false) {
		$update_sql='';
		$sorting=0;
		$sql="
			SELECT *
			FROM PRODUCT_CATEGORY
			WHERE 	parent_id!=id
				" . ($this->id==-1 ? '' : "AND company_id=" . DB::Quote($this->company_id)) . "
				AND parent_id=" . DB::Quote($this->id) . "
			ORDER BY " . ($this->id==-1 ? "company_id," : "") . " sorting
				" . ($changed_category_id ? ", CASE WHEN id=" . DB::Quote($changed_category_id) . " THEN 0 ELSE 1 END ASC" : "") . ";
		";
		$last_company_id='';
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			if($this->id==-1) $sorting=$last_company_id!=$row['company_id'] ? 1 : $sorting + 1; else $sorting++;
			$update_sql.="UPDATE PRODUCT_CATEGORY SET sorting=" . DB::Quote($sorting) . " WHERE id=" . DB::Quote($row['id']) . ";\n";
			$last_company_id=$row['company_id'];
			if($row['id']!=-1 && $fix_subcategories) {
				// Fix subcategories
				$category=new ProductCategory;
				$category->CreateFromArray($row);
				if($category->GetSubcategories(false, false, false)) foreach($category->subcategories as $subcategory) $subcategory->FixSorting();
			}
		}
		if($update_sql) {
			$result=DB::Update($update_sql, true);
			return new Response($result, $result ? 'OK' : str_replace(['#PRODUCT_CATEGORY.id#', '#PRODUCT_CATEGORY.name#'], [$this->id, $this->name], Strings::Get('update_category_sorting_failed')), $update_sql);
		}
		return new Response(true, Strings::Get('category_has_no_subcategories'));
	}

	public function FixProductsSorting($changed_product_id=0) {
		$update_sql='';
		$sorting=0;
		$sql="
			SELECT id, sorting
			FROM PRODUCT
			WHERE category_id=" . DB::Quote($this->id) . "
			ORDER BY sorting
				" . ($changed_product_id ? ", CASE WHEN id=" . DB::Quote($changed_product_id) . " THEN 0 ELSE 1 END ASC" : "") . ";
		";
		if($rows=DB::Query($sql)) foreach($rows as $row) {
			$sorting++;
			$update_sql.="UPDATE PRODUCT SET sorting=" . DB::Quote($sorting) . " WHERE id=" . DB::Quote($row['id']) . ";\n";
		}
		if($update_sql) {
			$result=DB::Update($update_sql, true);
			return new Response($result, $result ? 'OK' : str_replace(['#PRODUCT_CATEGORY.id#', '#PRODUCT_CATEGORY.name#'], [$this->id, $this->name], Strings::Get('update_category_sorting_failed')), $update_sql);
		}
		return new Response(true, Strings::Get('category_has_no_products'));
	}

	public static function FixAllPaths($id=0, $names=[], $previous_paths=[], $previous_ids='') {
		// Update current category
		if(!empty($id) && !empty($previous_paths)) {
			$paths_values=''; foreach($previous_paths as $pk=>$pv) $paths_values.=($paths_values=='' ? '' : ', ') . "{$pk}=" . DB::Quote($pv);
			$sql="UPDATE PRODUCT_CATEGORY SET {$paths_values}, path_ids=" . DB::Quote($previous_ids) . " WHERE id=" . DB::Quote($id) . ";";
			DB::Update($sql, true);
		}
		// Set new names and paths
		$new_paths=[];
		if($id>0) {
			foreach(LANGUAGES_EXTENSIONS as $index=>$ext) {
				$new_paths["name{$ext}"]=(isset($previous_paths["path{$ext}"]) ? $previous_paths["path{$ext}"] . PRODUCT_CATEGORIES_VIEW_DELIMITER : '') . (isset($names["name{$ext}"]) ? $names["name{$ext}"] : '');
			}
			$previous_ids=(empty($previous_ids) ? '#' : $previous_ids) . $id . '#';
		}

		// Get subcategories
		$sql="SELECT id, " . implode(', name', LANGUAGES_EXTENSIONS) . implode(', path', LANGUAGES_EXTENSIONS) . " FROM PRODUCT_CATEGORY WHERE parent_id=" . DB::Quote($id) . ";";
		$subs=DB::Query($sql);
		if($subs) foreach($subs as $index=>$sub) {
			$new_names=[]; foreach(LANGUAGES_EXTENSIONS as $index=>$ext) $new_names["name{$ext}"]=$sub["name{$ext}"];
			// Update subcategory
			ProductCategory::FixAllPaths($sub['id'], $new_names, $new_paths, $previous_ids);
		}
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name, path_" . Strings::GetLanguage() . " AS path FROM PRODUCT_CATEGORY " . (empty($company_id) ? '' :  "WHERE company_id=" . DB::Quote($company_id)) . " ORDER BY sorting, name;";
		} else if(Session::IsUser()) {
			$sql="SELECT *, name_" . Strings::GetLanguage() . " AS name, path_" . Strings::GetLanguage() . " AS path FROM PRODUCT_CATEGORY WHERE company_id=" . DB::Quote(Session::User()->company_id) . " ORDER BY sorting, name;";
		} else { // Only admins and users are allowed to get list
			return [];
		}
		return parent::GetList($sql, $class);
	}

	public static function FixAllSortings() {
		if(!$categories=DB::Query('SELECT * FROM PRODUCT_CATEGORY ORDER BY id;')) return new Response(false, Strings::Get('no_categories_found'));
		$error_messages=''; $error_sqls='';
		foreach($categories as $category_db) {
			$category=new ProductCategory;
			$category->CreateFromArray($category_db);
			$response=$category->FixSorting();
			if(!$response->status) {
				$error_messages.=$response->message . "\n";
				$error_sqls.=$response->data . "\n";
			} else {
				$response=$category->FixProductsSorting();
				if(!$response->status) {
					$error_messages.=$response->message . "\n";
					$error_sqls.=$response->data . "\n";
				}
			}
		}
		return new Response($error_messages=='', $error_messages=='' ? 'OK' : $error_messages, $error_sqls);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=ProductCategory::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Load from db
			$model=new ProductCategory;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
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
				'table' => 'PRODUCT_CATEGORY',
				'allow_list' => Session::IsAdmin() || Session::IsUser(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}

}