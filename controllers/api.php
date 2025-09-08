<?php

class Api {

	public static function Parse() {

		$controller=GetRequest('controller');
		$action=GetRequest('action');
		$id=GetRequest('id');

		// Check controller
		// DEBUG
		if($controller=='debug') {
			// Check admin
			//if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
			// Check action
			if($action=='adam') {
				exit;
				DieJsonResponse(true, 'OK', DB_TABLES['USER']);
			} else if($action=='session') {
				DieJsonResponse(true, 'OK', array_merge($_SESSION, [
					'IsAdmin'=>Session::IsAdmin(),
					'IsUser'=>Session::IsUser(),
					'IsShopManager'=>Session::IsShopManager(),
					'IsBarista'=>Session::IsBarista(),
					'IsPreparation'=>Session::IsPreparation(),
					'IsWaiter'=>Session::IsWaiter(),
					'IsCustomer'=>Session::IsCustomer(),
				]));
			} else if($action=='php_info') {
				phpinfo(); exit;
			} else if($action=='fix_tables_sorting') {
				$result=Table::FixAllSortings();
				DieJsonResponse($result, $result ? 'OK' : 'ERROR');
			} else if($action=='fix_categories_sorting') {
				$result=ProductCategory::FixAllSortings();
				DieJsonResponse($result, $result ? 'OK' : 'ERROR');
			} else if($action=='encrypt') {
				DieJsonResponse(true, 'OK', Strings::EncryptPass(GetRequest('text')));
			} else if($action=='decrypt') {
				DieJsonResponse(true, 'OK', Strings::DecryptPass(GetRequest('text')));
			} else if($action=='decrypt_url') {
				DieJsonResponse(true, 'OK', Strings::DecryptUrlSegment(GetRequest('text')));
			} else if($action=='system_error_log') {
				$source=file_get_contents('/var/www/vhosts/orderandpay.gr/logs/panel.orderandpay.gr/error_log');
				die("<pre>{$source}</pre>");
				DieJsonResponse(true, 'OK', $source);
			} else {
				DieJsonResponse(false, Strings::Get('error_action_not_found') . " {$action}");
			}
		} else if($controller=='mydata_incame_category') {
			if($action=='list') {
				die("bre oust");
				$result = DB::Query('SELECT * FROM `MYDATA_INCOME_CATEGORY`');
				//diep($result);
				//$result = DB::GetTable('MYDATA_INCOME_CATEGORY');
				return new Response(true, str_replace('#COUNT#', $result ? count($result) : 0, Strings::Get('found_#COUNT#_records')), $result);
			} else {
				DieJsonResponse(false, Strings::Get('error_action_not_found') . " {$action}");
			}

		// AUTOGEN
		} else if($controller=='autogen') {
			// Check admin
			//if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
			// Check action
			if($action=='all') {
				$errors=[]; $files=[];
				if(!AutogenDBTables()) $errors[]='AutogenDBTables';
				if(!AutogenIncludes()) $errors[]='AutogenIncludes';
				if(!AutogenJsConstants(GetRequest('obfuscate')=='1')) $errors[]='AutogenJsConstants';
				if(!AutogenJsTables()) $errors[]='AutogenJsTables';
				if(!AutogenJsStrings()) $errors[]='AutogenJsStrings';
				if(!AutogenJsModels(GetRequest('obfuscate')=='1')) $errors[]='AutogenJsModels';
				foreach(scandir(AUTOGEN_PATH) as $file) if(substr($file, -4)=='.php') $files[$file]=round(filesize(AUTOGEN_PATH . $file)/1024, 2) . 'Kb';
				foreach(scandir(JS_PATH) as $file) if(substr($file, 0, 8)=='autogen_' && substr($file, -3)=='.js') $files[$file]=round(filesize(JS_PATH . $file)/1024, 2) . 'Kb';
				DieJsonResponse(empty($errors), Strings::Get('autogen_files_created'), [ 'files' => $files, 'errors' => $errors ]);
			} else {
				DieJsonResponse(false, Strings::Get('error_action_not_found') . " {$action}");
			}

		// ADMIN
		} else if($controller=='admin') {
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
			// Check action
			DieJsonResponse(Admin::HandleApi($id, $action));

		// ACCOUNT
		} else if($controller=='account') {
			// Check action
			DieJsonResponse(Account::HandleApi($id, $action));

		// BUY DOCUMENT
		} else if($controller=='buy_document') {
			// Check action
			DieJsonResponse(BuyDocument::HandleApi($id, $action));

		// BUY SERIES
		} else if($controller=='buy_series') {
			// Check action
			DieJsonResponse(BuySeries::HandleApi($id, $action));

		// COMPANY
		} else if($controller=='company') {
			// Check action
			DieJsonResponse(Company::HandleApi($id, $action));

		// COMPANY CUSTOMER
		} else if($controller=='company_customer') {
			// Check action
			DieJsonResponse(CompanyCustomer::HandleApi($id, $action));

		// CUSTOMER
		} else if($controller=='customer') {
			// Check action
			DieJsonResponse(Customer::HandleApi($id, $action));

		// DAY END
		} else if($controller=='day_end') {
			// Check action
			DieJsonResponse(DayEnd::HandleApi($id, $action));

		// DEPARTMENT
		} else if($controller=='department') {
			// Check action
			DieJsonResponse(Department::HandleApi($id, $action));

		// NOTIFICATION
		} else if($controller=='notification') {
			// Check action
			DieJsonResponse(Notification::HandleApi($id, $action));

		// ORDER
		} else if($controller=='order') {
			// Check action
			DieJsonResponse(Order::HandleApi($id, $action));

		// PAYMENT
		} else if($controller=='payment') {
			// Check action
			DieJsonResponse(Payment::HandleApi($id, $action));

		// PRODUCT
		} else if($controller=='product') {
			// Check action
			DieJsonResponse(Product::HandleApi($id, $action));

		// PRODUCT CATEGORY
		} else if($controller=='product_category') {
			// Check action
			DieJsonResponse(ProductCategory::HandleApi($id, $action));

		// ROOM
		} else if($controller=='room') {
			// Check action
			DieJsonResponse(Room::HandleApi($id, $action));

		// SALE DOCUMENT
		} else if($controller=='sale_document') {
			// Check action
			DieJsonResponse(SaleDocument::HandleApi($id, $action));

		// SALE SERIES
		} else if($controller=='sale_series') {
			// Check action
			DieJsonResponse(SaleSeries::HandleApi($id, $action));

		// SPEC
		} else if($controller=='spec') {
			// Check action
			DieJsonResponse(Spec::HandleApi($id, $action));

		// TABLE
		} else if($controller=='table') {
			// Check action
			DieJsonResponse(Table::HandleApi($id, $action));

		// TIP
		} else if($controller=='tip') {
			// Check action
			DieJsonResponse(Tip::HandleApi($id, $action));

		// UNIT
		} else if($controller=='unit') {
			// Check action
			DieJsonResponse(Unit::HandleApi($id, $action));

		// USER
		} else if($controller=='user') {
			// Check action
			DieJsonResponse(User::HandleApi($id, $action));

		// VAT CATEGORY
		} else if($controller=='vat_category') {
			// Check action
			DieJsonResponse(VatCategory::HandleApi($id, $action));





		// DOCUMENTS
		} else if($controller=='document') {
			// Check action
			if($action=='upload') {
				$type=GetRequest('type');
				// Check file type
				if(empty($type) || !in_array($type, ACCEPTED_UPLOAD_TYPES)) DieJsonResponse(false, Strings::Get('error_file_type_not_accepted'));
				switch($type) {
					case 'image': 	$document_type=0; break;
					case 'video': 	$document_type=1; break;
					case 'text': 	$document_type=2; break;
					default: DieJsonResponse(false, Strings::Get('error_file_type_not_accepted'));
				}
				// Check image
				if($type=='image') DieJsonResponse(Image::Upload());

				/*
				// Check permissions
				if(!Session::IsLoggedIn()) DieJsonResponse(false, Strings::Get('error_login_required'));
				// Check source
				$source=GetRequest('source');
				if(empty($source)) DieJsonResponse(false, Strings::Get('error_invalid_source'));
				if(count(explode('.', $source))!=3) DieJsonResponse(false, Strings::Get('error_invalid_source'));
				$file=isset($_FILES['id']) ? $_FILES['id'] : '';
				if(empty($file)) DieJsonResponse(false, Strings::Get('error_invalid_file'));
				$type=GetRequest('type');
				// Check file type
				if(empty($type) || !in_array($type, ACCEPTED_UPLOAD_TYPES)) DieJsonResponse(false, Strings::Get('error_file_type_not_accepted'));
				switch($type) {
					case 'image': 	$document_type=0; break;
					case 'video': 	$document_type=1; break;
					case 'text': 	$document_type=2; break;
					default: DieJsonResponse(false, Strings::Get('error_file_type_not_accepted'));
				}
				$file_extension=strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				// Check file extension
				if(empty($file_extension) || !in_array($file_extension, ACCEPTED_UPLOAD_EXTENSIONS[$type])) DieJsonResponse(false, Strings::Get('error_file_extension_not_accepted'));
				// Check file size
				if($file['size']>MAX_UPLOAD_SIZE) DieJsonResponse(false, Strings::Get('error_file_exceeds_max_upload_size'));
				$file_info = new finfo(FILEINFO_MIME_TYPE);
				// Check image
				if($type=='image') return Image::Upload();
				*/

			} else {
				DieJsonResponse(false, Strings::Get('error_action_not_found') . " {$action}");
			}


		// CUSTOM LISTS
		} else if($controller=='user' && $action=='list') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
			$rows=User::GetList();
			DieJsonResponse(true, str_replace('#COUNT#', count($rows), Strings::Get('found_#COUNT#_records')), $rows);

		} else if($controller=='station' && $action=='list') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
			$rows=DB::GetTable('attendance_stations');
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		} else if($controller=='department' && $action=='list') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			$rows=Department::GetList();
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		} else if($controller=='employee' && $action=='fix_encrypted') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get("Insufficient rights"));
			$sql='';
			$employees=DB::Query('SELECT id, user_name FROM attendance_employees ORDER BY id;');
			if($employees) foreach($employees as $employee) $sql.="UPDATE attendance_employees SET user_name_enc=" . DB::Quote(EncryptCredentials($employee['user_name'])) . " WHERE id=" . DB::Quote(EncryptCredentials($employee['id'])) . ";\n";
			$result=DB::Update($sql);
			DieJsonResponse($result, $result ? 'Τα κωδικοιποιημένα user names των υπαλλήλων ενημερώθηκαν' : 'Πρόβλημα κατά την ενημέρωση. ' . print_r(DB::GetLastError(), true));
		} else if($controller=='employee' && $action=='list') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			$rows=Employee::GetList();
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		} else if($controller=='event' && $action=='list') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			$rows=Event::GetList();
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		} else if($controller=='user' && $action=='invalid_events') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			DieJsonResponse(true, 'OK', Event::GetInvalid());

		} else if($controller=='notification' && $action=='list') {

		} else if($controller=='event' && $action=='import_from_station') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get("Insufficient rights"));
			$import=Event::ImportFromLogs(GetRequest('date'), GetRequest('station_id'), GetRequest('department_id'));
			DieJsonResponse($import['status'], $import['message']);

		} else if($controller=='notification' && $action=='list') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get("Insufficient rights"));
			$rows=Notification::GetList();
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		} else if($controller=='event' && $action=='fix_employee') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get("Insufficient rights"));
			// Get fix all parameter
			$fix_all=GetRequest('fix_all')=='1';
			$errors='';

			// Get employees, departments and companies
			$sql="
				SELECT E.id, E.user_name, E.user_name_enc, E.department_id, D.id AS join_department_id, C.id AS company_id
				FROM attendance_employees AS E
				LEFT JOIN attendance_departments AS D ON E.department_id=D.id
				LEFT JOIN attendance_companies AS C ON D.company_id=C.id
				ORDER BY E.id;
			";
			$rows=DB::Query($sql);
			$employees=[];
			if($rows) foreach($rows as $row) {
				$employees[$row['id']]=$row;
				if($row['user_name']!=$row['user_name_enc']) $employees[$row['user_name_enc']]=$row;
				if(empty($row['department_id'])) {
					$errors.="Employee {$row['id']} - {$row['user_name']} does not have department. (department_id={$row['department_id']})\n";
				} else if(empty($row['join_department_id'])) {
					$errors.="Employee {$row['id']} - {$row['user_name']} does not have valid department. (department_id={$row['department_id']})\n";
				} else if(empty($row['company_id'])) {
					$errors.="Employee {$row['id']} - {$row['user_name']} has department with invalid company. (department_id={$row['department_id']})\n";
				}
			}
			$update='';

			// Get events
			$sql='SELECT id, date, user_name, employee_id FROM attendance_events WHERE type IN (0, 1) ' . ($fix_all ? '' : 'AND (user_name IS NULL OR company_id IS NULL OR department_id IS NULL OR employee_id IS NULL)') . ' ORDER BY id;';
			$events=DB::Query($sql);
			if($events) foreach($events as $event) {
				$sql='';

				if(empty($event['employee_id']) && empty($event['user_name'])) {
					$errors.="Cannot find employee of event {$event['date']} ({$event['id']}).\n";
					continue;
				} else if(!empty($event['employee_id'])) {
					$employee=empty($employees[$event['employee_id']]) ? null : $employees[$event['employee_id']];
					if(empty($employee)) {
						$errors.="Cannot find employee with id {$event['employee_id']}.\n";
						continue;
					}
				} else {
					$employee=null;
					foreach($employees as $eid=>$e) if($e['user_name']==$event['user_name']) { $employee=$e; break; }
					if(empty($employee)) {
						$errors.="Cannot find employee with user name {$event['user_name']}.\n";
						continue;
					}
				}

				if(empty($employee['id'])) {
					$errors.="Cannot find employee id of employee {$employee['user_name']}.\n";
				} else {
					$sql.=($sql=='' ? '' : ', ') . 'user_name=' . DB::Quote($employee['user_name']) . ', employee_id=' . DB::Quote($employee['id']);
				}
				if(empty($employee['department_id'])) {
					$errors.="Cannot find department of employee {$employee['user_name']}.\n";
				} else {
					$sql.=($sql=='' ? '' : ', ') . 'department_id=' . DB::Quote($employee['department_id']);
				}
				if(empty($employee['company_id'])) {
					$errors.="Cannot find company of employee {$employee['user_name']}.\n";
				} else {
					$sql.=($sql=='' ? '' : ', ') . 'company_id=' . DB::Quote($employee['company_id']);
				}
				if(empty($sql)) continue;
				$update.="UPDATE attendance_events SET {$sql} WHERE id=" . DB::Quote($event['id']) . ";\n";
			}

			if(empty($update)) DieJsonResponse(true, 'Δε βρέθηκαν εγγραφές για ενημέρωση', $errors);
			$result=DB::Update($update);
			DieJsonResponse($result, $result ? 'Έγινε ενημέρωση στοιχείων υπαλλήλων σε ' . DB::GetAffected() . ' συμβάντα' : 'Πρόβλημα κατά την ενημέρωση. ' . print_r(DB::GetLastError(), true), $errors);

		} else if($controller=='department' && $action=='overtime') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			// Get from
			$from=GetRequest('from');
			if(empty($from)) $from=date('Y-m-01 00:00:00');
			if(strpos($from, ':')===false) $from.=" 00:00:00";
			// Get to
			$to=GetRequest('to');
			if(empty($to)) $to=date('Y-m-d 23:59:59');
			if(strpos($to, ':')===false) $to.=" 23:59:59";
			// Get department
			$department_id=Session::IsAdmin() ? GetRequest('department_id') : Session::User()->department_id;
			if(empty($department_id)) DieJsonResponse(false, "Το τμήμα είναι υποχρεωτικό");
			// Validate department
			$department=new Department($department_id);
			if(!$department->Load()) DieJsonResponse(false, "Μη έγκυρο τμήμα");
			// Full day overtime
			$set_full_day_overtime=GetRequest('set_full_day_overtime');
			$unset_full_day_overtime=GetRequest('unset_full_day_overtime');
			if($set_full_day_overtime || $unset_full_day_overtime) {
				// Get employee
				$employee_id=GetRequest('employee_id');
				if(empty($employee_id)) DieJsonResponse(false, "Δεν ορίστηκε id υπαλλήλου");
				// Validate employee
				$employee=new Employee($employee_id);
				if(!$employee->Load()) DieJsonResponse(false, "Μη έγκυρος υπάλληλος");
				if(!Session::IsAdmin() && $employee->department_id!=Session::User()->department_id) DieJsonResponse(false, "Ο υπάλληλος δεν ανήκει στο τμήμα σας");
				// Update full_day_overtime
				$sql="
					UPDATE attendance_events
					SET full_day_overtime=" . ($set_full_day_overtime ? 1 : 0) . "
					WHERE 	type=0
							AND employee_id=" . DB::Quote($employee_id) . "
							AND date>='" . ($set_full_day_overtime ? $set_full_day_overtime : $unset_full_day_overtime) . " 00:00:00'
							AND date<='" . ($set_full_day_overtime ? $set_full_day_overtime : $unset_full_day_overtime) . " 23:59:59';
				";
				diep($sql);
				if(!DB::Update($update)) DieJsonResponse(false, "Σφάλμα κατά την ενημέρωση των συμβάντων", $sql);
			}
			DieJsonResponse(true, 'OK', $department->CreateOvertimeExcel($from, $to));

		} else if($controller=='department' && $action=='in_out') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			// Get from
			$from=GetRequest('from');
			if(empty($from)) $from=date('Y-m-01 00:00:00');
			if(strpos($from, ':')===false) $from.=" 00:00:00";
			// Get to
			$to=GetRequest('to');
			if(empty($to)) $to=date('Y-m-d 23:59:59');
			if(strpos($to, ':')===false) $to.=" 23:59:59";
			// Get department
			$department_id=Session::IsAdmin() ? GetRequest('department_id') : Session::User()->department_id;
			if(empty($department_id)) DieJsonResponse(false, "Το τμήμα είναι υποχρεωτικό");
			// Validate department
			$department=new Department($department_id);
			if(!$department->Load()) DieJsonResponse(false, "Μη έγκυρο τμήμα");
			// Get employee
			$employee_id=GetRequest('employee_id');
			// Validate employee
			if($employee_id) {
				$employee=new employee($employee_id);
				if(!$employee->Load()) DieJsonResponse(false, "Μη έγκυρος υπάλληλος");
			}
			DieJsonResponse(true, 'OK', $department->GetInsOuts($from, $to, $employee_id, GetRequest('show_type')));

		// STATION
		} else if($controller=='station' && $action=='employee_login') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station');
			// Get username
			$username=GetRequest('username');
			if(empty($username)) DieJsonResponse(false, 'Empty username');
			// Get password
			$password=GetRequest('password');
			if(empty($password)) DieJsonResponse(false, 'Empty password');
			// Validate username and password
			$sql="
				SELECT id
				FROM attendance_employees
				WHERE  (user_name=" . DB::Quote($username) . " AND password=" . DB::Quote($password) . ")
					OR (user_name=" . DB::Quote(DecryptCredentials($username)) . " AND password=" . DB::Quote(DecryptCredentials($password)) . ")
				LIMIT 1;
			";
			$row=DB::Query($sql);
			if(empty($row)) {
				AddToRequests("Invalid login {$_SERVER['REMOTE_ADDR']}: Station: $station_id - Date: " . date('Y-m-d H:i:s') . " - Employee: $username - Pass: $password");
				DieJsonResponse(false, 'Invalid login credentials');
			}
			// Load employee
			$employee=new Employee($row[0]['id']);
			// Check
			if(!$employee->Load()) {
				AddToRequests("Invalid login {$_SERVER['REMOTE_ADDR']}: Station: $station_id - Date: " . date('Y-m-d H:i:s') . " - Employee: $username - Pass: $password");
				DieJsonResponse(false, 'Invalid login credentials');
			}
			// Set encrypted password
			$employee->password_enc=EncryptCredentials($employee->password);
			// Set full name
			$employee->full_name="{$employee->last_name} {$employee->first_name}";
			// Get company data
			$employee->GetCompanyData();
			// Update last_login
			$employee->last_login=date('Y-m-d H:i:s');
			$employee->Save();
			AddToRequests("Successful login {$_SERVER['REMOTE_ADDR']}: Station: $station_id - Date: " . date('Y-m-d H:i:s') . " - Employee: $username");
			DieJsonResponse(true, "{$employee->last_name} {$employee->first_name}", $employee);

		} else if($controller=='station' && $action=='get_time') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$station=new Station($station_id);
			if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
			$station->ip=$_SERVER['REMOTE_ADDR'];
			$station->last_contact=date('Y-m-d H:i:s');
			$station->Save();
			DieJsonResponse(true, 'OK', date('y.m.d.H.i.s.u'));

		} else if($controller=='station' && $action=='version_check') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$station=new Station($station_id);
			if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
			$station->ip=$_SERVER['REMOTE_ADDR'];
			$station->last_contact=date('Y-m-d H:i:s');
			$station->app_version=GetRequest('app_version');
			$station->Save();
			// Check if APK exists
			if(!file_exists(APK_PATH)) DieJsonResponse(false, 'APK does not exist');
			$should_parse_apk=false;
			$apk_date=filemtime(APK_PATH);
			if(file_exists(APK_VERSION_PATH)) {
				$contents=explode("\n", file_get_contents(APK_VERSION_PATH));
				$version_int=$contents[0];
				$version_date=count($contents)>1 ? $contents[1] : '';
				if($apk_date==$version_date) DieJsonResponse(true, 'OK', [ 'version' => $version_int, 'date' => $apk_date ], 'From version file');
			}
			$version_int=Apk::GetVersionCodeFromAPK();
			if(empty($version_int)) DieJsonResponse(false, 'Cannot get APK version');
			file_put_contents(APK_VERSION_PATH, "{$version_int}\n{$apk_date}");
			DieJsonResponse(true, 'OK', [ 'version' => $version_int, 'date' => $apk_date ], 'From parsed APK');

		} else if($controller=='station' && $action=='encrypt') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$creds=GetRequest('creds');
			DieJsonResponse(true, 'OK', EncryptCredentials($creds));

		} else if($controller=='station' && $action=='decrypt') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$creds=GetRequest('creds');
			DieJsonResponse(true, 'OK', DecryptCredentials($creds));

		} else if($controller=='station' && $action=='get_companies') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$station=new Station($station_id);
			if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
			$station->ip=$_SERVER['REMOTE_ADDR'];
			$station->last_contact=date('Y-m-d H:i:s');
			$station->Save();
			DieJsonResponse(true, 'OK', Company::GetListWithDepartments());

		} else if($controller=='station' && $action=='get_stations') {
			// Get station
			$station_id=GetRequest('station_id');
			if(!empty($station_id) && $station_id!='new') {
				$station=new Station($station_id);
				if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
				$station->ip=$_SERVER['REMOTE_ADDR'];
				$station->last_contact=date('Y-m-d H:i:s');
				$station->Save();
			}
			DieJsonResponse(true, 'OK', DB::Query("SELECT * FROM attendance_stations ORDER BY name;"));

		} else if($controller=='station' && $action=='get_employees') {
			// Get station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$station=new Station($station_id);
			if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
			$station->ip=$_SERVER['REMOTE_ADDR'];
			$station->last_contact=date('Y-m-d H:i:s');
			$station->Save();

			$employees=[];
			// Get employees
			$sql="
				SELECT E.*, D.name AS department_name, C.id AS company_id, C.name AS company_name
				FROM attendance_employees AS E
				LEFT JOIN attendance_departments AS D ON E.department_id=D.id
				LEFT JOIN attendance_companies AS C ON D.company_id=C.id
				ORDER BY C.id, E.id;
			";
			$rows=DB::Query($sql);
			if($rows) foreach($rows as $row) {
				$row['password_enc']=EncryptCredentials($row['password']);
				$row['full_name']="{$row['last_name']} {$row['first_name']}";
				$employees[]=$row;
			}
			DieJsonResponse(true, 'OK', $employees);

		} else if($controller=='station' && $action=='event') {
			// Validate station
			$station_id=GetRequest('station_id');
			if(empty($station_id)) DieJsonResponse(false, 'Empty station_id');
			$station=new Station($station_id);
			if(!$station->Load()) DieJsonResponse(false, 'Invalid station id');
			// Get passed parameters
			$id=GetRequest('id');
			$date=GetRequest('date');
			$type=preg_replace('/\D/', '', GetRequest('type'));
			$employee_id=preg_replace('/\D/', '', GetRequest('employee_id'));
			$company_id=preg_replace('/\D/', '', GetRequest('company_id'));
			$department_id=preg_replace('/\D/', '', GetRequest('department_id'));
			$username=GetRequest('username');
			$extra=GetRequest('extra');

			$image=$_FILES && isset($_FILES['image']) ? $_FILES['image'] : '';
			$image_before=$_FILES && isset($_FILES['image_before']) ? $_FILES['image_before'] : '';
			$image_after=$_FILES && isset($_FILES['image_after']) ? $_FILES['image_after'] : '';

			AddToLog("Event from {$_SERVER['REMOTE_ADDR']}: Id: $id - Date: $date - Type: $type - Employee: $username - Id: $employee_id - Company: $company_id - Department: $department_id - Station: $station_id - Extra: $extra - Image: " . (empty($image) ? '0' : '1') . " - Image before: " . (empty($image_before) ? '0' : '1') . " - Image after: " . (empty($image_after) ? '0' : '1'));

			$employee=null;
			$has_image=0;
			// Validate id
			if(empty($id)) DieJsonResponse(false, 'Missing id');
			if(!is_numeric($id)) DieJsonResponse(false, 'Invalid id');
			// Check date
			if(empty($date)) $date=date('Y-m-d H:i:s', round($id/1000));
			// Validate type
			if($type<0 || $type>4) DieJsonResponse(false, "Invalid type {$type}.");
			if($type=='0' || $type=='1') {
				// Validate employee
				if(empty($employee_id)) DieJsonResponse(false, 'Missing employee id');
				$employee=new Employee($employee_id);
				if(!$employee->Load()) DieJsonResponse(false, 'Invalid employee id');
				$employee->KeepOnlyDBFields();
				$employee->GetCompanyData();
				unset($employee->password);
				$notification_message='';
				// Check user name
				if($employee->user_name!=$username) $notification_message.="Employee's user name ({$employee->user_name}) mismatches event's user name id ({$username}).<br />\n";
				// Check department id
				if($employee->department_id!=$department_id) $notification_message.="Employee's department id ({$employee->department_id}) mismatches event's department id ({$department_id}).<br />\n";
				// Check company id
				if($employee->company_id!=$company_id) $notification_message.="Employee's company id ({$employee->company_id}) mismatches event's company id ({$company_id}).<br />\n";
				// Create notification if needed
				if(!empty($notification_message)) $notification=Notification::Create("{$date} - Invalid event data", $notification_message);
				// Set employee data from database
				$username=$employee->user_name;
				$department_id=$employee->department_id;
				$company_id=$employee->company_id;
			}

			// Move uploaded image file
			if(!empty($image)) {
				$image_save=MoveUploadedImage($id, $date, $image);
				if(!$image_save->status) DieJsonResponse(false, $image_save->message);
				$has_image=1;
			}

			// Move uploaded image_before file
			if(!empty($image_before)) {
				$image_save=MoveUploadedImage($id, $date, $image_before, "{$id}_0");
				if(!$image_save->status) DieJsonResponse(false, $image_save->message);
				$has_image=1;
			}

			// Move uploaded image_after file
			if(!empty($image_after)) {
				$image_save=MoveUploadedImage($id, $date, $image_after, "{$id}_1");
				if(!$image_save->status) DieJsonResponse(false, $image_save->message);
				$has_image=1;
			}

			// Check if event exists
			if(DB::Query("SELECT id FROM attendance_events WHERE id=" . DB::Quote($id) . " LIMIT 1;")) {
				// Update in database
				$sql="	UPDATE attendance_events SET
							date=" . DB::Quote($date) . ",
							type=" . DB::Quote($type) . ",
							user_name=" . (empty($username) ? "null" : DB::Quote($username)) . ",
							station=" . DB::Quote($station_id) . ",
							has_image=" . DB::Quote($has_image) . ",
							extra=" . (empty($extra) ? "null" : DB::Quote($extra)) . ",
							company_id=" . (empty($company_id) ? "null" : DB::Quote($company_id)) . ",
							department_id=" . (empty($department_id) ? "null" : DB::Quote($department_id)) . ",
							employee_id=" . (empty($employee_id) ? "null" : DB::Quote($employee_id)) . "
						WHERE id=" . DB::Quote($id) . ";
				";
				$insert=DB::Update($sql);
			} else {
				// Import in database
				$sql="INSERT INTO attendance_events (id, date, type, user_name, station, has_image, extra, company_id, department_id, employee_id) VALUES (
						 " . DB::Quote($id) . ",
						 " . DB::Quote($date) . ",
						 " . DB::Quote($type) . ",
						 " . (empty($username) ? "null" : DB::Quote($username)) . ",
						 " . DB::Quote($station_id) . ",
						 " . DB::Quote($has_image) . ",
						 " . (empty($extra) ? "null" : DB::Quote($extra)) . ",
						 " . (empty($company_id) ? "null" : DB::Quote($company_id)) . ",
						 " . (empty($department_id) ? "null" : DB::Quote($department_id)) . ",
						 " . (empty($employee_id) ? "null" : DB::Quote($employee_id)) . "
					);
				";
				$insert=DB::Insert($sql);

			}

			DieJsonResponse($insert, $insert ? 'OK' : 'Cannot insert event. ' . print_r(DB::GetLastError(), true));


		// REPORTS
		} else if($controller=='report' && $action=='xls') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			DieJsonResponse(true, 'OK', DownloadExcelTable(GetRequest('data'), GetRequest('filename')));
		} else if($controller=='report' && $action=='count_event_data') {
			// Check admin
			if(!Session::IsAdmin()) DieJsonResponse(false, Strings::Get("Insufficient rights"));
			// Get date from
			$date_from=GetRequest('date_from');
			if(empty($date_from)) DieJsonResponse(false, "Η ημερομηνία ΑΠΟ δε μπορεί να είναι κενή");
			// Get date to
			$date_to=GetRequest('date_to');
			if(empty($date_to)) DieJsonResponse(false, "Η ημερομηνία ΕΩΣ δε μπορεί να είναι κενή");
			$data=[ 'images_count' => 0, 'images_size' => 0, 'events_count' => 0 ];
			// Count images
			$glob=glob(EVENTS_IMAGES_PATH . '*');
			if($glob) foreach($glob as $dir) {
				if(!is_dir($dir)) continue;
				$tmp=explode(DIRECTORY_SEPARATOR, $dir);
				$dir_date=end($tmp);
				if($dir_date<$date_from || $dir_date>$date_to) continue;
				$g=glob($dir . DIRECTORY_SEPARATOR . '*');
				if($g) foreach($g as $file) {
					$data['images_count']++;
					$data['images_size']+=filesize($file);
				}
			}
			$data['images_size']=round($data['images_size'] / (1024 * 1024), 2) . ' MB';
			$message=($data['images_count']>0 ? ($data['images_count']>1 ? "Βρέθηκαν <b>{$data['images_count']}</b> αποθηκευμένες εικόνες συμβάντων συνολικού μεγέθους <b>{$data['images_size']}</b>." : "Βρέθηκε <b>1</b> αποθηκευμένη εικόνα συμβάντος μεγέθους <b>{$data['images_size']}</b>.") : "Δε βρέθηκαν αποθηκευμένες εικόνες συμβάντων για αυτό το διάστημα.");

			// Count events
			$rows=DB::Query("SELECT COUNT(id) AS total FROM attendance_events WHERE date>=" . DB::Quote($date_from) . " AND date<=" . DB::Quote($date_to) . ";");
			$data['events_count']=empty($rows) ? 0 : $rows[0]['total'];
			$message.=($message=='' ? '' : '<br />') . ($data['events_count']>0 ? ($data['events_count']>1 ? "Βρέθηκαν <b>{$data['events_count']}</b> εγγραφές στη βάση δεδομένων." : "Βρέθηκε <b>1</b> εγγραφή στη βάση δεδομένων.") : "Δε βρέθηκαν εγγραφές στη βάση δεδομένων.");

			DieJsonResponse(true, $message, $data);

		// MESSAGE
		} else if($controller=='message' && $action=='list') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			$rows=Message::GetList(isset($_REQUEST['type']) ? GetRequest('type') : -1);
			DieJsonResponse(true, 'Βρέθηκαν ' . count($rows) . ' εγγραφές', $rows);

		// MESSAGE
		} else if($controller=='message' && $action=='delete') {
			// Check login
			if(!Session::IsLoggedIn()) DieJsonResponse(false, "Πρέπει να συνδεθείτε για να συνεχίσετε");
			$id=GetRequest('id');
			$model=new Message($id);
			if(!$model->Load()) DieJsonResponse(false, "Το μήνυμα {$id} δε βρέθηκε");
			$delete=$model->Delete();
			if($delete && $delete['status']) DieJsonResponse(true, "Η εγγραφή διαγράφηκε");
			DieJsonResponse(false, "Σφάλμα κατά τη διαγραφή της εγγραφής με id {$id}. " . ($delete ? $delete['message'] : ''));

		// MODEL
		} else if(in_array($controller, APP_MODELS)) {
			$model=new $controller(GetRequest('id'));
			// LIST
			if($action=='list') {
				// Check login
				if(!Session::IsLoggedIn()) DieJsonResponse(false, Strings::Get('error_login_required'));
				$rows=DB::GetTable($model->table);
				DieJsonResponse(true, str_replace('#COUNT#', count($rows), Strings::Get('found_#COUNT#_records')), $rows);

			// DELETE
			} else if($action=='delete') {
				// Check table
				//if(in_array($controller, ['event'])) DieJsonResponse(false, "Δεν έχετε δικαιώματα για τη λειτουργία αυτή");
				// Check login
				if(!Session::IsLoggedIn()) DieJsonResponse(false, Strings::Get('error_login_required'));
				// Check permission
				if((Session::IsAdmin() && !in_array($controller, CONTROLLERS_ADMIN_CAN_DELETE)) || (!Session::IsAdmin() && !in_array($controller, CONTROLLERS_USER_CAN_DELETE))) DieJsonResponse(false, "Δεν έχετε δικαιώματα για τη λειτουργία αυτή");
				// Load from db
				if($model->Load()) {
					$delete=$model->Delete();
					if($delete && $delete['status']) DieJsonResponse(true, Strings::Get('record_deleted'));
					DieJsonResponse(false, Strings::Get('error_deleting_record_with_id') . ": {$id}" . ($delete ? $delete['message'] : ''));
				} else {
					DieJsonResponse(false, Strings::Get('record_with_id_not_found') . ": {$id}");
				}

			// EDIT
			} else if($action=='edit') {
				// Check permission
				if((Session::IsAdmin() && !in_array($controller, CONTROLLERS_ADMIN_CAN_EDIT)) || (!Session::IsAdmin() && !in_array($controller, CONTROLLERS_USER_CAN_EDIT))) DieJsonResponse(false, "Δεν έχετε δικαιώματα για τη λειτουργία αυτή");
				// Get from database
				$model->Load();
				// Update from request
				$model->CreateFromRequest();
				// Save
				$save=$model->Save();
				// Check result
				if($save['status']) {
					DieJsonResponse(true, Strings::Get('record_saved'));
				} else {
					DieJsonResponse(false, Strings::Get('error_saving_record') . "\n{$save['message']}");
				}

			// ADD
			} else if($action=='add') {
				// Check permission
				if((Session::IsAdmin() && !in_array($controller, CONTROLLERS_ADMIN_CAN_ADD)) || (!Session::IsAdmin() && !in_array($controller, CONTROLLERS_USER_CAN_ADD))) DieJsonResponse(false, "Δεν έχετε δικαιώματα για τη λειτουργία αυτή");
				// Get from database
				if($model->primary_key_value) $model->Load();
				// Update from request
				$model->CreateFromRequest();
				// Save
				$save=$model->Save();
				// Check result
				if($save['status']) {
					DieJsonResponse(true, Strings::Get('record_saved'));
				} else {
					DieJsonResponse(false, Strings::Get('error_saving_record') . "\n{$save['message']}");
				}

			// MASS DELETE
			} else if($action=='mass_delete') {
				$ids=GetRequest('ids');
				if(empty($ids)) DieJsonResponse(false, Strings::Get('no_records_selected_for_deletion'));
				// Check table
				if(in_array($controller, ['event'])) DieJsonResponse(false, Strings::Get('error_insufficient_rights'));
				// Check login
				if(!Session::IsLoggedIn()) DieJsonResponse(false, Strings::Get('error_login_required'));
				// Check permission
				if((Session::IsAdmin() && !in_array($controller, CONTROLLERS_ADMIN_CAN_DELETE)) || (!Session::IsAdmin() && !in_array($controller, CONTROLLERS_USER_CAN_DELETE))) DieJsonResponse(false, "Δεν έχετε δικαιώματα για τη λειτουργία αυτή");
				$ids=explode(',', $ids);
				$success='';
				$errors='';
				foreach($ids as $id) {
					// Initialize model
					$model=new $controller($id);
					// Get from database
					$model->Load();
					if(!$model) {
						$errors.=Strings::Get('record_with_id_not_found') . ": {$id}\n";
					} else {
						$resp=$model->Delete();
						if($resp['status'])
							$success.="{$resp['message']} {$id}\n";
						else
							$errors.="{$resp['message']} {$id}\n";
					}
				}
				DieJsonResponse(true, (empty($errors) ? '' : Strings::Get('errors') . ":\n{$errors}") . (empty($success) ? '' : Strings::Get('deletions') . ":\n{$success}"));
			}
		}

		// USER LOGIN
		if($controller=='user' && $action=='login') {
			$user_name=GetRequest('user_name');
			if(empty($user_name)) DieJsonResponse(false, Strings::Get('error_username_is_empty'));
			$password=GetRequest('password');
			if(empty($password)) DieJsonResponse(false, Strings::Get('error_password_is_empty'));
			$user=new User;
			$user->user_name=$user_name;
			$user->password=$password;
			$login=$user->Login();
			DieJsonResponse($login->status, $login->message, $login->data);

		// COMPANY
		} else if($controller=='company') {
			// LIST
			if($action=='list') {
				$rows=DB::GetTable('attendance_companies');
				DieJsonResponse(true, str_replace('#COUNT#', count($rows), Strings::Get('found_#COUNT#_records')), $rows);
			} else if($action=='delete') {
				// Load from db
				$record=new Company($id);
				if($record->Load()) {
					$delete=$record->Delete();
					if($delete && $delete['status']) DieJsonResponse(true, Strings::Get('record_deleted'));
					DieJsonResponse(false, Strings::Get('error_deleting_record_with_id') . ": {$id}" . ($delete ? $delete['message'] : ''));
				} else {
					DieJsonResponse(false, Strings::Get('record_with_id_not_found') . ": {$id}");
				}

			// EDIT
			} else if($action=='edit') {
				// Create model
				$model=new Company($id);
				// Get from database
				$model->Load();
				// Update from request
				$model->CreateFromRequest();
				// Save
				$save=$model->Save();
				// Check result
				if($save['status']) {
					DieJsonResponse(true, Strings::Get('record_saved'));
				} else {
					DieJsonResponse(false, Strings::Get('error_saving_record') . "\n{$save['message']}");
				}

			// MASS DELETE
			} else if($action=='mass_delete') {
				$ids=GetRequest('ids');
				if(empty($ids)) DieJsonResponse(false, Strings::Get('no_records_selected_for_deletion'));
				$ids=explode(',', $ids);
				$success='';
				$errors='';
				foreach($ids as $id) {
					// Initialize model
					$model=new Company($id);
					// Get from database
					$model->Load();
					if(!$model) {
						$errors.=Strings::Get('record_with_id_not_found') . ": {$id}\n";
					} else {
						$resp=$model->Delete();
						if($resp['status'])
							$success.="{$resp['message']} {$id}\n";
						else
							$errors.="{$resp['message']} {$id}\n";
					}
				}
				DieJsonResponse(true, (empty($errors) ? '' : Strings::Get('errors') . ":\n{$errors}") . (empty($success) ? '' : Strings::Get('deletions') . ":\n{$success}"));

			} else {
				DieJsonResponse(false, Strings::Get('error_action_not_found') . ": {$action}");
			}
		}
		DieJsonResponse(false, "Δε βρέθηκε ο ελεγκτής {$controller}");
	}

}
