<?php
/**
 * Provides basic functionality with databases.
 *
 * @package Atk14
 * @subpackage Database
 * @abstract
 * @filesource
 */

!defined("DBMOLE_ORACLE_TRUE") && define("DBMOLE_ORACLE_TRUE","Y");
!defined("DBMOLE_ORACLE_FALSE") && define("DBMOLE_ORACLE_FALSE","N");
!defined("DBMOLE_CHECK_BIND_AR_FORMAT") && define("DBMOLE_CHECK_BIND_AR_FORMAT",true);

/**
 * Provides basic functionality with databases.
 *
 * This class provides methods that are independent on the database type.
 * Database dependent methods are overridden in descendants
 *
 * Getting Postgres database DbMole instance
 * <code>
 * $dbmole = &PgMole::GetInstance();
 * </code>
 *
 * Basic query execution
 * <code>
 * $query = "SELECT id FROM customers WHERE UPPER(name) = UPPER(:customer_name)";
 * $bind_ary = array(
 * 	":customer_name" => "john",
 * );
 * $customer_ids = $dbmole->selectIntoArray($query, $bind_ary);
 * </code>
 *
 * Error handling
 * <code>
 *	DbMole::RegisterErrorHandler("dbmole_error_handler");
 *	function dbmole_error_handler($dbmole){
 *		echo "Dear visitor, unfortunately an error has occurred";
 *		$dbmole->sendErrorReportToEmail("admin@test.cz");
 *		$dbmole->logErrorReport();
 *		exit(1);
 *	}
 * </code>
 *
 * Statistics:
 * <code>
 *	define("DBMOLE_COLLECT_STATICTICS",true);
 *	$dbmole = &OracleMole::GetInstance();
 *	echo $dbmole->getStatistics();
 * </code>
 *
 * @package Atk14
 * @subpackage Database
 * @abstract
 * @filesource
 */
class DbMole{
	/**
	 * Name of database configuration
	 *
	 * @access private
	 */
	var $_ConfigurationName = "";

	/**
	 * Connection resource
	 *
	 * @var resource
	 * @access private
	 */
	var $_DbConnect = null;


	/**
	 * Error message returned by a database operation
	 *
	 * @access private
	 */
	var $_ErrorMessage = null;


	/**
	 * Just a flag that the error handler has been called.
	 *
	 * @var boolean
	 * @access private
	 */
	var $_ErrorRaised = false;

	/**
	 * Name of error handling function.
	 *
	 * @var string
	 * @access private
	 */
	var $_ErrorHandler = "";


	/**
	 * Last executed query.
	 *
	 * @var string
	 * @access private
	 */
	var $_Query = "";

	/**
	 * Parameters of last executed query.
	 *
	 * @var array
	 * @access private
	 */
	var $_BindAr = array();

	/**
	 * Parameters used by query execution.
	 *
	 * They are cleared before each query.
	 *
	 * @var array
	 * @access private
	 */
	var $_Options = array();

	/**
	 * Number of executed queries since connection to the database.
	 *
	 * @var integer
	 * @access private
	 */
	var $_QueriesExecuted = 0;


	/**
	 * Path for caching queries.
	 *
	 * @var string
	 * @access private
	 */
	var $_CacheDir = null;

	/**
	 * 
	 */
	var $_BeginTransactionDelayed = false;

	protected function __construct($configuration_name){
		$this->_ConfigurationName = $configuration_name;

	}

	/**
	 * Returns an instance of DB connector.
	 *
	 * Returns an instance of DB connector for given configuration. The object is always the same for given configuration.
	 *
	 * Basic call:
	 * <code>
	 * $dbmole = &DbMole::GetInstance("default","OracleMole");
	 * </code>
	 *
	 * This call using a subclass is better:
	 * <code>
	 * $dbmole = &OracleMole::GetInstance("default");
	 * </code>
	 *
	 * @param string $configuration_name
	 * @param string $class_name
	 * @return DbMole
	 */
	static function &GetInstance($configuration_name = "default",$class_name = null){
		static $instance_store_ar;

		if(!$class_name){
			$class_name = get_called_class();
		}

		settype($configuration_name,"string");
		settype($class_name,"string");

		$out = new $class_name($configuration_name);
		$db_type = $out->getDatabaseType();

		if(!isset($instance_store_ar)){ $instance_store_ar = array(); }
		if(!isset($instance_store_ar[$db_type])){ $instance_store_ar[$db_type] = array(); }
		
		if(!isset($instance_store_ar[$db_type][$configuration_name])){
			$instance_store_ar[$db_type][$configuration_name] = &$out;
		}
		
		return $instance_store_ar[$db_type][$configuration_name];
	}

	/**
	 * Returns type of database connector.
	 *
	 * Type of databse is determined by DbMoles subclass.
	 *
	 * @return string
	 */
	function getDatabaseType(){
		if(preg_match("/^(.+)mole$/",strtolower(get_class($this)),$matches) && $matches[1]!="db"){
			return $matches[1];
		}
		return "unknown";
	}

