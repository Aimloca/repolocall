<?php

class Strings {

	public static function GetLanguage() {
		if(!in_array(Session::Get('language'), LANGUAGES)) Session::Add('language', DEFAULT_LANG);
		return Session::Get('language');
	}

	public static function GetLanguageIndex() {
		switch(Strings::GetLanguage()) {
			case 'gr': return 1;
			case 'ru': return 2;
			default: return 0;
		}
	}

	public static function GetLanguageByIndex($index) {
		switch($index) {
			case 1: return 'gr';
			case 2: return 'ru';
			default: return 'en';
		}
	}

	public static function SetLanguage($lang) {
		if($lang=='en' || $lang=='0') Session::Add('language', 'en');
		else if($lang=='gr' || $lang=='1') Session::Add('language', 'gr');
		else if($lang=='ru' || $lang=='2') Session::Add('language', 'ru');
		else Session::Add('language', DEFAULT_LANG);
		setcookie('lang', Strings::GetLanguage(), time()+3600*24*7);
	}

	public static function Get($id, $preferred_language='') {
		if(!empty($preferred_language) && isset($_SESSION['STRINGS'][$id][$preferred_language]) && !empty($_SESSION['STRINGS'][$id][$preferred_language])) return $_SESSION['STRINGS'][$id][$preferred_language];
		$lang=Strings::GetLanguage();
		if(isset($_SESSION['STRINGS'][$id][$lang]) && !empty($_SESSION['STRINGS'][$id][$lang])) {
			return $_SESSION['STRINGS'][$id][$lang];
		} else {
			DB::Query("INSERT IGNORE INTO STRINGS (id, position) VALUES (" . DB::Quote($id) . "," . DB::Quote(debug_backtrace()[1]['file'] . ":" . debug_backtrace()[1]['line']) . ");");
			return $id;
		}
	}

	public static function LoadStrings() {
		$_SESSION['STRINGS']=[];
		$rows=DB::Query('SELECT * FROM STRINGS;');
		if($rows) foreach($rows as $row) {
			$rec=[];
			foreach($row as $k=>$v) $rec[$k]=is_null($v) || $v=='' ? $row['en'] : $v;
			$_SESSION['STRINGS'][$row['id']]=$rec;
		}
	}

	public static function ReplaceModelsFields($text, $models=[]) {
		if(!is_array($models) && !($models instanceof Model)) return $text;
		if(!is_array($models)) $models=[$models];
		foreach($models as $model) {
			if(!($model instanceof Model)) continue;
			foreach($model as $field=>$value) {
				if(is_array($value) || is_object($value)) continue;
				$text=str_replace('#' . $model::table . '.' . $field . '#', $value==null || $value==DB_NULL_STRING ? '' : $value, $text);
			}
		}
		return $text;
	}

	public static function DecryptUrlSegment($input) {
		try {
			return @openssl_decrypt($input, 'AES-128-ECB', STRINGS_URL_KEY);
		} catch(Exception $e) {
			ErrorLogger::Write('Decryption error. ' . $e->getMessage());
			return false;
		}
	}

	public static function EncryptUrlSegment($input) {
		try {
			return @openssl_encrypt($input, 'AES-128-ECB', STRINGS_URL_KEY);
		} catch(Exception $e) {
			ErrorLogger::Write('Encryption error. ' . $e->getMessage());
			return false;
		}
	}

	public static function CreateEncryptedLink($link) {
		return BaseUrl() . 'index.php?' . Strings::EncryptUrlSegment($link);
	}

	public static function DecompressUrlSegment($input) {
		try {
			return urldecode(gzinflate(base64_decode(strtr($input, '-_', '+/'))));
		} catch(Exception $e) {
			ErrorLogger::Write('Decompression error. ' . $e->getMessage());
			return false;
		}
	}

	public static function CompressUrlSegment($input) {
		try {
			return urlencode(rtrim(strtr(base64_encode(gzdeflate($input, 9)), '+/', '-_'), '='));
		} catch(Exception $e) {
			ErrorLogger::Write('Compression error. ' . $e->getMessage());
			return false;
		}
	}

