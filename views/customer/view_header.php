<?

?>
<style>
	#visitor_page_header_underlay { position: relative; display: block; width: 100%; height: 70px; }
	#visitor_page_header { position: fixed; left: 0; top: 0; right: 0; width: 100%; padding: 5px; display: flex; flex-direction: row; background-color: black; overflow: hidden; z-index: 2; }
	.visitor_page_header_cell { flex: 1; text-align: center; overflow: hidden; transition: 0.3s; }
	.visitor_page_header_cell:hover { background-color: #222222; }
	.visitor_page_header_cell_box { display: inline-block; position: relative; width: 70px; height: 50px; padding: 5px; border: 1px solid #ccc; text-align: center; overflow: hidden; border-radius: 10px; }
	.visitor_page_header_cell_box.selected { background-color: #444; }
	.visitor_page_header_cell_box_icon { width: 30px; height: 30px; }
	.visitor_page_header_cell_box_badge { display: none; position: absolute; top: 0; right: 0px; width: 20px; height: 20px; line-height: 20px; border-radius: 100px; font-size: x-small; color: white; background-color: red; text-align: center; overflow: hidden; }
	.visitor_page_header_cell_box_text { font-size: xx-small; color: #ccc; text-align: center; }
	.visitor_page_header_overflow_container { display: none; position: fixed; left: 0; top: 0; right: 0; bottom: 0; z-index: 3; }
	.visitor_page_header_overflow_menu { display: relative; position: absolute; top: 50px; right: 0; padding: 5px 5px 15px 5px; color: #ccc; background-color: black; border: 1px solid #222; border-bottom-left-radius: 5px; border-bottom-right-radius: 5px; overflow: hidden;  }
	.visitor_page_header_overflow_menu_label { padding: 5px; text-align: center; border-top: 1ps solid #888; overflow: hidden; }
	.visitor_page_header_overflow_menu_languages { display: flex; flex-direction: row; gap: 5px; align-items: center; overflow: hidden;  }
	.visitor_page_header_overflow_menu_languages div { flex: 1; text-align: center; overflow: hidden;  }
	.visitor_page_header_overflow_menu_languages div img { width: 40px; height: 40px; overflow: hidden;  }
	.visitor_page_header_overflow_menu_waiters { display: flex; flex-direction: row; gap: 5px; align-items: center; overflow: hidden;  }
	.visitor_page_header_overflow_menu_waiters div { flex: 1; text-align: center; overflow: hidden;  }
	.visitor_page_header_overflow_menu_waiters div img { width: 45px; height: 45px; border-radius: 100px; overflow: hidden;  }
	.visitor_page_header_overflow_menu_call_waiter { margin: 20px 10px 0 10px; padding: 5px 15px; color: white; background-color: darkgreen; border-radius: 3px; text-align: center; text-transform: uppercase; overflow: hidden;  }
	.visitor_page_header_overflow_menu_logout { margin: 20px 10px 0 10px; padding: 5px 15px; color: white; background-color: darkred; border-radius: 3px; text-align: center; text-transform: uppercase; overflow: hidden;  }
</style>
<div id="visitor_page_header_underlay"></div>
<div id="visitor_page_header">
	<div class="visitor_page_header_cell">
		<div id="visitor_page_header_menu" class="visitor_page_header_cell_box <?=$selected_menu=='menu' ? 'selected' : ''?>">
			<img class="visitor_page_header_cell_box_icon" src="<?=IMAGES_URL?>menu_white.png" />
			<div class="visitor_page_header_cell_box_text"><?=Strings::Get('visitor_page_header_menu')?></div>
		</div>
	</div>
	<div class="visitor_page_header_cell">
		<div id="visitor_page_header_order" class="visitor_page_header_cell_box <?=$selected_menu=='order' ? 'selected' : ''?>">
			<img class="visitor_page_header_cell_box_icon" src="<?=IMAGES_URL?>order_white.png" />
			<div class="visitor_page_header_cell_box_badge" id="header_order_badge"></div>
			<div class="visitor_page_header_cell_box_text"><?=Strings::Get('visitor_page_header_order')?></div>
		</div>
	</div>
	<div class="visitor_page_header_cell">
		<div id="visitor_page_header_bill" class="visitor_page_header_cell_box <?=$selected_menu=='cart' ? 'selected' : ''?>">
			<img class="visitor_page_header_cell_box_icon" src="<?=IMAGES_URL?>cart_white.png" />
			<div class="visitor_page_header_cell_box_badge" id="header_bill_badge"></div>
			<div class="visitor_page_header_cell_box_text"><?=Strings::Get('visitor_page_header_cart')?></div>
		</div>
	</div>
	<div class="visitor_page_header_cell">
		<div id="visitor_page_header_settings" class="visitor_page_header_cell_box <?=$selected_menu=='settings' ? 'selected' : ''?>">
			<img class="visitor_page_header_cell_box_icon" src="<?=IMAGES_URL?>settings_white.png" />
			<div class="visitor_page_header_cell_box_text"><?=Strings::Get('visitor_page_header_settings')?></div>
		</div>
	</div>
</div>
<div class="visitor_page_header_overflow_container">
	<div class="visitor_page_header_overflow_menu">
		<div class="visitor_page_header_overflow_menu_label"><?=Strings::Get('change_language')?></div>
		<div class="visitor_page_header_overflow_menu_languages">
			<? foreach(LANGUAGES as $language) { ?>
			<div lang="<?=$language?>"><img id="visitor_page_header_overflow_menu_language_<?=$language?>" src="<?=IMAGES_URL?>language_<?=$language?>.png" /></div>
			<? } ?>
		</div>
		<?if(isset($table)) { ?>
			<?if($table->waiters && 1==2) { ?>
			<div class="visitor_page_header_overflow_menu_label"><?=Strings::Get('call_waiter')?></div>
			<div class="visitor_page_header_overflow_menu_waiters">
				<?foreach($table->waiters as $waiter_index=>$waiter) { ?>
				<div waiter_id="<?=$waiter->id?>"><img src="<?=$waiter->icon ? IMAGES_DATA_URL . "USER.icon.{$waiter->id}" : IMAGES_URL . "no_user_image.png"?>" /></div>
				<? } ?>
			</div>
			<? } else if($table->users_with_customer_notification_ids) { ?>
			<div class="visitor_page_header_overflow_menu_call_waiter"><?=Strings::Get('call_waiter')?></div>
			<? } ?>
		<? } ?>
		<div class="visitor_page_header_overflow_menu_logout"><?=Strings::Get('menu_logout')?></div>
	</div>
</div>
<script>

	<?if(isset($table) && ($table->waiters || $table->users_with_customer_notification_ids)) { ?>
	function CallWaiter(waiter_id='') {
		ShowLoader();
		Post('<?=API_URL?>',
			{ controller: 'customer', action: 'call_waiter', table_id: '<?=$table->id?>', waiter_id: waiter_id },
			function(response) {
				HideLoader();
				if(response==undefined || response==null || response.status==undefined) {
					alert('<?=Strings::Get('error_invalid_server_response')?>');
				} else if(response.status) {
					ShowModal('<?=Strings::Get('call_waiter')?>', response.message ?? '<?=Strings::Get('error_calling_waiter')?>');
				} else {
					ShowModal('<?=Strings::Get('call_waiter')?>', response.message ?? '<?=Strings::Get('error_calling_waiter')?>');
				}
			}
		);
	}
	<? } ?>
	$(document).ready(function(){
		$('#visitor_page_header_menu').click(function(e){
			window.location='<?=BaseUrl()?>menu/view/';
		});
		$('#visitor_page_header_order').click(function(e){
			window.location='<?=BaseUrl()?>order/view/';
		});
		$('#visitor_page_header_bill').click(function(e){
			window.location='<?=BaseUrl()?>bill/view/';
		});
		$('#visitor_page_header_settings').click(function(e){
			$('.visitor_page_header_overflow_container').show();
		});
		$('.visitor_page_header_overflow_container').click(function(e){
			e.stopPropagation(); e.preventDefault();
			$(this).fadeOut('fast');
		});
		$('.visitor_page_header_overflow_menu_languages div').click(function() {
			$('#loader_container').fadeIn();
			const lang=$(this).attr('lang');
			ShowLoader();
			window.location.href='?lang=' + lang;
		});

		<?if(isset($table) && ($table->waiters || $table->users_with_customer_notification_ids)) { ?>
		$(".visitor_page_header_overflow_menu_call_waiter, .visitor_page_header_overflow_menu_waiters div[waiter_id]").click(function(){ CallWaiter($(this).attr('waiter_id')); });
		<? } ?>

		$(".visitor_page_header_overflow_menu_logout").click(function(){
			if(!confirm("<?=Strings::Get('menu_logout_message')?>")) return;
			$.removeCookie('menu_scroll_top', { path: '/' });
			window.location='<?=BASE_URL?>?/account/logout';
		});
		$('.visitor_page_header_underlay').css('height', $('#visitor_page_header').outerHeight() + 'px');
		$('.visitor_page_header_overflow_menu').css('top', $('#visitor_page_header').outerHeight() + 'px');
	});
</script>