	/**
	 * Returns the database name.
	 *
	 * If the subclass doesn't know how to determine the database name,
	 * the configuration name will be returned instead.
	 *
	 * @return string
	 */
	function getDatabaseName(){
		if($dbname = $this->_getDatabaseName()){ return (string)$dbname; }
		return $this->_ConfigurationName;
	}

	/**
	 * Needs to be covered by a descendant.
	 *
	 * @access private
	 */
	function _getDatabaseName(){ }

	/**
	 * Registers a global function that will be called whenever execution of SQL command fails within any $dbmole.
	 * Instance of a DbMole will be passed to the function as parameter.
	 *
	 * Returns name of error handler registered with the last call of this method.
	 *
	 * Registration of an error handler
	 * <code>
	 * DbMole::RegisterErrorHandler("dbmole_error_handler");
	 * </code>
	 *
	 * Common handler example
	 * <code>
	 * function dbmole_error_handler($dbmole){
	 *   echo "Dear visitor, unfortunately an internal error occured";
	 *	 $dbmole->sendErrorReportToEmail("admin@test.cz");
	 *	 $dbmole->logErrorReport();
	 *	 exit(1);
	 * }	
	 * </code>
	 *
	 * You can also specify an error handler to a certain $dbmole:
	 * <code>
	 *	$dbmole->setErrorHandler($function_name);
	 * </code>
	 *
	 * @param string $function_name
	 * @return string name of previously registered error handler
	 */
	static function RegisterErrorHandler($function_name){
		return DbMole::_GetSetErrorHandlerFunction($function_name,true);
	}

	/**
	 * Registers an error handler function to a given DbMole instance.
	 *
	 * <code>
	 *	$dbmole = PgMole::GetInstance();
	 *	$dbmole_session = PgMole::GetInstance("session");
	 *	$dbmole_archive = PgMole::GetInstance("archive");
	 *
	 * 	DbMole::RegisterErrorHandler("default_error_handler");
	 * 	$dbmole_session->setErrorHandler("session_error_handler");
	 * </code>
	 *
	 * @param string $function_name
	 */ 
	function setErrorHandler($function_name){
		$this->_ErrorHandler = $function_name;
	}

	/**
	 * Returns the name of error handling function.
	 *
	 * @return string name of function that handles database errors
	 */
	function getErrorHandler(){
		if($this->_ErrorHandler){ return $this->_ErrorHandler; }
		return DbMole::_GetSetErrorHandlerFunction();
	}

	/**
	 * Returns database usage statistics.
	 *
	 * @return string
	 */
	function getStatistics(){
		global $__DMOLE_STATISTICS__;

		if(!isset($__DMOLE_STATISTICS__)){ $__DMOLE_STATISTICS__ = array(); }

		$ar = array();

		$total_queries = 0;
		$total_time = 0.0;

		$counter = 1;
		foreach($__DMOLE_STATISTICS__ as $q => $itms){	
			$total_queries += sizeof($itms);
			$current_query_time = 0.0;
			foreach($itms as $itm){	
				$total_time += $itm["time"];
				$current_query_time += $itm["time"];
			}
			$ar[$this->_formatSeconds($current_query_time).$counter] = array(
				"count" => sizeof($itms),
				"query" => $q,
				"time" => $current_query_time
			);
			$counter++;
		}

		krsort($ar,SORT_NUMERIC);

		$out = array();
		$out[] = "<div style=\"text-align: left;\">";
		$out[] = "<h3>total queries: $total_queries</h3>";
		$out[] = "<h3>total time: ".$this->_formatSeconds($total_time)."s</h3>";
		foreach($ar as $item){
			$percent = number_format((($item["time"]/$total_time)*100),1,".","");
			$time_per_single_query = $this->_formatSeconds($item["time"]/$item["count"])."s";
			$out[] = "<h3>$item[count]&times; ($percent%, $item[count]&times;$time_per_single_query=".$this->_formatSeconds($item["time"])."s)</h3>";
			$out[] = "<pre>";
			$out[] = h(str_replace("\t","  ",$item["query"]));
			$out[] = "</pre>";
		}
		$out[] = "</div>";

		return join("\n",$out);
	}

	/**
	 * Is this DbMole connected to it's database?
	 *
	 * echo "Connection to the database " . ($dbmole->isConnected() ? "has been established" : "has not yet been established");
	 */
	function isConnected(){
		return isset($this->_DbConnect);
	}

	/**
	 * @ignore
	 */
	function _formatSeconds($sec){
		return number_format($sec,3,".","");
	}

