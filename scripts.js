setInterval(KeepSessionAlive, 1000 * 60 * 30);

function FixNumerics(object_or_array) {
	if(object_or_array==null) return;
	if(typeof object_or_array==='object' || object_or_array instanceof Object) {
		for(const [key, value] of Object.entries(object_or_array)) {
			if(BOOLEAN_FIELDS.indexOf(key)>=0) object_or_array[key]=object_or_array[key]===true ? 1 : (object_or_array[key]===false ? 0 : parseInt(object_or_array[key]));
			else if(INT_FIELDS.indexOf(key)>=0) object_or_array[key]=parseInt(object_or_array[key]);
			else if(FLOAT_FIELDS.indexOf(key)>=0) object_or_array[key]=parseFloat(object_or_array[key]);
			else FixNumerics(object_or_array[key]);
		}
	} else if(typeof object_or_array==='array' || object_or_array instanceof Array) {
		for(let key of object_or_array) {
			if(BOOLEAN_FIELDS.indexOf(key)>=0) object_or_array[key]=object_or_array[key]===true ? 1 : (object_or_array[key]===false ? 0 : parseInt(object_or_array[key]));
			else if(INT_FIELDS.indexOf(key)>=0) object_or_array[key]=parseInt(object_or_array[key]);
			else if(FLOAT_FIELDS.indexOf(key)>=0) object_or_array[key]=parseFloat(object_or_array[key]);
			else FixNumerics(object_or_array[key]);
		}
	}
}

function StrFloat(val, points=2) {
	return parseFloat(val).toFixed(points);
}

function KeepSessionAlive() {
	Post('?', { api: 1, controller: 'account', action: 'keep_alive' });
}

function ValidateEmail(email) {
	if(IsEmpty(email) || email.trim()=='') return { status:false, message:'email_cannot_be_empty' };
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
	var result=re.test(String(email).toLowerCase());
	return { status:result, message:(result ? 'OK' : 'invalid_email_address') };
}

function Post(url, params, on_success, on_error) {
	$.ajax({
        url: url,
        type: 'post',
        data: params,
		dataType: 'json',
        success: function (response) {
			if(!IsEmpty(on_success)) on_success(response);
		},
        error: function(jqXHR, textStatus, errorThrown) {
			console.log(textStatus, errorThrown);
			if(!IsEmpty(on_error)) on_error(jqXHR, textStatus, errorThrown);
        }
    });
}

function PostUpload(url, params, on_success, on_error) {
	$.ajax({
		url : url,
		type : 'post',
		data : params,
		processData: false,
		contentType: false,
		dataType: 'json',
		timeout: 60000,
		xhr: function () {
			var myXhr = $.ajaxSettings.xhr();
			if (myXhr.upload) {
				myXhr.upload.addEventListener('progress', function (event) {
					try {
						var percent = 0;
						var position = event.loaded || event.position;
						var total = event.total;
						var progress_bar_id = "#progress-wrp";
						if (event.lengthComputable) percent = Math.ceil(position / total * 100);
						$(progress_bar_id + " .progress-bar").css("width", +percent + "%");
						$(progress_bar_id + " .status").text(percent + "%");
					} catch(e) { }
				}, false);
			}
			return myXhr;
		},
		success: function (response) {
			if(!IsEmpty(on_success)) on_success(response);
		},
        error: function(jqXHR, textStatus, errorThrown) {
			console.log(textStatus, errorThrown);
			if(!IsEmpty(on_error)) on_error(jqXHR, textStatus, errorThrown);
        }
	});
}

function ForceHideModal() {
	$('#app_modal').modal('hide');
	$('body').removeClass('modal-open');
	$('.modal-backdrop').remove();
}

