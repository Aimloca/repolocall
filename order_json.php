<?php
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

$order_details=DB::Query($sql);
if(!$order_details) die('2');
	
/* $sql = 'select OP.id, P.name_gr, OP.comment, JSON_ARRAYAGG(S.name_gr) spec, D.printable_id from ORDERS O 
	inner join ORDER_PRODUCT OP on O.id = OP.order_id 
	inner join PRODUCT P on OP.product_id = P.id 
	left join ORDER_PRODUCT_SPEC as OPS on OP.id = OPS.order_product_row_id
	inner join DEPARTMENT D on D.id = OP.sent_to_department_id	
	left join SPEC S on OPS.product_spec_id = S.id 
	where O.id = '.$order_details[0]["id"].' and O.completed = 0 and O.date_canceled is null and (OP.sent = 1 and OP.date_printed is null) group by OP.id order by O.id;'; */
	

$sql = 'select OP.id, P.name_gr, OP.comment, JSON_ARRAYAGG(S.name_gr) spec, D.printable_id from ORDERS O 
	inner join ORDER_PRODUCT OP on O.id = OP.order_id 
	inner join PRODUCT P on OP.product_id = P.id 
	left join ORDER_PRODUCT_SPEC as OPS on OP.id = OPS.order_product_row_id	
	left join SPEC S on OPS.product_spec_id = S.id
    inner join PRODUCT_CATEGORY PC on PC.id= P.category_id
    inner join DEPARTMENT D on D.id = PC.department_id
	where O.id = '.$order_details[0]["id"].' and O.completed = 0 and O.date_canceled is null and (OP.sent = 1 and OP.date_printed is null) group by OP.id order by O.id;';

	
$resalt=DB::Query($sql);
if(!$resalt) die('3');

$products = array();
foreach($resalt as $r){
	if($r['spec'] == "[null]"){
		$specs = json_decode("[]",true);
	}else{
		$specs = json_decode($r['spec'],true);
	}
	array_push($products,[ "id" => $r['id'], "name" => $r['name_gr'], "specs" => $specs , "comment" => $r['comment'], "printable_id" => $r['printable_id'] ]);
}

$order = ["order_id" => $order_details[0]['id'], "waiter" => $order_details[0]['waiter_name'], "table" => $order_details[0]['name_table'], "products" =>  $products];

header('Content-Type: application/json; charset=utf-8');
ToJson($order, true);

?>