	/**
	 *
	 * @ignore
	 * @param string $function_name
	 * @param bool $set									true -> ulezeni nazvu fce
	 * @return string										aktualni jmeno (nebo predchozi pri nastavavovani) error handler funkce 
	 *																		pokud je vracen prazdny string "", nema se nic volat
	 */
	static function _GetSetErrorHandlerFunction($function_name = "",$set = false){
		static $_FUNCTION_NAME_;

		settype($set,"bool");
		settype($function_name,"string");

		$prev_function_name = "";
		if(isset($_FUNCTION_NAME_)){
			$prev_function_name = $_FUNCTION_NAME_;
		}
		
		if($set){
			$_FUNCTION_NAME_ = $function_name;
		}

		return $prev_function_name;
	}

	/**
	 * Connects the database.
	 *
	 * This method is abstract and must be overridden.
	 *
	 * @access private
	 * @abstract
	 * @return bool true -> uspesne napojeno, false -> doslo k chybe
	 */
	function _connectToDatabase(){
		if(isset($this->_DbConnect)){ return true; }

		$this->_DbConnect = &dbmole_connection($this);
		if(!isset($this->_DbConnect)){
			$this->_raiseDBError(sprintf("can't connect to %s database with configuration '%s'",$this->getDatabaseType(),$this->getConfigurationName()));
			return false;
		}

		if($this->_BeginTransactionDelayed){
			$this->_BeginTransactionDelayed = false;

			// TODO: I don't mean it seriously
			$em = $this->_ErrorMessage;
			$q = $this->_Query;
			$b = $this->_BindAr;
			$o = $this->_Options;

			$this->_begin();

			$this->_ErrorMessage = $em;
			$this->_Query = $q;
			$this->_BindAr = $b;
			$this->_Options = $o;
		}

		return true;
	}

	/**
	 * $connection = $this->_getDbConnect();
	 */
	protected function &_getDbConnect(){
		if(!$this->isConnected()){
			$this->_connectToDatabase();
		}
		return $this->_DbConnect;
	}

	/**
	 * @ignore
	 * @access private
	 */
	function _selectRows($query,&$bind_ar, $options = array()){
		$options = array_merge(array(
			"cache" => 0,
		),$options);
		$options["avoid_recursion"] = true; // protoze primo metoda selectRows() vola _selectRows() a naopak, mame tady tento ochranny parametr
		$cache = (int)$options["cache"];

		$this->_normalizeBindAr($bind_ar);

		if($cache>0){
			$rows = $this->_readCache($cache,$query,$bind_ar,$options);
			if(is_array($rows)){
				return $rows;
			}
		}

		$rows = $this->selectRows($query,$bind_ar,$options);

		if($cache>0){
			$this->_writeCache($rows,$query,$bind_ar,$options);
		}
			
		return $rows;
	}

	/**
	 * Checks if an error occured on last query.
	 *
	 * Some methods return values say that error occured (commit(), rollback(), selectRows(), ...).
	 * From return values of other methods it is impossible to recognize an error (selectSingleValue(), selectFirstRow(), ...).
	 *
	 * When you register an error handler that interrupts the script (with exit) then it is not needed to check for an error.
	 *
	 * @return bool true -> error occured, false -> no error
	 */
	function errorOccurred(){ return isset($this->_ErrorMessage); }

	/**
	 * Gettery vhodne pro error_handler funkci.
	 */

	/**
	 * Returns error message for last database operation.
	 *
	 * @return string
	 */
	function getErrorMessage(){ return $this->_ErrorMessage; }

	/**
	 * Returns last performed sql query.
	 *
	 * @return string
	 */
	function getQuery(){ return $this->_Query; }

	/**
	 * Return bind parameters used on last sql query.
	 *
	 * @return array
	 */
	function getBindAr(){ return $this->_BindAr; }

	/**
	 * Returns options used by executed query.
	 *
	 * @return array
	 */
	function getOptions(){ return $this->_Options; }

	/**
	 * Get number of executed queries since connection to the database.
	 *
	 * @return integer
	 */
	function getQueriesExecuted(){ return $this->_QueriesExecuted; }

	/**
	 * Returns name of configuration.
	 *
	 * It is 'default' by default.
	 *
	 * @return string
	 */
	function getConfigurationName(){ return $this->_ConfigurationName; }

	/**
	 * Gets error report.
	 *
	 * Error report contains important information about executed query and environment settings.
	 *
	 * @return string
	 */
	function getErrorReport(){
		$out = array();

		$out[] = "DbMole error report";
		$out[] = "";
		$out[] = "error message";
		$out[] = "-------------";
		$out[] = $this->getErrorMessage();
		$out[] = "";
		$out[] = "query";
		$out[] = "-----";
		$out[] = $this->getQuery();
		$out[] = "bind_ar";
		$out[] = "-------";
		$out[] = print_r($this->getBindAr(),true);
		if(isset($GLOBALS["_SERVER"]));{
			$out[] = "";
			$out[] = "server vars";
			$out[] = "-----------";
			$out[] = print_r($GLOBALS["_SERVER"],true);
		}
		if(isset($GLOBALS["_GET"]));{
			$out[] = "";
			$out[] = "get vars";
			$out[] = "--------";
			$out[] = print_r($GLOBALS["_GET"],true);
		}
		if(isset($GLOBALS["_POST"]));{
			$out[] = "";
			$out[] = "post vars";
			$out[] = "--------";
			$out[] = print_r($GLOBALS["_POST"],true);
		}
		return join("\n",$out);
	}