function ShowModal(title, message, button_positive, button_action, on_close_action) {
	message = message.replace(/(?:\r\n|\r|\n)/g, '<br>');
	if($('#app_modal').length) $('#app_modal').remove();
	var m='\
			<!-- Modal --> \
			<div class="modal fade" id="app_modal" role="dialog"> \
				<div class="modal-dialog"> \
					<!-- Modal content--> \
					<div class="modal-content"> \
						<div class="modal-header"> \
							<button type="button" class="close" data-dismiss="modal">&times;</button> \
							<h4 class="modal-title">' + title + '</h4> \
						</div> \
						<div class="modal-body"> \
							<p>' + message + '</p> \
						</div> \
						<div class="modal-footer"> \
							' + (!IsEmpty(button_positive) ? '<button type="button" class="btn btn-primary" id="app_modal_button_positive" onclick="' + button_action + '">' + button_positive + '</button>' : '') + ' \
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button> \
						</div> \
					</div> \
				</div> \
			</div>';

	$('body').append(m);
	$('#app_modal').modal('show');
	$('#app_modal').on('hidden.bs.modal', function () {
		if(!IsEmpty(on_close_action)) eval(on_close_action);
	});
}

function OpenInNewTab(url) {
	window.open(url, '_blank').focus();
}

function Redirect(url='', new_tab=false) {
	if(url=='') { window.location=BASE_URL; return; }
	if(!new_tab) ShowLoader();
	var u=url.split('?');
	var action=u[0];
	var route_controller, route_action;
	var inputs='';
	var params=u.length>1 ? u[1].split('&') : [];
	for(var i=0;i<params.length;i++) {
		var nv=params[i].split('=');
		if(nv.length==2) {
			inputs+='<input type="hidden" name="' + nv[0] + '" value="' + nv[1] + '" />';
			if(nv[0]=='controller') route_controller=null;
			else if(nv[0]=='action') route_action=null;
		} else if(nv.length==1 && nv[0].split('/').length>2) {
			route_controller=nv[0].split('/')[1];
			route_action=nv[0].split('/')[2];
		}
	}
	if($('#form_redirect').length) $('#form_redirect').remove();
	if(route_controller) inputs+='<input type="hidden" name="controller" value="' + route_controller + '" />';
	if(route_action) inputs+='<input type="hidden" name="action" value="' + route_action + '" />';
	var html='<form id="form_redirect" class="app_redirect_form" method="POST" action="' + action + '" ' + (new_tab ? 'target="_BLANK"' : '') + '>' + inputs + '</form>';
	$('body').append(html);
	setTimeout(function() { $('#form_redirect').submit(); }, 500);
}

/*
function ValidateEmail(email) {
  var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  return regex.test(email);
} */

function ValidateFormByType(form) {
	var has_error=false;

	$("input", form).each(function( index ) {
		$(this).removeClass('erroneous');

		if($(this).attr('offline')!=undefined) return;

		if($(this).attr('dont_check')!=undefined) return;

		if($(this).attr('type').toLowerCase()=='text' || $(this).attr('type').toLowerCase()=='email') {
			if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
				$(this).addClass('erroneous');
				has_error=true;
			} else if($(this).attr('type').toLowerCase()=='email' && $(this).val().trim()!='' && ValidateEmail($(this).val()).status==false) {
				$(this).addClass('erroneous');
				has_error=true;
			} else if($(this).attr('id').toLowerCase()=='afm' && $(this).val().trim()!='' && ValidateAfm($(this).val())==false) {
				$(this).addClass('erroneous');
				has_error=true;
			}
		} else if($(this).attr('type').toLowerCase()=='number') {
			if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
				$(this).addClass('erroneous');
				has_error=true;
			} else if(!$(this)[0].hasAttribute('not_null') && $(this).val().trim()!='' && isNaN(parseInt($(this).val().trim()))) {
				$(this).addClass('erroneous');
				has_error=true;
			}
		} else if($(this).attr('type').toLowerCase()=='phone') {
			$(this).val($(this).val().replace(/\D/g,''));
			if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
				$(this).addClass('erroneous');
				has_error=true;
			} else if($(this)[0].hasAttribute('not_null') && $(this).val()!='' && $(this).val().length!=10 && $(this).val().length!=12) {
				$(this).addClass('erroneous');
				has_error=true;
			}
		} else if($(this).attr('type').toLowerCase().indexOf('datetime')>=0) {
			if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
				$(this).addClass('erroneous');
				has_error=true;
			}
		} else if($(this).attr('type').toLowerCase()=='password' && $(this).val().trim()=='') {
			$(this).addClass('erroneous');
			has_error=true;
		} else if($(this).attr('type').toLowerCase()=='file' && $(this).val().trim()=='') {
			$(this).addClass('erroneous');
			has_error=true;
		}
	});

	$("textarea", form).each(function( index ) {
		$(this).removeClass('erroneous');

		if($(this).attr('offline')!=undefined) return;

		if($(this).attr('dont_check')!=undefined) return;

		if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
			$(this).addClass('erroneous');
			has_error=true;
		}

	});

	$("select", form).each(function( index ) {
		$(this).removeClass('erroneous');

		if($(this).attr('offline')!=undefined) return;

		if($(this).attr('dont_check')!=undefined) return;

		if($(this)[0].hasAttribute('not_null') && $(this).val().trim()=='') {
			$(this).addClass('erroneous');
			has_error=true;
		}

	});
	return !has_error;
}

