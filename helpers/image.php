<?

class Image {

	public static $image_sizes=[ 80, 100, 300, 500, 700, 1000 ];

	public static function CreateTempFile() {
		return tempnam(sys_get_temp_dir(), 'onp_img_');
	}

	public static function FileToGraphic($file) {

		// Check file existence
		if(!file_exists($file)) {
			ErrorLogger::Write('Document: File ' . $file . ' does not exist.');
			return false;
		}

		// Check file extension
		$ext=strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if($ext=='jpg' || $ext=='jpeg')
			$result=imagecreatefromjpeg($file);
		else if($ext=='bmp')
			$result=imagecreatefrombmp($file);
		else if($ext=='gif')
			$result=imagecreatefromgif($file);
		else if($ext=='png')
			$result=imagecreatefrompng($file);
		else {
			ErrorLogger::Write('Document: Unknown image format ' . $file);
			return false;
		}
		if(!$result) {
			ErrorLogger::Write('Document: Cannot create image from ' . $file);
			return false;
		}

		return $result;
	}

	public static function Resize($image_file, $new_width, $new_image_file) {
		if(!$image_file_data=getimagesize($image_file)) return false;
		$width=$image_file_data[0];
		$height=$image_file_data[1];
		$ratio=$width/($height==0 ? 1 : $height);
		$new_height=$new_width/($ratio==0 ? 1 : $ratio);

		$src=imagecreatefromstring(file_get_contents($image_file));
		$dst=imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		imagedestroy($src);
		if($new_width>=500) {
			//imagefill($dst, 0, 0, imagecolorallocate($dst, 0, 0, 0));
			imagejpeg($dst, $new_image_file, 75);
		} else {
			imagecolortransparent($dst, imagecolorallocate($dst, 0, 0, 0));
			imagepng($dst, $new_image_file);
		}
		imagedestroy($dst);
		return true;
	}

	public static function Delete($image_file) {
		if(strlen($image_file)<=IMAGES_DATA_PATH || substr($image_file, 0, strlen(IMAGES_DATA_PATH))!=IMAGES_DATA_PATH) $image_file=IMAGES_DATA_PATH . $image_file;
		if(file_exists($image_file)) @unlink($image_file);
		foreach(Image::$image_sizes as $size) if(file_exists("{$image_file}.w{$size}")) @unlink("{$image_file}.w{$size}");
	}

	public static function Rename($image_file, $new_name) {
		if(file_exists($image_file)) @rename($image_file, $new_name);
		foreach(Image::$image_sizes as $size) if(file_exists("{$image_file}.w{$size}")) @rename("{$image_file}.w{$size}", "{$new_name}.w{$size}");
	}

	public static function AddWatermark($file, $temp_file='') {
		// Check image file
		if(!file_exists($file) || !is_readable($file)) return false;
		// Get dimensions and type
		if(!$image_file_data=getimagesize($file)) return false;
		if(!$image_file_data) return false;
		// Create image by type
		switch($image_file_data['mime']) {
			case 'image/jpeg': $img=imagecreatefromjpeg($file); break;
			case 'image/gif': $img=imagecreatefromgif($file); break;
			case 'image/png': $img=imagecreatefrompng($file); break;
			default: return false;
		}
		$img_width=imagesx($img);
		$img_height=imagesy($img);

		// Get watermark
		$file_watermark=IMAGES_PATH . 'watermark.png';
		// Create watermark image
		$img_watermark=imagecreatefrompng($file_watermark);
		// Get watermark dimensions
		$img_watermark_width = imagesx($img_watermark);
		$img_watermark_height = imagesy($img_watermark);

		// Put watermark on image
		imagecopy($img, $img_watermark, $img_width - $img_watermark_width - 15, $img_height - $img_watermark_height - 15, 0, 0, $img_watermark_width, $img_watermark_height);

		DeleteTemps('img.', 120);

		if(empty($temp_file)) $temp_file=time() . '_' . rand(10000, 99999) . '.jpg';
		if(@imagejpeg($img, $temp_file, 80)) return $temp_file;
		return false;
	}

	public static function FixResizedImages($file='', $output=false) {
		if(empty($file)) {
			foreach(Image::$image_sizes as $size) {
				$result=shell_exec("rm " . IMAGES_DATA_PATH . "*.w{$size}");
				if($output) echo "rm " . IMAGES_DATA_PATH . "*.w{$size}: {$result}\n";
			}
			$files=scandir(IMAGES_DATA_PATH);
			$total_files=count($files);
			$file_index=0;
			foreach($files as $file) {
				$file_index++;
				if($output) echo "$file_index / $total_files\n";
				if(substr($file, 0, 1)=='.') continue;
				if(substr($file, -4)=='.apk') continue;
				if(substr($file, -11)=='.properties') continue;
				if(substr($file, -5)=='.icon') {
					@unlink(IMAGES_DATA_PATH . $file);
					if($output) echo "$file: Deleted\n";
					continue;
				}
				if(substr($file, -5)=='.list') {
					@unlink(IMAGES_DATA_PATH . $file);
					if($output) echo "$file: Deleted\n";
					continue;
				}
				foreach(Image::$image_sizes as $size) {
					if(!file_exists(IMAGES_DATA_PATH . "{$file}.w{$size}")) {
						$result=Image::Resize(IMAGES_DATA_PATH . $file, $size, IMAGES_DATA_PATH . "{$file}.w{$size}");
						if($output) echo "{$file}.w{$size}: " . ($result ? 'Created' : 'Cannot be created') . "\n";
					} else {
						if($output) echo "{$file}.w{$size}: Exists\n";
					}
				}
			}
			foreach(Image::$image_sizes as $size) {
				$result=shell_exec("chown apache:apache " . IMAGES_DATA_PATH . "*.w{$size}");
				if($output) echo "chown apache:apache " . IMAGES_DATA_PATH . "*.w{$size}: {$result}\n";
			}
		} else {
			if(strpos($file, IMAGES_DATA_PATH)===false) $file=IMAGES_DATA_PATH . $file;
			if(file_exists($file)) {
				foreach(Image::$image_sizes as $size) {
					$result=Image::Resize($file, $size, "{$file}.w{$size}");
					if($output) echo "{$file}.w{$size}: " . ($result ? 'created' : 'cannot be created') . "\n";
				}
			} else {
				if($output) echo "File $file does not exists\n";
			}
		}
	}

