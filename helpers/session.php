<?
class Session {
	
	public static function Start() { session_save_path(SESSIONS_PATH); ini_set('session.gc_maxlifetime', 3600 * 6);  ini_set('session.cookie_lifetime', 3600 * 6); ini_set('session.cache_expire', 360); session_set_cookie_params(3600 * 6); if(session_status()===PHP_SESSION_NONE) session_start(); }	
	public static function Destroy() { if($_SESSION) unset($_SESSION); @session_destroy();	}
	
	public static function IsLoggedIn() { return !empty($_SESSION['admin']) || !empty($_SESSION['user']) || !empty($_SESSION['customer']); }	
	
	public static function Account() { return empty($_SESSION['admin']) ? (empty($_SESSION['user']) ? (empty($_SESSION['customer']) ? false : $_SESSION['customer']) : $_SESSION['user']) : $_SESSION['admin']; }
	public static function AccountId() { return Session::Account() ? Session::Account()->id : false; }
	
	public static function Admin() { return empty($_SESSION['admin']) ? false : $_SESSION['admin']; }
	public static function AdminId() { return empty($_SESSION['admin']) ? false : $_SESSION['admin']->id; }
	public static function IsAdmin() { return !empty($_SESSION['admin']); }
	
	public static function User() { return empty($_SESSION['user']) ? false : $_SESSION['user']; }	
	public static function UserId() { return empty($_SESSION['user']) ? false : $_SESSION['user']->id; }
	public static function IsUser() { return Session::User()!=false; }
	public static function IsShopManager() { return Session::IsUser() && $_SESSION['user']->position==0; }
	public static function IsBarista() { return Session::IsUser() && $_SESSION['user']->position==1; }
	public static function IsPreparation() { return Session::IsUser() && $_SESSION['user']->position==2; }
	public static function IsWaiter() { return Session::IsUser() && $_SESSION['user']->position==3; }

	public static function Customer() { return empty($_SESSION['customer']) ? false : $_SESSION['customer']; }	
	public static function CustomerId() { return empty($_SESSION['customer']) ? false : $_SESSION['customer']->id; }	
	public static function IsCustomer() { return Session::Customer()!=false; }
	
	public static function SelectedTable() { return empty($_SESSION['selected_table']) ? false : $_SESSION['selected_table']; }	
	public static function SelectedCompany() { return empty($_SESSION['selected_company']) ? false : $_SESSION['selected_company']; }	
	public static function SelectedCompanyId() { return empty($_SESSION['selected_company']) ? false : $_SESSION['selected_company']->id; }	
	public static function SelectedOrder() { return empty($_SESSION['selected_order']) ? false : $_SESSION['selected_order']; }		
	
	public static function Add($key, $data) { $_SESSION[$key]=$data; }
	public static function Set($key, $data) { Session::Add($key, $data); }
	public static function Get($key) { return isset($_SESSION[$key]) ? $_SESSION[$key] : ''; }
	public static function Remove($key) { if(isset($_SESSION[$key])) unset($_SESSION[$key]); }
	
}