	public static function DecryptPass($input) {
		try {
			return @openssl_decrypt($input, 'AES-128-ECB', DB_PASS_KEY);
		} catch(Exception $e) {
			ErrorLogger::Write('Decryption error. ' . $e->getMessage());
			return false;
		}
	}

	public static function EncryptPass($input) {
		try {
			return @openssl_encrypt($input, 'AES-128-ECB', DB_PASS_KEY);
		} catch(Exception $e) {
			ErrorLogger::Write('Encryption error. ' . $e->getMessage());
			return false;
		}
	}

	public static function GetCustomTableStrings() {
		return ToJson(Strings::Get('custom_table_strings'));
	}

	public static function ToUTFEntity($inputed) {
		return html_entity_decode($inputed, ENT_COMPAT, 'UTF-8');
	}

	public static function ToUTF($inputed) {
		return iconv('ISO-8859-7', 'UTF-8//INGORE', $inputed);
	}

	public static function ToISO($inputed) {
		return iconv('UTF-8', 'ISO-8859-7//INGORE', $inputed);
	}

	public static function FormatNumber($number, $decs) {
		return sprintf('%0.' . $decs . 'f', $number);
	}

	public static function FormatAmount($amount) {
		return sprintf('%0.2f', $amount);
	}

	public static function GetSearchable($inputed) {
		$inputed=str_replace(["\r", "\n"], '', $inputed);
		$inputed=str_replace("\t", ' ', $inputed);
		$inputed=strtolower($inputed);
		$inputed=Strings::ConvertGreekToLatin($inputed);
		$inputed=Strings::RemoveDoubleSpace($inputed);
		$inputed=strtolower($inputed);
		return $inputed;
	}

	public static function ConvertGreekToLatin($inputed) {
		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("ά", "a", $inputed);
		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("α", "a", $inputed);
		$inputed=str_replace("β", "b", $inputed);
		$inputed=str_replace("γ", "g", $inputed);
		$inputed=str_replace("δ", "d", $inputed);
		$inputed=str_replace("ε", "e", $inputed);
		$inputed=str_replace("έ", "e", $inputed);
		$inputed=str_replace("ζ", "z", $inputed);
		$inputed=str_replace("η", "h", $inputed);
		$inputed=str_replace("ή", "h", $inputed);
		$inputed=str_replace("θ", "u", $inputed);
		$inputed=str_replace("ι", "i", $inputed);
		$inputed=str_replace("ί", "i", $inputed);
		$inputed=str_replace("ϊ", "i", $inputed);
		$inputed=str_replace("ΐ", "i", $inputed);
		$inputed=str_replace("κ", "k", $inputed);
		$inputed=str_replace("λ", "l", $inputed);
		$inputed=str_replace("μ", "m", $inputed);
		$inputed=str_replace("ν", "n", $inputed);
		$inputed=str_replace("ξ", "j", $inputed);
		$inputed=str_replace("ο", "o", $inputed);
		$inputed=str_replace("ό", "o", $inputed);
		$inputed=str_replace("π", "p", $inputed);
		$inputed=str_replace("ρ", "r", $inputed);
		$inputed=str_replace("σ", "s", $inputed);
		$inputed=str_replace("ς", "s", $inputed);
		$inputed=str_replace("τ", "t", $inputed);
		$inputed=str_replace("υ", "y", $inputed);
		$inputed=str_replace("ύ", "y", $inputed);
		$inputed=str_replace("ϋ", "y", $inputed);
		$inputed=str_replace("ΰ", "y", $inputed);
		$inputed=str_replace("φ", "f", $inputed);
		$inputed=str_replace("χ", "x", $inputed);
		$inputed=str_replace("ψ", "c", $inputed);
		$inputed=str_replace("ω", "v", $inputed);
		$inputed=str_replace("ώ", "v", $inputed);

		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Ά", "A", $inputed);
		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Α", "A", $inputed);
		$inputed=str_replace("Β", "B", $inputed);
		$inputed=str_replace("Γ", "G", $inputed);
		$inputed=str_replace("Δ", "D", $inputed);
		$inputed=str_replace("Ε", "E", $inputed);
		$inputed=str_replace("Έ", "E", $inputed);
		$inputed=str_replace("Ζ", "Z", $inputed);
		$inputed=str_replace("Η", "H", $inputed);
		$inputed=str_replace("Ή", "H", $inputed);
		$inputed=str_replace("Θ", "U", $inputed);
		$inputed=str_replace("Ι", "I", $inputed);
		$inputed=str_replace("Ί", "I", $inputed);
		$inputed=str_replace("Ϊ", "I", $inputed);
		$inputed=str_replace("Κ", "K", $inputed);
		$inputed=str_replace("Λ", "L", $inputed);
		$inputed=str_replace("Μ", "M", $inputed);
		$inputed=str_replace("Ν", "N", $inputed);
		$inputed=str_replace("Ξ", "J", $inputed);
		$inputed=str_replace("Ο", "O", $inputed);
		$inputed=str_replace("Ό", "O", $inputed);
		$inputed=str_replace("Π", "P", $inputed);
		$inputed=str_replace("Ρ", "R", $inputed);
		$inputed=str_replace("Σ", "S", $inputed);
		$inputed=str_replace("Τ", "T", $inputed);
		$inputed=str_replace("Υ", "Y", $inputed);
		$inputed=str_replace("Ύ", "Y", $inputed);
		$inputed=str_replace("Ϋ", "Y", $inputed);
		$inputed=str_replace("Φ", "F", $inputed);
		$inputed=str_replace("Χ", "X", $inputed);
		$inputed=str_replace("Ψ", "C", $inputed);
		$inputed=str_replace("Ω", "V", $inputed);
		$inputed=str_replace("Ώ", "V", $inputed);
		return $inputed;
	}

