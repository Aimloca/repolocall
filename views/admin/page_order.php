<?
if(!Session::IsAdmin()) Redirect();

$order_id=GetRequest('id');
if(empty($order_id)) { $error=Strings::Get('error_admin_cannot_create_order'); include VIEWS_PATH . 'page_error.php'; }

$order=new Order;
if(!$order->Load(['id'=>$order_id]))  { $error=Strings::Get('error_order_not_found'); include VIEWS_PATH . 'page_error.php'; }
$order->GetData(true);
$order->has_changes=false;

$company=new Company;
$tables=$company->Load(['id' => $order->company_id]) ? $company->GetTables() : [];

// Get waiters
$all_waiters=$company->GetWaiters();
$all_waiters_options='<option value="" ' . (empty($order->waiter_id) ? 'selected="selected"' : '') . '>' . Strings::Get('without_waiter') . '</option>' . PHP_EOL;
foreach($all_waiters as $waiter) $all_waiters_options.='<option value="' . $waiter->id . '" ' . ($order->waiter_id==$waiter->id ? 'selected="selected"' : '') . '>' . Html_Entities($waiter->name) . '</option>' . PHP_EOL;

$order_product_row_new=new OrderProduct;

// Print page head
PrintPageHead(Strings::Get('page_title_order'));

include VIEWS_PATH . 'widget_order.php';