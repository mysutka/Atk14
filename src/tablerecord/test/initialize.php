<?php
// struktura testovaci databaze je k dispozici v souboru testing_structures.sql
require("../../dbmole/dbmole.inc");
require("../../dbmole/pgmole.inc");
require("../../string/load.inc");

require("../load.php");
require("./inobj_testtable.php");
require("./article.php");
require("./image.php");
require("./author.php");

function &dbmole_connection($dbmole){
	static $connection;
	//if($dbmole->getDatabaseType()=="postgresql" && $dbmole->getConfigurationName()=="default"){
		if(!isset($connection)){
			$connection = pg_connect("dbname=test user=test password=test");
		}
		return $connection;
	//}
}