	public static function ConvertLatinToGreek($inputed) {
		$inputed=str_replace("a", "α", $inputed);
		$inputed=str_replace("b", "β", $inputed);
		$inputed=str_replace("c", "κ", $inputed);
		$inputed=str_replace("d", "δ", $inputed);
		$inputed=str_replace("e", "ε", $inputed);
		$inputed=str_replace("f", "φ", $inputed);
		$inputed=str_replace("h", "η", $inputed);
		$inputed=str_replace("g", "γ", $inputed);
		$inputed=str_replace("i", "ι", $inputed);
		$inputed=str_replace("j", "ξ", $inputed);
		$inputed=str_replace("k", "κ", $inputed);
		$inputed=str_replace("l", "λ", $inputed);
		$inputed=str_replace("m", "μ", $inputed);
		$inputed=str_replace("n", "ν", $inputed);
		$inputed=str_replace("o", "ο", $inputed);
		$inputed=str_replace("p", "π", $inputed);
		$inputed=str_replace("q", "κ", $inputed);
		$inputed=str_replace("r", "ρ", $inputed);
		$inputed=str_replace("s", "σ", $inputed);
		$inputed=str_replace("t", "τ", $inputed);
		$inputed=str_replace("u", "υ", $inputed);
		$inputed=str_replace("v", "β", $inputed);
		$inputed=str_replace("w", "ω", $inputed);
		$inputed=str_replace("x", "χ", $inputed);
		$inputed=str_replace("y", "υ", $inputed);
		$inputed=str_replace("z", "ζ", $inputed);

		$inputed=str_replace("A", "Α", $inputed);
		$inputed=str_replace("B", "Β", $inputed);
		$inputed=str_replace("C", "Κ", $inputed);
		$inputed=str_replace("D", "Δ", $inputed);
		$inputed=str_replace("E", "Ε", $inputed);
		$inputed=str_replace("F", "Φ", $inputed);
		$inputed=str_replace("H", "Η", $inputed);
		$inputed=str_replace("G", "Γ", $inputed);
		$inputed=str_replace("I", "Ι", $inputed);
		$inputed=str_replace("J", "Ξ", $inputed);
		$inputed=str_replace("K", "Κ", $inputed);
		$inputed=str_replace("L", "Λ", $inputed);
		$inputed=str_replace("M", "Μ", $inputed);
		$inputed=str_replace("N", "Ν", $inputed);
		$inputed=str_replace("O", "Ο", $inputed);
		$inputed=str_replace("P", "Π", $inputed);
		$inputed=str_replace("Q", "Κ", $inputed);
		$inputed=str_replace("R", "Ρ", $inputed);
		$inputed=str_replace("S", "Σ", $inputed);
		$inputed=str_replace("T", "Τ", $inputed);
		$inputed=str_replace("U", "Υ", $inputed);
		$inputed=str_replace("V", "Β", $inputed);
		$inputed=str_replace("W", "Ω", $inputed);
		$inputed=str_replace("X", "Χ", $inputed);
		$inputed=str_replace("Y", "Υ", $inputed);
		$inputed=str_replace("Z", "Ζ", $inputed);
		return $inputed;
	}