	/**
	 * Gets error report and sends it to email address.
	 *
	 * @param string $email_address
	 * @param array $options
	 * - report_failed_database_connection boolean whether send report about failed database connection
	 */
	function sendErrorReportToEmail($email_address,$options = array()){
		$options["report_failed_database_connection"] = isset($options["report_failed_database_connection"]) ? (bool)$options["report_failed_database_connection"] : false;

		if(!$options["report_failed_database_connection"] && preg_match("/^can't connect to database/",$this->getErrorMessage())){
			return;
		}

		sendmail(array(
			"to" => $email_address,
			"subject" => "DbMole: error report",
			"body" => $this->getErrorReport(),
			"mime_type" => "text/plain",
		));
	}

	/**
	 * Gets error report and logs it into php error log.
	 */
	function logErrorReport(){
		error_log($this->getErrorReport());
	}

	/**
	 * Resets some states of objects.
	 *
	 * Is called before execution of a SQL command.
	 *
	 * @access private
	 * @ignore
	 */
	function _reset(){
		$this->_ErrorMessage = null;
		$this->_Query = "";
		$this->_BindAr = array();
		$this->_Options = array();
	}

	/**
	 * This method is called in case of error.
	 *
	 * @ignore
	 * @access private
	 * @param string $message_prefix				ie. "OCIParse failed"
	 *
	 */
	function _raiseDBError($message){
    $this->_ErrorMessage = "$message";

		if(strlen($db_error = $this->_getDbLastErrorMessage())>0){
			$this->_ErrorMessage .= " ".$db_error;
		}

		if($this->_ErrorRaised){
			return null;
		}
		$this->_ErrorRaised = true;

		$error_handler = $this->getErrorHandler();
		if(strlen($error_handler)>0){
			$error_handler($this);
		}else{
			$this->logErrorReport();
			exit(1);
		}

		return null;
	}

	/**
	 * Executes SQL query.
	 *
	 * @see executeQuery()
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return bool			true -> query executed with success
	 *									false -> error
	 */
	function doQuery($query,$bind_ar = array(), $options = array()){
		$result = $this->executeQuery($query,$bind_ar,$options);
		if(!$result){ return false; }
		$this->_freeResult($result);
		return true;
	}

	/**
	 * Returns the number of rows affected during the last sql execution.
	 * 
	 * <code>
	 *	$dbmole->doQuery("UPDATE articles SET author_id=22 WHERE author_id=11");
	 *	echo $dbmole->getAffectedRows(); // amount of records updated
	 * </code>
	 * @return integer
	 */
	function getAffectedRows(){
		return $this->_getAffectedRows();
	}

	/**
	 * @ignore
	 * @abstract
	 */
	function _getAffectedRows(){ return null; }

	/**
	 * Returns first record as associative array.
	 *
	 * Returns null when result doesn't contain any record or an error occurs.
	 *
	 * <code>
	 *	$row = $dbmole->selectFirstRow("SELECT * FROM articles WHERE id=:id",array(":id" => $id));
	 *	$row = $dbmole->selectFirstRow("SELECT * FROM articles",array(),array("order" => "create_date DESC", "limit" => 1));
	 * </code>
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return array associative array
	 */
	function selectFirstRow($query,$bind_ar = array(), $options = array()){
		$records = $this->_selectRows($query,$bind_ar,$options);
		if(!isset($records) || sizeof($records)==0){
			return null;
		}
		return $records[0];
	}

	/**
	 * Alias for selectFirstRow().
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return array
	 * @see selectFirstRow
	 */
	function selectRow($query,$bind_ar = array(),$options = array()){
		return $this->selectFirstRow($query,$bind_ar,$options);
	}
	
