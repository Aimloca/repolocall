<?
// Load functions
include_once 'app/functions.php';

// Load configuration
include_once 'app/config.php';

// Load autogen
if(!file_exists(AUTOGEN_PATH . 'includes.php')) AutogenIncludes();
include_once AUTOGEN_PATH . 'includes.php';
if(!file_exists(AUTOGEN_PATH . 'db_tables.php')) AutogenDBTables();
include_once AUTOGEN_PATH . 'db_tables.php';
if(!file_exists(BASE_PATH . '/assets/js/autogen_strings.js')) AutogenJsStrings();
if(!file_exists(BASE_PATH . '/assets/js/autogen_tables.js')) AutogenJsTables();
if(!file_exists(BASE_PATH . '/assets/js/autogen_models.js')) AutogenJsModels();

$sql=GetRequest('sql');
if(empty($sql)) die('1');

$sale_doc=DB::Query($sql);
if(!$sale_doc) die('2');

$sql = 'SELECT P.name_gr, SDP.vat_amount, SDP.net_amount, SDP.vat_percent, MDIC.code as mydata_income_category, MDIT.code as mydata_income_type, VC.vat_category_mydata_code  FROM `SALE_DOCUMENT_PRODUCT` SDP
		inner join PRODUCT P on P.id=SDP.product_id
		inner join MYDATA_INCOME_CATEGORY MDIC on MDIC.id=SDP.mydata_income_category 
		inner join MYDATA_INCOME_TYPE MDIT on MDIT.id=SDP.mydata_income_type
		inner join VAT_CATEGORY VC on VC.percent=SDP.vat_percent
		where SDP.sale_document_id='.$sale_doc[0]["id"];

$resalt=DB::Query($sql);
if(!$resalt) die('3');

$products = array();
foreach($resalt as $r){
	array_push($products, ["name" => $r['name_gr'], 
							"vat_amount" => $r['vat_amount'],
							"net_amount" => $r['net_amount'],
							"vat_category_mydata_code" => $r['vat_category_mydata_code'],
							"mydata_income_category" => $r['mydata_income_category'],
							"mydata_income_type" => $r['mydata_income_type']] 
			);
}

$sale_doc = ["sale_doc_id" => $sale_doc[0]['id'],
 "series_code" => $sale_doc[0]['code'],
 "series_sequence" => $sale_doc[0]['sequence'],
 "total_amount" => $sale_doc[0]['products_amount'],
 "total_net_amount" => $sale_doc[0]['products_net_amount'],
 "total_vat_amount" => $sale_doc[0]['products_vat_amount'],
 "mydata_doc_type" => $sale_doc[0]['mydata_doc_type'],
 "tax_number" => $sale_doc[0]['tax_number'],
 "city" => $sale_doc[0]['city'],
 "country_iso_code" => $sale_doc[0]['country_iso_code'],
 "products" =>  $products];

header('Content-Type: application/json; charset=utf-8');
ToJson($sale_doc, true);

?>