<?
if(!Session::IsAdmin()) { Account::Logout(); Redirect(); }

// Print page head
PrintPageHead(Strings::Get('page_title_home'));

// Count database objects
$sql="SELECT
		(SELECT count(id) FROM COMPANY) AS total_companies,
		(SELECT count(id) FROM USER) AS total_users,
		(SELECT count(id) FROM ORDERS) AS total_orders,
		(SELECT count(id) FROM PRODUCT_CATEGORY) AS total_products_categories,
		(SELECT count(id) FROM PRODUCT) AS total_products,
		(SELECT count(id) FROM CUSTOMER) AS total_customers,
		(SELECT count(id) FROM STRINGS WHERE en IS NULL OR en='') AS total_empty_strings
	;";

$row=DB::Query($sql);
$total_companies=empty($row) || empty($row[0]['total_companies']) ? 0 : $row[0]['total_companies'];
$total_users=empty($row) || empty($row[0]['total_users']) ? 0 : $row[0]['total_users'];
$total_orders=empty($row) || empty($row[0]['total_orders']) ? 0 : $row[0]['total_orders'];
$total_products_categories=empty($row) || empty($row[0]['total_products_categories']) ? 0 : $row[0]['total_products_categories'];
$total_products=empty($row) || empty($row[0]['total_products']) ? 0 : $row[0]['total_products'];
$total_devices=empty($row) || empty($row[0]['total_devices']) ? 0 : $row[0]['total_devices'];
$total_customers=empty($row) || empty($row[0]['total_customers']) ? 0 : $row[0]['total_customers'];
$total_empty_strings=empty($row) || empty($row[0]['total_empty_strings']) ? 0 : $row[0]['total_empty_strings'];

// Warnings
$warnings=[];
$sql="SELECT * FROM COMPANY WHERE active=1 ORDER BY name_" . Strings::GetLanguage() . ";";
if($rows=DB::Query($sql)) foreach($rows as $row) {
	$c=new Company;
	$c->CreateFromArray($row);
	$w=$c->GetWarnings();
	if(!empty($w)) $warnings[$row['name_' . Strings::GetLanguage()] . " ({$row['id']})"]=$w;
}

// Errors
$errors_log_size=GetFileSize(ERRORS_LOG_FILE);
$error_log_changed=$errors_log_size>Session::Get('errors_log_size');

?>

	<body>
		<?=GetPageTopMenu('home')?>

		<div class="container-fluid text-center">
		  <div class="row content">
			<div class="app-page-content">

				<!-- Companies -->
				<div class="home_box">
					<h2><?=Strings::Get('home_companies')?></h2>
					<h1><?=$total_companies?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>company/list';">
				</div>
				<!-- Users -->
				<div class="home_box">
					<h2><?=Strings::Get('home_users')?></h2>
					<h1><?=$total_users?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>user/list';">
				</div>
				<!-- Orders -->
				<div class="home_box">
					<h2><?=Strings::Get('home_orders')?></h2>
					<h1><?=$total_orders?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>order/list';">
				</div>
				<!-- Products categories -->
				<div class="home_box">
					<h2><?=Strings::Get('home_products_categories')?></h2>
					<h1><?=$total_products_categories?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>product_category/list';">
				</div>
				<!-- Products  -->
				<div class="home_box">
					<h2><?=Strings::Get('home_products')?></h2>
					<h1><?=$total_products?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>product/list';">
				</div>
				<!-- Customers  -->
				<div class="home_box">
					<h2><?=Strings::Get('home_customers')?></h2>
					<h1><?=$total_customers?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>customer/list';">
				</div>
				<? if($total_empty_strings) { ?>
				<!-- Empty strings  -->
				<div class="home_box red_border">
					<h2><?=Strings::Get('home_empty_strings')?></h2>
					<h1><?=$total_empty_strings?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>admin/strings';">
				</div>
				<? } ?>
				<? if($error_log_changed) { ?>
				<!-- Errors  -->
				<div class="home_box red_border">
					<h2><?=Strings::Get('home_errors')?></h2>
					<h1><?=$errors_log_size?></h1>
					<input type="button" value="<?=Strings::Get('home_view')?>" onclick="window.stop(); window.location='<?=BaseUrl()?>report/errors_log';">
				</div>
				<? } ?>

				<? if($warnings) { ?>
				<!-- Warnings -->
				<style>
					#warnings { max-width: unset; width: 96% !important; }
					.warnings_shop_group { margin-bottom: 10px; padding: 10px; border-bottom: 1px solid red; }
					.warnings_shop_group:last-child { border-bottom: none; }
					.warnings_shop_title { font-size: x-large; text-align: left; font-weight: bold; }
					.warnings_shop_warning { font-size: large; text-align: center; }
				</style>
				<div id="warnings" class="home_box red_border">
					<? foreach($warnings as $warning_company_id=>$ws) { ?>
					<div class="warnings_shop_group">
						<div class="warnings_shop_title"><?=Html_Entities($warning_company_id)?></div>
						<div>
						<? foreach($ws as $w) echo empty($w['link']) ? '<div class="warnings_shop_warning">' . Html_Entities($w['title']) . '</div>' : '<a href="' . $w['link'] . '" class="warnings_shop_warning">' . Html_Entities($w['title']) . '</a><br />'; ?>
						</div>
					</div>
					<? } ?>
				</div>
				<? } ?>
			</div>
		  </div>
		</div>

<?

// Print page footer
PrintPageFooter();