	/**
	 * Returns first value from the first record.
	 *
	 * Useful method for queries that count something like this:
	 * 'SELECT COUNT(*) AS count FROM articles WHERE source_date>SYSDATE'
	 *
	 * When the value is NULL this method returns null
	 *
	 *
	 * Options can specify type of returned value. Just use 'type' => "integer" option.
	 *
	 * As this is used a lot, this option can be passed directly without the 'type' keyword.
	 * It is internally converted to array.
	 *
	 *
	 * Basic usage
	 * <code>
	 * $mole->selectSingleValue("SELECT COUNT(*) FROM articles WHERE id<:id",array(":id" => 3000),array("type" => "integer"));	// takto to bylo vsechno zamysleno
	 * </code>
	 *
	 * can be shortened:
	 * <code>
	 * $mole->selectSingleValue("SELECT COUNT(*) FROM articles WHERE id<:id",array(":id" => 3000),"integer");
	 * </code>
	 *
	 * and can be even more shortened when no bind_ar is passed
	 * <code>
	 * $mole->selectSingleValue("SELECT COUNT(*) FROM articles WHERE id<3000","integer");
	 * </code>
	 *
	 * @param string $query
	 * @param array|string $bind_ar	when string will be used as if given $options["type"]
	 * @param array|string $options	when string it will be used as if given $options["type"]		
	 * @return mixed						
	 */
	function selectSingleValue($query,$bind_ar = array(), $options = array()){
		if(is_string($bind_ar)){
			$options = array("type" => $bind_ar);
			$bind_ar = array();
		}
		if(is_string($options)){
			$options = array("type" => $options);
		}
		$ar = $this->selectFirstRow($query,$bind_ar,$options);

		if(!isset($ar) || sizeof($ar)==0){ return null; }

		$out = null;

		foreach($ar as $_v){	
			$out = $_v;
			break;
		}
		if(isset($out) && isset($options["type"])){
			settype($out,"$options[type]");
		}

		return $out;
	}

	/**
	 * Alias to method selectSingleValue().
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return mixed
	 */
	function selectValue($query,$bind_ar = array(), $options = array()){
		return $this->selectSingleValue($query,$bind_ar,$options);
	}

	/**
	 * Shortcut to getting an integer.
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return integer
	 * @see selectSingleValue
	 */
	function selectInt($query,$bind_ar = array(),$options = array()){
		$options["type"] = "integer";
		return $this->selectSingleValue($query,$bind_ar,$options);
	}

	/**
	 * Shortcut to getting an string.
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return string
	 * @see selectSingleValue
	 */
	function selectString($query,$bind_ar = array(),$options = array()){
		$options["type"] = "string";
		return $this->selectSingleValue($query,$bind_ar,$options);
	}

	/**
	 * Shortcut to getting an float.
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return float
	 * @see selectSingleValue
	 */
	function selectFloat($query,$bind_ar = array(),$options = array()){
		$options["type"] = "float";
		return $this->selectSingleValue($query,$bind_ar,$options);
	}

	/**
	 * Shortcut to getting a boolean.
	 *
	 * Values considered as the true are: 'y', 'yes', 't', 'true', '1'...
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return boolean
	 * @see selectSingleValue
	 */
	function selectBool($query,$bind_ar = array(),$options = array()){
		$value = $this->selectString($query,$bind_ar,$options);
		if(!isset($value)){ return null; }
		return
			in_array(strtoupper($value),array("Y","YES","YUP","T","TRUE","1","ON","E","ENABLE","ENABLED")) ||
			(is_numeric($value) && $value>0);
	}

	/**
	 * Executes a SQL query and packs all values from all records to an array.
	 *
	 * Setting option type to 'integer' causes that all returned values are converted to integers.
	 *
	 * <code>
	 * $article_ids = $dbmole->selectIntoArray("SELECT id FROM articles WHERE source_id=100010");
	 * </code>
	 * returned array $article_ids can be array("233221","233222","233225"...)
	 *
	 * <code>
	 * $arr = $dbmole->selectIntoArray("SELECT id,name FROM articles WHERE ...");
	 * </code>
	 * returns array $ar like this
	 * array("233221","nazev prvniho clanku","233222","nazev druheho clanku"...)
	 *
	 * @param string $query
	 * @param array $bind_ar				muze byt string (prevedeno bude na $options["type"])
	 * @param array $options				muze byt string (prevedeno bude na $options["type"])
	 * @return array 
	 */
	function selectIntoArray($query,$bind_ar = array(),$options = array()){
		if(is_string($bind_ar)){
			$options = array("type" => $bind_ar);
			$bind_ar = array();
		}
		if(is_string($options)){
			$options = array("type" => $options);
		}

		$out = array();

		$rows = $this->_selectRows($query,$bind_ar,$options);
		if(!is_array($rows)){ return null; }
		foreach($rows as $row){	
			foreach($row as $value){	
				if(isset($value) && isset($options["type"])){
					settype($value,$options["type"]);
				}
				$out[] = $value;
			}
		}

		reset($out);
		return $out;
	}

	/**
	 * Returns records as associative arrays with the first attributes value as key.
	 *
	 * sql specifies 2 fields:
	 * <code>
	 * $articles = $dbmole->selectIntoAssociativeArray("SELECT id,name FROM articles WHERE source_id=100010");
	 * </code>
	 * can return for example
	 *	array(
	 *		"12" => "Nazev 1",
	 *		"3342" => "Nazev 2",
	 *		"2311" => "Nazev 3",
	 *		...
	 *	)
	 *
	 * sql specifies more fields and this call
	 * <code>
	 * $articles = $dbmole->selectIntoAssociativeArray("SELECT id,name,author FROM articles WHERE source_id=100010");
	 * </code>
	 * can return this
	 *	array(
	 *		"12" => array("name" => "Nazev 1", "author" => "Jan Tuna"),
	 *		"3342" => array("name" => "Nazev 2", "author" => "Dr. Kanal"),
	 *		...
	 *	)
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return array
	 */
	function selectIntoAssociativeArray($query,$bind_ar = array(), $options = array()){
		$out = array();
		$rows = $this->selectRows($query,$bind_ar,$options);
		foreach($rows as $row){
			$keys = array_keys($row);
			if(sizeof($keys)==2){
				$out[$row[$keys[0]]] = $row[$keys[1]];
			}else{
				$k = $row[$keys[0]];
				unset($row[$keys[0]]);
				$out[$k] = $row; 
			}
		}
		return $out;
	}

