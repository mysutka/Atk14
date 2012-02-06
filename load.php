<?php
/**
* There are a few components needs to be loaded.
* Atk14 connects these components to the right order.
*/
error_reporting(255);

// we need to load Atk14Utils first, then using it determine environment and then finally load the rest of ATK14...
// HTTP* classes give us right advices about environment & configuration
require_once(dirname(__FILE__)."/src/stringbuffer/stringbuffer.inc");
require_once(dirname(__FILE__)."/src/files/files.inc");
require_once(dirname(__FILE__)."/src/http/load.inc");
require_once(dirname(__FILE__)."/src/atk14/atk14_utils.inc");
Atk14Utils::DetermineEnvironment();

// now we are gonna to set up config constants
if(defined("ATK14_DOCUMENT_ROOT")){
	atk14_require_once(ATK14_DOCUMENT_ROOT."/config/local_settings.inc");
}else{
	atk14_require_once(dirname(__FILE__)."/../config/local_settings.inc");
}
require_once(dirname(__FILE__)."/default_settings.php");

// load the rest...
require_once(dirname(__FILE__)."/src/string/load.inc");
require_once(dirname(__FILE__)."/src/translate/translate.inc");
require_once(dirname(__FILE__)."/src/dictionary/dictionary.inc");
require_once(dirname(__FILE__)."/src/miniyaml/miniyaml.inc");
require_once(dirname(__FILE__)."/src/dates/load.inc");
require_once(dirname(__FILE__)."/src/xmole/xmole.php");
require_once(dirname(__FILE__)."/src/stopwatch/stopwatch.inc");
require_once(dirname(__FILE__)."/src/logger/logger.inc");
require_once(dirname(__FILE__)."/src/json/load.inc");
require_once(dirname(__FILE__)."/src/smarty/libs/Smarty.class.php");
require_once(dirname(__FILE__)."/src/class_autoload/class_autoload.inc");
require_once(dirname(__FILE__)."/src/dbmole/dbmole.inc");
require_once(dirname(__FILE__)."/src/dbmole/pgmole.inc");
require_once(dirname(__FILE__)."/src/tablerecord/load.php");
require_once(dirname(__FILE__)."/src/sessionstorer/sessionstorer.inc");
require_once(dirname(__FILE__)."/src/packer/packer.inc");
require_once(dirname(__FILE__)."/src/sendmail/sendmail.inc");
require_once(dirname(__FILE__)."/src/forms/load.inc");
require_once(dirname(__FILE__)."/src/atk14/load.inc");
require_once(dirname(__FILE__)."/src/functions.inc");

// ...and load basic application`s objects
atk14_require_once_if_exists(ATK14_DOCUMENT_ROOT."/app/forms/application_form.php");
atk14_require_once_if_exists(ATK14_DOCUMENT_ROOT."/app/forms/form.php");
atk14_require_once_if_exists(ATK14_DOCUMENT_ROOT."/config/routers/load.php");

// Loading model classes, field (and widget) classes and external (3rd party) libs.
// In every directory class_autoload() is applied. I believe it can do a lot.
// But everywhere the load file is optional.
foreach(array("app/models","app/fields","lib") as $_d_){
	class_autoload(ATK14_DOCUMENT_ROOT."/$_d_/");
	atk14_require_once_if_exists(ATK14_DOCUMENT_ROOT."/$_d_/load.php");
}

// global variable $dbmole holds database connection
// at the moment only postgresql is supported (why don't just support the best open source database worldwide?)
$dbmole = &PgMole::GetInstance();

function &dbmole_connection(&$dbmole){
	global $ATK14_GLOBAL;

	$out = null;

	$d = $ATK14_GLOBAL->getDatabaseConfig();

	// there is a configuration name in $dbmole->getConfigurationName()
	// it's useful when there is a need to connect to more databases

	switch($dbmole->getDatabaseType()){
		case "mysql":
			//TODO
			break;

		case "postgresql":
			$out = pg_connect("dbname=$d[database] host=$d[host] user=$d[username] password=$d[password]");
			break;

		case "oracle":
			// TODO
			break;
	}

	return $out;
}

function dbmole_error_handler($dbmole){
	if(PRODUCTION){
		$dbmole->sendErrorReportToEmail(ATK14_ADMIN_EMAIL);
		$dbmole->logErrorReport(); // zaloguje chybu do error logu

		$response = Atk14Dispatcher::ExecuteAction("application","error500",array(
			"render_layout" => false,
			"apply_render_component_hacks" => true,
		));
		$response->flushAll();
	}else{
		echo "<pre>";
		echo $dbmole->getErrorReport();
		echo "</pre>";
	}

	exit;
}
DbMole::RegisterErrorHandler("dbmole_error_handler");

function atk14_initialize_locale(&$lang){
	global $ATK14_GLOBAL;

	$locale = $ATK14_GLOBAL->getValue("locale");

	if(!isset($locale[$lang])){
		$_keys = array_keys($locale);
		$lang = $_keys[0];
	}

	$l = $locale[$lang]["LANG"];

	putenv("LANG=$l");
	setlocale(LC_MESSAGES,$l);
	setlocale(LC_ALL,$l);
	setlocale(LC_CTYPE,$l);
	setlocale(LC_COLLATE,$l);
	bindtextdomain("messages",dirname(__FILE__)."/../locale/");
	bind_textdomain_codeset("messages", "UTF-8");
	textdomain("messages");
}
