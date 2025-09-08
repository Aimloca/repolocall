<?

	error_reporting(E_ALL); ini_set('display_errors', 'On');

	date_default_timezone_set('Europe/Athens');

	// Set time limit to 2 minutes
	set_time_limit(120); ini_set('max_execution_time', '120');

	// Load functions
	include_once 'functions.php';

	// Load configuration
	include_once 'config.php';

	// Load DB Model
	//include_once HELPERS_PATH . 'db.php';

	// Load autogen
	if(!file_exists(AUTOGEN_PATH . 'includes.php')) AutogenIncludes();
	include_once AUTOGEN_PATH . 'includes.php';
	if(!file_exists(AUTOGEN_PATH . 'db_tables.php')) AutogenDBTables();
	include_once AUTOGEN_PATH . 'db_tables.php';
	if(!file_exists(BASE_PATH . '/assets/js/autogen_strings.js')) AutogenJsStrings();
	if(!file_exists(BASE_PATH . '/assets/js/autogen_tables.js')) AutogenJsTables();
	if(!file_exists(BASE_PATH . '/assets/js/autogen_models.js')) AutogenJsModels();

	// Create static database reference
	new DB;

	// Register shutdown function to close DB connection
	register_shutdown_function('DisconnectFromDB');

	// Start session
	Session::Start();

	// Load strings
	Strings::LoadStrings();

	// Set language if needed
	if(GetRequest('lang')!='') Strings::SetLanguage(GetRequest('lang'));

	// Guide route
	if(!isset($dont_guide) || !$dont_guide) Route::Guide();
