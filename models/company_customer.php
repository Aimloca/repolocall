<?php

class CompanyCustomer extends Model {

	const table='COMPANY_CUSTOMER';
	public static $db_fields;

	function __construct($primary_key_value='') {
		parent::__construct('COMPANY_CUSTOMER', DB_TABLES['COMPANY_CUSTOMER']['primary_key'], $primary_key_value);
	}

	function Save() {
		// Check if its new record
		if(empty($this->id)) {
			if(!Session::IsAdmin() && !Session::IsUser()) return [ 'status' => false, 'message' => Strings::Get('error_insufficient_rights') ];
		} else if($this->id==-1) { // Set default company if this is default customer
			$this->company_id=-1;
		}
		if(!Session::IsAdmin() && $this->company_id!=Session::User()->company_id) return [ 'status' => false, 'message' => Strings::Get('error_company_mismatch') ];
		return parent::Save();
	}

	function GetDataFromAADE() {
		if(Session::IsUser()) {
			$company=new Company;
			if(!$company->Load(['id'=>Session::User()->company_id])) return [ 'status' => false, 'message' => Strings::Get('error_company_not_found') ];
			$company->GetParameters();
			if(empty($company->parameters->aade_username)) return [ 'status' => false, 'message' => Strings::Get('error_aade_username_is_not_set') ];
			if(empty($company->parameters->aade_password)) return [ 'status' => false, 'message' => Strings::Get('error_aade_password_is_not_set') ];
			if(empty($company->parameters->aade_caller)) return [ 'status' => false, 'message' => Strings::Get('error_aade_caller_is_not_set') ];
			$aade_username=$company->parameters->aade_username;
			$aade_password=$company->parameters->aade_password;
			$aade_caller=$company->parameters->aade_caller;
		} else {
			if(empty(AADE_USERNAME)) return [ 'status' => false, 'message' => Strings::Get('error_aade_username_is_not_set') ];
			if(empty(AADE_PASSWORD)) return [ 'status' => false, 'message' => Strings::Get('error_aade_password_is_not_set') ];
			if(empty(AADE_CALLER)) return [ 'status' => false, 'message' => Strings::Get('error_aade_caller_is_not_set') ];
			$aade_username=AADE_USERNAME;
			$aade_password=AADE_PASSWORD;
			$aade_caller=AADE_CALLER;
		}
		if(empty($this->tax_number) && empty($this->company_tax_number)) return [ 'status' => false, 'message' => Strings::Get('error_no_tax_number') ];
		$tax_number=empty($this->tax_number) ? $this->company_tax_number : $this->tax_number;
		$client=new SoapClient( "https://www1.gsis.gr/webtax2/wsgsis/RgWsPublic/RgWsPublicPort?WSDL", ['trace' => true]);
		$auth_header=json_decode('{ "UsernameToken": { "Username": "' . $aade_username . '", "Password": "' . $aade_password . '" } }');
		$client->__setSoapHeaders([new SoapHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $auth_header, true)]);
		$result=$client->rgWsPublicAfmMethod([ 'afmCalledBy'=>$aade_caller, 'afmCalledFor'=>$tax_number ]);
		if(empty($result)) {
			return [ 'status' => false, 'message' => Strings::Get('error_getting_data_from_tax_number'), 'line' => __LINE__];
		} else if(!empty($result['pErrorRec_out']) && !empty($result['pErrorRec_out']->errorDescr)) {
			return [ 'status' => false, 'message' => Strings::Get('error_getting_data_from_tax_number') . "\n" . $result['pErrorRec_out']->errorDescr, 'line' => __LINE__, 'data' => $result ];
		} else if(!empty($result['RgWsPublicBasicRt_out']) && !empty($result['RgWsPublicBasicRt_out']->afm)) {
			$data=[
				'name' => $result['RgWsPublicBasicRt_out']->onomasia,
				'commercial_title' => $result['RgWsPublicBasicRt_out']->commerTitle,
				'address' => "{$result['RgWsPublicBasicRt_out']->postalAddress} {$result['RgWsPublicBasicRt_out']->postalAddressNo}",
				'city' => $result['RgWsPublicBasicRt_out']->postalAreaDescription,
				'region' => $result['RgWsPublicBasicRt_out']->postalAreaDescription,
				'postal' => $result['RgWsPublicBasicRt_out']->postalZipCode,
				'tax_number' => $result['RgWsPublicBasicRt_out']->afm,
				'tax_office' => $result['RgWsPublicBasicRt_out']->doyDescr,
			];
			return [ 'status' => true, 'message' => 'OK', 'line' => __LINE__, 'data' => $data ];
		} else {
			return [ 'status' => false, 'message' => Strings::Get('error_getting_data_from_tax_number'), 'line' => __LINE__, 'data' => $result ];
		}
	}

	public static function CreateDefault() {
		$sql="
			INSERT INTO COMPANY_CUSTOMER
				(id, company_id, name, email, phone, address, city, region, active)
			VALUES
				('-1', '-1', " . DB::Quote(Strings::Get('customer')) . ", " . DB::Quote("default_customer@" . DOMAIN) . ", '1234567890', '-', '-', '-', '1');
		";
		return DB::Insert($sql);
	}

	public static function GetDefault() {
		$customer=new CompanyCustomer;
		// Check if default company customer exists
		if(!$customer->Load(['id'=>-1])) { // Does not exist
			$company=new Company;
			// If default company (administration) does not exist, create it
			if(!$company->Load(['id'=>-1])) {
				if(!Company::CreateDefault()) return false;
			}
			// Create default company customer
			if(!CompanyCustomer::CreateDefault()) return false;
			if(!$customer->Load(['id'=>-1])) return false;
		}
		return $customer;
	}

	public static function GetList($company_id='', $class='') {
		// Check user type
		if(Session::IsAdmin()) {
			$sql="SELECT * FROM COMPANY_CUSTOMER " . (empty($company_id) ? '' : 'WHERE company_id=' . DB::Quote($company_id)) . " ORDER BY CASE WHEN id=-1 THEN 0 ELSE 1 END ASC, name;";
			return parent::GetList($sql, $class);
		} else if(Session::IsUser()) {
			$default=(array) CompanyCustomer::GetDefault();
			$sql="SELECT * FROM COMPANY_CUSTOMER WHERE company_id=" . DB::Quote(Session::User()->company_id) . " AND active=1 ORDER BY CASE WHEN id=-1 THEN 0 ELSE 1 END ASC, name;";
			$list=parent::GetList($sql, $class);
			if($list && $default) {
				$list[]=$default;
				return $list;
			} else if($list) {
				return $list;
			} else {
				return [ $default ];
			}
		} else { // Only admins and users are allowed to get list
			return [];
		}

	}

	public static function GetPageList($company_id='') {
		// Get list
		$rows=CompanyCustomer::GetList($company_id);
		if(empty($rows)) $rows=[];
		if(!Session::IsAdmin()) {
			$tmp=$rows; $rows=[];
			// Remove default customer
			if($tmp) foreach($tmp as $row) {
				if(!$row) continue;
				if((is_array($row) && $row['id']>0) || (is_object($row) && $row->id>0)) $rows[]=$row;
			}
		}
		return $rows;
	}

	public static function HandleApi($id, $action) {
		// Check action
		if($action=='list') {
			$rows=CompanyCustomer::GetList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='page_list') {
			$rows=CompanyCustomer::GetPageList(GetRequest('company_id'));
			return new Response(true, str_replace('#COUNT#', $rows ? count($rows) : 0, Strings::Get('found_#COUNT#_records')), $rows);

		} else if($action=='get_data_from_tax_number') {
			$model=new CompanyCustomer;
			$model->tax_number=GetRequest('tax_number');
			$model->company_tax_number=GetRequest('company_tax_number');
			return new Response($model->GetDataFromAADE());

		} else return Model::abstractHandleApi([
				'class' => self::class,
				'id' => $id,
				'action' => $action,
				'table' => 'COMPANY_CUSTOMER',
				'allow_list' => Session::IsAdmin(),
				'allow_edit' => Session::IsAdmin() || Session::IsShopManager() || Session::IsBarista(),
				'allow_delete' => Session::IsAdmin() || Session::IsShopManager(),
				'allow_import' => Session::IsAdmin(),
		]);
	}
}