	/**
	 * Starts a database transaction.
	 *
	 * $dbmole->begin(array("execute_after_connecting" => true)); // no connection is being made right now
	 *
	 * @return bool
	 */
	final function begin($options = array()){
		$options += array(
			"execute_after_connecting" => false,
		);
		if($options["execute_after_connecting"] && !$this->isConnected()){
			$this->_BeginTransactionDelayed = true;
			return true;
		}
		return $this->_begin();
	}

	function _begin(){
		return $this->doQuery("BEGIN");
	}

	/**
	 * Ends a database transaction.
	 *
	 * @return bool
	 */
	final function commit(){
		$this->_BeginTransactionDelayed = false;

		if(!$this->isConnected()){ return true; }
		return $this->_commit();
	}

	function _commit(){
		if(!$this->isConnected()){ return true; }
		return $this->doQuery("COMMIT");
	}

	/**
	 * Rollbacks all database operations.
	 *
	 * @return bool
	 */
	final function rollback(){
		$this->_BeginTransactionDelayed = false;

		if(!$this->isConnected()){ return true; }
		return $this->_rollback();
	}

	function _rollback(){
		return $this->doQuery("ROLLBACK");
	}

	/**
	 * Inserts a record into database table.
	 *
	 * Takes an associative array of column => value pairs and creates a new record with those values in given table.
	 *
	 * <code>
	 * $dbmole->insertIntoTable("comments",array(
	 *		"title" => "Titulek",
	 *		"author" => "Yarri",
	 *		"body" => "text prispevku"
	 *	));
	 * </code>
	 *
	 * @param string $table_name
	 * @param array $values		associative array
	 * @param array $options	associative array
	 * @return bool
	 */
	function insertIntoTable($table_name,$values,$options = array()){
		settype($table_name,"string");
		settype($values,"array");

		if(!isset($options["do_not_escape"])){ $options["do_not_escape"] = array(); } 
		if(!is_array($options["do_not_escape"])){ $options["do_not_escape"] = array($options["do_not_escape"]); }
		
		$query_fields = array();
		$query_values = array();
		$bind_ar = array();
		foreach($values as $_field_name => $_value){	
			$query_fields[] = $_field_name;
			if(in_array($_field_name,$options["do_not_escape"])){
				$query_values[] = $_value;
				continue;
			}
			$query_values[] = ":$_field_name";
			//Matyas - test on object is performed in parameters bindings
			$bind_ar[":$_field_name"] = $_value;
		}

		return $this->doQuery("INSERT INTO $table_name (".join(",",$query_fields).") VALUES(".join(",",$query_values).")",$bind_ar,$options);
	}

	/**
	 * Inserts a record into a table or updates a record if it already exists.
	 *
	 * <code>
	 *	$dbmole->insertOrUpdateRecord("persons",
	 *		array(
	 *			"id" => 1000,
	 *			"firstname" => "John",
	 *			"surname" => "Blbec",
	 *			"updated" => "NOW()"
	 *		),
	 *		array(
	 *			"id_field" => "id",
	 *			"do_not_escape" => array("updated")
	 *		)
	 *	);
	 * </code>
	 *
	 * @param string $table_name
	 * @param array $values
	 * @param array $options
	 * @return bool
	 */
	function insertOrUpdateRecord($table_name,$values,$options = array()){
		settype($table_name,"string");
		settype($values,"array");

		// nazev policka, ktere je rozhodujici, zda zaznam existuje nebo nikoli
		$options["id_field"] = isset($options["id_field"]) ? (string)$options["id_field"] : "id";
		if(!isset($options["do_not_escape"])){ $options["do_not_escape"] = array(); } 
		if(!is_array($options["do_not_escape"])){ $options["do_not_escape"] = array($options["do_not_escape"]); }

		$id_field = $options["id_field"];
		$id_value = $values[$id_field];

		unset($options["id_field"]); // dale toto nastaveni uz neni nutne

		// TODO: tady se zatim vubec neresi to, ze muze byt nastaveno $options["do_not_escape"] = array("id")
		$_options = $options;
		$_options["type"] = "integer";
		$count = $this->selectSingleValue("SELECT COUNT(*) FROM $table_name WHERE $id_field=:id_value",array(":id_value" => $id_value),$_options);

		if($count==0){

			return $this->insertIntoTable($table_name,$values,$options);

		}else{

			$update_ar = array();
			$bind_ar = array();
			foreach($values as $_key => $_value){	
				/*if(!isset($options["do_not_escape"]["$_key"])){
					$bind_ar[":$_key"] = is_object($_value) ? $_value->getId() : $_value;
				}*/
				$bind_ar[":$_key"] = $_value;
				if($_key == $id_field){ continue; }
				if(!isset($options["do_not_escape"]["$_key"])){
					$update_ar[] = "$_key=:$_key";	
				}else{
					$update_ar[] = "$_key=$_value";
				}
			}
			if(sizeof($update_ar)==0){ return true; } // je to podivne, ale tady se nic nemeni; nekdo vola nmetodu nesmyslne ve stylu: $dbmole->insertOrUpdateRecord("persons",array("id" => 20));
			return $this->doQuery("UPDATE $table_name SET ".join(", ",$update_ar)." WHERE $id_field=:$id_field",$bind_ar,$options);

		}
	}

