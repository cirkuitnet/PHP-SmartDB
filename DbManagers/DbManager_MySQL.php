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
 * @package SmartDatabase
 */
/**
 */
require_once(dirname(__FILE__)."/DbManager.php");
/**
 * Handles the actual database communication. This DbManager is for MySQL. (Set $GLOBALS['SQL_DEBUG_MODE'] to true to print all SQL requests.)
 * @package SmartDatabase
 */
class DbManager_MySQL implements DbManager {

	// Variables
	private $_isConnected = false;
	private $_connection;
	private $_lastResult;
	private $_databaseName;
	private $_isDbSelected = false;

	private $_server;
	private $_user;
	private $_password; //encrypted. use $this->GetPassword();
	private $_connectParams;
	
	private $PHP_INT_MAX; //the PHP_INT_MAX constant doesnt work in some encrypters
	private $PHP_INT_MAX_HALF;
	
	/**
	 * First Dimension Where Operator - You'll probably want to ignore this option. It's for reverse compatibility reasons only.
	 * Used for joining multiple elements within the first dimension of a WHERE clause array. Can be 'AND' or 'OR'.
	 * @var string Can be "AND" or "OR"
	 * @ignore
	 */
	public static $FirstDimWhereOp = "AND"; //should be "AND" or "OR" only

	/**
	 * Constructor: pass connection values, set the db settings. note: $options is an ARRAY
	 * <code>
	 * $options = array(
	 * 	'new_link'=>false,	//this is the 4th parameters for mysql_connect. see mysql_connect documentation
	 * 	'client_flags'=>'',	//this is the 5th parameters for mysql_connect. see mysql_connect documentation
	 * 	'charset'=>'',		//if set, OpenConnection does a mysql_set_charset() with the given option (ie 'utf8')
	 * );
	 * </code>
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
		$this->_connectParams = $options;
		
		$this->SetPassword($password);
		
		$this->PHP_INT_MAX = self::PHP_INT_MAX();
		$this->PHP_INT_MAX_HALF = $this->PHP_INT_MAX/2;
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
	 * This function is AUTOMATICALLY invoked when the first query is made. You likely won't need to call it.
	 * <code>
	 * $options = array(
	 * 	'skip-select-db'=>false, //doesn't do a mysql_select_db. good for creating databases and etc management
	 * );
	 * </code>
	 * @param array $options See description above
	 * @return bool true when connected (or already connected), throws exception if there is an error
	 * @see DbManager_MySQL::CloseConnection()
	 */
	public function OpenConnection($options = null){
		if($this->_isConnected){
			if(!$this->_isDbSelected && !$options['skip-select-db']) $this->SelectDatabase();
			return true;
		}
		try {
			$this->_connection = mysql_connect($this->_server, $this->_user, $this->GetPassword(), $this->_connectParams['new_link'], $this->_connectParams['client_flags']);
			if(!$this->_connection) throw new Exception("Couldn't connect to the server. Please check your settings.");
			
			//see if a charset is set (ie "utf8");
			if($this->_connectParams['charset']){
				mysql_set_charset($this->_connectParams['charset'], $this->_connection);
			}

			if(!$options['skip-select-db']){
				$this->SelectDatabase();			
			}

			$this->_isConnected = true;
			return true;
		}
		catch (Exception $e){
			$this->_isConnected = false;
			//exception show's the password passed through the constructor. only use for debugging
			throw new Exception("Bad arguments for DbManager constructor. Server: '".$this->_server."', User: '".$this->_user."'. Msg: ".$e->getMessage());
			//die("Bad arguments for DbManager constructor. Msg: ".$e->getMessage());
		}
	}
	
	/**
	 * Closes the MySQL connection if connected. Note that the connection is automatically closed when the PHP script has finished executing.
	 * @return bool true if the connection is successfull closed, false if there is no connection to close
	 * @see DbManager_MySQL::OpenConnection() 
	 */
	public function CloseConnection() {
		if($this->_isConnected){
			mysql_close($this->_connection);
			$this->_isConnected = false;
			return true;
		}
		return false;
	}