function PostEditForm(form) {

	var has_upload=false;
	var params={};
	var upload_form_data=new FormData();

	$("textarea", form).each(function( index ) {
		if($(this).attr('offline')!=undefined) return;
		params[$(this).attr('id')]=$(this).val();
		upload_form_data.append($(this).attr('id'), $(this).val());
	});

	$("input:not(:checkbox):not(:file)", form).each(function( index ) {
		if($(this).attr('offline')!=undefined) return;
		params[$(this).attr('id')]=$(this).val();
		upload_form_data.append($(this).attr('id'), $(this).val());
	});

	$("input:checkbox", form).each(function( index ) {
		if($(this).attr('offline')!=undefined) return;
		params[$(this).attr('id')]=$(this).is(':checked') ? 1 : 0;
		upload_form_data.append($(this).attr('id'), $(this).is(':checked') ? 1 : 0);
	});

	$("input:file", form).each(function( index ) {
		if($(this).attr('offline')!=undefined) return;
		has_upload=true;
		params[$(this).attr('id')]= $(this)[0].files[0];
		upload_form_data.append($(this).attr('id'), $(this)[0].files[0]);
	});

	$("select", form).each(function( index ) {
		if($(this).attr('offline')!=undefined) return;
		params[$(this).attr('id')]=$(this).val();
		upload_form_data.append($(this).attr('id'), $(this).val());
	});

	if(has_upload) {
		$(form).css('filter', 'blur(3px)').css('webkitFilter', 'blur(3px)').css('mozFilter', 'blur(3px)').css('oFilter', 'blur(3px)').css('msFilter', 'blur(3px)').css('cursor', 'wait').prop('disable', true);
		PostUpload($(form).attr('save_link'), upload_form_data,
			function (response) {
				$(form).css('cursor', 'default');
				if(IsEmpty(response) || response.status==undefined) {
					alert('Invalid server response');
				} else if(!response.status) {
					alert(response.message);
				} else if($(form).attr('after_save_link')=='') {
					window.location.back();
				} else {
					window.location=$(form).attr('after_save_link');
				}
				$(form).css('filter', 'blur(0px)').css('webkitFilter', 'blur(0px)').css('mozFilter', 'blur(0px)').css('oFilter', 'blur(0px)').css('msFilter', 'blur(0px)').css('cursor', 'default').prop('disable', false);
			},
			function(jqXHR, textStatus, errorThrown) {
				alert(textStatus);
				$(form).css('filter', 'blur(0px)').css('webkitFilter', 'blur(0px)').css('mozFilter', 'blur(0px)').css('oFilter', 'blur(0px)').css('msFilter', 'blur(0px)').css('cursor', 'default').prop('disable', false);
			}
		);
	} else {
		$(form).css('filter', 'blur(3px)').css('webkitFilter', 'blur(3px)').css('mozFilter', 'blur(3px)').css('oFilter', 'blur(3px)').css('msFilter', 'blur(3px)').css('cursor', 'wait').prop('disable', true);
		Post($(form).attr('save_link'), params,
			function (response) {
				$(form).css('cursor', 'default');
				if(IsEmpty(response) || response.status==undefined) {
					alert('Invalid server response');
				} else if(!response.status) {
					alert(response.message);
				} else if($(form).attr('after_save_link')!=''){
					window.location=$(form).attr('after_save_link');
				} else {
					if(history.length>1) history.back(); else window.close();
				}
				$(form).css('filter', 'blur(0px)').css('webkitFilter', 'blur(0px)').css('mozFilter', 'blur(0px)').css('oFilter', 'blur(0px)').css('msFilter', 'blur(0px)').css('cursor', 'default').prop('disable', false);
			},
			function(jqXHR, textStatus, errorThrown) {
				alert(textStatus);
				$(form).css('filter', 'blur(0px)').css('webkitFilter', 'blur(0px)').css('mozFilter', 'blur(0px)').css('oFilter', 'blur(0px)').css('msFilter', 'blur(0px)').css('cursor', 'default').prop('disable', false);
			}
		);
	}
}