	/**
	 * Disconnects database
	 */
	function closeConnection(){
		if(!isset($this->_DbConnect)){ return; }
		$this->_disconnectFromDatabase();
		$this->_DbConnect = null;
	}

	/**
	 * Gets next value of a sequence.
	 *
	 * @param string $sequence_name
	 * @abstract
	 */
	function selectSequenceNextval($sequence_name){ return null; }

	/**
	 * Gets current value of a sequence.
	 *
	 * @param string $sequence_name
	 * @abstract
	 */
	function selectSequenceCurrval($sequence_name){ return null; }

	/**
	 * Checks if the database uses sequencies
	 *
	 * @return boolean
	 */
	function usesSequencies(){ return true; }

	/**
	 * Executes a query.
	 *
	 * To prevent against a SQL attack you should not write conditions directly to query string but you should use the form with $bind_ar to sanitize the input data.
	 *
	 * <code>
	 * $dbmole->executeQuery("SELECT * FROM articles WHERE id=:id",array(":id" => 123));
	 * </code>
	 *
	 * Also arrays can be used as bind_ar
	 * <code>
	 * $dbmole->executeQuery("SELECT * FROM articles WHERE id IN :ids",array(":ids" => array(123,124,125)));
	 * </code>
	 * which will be internally transformed into this
	 * <code>
	 * $dbmole->executeQuery("SELECT * FROM articles WHERE id IN (:ids_0, :ids_1, :ids_2)",array(":ids_0" => 123, ":ids_1" => 124, ":ids_2" => 125));
	 * </code>
	 *
	 * In $options array the execution mode can be set:
	 *		$options["mode"] = OCI_DEFAULT
	 *		$options["mode"] = OCI_COMMIT_ON_SUCCESS
	 * Default mode is OCI_DEFAULT.
	 *
	 * @param string $query
	 * @param array $bind_ar
	 * @param array $options
	 * @return statement or null on error
	 */
	function executeQuery($query,$bind_ar = array(),$options = array()){
		settype($query,"string");
		settype($bind_ar,"array");
		settype($options,"array");

		// prevod prip. poli v $bind_ar
		$b_ar = array();
		foreach($bind_ar as $key => $value){
			if(is_array($value)){
				$replace = array();
				foreach($value as $_k => $_v){
					$b_ar["{$key}_$_k"] = $_v;
					$replace[] = "{$key}_$_k";
				}
				$query = str_replace($key,"(".join(", ",$replace).")",$query);
				continue;
			}
			$b_ar[$key] = $value;
		}
		$bind_ar = $b_ar;

		$this->_reset();

		$this->_Query = $query;
		$this->_BindAr = $bind_ar;
		$this->_Options = $options;

		if(DBMOLE_CHECK_BIND_AR_FORMAT){
			foreach($bind_ar as $k => &$v){
				if(!preg_match('/^:.*/',$k)){
					$this->_raiseDBError("there is a suspicious key in bind_ar: \"$k\"");
					return;
				}
			}
		}

		$this->_hookBeforeQueryExecution();

		$out = $this->_executeQuery();

		$this->_QueriesExecuted++;

		$this->_hookAfterQueryExecution();

		return $out;
	}

	/**
	 * Escapes float value for use in sql string.
	 *
	 * @param float $f
	 * @return string
	 */
	function escapeFloat4Sql($f){
		return (string)$f;
	}

	/**
	 * Escapes table name so it can be used in sql string
	 *
	 * @param string $t name of table
	 * @return string
	 */
	function escapeTableName4Sql($t){
		return $t;
	}

