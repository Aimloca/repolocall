<?

class View {

	public static function GetFieldsForImport($class) {
		if(empty($class)) return '';
		$fields='';
		$model=new $class;
		foreach($model->db_fields_names as $field) {
			if($field=='id') continue;
			$fields.='<b>' . $field . '</b> : ' . Strings::Get("{$model->table}.$field") . (in_array($field, $model->required_db_fields) && !in_array($field, $model->predefined_db_fields) ? ' <font color="red">*</font>' : '') . '<br>';
		}
		return $fields;
	}

	public static function GetJavascriptForList($parameters='') {
		$random_int=rand(5000, 10000);
		if(empty($parameters)) return 'alert("GetJavascriptForList: No parameters passed");';
		if(empty($parameters['class'])) return 'alert("GetJavascriptForList: No class passed");';
		if(empty($parameters['controller'])) return 'alert("GetJavascriptForList: No controller passed");';
		if(empty($parameters['var_name'])) $parameters['var_name']='custom_table' . $random_int;
		if(empty($parameters['element'])) $parameters['element']='custom_table_div' . $random_int;
		if(empty($parameters['title'])) $parameters['title']='';
		if(empty($parameters['allow_add'])) $parameters['allow_add']=false;
		if(empty($parameters['allow_edit'])) $parameters['allow_edit']=false;
		if(empty($parameters['allow_delete'])) $parameters['allow_delete']=false;
		if(empty($parameters['allow_import'])) $parameters['allow_import']=false;
		if(empty($parameters['list_url'])) $parameters['list_url']=API_URL . "controller={$parameters['controller']}&action=list";
		if(empty($parameters['add_url'])) $parameters['add_url']=BASE_URL . "{$parameters['controller']}/add/";
		if(empty($parameters['edit_url'])) $parameters['edit_url']=BASE_URL . "{$parameters['controller']}/edit/";
		if(empty($parameters['delete_url'])) $parameters['delete_url']=API_URL . "controller={$parameters['controller']}&action=delete";

		//if(empty($parameters['allow_add_to_list'])) $parameters['allow_add_to_list']=false;

		if(empty($parameters['fields']) || !is_array($parameters['fields'])) $parameters['fields']=$parameters['class']::GetListFields();
		if(!is_array($parameters['fields'])) return 'alert("GetJavascriptForList: Fields are not array");';

		$out= "
			var {$parameters['var_name']};
			$(document).ready(function() {
				" . CreateCustomTable($parameters['var_name'], $parameters['element'], $parameters['controller']) . "
				{$parameters['var_name']}.Title('{$parameters['title']}');
				{$parameters['var_name']}.SetAjaxUrl('{$parameters['list_url']}');
				{$parameters['var_name']}.SetFields([";
		foreach($parameters['fields'] as $field_name=>$field_data) {
			if(empty($field_name)) return 'alert("GetJavascriptForList: Field ' . $field_data . ' has no name");';
			if(empty($field_data['header'])) return 'alert("GetJavascriptForList: Field ' . $field_name . ' has no header");';
			if(empty($field_data['type'])) $field_data['type']='text';
			if(empty($field_data['width'])) $field_data['width']='*';
			$out.="
					{ name: '{$field_name}', header: '{$field_data['header']}', type: '{$field_data['type']}', width: '{$field_data['width']}' },";
		}
		$out.="
				]);\n";
		if($parameters['allow_edit']) $out.="\t\t\t\t{$parameters['var_name']}.SetEditButtonUrl('{$parameters['edit_url']}');\n";
		if($parameters['allow_delete']) $out.="\t\t\t\t{$parameters['var_name']}.SetDeleteButtonUrl('{$parameters['delete_url']}');\n";
		if(!empty($parameters['before_query'])) $out.="\t\t\t\t{$parameters['var_name']}.SetBeforeQuery('" . str_replace(["\r\n", "\r", "\n", "\t"], ' ', $parameters['before_query']) . "');\n";
		if(!empty($parameters['after_query'])) $out.="\t\t\t\t{$parameters['var_name']}.SetAfterQuery('" . str_replace(["\r\n", "\r", "\n", "\t"], ' ', $parameters['after_query']) . "');\n";
		if(!empty($parameters['before_draw'])) $out.="\t\t\t\t{$parameters['var_name']}.SetBeforeDraw('" . str_replace(["\r\n", "\r", "\n", "\t"], ' ', $parameters['before_draw']) . "');\n";
		if(!empty($parameters['after_draw'])) $out.="\t\t\t\t{$parameters['var_name']}.SetAfterDraw('" . str_replace(["\r\n", "\r", "\n", "\t"], ' ', $parameters['after_draw']) . "');\n";


		$out.="\t\t\t\t{$parameters['var_name']}.SetTopButtons([\n";
		if($parameters['allow_add']) $out.="\t\t\t\t{ name: 'add', icon: 'plus-sign', text: '" . Strings::Get('add_new') . "', url: '{$parameters['add_url']}' },\n";
		if($parameters['allow_delete']) $out.="\t\t\t\t{ name: 'mass_delete', icon: 'trash', text: '" . Strings::Get('mass_delete') . "', action: 'MassDelete_{$parameters['var_name']}();' },\n";
		if($parameters['allow_import']) $out.="\t\t\t\t{ name: 'import', icon: 'import', text: '" . Strings::Get('import') . "', action: 'ImportData_{$parameters['var_name']}();' },\n";

        //if($parameters['allow_add_to_list']) $out.="\t\t\t\t{ name: 'addlist', icon: 'import', text: '" . Strings::Get('addlist') . "', action: 'add_to_list_{$parameters['var_name']}();'},\n";

        $out.="\t\t\t\t]);\n";
		if(isset($parameters['search_row_top'])) $out.="\t\t\t\t{$parameters['var_name']}.SetSearchRowTop('{$parameters['search_row_top']}');\n";
		if(!empty($parameters['on_document_ready'])) $out.="\t\t\t\t{$parameters['on_document_ready']}\n";
		if(!isset($parameters['dont_draw']) || !$parameters['dont_draw']) $out.="\t\t\t\t{$parameters['var_name']}.Draw();\n";
		$out.="
			});
		";

		// Mass delete
		if($parameters['allow_delete']) {
			$out.="
			function MassDelete_{$parameters['var_name']}() {
				var ids={$parameters['var_name']}.Selection();
				if(IsEmpty(ids)) {
					ShowModal('', '" . Strings::Get('no_record_selected') . "');
					return;
				}
				if(confirm('" . Strings::Get('proceed_delete_records') . "')) {
					Post('" . API_URL . "', { controller: '{$parameters['controller']}', action: 'mass_delete', ids: ids},
						function (response) {
							if(response==undefined || response==null || response.is_error==undefined) {
								ShowModal('" . Strings::Get('mass_delete') . "', '" . Strings::Get('invalid_server_response') . "');
							} else if(response.is_error) {
								ShowModal('" . Strings::Get('mass_delete') . "', response.message);
							} else {
								ShowModal('" . Strings::Get('mass_delete') . "', response.message);
								{$parameters['var_name']}.ForceDraw(true);
							}
						},
						function(jqXHR, textStatus, errorThrown) {
							ShowModal('" . Strings::Get('mass_delete') . "', textStatus + '\\n' + jqXHR.responseText);
						}
					);
				}
			}";
		}

		// Import
		if($parameters['allow_import']) {
			$import_fields=View::GetFieldsForImport($parameters['class']);
			$out.="
					var data_import_modal_content_{$parameters['var_name']}=' \\
						" . Strings::Get('import_help') . "<br>$import_fields<br><br> \\
						<input type=\"file\" id=\"import_data_file\" accept=\".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel\" /> \\
						<div> \\
							<div id=\"progress-wrp\"> \\
								<div class=\"progress-bar\"></div> \\
								<div class=\"status\">0%</div> \\
							</div> \\
						</div>';

					function ImportData_{$parameters['var_name']}() {
						ShowModal('" . Strings::Get('import_data') . "', data_import_modal_content_{$parameters['var_name']}, '" . Strings::Get('upload') ."', 'DoImportData_{$parameters['var_name']}();');
						setTimeout(function() { $(\"#import_data_file\").on(\"change\", function (e) { DoImportData_{$parameters['var_name']}(); }); }, 500);
					}

					function DoImportData_{$parameters['var_name']}() {
						$('#progress-wrp').hide();
						var files =$(\"#import_data_file\")[0].files;
						if(files==null || files==undefined || files.length<=0) {
							alert('" . Strings::Get('import_file_not_selected') . "');
							$('#import_data_file').focus();
							return;
						}
						var file=files[0];
						if(file==null || file==undefined) {
							alert('" . Strings::Get('import_file_not_selected') . "');
							$('#import_data_file').focus();
							return;
						}

						$('#app_modal_button_positive').prop('disabled' ,'disabled');

						setTimeout(function() {

							$('#progress-wrp').show();

							var formData = new FormData();
							formData.append(\"controller\", \"{$parameters['controller']}\");
							formData.append(\"action\", \"import\");
							formData.append(\"id\", file);

							$.ajax({
									url : '" . API_URL . "',
									type : 'POST',
									data : formData,
									processData: false,
									contentType: false,
									dataType: 'json',
									timeout: 60000,
									xhr: function () {
										var myXhr = $.ajaxSettings.xhr();
										if (myXhr.upload) {
											myXhr.upload.addEventListener('progress', function (event) {
												var percent = 0;
												var position = event.loaded || event.position;
												var total = event.total;
												var progress_bar_id = \"#progress-wrp\";
												if (event.lengthComputable) percent = Math.ceil(position / total * 100);
												$(progress_bar_id + \" .progress-bar\").css(\"width\", +percent + \"%\");
												$(progress_bar_id + \" .status\").text(percent + \"%\");
											}, false);
										}
										return myXhr;
									},
									success : function (response) {
										ForceHideModal();
										$('#app_modal_button_positive').removeProp('disabled');
										if(response==undefined || response==null || response.is_error==undefined) {
											ShowModal('" . Strings::Get('import_result') ."', '" . Strings::Get('invalid_server_response') . "', null, null, 'ReloadPage();');
										} else if(response.is_error) {
											ShowModal('" . Strings::Get('import_result') . "', response.message, null, null, 'ReloadPage();');
										} else {
											window.location.reload();
										}
									},
									error: function(jqXHR, textStatus, errorThrown) {
										ForceHideModal();
										$('#app_modal_button_positive').removeProp('disabled');
										ShowModal('" . Strings::Get('import_result') . "', textStatus + \"\\n\" + jqXHR.responseText);
									}
							});

						}, 500);
					}
				";


                /* if($parameters['allow_add_to_list']) {
                    $out.="
                    function add_to_list_{$parameters['var_name']}() {
						var new_row='<div class='custom_table_data_row'></div>';
                        new_row= new_row +'<div class='custom_table_data_column custom_table_data_column_number'></div>';
                        new_row= new_row +'<div class='custom_table_data_column'><input type='text'></div>';
                        new_row= new_row +'<div class='custom_table_data_column'><input type='text'></div>';
                        new_row= new_row +'<div class='custom_table_data_column'><input type='text'><select><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option></select></div>';
                        new_row= new_row +'<div class='custom_table_data_column'></div>';
                        $(new_row).insertAfter('.custom_table_data .custom_table_data_row: first');
					}";
                } */
		}


		return $out;
	}

	public static function GetJavascriptForRemoteList($parameters) {
		$random_int=rand(5000, 10000);
		if(empty($parameters)) return 'alert("GetJavascriptForRemoteList: No parameters passed");';
		if(empty($parameters['class'])) return 'alert("GetJavascriptForRemoteList: No class passed");';
		if(empty($parameters['controller'])) return 'alert("GetJavascriptForRemoteList: No controller passed");';
		if(empty($parameters['var_name'])) $parameters['var_name']='custom_table' . $random_int;
		if(empty($parameters['element'])) $parameters['element']='custom_table_div' . $random_int;
		if(empty($parameters['title'])) $parameters['title']='';
		if(empty($parameters['allow_add'])) $parameters['allow_add']=false;
		if(empty($parameters['allow_edit'])) $parameters['allow_edit']=false;
		if(empty($parameters['allow_delete'])) $parameters['allow_delete']=false;
		if(empty($parameters['allow_import'])) $parameters['allow_import']=false;
		$parameters['dont_draw']=true;

		$out=View::GetJavascriptForList($parameters);

		if(!empty($parameters['base_field'])) $out.="$('#{$parameters['base_field']}').hide();";

		$out.="
				$('#{$parameters['base_field']}_ffd').on('click', function(event) {
					event.stopPropagation(); event.stopImmediatePropagation();
					" . (isset($parameters['before_display']) ? $parameters['before_display'] : '') ."
					var local=$(this).prev();
					var remote=$(this);
					let on_select=function() { " .
					( !empty($parameters['on_select']) ? $parameters['on_select'] : "
						$(local).val({$parameters['var_name']}.Selection());
						$(remote).val(EscapeHtml({$parameters['var_name']}.data[{$parameters['var_name']}.SelectionData()[0]][1]));
						ShowHideForeignDataClear();
						" . (isset($parameters['on_select_extra']) ? $parameters['on_select_extra'] : '')
					) . "
					}
					new CustomTableModal('{$parameters['title']}', {$parameters['var_name']}, on_select).Show();
				});

		";
		return $out;
	}
}