	/**
	 * Sets the database to use for this connection
	 * @param string $databaseName The database to use for this connection
	 * @param array $options{
	 * 	'force-select-db' => false, //if true, will immediately call mysql_select_db() with the database passed to this class
	 * }
	 * @return null
	 * @see DbManager_MySQL::GetDatabaseName()
	 */
	public function SetDatabaseName($databaseName, $options=null){
		$this->_databaseName = $databaseName;
		if($this->_isConnected && $options['force-select-db'] && $this->_databaseName){
			$this->SelectDatabase();
		}
	}

	/**
	 * Gets the database name in use for this connection
	 * @return string the database name currently set for this connection
	 * @see DbManager_MySQL::SetDatabaseName()
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
		
		$dbSelected = mysql_select_db($this->_databaseName, $this->_connection);
		if(!$dbSelected) throw new Exception("Couldn't select database '".$this->_databaseName."'. Please make sure it exists.");
		
		$this->_isDbSelected = true;			
	}

	/**
	 * Executes a SELECT statement on the currently selected database
	 * <code>
	 * $options = array(
	 * 	'distinct' => false, //if true, does a SELECT DISTINCT for the select. Note: there must only be 1 select field, otherwise an exception is thrown
	 * 
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 *  'add-select-fields-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in select fields
	 *  'add-where-clause-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in the where clause
	 *  'add-order-clause-column-quotes' => false, //if true, automatically adds `quotes` around the `column names` in the order clause
	 *  
	 *  'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 *  'add-select-fields-dot-notation' => false, //if true, automatically adds dot.notation before column names in select fields
	 *  'add-where-clause-dot-notation' => false, //if true, automatically adds dot.notation before column names in the where clause
	 *  'add-order-clause-dot-notation' => false, //if true, automatically adds dot.notation before column names in the order clause
	 *  
	 *  'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie "WHERE `price`='123'" instead of "WHERE `price`=123")
	 *  'force-select-db' => false, //if true, will always call mysql_select_db() with the database passed to this class
	 * );
	 * </code>
	 * @param array $array_select_fields The columns to select. Ex: array("CustomerId", "Name", "EmailAddress")
	 * @param string $table The table name. Ex: "Customer"
	 * @param array $array_where The WHERE clause of the query. Ex: array( array("CustomerId"=>5, "CustomerName"=>"Jack"), array("CustomerName"=>"Cindy") ) - ...WHERE (CustomerId=5 AND CustomerName='Jack') OR (CustomerName='Cindy')
	 * @param array $array_order The "ORDER BY" clause. Ex: array("CustomerId"=>"asc", "CustomerName"=>"desc") ... ORDER BY CustomerId ASC, CustomerName DESC
	 * @param string $limit With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set. With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1).
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of selected rows
	 * @see DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 */
	public function Select($array_select_fields, $table, $array_where='', $array_order='', $limit = '', $options=null) {
		if(!is_array($array_select_fields) || count($array_select_fields)==0){
			$array_select_fields = array("*"); //default to "SELECT * ..."
		}
		if(!is_array($array_where)) {
			$array_where = array();
		}
		if(!is_array($array_order)) {
			$array_order = null;
		}

		if($options['distinct']){
			if(count($array_select_fields) > 1) throw new Exception("Cannot SELECT DISTINCT on more than 1 column.");
			$distinct = "DISTINCT ";
		}
		$select_items = $this->ArrayToCSV($table, $array_select_fields, ($options['add-dot-notation'] || $options['add-select-fields-dot-notation']), ($options['add-column-quotes'] || $options['add-select-fields-column-quotes']) );
		$order_clause = $this->OrderArrayToString($table, $array_order, ($options['add-dot-notation'] || $options['add-order-clause-dot-notation']), ($options['add-column-quotes'] || $options['add-order-clause-column-quotes']));
		$where_clause = $this->GenerateWhereClause($table, $array_where, ($options['add-dot-notation'] || $options['add-where-clause-dot-notation']), ($options['add-column-quotes'] || $options['add-where-clause-column-quotes']), array(
			//extended options
			'quote-numerics'=>$options['quote-numerics'],
		));

		if($limit){ //should handle $limit==0, which is invalid.
			$validLimit = preg_match('/^\d+(,[1-9]+\d*)?$/',$limit); //matches "10" or "100,10". will incorrectly match "0", but "0,10" is correct. the if statement above should handle the "0" case though
			if($validLimit) $limit = "LIMIT ".$limit;
		}
		if(!$validLimit) $limit = null;

		$sql_select = "SELECT ".$distinct.$select_items." FROM ".$this->GetDotNotation($table)." ".$where_clause." ".$order_clause." ".$limit;

		$this->Query($sql_select, $options['force-select-db']);

		return $this->AffectedRows();
	}