	/**
	 * Escapes boolean value for use in sql string.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function escapeBool4Sql($value){
		return $value? 'TRUE' : 'FALSE';
	}

	/**
	 * Escapes given value for use in sql string
	 *
	 * @param mixed php value
	 * @return string SQL reprezentation of given value
	 */
	function escapeValue4sql($value){
			if(is_object($value)){ $value = $value->getId(); }
			if($value===null)
					return 'NULL';
			if(is_float($value))
					return $this->escapeFloat4sql($value);
			if(is_integer($value))
					return $value;
			if(is_bool($value))
					return $this->escapeBool4sql($value);
			return $this->escapeString4Sql($value);
	}

	/**
	 * Realizes the query execution.
	 *
	 * @access private
	 * @ignore
	 * @return statement or null on error
	 */
	function _executeQuery(){
		$query = &$this->_Query;
		$bind_ar = &$this->_BindAr;
		$options = &$this->_Options;

		$this->_normalizeBindAr($bind_ar);

		foreach($bind_ar as &$value){
			$value = $this->escapeValue4sql($value);
		}

		$query_to_execute = strtr($query,$bind_ar);

		//
		$this->_connectToDatabase();
		$result = $this->_runQuery($query_to_execute);
		if(!$result){
			$this->_raiseDBError("failed to execute SQL query");
			return null;
		}

		return $result;
	}

	/**
	 * @ignore
	 */
	function _normalizeBindAr(&$bind_ar){
		foreach($bind_ar as $k => &$value){
			if(is_object($value)){ $value = $value->getId(); }
		}
	}
	
	/**
	 * error message dependent on database type
	 *
	 * @ignore
	 * @access private
	 */
	function _getDbLastErrorMessage(){ return ""; }

	/**
	 *
	 * @ignore
	 * @access private
	 */
	function _hookBeforeQueryExecution(){
		if(defined("DBMOLE_COLLECT_STATICTICS") && DBMOLE_COLLECT_STATICTICS){
			list($usec, $sec) = explode(" ", microtime());
			$this->_start_utime = ((float)$usec + (float)$sec);
		}
	}

	/**
	 * @ignore
	 * @access private
	 */
	function _hookAfterQueryExecution(){
		global $__DMOLE_STATISTICS__;

		if(defined("DBMOLE_COLLECT_STATICTICS") && DBMOLE_COLLECT_STATICTICS){
			if(!isset($__DMOLE_STATISTICS__)){ $__DMOLE_STATISTICS__ = array(); }
			if(!isset($__DMOLE_STATISTICS__[$this->getQuery()])){
				$__DMOLE_STATISTICS__[$this->getQuery()] = array();
			}

			$start_utime = $this->_start_utime;
			list($usec, $sec) = explode(" ", microtime());
			$stop_utime = ((float)$usec + (float)$sec);

			$__DMOLE_STATISTICS__[$this->getQuery()][] = array(
				"time" => $stop_utime - $start_utime,
				"bind_ar" => $this->getBindAr()
			);
		}

		//echo "<pre>";
		//echo $this->getQuery();
		//echo "</pre>";

		//echo $stop_utime - $start_utime;
		//echo " -> ";
		//echo $this->total_time;
		//echo "<br>";
	}

	/**
	 * @ignore
	 * @access private
	 */
	function _readCache($seconds,$query,$bind_ar,$options){
		$filename = $this->_getCacheFilename($query,$bind_ar,$options);
		if(!file_exists($filename) || filemtime($filename)<(time()-$seconds)){
			return null;
		}
		$cache = Files::GetFileContent($filename,$error,$error_str);
		$rows = unserialize($cache);
		if(!is_array($rows)){
			return null;
		}
		return $rows;
	}

	/**
	 * @ignore
	 * @access private
	 */
	function _writeCache(&$rows,$query,$bind_ar,$options){
		$cache = serialize($rows);
		$filename = $this->_getCacheFilename($query,$bind_ar,$options);
		$dir = preg_replace("/[^\\/]*$/","",$filename);
		Files::Mkdir($dir,$error,$error_str);
		Files::WriteToFile($filename,$cache,$error,$error_str);
		return true;
	}

	/**
	 * @ignore
	 * @access private
	 */
	function _getCacheFilename($query,$bind_ar,$options){
		// TODO: do we really need the atribute $this->_CacheDir?
		if(!isset($this->_CacheDir)){
			if(defined("TEMP")){
				$this->_CacheDir = TEMP;
			}else{
				$this->_CacheDir = "/tmp/";
			}
			$this->_CacheDir .= "/dbmole_cache/".$this->getDatabaseType()."/".$this->getConfigurationName()."/";
		}

		return $this->_CacheDir."/".md5($query)."/".md5(
			serialize(array(
				"bind_ar" => $bind_ar,
				"options" => $options
			))
		);
	}

	/**
	 * Detects boolean value returned by database.
	 *
	 * @param mixed $value boolean field returned from db layer (in form of string or integer or...)
	 * @return bool  PHP boolean representation
	 */
	function parseBoolFromSql($value){
		if(is_null($value)){return null; }
		if(is_numeric($value)){
			return (bool)$value;
		}
		return in_array(strtolower($value),array("t","true","y"));
	}
}