var simple_checkbox = function ( data, type, full, meta ) {
    var is_checked = data == true || data == 1 ? "checked" : "";
    return '<input type="checkbox" class="checkbox" disabled ' + is_checked + ' />';
}

function ViewDocument(document_id) {

	if(IsEmpty(document_id)) return;

	Post('/api/', { controller: 'document', action: 'view', id: document_id },
		function (response) {
			if(IsEmpty(response) || response.is_error==undefined) {
				ShowModal('Document', 'Invalid server response');
			} else if(response.is_error) {
				ShowModal('Document', response.message);
			} else {
				ShowModal('Document', response.data, 'Download', 'window.open(\'/api/?controller=document&action=get&dispose=1&id=' + document_id + '\', \'_blank\');');
			}
		},
		function(jqXHR, textStatus, errorThrown) {
			ShowModal('Dowcument', textStatus + '\n' + jqXHR.responseText);
		}
	);

}

function IsSet(look) {
	return look!=undefined && look!=null;
}

function IsEmpty(look) {
	return Empty(look);
}

function Empty(look) {
	return look==undefined || look==null || look=='';
}

function NullToEmpty(look) {
	return Empty(look) ? '' : look;
}

function Includes(haystack, needle, ignoreCapitals=false) {
	if(haystack==0) haystack='0';
	if(needle==0) needle='0';
	if(haystack==undefined || haystack==null || haystack=='') return false;
	else if(needle==undefined || needle==null || needle=='') return false;
	else if(ignoreCapitals) return ('' + haystack).toLowerCase().indexOf(('' + needle).toLowerCase())>=0;
	else return ('' + haystack).indexOf('' + needle)>=0;
}

const entity_map = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