	public static function Upload() {
		// Check permissions
		if(!Session::IsLoggedIn()) return new Response(false, Strings::Get('error_insufficient_rights'));
		// Check source
		$source=GetRequest('source');
		if(empty($source)) return new Response(false, Strings::Get('error_invalid_source'));
		if(count(explode('.', $source))!=3) return new Response(false, Strings::Get('error_invalid_source'));
		$file=isset($_FILES['id']) ? $_FILES['id'] : '';
		if(empty($file)) return new Response(false, Strings::Get('error_invalid_file'));
		$type=GetRequest('type');
		// Check file path
		if(empty($file['tmp_name'])) return new Response(false, Strings::Get('error_upload_file_path_is_empty'));
		// Check file type
		if($type!='image') return new Response(false, Strings::Get('error_file_type_not_accepted'));
		$file_extension=strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		// Check file extension
		if(empty($file_extension) || !in_array($file_extension, ACCEPTED_UPLOAD_EXTENSIONS['image'])) return new Response(false, Strings::Get('error_file_extension_not_accepted'));
		// Check file size
		if($file['size']>1024 * 1024 * 5) return new Response(0, str_replace('#MAX_UPLOAD_SIZE#', MAX_UPLOAD_SIZE, Strings::Get('file_is_too_large_#MAX_UPLOAD_SIZE#')) . ' [' . round($file['size']/1024/1024, 2) . 'MB]', true);
		if($file['size']>MAX_UPLOAD_SIZE) return new Response(false, Strings::Get('error_file_exceeds_max_upload_size'));
		$file_info = new finfo(FILEINFO_MIME_TYPE);
		// Check image
		$image_file_data=getimagesize($file['tmp_name']);
		if(!$image_file_data) return new Response(false, Strings::Get('error_invalid_image_file'));
		switch($image_file_data['mime']) {
			case 'image/jpeg': $image_format='jpg'; break;
			case 'image/gif': $image_format='gif'; break;
			case 'image/png': $image_format='png'; break;
			default: return new Response(false, Strings::Get('error_invalid_image_format'));
		}

		$target_file_name=$source;
		$target_file=IMAGES_DATA_PATH . $target_file_name;
		// Delete existing file
		if(file_exists($target_file)) @unlink($target_file);

		// Move file
		if(@move_uploaded_file($file['tmp_name'], $target_file)) {
			//Image::FixResizedImages($target_file);
			return new Response(true, Strings::Get('document_uploaded_successfully'), $target_file_name);
		} else {
			ErrorLogger::Write('Image cannot be uploaded: ' . print_r(error_get_last(), true));
			return new Response(false, Strings::Get('error_image_cannot_be_saved') . "\n" . print_r(error_get_last(), true));
		}
	}

	public static function GetUrlByModel(Model $model, $field, $id, $default='', $width=0) {
		$file=$model->table . '.' . $field . '.' . $id;
		return Image::GetUrl($model->table . '.' . $field . '.' . $id, $default, $width);
	}

	public static function GetUrl($model_field_id, $default='', $width=0) {
		if($width<=0 && !empty(GetRequest('screen_width'))) $width=intval(GetRequest('screen_width'));
		$size='';
		if($width>0) foreach(Image::$image_sizes as $isize) if($width<=$isize) { $size=$isize; break; }
		$file_name="{$model_field_id}.w{$size}";
		if(file_exists(IMAGES_DATA_PATH . $file_name)) {
			 return (StartsWith(IMAGES_DATA_URL, 'http') ? '' : 'https:') . IMAGES_DATA_URL . $file_name . '?t=' . filemtime(IMAGES_DATA_PATH . $file_name);
		} else if(file_exists(IMAGES_DATA_PATH . $model_field_id)){
			return (StartsWith(IMAGES_DATA_URL, 'http') ? '' : 'https:') . IMAGES_DATA_URL . $model_field_id . '?t=' . filemtime(IMAGES_DATA_PATH . $model_field_id);
		} else {
			return $default;
		}
	}

	public static function Base64ToFile($base64_string, $output_file) {
		// Open the output file for writing
		$ifp=fopen($output_file, 'wb');

		// Split the string on commas
		// $data[ 0 ] == "data:image/png;base64"
		// $data[ 1 ] == <actual base64 string>
		$data=explode(',', $base64_string);

		// Write to file
		fwrite($ifp, base64_decode(end($data)));

		// Clean up the file resource
		fclose($ifp);

		return $output_file;
	}

	public static function GetQRUrl($data, $format='JPG', $size=500, $foreground_color='#000000', $frame_color='#000000') {
		return $data=='' ? '' : 'https://public-api.qr-code-generator.com/v1/create/free?image_format=' . urlencode($format) . '&image_width=' . urlencode($size) . '&foreground_color=' . urlencode($foreground_color) . '&frame_color=' . urlencode($frame_color) . '&frame_name=no-frame&qr_code_logo=&qr_code_pattern=rounded-3&qr_code_text=' . urlencode($data);
	}

}