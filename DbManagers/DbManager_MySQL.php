<?php
/*!
 * PHP SmartDb
 * http://www.phpsmartdb.com/
 *
 * Copyright 2011, Cirkuit Networks
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://www.phpsmartdb.com/license
 */
/**
 * Handles the actual database communication. This DbManager is for MySQL. (Set $GLOBALS['SQL_DEBUG_MODE'] to true to error_log() all SQL requests.)
 */
/**
 */
require_once(dirname(__FILE__)."/DbManager.php");
/**
 * Handles the actual database communication. This DbManager is for MySQL. (Set $GLOBALS['SQL_DEBUG_MODE'] to true to error_log() all SQL requests.)
 * @package SmartDatabase
 */
class DbManager_MySQL implements DbManager {

	// Variables
	protected $_connection;
	protected $_lastResult;
	protected $_databaseName;
	private $_isConnected = false;
	private $_isDbSelected = false;

	protected $_server;
	protected $_user;
	protected $_password; //encrypted. use $this->GetPassword();
	protected $_connectParams;

	protected $_driver = 2; //default. see consts below
	const MYSQL_DRIVER = 1; //deprecated in PHP v5.5. not recommended
	const MYSQLI_DRIVER = 2;
	
	protected $_serverVersion;
	
	private $PHP_INT_MAX; //the PHP_INT_MAX constant doesnt work in some encrypters
	private $PHP_INT_MAX_HALF;
	
	/**
	 * First Dimension Where Operator - You'll probably want to ignore this option. It's for reverse compatibility reasons only.
	 * Used for joining multiple elements within the first dimension of a WHERE clause array. Can be 'AND' or 'OR'.
	 * @var string Can be "AND" or "OR"
	 */
	public static $FirstDimWhereOp = "AND"; //should be "AND" or "OR" only
	
	/**
	 * Default options for connecting. Can be overwritten at a global scope by changing this variable in your code
	 * 
	 * Currently, these are:
	 * ``` php
	 * public static $DefaultOptions = array(
	 * 	'new_link'=>false,		//for mysqli driver, when false, persistent connections are used (recommended). for mysql driver, this is the 4th parameters for mysql_connect. see mysql_connect documentation
	 * 	'client_flags'=>null,	//for mysql driver only- this is the 5th parameters for mysql_connect. see mysql_connect documentation
	 * 	'charset'=>'utf8mb4',	//if set, OpenConnection does a mysql_set_charset() with the given option (ie 'utf8mb4'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
	 * 	'collation'=>'utf8mb4_unicode_ci',	//if set with charset, OpenConnection does a "SET NAMES..." with the given option (ie 'utf8mb4_unicode_ci'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
	 * 	'driver'=>'mysqli'		//can be 'mysql' or 'mysqli' - the PHP extension to use for sql interaction. note- PHP v5.5+ deprecates 'mysql' driver
	 * 	'timezone'=>'',			//TODO: make default '+0:00'. empty will use system default time (not recommended). use hard numbers like '+0:00' cause timezone abbreviations must be defined in mysql otherwise, otherwise they may take no effect 
	 * );
	 * ```	
	 * @var array
	 * @see DbManager_MySQL::__construct() DbManager_MySQL::__construct()
	 */
	public static $DefaultOptions = array(
		'new_link'=>false,		//for mysqli driver, when false, persistent connections are used (recommended). for mysql driver, this is the 4th parameters for mysql_connect. see mysql_connect documentation
		'client_flags'=>null,	//for mysql driver only- this is the 5th parameters for mysql_connect. see mysql_connect documentation
		'charset'=>'utf8mb4',	//if set, OpenConnection does a mysqli_set_charset() with the given option (ie 'utf8mb4'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
		'collation'=>'utf8mb4_unicode_ci',	//if set with charset, OpenConnection does a "SET NAMES..." with the given option (ie 'utf8mb4_unicode_ci'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
		'driver'=>'mysqli',		//can be 'mysql' or 'mysqli' - the PHP extension to use for sql interaction. note- PHP v5.5+ deprecates 'mysql' driver
		'timezone'=>''			//TODO: make default '+0:00'. empty will use system default time (not recommended). use hard numbers like '+0:00' cause timezone abbreviations must be defined in mysql otherwise, otherwise they may take no effect
	);

	/**
	 * Constructor: pass connection values, set the db settings.
	 * 
	 * $options is an ARRAY as follows:
	 * ``` php
	 * $options = array( //SEE self::$DefaultOptions
	 * 	'new_link'=>false,		//this is the 4th parameters for mysql_connect. see mysql_connect documentation
	 * 	'client_flags'=>null,	//this is the 5th parameters for mysql_connect. see mysql_connect documentation
	 * 	'charset'=>'utf8mb4',	//if set, OpenConnection does a mysql_set_charset() with the given option (ie 'utf8mb4'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
	 * 	'collation'=>'utf8mb4_unicode_ci',	//if set with charset, OpenConnection does a "SET NAMES..." with the given option (ie 'utf8mb4_unicode_ci'). ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
	 * 	'driver'=>'mysqli',		//can be 'mysql' or 'mysqli' - the PHP extension to use for sql interaction
	 * 	'timezone'=>'',			//recommended this is set to '+0:00'. empty will use system default time (not recommended). use hard numbers like '+0:00' cause timezone abbreviations must be defined in mysql otherwise, otherwise they may take no effect
	 * );
	 * ```
	 * @see DbManager_MySQL::$DefaultOptions DbManager_MySQL::$DefaultOptions
	 * @param string $server The server to connect to. Ex: "localhost"
	 * @param string $user The username to connect with. Ex: "smartdb"
	 * @param string $password The password to connect with. Ex: "smartdb123"
	 * @param string $databaseName The database name to connect to.
	 * @param array $options Assoc-array of key => value options. See description above.
	 */
	public function __construct($server, $user, $password, $databaseName=null, $options=null) {
		if(!$server || !$user || !$password) throw new Exception("Not all connection variables are set.");
		$this->_server = $server;
		$this->_user = $user;
		$this->_databaseName = $databaseName;
		
		//merge in options
		if(is_array($options)){ //merge passed options with DefaultOptions
			$options = array_merge(self::$DefaultOptions, $options);
		}
		else $options = self::$DefaultOptions;
		
		//set the db driver from options (mysql or mysqli)
		$this->SetDriver($options['driver']);
		unset($options['driver']);
		
		//the rest of the options are our connection params
		$this->_connectParams = $options;
		
		$this->SetPassword($password);
		
		$this->PHP_INT_MAX = self::PHP_INT_MAX();
		$this->PHP_INT_MAX_HALF = $this->PHP_INT_MAX/2;
	}
	
	/**
	 * Sets the driver that should be used for communicating with the db
	 * @param string $driver 'mysqli' or 'mysql' ('mysql' is deprecated)
	 * @throws Exception if invalid $driver is given
	 */
	private function SetDriver($driver){
		switch ($driver){
			case '': //default
			case 'mysql':
				$this->_driver = self::MYSQL_DRIVER;
				break;
			case 'mysqli':
				$this->_driver = self::MYSQLI_DRIVER;
				break;
			default:
				throw new Exception("Invalid Driver - ".$driver);
		}
	}
	
	/**
	 * Sets the timezone for the connection. Default is empty which is system time (not recommended. store as UTC).
	 * @param string $timezone a valid mysql timezone. can use hard numbers like '+0:00' cause timezone abbreviations must be defined in mysql otherwise, otherwise they may take no effect 
	 * @throws Exception if invalid $driver is in use
	 * @returns true on success, false if connection is not open
	 */
	private function SetTimezone($timezone){
		if(!$this->_isConnected) return false;
		
		if($this->_driver == self::MYSQLI_DRIVER){ 
			//query MySQL to set client charset & collation. ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
			
			$query = "SET time_zone = '".$timezone."'";
			mysqli_query($this->_connection, $query);
			return true;
		}
		else if($this->_driver == self::MYSQL_DRIVER){
			throw new Exception("Timezones not implemented for MYSQL_DRIVER");
		}
	}
	
	/**
	 * Encrypts the password when stored locally so it won't be visible in most stacktraces
	 * @param string $password The password for this database connection. It will be encrypted and set to $this->_password
	 * @return null
	 */
	private function SetPassword($password){
		$this->_password = $this->Encrypt($password, $this->_server.$this->_user); //$this->_server.$this->_user will be our encrypt key
	}
	
