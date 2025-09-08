function CustomTable(element_id, language_strings, base_url, api_url, page_controller, user_can_edit=true, user_can_delete=true) {

	var ct=this;

	ct.base_url=base_url ? base_url : ''; if(!base_url) console.log('No base url passed');
	ct.api_url=api_url ? api_url : ''; if(!api_url) console.log('No API url passed');
	ct.page_controller=page_controller ? page_controller : ''; if(!page_controller) console.log('No page controller passed');
	ct.can_edit=user_can_edit;
	ct.can_delete=user_can_delete;

	var IsArray=function(look) {
		return IsSet(look) && Array.isArray(look);
	}

	var IsSet=function(look) {
		return look!=undefined && look!=null;
	}

	var IsEmpty=function(look) {
		return IsArray(look) ? look.length<=0 : !IsSet(look) || look=='';
	}

	var SetContainerId=function(element_id) {
		ct.element_id=element_id;
		ct.element=$('#' + element_id)[0];
		if(!IsSet(ct.element)) {
			console.log(ct.element_id + ': ' + GetString('element_with_id') + ' ' + ct.element_id + ' ' + GetString('not_found'));
		} else {
			ct.instance='js_custom_table_' + element_id;
			$(window).attr(ct.instance, ct);
		}
	}

	ct.strings=JSON.parse(IsSet(language_strings) ? language_strings : {
		'element_with_id': 'Element with id',
		'not_found': 'not found',
		'passed_fields_is_not_array': 'Passed fields is not array',
		'passed_data_is_not_array': 'Passed data is not array',
		'no_fields_are_set': 'No fields are set',
		'no_ajax_url_is_set': 'No ajax url is set.',
	});

	var GetString=function(string) {
		return ct.strings.hasOwnProperty(string) ? ct.strings[string] : window.GetString(string);
	}

	ct.SetString=function(string, value) {
		return ct.strings[string]=value;
	}

	ct.SetStrings=function(language_strings) {
		ct.strings=language_strings;
	}

	ct.UseCookies=function(use_cookies) {
		if(IsSet(use_cookies)) ct.use_cookies=use_cookies;
		return ct.use_cookies;
	}

	ct.UseFilterCookies=function(use_filter_cookies) {
		if(IsSet(use_filter_cookies)) ct.use_filter_cookies=use_filter_cookies;
		return ct.use_filter_cookies;
	}

	ct.CanDelete=function(can) {
		ct.can_delete=can ? true : false;
	}

	ct.CanEdit=function(can) {
		ct.can_edit=can ? true : false;
	}

	SetContainerId(element_id);

	const default_use_cookies=true;
	const default_use_filter_cookies=true;
	const default_page=1;
	const default_page_records=100;
	const default_sort_index=0;
	const default_sort_type=0;
	const default_column_filters=[];
	const default_is_multiselect=true;
	const default_select_all=false;
	const default_top_buttons=[];
	const default_ajax_url='';
	const default_edit_button_url='';
	const default_delete_button_url='';
	const default_data=null;
	const default_frame_drawn=false;
	const default_show_export_buttons=true;
	const default_is_modal=false;
	const default_title='';
	const default_cookie_expiration=10;

	ct.use_cookies=default_use_cookies;
	ct.use_filter_cookies=default_use_filter_cookies;
	ct.page=default_page;
	ct.page_records=default_page_records;
	ct.sort_index=default_sort_index;
	ct.sort_type=default_sort_type;
	ct.column_filters=default_column_filters;
	ct.is_multiselect=default_is_multiselect;
	ct.select_all=default_select_all;
	ct.top_buttons=default_top_buttons;
	ct.ajax_url=default_ajax_url;
	ct.edit_button_url=default_edit_button_url;
	ct.delete_button_url=default_delete_button_url;
	ct.data=default_data;
	ct.frame_drawn=default_frame_drawn;
	ct.show_export_buttons=default_show_export_buttons;
	ct.is_modal=default_is_modal;
	ct.title=default_title;
	ct.cookie_expiration=default_cookie_expiration;

	ct.SetCookieExpiration=function(cookie_expiration) {
		if(IsSet(cookie_expiration)) ct.cookie_expiration=cookie_expiration;
		return ct.cookie_expiration;
	}

	ct.Title=function(title) {
		if(IsSet(title)) ct.title=title;
		return ct.title;
	}

	ct.BelowTitle=function(below_title) {
		if(IsSet(below_title)) ct.below_title=below_title;
		return ct.below_title;
	}

	ct.SetContainer=function(element_id) {
		SetContainerId(element_id);
	}

	ct.Load=function() {
		if(ct.use_cookies) ct.sort_index=$.cookie(ct.element_id + '_' + ct.page_controller + '_sort_index');
		if(IsEmpty(ct.sort_index)) ct.sort_index=default_sort_index;
		if(ct.use_cookies) ct.sort_type=$.cookie(ct.element_id + '_' + ct.page_controller + '_sort_type');
		if(IsEmpty(ct.sort_type)) ct.sort_type=default_sort_type;
		if(ct.use_cookies && ct.use_filter_cookies) {
			var cookie_value=$.cookie(ct.element_id + '_' + ct.page_controller + '_column_filters');
			ct.column_filters=cookie_value ? JSON.parse(cookie_value) : default_column_filters;
		}
		if(IsEmpty(ct.column_filters)) ct.column_filters=default_column_filters;
		if(ct.use_cookies) ct.page=$.cookie(ct.element_id + '_' + ct.page_controller + '_page');
		if(IsEmpty(ct.page)) ct.page=default_page; else ct.page=Math.max(ct.page, 1);
		ct.page_records=$.cookie(ct.element_id + '_' + ct.page_controller + '_page_records');
		if(IsEmpty(ct.page_records)) ct.page_records=default_page_records;
	}

	ct.Save=function() {
		$.cookie(ct.element_id + '_' + ct.page_controller + '_page_records', ct.page_records, { expires: ct.cookie_expiration });
		if(!ct.use_cookies) return;
		if(ct.use_filter_cookies) $.cookie(ct.element_id + '_' + ct.page_controller + '_column_filters', JSON.stringify(ct.column_filters), { expires: ct.cookie_expiration });
		$.cookie(ct.element_id + '_' + ct.page_controller + '_sort_index', ct.sort_index, { expires: ct.cookie_expiration });
		$.cookie(ct.element_id + '_' + ct.page_controller + '_sort_type', ct.sort_type, { expires: ct.cookie_expiration });
		$.cookie(ct.element_id + '_' + ct.page_controller + '_page', ct.page, { expires: ct.cookie_expiration });
	}

	ct.Load();
	ct.Save();

	ct.SetPage=function(page) {
		ct.page=Math.max(page, 1);
		ct.Save();
		ct.Draw();
	}

	ct.SetPageRecords=function(page_records) {
		ct.page_records=page_records;
		ct.page=1;
		ct.Save();
		ct.Draw();
	}

	ct.SetSortIndex=function(sort_index) {
		ct.sort_index=Math.max(sort_index, 0);
		ct.Save();
		ct.SortData();
		ct.Draw();
	}

	ct.SetSortType=function(sort_type) {
		ct.sort_type=sort_type;
		ct.Save();
		ct.SortData();
		ct.Draw();
	}

	ct.SetSort=function(sort_index, sort_type) {
		ct.sort_index=Math.max(sort_index, 0);
		ct.sort_type=sort_type;
		ct.Save();
		ct.SortData();
		ct.Draw();
	}

	ct.ShowExportButtons=function(show_export_buttons) {
		if(IsSet(show_export_buttons)) ct.show_export_buttons=show_export_buttons;
		return ct.show_export_buttons;
	}

	ct.HeaderClicked=function(sort_index) {
		if(sort_index<0 || sort_index>=ct.fields.length) return;
		if(ct.sort_index==sort_index) {
			ct.sort_type=ct.sort_type==1 ? 0 : 1;
		} else {
			ct.sort_index=sort_index;
			ct.sort_type=0;
		}
		ct.Save();
		ct.SortData();
		ct.Draw();
	}

	ct.SetFields=function(fields) {
		ct.fields=fields;
		if(!IsArray(ct.fields)) {
			console.log(ct.element_id + ': ' + GetString('passed_fields_is_not_array') + '\n' + fields);
			return;
		}
		ct.fields.forEach(function(field, index) {
			field.index=index;
			if(IsEmpty(field.name)) field.name='custom_field_' + index;
			if(IsEmpty(field.header)) field.header='not set';
			if(IsEmpty(field.type)) field.type='text';
			if(IsEmpty(field.width)) field.width='100px';
		});
	}

	ct.GetFields=function() {
		return ct.fields;
	}

	ct.SetData=function(data) {
		if(!IsArray(ct.data)) {
			console.log(ct.element_id + ': ' + GetString('passed_data_is_not_array') + '\n' + data);
			return;
		}
		ct.data=data;
	}

	ct.SetAjaxUrl=function(ajax_url, redraw, reset) {
		ct.ajax_url=ajax_url;
		ct.data=null;
		if(IsSet(reset) && reset) ct.Reset();
		if(IsSet(redraw) && redraw) ct.ForceDraw();
	}

	ct.SetEditButtonUrl=function(edit_button_url) {
		ct.edit_button_url=edit_button_url;
	}

	ct.SetDeleteButtonUrl=function(delete_button_url) {
		ct.delete_button_url=delete_button_url;
	}

	ct.SetTopButtons=function(top_buttons) {
		ct.top_buttons=top_buttons;
	}

	ct.GetTopButtons=function() {
		return ct.top_buttons;
	}

	ct.SetIsMultiselect=function(is_multiselect) {
		ct.is_multiselect=is_multiselect;
	}

	ct.Reset=function() {
		ct.page=1;
		ct.Selection('');
		$('#' + ct.element_id + ' .custom_table_search_column input').val('');
		$('#' + ct.element_id + ' .custom_table_search_column button').attr('cur_value', '-1');
		ct.RefreshClearFiltersButton();
	}

	ct.ForceDraw=function(query) {
		ct.frame_drawn=false;
		if(IsSet(query) && query) ct.data=null;
		ct.Draw(true);
	}

	ct.DrawFrame=function(force) {
		if(!IsSet(ct.element)) {
			console.log(ct.element_id + ': ' + GetString('element_with_id') + ' ' + ct.element_id + ' ' + GetString('not_found'));
			return;
		}

		if(IsEmpty(ct.fields)) {
			console.log(ct.element_id + ': ' + GetString('no_fields_are_set'));
			return;
		}

		if(ct.frame_drawn) return;

		if((IsSet(force) && force) || !$(ct.element).hasClass('custom_table')) {
			$(ct.element).removeClass('custom_table');
			$(ct.element).addClass('custom_table');

			let html='';

			// CSS
			if(IsArray(ct.fields)) {
				html+='<style class="custom_table_style">\n';
				ct.fields.forEach(function(field, index) {
					var css=':nth-child(' + (index + 1) + ') { width: ' + field.width + 'px; }\n';
					html+='#' + ct.element_id + ' .custom_table_header .custom_table_header_column' + css;
					html+='#' + ct.element_id + ' .custom_table_data_row .custom_table_data_column' + css;
					html+='#' + ct.element_id + ' .custom_table_search_row .custom_table_search_column' + css;
				});
				html+='</style>\n';
			}

			// Title
			if(!IsEmpty(ct.title))
				html+='	<div class="custom_table_title">' + ct.title + '</div>';

			// Below title
			if(!IsEmpty(ct.below_title))
				html+='	<div class="custom_table_below_title">' + ct.below_title + '</div>';

			// Pre header
			html+='	<div class="custom_table_pre_header">\
						<div class="custom_table_page_length_box">\
							<span class="glyphicon glyphicon-th-list" style="float: left; padding: 9px;"></span>\
							<select class="custom_table_page_length" title="' + GetString('records_per_page') + '">\
								<option value="10" ' + (ct.page_records==10 ? 'selected=selected' : '') + '>10</option>\
								<option value="20" ' + (ct.page_records==20 ? 'selected=selected' : '') + '>20</option>\
								<option value="40" ' + (ct.page_records==40 ? 'selected=selected' : '') + '>40</option>\
								<option value="100" ' + (ct.page_records==100 ? 'selected=selected' : '') + '>100</option>\
								<option value="200" ' + (ct.page_records==200 ? 'selected=selected' : '') + '>200</option>\
							</select> \
							' + (ct.is_multiselect ? '<button type="button" class="btn btn-default custom_table_top_button_select_deselect_all" title="' + GetString('select_deselect_all') + '"><span class="glyphicon glyphicon-check"></span></button>' : '') + '\
							<button type="button" class="btn btn-default custom_table_top_button_clear_filters"><span class="glyphicon glyphicon-trash"></span> ' + GetString('clear_filters') + '</button>\
						</div>';

			if(IsSet(ct.top_buttons) && IsArray(ct.top_buttons)) {
				html+='<div class="custom_table_top_buttons">';
				ct.top_buttons.forEach(function(button, index) {
					html+='<button type="button" class="btn btn-primary custom_table_top_button" id="custom_table_top_button_' + button.name + '" title="' + button.text + '">' + (IsEmpty(button.icon) ? '' : '<span class="glyphicon glyphicon-' + button.icon + '"></span> ') + '<span class="custom_table_button_text">' + button.text + '</span></button>';
				});
				html+='</div>\n<script>\n';
				ct.top_buttons.forEach(function(button, index) {
					if(!IsEmpty(button.action))
						html+='$("#' + ct.element_id + '").on("click", "#custom_table_top_button_' + button.name + '", function(event) { event.stopPropagation(); event.stopImmediatePropagation(); eval("' + button.action + '"); });\n';
					else if(!IsEmpty(button.url))
						html+='$("#' + ct.element_id + '").on("click", "#custom_table_top_button_' + button.name + '", function(event) { event.stopPropagation(); event.stopImmediatePropagation(); window.location="' + button.url + '"; });\n';
				});
				html+='</script>\n';
			}
			html+='</div>';

			// Header
			if(IsSet(ct.fields) && IsArray(ct.fields)) {
				html+='<div class="custom_table_header">';
				ct.fields.forEach(function(field, index) {
					html+='<div class="custom_table_header_column"><span>' + field.header + '</span></div>';
				});
				if((ct.can_edit && !IsEmpty(ct.edit_button_url)) || (ct.can_delete && !IsEmpty(ct.delete_button_url)))
					html+='<div class="custom_table_header_column custom_table_header_column_actions"></div>';
				html+='</div>';
			}

			// Search row
			if(IsSet(ct.fields) && IsArray(ct.fields)) {
				html+='<div class="custom_table_search_row">';
				ct.fields.forEach(function(field, index) {
					if(field.type=='checkbox') {
						let button_value;
						if(ct.column_filters.length>index)
							button_value=ct.column_filters[index];
						else
							button_value='-1';

						let button_text;
						if(button_value=='0')
							button_text='<span class="glyphicon glyphicon-check"></span>';
						else if(button_value=='0')
							button_text='<span class="glyphicon glyphicon-unchecked"></span>';
						else
							button_text='<font color="#DDDDDD"><span class="glyphicon glyphicon-check"></span></font>';
						html+='<div class="custom_table_search_column"><button type="button" class="btn btn-basic" cur_value="' + button_value + '">' + button_text + '</button></div>';
					} else {
						html+='<div class="custom_table_search_column"><input type="' + field.type + '" placeholder="&#128270; ' + field.header + '" value="' + (ct.column_filters[index] ? ct.column_filters[index] : '') + '" /></div>';
					}
				});
				if((ct.can_edit && !IsEmpty(ct.edit_button_url)) || (ct.can_delete && !IsEmpty(ct.delete_button_url)))
					html+='<div class="custom_table_search_column"></div>';
				html+='</div>';
			}

			// Data
			html+='<div class="custom_table_data"></div>';

			// Footer
			html+='	<div class="custom_table_footer">\n';
			if(ct.show_export_buttons)
				html+='	<div class="custom_table_footer_export_buttons"> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="xls">Excel</button> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="csv">CSV</button> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="pdf">PDF</button> \
						</div>\n';
			html+='		<div class="custom_table_footer_pagination"> \
						</div> \
					</div>';

			// Loading
			html+='<div class="custom_table_loading"></div>';

			// Scripts
			html+='	<script>\
						$("#' + ct.element_id + '").on("change", ".custom_table_page_length", function(event) { \
							event.stopPropagation();\
							event.stopImmediatePropagation();\
							' + ct.instance + '.SetPageRecords($(this).val());\
						});\
						\
						$("#' + ct.element_id + '").on("click", ".custom_table_header_column", function(event) { \
							event.stopPropagation();\
							event.stopImmediatePropagation();\
							let index=$("#' + ct.element_id + ' .custom_table_header_column").index(this);\
							' + ct.instance + '.HeaderClicked(index);\
						});\
						\
						$("#' + ct.element_id + ' .custom_table_search_column input").on("input", function(event) { \
							' + ct.instance + '.FilterChanged();\
						});\
						\
						$("#' + ct.element_id + '").on("click", ".custom_table_search_column button", function(event) { \
							let button_value=$(this).attr("cur_value");\
							if(button_value==undefined || button_value==null || button_value=="") button_value="-1";\
							button_value=parseInt(button_value) + 1;\
							if(button_value<-1 || button_value>1) button_value=-1;\
							$(this).attr("cur_value", "" + button_value);\
							' + ct.instance + '.FilterChanged();\
						});\
						\
						$("#' + ct.element_id + '").on("click", ".custom_table_footer_export_button", function(event) { \
							let export_type=$(this).attr("export_type");\
							if(export_type==undefined || export_type==null || export_type=="") return;\
							if(export_type=="csv") return ' + ct.instance + '.ExportCSV();\
							if(export_type=="xls") return ' + ct.instance + '.ExportXLS();\
							if(export_type=="pdf") return ' + ct.instance + '.ExportPDF();\
						});\
						\
						$("#' + ct.element_id + '").on("click", ".custom_table_top_button_select_deselect_all", function(event) { \
							event.stopPropagation();\
							event.stopImmediatePropagation();\
							' + ct.instance + '.ToggleSelectAll();\
						});\
						\
						$("#' + ct.element_id + '").on("click", ".custom_table_top_button_clear_filters", function(event) { \
							event.stopPropagation();\
							event.stopImmediatePropagation();\
							' + ct.instance + '.ClearFilters();\
							' + ct.instance + '.Draw(false);\
						});\
						\
						$(window).on(\"resize", function(){\
							' + ct.instance + '.FixColumnsWidth();\
						});\
						' + (ct.is_modal ? '' : '\
							$("#' + ct.element_id + '").on("dblclick", ".custom_table_data_row", function(event) { \
								event.stopPropagation(); \
								event.stopImmediatePropagation(); \
								if(!' + ct.instance + '.can_edit || IsEmpty(' + ct.instance + '.edit_button_url)) return; \
								if(IsEmpty($(this).attr("row-id"))) return; \
								$("#modal_custom_table .custom_table_data_row").removeClass("row_selected"); \
								$(this).addClass("row_selected"); \
								$("#' + ct.element_id + '").css("cursor", "wait"); \
								$(this).css("cursor", "wait"); \
								$(this).find(".custom_table_button_edit").first().click(); \
							});') + '\
					</script>';
			$('#' + ct.element_id).html(html);
			// Fix columns width
			ct.FixColumnsWidth();
		}
		ct.frame_drawn=true;
	}

	ct.Draw=function(force) {
		if(IsEmpty(ct.element)) {
			console.log(ct.element_id + ': Element with id ' + ct.element_id + ' not found');
			return;
		}

		if(IsEmpty(ct.fields)) {
			console.log(ct.element_id + ': No fields are set');
			return;
		}

		// Fix default buttons
		if(!ct.ajax_url) ct.ajax_url=ct.api_url + 'controller=' + ct.page_controller + '&action=list';
		if(!ct.edit_button_url) ct.edit_button_url=ct.base_url + '?/' + ct.page_controller + '/edit/';
		if(!ct.delete_button_url) ct.delete_button_url=ct.api_url + 'controller=' + ct.page_controller + '&action=delete';

		if(!ct.frame_drawn) ct.DrawFrame(force);

		if(IsEmpty(ct.data)) {
			ct.Query();
		} else {

			// Fix header
			ct.FixHeader();

			// Fix filter buttons
			ct.FixFilterButtons();

			// Apply filters
			var filtered_data=ApplyFilters();

			var data_html='';
			var page_first_record=((ct.page - 1) * parseInt(ct.page_records)) + 1;
			var page_last_record=parseInt(page_first_record) + parseInt(ct.page_records) - 1;
			var record_index=0;
			var previous_pages_records=0;
			var next_pages_records=0;
			var column_actions_width=(IsEmpty(ct.can_edit && ct.edit_button_url) ? 0 : 52) + (ct.can_delete && !IsEmpty(ct.delete_button_url) ? 48 : 0);

			filtered_data.forEach(function(row, row_index) {
				record_index++;
				if(record_index<page_first_record) {
					previous_pages_records++;
					return;
				}
				if(record_index>page_last_record) {
					next_pages_records++;
					return;
				}

				// Create data row
				data_html+='<div class="custom_table_data_row" row-id="' + row[0] + '" data-index="' + ct.data.indexOf(row) + '">';

				// Loop through columns
				row.forEach(function(column, column_index) {
					var output_data='';
					if(ct.fields[column_index].type=='checkbox')
						data_html+='<div class="custom_table_data_column custom_table_data_column_check"><input type="checkbox" ' + (column=='1' ? 'checked' : '') + ' disabled /></div>';
					else if(ct.fields[column_index].type=='number')
						data_html+='<div class="custom_table_data_column custom_table_data_column_number">' + column + '</div>';
					else if(ct.fields[column_index].type=='list')
						data_html+='<div class="custom_table_data_column custom_table_data_column_list">' + ct.fields[column_index].list[parseInt(column)] + '</div>';
					else
						data_html+='<div class="custom_table_data_column">' + column + '</div>';
				});

				// Add edit and delete if needed
				if((ct.can_edit && !IsEmpty(ct.edit_button_url)) || (ct.can_delete && !IsEmpty(ct.delete_button_url))) {
					data_html+='<div class="custom_table_data_column custom_table_data_actions" style="width: ' + column_actions_width + 'px;">';
					var b='';
					if(ct.can_edit && !IsEmpty(ct.edit_button_url))
						b+=(b=='' ? '' : '&nbsp;&nbsp;') + '<button type="button" class="btn btn-primary custom_table_button_edit" row-id="' + row[0] + '"><span class="glyphicon glyphicon-edit"></span></button>';
					if(ct.can_delete && !IsEmpty(ct.delete_button_url))
						b+=(b=='' ? '' : '&nbsp;&nbsp;') + '<button type="button" class="btn btn-danger custom_table_button_delete" row-id="' + row[0] + '"><span class="glyphicon glyphicon-trash"></span></button>';
					data_html+=b + '</div>';
				}
				data_html+='</div>';
			});

			// Scripts
			data_html+='<script>';
			if((ct.can_edit && !IsEmpty(ct.edit_button_url)) || (ct.can_delete && !IsEmpty(ct.delete_button_url))) {
				if(ct.can_edit && !IsEmpty(ct.edit_button_url))
					data_html+='	$("#' + ct.element_id + '").on("click", ".custom_table_button_edit", function(event) { \
										event.stopPropagation();\
										event.stopImmediatePropagation();\
										Redirect("' + ct.edit_button_url + (Includes(ct.edit_button_url, '?') ? '&' : '?') + 'id=" + $(this).attr("row-id"));\
									});';
				if(ct.can_delete && !IsEmpty(ct.delete_button_url))
					data_html+='	$("#' + ct.element_id + '").on("click", ".custom_table_button_delete", function(event) { \
										event.stopPropagation();\
										event.stopImmediatePropagation();\
										var ct=' + ct.instance + ';\
										if(confirm(GetString("delete_record_confirm") + " " + $(this).attr("row-id") + ";")) \
											ct.Post("' + ct.delete_button_url + '", { "id": $(this).attr("row-id") }, function(response) { if(response==undefined || response==null || response.status==undefined) { alert(GetString("invalid_server_response")); } else if(!response.status) { alert(response.message); } else { ' + ct.instance + '.ForceDraw(true); } }, function(jqXHR, textStatus, errorThrown) { alert(textStatus); });\
									});';

			}
			data_html+='$("#' + ct.element_id + '").on("click", ".custom_table_data_row", function(event) { \
							event.stopPropagation();\
							event.stopImmediatePropagation();\
							if($(this).hasClass("row_selected")) {\
								$(this).removeClass("row_selected");\
								if(!' + ct.instance + '.is_multiselect) $("#' + ct.element_id + ' .custom_table_data_row").removeClass("row_selected");\
							} else {\
								if(!' + ct.instance + '.is_multiselect) $("#' + ct.element_id + ' .custom_table_data_row").removeClass("row_selected");\
								$(this).addClass("row_selected");\
							}\
							' + ct.instance + '.Selection();\
						});';
			data_html+='</script>';

			$('#' + ct.element_id + ' .custom_table_data').html(data_html);

			// Fix paging
			let pages_html=filtered_data.length==0 ? GetString('no_records_found') : (filtered_data.length==1 ? GetString('one_record_found') : GetString('#COUNT#_records_found').replaceAll('#COUNT#', filtered_data.length));
			let i=0;
			// Previous pages
			var previous_pages=Math.ceil(previous_pages_records / ct.page_records);
			if(previous_pages>0) pages_html+='<button type="button" class="btn btn-default custom_table_footer_pagination_button" page_index="1" title="First page">&lt;</button>';
			if(previous_pages>1) pages_html+='<button type="button" class="btn btn-default custom_table_footer_pagination_button" page_index="' + previous_pages + '" title="Previous page">' + previous_pages + '</button>';

			// Current page
			pages_html+='<button type="button" class="btn btn-primary custom_table_footer_pagination_button" page_index="' + ct.page + '">' + ct.page + '</button>';

			// Next pages
			var next_pages=Math.ceil(next_pages_records / ct.page_records);
			if(next_pages>1) pages_html+='<button type="button" class="btn btn-default custom_table_footer_pagination_button" page_index="' + (ct.page+1) + '" title="Next page">' + (ct.page+1) + '</button>';
			if(next_pages>0) pages_html+='<button type="button" class="btn btn-default custom_table_footer_pagination_button" page_index="' + (ct.page+next_pages) + '" title="Last page">&gt;</button>';

			// Scripts
			pages_html+='<script>\
							$("#' + ct.element_id + '").on("click", ".custom_table_footer_pagination_button", function(event) { \
								event.stopPropagation();\
								event.stopImmediatePropagation();\
								let page=$(this).attr("page_index");\
								' + ct.instance + '.SetPage(page);\
							});\
						</script>';

			$('#' + ct.element_id + ' .custom_table_footer_pagination').html(pages_html);
		}
		ct.RefreshClearFiltersButton();

	}

	ct.Query=function() {
		if(IsEmpty(ct.ajax_url)) {
			console.log(ct.element_id + ': ' + GetString('no_ajax_url_is_set'));
			return;
		}

		$('#' + ct.element_id + ' .custom_table_loading').fadeIn();
		ct.Post(ct.ajax_url, [],
			function (response) {
				$('#' + ct.element_id + ' .custom_table_loading').fadeOut();
				if(!IsSet(response) || !IsSet(response.status)) {
					alert('Invalid server response');
				} else if(!response.status) {
					alert(response.message);
				} else {
					ct.data=[];
					response.data.forEach(function(row, row_index) {
						var data_row=[];
						ct.fields.forEach(function(field, field_index) {
							if(row.hasOwnProperty(field.name))
								data_row.push(row[field.name]);
						});
						ct.data.push(data_row);
					});
					if(ct.data.length>0) {
						// Sort data
						ct.SortData();
						// Draw
						ct.Draw();
					} else {
						ct.NoData();
					}
				}
			},
			function(jqXHR, textStatus, errorThrown) {
				alert(textStatus);
			}
		);
	}

	var ApplyFilters=function() {
		ct.column_filters=[];
		$('#' + ct.element_id + ' .custom_table_search_column input, #' + ct.element_id + ' .custom_table_search_column button').each(function(index) {
			if($(this).prop('tagName').toLowerCase()=='button')
				ct.column_filters.push($(this).attr('cur_value'));
			else
				ct.column_filters.push($(this).val());
		});
		var filtered_data=$.grep(ct.data, function(row) {
			let i=0;
			for(i=0;i<ct.column_filters.length;i++) {
				if(ct.fields[i].type=='checkbox') {
					if(ct.column_filters[i]!='-1' && !Includes(row[i], ct.column_filters[i])) return false;
				} else if(!IsEmpty(ct.column_filters[i]) && !Includes(row[i], ct.column_filters[i], true)) return false;
			}
			return true;
		});
		ct.Save();
		return filtered_data;
	}

	ct.ClearFilters=function() {
		$('#' + ct.element_id + ' .custom_table_search_column input').each(function(index) { $(this).val(''); });
		$('#' + ct.element_id + ' .custom_table_search_column button').each(function(index) { $(this).attr('cur_value', '-1'); });
		ct.RefreshClearFiltersButton();
	}

	ct.RefreshClearFiltersButton=function() {
		var has_filters=false;
		$('#' + ct.element_id + ' .custom_table_search_column input').each(function(index) { if($(this).val()!='') has_filters=true; });
		if(!has_filters) $('#' + ct.element_id + ' .custom_table_search_column button').each(function(index) { if($(this).attr('cur_value')!='-1') has_filters=true; });
		$('#' + ct.element_id + ' .custom_table_top_button_clear_filters').css('background-color', has_filters ? '#E0E0E0' : 'white');
	}

	ct.CompareData=function(c1, c2) {
		var a, b;
		if(ct.fields[ct.sort_index].type=='number') {
			a='00000000' + c1[ct.sort_index]; a=a.substr(a.length-8);
			b='00000000' + c2[ct.sort_index]; b=b.substr(b.length-8);
		} else {
			a=c1[ct.sort_index];
			b=c2[ct.sort_index];
		}
		if(a==null && b==null) return 0;
		else if(a==null) return ct.sort_type==0 ? -1: 1;
		else if(b==null) return ct.sort_type==0 ? 1: -1;
		else if(a<b) return ct.sort_type==0 ? -1: 1;
		else if(a>b) return ct.sort_type==0 ? 1: -1;
		else return 0;
	}

	ct.SortData=function() {
		ct.data.sort(ct.CompareData);
	}

	ct.FixHeader=function() {
		$('#' + ct.element_id + ' .custom_table_header_column').removeClass('sorted_asc').removeClass('sorted_desc');
		$($('#' + ct.element_id + ' .custom_table_header_column')[ct.sort_index]).addClass(ct.sort_type==0 ? 'sorted_asc' : 'sorted_desc');
	}

	ct.NoData=function() {
		ct.page=1;
		ct.Save();
		$('#' + ct.element_id + ' .custom_table_data').html('<div class="custom_table_data_row_no_data">' + GetString('no_data_available') + '</div>');
		$('#' + ct.element_id + ' .custom_table_footer').html('\
						<div class="custom_table_footer_export_buttons"> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="xls">Excel</button> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="csv">CSV</button> \
							<button type="button" class="btn btn-primary custom_table_footer_export_button" export_type="pdf">PDF</button> \
						</div> \
						<div class="custom_table_footer_pagination"> \
						</div>');
	}

	ct.FilterChanged=function() {
		ct.page=1;
		ct.Selection('');
		ct.Draw();
	}

	ct.FixColumnsWidth=function() {
		if(IsEmpty(ct.fields)) return;
		var column_actions_width=(ct.can_edit && !IsEmpty(ct.edit_button_url) ? 52 : 0) + (ct.can_delete && !IsEmpty(ct.delete_button_url) ? 48 : 0);
		var avail_width=$('#' + ct.element_id + ' .custom_table_header').width() - column_actions_width;
		var widths=[];
		var w_count=0;
		ct.fields.forEach(function(field, index) {
			var field_width=!IsEmpty(field.width) ? field.width : '1w';
			field_width=field_width.replace(' px', '');
			field_width=field_width.replace('px', '');
			if(Includes(field_width, '*'))
				field_width='1w';
			else if(!Includes(field_width, 'w') && !isNaN(parseInt(field_width)))
				avail_width-=parseInt(field_width);

			if(Includes(field_width, 'w')) w_count++;
			widths.push(field_width);
		});
		avail_width=Math.max(avail_width, 0);
		var w_width=w_count==0 ? 100 : Math.floor(avail_width / w_count);
		var html_css='';
		widths.forEach(function(width, index) {
			var final_width;
			if(Includes(width, 'w'))
				final_width=parseInt(width.replace('w', '')) * w_width;
			else
				final_width=width;

			let css=':nth-child(' + (index + 1) + ') { width: ' + final_width + 'px; }\n';
			html_css+='#' + ct.element_id + ' .custom_table_header .custom_table_header_column' + css;
			html_css+='#' + ct.element_id + ' .custom_table_data_row .custom_table_data_column' + css;
			html_css+='#' + ct.element_id + ' .custom_table_search_row .custom_table_search_column' + css;
		});
		$('#' + ct.element_id + ' .custom_table_style').html(html_css);
	}

	ct.FixFilterButtons=function() {
		$('#' + ct.element_id + ' .custom_table_search_column button').each(function(index) {
			let button_value=$(this).attr('cur_value');
			if(['-1', '0', '1'].indexOf(button_value)<0) button_value='-1';

			let button_text;
			if(button_value=='1')
				button_text='<span class="glyphicon glyphicon-check"></span>';
			else if(button_value=='0')
				button_text='<span class="glyphicon glyphicon-unchecked"></span>';
			else
				button_text='<font color="#AAAAAA"><span class="glyphicon glyphicon-check"></span></font>';
			$(this).attr('cur_value', button_value);
			$(this).html(button_text);
		});
	}

	ct.ToggleSelectAll=function() {
		ct.select_all=!ct.select_all;
		if(ct.select_all)
			$('#' + ct.element_id + ' .custom_table_data_row').addClass('row_selected');
		else
			$('#' + ct.element_id + ' .custom_table_data_row').removeClass('row_selected');
	}

	ct.Selection=function(new_selection) {
		if(!IsEmpty(new_selection)) {
			$('#' + ct.element_id + ' .custom_table_data_row').removeClass('row_selected');
			var new_ids=new_selection.split(',');
			new_ids.forEach(function(id, index) {
				$('#' + ct.element_id + ' .custom_table_data_row[row-id="' + id + '"]').addClass('row_selected');
			});
		}
		var ids='';
		$('#' + ct.element_id + ' .custom_table_data .row_selected').each(function(index) {
			ids+=(ids=='' ? '' : ',') + $(this).attr('row-id');
		});
		return ids;
	}

	ct.SelectionData=function(new_selection) {
		if(!IsEmpty(new_selection)) {
			$('#' + ct.element_id + ' .custom_table_data_row').removeClass('row_selected');
			var new_ids=new_selection.split(',');
			new_ids.forEach(function(id, index) {
				$('#' + ct.element_id + ' .custom_table_data_row[data-index="' + id + '"]').addClass('row_selected');
			});
		}
		var indexes=[];
		$('#' + ct.element_id + ' .custom_table_data .row_selected').each(function(index) {
			indexes.push($(this).attr('data-index'));
		});
		return indexes;
	}

	ct.Filters=function(new_filters) {
		if(!IsEmpty(new_filters)) {
			$('#' + ct.element_id + ' .custom_table_data_row').removeClass('row_selected');
			var new_ids=new_filters.split(',');
			new_ids.forEach(function(id, index) {
				$('#' + ct.element_id + ' .custom_table_data_row[row-id="' + id + '"]').addClass('row_selected');
			});
			ct.FixFilterButtons();
		}
		var ids='';
		$('#' + ct.element_id + ' .custom_table_data .row_selected').each(function(index) {
			ids+=(ids=='' ? '' : ',') + $(this).attr('row-id');
		});
		$('#' + ct.element_id + ' .custom_table_search_column input').val('');

		return ids;
	}

	ct.GetFilters=function() {
		return ct.column_filters;
	}

	ct.ExportPDF=function() {
		html2canvas(ct.element).then(function(canvas) {
			var imgData = canvas.toDataURL('image/png');
			var doc = new jsPDF('l', 'mm');
			doc.addImage(imgData, 'PNG', 10, 10);
			doc.save('cutom_table_data.pdf');
		});
	}

	ct.ExportCSV=function() {
		let filtered_data=ApplyFilters();
		var contents='\uFEFF';
		filtered_data.forEach(function(row, row_index) {
			var line='';
			row.forEach(function(field, field_index) {
				line+=(line=='' ? '' : ',') + field;
			});
			contents+=line + '\n';
		});
		contents='data:text/csv;charset=utf-8,' + encodeURI(contents);
		DownloadExportedFile('cutom_table_data.csv', contents);
	}

	ct.ExportXLS=function() {
		let filtered_data=ApplyFilters();
		var contents='\uFEFF<?xml version="1.0"?>\r\n<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\r\n<ss:Worksheet ss:Name="Sheet1">\r\n<ss:Table>\r\n\r\n';
		filtered_data.forEach(function(row, row_index) {
			contents+='<ss:Row>\r\n';
			row.forEach(function(field, field_index) {
				contents+='  <ss:Cell>\r\n    <ss:Data ss:Type="String">' + field + '</ss:Data>\r\n  </ss:Cell>\r\n';
			});
			contents+='</ss:Row>\r\n\r\n';
		});
		contents+='</ss:Table>\r\n</ss:Worksheet>\r\n</ss:Workbook>';

		contents='data:application/vnd.ms-excel;charset=utf-8,' + encodeURI(contents);
		DownloadExportedFile('cutom_table_data.xls', contents);
	}

	var DownloadExportedFile=function(filename, contents) {
		let link = document.createElement('a');
		link.setAttribute('href', contents);
		link.setAttribute('download', filename);
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	}

	ct.Post=function(url, params, on_success, on_error) {
		$.ajax({
			url: url,
			type: 'post',
			data: params,
			dataType: 'json',
			success: function (response) {
				if(IsSet(on_success)) on_success(response);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.log(textStatus, errorThrown);
				if(IsSet(on_error)) on_error(jqXHR, textStatus, errorThrown);
			}
		});
	}

	ct.DisableDoubleClick=function() {
		$('#' + ct.element_id + ' .custom_table_data_row').unbind('dblclick');
	}

	ct.SetModal=function(is_modal) {
		ct.is_modal=is_modal;
		if(ct.is_modal) {
			ct.SetIsMultiselect(false);
			ct.ShowExportButtons(false);
			ct.DisableDoubleClick();
		}
	}

	ct.MassDelete=function() {
		var process_title=GetString('mass_delete');
		var ids=ct.Selection();
		if(IsEmpty(ids)) {
			ShowModal('', GetString('no_record_selected'));
			return;
		}
		if(confirm(GetString('delete_confirm') + ':\n' + ids)) {
			Post(ct.api_url, { controller: ct.page_controller, action: 'mass_delete', ids: ids},
				function (response) {
					if(response==undefined || response==null || response.status==undefined) {
						ShowModal(process_title, GetString('invalid_server_response'));
					} else if(response.status) {
						ShowModal(process_title, response.message);
						ct.ForceDraw(true);
					} else {
						ShowModal(process_title, response.message);
					}
				},
				function(jqXHR, textStatus, errorThrown) {
					ShowModal(process_title, textStatus + '\n' + jqXHR.responseText);
				}
			);
		}
	}
}

