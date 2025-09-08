<?
// Warning: this is a view not a page

// Check account
if(!Session::IsAdmin()) return;

$exclamation_badge='<span style="color: red; font-size: small;" class="glyphicon glyphicon-info-sign"></span>';
$errors_log_size=GetFileSize(ERRORS_LOG_FILE);
$error_log_changed=$errors_log_size>Session::Get('errors_log_size');

?>

<!-- Top menu widget : Start -->
<nav class="navbar navbar-inverse">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#top_navbar">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="<?=BaseUrl()?>"><img id="top_navbar_logo" src="<?=IMAGES_URL?>app_logo.png" height="50" /></a>
        </div>
        <div class="collapse navbar-collapse" id="top_navbar">
            <ul class="nav navbar-nav">
                <li <?=($selected_menu=='' || $selected_menu=='home' ? 'class="active"' : '')?>><a href="<?=BaseUrl()?>"><?=Strings::Get('menu_home')?></a></li>

                <li class="dropdown <?=$selected_menu=='entities' ? 'active' : ''?>">
                    <a href="#"><?=Strings::Get('menu_entities')?></a>
                    <div class="dropdown-content">
                        <a href="<?=BaseUrl()?>admin/list"><?=Strings::Get('menu_admins')?></a>
                        <a href="<?=BaseUrl()?>company/list"><?=Strings::Get('menu_companies')?></a>
                        <a href="<?=BaseUrl()?>department/list"><?=Strings::Get('menu_departments')?></a>
                        <a href="<?=BaseUrl()?>user/list"><?=Strings::Get('menu_users')?></a>
                        <a href="<?=BaseUrl()?>order/list"><?=Strings::Get('menu_orders')?></a>
                        <a href="<?=BaseUrl()?>product_category/list"><?=Strings::Get('menu_products_categories')?></a>
                        <a href="<?=BaseUrl()?>product/list"><?=Strings::Get('menu_products')?></a>
                       <!-- <a href="<?=BaseUrl()?>device/list"><?=Strings::Get('menu_devices')?></a>-->
                        <a href="<?=BaseUrl()?>room/list"><?=Strings::Get('menu_rooms')?></a>
                        <a href="<?=BaseUrl()?>table/list"><?=Strings::Get('menu_tables')?></a>
                        <a href="<?=BaseUrl()?>spec/list"><?=Strings::Get('menu_specs')?></a>
                        <a href="<?=BaseUrl()?>unit/list"><?=Strings::Get('menu_units')?></a>
                        <a href="<?=BaseUrl()?>vat_category/list"><?=Strings::Get('menu_vat_categories')?></a>
                        <a href="<?=BaseUrl()?>tip/list"><?=Strings::Get('menu_tips')?></a>
                        <a href="<?=BaseUrl()?>company_customer/list"><?=Strings::Get('menu_company_customers')?></a>
                        <a href="<?=BaseUrl()?>customer/list"><?=Strings::Get('menu_customers')?></a>
                    </div>
                </li>
                <li class="dropdown <?=($selected_menu=='series_documents' ? 'active' : '')?>" style="display: none;">
                    <a href="#"><?=Strings::Get('menu_series_documents')?></a>
                    <div class="dropdown-content">
                        <!--<a href="<?=BaseUrl()?>buy_series/list"><?=Strings::Get('menu_buy_series')?></a>-->
                        <!--<a href="<?=BaseUrl()?>buy_document/list"><?=Strings::Get('menu_buy_documents')?></a>-->
                        <a href="<?=BaseUrl()?>sale_series/list"><?=Strings::Get('menu_sale_series')?></a>
                        <a href="<?=BaseUrl()?>sale_document/list"><?=Strings::Get('menu_sale_documents')?></a>
                        <!--<a href="<?=BaseUrl()?>stock_transaction/list"><?=Strings::Get('menu_stock_transactions')?></a>-->
                        <!--<a href="<?=BaseUrl()?>user/cash"><?=Strings::Get('menu_cash')?></a>-->
                        <a href="<?=BaseUrl()?>day_end/list"><?=Strings::Get('menu_day_ends')?></a>
                    </div>
                </li>
                <li class="dropdown <?=($selected_menu=='reports' ? 'active' : '')?>">
                    <a href="#"><?=Strings::Get('menu_reports')?></a>
                    <div class="dropdown-content">
                        <a href="<?=BaseUrl()?>report/commissions"><?=Strings::Get('menu_reports_commissions')?></a>
                        <a href="<?=BaseUrl()?>report/parameters"><?=Strings::Get('menu_reports_parameters')?></a>
                        <!--<a href="<?=BaseUrl()?>report/orders"><?=Strings::Get('menu_reports_orders')?></a>-->
                    </div>
                </li>
                <li class="dropdown <?=($selected_menu=='maintenance' ? 'active' : '')?>">
                    <a href="#"><?=Strings::Get('menu_maintenance') . ($error_log_changed ? $exclamation_badge : '')?></a>
                    <div class="dropdown-content">
                        <a href="<?=BaseUrl()?>notification/list"><?=Strings::Get('menu_notifications')?></a>
                        <a href="javascript:ModalAutoGen();"><?=Strings::Get('menu_create_autogen')?></a>
                        <a href="<?=BaseUrl()?>report/errors_log"><?=Strings::Get('menu_maintenance_error_log')?> (<?=$errors_log_size?>)<?=($error_log_changed ? $exclamation_badge : '')?></a>
                        <a href="<?=BaseUrl()?>admin/strings"><?=Strings::Get('menu_admin_strings')?></a>
                    </div>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li><? foreach(LANGUAGES as $language) if(Strings::GetLanguage()!=$language) echo '<img class="top_menu_language_image" lang="' . $language . '" src="' . IMAGES_URL . 'language_' . $language . '.png" />'; ?></li>
                <li class="dropdown">
                    <a href="#" style="padding: 0 0 0 14px; white-space: nowrap;"><span style="color: black"><?=Html_Entities(Session::Account()->name)?></span><img id="top_menu_user_logo" src="<?=(isset(Session::Account()->icon) && Session::User()->icon ? IMAGES_DATA_URL . 'USER.icon.'  . Session::UserId() : IMAGES_URL . 'user_icon_white.png')?>" /></a>
                    <div class="dropdown-content">
                        <a id="top_menu_notifications"><?=Strings::Get('menu_notifications')?></a>
                        <a id="top_menu_user_logout"><?=Strings::Get('menu_logout')?></a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div id="alerts_top"></div>