	/**
	 * encrypts the password when stored locally so it won't be visible in most stacktraces
	 * @return string The decrypted password for this database connection
	 */
	private function GetPassword(){
		return $this->Decrypt($this->_password, $this->_server.$this->_user); //$this->_server.$this->_user will be our encrypt key 
	}

	/**
	 * Establishes the connection with the MySql database based off credentials and options passed to the DbManager constructor
	 * 
	 * This function is AUTOMATICALLY invoked when the first query is made. You likely won't need to call it.
	 * ``` php
	 * $options = array(
	 * 	'skip-select-db'=>false, //doesn't do a mysql_select_db. good for creating databases and etc management
	 * );
	 * ```
	 * @param array $options See description above
	 * @return bool true when connected (or already connected), throws exception if there is an error
	 * @see DbManager_MySQL::CloseConnection() DbManager_MySQL::CloseConnection()
	 */
	private static $_links = [];
	public function OpenConnection($options=null){
		//OPTIONS
		$defaultOptions = [ //default options
			'skip-select-db' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if($this->_isConnected){
			if(!$this->_isDbSelected && !$options['skip-select-db']) $this->SelectDatabase();
			return true;
		}
		try {
			if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
				$this->_connection = mysql_connect($this->_server, $this->_user, $this->GetPassword(), $this->_connectParams['new_link'], $this->_connectParams['client_flags']);
				if(!$this->_connection) throw new Exception("Error opening connection. MySQL Error No: ".mysql_errno()." - ".mysql_error());
			}
			else{ //-- mysqli
				//"new link" is created by default with mysqli
				//persistent connections are special (to help avoid max_user_connections
				//see http://php.net/manual/en/mysqli.persistconns.php - "To open a persistent connection you must prepend p: to the hostname when connecting."
				if(!$this->_connectParams['new_link']){
					//store active 'links' for each server/username combination for reuse
					$this->_connection = self::$_links[$this->_server][$this->_user] ?? null;
					if(!$this->_connection) $this->_connection = self::$_links[$this->_server][$this->_user] = mysqli_init(); //no connection yet, create one and cache in static links array
					if(!$this->_connection) throw new Exception("Error opening cached connection. MySQLi Error No: ".mysqli_connect_errno()." - ".mysqli_connect_error());
					
					//connect to database using cached link
					$connected = mysqli_real_connect($this->_connection, 'p:'.$this->_server, $this->_user, $this->GetPassword());
					if(!$connected) throw new Exception("Error opening persistent connection. MySQLi Error No: ".mysqli_connect_errno()." - ".mysqli_connect_error());
				}
				else{
					$this->_connection = mysqli_connect($this->_server, $this->_user, $this->GetPassword());
					if(!$this->_connection) throw new Exception("Error opening new connection. MySQLi Error No: ".mysqli_connect_errno()." - ".mysqli_connect_error());
				}
			}

			$this->_isConnected = true;
			
			//see if a charset is set (ie "utf8mb4");
			if($this->_connectParams['charset']){
				
				//get options
				$charset = $this->_connectParams['charset'];
				$collation = $this->_connectParams['collation'];
			
				if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
					//set PHP driver charset
					mysql_set_charset($charset, $this->_connection);
					
					//query MySQL to set client charset & collation. ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
					$query = "SET NAMES ".$charset;
					if($collation) $query .= " COLLATE ".$collation;
					mysql_query($query, $this->_connection);
				}
				else{ //-- mysqli
					//set PHP driver charset
					mysqli_set_charset($this->_connection, $charset);
					
					//query MySQL to set client charset & collation. ref: http://dev.mysql.com/doc/refman/5.7/en/charset-connection.html
					$query = "SET NAMES ".$charset;
					if($collation) $query .= " COLLATE ".$collation;
					mysqli_query($this->_connection, $query);
				}
			}
			
			if( ($timezone = $this->_connectParams['timezone']) ){
				$this->SetTimezone($timezone);
			}

			if(!$options['skip-select-db']){
				$this->SelectDatabase();
			}

			return true;
		}
		catch (Exception $e){
			$this->_isConnected = false;
			//exception show's the password passed through the constructor. only use for debugging
			throw new Exception("DbManager could not connect. Server: '".$this->_server."', User: '".$this->_user."'. Msg: ".$e->getMessage());
			//die("Bad arguments for DbManager constructor. Msg: ".$e->getMessage());
		}
	}
	
	/**
	 * Closes the MySQL connection if connected. Note that the connection is automatically closed when the PHP script has finished executing.
	 * @return bool true if the connection is successfully closed, false if there is no connection to close
	 * @see DbManager_MySQL::OpenConnection() DbManager_MySQL::OpenConnection() 
	 */
	public function CloseConnection() {
		if(!$this->_isConnected) return false;
		
		$this->FlushResults();
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			mysql_close($this->_connection);
		}
		else{ //-- mysqli
			mysqli_close($this->_connection);
			unset( self::$_links[$this->_server][$this->_user] );
		}
		$this->_isConnected = false;
		return true;
	}
       
	/**
	 * Returns the version of MySQL server currently connected to. Can use something like: version_compare($serverVersion,'1.2.3','>='); to compare versions
	 * @return string - version of the mysql server if successful, false on failure
	 * @see DbManager_MySQL::GetServerVersion() DbManager_MySQL::GetServerVersion() 
	 */
	public function GetServerVersion() {
		if($this->_serverVersion) return $this->_serverVersion;
		if(!$this->_isConnected){
			$this->OpenConnection(array(
				'skip-select-db' => false
			));
		}
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			$this->_serverVersion = preg_replace('#[^0-9\.]#', '', mysql_get_server_info($this->_connection));
		}
		else{ //-- mysqli
			//return mysqli_get_server_version($this->_connection);
			$this->_serverVersion = preg_replace('#[^0-9\.]#', '', mysqli_get_server_info($this->_connection));
		}
		return $this->_serverVersion;
	}

	/**
	 * Sets the database to use for this connection
	 * @param string $databaseName The database to use for this connection
	 * @param array $options as follows:
	 * ``` php
	 * $options = {
	 * 	'force-select-db' => false, //if true, will immediately call mysql_select_db() with the database passed to this class
	 * }
	 * ```
	 * @return null
	 * @see DbManager_MySQL::GetDatabaseName() DbManager_MySQL::GetDatabaseName()
	 */
	public function SetDatabaseName($databaseName, $options=null){
		//OPTIONS
		$defaultOptions = [ //default options
			'force-select-db' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		$this->_databaseName = $databaseName;
		if($this->_isConnected && $options['force-select-db'] && $this->_databaseName){
			$this->SelectDatabase();
		}
	}

	/**
	 * Gets the database name in use for this connection
	 * @return string the database name currently set for this connection
	 * @see DbManager_MySQL::SetDatabaseName() DbManager_MySQL::SetDatabaseName()
	 */
	public function GetDatabaseName(){
		return $this->_databaseName;
	}
	
	/**
	 * Internal. Does a mysql_select_db() using $this->_databaseName and $this->_connection
	 * @return null 
	 */
	private function SelectDatabase(){
		if(!$this->_databaseName) throw new Exception("Database Name is not set.");
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			$dbSelected = mysql_select_db($this->_databaseName, $this->_connection);
		}
		else{ //-- mysqli
			$dbSelected = mysqli_select_db($this->_connection, $this->_databaseName);
		}
		
		if(!$dbSelected) throw new Exception("Couldn't select database '".$this->_databaseName."'. Please make sure it exists.");
		
		$this->_isDbSelected = true;			
	}

	/**
	 * Executes a SELECT statement on the currently selected database
	 * 
	 * ``` php
	 * $options = array(
	 * 	'distinct' => false, //if true, does a SELECT DISTINCT for the select. Note: there must only be 1 select field, otherwise an exception is thrown
	 * 
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-select-fields-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in select fields
	 * 	'add-where-clause-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in the where clause
	 * 	'add-order-clause-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in the order clause
	 *  
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'add-select-fields-dot-notation' => false, //if true, automatically adds dot.notation before column names in select fields
	 * 	'add-where-clause-dot-notation' => false, //if true, automatically adds dot.notation before column names in the where clause
	 * 	'add-order-clause-dot-notation' => false, //if true, automatically adds dot.notation before column names in the order clause
	 *  
	 * 	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie "WHERE `price`='123'" instead of "WHERE `price`=123"). NOTE- this is for reverse compatibility. only used if $table is a string and not a SmartTable object
	 * 	'force-select-db' => false, //if true, will always call mysql_select_db() with the database passed to this class
	 * );
	 * ```
	 * @param array $array_select_fields The columns to select. Ex: array("CustomerId", "Name", "EmailAddress")
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $array_where The WHERE clause of the query. Ex: array( array("CustomerId"=>5, "CustomerName"=>"Jack"), array("CustomerName"=>"Cindy") ) - ...WHERE (CustomerId=5 AND CustomerName='Jack') OR (CustomerName='Cindy')
	 * @param array $array_order The "ORDER BY" clause. Ex: array("CustomerId"=>"asc", "CustomerName"=>"desc") ... ORDER BY CustomerId ASC, CustomerName DESC
	 * @param string $limit With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set. With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1).
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of selected rows
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 */
	public function Select($array_select_fields, $table, $array_where='', $array_order='', $limit = '', $options=null) {
		//OPTIONS
		$defaultOptions = [ //default options
			'distinct' => false,
			'add-column-quotes' => false,
			'add-select-fields-column-quotes' => false,
			'add-where-clause-column-quotes' => false,
			'add-order-clause-column-quotes' => false,
			'add-dot-notation' => false,
			'add-select-fields-dot-notation' => false,
			'add-where-clause-dot-notation' => false,
			'add-order-clause-dot-notation' => false,
			'quote-numerics' => false,
			'force-select-db' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if(!is_array($array_select_fields) || count($array_select_fields)==0){
			$array_select_fields = array("*"); //default to "SELECT * ..."
		}
		if(!is_array($array_where)) {
			$array_where = array();
		}
		if(!is_array($array_order)) {
			$array_order = null;
		}

        $distinct = '';
		if($options['distinct']){
			if(count($array_select_fields) > 1) throw new Exception("Cannot SELECT DISTINCT on more than 1 column.");
			$distinct = "DISTINCT ";

			//DISTINCT and ORDER BY have some restrictions when used together. we cant order-by a column not selected as distinct: it's illogical.
			if($array_order){ 
				if( !in_array("*", $array_select_fields) ){ //if selecting individual columns and not *, all sort columns MUST be in the select array
					$sortCols = $this->OrderArrayToColArray($array_order);
					$intersectedCols = array_intersect($array_select_fields, $sortCols); //all sort columns MUST also be in the select array
					if(count($intersectedCols) != count($sortCols)){ //these must match
						throw new Exception("Cannot order restults of a distinct set by a column that is not selected as distinct. It's illogical."); //mysql will error out here too
					}
				}
			}
		}
		$select_items = $this->ArrayToCSV($table, $array_select_fields, ($options['add-dot-notation'] || $options['add-select-fields-dot-notation']), ($options['add-column-quotes'] || $options['add-select-fields-column-quotes']) );
		$order_clause = $this->OrderArrayToString($table, $array_order, ($options['add-dot-notation'] || $options['add-order-clause-dot-notation']), ($options['add-column-quotes'] || $options['add-order-clause-column-quotes']));
		$where_clause = $this->GenerateWhereClause($table, $array_where, ($options['add-dot-notation'] || $options['add-where-clause-dot-notation']), ($options['add-column-quotes'] || $options['add-where-clause-column-quotes']), array(
			//extended options
			'quote-numerics'=>$options['quote-numerics'],
		));

		$validLimit = false;
		if($limit){ //should handle $limit==0, which is invalid.
			$validLimit = preg_match('/^\d+(,[1-9]+\d*)?$/',$limit); //matches "10" or "100,10". will incorrectly match "0", but "0,10" is correct. the if statement above should handle the "0" case though
			if($validLimit) $limit = "LIMIT ".$limit;
		}
		if(!$validLimit) $limit = null;

		$sql_select = "SELECT ".$distinct.$select_items." FROM ".$this->GetDotNotation($table)." ".$where_clause." ".$order_clause." ".$limit;

		$this->Query($sql_select, $options);

		return $this->AffectedRows();
	}

	/**
	 * Executes an INSERT statement on the currently selected database
	 * 
	 * ``` php
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * );
	 * ```
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $field_val_array Assoc array of columnName=value of values to insert. Ex: array('Name'=>'Jack', 'EmailAddress'=>'jack@frost.com') ... INSERT INTO Customer (Name, EmailAddress) VALUES ('Jack', 'jack@frost.com')
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of inserted rows (1 or 0)
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 */
	public function Insert($table, $field_val_array, $options=null) {
		//OPTIONS
		$defaultOptions = [ //default options
			'add-column-quotes' => false,
			'add-dot-notation' => false,
			'force-select-db' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		$sql_fields = "";
		$sql_values = null;
		foreach ($field_val_array as $field => $value) {
			// Compile SQL fields
			if ($sql_fields) $sql_fields .= ", ";
			
			$cleanColumnName = $field; //save column name without quotes or dot notation

			if($options['add-dot-notation']) $sql_fields .= $this->GetDotNotation($table, $field, $options['add-column-quotes']);
			else if($options['add-column-quotes']) $sql_fields .= "`$field`";
			else $sql_fields .= $field;

			// Compile SQL values
			if ($sql_values!==null) {$sql_values .= ", ";}

			if ($value == "now()") { //now()
				$sql_values .= $value;
			}
			else {
				$castQuoteVal = $this->CastQuoteValue($table, $cleanColumnName, $value);
				if($castQuoteVal === null) $sql_values .= "null";
				else $sql_values .= $castQuoteVal;
			}
		}
		$sql_insert = "INSERT INTO ".$this->GetDotNotation($table)." (".$sql_fields.") VALUES (".$sql_values.")";

		$this->Query($sql_insert, $options);

		return $this->AffectedRows();
	}

	/**
	 * Executes an UPDATE statement on the currently selected database
	 * 
	 * ``` php
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * 	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...). NOTE- this is for reverse compatibility. only used if $table is a string and not a SmartTable object
	 * );
	 * ```
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $field_val_array Assoc array of columnName=value of data to update. ex: array('col1'=>5, 'col2'=>'foo') ... UPDATE $table SET col1=5, col2='foo' ...
	 * @param array $array_where The where clause. ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	 * @param string $limit The amount of rows to limit the update to (if any) from the beginning of the result set
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of updated rows
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 */
	public function Update($table, $field_val_array, $array_where='', $limit = '', $options=null) {
		//OPTIONS
		$defaultOptions = [ //default options
			'add-column-quotes' => false,
			'add-dot-notation' => false,
			'force-select-db' => false,
			'quote-numerics' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		$arg = "";
		foreach ($field_val_array as $field => $value) {
			if ($arg) {$arg .= ", ";}
			
			$cleanColumnName = $field; //save column name without quotes or dot notation

			if($options['add-dot-notation']) $arg .= $this->GetDotNotation($table, $field, $options['add-column-quotes']);
			else if($options['add-column-quotes']) $arg .= "`$field`";
			else $arg .= $field;

			$arg .= '=';

			if ($value == "now()") { //now()
				$arg .= $value;
			}
			else {
				$castQuoteVal = $this->CastQuoteValue($table, $cleanColumnName, $value);
				if($castQuoteVal === null) $arg .= "null";
				else $arg .= $castQuoteVal;
			}
		}

		$where_clause = $this->GenerateWhereClause($table, $array_where, $options['add-dot-notation'], $options['add-column-quotes'], array(
			//extended options
			'quote-numerics'=>$options['quote-numerics'],
		));

		$limit = (int)$limit;
		if($limit > 0) $limit = "LIMIT ".$limit;
		else $limit = null;

		$sql_update = "UPDATE ".$this->GetDotNotation($table)." SET ".$arg." ".$where_clause." ".$limit;

		$this->Query($sql_update, $options);

		return $this->AffectedRows();
	}

	/**
	 * Executes a DELETE statement on the currently selected database
	 * 
	 * ``` php
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * 	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...). NOTE- this is for reverse compatibility. only used if $table is a string and not a SmartTable object
	 * );
	 * ```
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $array_where The WHERE clause. Ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	 * @param string $limit The amount of rows to limit the delete to (if any) from the beginning of the result set
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of selected rows
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 */
	public function Delete($table, $array_where='', $limit='', $options=null) {
		//OPTIONS
		$defaultOptions = [ //default options
			'add-column-quotes' => false,
			'add-dot-notation' => false,
			'force-select-db' => false,
			'quote-numerics' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		$where_clause = $this->GenerateWhereClause($table, $array_where, $options['add-dot-notation'], $options['add-column-quotes'], array(
			//extended options
			'quote-numerics'=>$options['quote-numerics'],
		) );

		$limit = (int)$limit;
		if($limit > 0) $limit = "LIMIT ".$limit;
		else $limit = null;

		$sql_delete = "DELETE FROM ".$this->GetDotNotation($table)." ".$where_clause." ".$limit;

		$this->Query($sql_delete, $options['force-select-db']);

		return $this->AffectedRows();
	}

	/**
	 * Executes a query against the selected database. IT IS NOT RECOMMENDED THAT YOU USE THIS FUNCTION DIRECTLY.
	 * 
	 * ``` php
	 * $options = array(
	 * 	'force-select-db' => false, //set to true if writing a query without dot notation (database.table.field) AND your app uses multiple databases with 1 connection (ie not using the 'new_link' flag on any database connection)
	 * 	'skip-select-db' => false, //if true, will skip any call to mysql_select_db. good for creating databases and etc management
	 * 	'multi-query' => false, //(NOT SUPPORTED WITH DRIVER 'mysql') - if true, will use mysqli_multi_query. use NextResult() to iterate through each query's result set. throws an exception on error
	 * );
	 * ```
	 * @param string $query The query to execute. Ex: "SELECT * FROM Customers WHERE CustomerId=1"
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return mixed Returns the result of mysql_query() - For SELECT, SHOW, DESCRIBE, EXPLAIN and other statements returning resultset, mysql_query() returns a resource. For other type of SQL statements, INSERT, UPDATE, DELETE, DROP, etc, mysql_query() returns TRUE  on success or FALSE on error.
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 */
	public function Query($query, $options=null) {
		//OPTIONS
		$defaultOptions = [ //default options
			'force-select-db' => false,
			'skip-select-db' => false,
			'multi-query' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if(!empty($GLOBALS['SQL_DEBUG_MODE'])){
			error_log( "DbManager->Query - ".$query );
		}
		
		//reset vars
		$this->FlushResults();

		if(!$this->_isConnected){
			$this->OpenConnection(array(
				'skip-select-db' => $options['skip-select-db']
			)); //lazy connect
		}
		else if($options['force-select-db']){ //the reason for this else: if we just connected, database was just selected. no need to select it again here.
			$this->SelectDatabase();
		}

		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			if($options['multi-query']){ //NOT SUPPORTED with mysql
				throw new Exception("'multi-query' not supported with driver 'mysql' (use mysqli)");
			}
			$this->_lastResult = mysql_query($query, $this->_connection);
		}
		else{ //-- mysqli
			if($options['multi-query']){
				if( mysqli_multi_query($this->_connection, $query) ){
					$this->_lastResult = mysqli_store_result($this->_connection);
				}
				if( mysqli_errno($this->_connection) ){
					throw new Exception("Query(): Invalid multi-query: ".$this->Error()." - SQL: ".$query);
				}
				
				//if last_result is false and there is no error, this could have been an insert. this is valid
				if($this->_lastResult===false){
					$this->_lastResult = true;
				}
			}
			else{
				//var_dump($query);
				//var_dump($this->_connection);
				$this->_lastResult = mysqli_query($this->_connection, $query);
				//var_dump($this->_lastResult);
			}
		}

		if (!$this->_lastResult) {
			throw new Exception("Query() Exception: ".$this->Error()." - SQL: ".$query);
		}
		return $this->_lastResult;
	}
	
	/**
	 * (NOT SUPPORT WITH DRIVER 'mysql') To be used with Query() and the 'multi-query' option set to true. This will get the result set of the next query in the batch from 'multi-query'. Use FetchAssoc* and FetchArray* functions to iterate over each result set of rows.
	 * Returns true if the next result set is ready for use, false if there are no more result sets. throws an exception on error
	 * @return mixed Returns true if the next result set is ready for use, false if there are no more result sets. throws an exception on error 
	 */
	public function NextResult(){
		if(!$this->_isConnected) return false;
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql //NOT SUPPORTED with mysql
			throw new Exception("NextResult() and 'multi-query' are not supported with driver 'mysql' (use mysqli)");
		}
		else{ //-- mysqli
			$this->FreeResult();
			if(mysqli_more_results($this->_connection)==false || mysqli_next_result($this->_connection)==false){
				//no more results
				return false;
			}
			$this->_lastResult = mysqli_store_result($this->_connection);
			if( mysqli_errno($this->_connection) ){
				throw new Exception("NextResult(): Invalid multi-query: ".$this->Error());
			}
			
			//if last_result is false and there is no error, this could have been an insert. this is valid
			if($this->_lastResult===false){
				$this->_lastResult = true;
			}
			return $this->_lastResult;
		}
	}
	
	/**
	 * clears remaining results of a multi-query result set. you must do this before you execute the next query.
	 * see http://php.net/manual/en/mysqli.multi-query.php
	 */
	public function FlushResults(){
		$this->FreeResult();
		if(!$this->_isConnected) return false;
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql //NOT SUPPORTED with mysql
			//throw new Exception("NextResult(), FlushResults(), and 'multi-query' are not supported with driver 'mysql' (use mysqli)");
			return false;
		}
		else{ //-- mysqli
			while(mysqli_more_results($this->_connection) && mysqli_next_result($this->_connection));
			return true;
		}		
	}

	/**
	 * frees the current result set. mostly needed for multi-query statements
	 */
	private function FreeResult(){
		if(is_resource($this->_lastResult)) {
			if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
				mysql_free_result($this->_lastResult);
			}
			else{ //-- mysqli
				mysqli_free_result($this->_lastResult);
			}
		}
		$this->_lastResult = null;
		return true;
	}
	
	/**
	 * Places the last query results into an array of ASSOC arrays, and returns it. Each row is an index in the array. Returns an empty array if there are no results.
	 * 
	 * Example:
	 * ``` php
	 * Function returns an array similar to:
	 * array(
	 * 	0 => array(
	 * 		"CustomerId" => "4",
	 * 		"EmailAddress" => "jack@frost.com",
	 * 		"Name" => "Jack",
	 *  ),
	 * 	1 => array(
	 * 		"CustomerId" => "6",
	 * 		"EmailAddress" => "queen@muppets.com",
	 * 		"Name" => "Miss Piggy",
	 * 	),
	 * 	...
	 * )
	 * ```
	 * @return array An array of ASSOC arrays, and returns it. Each row is an index in the array. Returns an empty array if there are no results.
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 */
	public function FetchAssocList() {
		$data = array(); //so an empty array is returned if no rows are set. avoids "Warning: Invalid argument supplied for foreach()"
		$numRows = $this->NumRows();
   		for ($i = 0; $i < $numRows; $i++) {
       		$data[$i] = $this->FetchAssoc();
   		}
   		return $data;
	}

	/**
	 * Places the last query results into an array of NON-ASSOC arrays, and returns the array. Returns an empty array if there are no results.
	 * 
	 * Example:
	 * ``` php
	 * Function returns an array similar to:
	 * array(
	 * 	0 => array(
	 * 		0 => "4",
	 * 		1 => "jack@frost.com",
	 * 		2 => "Jack",
	 * 	),
	 * 	1 => array(
	 * 		0 => "6",
	 * 		1 => "queen@muppets.com",
	 * 		2 => "Miss Piggy",
	 * 	),
	 * 	...
	 * );
	 * ```
	 * @return array An array of NON-ASSOC arrays, and returns the array. Returns an empty array if there are no results.
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 */
	public function FetchArrayList() {
		$data = array(); //so an empty array is returned if no rows are set. avoids "Warning: Invalid argument supplied for foreach()"
		$numRows = $this->NumRows();
   		for ($i = 0; $i < $numRows; $i++) {
       		$data[$i] = $this->FetchArray();
   		}
   		return $data;
	}

	/**
	 * Returns an ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * 
	 * Example:
	 * if( (row = $dbManager->FetchAssoc()) ){ $row['id']...
	 * 
	 * Function returns an array similar to:
	 * ``` php
	 * array(
	 * 		"CustomerId" => "4",
	 * 		"EmailAddress" => "jack@frost.com",
	 * 		"Name" => "Jack",
	 * );
	 * ```
	 * @return array An ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchArray() DbManager_MySQL::FetchArray()
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 */
	public function FetchAssoc() {
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_fetch_assoc($this->_lastResult);
		}
		else{ //-- mysqli
			return mysqli_fetch_assoc($this->_lastResult);
		}
	}

	/**
	 * Returns a NON-ASSOC array of the last query results. Array keys are numeric. False is returned if there are no more results.
	 *
	 * Example - Function returns an array similar to:
	 * ``` php
	 * array(
	 * 		0 => "4",
	 * 		1 => "jack@frost.com",
	 * 		2 => "Jack",
	 * );
	 * ```
	 * @return array An ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * @see DbManager_MySQL::FetchAssocList() DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList() DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc() DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 */
	public function FetchArray() {
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_fetch_array($this->_lastResult);
		}
		else{ //-- mysqli
			return mysqli_fetch_array($this->_lastResult);
		}
	}

	/**
	 * Returns the number of rows returned from the last query (not affected rows!). This command is only valid for statements like SELECT or SHOW that return an actual result set. To retrieve the number of rows affected by a INSERT, UPDATE, REPLACE or DELETE query, use AffectedRows()
	 * @return int Returns the number of rows returned from the last query
	 * @see DbManager_MySQL::AffectedRows() DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 */
	public function NumRows() {
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_num_rows($this->_lastResult);
		}
		else{ //-- mysqli
			return mysqli_num_rows($this->_lastResult);
		}
	}

	/**
	 * Returns the error text from the last MySQL function
	 * @return string Returns the error text from the last MySQL function, or empty string ("")
	 */
	public function Error() {
		if(!$this->_isConnected) return false;
	
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_error($this->_connection);
		}
		else{ //-- mysqli
			return mysqli_error($this->_connection);
		} 
	}

	/**
	 * Returns the ID generated for an AUTO_INCREMENT column by the previous query
	 * @return int The ID generated for an AUTO_INCREMENT column by the previous query, or FALSE
	 */
	public function InsertId() {
		if(!$this->_isConnected) return false;
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_insert_id($this->_connection);
		}
		else{ //-- mysqli
			return mysqli_insert_id($this->_connection);
		}
	}

	/**
	 * Returns the number of affected rows by the last INSERT, UPDATE, REPLACE or DELETE query
	 * @return int Returns the number of affected rows by the last INSERT, UPDATE, REPLACE or DELETE query
	 * @see DbManager_MySQL::Select() DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert() DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update() DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows() DbManager_MySQL::NumRows()
	 */
	public function AffectedRows() {
		if(!$this->_isConnected) return false;
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			return mysql_affected_rows($this->_connection);
		}
		else{ //-- mysqli
			return mysqli_affected_rows($this->_connection);
		}
	}

	/**
	 * Runs mysql_real_escape_string() on the given $string and returns it.
	 * @param string $string The string to run mysql_real_escape_string() on.
	 * @param array $options [optional] These options are passed to OpenConnection()
	 * @return string Runs mysql_real_escape_string() on the given $string and returns it.
	 */
	public function EscapeString($string, $options=null) {
		//TODO: get rid of this completely and disable magic quotes: http://www.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc
		//if (get_magic_quotes_gpc()) { //DEPRECATED! will be removed in PHP 6
		//	$string = stripslashes($string);
		//}
		
		if(!$this->_isConnected) $this->OpenConnection($options);
		
		if($this->_driver == self::MYSQL_DRIVER){ //-- mysql
			$string = mysql_real_escape_string($string,$this->_connection);
		}
		else{ //-- mysqli
			$string = mysqli_real_escape_string($this->_connection, $string);
		}
		
		return $string;
	}

	/**
	 * Converts a sort array of structure array("id"=>"asc", "col1"=>"desc") into a string like  "ORDER BY id ASC, col1 DESC"
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $array_order
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @return array Returns a sort array of structure array("id"=>"asc", "col1"=>"desc") into a string like  "ORDER BY id ASC, col1 DESC"
	 */
	private function OrderArrayToString($table, $array_order, $dotNotation=false, $addColumnQuotes=false) {
		if(!is_array($array_order) || count($array_order)==0) return "";
		$sortOrder = "ORDER BY ";
		$firstFound = false;
		foreach($array_order as $colName => $direction){
			if(is_numeric($colName)){ //$direction is actually the col name them because user input array like array("id","col1"); assume asc for cols
				$colName = $direction;
				$direction = "ASC";
			}
			if($dotNotation) $colName = $this->GetDotNotation($table, $colName, $addColumnQuotes);
			else if($addColumnQuotes) $colName = "`$colName`";
			//else $colName = $colName;

			$direction = strtoupper($direction);
			if($direction != "ASC" && $direction != "DESC") throw new Exception("Sort direction must be 'ASC' or 'DESC', not '$direction' for column: $colName");
			if($firstFound) $sortOrder .= ", ";
			$sortOrder .= "$colName $direction";
			$firstFound = true;
		}
		return $sortOrder;
	}
	
	/**
	 * Converts a sort array of structure array("id"=>"asc", "col1"=>"desc") into an array of only the column names array("id","col1")
	 * @param array $array_order
	 * @return array Returns a sort array of structure array("id"=>"asc", "col1"=>"desc") into an array of only the column names array("id","col1")
	 */
	private function OrderArrayToColArray($array_order) {
		if(!is_array($array_order)) return array();
		$cols = array();
		foreach($array_order as $colName => $direction){
			if(is_numeric($colName)){ //$direction is actually the col name them because user input array like array("id","col1");
				$colName = $direction;
			}
			$cols[] = $colName;
		}
		return $cols;
	}

	/**
	 * Converts items in $array into a comma separated value string and returns that string
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $array
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @return string Converts items in $array into a comma separated value string and returns that string
	 */
	private function ArrayToCSV($table, $array, $dotNotation=false, $addColumnQuotes=false) {
		$csv = "";
		$i = 0;
		foreach($array as $val){
			$val = trim($val,"` "); //clean up val
			if ($i>0) { $csv .= ", "; }

			if($dotNotation) $csv .= $this->GetDotNotation($table, $val, $addColumnQuotes); //yes dot notation
			else if($addColumnQuotes && $val!="*") $csv .= "`$val`"; //no dot notation, but yes column quotes
			else  $csv .= $val; //no dot notation or column quotes
			$i++;
		}
		return $csv;
	}

	/**
	 * Returns a string of appropriate dot-notation/column quites for the given $table and $column
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param string $column
	 * @param bool $addColumnQuotes
	 * @return string Returns a string of appropriate dot-notation/column quites for the given $table and $column
	 */
	private function GetDotNotation($table, $column=null, $addColumnQuotes=false){
		//get table name. if $table is not a string, it has to be a SmartTable object
		if($table && !is_string($table)){
			$table = $table->TableName;
		}
		if(!$table) throw new \Exception('No $table given.');
		
		if($column){
			if($addColumnQuotes && $column!="*") return "`{$this->_databaseName}`.`$table`.`$column`";
			else return "`{$this->_databaseName}`.`$table`.$column";
		}
		else{
			return "`{$this->_databaseName}`.`$table`";
		}
	}

	/**
	 * Prepares the WHERE clause (from $array_where) for a SQL statement
	 * 
	 * ``` php
	 *  $options = array(
	 *  	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...). NOTE- this is for reverse compatibility. only used if $table is a string and not a SmartTable object 
	 *  );
	 * ```
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param array $array_where
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param array $options See description above
	 * @return string
	 */
	protected function GenerateWhereClause($table, $array_where, $dotNotation=false, $addColumnQuotes=false, $options=null) {
		if( !is_array($array_where) || count($array_where)<=0 ) return '';
	
		$where_clause = '';
		foreach($array_where as $key=>$val){
			$thisWhere = $this->GenerateWhereRecursive($table, $key, $val, $dotNotation, $addColumnQuotes, '', '=', 'AND', $options);
			
			//need to add an " AND " (or " OR ") in between clauses. see constructor options
			if($thisWhere){
				$firstDimWhereOp = ( strtolower(self::$FirstDimWhereOp) == "or" ? "OR" : "AND" ); //force "AND" or "OR"
				if($where_clause) $where_clause .= " $firstDimWhereOp $thisWhere";
				else $where_clause = $thisWhere;
			} 
		}
	
		if (!$where_clause) return '';
		else return "WHERE ".$where_clause;
	}

	/**
	 * Helper for GenerateWhereClause()
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param string $key
	 * @param mixed $val
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param string $column
	 * @param string $condition
	 * @param string $operator
	 * @param array $options
	 * @return string
	 * @see DbManager_MySQL::GenerateWhereClause() DbManager_MySQL::GenerateWhereClause()
	 */
	private function GenerateWhereRecursive($table, $key, $val, $dotNotation=false, $addColumnQuotes=false, $column='', $condition='=', $operator='AND', $options=null){
		$key = trim($key);
	 
		if( ($newCondition = $this->IsCondition($key)) ){ //check if key is a condition
			$condition = $newCondition;
		}
		else if( ($newOperator = $this->IsOperator($key)) ){ //check if key is an operator
			$operator = $newOperator;
		}
		else if(!is_numeric($key)){ 		//if the key is not a keyword and not numeric, it is assumed to be the column name
			$column = $key;
		}
		
		$ret = ""; //the value returned
		//$val can either be a scalar or an array
		if( is_array($val) ){ //value is an array, recurse.
			foreach($val as $nextKey=>$nextVal){
				$thisWhere = $this->GenerateWhereRecursive($table, $nextKey, $nextVal, $dotNotation, $addColumnQuotes, $column, $condition, $operator, $options);
				
				//need to add an " AND " or " OR " in between clauses
				if($thisWhere){
					if($ret) $ret .= " $operator $thisWhere";
					else $ret = $thisWhere;
				}
			}
			if($ret) $ret = "($ret)"; //wrap in parenthesis
		}
		else{ //$val is a scalar. this is the end of the recursion
			$ret = $this->GenerateWhereSingle($table, $column, $condition, $val, $dotNotation, $addColumnQuotes, $options);
		}
		return $ret;
	}

	/**
	 * Helper for GenerateWhereRecursive()
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param string $column
	 * @param string $condition
	 * @param string $val
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param array $options
	 * @return string
	 * @see DbManager_MySQL::GenerateWhereRecursive() DbManager_MySQL::GenerateWhereRecursive()
	 */
	private function GenerateWhereSingle($table, $column, $condition, $val, $dotNotation, $addColumnQuotes, $options=null){
		$ret = ""; //the value returned
		$column = trim($column,"` "); //clean up field name
		
		if(!$column) throw new Exception("No column has been defined");
		
		$cleanColumnName = $column; //save column name without quotes or dot notation

		if($dotNotation) $column = $this->GetDotNotation($table,$column,$addColumnQuotes);
		else if($addColumnQuotes) $column = "`$column`";
		//else $column = $column;

		$castQuoteVal = $this->CastQuoteValue($table, $cleanColumnName, $val, $options);
		if($castQuoteVal === null){ //null is special
			if($condition === "!=" || $condition === "IS NOT"){
				$ret = $column." is not null"; //'is not null' for null
			}
			else $ret = $column." is null"; //'is null' for null
		}
		else{ //value is not null
			//SPECIAL CASES FOR "LIKE" CONDITION
			//See: http://stackoverflow.com/questions/3683746/escaping-mysql-wild-cards
			if($condition === "LIKE" || $condition === "NOT LIKE"){
				//to make sure we don't over-escape single quotes, we'll escape single quotes as "''" instead of "\'"
				//underscore ('_') is a wildcard in LIKE matching one character. need to escape it to match the actual underscore
				//see http://dev.mysql.com/doc/refman/5.1/en/string-literals.html 
				$castQuoteVal = str_replace( array("\'", '_'), array("''", '\_'), $castQuoteVal);
				
				//"To search for "\", specify it as "\\\\"; this is because the backslashes are stripped once by the
				//parser and again when the pattern match is made, leaving a single backslash to be matched against."
				//see http://dev.mysql.com/doc/refman/5.0/en/string-comparison-functions.html
				$castQuoteVal = addcslashes($castQuoteVal, '\\\\');
			}
			
			$ret = "$column $condition ".$castQuoteVal;
		}
		
		//HACK-ish: MySQL doesn't recognize NULL as '!=' a value or 'not like' a value.
		//so we have to force null checking for operators that negate.
		if(($condition === "!=" || $condition === "NOT LIKE") && $castQuoteVal !== null){
			$ret = "($ret OR $column is null)";
		}
		
		return $ret;
	}
	
	/**
	 * Some encrypters dont support PHP_INT_MAX. This calculates it.
	 * @return int PHP_INT_MAX for this machine
	 */
	private static function PHP_INT_MAX(){
	    $max=0x7fff;
	    $probe = 0x7fffffff;
	    while ($max == ($probe>>16))
	    {
	        $max = $probe;
	        $probe = ($probe << 16) + 0xffff;
	    }
	    return $max;
	}
	
	/**
	 * Checks if the given $keyword is a special keyword (ie "OR", "AND", "<", "!=", etc) and returns the match. Returns false if $keyword is not a keyword.
	 * @param string $keyword The keyword to check.
	 * @return mixed The matched operator, condition, or FALSE if there is no match.
	 * @see DbManager_MySQL::IsOperator() DbManager_MySQL::IsOperator()
	 * @see DbManager_MySQL::IsCondition() DbManager_MySQL::IsCondition()
	 */
	public function IsKeyword($keyword){
		if( ($operator = $this->IsOperator($keyword)) ) return $operator; //match operator
		if( ($condition = $this->IsCondition($keyword)) ) return $condition; //match condition
		return false; //no match
	}
	
	/**
	 * Returns the proper operator (AND or OR) if the $keyword is an operator (ie AND or OR). Otherwise returns false.
	 * @param string $keyword The keyword to check
	 * @return mixed The matched operator or FALSE if there is no match
	 * @see DbManager_MySQL::IsKeyword() DbManager_MySQL::IsKeyword()
	 * @see DbManager_MySQL::IsCondition() DbManager_MySQL::IsCondition()
	 */
	public function IsOperator($keyword){
		$keywordLower = strtolower(trim($keyword));
		switch($keywordLower){
			//operators
			case "or":
			case "and":
				return strtoupper($keyword);
		}
		return false; //no match
	}
	
	/**
	 * Returns the proper condition ("<",">","!=", etc) if the $keyword is an condition. Otherwise returns false.
	 * @param string $keyword The keyword to check
	 * @return mixed The matched condition or FALSE if there is no match
	 * @see DbManager_MySQL::IsOperator() DbManager_MySQL::IsOperator()
	 * @see DbManager_MySQL::IsKeyword() DbManager_MySQL::IsKeyword()
	 */
	public function IsCondition($keyword){
		$keyword = trim($keyword);
		$keywordLower = strtolower($keyword);
		switch($keywordLower){
			//conditions
			case ">":
			case ">=":
			case "<":
			case "<=":
			case "=":
			case "!=":
			case "like":
			case "not like":
			case "is not":
				return strtoupper($keyword);
			case "==": //special conditions
				return "=";
			case "<>": //special conditions
				return "!=";
		}
		return false; //no match
	}
	
	/**
	 * Returns the given $value with appropriate casts and quotes for SQL queries
	 * 
	 * ``` php
	 * $options = array(
	 * 	'quote-numerics' => false //for reverse compatibility. only used if $table is a string and not a SmartTable object
	 * );
	 * ```
	 * @param mixed $table The table name. Ex: "Customer". This can also be a SmartTable object- if so, data will be strongly typed and more accurate.
	 * @param mixed $value The value to check for quote usage
	 * @param string $column The column name this value is used for. Used for looking up the data type in the SmartDb
	 * @param array $options [optional] Array of function options. See description.
	 * @return mixed Returns the given $value with appropriate casts and quotes for SQL queries
	 */
	private function CastQuoteValue($table, $column, $value, $options=null){
		//OPTIONS
		$defaultOptions = [ //default options
			'quote-numerics' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
				
		//HACKish - handle SmartCells in case someone accidentally forgets to call ->GetValue() on the smart cell
		if( is_object($value) && get_class($value)=="SmartCell" ){
			$value = $value->GetRawValue();
		}
		
		//null is null
		if($value === null){
			return null;
		}
		
		//if $table is not a string, it has to be a SmartTable object. we can use this to get the column type
		if($table && !is_string($table)){
			try{
				//handle booleans.
				$isBool = is_bool($value); 
				if($isBool){ //boolean false is '\0'. make booleans default to 1 and 0
					if($value) $value = 1;
					else $value = 0;
				}
				
				//get the column's data type to determine if the value should be quoted
				$smartColumn = $table[$column];
				$columnDataType = $smartColumn->DataType; //$table is a SmartTable object
				switch($columnDataType){
					//dont quote numbers
					case 'tinyint':
					case 'smallint':
					case 'mediumint':
					case 'int':
					case 'bigint':
						if($value === "") $value = null;
						else $value = (int)$value;
						break;
						
					case 'float':
					case 'double':
					case 'decimal':
						if($value === "") $value = null;
						else $value = (float)$value;
						break;
						
					case 'binary': //needs quotes. this data type stores binary strings that have no character set or collation (it is NOT strictly ones and zeros)
						if(!$value || $value == "\0") $value = '0'; //force binary to be 0 if nothing is set
						$value = "'".$this->EscapeString($value)."'";
						break;

					//quote non-numbers
					default:
						if($isBool && !$value) $value = ""; //false should evalute to empty string
						$value = "'".$this->EscapeString($value)."'";
						break;
				}
				return $value;
			}
			catch(\Exception $e){
				//notify us of the invalid column and table, but don't error out
				//trigger_error($e->getMessage(), E_USER_WARNING );
				error_log($e->getMessage());
			}
		}
		
		//if we get here, we dont have database structure info and need to try to best guess how quotes should be used (also for reverse compatibility)
		//note that the following will incorrectly not quote something like "053" or even "546t" for varchar fields, and 53 or 546 get used instead.
		//this is why a SmartDb is recommended for db structure info
		if(!$options['quote-numerics'] && is_numeric($value) && ($value >= (0-$this->PHP_INT_MAX_HALF) && $value <= $this->PHP_INT_MAX_HALF)){
			return $value;
		}
		else{
			$value = "'".$this->EscapeString($value)."'";
			return $value;
		}
	}


	/*************** DATABASE MANAGEMENT ***************/
	/**
	 * Returns true if the given $databaseName exists, false otherwise.
	 * @param string $databaseName The name of the database to check for existence
	 * @return bool true if the given $databaseName exists, false otherwise.
	 */
	public function DatabaseExists($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$this->EscapeString($databaseName, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		$results = $this->FetchAssocList();
		return (count($results)>0);
	}
	/**
	 * Returns true if the given $tableName exists within the given $databaseName, false otherwise.
	 * @param string $databaseName The name of the database to check for existence
	 * @param string $tableName The name of the table to check for existence within the given $databaseName
	 * @return bool true if the given $tableName exists within the given $databaseName, false otherwise.
	 */
	public function TableExists($databaseName, $tableName) {
		if (!$databaseName) throw new Exception('$databaseName not set');

		$sql = "SELECT count(*) FROM information_schema.tables WHERE table_schema = '".$this->EscapeString($databaseName)."' AND table_name = '".$this->EscapeString($tableName)."'";
		$this->Query($sql);
		$results = $this->FetchArray();
		return ($results[0]>0);
	}
	/**
	 * Removes the given $tableName from within the given $databaseName.
	 * @param string $databaseName The database to remove the given $tableName from
	 * @param string $tableName The name of the table to remove.
	 * @return bool true if the table was successfully dropped or doesn't exist, false if the table exists and could not be dropped
	 */
	public function DropTable($databaseName, $tableName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$tableName) throw new Exception('$tableName not set');
		
		if(!$this->TableExists($databaseName, $tableName)) return true; //table doesn't exist to drop

		$sql = "DROP TABLE `".$this->EscapeString($databaseName)."`.`".$this->EscapeString($tableName)."`";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
		
		if($this->TableExists($databaseName, $tableName)) return false; //table still exists
		else return true; //table doesn't exist anymore
	}
	/**
	 * Emptys all rows from the given $tableName from within the given $databaseName.
	 * @param string $databaseName The database containing the $tableName to empty all rows from
	 * @param string $tableName The name of the table to empty all rows from
	 * @return bool true if the table was successfully emptied, false if the table doesn't exist
	 */
	public function EmptyTable($databaseName, $tableName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$tableName) throw new Exception('$tableName not set');
	
		if(!$this->TableExists($databaseName, $tableName)) return false; //table doesn't exist to drop
	
		$sql = "TRUNCATE TABLE `".$this->EscapeString($databaseName)."`.`".$this->EscapeString($tableName)."`";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
		
		return true;
	}
	/**
	 * Returns true if the database was created, false if it already exists or was not created for some reason.
	 * @param string $databaseName The name of the database to create 
	 * @return int Returns true if the database was created or already exists, false if it not created for some reason
	 */
	public function CreateDatabase($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		
		if($this->DatabaseExists($databaseName)) return true; //database already exists
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "CREATE DATABASE `".$this->EscapeString($databaseName, $connectOptions)."`";
		$this->Query($sql, $connectOptions);
		
		if(!$this->DatabaseExists($databaseName)) return false; //database wasn't created
		else return true; //database created
	}
	/**
	 * Returns true if the database was dropped, false if it doesn't exist or could not be dropped for some reason.
	 * @param string $databaseName The name of the database to drop 
	 * @return int Returns true if the database was dropped or doesn't exist, false if it could not be dropped for some reason
	 */
	public function DropDatabase($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		
		if(!$this->DatabaseExists($databaseName)) return true; //database doesn't exist to drop
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "DROP DATABASE `".$this->EscapeString($databaseName, $connectOptions)."`";
		$this->Query($sql, $connectOptions);
		
		if($this->DatabaseExists($databaseName)) return false; //database still exists
		else return true; //database doesn't exist anymore
	}
	/**
	 * Copies all structure and data from $sourceDatabaseName to $destDatabaseName. $destDatabaseName will be created if it is not already
	 * 
	 * The database user running this command will need appropriate privileges to both databases and/or the ability to create new databases
	 * ``` php
	 * $options = array(
	 * 	'create-tables' => true,
	 * 	'create-database' => true,
	 * 	'copy-data' => true,
	 * 	'drop-existing-tables' => false,
	 * 	'drop-existing-database' => false, 
	 * )
	 * ```
	 * @param string $sourceDatabaseName The name of the source database
	 * @param string $destDatabaseName The name of the destination database. This database will be created if it is not already
	 * @param array $options An array of key-value pairs (see description above)
	 * @return bool true on success. May throw an exception on error.
	 */
	public function CopyDatabase($sourceDatabaseName, $destDatabaseName, $options=null){
		$defaultOptions = array( //default options
			'create-tables' => true,
			'create-database' => true,
			'copy-data' => true,
			'drop-existing-tables' => false,
			'drop-existing-database' => false, 
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		//make sure the source database exists
		if(!$this->DatabaseExists($sourceDatabaseName)){
			throw new Exception("Source database does not exist to copy: ".$sourceDatabaseName);
		}
		
		//drop the destination database if it already exists and $options['drop-existing-database']==true
		if($options['drop-existing-database']){
			$success = $this->DropDatabase($destDatabaseName);
			if(!$success) throw new Exception("Could not drop destination database: ".$destDatabaseName);
		}
		
		//create the destination database if it doesnt already exist
		if($options['create-database']){
			$success = $this->CreateDatabase($destDatabaseName);
			if(!$success) throw new Exception("Could not create destination database: ".$destDatabaseName);
		}
		
		//set our dbmanager to the $sourceDatabaseName so we can select the tables/data
		$curDatabaseName = $this->GetDatabaseName(); //we'll restore this after the copy operation
		$this->SetDatabaseName($sourceDatabaseName, array('force-select-db'=>true));
		
		try{ //need to restore the database connection if something fails
			//get all table records in the source database
			$this->Query("show tables");
			$results = $this->FetchArrayList();
			
			//loop through all source table records
			foreach($results as $tableInfo){
				$tableName = $tableInfo[0];
				
				//copy the structure of the table
				if($options['drop-existing-tables']){
					$success = $this->DropTable($destDatabaseName, $tableName);
					if(!$success) throw new Exception("Could not drop destination table: `$destDatabaseName`.`$tableName`");
				}
				
				if($options['create-tables']){
					$this->Query("CREATE TABLE IF NOT EXISTS `".$this->EscapeString($destDatabaseName)."`.`".$this->EscapeString($tableName)."` LIKE `".$this->EscapeString($sourceDatabaseName)."`.`".$this->EscapeString($tableName)."`");
				}
				
				//copy the data with primary keys and indexes and etc
				if($options['copy-data']){
					$this->Query("INSERT `".$this->EscapeString($destDatabaseName)."`.`".$this->EscapeString($tableName)."` SELECT * FROM `".$this->EscapeString($sourceDatabaseName)."`.`".$this->EscapeString($tableName)."`");
				}
			}
		}
		catch(Exception $e){ //restore the database connection if something goes wrong
			$this->SetDatabaseName($curDatabaseName, array('force-select-db'=>true));
			throw $e;
		}
		
		//restore the database connection
		$this->SetDatabaseName($curDatabaseName, array('force-select-db'=>true));
		
		return true;
	}
	/**
	 * Returns true if the user exists and can connect from the given $host, false otherwise
	 * @param string $username The username to check for existence
	 * @param string $host The host the $username can connect from
	 * @return bool Returns true if the user exists for the given $host, false otherwise
	 */
	public function UserExists($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "SELECT `User` FROM `mysql`.`user` WHERE `User`='".$this->EscapeString($username, $connectOptions)."' AND `Host`='".$this->EscapeString($host, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		$results = $this->FetchAssocList();
		return (count($results)>0);
	}
	/**
	 * Creates a SQL user with the given $username and $password, able to connect from the given $host
	 * @param string $username The username to create
	 * @param string $password The password for the given $username
	 * @param string $host The host that the given $username can connect from 
	 * @return int Returns the number of affected rows (1 if the user was created, 0 otherwise) 
	 */
	public function CreateUser($username, $password, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$password) throw new Exception('$password not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "CREATE USER '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."' IDENTIFIED BY '".$this->EscapeString($password, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		return $this->AffectedRows();
	}
	/**
	 * Drops the SQL user with the given $username, able to connect from the given $host
	 * @param string $username The username to drop
	 * @param string $host The host that the given $username could connect from 
	 * @return int Returns the number of affected rows (1 if the user was dropped, 0 otherwise) 
	 */
	public function DropUser($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "DROP USER '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		return $this->AffectedRows();
	}
	/**
	 * Changes the SQL password for the user with the given $username on the given $host
	 * @param string $username The username to update the password on
	 * @param string $password The new password to set for this username on thie host
	 * @param string $host The host that the given $username can connect from
	 * @return int Returns the number of affected rows (1 if the user was created, 0 otherwise)
	 */
	public function ChangePassword($username, $password, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$password) throw new Exception('$password not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);
		
		// For mysql database server version 5.7.6, see https://www.cyberciti.biz/faq/mysql-change-user-password/
		$serverVersion = $this->GetServerVersion();
		if (version_compare($serverVersion,'5.7.6','>=')) { 
			$sql = "ALTER USER '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."' IDENTIFIED BY '".$this->EscapeString($password, $connectOptions)."'";
		}
		else{ //older mysql
			$sql = "SET PASSWORD FOR '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."' = PASSWORD('".$this->EscapeString($password, $connectOptions)."')";
		}
		
		$this->Query($sql, $connectOptions);
		return $this->AffectedRows();
	}
	/**
	 * Grants the given $username permission to the given $database, from the given $host
	 * @param $databaseName The database name that the $username should be granted permission to 
	 * @param $username The $username that should have permission to connect to the given $databaseName
	 * @param $host The host that the $username can connect from to connect to the given $databaseName
	 * @return int Returns the number of affected rows (1 if the user was granted permission, 0 otherwise) 
	 */
	public function GrantUserPermissions($databaseName, $username, $host="localhost"){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "GRANT ALL PRIVILEGES ON `".$this->EscapeString($databaseName, $connectOptions)."`.* TO '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		return $this->AffectedRows();
	}
	/**
	 * Grants the given $username FILE permissions when connecting from the given $host
	 * @param $username The username to grant FILE permissions to
	 * @param $host The host the given $username can connect from for FILE permissions
	 * @return int Returns the number of affected rows (1 if the user was granted permission, 0 otherwise) 
	 */
	public function GrantGlobalFilePermissions($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "GRANT FILE ON *.* TO '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."'";
		$this->Query($sql, $connectOptions);
		return $this->AffectedRows();
	}
	/**
	 * Revokes all permissions for the given $username to the given $databaseName, when connecting from the given $host
	 * @param $databaseName The database name to revoke the given $username permissions from
	 * @param $username The username to revoke permissions from
	 * @param $host The host the given $username could connect from that should have permissions revoked
	 * @return int Returns the number of affected rows (1 if the user was revoked permissions, 0 otherwise)
	 */
	public function RevokeUserPermissions($databaseName, $username, $host="localhost"){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');
		
		$connectOptions = array(
			"skip-select-db" => true
		);

		$sql = "REVOKE ALL PRIVILEGES ON `".$this->EscapeString($databaseName, $connectOptions)."`.* FROM '".$this->EscapeString($username, $connectOptions)."'@'".$this->EscapeString($host, $connectOptions)."'";
		$this->Query($sql, $connectOptions);

		return $this->AffectedRows();
	}
	
	//********************* password management ********************************
	
	//it's recommended to change these defaults
	private $_encryptMethod = 'AES-256-CFB';
	private $_encryptSalt = 'SmartDb';
	
	/**
	 * Set encrypt method for internal password storage. See https://www.php.net/manual/en/function.openssl-get-cipher-methods.php
	 * @param string $method ssl method to use, ref: https://www.php.net/manual/en/function.openssl-get-cipher-methods.php
	 */
	public function SetEncryptMethod($method){
		$this->_encryptMethod = $method;
	}
	
	/**
	 * Set encrypt salt for internal password.
	 * @param string $salt private salt text for additional encrypting security beyond encrypt key alone
	 */
	public function SetEncryptSalt($salt){
		$this->_encryptSalt = $salt;
	}
	
	/**
	 * Key must be length of 16, 24, or 32
	 * @return string the unique encrypt key used internally
	 */
	private function PadEncryptKey($key){
		$keyLength = strlen($key);
		if($keyLength > 24) $keyLength = 32;
		else if($keyLength > 16) $keyLength = 24;
		else $keyLength = 16;
		
		$key = substr($key, 0, $keyLength);
		$key = str_pad($key, $keyLength, "\0"); //pad with null characters
		return $key;
	}
	
	/**
	 * A basic encrypt function for storing the password locally in this class
	 * @param string $encrypt text to encrypt
	 * @param string $key key to encrypt against
	 */
	private function Encrypt($encrypt,$key) {
		$key = $key.$this->_encryptSalt;
		$key = $this->PadEncryptKey($key);
		$cipher = $this->_encryptMethod;

		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length($cipher) );
		$ciphertext_raw = openssl_encrypt($encrypt, $cipher, $key, OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
		return base64_encode( $iv.$hmac.$ciphertext_raw );
	}

	/**
	 * A basic decrypt function for storing the password locally in this class
	 * @param string $decrypt text to decrypt
	 * @param string $key key to decrypt w
	 */
	private function Decrypt($decrypt,$key) {
		$key = $key.$this->_encryptSalt;
		$key = $this->PadEncryptKey($key);
		$cipher = $this->_encryptMethod;

		$c = base64_decode($decrypt);
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = substr($c, 0, $ivlen);
		$sha2len=32;
		//$hmac = substr($c, $ivlen, $sha2len);
		$ciphertext_raw = substr($c, $ivlen+$sha2len);
		$original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);

		//verify. (no need to do this here. failure will cause connection issue regardless. removing for performance.)
		//$calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
		//if (hash_equals($hmac, $calcmac)){ // timing attack safe comparison 
		//	return $original_plaintext;
		//}
		//else return false; //decryption failed
		
		return $original_plaintext;
	}

}