	/**
	 * Executes an INSERT statement on the currently selected database
	 * <code>
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * );
	 * </code>
	 * @param string $table The table name. ex: "Customer"
	 * @param array $field_val_array Assoc array of columnName=value of values to insert. Ex: array('Name'=>'Jack', 'EmailAddress'=>'jack@frost.com') ... INSERT INTO Customer (Name, EmailAddress) VALUES ('Jack', 'jack@frost.com')
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of inserted rows (1 or 0)
	 * @see DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 */
	public function Insert($table, $field_val_array, $options=null) {
		$sql_fields = "";
		$sql_values = null;
		foreach ($field_val_array as $field => $value) {
			// Compile SQL fields
			if ($sql_fields) $sql_fields .= ", ";

			if($options['add-dot-notation']) $sql_fields .= $this->GetDotNotation($table, $field, $options['add-column-quotes']);
			else if($options['add-column-quotes']) $sql_fields .= "`$field`";
			else $sql_fields .= $field;

			// Compile SQL values
			if ($sql_values!==null) {$sql_values .= ", ";}

			if ($value == "now()") {$sql_values .= $value;}
			else if ($value === null) {$sql_values .= "null";}
			else if(is_numeric($value)) {$sql_values .= $value;} //no quotes around a numeric
			else {$sql_values .= "'".$this->EscapeString($value)."'";}
		}
		$sql_insert = "INSERT INTO ".$this->GetDotNotation($table)." (".$sql_fields.") VALUES (".$sql_values.")";

		$this->Query($sql_insert, $options['force-select-db']);

		return $this->AffectedRows();
	}

	/**
	 * Executes an UPDATE statement on the currently selected database
	 * <code>
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * 	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...)
	 * );
	 * </code>
	 * @param string $table The table name. Ex: "Customers"
	 * @param array $field_val_array Assoc array of columnName=value of data to update. ex: array('col1'=>5, 'col2'=>'foo') ... UPDATE $table SET col1=5, col2='foo' ...
	 * @param array $array_where The where clause. ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	 * @param string $limit The amount of rows to limit the update to (if any) from the beginning of the result set
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of updated rows
	 * @see DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 */
	public function Update($table, $field_val_array, $array_where='', $limit = '', $options=null) {
		$arg = "";
		foreach ($field_val_array as $field => $value) {
			if ($arg) {$arg .= ", ";}

			if($options['add-dot-notation']) $arg .= $this->GetDotNotation($table, $field, $options['add-column-quotes']);
			else if($options['add-column-quotes']) $arg .= "`$field`";
			else $arg .= $field;

			$arg .= '=';

			if ($value == "now()") {$arg .= $value;}
			else if ($value === null) {$arg .= "null";}
			else if(is_numeric($value)) {$arg .= $value;} //no quotes around a numeric
			else {$arg .= "'".$this->EscapeString($value)."'";}
		}

		$where_clause = $this->GenerateWhereClause($table, $array_where, $options['add-dot-notation'], $options['add-column-quotes'], array(
			//extended options
			'quote-numerics'=>$options['quote-numerics'],
		));

		$limit = (int)$limit;
		if($limit > 0) $limit = "LIMIT ".$limit;
		else $limit = null;

		$sql_update = "UPDATE ".$this->GetDotNotation($table)." SET ".$arg." ".$where_clause." ".$limit;

		$this->Query($sql_update, $options['force-select-db']);

		return $this->AffectedRows();
	}