<img id="page_backdrop" src="<?=IMAGES_URL?>backdrop.jpg" />
<img id="page_backdrop"/>
<script>

    function ModalAutoGen() {
        ShowModal('AuroGen', '<iframe id="autogen_viewer" src="<?=BASE_URL?>admin/create_autogen" width="100%" height="500"></iframe>');
        $('.modal-dialog').css('width', '90%');
        $('#autogen_viewer').css('height', ($(window).height() * 0.7) + 'px');
        setTimeout(function() { let $contents = $('#autogen_viewer').contents(); $contents.scrollTop($contents.height()); }, 1000);
    }

    $(document).ready(function() {

        $(".navbar a").click(function(){ window.stop(); });

        $("#top_menu_user_logout").click(function(){
            if(!confirm("<?=Session::Account()->name?>\n<?=Strings::Get('menu_logout_message')?>")) return;
            window.location="<?=BASE_URL?>?/account/logout";
        });

        $("#top_menu_notifications").click(function(){
            if(typeof GetNotifications === 'function') {
                force_show_list=true;
                GetNotifications();
                $('#view_notifications_list').show();
            }
        });

        $(".top_menu_language_image").click(function(){
            window.location.href = '<?=BASE_URL?>?lang=' + $(this).attr('lang');
        });

    });
</script>
<!-- Top menu widget : End -->