function EscapeHtml(string) {
	return String(string).replace(/[&<>"'`=\/]/g, function (s) { return entity_map[s]; });
}

var Trim=function(text) {
	return String(text).replace(/^\s+|\s+$/gm,'');
}

function ValidateAfm(afm) {
	if (!afm.match(/^\d{9}$/) || afm == '000000000') return false;
    var m = 1, sum = 0;
    for(var i=7;i>=0;i--) {
        m*= 2;
        sum+=afm.charAt(i) * m;
    }
	return sum % 11 % 10 == afm.charAt(8);
}

function ReloadPage() {
	window.location.reload();
}

function ShowLoader() {
	$('#loader').remove();
	$('body').append('<div id="loader" style="display: block;" onclick="return true;"><div id="loader_icon"></div></div>');
}

function HideLoader() {
	$('#loader').hide('fast');
}

function GetString(id, lang) {
	if(!id) return id;
	if(!lang) {
		var cookie_lang=$.cookie('lang');
		if(!cookie_lang || ['en', 'gr', 'ru'].indexOf(cookie_lang)<0) {
			cookie_lang='en';
			$.cookie('lang', cookie_lang, { expires: 7 });
		}
		lang=cookie_lang;
	}
	if(['en', 'gr', 'ru'].indexOf(lang)<0) {
		cookie_lang='en';
		$.cookie('lang', cookie_lang, { expires: 7 });
		lang=cookie_lang;
	}

	if(APP_STRINGS && APP_STRINGS[id] && APP_STRINGS[id][lang]) return APP_STRINGS[id][lang];
	if(APP_STRINGS && APP_STRINGS[id] && APP_STRINGS[id]['en']) return APP_STRINGS[id]['en'];
	return id;
}

function ShowToast(content) {
	if(content==undefined || content=='' || content==null) return;
	if($('#toasts_container').length==0) $('body').append('<div id="toasts_container"></div>');
	const data={ text: '', background: 'orange', color: '#333' };
	if(typeof content==='string' || content instanceof String) {
		data.text=content;
	} else if(typeof params==='object' || params instanceof Object) {
		if(content.text!=undefined) data.text=content.text;
		if(content.background!=undefined) data.background=content.background;
		if(content.color!=undefined) data.color=content.color;
	} else if(typeof params==='array' || params instanceof Array) {
		if(content[text]!=undefined) data.text=content[text];
		if(content[background]!=undefined) data.background=content[background];
		if(content[color]!=undefined) data.color=content[color];
	}
	if(data.text=='') return;
	const toast_id='toast_' + new Date().valueOf();
	$('#toasts_container').append('\
		<div class="toast" id="' + toast_id + '" style="color: ' + data.color + '; background-color: ' + data.background + ';">\
			' + data.text + '\
		</div>\
		<script>$("#' + toast_id + '").fadeIn("slow", function() { setTimeout(function() { $("#' + toast_id + '").fadeOut("slow", function() { $("#' + toast_id + '").remove(); }); }, 4000); });</script>\
	');
}

function MoveToTop() {
	$("html, body").animate({ scrollTop: 0 }, "slow");
}

function Round(num, decimals) {
	num=num==undefined ? 0 : parseFloat(num);
	decimals=decimals==undefined ? 0 : parseInt(decimals);
	return decimals==0 ? Math.round(num) : (num * 10 ** decimals) / (10 ** decimals);
}

function GetSearchable(inputed) {
	inputed=inputed.replaceAll('\r', '');
	inputed=inputed.replaceAll('\n', '');
	inputed=inputed.replaceAll('\t', ' ');
	inputed=inputed.toLowerCase();
	inputed=ConvertGreekToLatin(inputed);
	inputed=RemoveDoubleSpace(inputed);
	return inputed;
}

function ConvertGreekToLatin(inputed) {
	return inputed
		.replaceAll("α", "a")
		.replaceAll("ά", "a")
		.replaceAll("α", "a")
		.replaceAll("α", "a")
		.replaceAll("β", "b")
		.replaceAll("γ", "g")
		.replaceAll("δ", "d")
		.replaceAll("ε", "e")
		.replaceAll("έ", "e")
		.replaceAll("ζ", "z")
		.replaceAll("η", "h")
		.replaceAll("ή", "h")
		.replaceAll("θ", "u")
		.replaceAll("ι", "i")
		.replaceAll("ί", "i")
		.replaceAll("ϊ", "i")
		.replaceAll("ΐ", "i")
		.replaceAll("κ", "k")
		.replaceAll("λ", "l")
		.replaceAll("μ", "m")
		.replaceAll("ν", "n")
		.replaceAll("ξ", "j")
		.replaceAll("ο", "o")
		.replaceAll("ό", "o")
		.replaceAll("π", "p")
		.replaceAll("ρ", "r")
		.replaceAll("σ", "s")
		.replaceAll("ς", "s")
		.replaceAll("τ", "t")
		.replaceAll("υ", "y")
		.replaceAll("ύ", "y")
		.replaceAll("ϋ", "y")
		.replaceAll("ΰ", "y")
		.replaceAll("φ", "f")
		.replaceAll("χ", "x")
		.replaceAll("ψ", "c")
		.replaceAll("ω", "v")
		.replaceAll("ώ", "v")

		.replaceAll("Α", "A")
		.replaceAll("Ά", "A")
		.replaceAll("Α", "A")
		.replaceAll("Α", "A")
		.replaceAll("Β", "B")
		.replaceAll("Γ", "G")
		.replaceAll("Δ", "D")
		.replaceAll("Ε", "E")
		.replaceAll("Έ", "E")
		.replaceAll("Ζ", "Z")
		.replaceAll("Η", "H")
		.replaceAll("Ή", "H")
		.replaceAll("Θ", "U")
		.replaceAll("Ι", "I")
		.replaceAll("Ί", "I")
		.replaceAll("Ϊ", "I")
		.replaceAll("Κ", "K")
		.replaceAll("Λ", "L")
		.replaceAll("Μ", "M")
		.replaceAll("Ν", "N")
		.replaceAll("Ξ", "J")
		.replaceAll("Ο", "O")
		.replaceAll("Ό", "O")
		.replaceAll("Π", "P")
		.replaceAll("Ρ", "R")
		.replaceAll("Σ", "S")
		.replaceAll("Τ", "T")
		.replaceAll("Υ", "Y")
		.replaceAll("Ύ", "Y")
		.replaceAll("Ϋ", "Y")
		.replaceAll("Φ", "F")
		.replaceAll("Χ", "X")
		.replaceAll("Ψ", "C")
		.replaceAll("Ω", "V")
		.replaceAll("Ώ", "V");
}

function RemoveDoubleSpace(inputed) {
	while(inputed.indexOf('  ')!=-1) inputed=inputed.replaceAll('  ', ' ');
	return inputed;
}

function MD5(d){
	var r = M(V(Y(X(d),8*d.length)));return r.toLowerCase()};function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_
}

function IsString(a) {
	return typeof a === 'string' || a instanceof String;
}

function TableToPDF(table) {
	if(IsEmpty(table)) return;
	if(table[0]) table=table[0];
	html2canvas(table).then(function(canvas) {
		var doc = new jsPDF('l', 'mm', 'a4');
		var width = doc.internal.pageSize.getWidth();
		var height = doc.internal.pageSize.getHeight();
		var imgData = canvas.toDataURL('image/png');
		doc.addImage(imgData, 'PNG', 10, 10, width-20, height-20);
		doc.save('table_data.pdf');
	});
}

function TableToXLS(table) {
	if(IsEmpty(table)) return;
	var contents='\uFEFF<?xml version="1.0"?>\r\n<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\r\n<ss:Worksheet ss:Name="Sheet1">\r\n<ss:Table>\r\n\r\n';
	$('tr', table).each(function() {
		contents+='<ss:Row>\r\n';
		$('th, td', this).each(function() { contents+='  <ss:Cell>\r\n    <ss:Data ss:Type="String">' + $(this).text() + '</ss:Data>\r\n  </ss:Cell>\r\n'; });
		contents+='</ss:Row>\r\n\r\n';
	});
	contents+='</ss:Table>\r\n</ss:Worksheet>\r\n</ss:Workbook>';
	contents='data:application/vnd.ms-excel;charset=utf-8,' + encodeURI(contents);
	DownloadExportedFile('table_data.xls', contents);
}

function TableToCSV(table) {
	if(IsEmpty(table)) return;
	var contents='';
	$('tr', table).each(function() {
		var line='';
		$('th', this).each(function() { line+=(line=='' ? '' : ',') + $(this).text(); });
		if(line!='') { contents+=line + '\n'; line=''; }
		$('td', this).each(function() { line+=(line=='' ? '' : ',') + $(this).text(); });
		if(line!='') contents+=line + '\n';
	});

	contents='data:text/csv;charset=utf-8,' + encodeURI('\uFEFF' + contents);
	DownloadExportedFile('table_data.csv', contents);
}

function DownloadExportedFile(filename, contents) {
	let link = document.createElement('a');
	link.setAttribute('href', contents);
	link.setAttribute('download', filename);
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
}

$(document).ready(function(){
	// Add move to top
	if($('.move_to_top').length==0) $('body').append('<div class="move_to_top"><span class="glyphicon glyphicon-chevron-up"></span></div>');
	// Add move to top action
	$("body").on("click", ".move_to_top", MoveToTop);
	// Set move to top visibility according to screen scroll
	$(window).scroll(function(){
		if($(this).scrollTop()>300) $(".move_to_top").show(); else $(".move_to_top").hide();
	});
});