	public static function RemoveDoubleSpace($input) {
		if(empty($input)) return $input;
		while(strpos($input, '  ')!==false) $input=str_replace('  ', ' ', $input);
		return trim($input);
	}

	public static function StripTagsWithSpace($input) {
		if(empty($input)) return $input;
		$input=strip_tags(str_replace('<', ' <', str_replace('>', '> ', $input)));
		return Strings::RemoveDoubleSpace($input);
	}

	public static function GetRandomString($length=10, $characters='_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
		$characters_length = strlen($characters);
		$random_string = '';
		for ($i = 0; $i < $length; $i++) {
			$random_string .= $characters[rand(0, $characters_length - 1)];
		}
		return $random_string;
	}

	public static function KeepOnlyNumbers($in) {
		return preg_replace('/\D/', '', $in);
	}

	public static function GetForm($id, $language='') {
		Strings::GetLanguage();
		if(empty($language) || !in_array($language, LANGUAGES)) $language=Strings::GetLanguage();
		$form_path=FORMS_PATH . $id . '/';
		$form_file="{$form_path}form.{$language}.html";
		if(!file_exists($form_file)) return Strings::Get('error_form_file_not_found') . ' ' . $id;
		$content=@file_get_contents($form_file);
		if(empty($content)) return '';
		$constants=get_defined_constants();
		foreach($constants as $name=>$value) if(!is_array($value)) $content=str_replace('#' . $name . '#', $value, $content);
		$content=str_replace('#FORM_PATH#', $form_path, $content);
		$content=str_replace('#FORM_URL#', FORMS_URL . $id . '/', $content);
		$content=str_replace('#COPYRIGHT#', '&copy;' . date('Y'), $content);
		$content=str_replace('#SIGNATURE_IMAGE#', $_SERVER['PROTOCOL'] . IMAGES_URL . 'app_logo.png', $content);
		return $content;
	}

	public static function StartsWith($haystack, $needle, $match_case=true) {
		if($haystack=='' || $needle=='') return $haystack==$needle;
		$haystacks=is_array($haystack) ? $haystack : [ $haystack ];
		$needles=is_array($needle) ? $needle : [ $needle ];
		foreach($haystacks as $h) foreach($needles as $n)
			if(substr($h, 0, strlen($n))==$n || ($match_case && strtolower(substr($h, 0, strlen($n)))==strtolower($n))) return true;
		return false;
	}

	public static function FixDateAgo($date) {
		if(!$date) return '';
		$time=strtotime($date);
		if(time()-$time<60) return Strings::Get('just_now');
		else if(time()-$time<120) return Strings::Get('a_minute_ago');
		else if(time()-$time<3600) return str_replace('#MINUTES#', round((time()-$time)/60), Strings::Get('#MINUTES#_minutes_ago'));
		else if(date('Y-m-d')==date('Y-m-d', $time)) return str_replace('#TIME#', date('H:i', $time), Strings::Get('today_at_#TIME#'));
		else return date('d-m H:i', $time);
	}
}