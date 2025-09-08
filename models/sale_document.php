<?php
#[AllowDynamicProperties]
class SaleDocument extends Model {

	const table='SALE_DOCUMENT';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('SALE_DOCUMENT', 'id', $primary_key_value);
		$this->company=null;
		$this->series=null;
		$this->products=[];
		$this->products_vat=0;
		$this->products_vat_percents=[];
	}

	function Save() {
		// Get series
		$series=new SaleSeries;
		if(!$series->Load(['id'=>$this->series_id])) return [ 'status' => false, 'message' => Strings::Get('error_series_not_found') ];
		// Set new record
		$new_record=empty($this->id) || $this->id<=0;
		// Check if its new record
		if($new_record) {
			$this->id='';
			// Only admins and shop managers are allowed to add
			if(!Session::Account()->can_edit_document) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
			// Set user's company id
			if(Session::IsUser()) $this->company_id=Session::User()->company_id;
		}
		// Save
		$save=parent::Save();
		if($save['status']) {

		}
		return $save;
	}

	function GetData() {
		$this->GetCompany();
		$this->GetSeries();
		$this->GetCustomer();
		$this->GetProducts();
		$this->GetAmounts();
		$this->GetDocuments();
		return $this;
	}

	function GetCompany() {
		$this->company=null;
		$company=new Company;
		if(!$company->Load(['id'=>$this->company_id])) return;
		$this->company=$company;
	}

	function GetSeries() {
		$this->series=null;
		$series=new SaleSeries;
		if(!$series->Load(['id'=>$this->series_id])) return;
		$this->series=$series;
	}

	function GetCustomer() {
		$this->customer=null;
		$customer=new CompanyCustomer;
		if(!$customer->Load(['id'=>$this->customer_id])) return;
		$this->customer=$customer;
	}

	function GetProducts() {
		$this->products=[];
		$this->products_ids=[];
		$this->products_rows_ids=[];
		$products_hashes=[];

		$sql="
			SELECT SALE_DOCUMENT_PRODUCT.id AS row_id, SALE_DOCUMENT_PRODUCT.product_id, PRODUCT.name_" . Strings::GetLanguage() . " AS name
			FROM SALE_DOCUMENT_PRODUCT
			INNER JOIN PRODUCT ON SALE_DOCUMENT_PRODUCT.product_id=PRODUCT.id
			WHERE SALE_DOCUMENT_PRODUCT.sale_document_id=" . DB::Quote($this->id) . "
			ORDER BY PRODUCT.name_" . Strings::GetLanguage() . ";
		";
		if($rows=DB::Query($sql)) {
			foreach($rows as $row_index=>$row) {
				$product=new SaleDocumentProduct;
				$product->Load(['id' => $row['row_id']]);
				$product->row_id=$row['row_id'];
				$product->id=$row['product_id'];
				$product->GetData();

				//$this->products[]=$product;
				//$this->products_ids[]=$product->id;
				//$this->products_rows_ids[]=$product->row_id;
				if(!isset($products_hashes[$product->hash])) {
					$products_hashes[$product->hash]=$product;
				} else {
					$products_hashes[$product->hash]->quantity+=$product->quantity;
					$products_hashes[$product->hash]->GetAmount();
				}
			}
		}

		// Fill products
		foreach($products_hashes as $hash=>$product) {
			$this->products[]=$product;
			if(!in_array($product->product_id, $this->products_ids)) $this->products_ids[]=$product->product_id;
			if(!in_array($product->row_id, $this->products_rows_ids)) $this->products_rows_ids[]=$product->row_id;
		}
		// Fix products ids
		$this->products_ids_str=empty($this->products_ids) ? '' : '#' . implode('#', $this->products_ids) . '#';
		// Fix products rows ids
		$this->products_rows_ids_str=empty($this->products_rows_ids) ? '' : '#' . implode('#', $this->products_rows_ids) . '#';
		$products_hashes=null;
		return $this->products;
	}

	function GetDocuments() {
		$this->documents=[];
		$this->documents_ids=[];
		$this->documents_ids_str='';
		$sql="SELECT id FROM SALE_DOCUMENT WHERE relative_document_id=" . DB::Quote($this->id) . " ORDER BY date;";
		if($rows=DB::Query($sql)) foreach($rows as $row_index=>$row) {
			$doc=new SaleDocument;
			$doc->CreateFromArray($row);
			if(!in_array($doc->id, $this->documents_ids)) {
				$this->documents[]=$doc;
				$this->documents_ids[]=$doc->id;
			}
		}
		$this->documents_ids_str=empty($this->documents_ids) ? '' : '#' . implode('#', $this->documents_ids) . '#';
		$this->has_documents=!empty($this->documents);
		return $this->documents;
	}

	function GetAmounts() {
		if(empty($this->series)) $this->GetSeries();
		if(empty($this->products)) $this->GetProducts();
		$this->products_amount=0;
		$this->products_vat=0;
		$this->products_vat_percents=[];
		if(!empty($this->products)) foreach($this->products as $product) {
			$this->products_amount+=$product->amount;
			$this->products_vat+=$product->vat;
			if(!isset($this->products_vat_percents[$product->vat_category_name])) $this->products_vat_percents[$product->vat_category_name]=0;
			$this->products_vat_percents[$product->vat_category_name]+=$product->vat;
		}
		$this->total_amount=$this->products_amount + $this->tip_amount;
		$this->total_amount_without_vat=$this->products_amount - $this->products_vat;

		// Fix signs
		if(!empty($this->series) && $this->series->affects_price!=1) {
			$this->products_amount=$this->series->affects_price==0 ? 0 : -$this->products_amount;
			$this->products_vat=$this->series->affects_price==0 ? 0 : -$this->products_vat;
			$this->total_amount=$this->series->affects_price==0 ? 0 : -$this->total_amount;
			$this->total_amount_without_vat=$this->series->affects_price==0 ? 0 : -$this->total_amount_without_vat;
		}
	}

	function CreateFromOrder($order_id) {
		$order=new Order;
		if(!$order->Load(['id' => $order_id])) return false;
		$order->GetData();

		// Copy basic data
		$this->id='-' . time();
		$this->date=date('Y-m-d');
		$this->company_id=$order->company_id;
		$this->customer_id=$order->customer_id;
		$this->printed=0;
		$this->relative_order_id=$order_id;
		$this->customer_order=$order->customer_order;

		// Copy products
		$this->products_ids=[];
		$this->products_rows_ids=[];
		$products_hashes=[];
		foreach($order->products as $product_index=>$product_row) {
			if(empty($product_row->product) || empty($product_row->product->units)) {
				$product_row->product=new Product;
				$product_row->product->Load(['id' => $product_row->product_id]);
				$product_row->product->GetData();
			}

			// Unset unwanted properties
			if(isset($product_row->table)) unset($product_row->table);
			if(isset($product_row->controller)) unset($product_row->controller);
			if(isset($product_row->primary_key)) unset($product_row->primary_key);
			if(isset($product_row->primary_key_value)) unset($product_row->primary_key_value);
			if(isset($product_row->predefined_db_fields_values)) unset($product_row->predefined_db_fields_values);
			if(isset($product_row->id)) unset($product_row->id);
			if(isset($product_row->row_id)) unset($product_row->row_id);

			$doc_product=new SaleDocumentProduct;
			$doc_product->CreateFromArray($product_row);
			$doc_product->id='-' . time() . count($this->products);
			$doc_product->row_id=$doc_product->id;
			$doc_product->sale_document_id=$this->id;

			// Get unit
			$doc_product->unit=new Unit;
			if(!empty($doc_product->product->units)) foreach($doc_product->product->units as $unit) {
				if(is_array($unit)) { $tmp=new Unit; $tmp->CreateFromArray($unit); $unit=$tmp; $tmp=null; }
				if($product_row->unit_id==$unit->id) { $doc_product->unit=$unit; break; }
			}

			// Get specs
			$doc_product->specs=[];
			$doc_product->specs_ids=[];
			if($product_row->specs) foreach($product_row->specs as $spec_index=>$spec) {
				$tmp=new Spec;
				$tmp->CreateFromArray($spec);
				$spec=$tmp;
				$doc_product->specs[]=[ 'id' => $spec->id, 'name' => $spec->name, 'price' => $spec->price ];
				$doc_product->specs_ids[]=$spec->id;
				$doc_product->price+=$spec->price;
				$tmp=null;
			}

			$doc_product->price_total=$doc_product->price;
			$doc_product->amount=round($doc_product->price_total * $doc_product->quantity * ((100 - $doc_product->discount) / 100), 2);
			$doc_product->specs_ids_str=empty($doc_product->specs_ids) ? '' : '#' . implode('#', $doc_product->specs_ids) . '#';
			$doc_product->specs_json=json_encode($doc_product->specs);

			$doc_product->GetHash();

			if(!isset($products_hashes[$doc_product->hash])) {
				$products_hashes[$doc_product->hash]=$doc_product;
			} else {
				$products_hashes[$doc_product->hash]->quantity+=$doc_product->quantity;
				$products_hashes[$doc_product->hash]->GetAmount();
			}
		}

		// Fill products
		foreach($products_hashes as $hash=>$product) {
			$this->products[]=$product;
			if(!in_array($product->product_id, $this->products_ids)) $this->products_ids[]=$product->product_id;
			if(!in_array($product->row_id, $this->products_rows_ids)) $this->products_rows_ids[]=$product->row_id;
		}
		// Fix products ids
		$this->products_ids_str=empty($this->products_ids) ? '' : '#' . implode('#', $this->products_ids) . '#';
		// Fix products rows ids
		$this->products_rows_ids_str=empty($this->products_rows_ids) ? '' : '#' . implode('#', $this->products_rows_ids) . '#';
		$products_hashes=null;
		return $this;
	}

	function UpdateProducts($products, $document_json) {
		if(is_string($products)) $products=@json_decode($products);
		if($products=='') return new Response(false, Strings::Get('error_invalid_products_data'));

		try {
			// Begin transaction
			DB::BeginTransaction();

			// Delete products rows
			$sql='DELETE FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id=' . DB::Quote($this->id) . ';';
			DB::Query($sql);
			foreach($products as $index=>$product) {
				if(empty($product->company_id) && !empty($product->product->company_id)) $product->company_id=$product->product->company_id;
				// Check company id
				if($product->company_id!=$this->company_id) throw new Exception(Strings::Get('error_product_company_mismatch'));

				$sql ="SELECT mydata_income_category_id, mydata_retail_income_type, mydata_wholesale_income_type FROM PRODUCT_CATEGORY WHERE id=".$product->product->category_id.";";
				$result = DB::Query($sql);

				$sql ="SELECT retail, mydata_doc_type_id FROM SALE_SERIES WHERE id=".$document_json->series_id.";";
				$result1 = DB::Query($sql);
				$is_retail = $result1[0]["retail"];
				$doc_type_id = $result1[0]['mydata_doc_type_id'];

				$mydata_income_category = $result[0]["mydata_income_category_id"];
				if($is_retail){
					$mydata_income_type = $result[0]["mydata_retail_income_type"];
				}else{
					$mydata_income_type = $result[0]["mydata_wholesale_income_type"];
				}


				// Insert product row
				$sql='
					INSERT INTO SALE_DOCUMENT_PRODUCT (sale_document_id, product_id, unit_id, specs_json, quantity, price, discount, amount, mydata_income_category, mydata_income_type) VALUES (
					' . DB::Quote($this->id) . ',
					' . DB::Quote($product->product_id) . ',
					' . DB::Quote($product->unit_id) . ',
					' . DB::Quote(json_encode($product->specs)) . ',
					' . DB::Quote($product->quantity) . ',
					' . DB::Quote($product->price) . ',
					' . DB::Quote($product->discount) . ',
					' . DB::Quote($product->amount) . ',
					' . DB::Quote($mydata_income_category) . ',
					' . DB::Quote($mydata_income_type) . '
					);
				';

				if(!DB::Insert($sql, false, true)) throw new Exception(Strings::Get('error_inserting_document_products'));

				/* $sql1="UPDATE SALE_DOCUMENT SET mydata_doc_type=". DB::Quote($doc_type_id)."WHERE id=".$this->id.";";
				DB::Query($sql1); */

			}
			//$this->GetData();
			$this->Save();
			$this->GetAmountsAndSave();
			DB::CommitTransaction();
			$this->GetData();
			return new Response(true, 'OK', $this);
		} catch(Exception $e) {
			DB::RollBackTransaction();
			return new Response(false, $e->getMessage());
		}
	}

	public function Print() {
		// Check printed
		if($this->printed) return new Response(false, Strings::Get('error_document_is_printed'));
		// Check series
		$series=new SaleSeries;
		if(!$series->Load(['id'=>$this->series_id])) return new Response(false, Strings::Get('error_series_is_not_found'));
		// Check series layout
		if(empty($series->form_header) && empty($series->form_products) && empty($series->form_footer)) return new Response(false, Strings::Get('error_series_has_no_layout'));

		// Increase sequence
		$series_next_sequence=$series->sequence + 1;
		$this->code_sequence=$series->code . $series_next_sequence;

		try {
			// Begin transaction
			DB::BeginTransaction();

			// Update series sequence
			$series->sequence++;
			$save_series=$series->Save();
			if(!$save_series['status']) throw new Exception(Strings::Get('error_saving_series'));

			// Set code sequence
			$this->code_sequence=$series->code . $series->sequence;

			// Set printed
			$this->printed=1;

			// Set printed date and time
			$this->date_printed=date('Y-m-d H:i:s');
			$this->date_printed_date=substr($this->date_printed, 0, 10);
			$this->date_printed_time=substr($this->date_printed, 11, 5);

			// Save
			$save=$this->Save();
			if(!$save['status']) throw new Exception(Strings::Get('error_saving_document'));
			$this->GetAmountsAndSave();

			// Check company
			if(empty($this->company)) $this->GetCompany();
			$parameters=empty($this->company) ? new Model('PARAMETERS', 'id') : $this->company->GetParameters();

			// Create mydata mark
			$mark_result=$this->CreateMyDataMark();

			// Check mydata result
			if(!$mark_result->status) {
				if(!empty($this->company) && !$parameters->mydata_on_error_proceed) throw new Exception($mark_result->message);
			}

			// Commit transaction
			DB::CommitTransaction();

			// Get print HTML
			$print_html=$this->GetPrintHtml();

			// Create new page link
			$print_link=$print_html ? Strings::CreateEncryptedLink(BASE_URL . 'sale_document/print/?id=' . $this->id) : '';

			return new Response(true, 'OK', $print_link);
		} catch(Exception $e) {
			// Rollback transaction
			DB::RollBackTransaction();
			return new Response(false, $e->getMessage());
		}
	}

	public function Reprint() {
		// Check printed
		if(!$this->printed) return new Response(false, Strings::Get('error_document_is_not_printed'));
		// Check series
		$series=new SaleSeries;
		if(!$series->Load(['id'=>$this->series_id])) return new Response(false, Strings::Get('error_series_is_not_found'));
		// Check series layout
		if(empty($series->form_header) && empty($series->form_products) && empty($series->form_footer)) return new Response(false, Strings::Get('error_series_has_no_layout'));
		// Get print HTML
		$print_html=$this->GetPrintHtml();
		// Create new page link
		$print_link=$print_html ? Strings::CreateEncryptedLink(BASE_URL . 'sale_document/print/?id=' . $this->id) : '';
		return new Response(true, 'OK', $print_link);
	}

	function GetPrintHtml() {
		// Set printed date and time
		$this->date_printed_date=substr($this->date_printed, 0, 10);
		$this->date_printed_time=substr($this->date_printed, 11, 5);

		// Get company
		if(empty($this->company)) $this->GetData();
		// Get series
		if(empty($this->series)) $this->GetData();
		// Get customer
		if(empty($this->customer)) $this->GetData();
		// Get products
		if(empty($this->products)) $this->GetData();

		// Get series form header
		$form=GetPrintableHtml($this->series->form_header, [$this, $this->series, $this->company, $this->customer]);

		// Loop through items and get series form products
		foreach($this->products as $product) {
			$tmp=new Product; $tmp->CreateFromArray($product->product); $product->product=$tmp;
			$tmp=new Unit; $tmp->CreateFromArray($product->unit); $product->unit=$tmp;
			$specs=[]; if($product->specs) foreach($product->specs as $spec) { $tmp=new Spec; $tmp->CreateFromArray($spec); $specs[]=$tmp; } $product->specs=$tmp;
			$form.=GetPrintableHtml($this->series->form_products, [$this, $this->series, $this->company, $this->customer, $product, $product->product, $product->unit, $product->specs]);
		}

		// Get series form footer
		$form.=GetPrintableHtml($this->series->form_footer, [$this, $this->series, $this->company, $this->customer]);

		// Fix mydata qr code image
		if(!empty($this->mydata_qr_url)) $form=str_replace('#SALE_DOCUMENT.mydata_qr_url_base64#', Image::GetQRUrl($this->mydata_qr_url), $form);

		// Check if form contains HTML tag
		if(strpos($form, '</html>')===false) $form='<html>' . $form . '</html>';
		// Check if form contains head tag
		if(strpos($form, '</head>')===false) $form=str_replace('<html>', '<html><head><title>' . APP_NAME . ' - Sale Document</title></head>', $form);
		// Check if form contains body tag
		if(strpos($form, '</body>')===false) $form=str_replace(['</head>', '</html>'], ['</head><body margin="0" padding="0">', '</body></html>'], $form);
		// Check if form contains script tag
		if(strpos($form, 'window.print();')===false) $form=str_replace('</body>', '<script>document.addEventListener("DOMContentLoaded", function(event) { window.print(); });</script></body>', $form);
		return $form;
	}

	function CreateMyDataMark() {
		//include_once LOG_PATH .'mydata.php';

		// Get MyData mark
		$mark=MyData::Mark($this);

		// Check response
		if($mark && $mark->status) {
			// Store mydata_mark
			$this->mydata_mark=$mark->data['mark'];
			// Store mydata_qr_url
			$this->mydata_qr_url=$mark->data['url'];

			// Instant update record
			if($this->id && $this->id>0) DB::Update('UPDATE SALE_DOCUMENT SET mydata_mark=' . DB::Quote($this->mydata_mark) . ', mydata_qr_url=' . DB::Quote($this->mydata_qr_url) . ' WHERE id=' . DB::Quote($this->id));

			return new Response(true, 'OK', $mark->data);
		}
		return new Response(false, isset($mark) && $mark ? $mark->message : Strings::Get('error_getting_mydata_mark'), $mark->data);
	}

	function GetAmountsAndSave() {
		$this->GetAmounts();
		DB::Update('UPDATE SALE_DOCUMENT SET products_amount=' . DB::Quote($this->products_amount) . ', tip_amount=' . DB::Quote($this->tip_amount) . ', total_amount=' . DB::Quote($this->total_amount) . ' WHERE id=' . DB::Quote($this->id));
	}

	function RecalculateProductsAmount() {
		// Update product vat percent where is missing
		$sql="
			UPDATE SALE_DOCUMENT_PRODUCT SET vat_percent=(
				SELECT percent FROM VAT_CATEGORY WHERE id IN (
					SELECT vat_category_id FROM PRODUCT WHERE id=SALE_DOCUMENT_PRODUCT.product_id
				)
			) WHERE vat_percent=0 AND sale_document_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		// Update product net amount
		$sql="
			UPDATE SALE_DOCUMENT_PRODUCT
			SET net_amount=amount / (1 + vat_percent / 100)
			WHERE sale_document_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		// Update product vat amount
		$sql="
			UPDATE SALE_DOCUMENT_PRODUCT
			SET vat_amount=amount - net_amount
			WHERE sale_document_id=" . DB::Quote($this->id) . ";
		";
		DB::Update($sql, true);

		return $this->UpdateAmounts();
	}

	function UpdateAmounts() {
		// Update products, paid and tips amount
		$sql="
			UPDATE SALE_DOCUMENT SET
				products_amount=(SELECT SUM(amount) FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id=" . DB::Quote($this->id) . "),
				products_net_amount=(SELECT SUM(net_amount) FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id=" . DB::Quote($this->id) . "),
				products_vat_amount=(SELECT SUM(vat_amount) FROM SALE_DOCUMENT_PRODUCT WHERE sale_document_id=" . DB::Quote($this->id) . "),
				tip_amount=COALESCE((SELECT SUM(tip_amount) FROM PAYMENT WHERE completed=1 AND order_id=SALE_DOCUMENT.relative_order_id), 0)
			WHERE id=" . DB::Quote($this->id) . ";
		";
		$update=DB::Update($sql, true);
		if(!$update) return $update;

		// Update order total amount
		$sql="UPDATE SALE_DOCUMENT SET total_amount=products_amount + tip_amount WHERE id=" . DB::Quote($this->id) . ";";
		return DB::Update($sql, true);
	}

	public static function GetList($company_id='', $class='') {
		if(!Session::IsAdmin() && !Session::IsUser()) return [];
		$sql="
			SELECT SALE_DOCUMENT.*,
				COMPANY_CUSTOMER.name AS customer_name,
				(SELECT SUM(amount) FROM SALE_DOCUMENT_PRODUCT WHERE SALE_DOCUMENT_PRODUCT.sale_document_id=SALE_DOCUMENT.id) AS total
			FROM SALE_DOCUMENT
			LEFT JOIN COMPANY_CUSTOMER ON SALE_DOCUMENT.customer_id=COMPANY_CUSTOMER.id
			WHERE SALE_DOCUMENT.company_id" . (Session::IsUser() ? '=' . Session::User()->company_id : (empty($company_id) ? '>0' : '=' . DB::Quote($company_id))) . "
			ORDER BY SALE_DOCUMENT.date;
		";
		return parent::GetList($sql, $class);
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=SaleDocument::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='edit') {
			// Check permissions
			if(empty($id) && !Session::IsAdmin() && !Session::IsShopManager()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Load from db
			$model=new SaleDocument;
			// Get from database
			$load=$model->Load(['id'=>$id]);
			if(!Session::IsAdmin() && $load && $model->company_id!=Session::User()->company_id) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Update from request
			$model->CreateFromRequest();
			// Save
			$save=$model->Save();
			if($save['status']) {
				$model->GetAmountsAndSave();
				return new Response(true, Strings::Get('data_saved'));
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='user_edit') {
			// Check permissions
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check passed document
			$document_json_str=GetRequest('document');
			if(empty($document_json_str)) return new Response(false, Strings::Get('error_invalid_document'));
			$document_json=@json_decode($document_json_str);
			if(empty($document_json)) return new Response(false, Strings::Get('error_invalid_document'), $document_json_str);
			// Check company
			if(Session::IsUser() && Session::User()->company_id!=$document_json->company_id) return new Response(false, Strings::Get('error_company_mismatch'));
			// Create document for passed json
			$document=new SaleDocument();
			$document->CreateFromArray($document_json);
			$save=$document->Save();
			if($save['status']) {
				$update_products=$document->UpdateProducts($document_json->products, $document_json);
				if($update_products->status) {
					$document->GetAmountsAndSave();
					$document->RecalculateProductsAmount();
					return new Response(true, Strings::Get('data_saved'), $document);
				} else {
					return new Response(false, $update_products->message);
				}
			} else {
				return new Response(false, Strings::Get('error_data_cannot_be_saved') . PHP_EOL . $save['message']);
			}

		} else if($action=='print') {
			// Check permissions
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check passed document
			$document=new SaleDocument;
			if(!$document->Load(['id'=>$id])) return new Response(false, Strings::Get('error_invalid_document'));
			return $document->Print();

		} else if($action=='reprint') {
			// Check permissions
			if(!Session::IsAdmin() && !Session::IsUser()) return new Response(false, Strings::Get('error_insufficient_rights'));
			// Check passed document
			$document=new SaleDocument;
			if(!$document->Load(['id'=>$id])) return new Response(false, Strings::Get('error_invalid_document'));
			return $document->Reprint();

		} else if($action=='prnsrv_sale_document'){
			$company_id = GetRequest('company_id');
			if(empty($company_id)) return new Response(true, 'OK', '1');
								
			$sql_sale_doc ='SELECT SD.id, SS.name series_name, SS.code, SS.sequence, SS.printable_id, SS.retail retail, SS.allow_mydata_send, SD.products_amount, SD.products_net_amount, SD.products_vat_amount, MYDT.code mydata_doc_type, 
							CC.name customer_name, CC.address customer_address, CC.tax_number customer_tax_number, CC.city customer_city, CC.postal customer_postal,
							CC.tax_office customer_tax_office, T.name name_table, U.name waiter_name FROM `SALE_DOCUMENT` SD
							inner join SALE_SERIES SS on SS.id=SD.series_id
							inner join MYDATA_DOC_TYPES MYDT on MYDT.id=SD.mydata_doc_type
							left join CUSTOMER C on C.id=SD.customer_id
							left join COMPANY_CUSTOMER CC on SD.company_id=CC.id
							inner join ORDERS O on SD.relative_order_id=O.id
                            left join ORDER_TABLE OT on O.id=OT.order_id
                            left join TABLES T on OT.table_id=T.id
							left join USER U on O.waiter_id = U.id
							where 
								SD.company_id='.$company_id.'
								and SD.printed=0
								and SD.date_printed is null
								and SD.date_cancelled is null
								and SS.active=1 
								limit 1;';
					

			$sale_doc = DB::Query($sql_sale_doc);
			if(!$sale_doc) return new Response(true, 'OK', '2');			
					
			$sql_sale_doc_product = 'SELECT SDP.product_id, P.name_gr, count(1) quantity, sum(SDP.price) amount, SDP.price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount, SDP.vat_percent,
					MDIC.code mydata_income_category, MDIT.code mydata_income_type, VC.vat_category_mydata_code  FROM `SALE_DOCUMENT_PRODUCT` SDP
					inner join PRODUCT P on P.id=SDP.product_id
					inner join MYDATA_INCOME_CATEGORY MDIC on MDIC.id=SDP.mydata_income_category 
					inner join MYDATA_INCOME_TYPE MDIT on MDIT.id=SDP.mydata_income_type
					inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
					where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.product_id';		

			$resalt=DB::Query($sql_sale_doc_product);
			
			if(!$resalt) return new Response(true, 'OK', '3');
			
			$products = array();
			foreach($resalt as $r){
				array_push($products, ["product_name" => $r['name_gr'],
								"product_id" => $r['product_id'],
								"quantity" => $r['quantity'],
								"product_price" => $r['price'],
								"amount" => $r['amount'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount'],
								"product_vat_percent" => $r['vat_percent'],
								"vat_category_mydata_code" => $r['vat_category_mydata_code'],
								"mydata_income_category" => $r['mydata_income_category'],
								"mydata_income_type" => $r['mydata_income_type']]
						);
			}
			
			$sql_sale_doc_vat_analisis_product ='SELECT SDP.vat_percent, count(1) quntity, sum(SDP.price) price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount  FROM `SALE_DOCUMENT_PRODUCT` SDP
												inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
												where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.vat_percent';
												
			$resalt2=DB::Query($sql_sale_doc_vat_analisis_product);
			
			if(!$resalt2) return new Response(true, 'OK', '3');
			
			$vat_analisis_products = array();
			foreach($resalt2 as $r){
				array_push($vat_analisis_products, ["vat_percent" => $r['vat_percent'],
								"quntity" => $r['quntity'],
								"price" => $r['price'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount']]
				);
			}
			

			$sale_doc =["sale_doc_id" => $sale_doc[0]['id'],
			"series_name" => $sale_doc[0]['series_name'],
			"series_code" => $sale_doc[0]['code'],
			"series_sequence" => $sale_doc[0]['sequence'],
			"allow_mydata_send" => $sale_doc[0]['allow_mydata_send'],
			"retail" => $sale_doc[0]['retail'],
			"name_table" => $sale_doc[0]['name_table'],
			"waiter_name" => $sale_doc[0]['waiter_name'],
			"total_amount" => $sale_doc[0]['products_amount'],
			"total_net_amount" => $sale_doc[0]['products_net_amount'],
			"total_vat_amount" => $sale_doc[0]['products_vat_amount'],
			"mydata_doc_type" => $sale_doc[0]['mydata_doc_type'],
			"customer_name" => $sale_doc[0]['customer_name'],
			"customer_address" => $sale_doc[0]['customer_address'],
			"customer_tax_number" => $sale_doc[0]['customer_tax_number'],
			"customer_city" => $sale_doc[0]['customer_city'],
			"customer_postal" => $sale_doc[0]['customer_postal'],
			"customer_tax_office" => $sale_doc[0]['customer_tax_office'],
			"printable_id" => $sale_doc[0]['printable_id'],
			"products" =>  $products,
			"vat_analisis_products" => $vat_analisis_products];		
			
			return new Response(true, 'OK', $sale_doc);
		} else if($action=='prnsrv_sale_document_update'){
			$sale_doc = GetRequest('sale_doc');
			$mydata_provider = GetRequest('mydata_provider');
			$sale_doc = json_decode($sale_doc,true);
			$sale_document_id = $sale_doc['sale_doc_id'];
			$mark = $sale_doc['mark'];
			$uid = $sale_doc['uid'];
			$auth = $sale_doc['auth'];
			$url = $sale_doc['url'];
			$sql = 'UPDATE SALE_DOCUMENT SET 
						mydata_mark='.DB::Quote($mark).', 
						mydata_uid='.DB::Quote($uid).', 
						mydata_authentication_code='.DB::Quote($auth).', 
						mydata_qr_url='.DB::Quote($url).',
						mydata_provider='.$mydata_provider.',
						date_printed=NOW() 
					where id='.$sale_document_id;
			DB::Query($sql);
			return new Response(true, 'OK', 'ok');
		}else if($action=='prnsrv_req_xml'){
			//$company_id = GetRequest('company_id');
			$sql = 'SELECT id FROM SALE_DOCUMENT where request_xml=1 and date_printed is not null limit 1;';
			$result = DB::Query($sql);
			if($result){$requested_invoice = $result[0]['id'];} else {$requested_invoice = -1;}
			return new Response(true, 'OK', $requested_invoice);
		}else if($action=='prnsrv_get_xml'){
			$sale_document_id = GetRequest('sale_document_id');
			
			if(isset($_FILES['mydata_xml']['tmp_name'])){
				$mydata_xml = $_FILES['mydata_xml']['tmp_name'];
				$mydata_xml = file_get_contents($mydata_xml);
			}else{
				$mydata_xml = -1;
			}
			
			if(isset($_FILES['peppol_xml']['tmp_name'])){
				$peppol_xml = $_FILES['peppol_xml']['tmp_name'];
				$peppol_xml = file_get_contents($peppol_xml);
			}else{
				$peppol_xml = -1;
			}
			
			if(isset($_FILES['res_xml']['tmp_name'])){
				$res_xml = $_FILES['res_xml']['tmp_name'];
				$res_xml = file_get_contents($res_xml);
			}else{
				$res_xml = -1;
			}
			
			$sql = 'UPDATE SALE_DOCUMENT SET mydata_xml='.DB::Quote($mydata_xml).', peppol_xml='.DB::Quote($peppol_xml).', response_xml='.DB::Quote($res_xml).', request_xml=0 where id='.$sale_document_id;
			DB::Query($sql);
			return new Response(true, 'OK', 'ok');
		}else if($action=='prnsrv_reprint_invoice'){
			$company_id = GetRequest('company_id');
			if(!$company_id) return new Response(true, 'OK', '1');
			
			$sql_sale_doc ='SELECT SD.id, SS.name series_name, SS.code, SS.sequence, SS.printable_id, SS.retail retail, SS.allow_mydata_send, SD.products_amount, SD.products_net_amount, SD.products_vat_amount, MYDT.code mydata_doc_type, 
							CC.name customer_name, CC.address customer_address, CC.tax_number customer_tax_number, CC.city customer_city, CC.postal customer_postal,
							CC.tax_office customer_tax_office, T.name name_table, U.name waiter_name, SD.mydata_mark, SD.mydata_qr_url FROM `SALE_DOCUMENT` SD
							inner join SALE_SERIES SS on SS.id=SD.series_id
							inner join MYDATA_DOC_TYPES MYDT on MYDT.id=SD.mydata_doc_type
							left join CUSTOMER C on C.id=SD.customer_id
							left join COMPANY_CUSTOMER CC on SD.company_id=CC.id
							inner join ORDERS O on SD.relative_order_id=O.id
                            left join ORDER_TABLE OT on O.id=OT.order_id
                            left join TABLES T on OT.table_id=T.id
							left join USER U on O.waiter_id = U.id
							where 
								SD.company_id='.$company_id.'
								and SD.reprocess_flag=1
								limit 1;';
					

			$sale_doc = DB::Query($sql_sale_doc);
			if(!$sale_doc) return new Response(true, 'OK', '2');			
					
			$sql_sale_doc_product = 'SELECT SDP.product_id, P.name_gr, count(1) quantity, sum(SDP.price) amount, SDP.price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount, SDP.vat_percent,
					MDIC.code mydata_income_category, MDIT.code mydata_income_type, VC.vat_category_mydata_code  FROM `SALE_DOCUMENT_PRODUCT` SDP
					inner join PRODUCT P on P.id=SDP.product_id
					inner join MYDATA_INCOME_CATEGORY MDIC on MDIC.id=SDP.mydata_income_category 
					inner join MYDATA_INCOME_TYPE MDIT on MDIT.id=SDP.mydata_income_type
					inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
					where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.product_id';		

			$resalt=DB::Query($sql_sale_doc_product);
			
			if(!$resalt) return new Response(true, 'OK', '3');
			
			$products = array();
			foreach($resalt as $r){
				array_push($products, ["product_name" => $r['name_gr'],
								"product_id" => $r['product_id'],
								"quantity" => $r['quantity'],
								"product_price" => $r['price'],
								"amount" => $r['amount'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount'],
								"product_vat_percent" => $r['vat_percent'],
								"vat_category_mydata_code" => $r['vat_category_mydata_code'],
								"mydata_income_category" => $r['mydata_income_category'],
								"mydata_income_type" => $r['mydata_income_type']]
						);
			}
			
			$sql_sale_doc_vat_analisis_product ='SELECT SDP.vat_percent, count(1) quntity, sum(SDP.price) price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount  FROM `SALE_DOCUMENT_PRODUCT` SDP
												inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
												where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.vat_percent';
												
			$resalt2=DB::Query($sql_sale_doc_vat_analisis_product);
			
			if(!$resalt2) return new Response(true, 'OK', '3');
			
			$vat_analisis_products = array();
			foreach($resalt2 as $r){
				array_push($vat_analisis_products, ["vat_percent" => $r['vat_percent'],
								"quntity" => $r['quntity'],
								"price" => $r['price'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount']]
				);
			}
			

			$sale_doc =["sale_doc_id" => $sale_doc[0]['id'],
			"series_name" => $sale_doc[0]['series_name'],
			"series_code" => $sale_doc[0]['code'],
			"series_sequence" => $sale_doc[0]['sequence'],
			"allow_mydata_send" => $sale_doc[0]['allow_mydata_send'],
			"retail" => $sale_doc[0]['retail'],
			"name_table" => $sale_doc[0]['name_table'],
			"waiter_name" => $sale_doc[0]['waiter_name'],
			"total_amount" => $sale_doc[0]['products_amount'],
			"total_net_amount" => $sale_doc[0]['products_net_amount'],
			"total_vat_amount" => $sale_doc[0]['products_vat_amount'],
			"mydata_doc_type" => $sale_doc[0]['mydata_doc_type'],
			"customer_name" => $sale_doc[0]['customer_name'],
			"customer_address" => $sale_doc[0]['customer_address'],
			"customer_tax_number" => $sale_doc[0]['customer_tax_number'],
			"customer_city" => $sale_doc[0]['customer_city'],
			"customer_postal" => $sale_doc[0]['customer_postal'],
			"customer_tax_office" => $sale_doc[0]['customer_tax_office'],
			"mydata_mark" => $sale_doc[0]['mydata_mark'],
			"mydata_qr_url" => $sale_doc[0]['mydata_qr_url'],
			"printable_id" => $sale_doc[0]['printable_id'],
			"products" =>  $products,
			"vat_analisis_products" => $vat_analisis_products];		
			
			return new Response(true, 'OK', $sale_doc);
		} else if($action=='prnsrv_mydata_resent_invoice'){
			
			$company_id = GetRequest('company_id');
			if(!$company_id) return new Response(true, 'OK', '1');
			
			$sql_sale_doc ='SELECT SD.id, SS.name series_name, SS.code, SS.sequence, SS.printable_id, SS.retail retail, SS.allow_mydata_send, SD.products_amount, SD.products_net_amount, SD.products_vat_amount, MYDT.code mydata_doc_type, 
							CC.name customer_name, CC.address customer_address, CC.tax_number customer_tax_number, CC.city customer_city, CC.postal customer_postal,
							CC.tax_office customer_tax_office, T.name name_table, U.name waiter_name FROM `SALE_DOCUMENT` SD
							inner join SALE_SERIES SS on SS.id=SD.series_id
							inner join MYDATA_DOC_TYPES MYDT on MYDT.id=SD.mydata_doc_type
							left join CUSTOMER C on C.id=SD.customer_id
							left join COMPANY_CUSTOMER CC on SD.company_id=CC.id
							inner join ORDERS O on SD.relative_order_id=O.id
                            left join ORDER_TABLE OT on O.id=OT.order_id
                            left join TABLES T on OT.table_id=T.id
							left join USER U on O.waiter_id = U.id
							where 
								SD.company_id='.$company_id.'
								and SD.reprocess_flag=2
								limit 1;';
					

			$sale_doc = DB::Query($sql_sale_doc);
			if(!$sale_doc) return new Response(true, 'OK', '2');			
					
			$sql_sale_doc_product = 'SELECT SDP.product_id, P.name_gr, count(1) quantity, sum(SDP.price) amount, SDP.price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount, SDP.vat_percent,
					MDIC.code mydata_income_category, MDIT.code mydata_income_type, VC.vat_category_mydata_code  FROM `SALE_DOCUMENT_PRODUCT` SDP
					inner join PRODUCT P on P.id=SDP.product_id
					inner join MYDATA_INCOME_CATEGORY MDIC on MDIC.id=SDP.mydata_income_category 
					inner join MYDATA_INCOME_TYPE MDIT on MDIT.id=SDP.mydata_income_type
					inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
					where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.product_id';		

			$resalt=DB::Query($sql_sale_doc_product);
			
			if(!$resalt) return new Response(true, 'OK', '3');
			
			$products = array();
			foreach($resalt as $r){
				array_push($products, ["product_name" => $r['name_gr'],
								"product_id" => $r['product_id'],
								"quantity" => $r['quantity'],
								"product_price" => $r['price'],
								"amount" => $r['amount'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount'],
								"product_vat_percent" => $r['vat_percent'],
								"vat_category_mydata_code" => $r['vat_category_mydata_code'],
								"mydata_income_category" => $r['mydata_income_category'],
								"mydata_income_type" => $r['mydata_income_type']]
						);
			}
			
			$sql_sale_doc_vat_analisis_product ='SELECT SDP.vat_percent, count(1) quntity, sum(SDP.price) price, sum(SDP.vat_amount) vat_amount, sum(SDP.net_amount) net_amount  FROM `SALE_DOCUMENT_PRODUCT` SDP
												inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
												where SDP.sale_document_id='.$sale_doc[0]["id"].' group by SDP.vat_percent';
												
			$resalt2=DB::Query($sql_sale_doc_vat_analisis_product);
			
			if(!$resalt2) return new Response(true, 'OK', '3');
			
			$vat_analisis_products = array();
			foreach($resalt2 as $r){
				array_push($vat_analisis_products, ["vat_percent" => $r['vat_percent'],
								"quntity" => $r['quntity'],
								"price" => $r['price'],
								"vat_amount" => $r['vat_amount'],
								"net_amount" => $r['net_amount']]
				);
			}
			

			$sale_doc =["sale_doc_id" => $sale_doc[0]['id'],
			"series_name" => $sale_doc[0]['series_name'],
			"series_code" => $sale_doc[0]['code'],
			"series_sequence" => $sale_doc[0]['sequence'],
			"allow_mydata_send" => $sale_doc[0]['allow_mydata_send'],
			"retail" => $sale_doc[0]['retail'],
			"name_table" => $sale_doc[0]['name_table'],
			"waiter_name" => $sale_doc[0]['waiter_name'],
			"total_amount" => $sale_doc[0]['products_amount'],
			"total_net_amount" => $sale_doc[0]['products_net_amount'],
			"total_vat_amount" => $sale_doc[0]['products_vat_amount'],
			"mydata_doc_type" => $sale_doc[0]['mydata_doc_type'],
			"customer_name" => $sale_doc[0]['customer_name'],
			"customer_address" => $sale_doc[0]['customer_address'],
			"customer_tax_number" => $sale_doc[0]['customer_tax_number'],
			"customer_city" => $sale_doc[0]['customer_city'],
			"customer_postal" => $sale_doc[0]['customer_postal'],
			"customer_tax_office" => $sale_doc[0]['customer_tax_office'],
			"printable_id" => $sale_doc[0]['printable_id'],
			"products" =>  $products,
			"vat_analisis_products" => $vat_analisis_products];		
			
			return new Response(true, 'OK', $sale_doc);
		} else if($action=='prnsrv_reprint_process_invoice'){ 
			
			$sale_document_id = GetRequest('sale_document_id');
			$sql = 'UPDATE SALE_DOCUMENT SET reprocess_flag=0 where id='.$sale_document_id;
			DB::Query($sql);
			return new Response(true, 'OK', 'ok');
		} else if($action=='prnsrv_mydata_resent_process_invoice'){ 
			
			$response = GetRequest('response');
			$res = json_decode($response, true);
			$sale_document_id = $res['sale_doc_id'];
			$mydata_provider = GetRequest('mydata_provider');
			$mark = $res['mark'];
			$uid = $res['uid'];
			$auth = $res['auth'];
			$url = $res['url'];
			$sql = 'UPDATE SALE_DOCUMENT SET 
						reprocess_flag=0, mydata_mark='.DB::Quote($mark).', 
						mydata_uid='.DB::Quote($uid).', mydata_authentication_code='.DB::Quote($auth).', 
						mydata_qr_url='.DB::Quote($url).',
						mydata_provider = '.$mydata_provider.'
					where id='.$sale_document_id;
			DB::Query($sql);
			return new Response(true, 'OK', 'ok');
		}else return Model::abstractHandleApi([
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