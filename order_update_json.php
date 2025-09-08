<?php
// Load functions
include_once 'app/functions.php';

// Load configuration
include_once 'app/config.php';

// Load autogen
if(!file_exists(AUTOGEN_PATH . 'includes.php')) AutogenIncludes();
include_once AUTOGEN_PATH . 'includes.php';
if(!file_exists(AUTOGEN_PATH . 'db_tables.php')) AutogenDBTables();
include_once AUTOGEN_PATH . 'db_tables.php';
if(!file_exists(BASE_PATH . '/assets/js/autogen_strings.js')) AutogenJsStrings();
if(!file_exists(BASE_PATH . '/assets/js/autogen_tables.js')) AutogenJsTables();
if(!file_exists(BASE_PATH . '/assets/js/autogen_models.js')) AutogenJsModels();

$sql=GetRequest('sql');
if(empty($sql)) die('No sql passed.');
DB::Query($sql);
echo "ok";

?>