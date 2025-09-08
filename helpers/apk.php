<?
class Apk {
	
	static function GetVersionCodeFromAPK() {

		$versionCode = "N/A";

		//AXML LEW 32-bit word (hex) for a start tag
		$XMLStartTag = "00100102";

		$result = file_get_contents('zip://' . APK_PATH . '#AndroidManifest.xml');
		$hex=unpack("H*", $result);
		$axml=current($hex);
		$axmlArr=Apk::convert2wordArray($axml);
		//Convert AXML 32-bit word array into Little Endian format 32-bit word array
		$axmlArr = Apk::convert2LEWwordArray($axmlArr);
		//Get first AXML open tag word index
		$firstStartTagword = Apk::findWord($axmlArr, $XMLStartTag);
		//The version code is 13 words after the first open tag word
		$version=intval($axmlArr[$firstStartTagword + 13], 16);
		return $version;
	}

	// Get the contents of the file in hex format
	static function getHex($zip, $zip_entry) {
		if (zip_entry_open($zip, $zip_entry, 'r')) {
			$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
			$hex = unpack("H*", $buf);
			return current($hex);
		}
	}

	// Given a hex byte stream, return an array of words
	static function convert2wordArray($hex) {
		$wordArr = array();
		$numwords = strlen($hex)/8;

		for ($i = 0; $i < $numwords; $i++)
			$wordArr[] = substr($hex, $i * 8, 8);

		return $wordArr;
	}

	// Given an array of words, convert them to Little Endian format (LSB first)
	static function convert2LEWwordArray($wordArr) {
		$LEWArr = array();

		foreach($wordArr as $word) {
			$LEWword = "";
			for ($i = 0; $i < strlen($word)/2; $i++)
				$LEWword .= substr($word, (strlen($word) - ($i*2) - 2), 2);
			$LEWArr[] = $LEWword;
		}

		return $LEWArr;
	}

	// Find a word in the word array and return its index value
	static function findWord($wordArr, $wordToFind) {
		$currentword = 0;
		foreach ($wordArr as $word) {
			if ($word == $wordToFind)
				return $currentword;
			else
				$currentword++;
		}
	}
}