function CustomTableModal(title, custom_table, on_select) {

	var ct=this;
	ct.custom_table=custom_table;
	ct.on_select=on_select;
	ct.title=title;

	ct.Title=function(title) {
		if(!IsEmpty(title)) ct.title=title;
		return ct.title;
	}

	ct.OnSelect=function(on_select) {
		if(!IsEmpty(on_select)) ct.on_select=on_select;
		return ct.on_select;
	}

	ct.FixPosition=function() {
		$('#modal_custom_table_container').css('margin-left', (($(window).width() - $('#modal_custom_table_container').width()) / 2) + 'px');
	}

	$(window).on('resize', function(){
		ct.FixPosition();
	});

	ct.Show=function() {
		ct.custom_table.SetModal(true);
		if(!$('#modal_custom_table').length) {
			$('	<div id="modal_custom_table">\
					<div id="modal_custom_table_container">\
						<div id="modal_custom_table_box">\
							<div id="modal_custom_table_title">' + ct.title + '</div>\
							<div id="custom_table_div"></div>\
							<button type="button" class="btn btn-primary app_form_box_button" id="modal_custom_table_save">Save</button>\
							<button type="button" class="btn btn-default app_form_box_button" id="modal_custom_table_cancel">Cancel</button>\
							<div style="clear: both;"></div>\
						</div>\
					</div>\
				</div>').appendTo('body');
			SetEvents();
		}
		ct.custom_table.SetContainer('custom_table_div');
		$('#modal_custom_table').fadeIn();
		ct.FixPosition();
		ct.custom_table.ForceDraw(true);

	}

	var SetEvents=function() {
		$('#modal_custom_table_save').on('click', function(event) {
			event.stopPropagation();
			event.stopImmediatePropagation();
			if(IsEmpty(ct.custom_table.Selection())) {
				alert(GetString('no_record_selected'));
				return;
			}

			if(IsSet(ct.on_select) && {}.toString.call(ct.on_select) === '[object Function]')
				ct.on_select();

			$('#modal_custom_table').fadeOut(300, function(){$(this).remove();});
		});

		$('#modal_custom_table_cancel').on('click', function(event) {
			event.stopPropagation();
			event.stopImmediatePropagation();
			$('#modal_custom_table').fadeOut(300, function(){$(this).remove();});
		});

		$('#modal_custom_table').on('dblclick', '.custom_table_data_row', function(event) {
			event.stopPropagation();
			event.stopImmediatePropagation();
			$("#modal_custom_table .custom_table_data_row").removeClass("row_selected");
			$(this).addClass("row_selected");
			$('#modal_custom_table_save').click();
		});
	}
}