	/**
	 * Executes a DELETE statement on the currently selected database
	 * <code>
	 * $options = array(
	 * 	'add-column-quotes' => false, //if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	 * 	'add-dot-notation' => false, //if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	 * 	'force-select-db' => false, //if true, will call mysql_select_db() with the database passed to this class
	 * 	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...)
	 * );
	 * </code>
	 * @param string The table name. Ex: "Customer"
	 * @param array $array_where The WHERE clause. Ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	 * @param string $limit The amount of rows to limit the delete to (if any) from the beginning of the result set
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return int Returns the number of selected rows
	 * @see DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 */
	public function Delete($table, $array_where='', $limit='', $options=null) {
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
	 * <code>
	 * $options = array(
	 * 	'force-select-db' => false, //set to true if writing a query without dot notation (database.table.field) AND your app uses multiple databases with 1 connection (ie not using the 'new_link' flag on any database connection)
	 * 	'skip-select-db' => false, //if true, will skip any call to mysql_select_db. good for creating databases and etc management
	 * );
	 * </code>
	 * @param string $query The query to execute. Ex: "SELECT * FROM Customers WHERE CustomerId=1"
	 * @param array $options An array of key=>value pairs. See description above.
	 * @return mixed Returns the result of mysql_query() - For SELECT, SHOW, DESCRIBE, EXPLAIN and other statements returning resultset, mysql_query() returns a resource. For other type of SQL statements, INSERT, UPDATE, DELETE, DROP, etc, mysql_query() returns TRUE  on success or FALSE on error.
	 * @see DbManager_MySQL::NumRows()
	 * @see DbManager_MySQL::AffectedRows()
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 */
	public function Query($query, $options=null) {
		if($GLOBALS['SQL_DEBUG_MODE']){
			echo "DbManager->Query - ".$query."<br>\n";	// Troubleshoot Query command
		}

		if(!$this->_isConnected){
			$this->OpenConnection(array(
				'skip-select-db' => $options['skip-select-db']
			)); //lazy connect
		}
		else if($options['force-select-db']){ //the reason for this else: if we just connected, database was just selected. no need to select it again here.
			$this->SelectDatabase();
		}

		$this->_lastResult = mysql_query($query, $this->_connection);

		if (!$this->_lastResult) {
			throw new Exception($this->Error()." SQL: ".$query);
		}
		return $this->_lastResult;
	}

	/**
	 * Places the last query results into an array of ASSOC arrays, and returns it. Each row is an index in the array. Returns an empty array if there are no results.
	 * Example:
	 * <code>
	 * Function returns an array similar to:
	 * array(
	 * 	0 => array(
	 * 		"CustomerId" => "4",
	 * 		"EmailAddress" => "jack@frost.com",
	 * 		"Name" => "Jack",
	 *  ),
	 *  1 => array(
	 *  	"CustomerId" => "6",
	 * 		"EmailAddress" => "queen@muppets.com",
	 * 		"Name" => "Miss Piggy",
	 *  ),
	 *  ...
	 * )
	 * </code> 
	 * @return array An array of ASSOC arrays, and returns it. Each row is an index in the array. Returns an empty array if there are no results.
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray() 
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows()
	 */
	public function FetchAssocList() {
		$data = array(); //so an empty array is returned if no rows are set. avoids "Warning: Invalid argument supplied for foreach()"
   		for ($i = 0; $i < $this->NumRows(); $i++) {
       		$data[$i] = $this->FetchAssoc();
   		}
   		return $data;
	}

	/**
	 * Places the last query results into an array of NON-ASSOC arrays, and returns the array. Returns an empty array if there are no results.
	 * Example:
	 * <code>
	 * Function returns an array similar to:
	 * array(
	 * 	0 => array(
	 * 		0 => "4",
	 * 		1 => "jack@frost.com",
	 * 		2 => "Jack",
	 *  ),
	 *  1 => array(
	 *  	0 => "6",
	 * 		1 => "queen@muppets.com",
	 * 		2 => "Miss Piggy",
	 *  ),
	 *  ...
	 * );
	 * </code> 
	 * @return array An array of NON-ASSOC arrays, and returns the array. Returns an empty array if there are no results.
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::FetchArray()
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows()
	 */
	public function FetchArrayList() {
		$data = array(); //so an empty array is returned if no rows are set. avoids "Warning: Invalid argument supplied for foreach()"
   		for ($i = 0; $i < $this->NumRows(); $i++) {
       		$data[$i] = $this->FetchArray();
   		}
   		return $data;
	}

	/**
	 * Returns an ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * Example:
	 * if( (row = $dbManager->FetchAssoc()) ){ $row['id']...
	 * <code>
	 * Function returns an array similar to:
	 * array(
	 * 		"CustomerId" => "4",
	 * 		"EmailAddress" => "jack@frost.com",
	 * 		"Name" => "Jack",
	 * );
	 * </code>
	 * @return array An ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchArray()
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows()
	 */
	public function FetchAssoc() {
		$array = mysql_fetch_assoc($this->_lastResult);
		return $array;
	}

	/**
	 * Returns a NON-ASSOC array of the last query results. Array keys are numeric. False is returned if there are no more results.
	 * Example:
	 * <code>
	 * Function returns an array similar to:
	 * array(
	 * 		0 => "4",
	 * 		1 => "jack@frost.com",
	 * 		2 => "Jack",
	 * );
	 * </code>
	 * @return array An ASSOC array of the last query results. Column names are the array keys. False is returned if there are no more results.
	 * @see DbManager_MySQL::FetchAssocList()
	 * @see DbManager_MySQL::FetchArrayList()
	 * @see DbManager_MySQL::FetchAssoc()
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete()
	 * @see DbManager_MySQL::NumRows()
	 */
	public function FetchArray() {
		$array = mysql_fetch_array($this->_lastResult);
		return $array;
	}

	/**
	 * Returns the number of rows returned from the last query (not affected rows!). This command is only valid for statements like SELECT or SHOW that return an actual result set. To retrieve the number of rows affected by a INSERT, UPDATE, REPLACE or DELETE query, use AffectedRows()
	 * @return int Returns the number of rows returned from the last query
	 * @see DbManager_MySQL::AffectedRows();
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete()
	 */
	public function NumRows() {
		$rows = mysql_num_rows($this->_lastResult);
		return $rows;
	}

	/**
	 * Returns the error text from the last MySQL function
	 * @return string Returns the error text from the last MySQL function, or empty string ("")
	 */
	public function Error() {
		if($this->_isConnected) return mysql_error($this->_connection);
		else return false;
	}

	/**
	 * Returns the ID generated for an AUTO_INCREMENT column by the previous query
	 * @return int The ID generated for an AUTO_INCREMENT column by the previous query, or FALSE
	 */
	public function InsertId() {
		if($this->_isConnected) return mysql_insert_id($this->_connection);
		else return false;
	}

	/**
	 * Returns the number of affected rows by the last INSERT, UPDATE, REPLACE or DELETE query
	 * @return int Returns the number of affected rows by the last INSERT, UPDATE, REPLACE or DELETE query
	 * @see DbManager_MySQL::NumRows();
	 * @see DbManager_MySQL::Select()
	 * @see DbManager_MySQL::Insert()
	 * @see DbManager_MySQL::Update()
	 * @see DbManager_MySQL::Delete() 
	 */
	public function AffectedRows() {
		if($this->_isConnected) return mysql_affected_rows($this->_connection);
		else return false;
	}

	/**
	 * Runs stripslashes() and mysql_real_escape_string() on the given $string and returns it.
	 * @param string $string The string to run stripslashes() and mysql_real_escape_string() on.
	 * @return string Runs stripslashes() and mysql_real_escape_string() on the given $string and returns it.
	 */
	public function EscapeString($string) {
		if ($string===null) return null;

		// Stripslashes
		//if (get_magic_quotes_gpc()) { //DEPRECIATED IN PHP 5.3.x ALWAYS RETURNED TRUE FOR US ANYWAY
		   $string = stripslashes($string);
		//}
		// Quote if $string is not numeric, OR if it is a numeric and is too big/small for MySQL to treat it as a number
		if (!is_numeric($string)
				|| ($string < (0-$this->PHP_INT_MAX_HALF) || $string > $this->PHP_INT_MAX_HALF)) {
			if(!$this->_isConnected) $this->OpenConnection();
			$string = mysql_real_escape_string($string,$this->_connection);
		}
		return $string;
	}

	/**
	 * Converts a sort array of structure array("id"=>"asc", "col1"=>"desc") into a string like  "ORDER BY id ASC, col1 DESC"
	 * @param string $table
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
			if($dotNotation) $colName = $this->GetDotNotation($table, $colName, $options['add-column-quotes']);
			else if($addColumnQuotes) $colName = "`$colName`";
			//else $colName = $colName;

			$direction = strtoupper($direction);
			if($direction != "ASC" && $direction != "DESC") throw new Exception("Sort direction must be 'ASC' or 'DESC', not '$direction' for column: $colName");
			if($firstFound) $sortOrder .= ", ";
			$direction = strtoupper($direction);
			$sortOrder .= "$colName $direction";
			$firstFound = true;
		}
		return $sortOrder;
	}

	/**
	 * Converts items in $array into a comma separated value string and returns that string
	 * @param string $table
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
	 * @param string $table
	 * @param string $column
	 * @param bool $addColumnQuotes
	 * @return string Returns a string of appropriate dot-notation/column quites for the given $table and $column
	 */
	private function GetDotNotation($table, $column=null, $addColumnQuotes=false){
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
	 * <code>
	 *  $options = array(
	 *  	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...) 
	 *  );
	 * </code>
	 * @param string $table
	 * @param array $array_where
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param array $options See description above
	 * @return string
	 */
	protected function GenerateWhereClause($table, $array_where, $dotNotation=false, $addColumnQuotes=false, $options=array()) {
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
	 * @param string $table
	 * @param string $key
	 * @param mixed $val
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param string $column
	 * @param string $condition
	 * @param string $operator
	 * @param array $options
	 * @param bool $first Ignore this. only used for recursion
	 * @return string
	 * @see DbManager_MySQL::GenerateWhereClause()
	 */
	private function GenerateWhereRecursive($table, $key, $val, $dotNotation=false, $addColumnQuotes=false, $column='', $condition='=', $operator='AND', $options=array(), $first=true){
		$key = trim($key);
		$keyIsKeyword = false; //if the key is not a keyword and not numeric, it is assumed to be the column name
	 
		if( ($newCondition = $this->IsCondition($key)) ){ //check if key is a condition
			$condition = $newCondition;
			$keyIsKeyword = true;
		}
		else if( ($newOperator = $this->IsOperator($key)) ){ //check if key is an operator
			$operator = $newOperator;
			$keyIsKeyword = true;
		}
		else if(!is_numeric($key)){ 		//if the key is not a keyword and not numeric, it is assumed to be the column name
			$column = $key;
		}
		
		$ret = ""; //the value returned
		//$val can either be a scalar or an array
		if( is_array($val) ){ //value is an array, recurse.
			foreach($val as $nextKey=>$nextVal){
				$thisWhere = $this->GenerateWhereRecursive($table, $nextKey, $nextVal, $dotNotation, $addColumnQuotes, $column, $condition, $operator, $options, false);
				
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
	 * @param string $table
	 * @param string $column
	 * @param string $condition
	 * @param string $val
	 * @param bool $dotNotation
	 * @param bool $addColumnQuotes
	 * @param array $options
	 * @return string
	 * @see DbManager_MySQL::GenerateWhereRecursive()
	 */
	private function GenerateWhereSingle($table, $column, $condition, $val, $dotNotation, $addColumnQuotes, $options=array()){
		$ret = ""; //the value returned
		$column = trim($column,"` "); //clean up field name
		
		if(!$column) throw new Exception("No column has been defined");

		if($dotNotation) $column = $this->GetDotNotation($table,$column,$addColumnQuotes);
		else if($addColumnQuotes) $column = "`$column`";
		//else $column = $column;

		if ($val === null) {$ret = $column." is null";} //'is null' for null
		else if(is_numeric($val) && !$options['quote-numerics']){
			//no quotes around a numeric unless the value is HUGE and falls outside normal values for SIGNED integers... mysql does weird conversion things if the column is a varchar and these large numbers are used... it wont lookup these big numbers correctly unless quotes are used
			if($val < (0-$this->PHP_INT_MAX_HALF) || $val > $this->PHP_INT_MAX_HALF){
				$ret = "$column $condition '".$this->EscapeString($val)."'";
			}
			else $ret = "$column $condition $val";
		}
		else  {$ret = "$column $condition '".$this->EscapeString($val)."'";} //quotes around strings
		
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
	 * @see DbManager_MySQL::IsOperator()
	 * @see DbManager_MySQL::IsCondition()
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
	 * @see DbManager_MySQL::IsKeyword()
	 * @see DbManager_MySQL::IsCondition()
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
	 * @see DbManager_MySQL::IsOperator()
	 * @see DbManager_MySQL::IsKeyword()
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


	/*************** DATABASE MANAGEMENT ***************/
	/**
	 * Returns true if the given $databaseName exists, false otherwise.
	 * @param string $databaseName The name of the database to check for existence
	 * @return bool true if the given $databaseName exists, false otherwise.
	 */
	public function DatabaseExists($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');

		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".$this->EscapeString($databaseName)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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
	 * Returns true if the database was created, false if it already exists or was not created for some reason.
	 * @param string $databaseName The name of the database to create 
	 * @return int Returns true if the database was created or already exists, false if it not created for some reason
	 */
	public function CreateDatabase($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');
		
		if($this->DatabaseExists($databaseName)) return true; //database already exists

		$sql = "CREATE DATABASE `".$this->EscapeString($databaseName)."`";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
		
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

		$sql = "DROP DATABASE `".$this->EscapeString($databaseName)."`";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
		
		if($this->DatabaseExists($databaseName)) return false; //database still exists
		else return true; //database doesn't exist anymore
	}
	/**
	 * Copies all structure and data from $sourceDatabaseName to $destDatabaseName. $destDatabaseName will be created if it is not already
	 * The database user running this command will need appropriate privileges to both databases and/or the ability to create new databases
	 * <code>
	 * $options = array(
	 * 	'create-tables' => true,
	 * 	'create-database' => true,
	 * 	'copy-data' => true,
	 * 	'drop-existing-tables' => false,
	 * 	'drop-existing-database' => false, 
	 * )
	 * </code> 
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

		$sql = "SELECT `User` FROM `mysql`.`user` WHERE `User`='".$this->EscapeString($username)."' AND `Host`='".$this->EscapeString($host)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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

		$sql = "CREATE USER '".$this->EscapeString($username)."'@'".$this->EscapeString($host)."' IDENTIFIED BY '".$this->EscapeString($password)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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

		$sql = "DROP USER '".$this->EscapeString($username)."'@'".$this->EscapeString($host)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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

		$sql = "GRANT ALL PRIVILEGES ON `".$this->EscapeString($databaseName)."`.* TO '".$this->EscapeString($username)."'@'".$this->EscapeString($host)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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

		$sql = "GRANT FILE ON *.* TO '".$this->EscapeString($username)."'@'".$this->EscapeString($host)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));
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

		$sql = "REVOKE ALL PRIVILEGES ON `".$this->EscapeString($databaseName)."`.* FROM '".$this->EscapeString($username)."'@'".$this->EscapeString($host)."'";
		$this->Query($sql, array(
			"skip-select-db" => true
		));

		return $this->AffectedRows();
	}
	
	//********************* password management ********************************
	/**
	 * A basic encrypt function for storing the password locally in this class
	 * @param string $decrypt
	 * @param string $key
	 */
	private function Encrypt($encrypt,$key) {
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
		$passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt, MCRYPT_MODE_ECB, $iv);
		$encode = base64_encode($passcrypt);
		return trim($encode);
	}

	/**
	 * A basic decrypt function for storing the password locally in this class
	 * @param string $decrypt
	 * @param string $key
	 */
	private function Decrypt($decrypt,$key) {
		$decoded = base64_decode($decrypt);
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
		$decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_ECB, $iv);
		return trim($decrypted);
	}

}
?>