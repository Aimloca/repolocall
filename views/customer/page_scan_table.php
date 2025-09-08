<?

if(GetRequest('new_customer')=='1' && !Session::IsCustomer()) Customer::LoginAnonymous();

// Print page head
PrintPageHead(Strings::Get('page_title_scan_table'), '', '', '', '', '<script src="' . JS_URL . 'qr_scanner.js"></script>');

?>
	<body>
		<style>
			body { background-color: black; }
			#scan_header { position: fixed; left: 0; top: 0; right: 0; width: 100%; padding: 10px; display: flex; flex-direction: row; background-color: rgba(0, 0, 0, 0.7);  }
			#go_back { width: 30px; height: 30px; cursor: pointer; }
			#scan_header_title { flex: 1; color: white; text-align: center; font-size: large; text-shadow: 0 0 3px black; }
			#empty_placeholder { width: 30px; height: 30px; }
			#qr-canvas { position: fixed; left: 50%; top: 50%; transform: translateX(-50%) translateY(-50%);  }
			#bottom_toast { display: none; position: fixed; left: 40px; bottom: 40px; right: 40px; padding: 10px; font-size: x-large; text-align: center; color: white; background-color: rgba(255, 0, 0, 0.75); border-radius: 100px; }
		</style>
		<canvas id="qr-canvas" hidden=""></canvas>
		<div id="scan_header">
			<img id="go_back" src="<?=IMAGES_URL?>previous_white.png" onclick="history.back();" />
			<div id="scan_header_title"><?=Strings::Get('page_title_scan_table')?></div>
			<div id="empty_placeholder"></div>
		</div>
		<div id="bottom_toast"></div>

		<script type="text/javascript">
			var qrcode=window.qrcode;
			var elm_video=document.createElement('video');
			var elm_canvas=document.getElementById('qr-canvas');
			var elm_toast=document.getElementById('bottom_toast');
			var canvas=elm_canvas.getContext('2d');
			var scanning=false;
			var size_fixed=false;

			<? if(Session::IsCustomer() && 1==2) { ?>
			document.getElementById('go_back').style.display='none';
			document.getElementById('empty_placeholder').style.display='none';
			<? } ?>

			setTimeout(StartScanning, 1000);

			function DataScanned(scanned) {
				if(scanned.indexOf('<?=BaseUrl()?>')!=0) {
					$(elm_toast).text('<?=Strings::Get('error_invalid_scan')?>');
					$(elm_toast).fadeIn('slow', function() { setTimeout(function(){ $(elm_toast).fadeOut(); StartScanning(); }, 3000); });
					return;
				}
				ShowLoader();
				window.location=scanned;
			}

			function StartScanning() {
				navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
					scanning = true;
					elm_canvas.hidden = false;
					elm_video.setAttribute('playsinline', true); // required to tell iOS safari we don't want fullscreen
					elm_video.srcObject = stream;
					elm_video.play();
					tick();
					scan();
				});
			}

			function FixSize() {
				// Get video width, height and aspect
				const vw=elm_video.videoWidth;
				const vh=elm_video.videoHeight;
				if(vw==0 || vh==0) return;
				const va=vw/(vh==0 ? 1 : vh);
				// Get screen width, height and aspect
				const sw=screen.availWidth;
				const sh=screen.availHeight;
				const sa=sw/(sh==0 ? 1 : sh);
				// Set size
				const dw=vw/sw;
				const dh=vh/sh;
				const mind=Math.min(dw, dh);
				if(mind==0) return;
				// Set size
				elm_canvas.width=vw/mind;
				elm_canvas.height=vh/mind;
				size_fixed=elm_canvas.width>0;
			}

			qrcode.callback=function(res) {
				if(res) {
					scanning = false;
					elm_video.srcObject.getTracks().forEach(function (track) { track.stop(); });
					elm_canvas.hidden = true;
					DataScanned(res);
				}
			};

			function tick() {
				if(!size_fixed) FixSize();
				canvas.drawImage(elm_video, 0, 0, elm_canvas.width, elm_canvas.height);
				scanning && requestAnimationFrame(tick);
			}

			function scan() {
			  try { qrcode.decode(); } catch (e) { setTimeout(scan, 300); }
			}

			window.addEventListener('resize', function(event) {
				size_fixed=false;
			}, true);
		</script>

<? die(GetPageFooter(''));