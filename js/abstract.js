/* AppClass model - Start */
class AppClass {
	constructor(params={}) {
		this.Reconstruct(params);
	}

	GetTable() {
		return '';
	}

	SetDefaults() {
		if(!Empty(this.GetTable()) && DB_TABLES[this.GetTable()]) {
			for(let [field, field_data] of Object.entries(DB_TABLES[this.GetTable()].fields)) {
				if(field_data.default!=null) {
					this[field]=field_data.type=='number' || field_data.type=='check' ? (field_data.number_type=='float' ? parseFloat(field_data.default) : parseInt(field_data.default)) : field_data.default;
				}
			}
		}
	}

	Reconstruct(source) {
		const from_what=source==undefined ? this : source;
		this.Update({...from_what});
	}

	Update(params={}) {
		this.SetDefaults();
		if(params!=undefined && params!=null && params!='') {
			if(typeof params==='object' || params instanceof Object) for(let [key, value] of Object.entries(params)) this[key]=value;
			else if(typeof params==='array' || params instanceof Array) for(let key of params) this[key]=params[key];
		}
		this.FixNumerics();
		this.FixLanguageFields();
	}

	RemoveAppFields() {
		const remove=['table', 'controller', 'primary_key', 'primary_key_value', 'predefined_db_fields_values' ];
		for(let field in remove) if(this[field]) delete this[field];
	}

	FixNumerics() {
        FixNumerics(this);
	}

    FixLanguageFields() {
        var cookie_lang=$.cookie('lang');
		if(!cookie_lang || ['en', 'gr', 'ru'].indexOf(cookie_lang)<0) {
			cookie_lang='en';
			$.cookie('lang', cookie_lang, { expires: 7 });
		}
		const cur_lang=cookie_lang;
        const fixed_keys=[];
        for(let [key, value] of Object.entries(this)) {
            if(key.endsWith('_en') || key.endsWith('_gr') || key.endsWith('_ru')) {
                const key_without_lang=key.substring(0, key.length-3);
                if(fixed_keys.indexOf(key_without_lang)<0) {
                    if(Empty(this[key_without_lang]) && !Empty(this[key_without_lang + '_' + cur_lang])) this[key_without_lang]=this[key_without_lang + '_' + cur_lang];
                    fixed_keys.push(key_without_lang);
                }
            }
        }
    }

}
/* AppClass model - End */