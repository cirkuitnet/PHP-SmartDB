<?php
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

	private $_server;
	private $_user;
	private $_password; //encrypted. use $this->GetPassword();
	private $_connectParams;
	
	private $PHP_INT_MAX; //the PHP_INT_MAX constant doesnt work in sourceguardian
	private $PHP_INT_MAX_HALF;

	// Constructor: pass connection values, set the db settings. note: $extra_params is an ARRAY
	//$extra_params = array(
	//	'new_link'=>false,	//this is the 4th parameters for mysql_connect. see mysql_connect documentation
	//	'client_flags'=>"",	//this is the 5th parameters for mysql_connect. see mysql_connect documentation
	//	'charset'=>'',		//if set, OpenConnection does a mysql_set_charset() with the given option (ie 'utf8')
	//)
	public function __construct($server, $user, $password, $databaseName=null, $extra_params=null) {
		if(!$server || !$user || !$password) throw new Exception("Not all connection variables are set.");
		$this->_server = $server;
		$this->_user = $user;
		$this->_databaseName = $databaseName;
		$this->_connectParams = $extra_params;
		
		$this->SetPassword($password);
		
		$this->PHP_INT_MAX = self::PHP_INT_MAX();
		$this->PHP_INT_MAX_HALF = $this->PHP_INT_MAX/2;
	}
	
	//encrypts the password when stored locally
	private function SetPassword($password){
		$this->_password = $this->Encrypt($password, $this->_server.$this->_user); //$this->_server.$this->_user will be our encrypt key
	}
	
	//encrypts the password when stored locally
	private function GetPassword(){
		return $this->Decrypt($this->_password, $this->_server.$this->_user); //$this->_server.$this->_user will be our encrypt key
	}

	public function OpenConnection(){
		if($this->_isConnected) return true;
		try {
			$this->_connection = mysql_connect($this->_server, $this->_user, $this->GetPassword(), $this->_connectParams['new_link'], $this->_connectParams['client_flags']);
			if(!$this->_connection) throw new Exception("Couldn't connect to the server. Please check your settings.");
			
			//see if a charset is set (ie "utf8");
			if($this->_connectParams['charset']){
				mysql_set_charset($this->_connectParams['charset'], $this->_connection);
			}

			if(!$this->_databaseName) throw new Exception("Database Name is not set.");
			$dbSelected = mysql_select_db($this->_databaseName, $this->_connection);
			if(!$dbSelected) throw new Exception("Couldn't select database '".$this->_databaseName."'. Please make sure it exists.");

			$this->_isConnected = true;
			return true;
		}
		catch (Exception $e){
			$this->_isConnected = false;
			//exception show's the password passed through the constructor. only use for debugging
			throw new Exception("Bad arguments for DbManager constructor. Server: '$server', User: '$user'. Msg: ".$e->getMessage());
			//die("Bad arguments for DbManager constructor. Msg: ".$e->getMessage());
		}
	}

	//$databaseName - the database to make active
	//$options: (array of key=>value pairs)
	//	['force-select-db'] - default: false - if true, will immediately call mysql_select_db() with the database passed to this class
	public function SetDatabaseName($databaseName, $options=null){
		$this->_databaseName = $databaseName;
		if($this->_isConnected && $options['force-select-db'] && $this->_databaseName){
			$dbSelected = mysql_select_db($this->_databaseName, $this->_connection);
			if(!$dbSelected) throw new Exception("Couldn't select database '".$this->_databaseName."'. Ensure that it exists and permissions are set properly.");
		}
	}

	public function GetDatabaseName(){
		return $this->_databaseName;
	}

	//$array_select_fields - the fields to select. ex: array("id", "col1", "col2")
	//$table - the table name. ex: "Users"
	//$array_where - the where clause. ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	//$array_order - order by clause. ex: array("id"=>"asc", "col1"=>"desc") ... ORDER BY id ASC, col1 DESC
	//$limit - With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	//       - With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	//$options: (array of key=>value pairs)
	//	['distinct'] - default: false - if true, does a SELECT DISTINCT for the select. Note: there must only be 1 select field, otherwise an exception is thrown
	//
	//	['add-column-quotes'] - default: false - if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	//	['add-select-fields-column-quotes'] - default: false - if true, automatically adds `quotes` around the `column names` in select fields
	//	['add-where-clause-column-quotes'] - default: false - if true, automatically adds `quotes` around the `column names` in the where clause
	//	['add-order-clause-column-quotes'] - default: false - if true, automatically adds `quotes` around the `column names` in the order clause
	//
	//	['add-dot-notation'] - default: false - if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	//	['add-select-fields-dot-notation'] - default: false - if true, automatically adds dot.notation before column names in select fields
	//	['add-where-clause-dot-notation'] - default: false - if true, automatically adds dot.notation before column names in the where clause
	//	['add-order-clause-dot-notation'] - default: false - if true, automatically adds dot.notation before column names in the order clause
	//
	//	['quote-numerics'] - default: false - if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...)
	//
	//	['force-select-db'] - default: false - if true, will call mysql_select_db() with the database passed to this class
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

	//$table - the table name. ex: "Users"
	//$field_val_array - assoc array of columnName=value of values to insert. ex: array('col1'=>5, 'col2'=>'foo') ... INSERT INTO $table (col1, col2) VALUES (5, 'foo')
	//$options: (array of key=>value pairs)
	//	['add-column-quotes'] - default: false - if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	//	['add-dot-notation'] - default: false - if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	//	['force-select-db'] - default: false - if true, will call mysql_select_db() with the database passed to this class
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

	//$table - the table name. ex: "Users"
	//$field_val_array - assoc array of columnName=value of data to update. ex: array('col1'=>5, 'col2'=>'foo') ... UPDATE $table SET col1=5, col2='foo' ...
	//$array_where - the where clause. ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	//$limit - the amount of rows to limit the update to (if any) from the beginning of the result set
	//$options: (array of key=>value pairs)
	//	['add-column-quotes'] - default: false - if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	//	['add-dot-notation'] - default: false - if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	//	['force-select-db'] - default: false - if true, will call mysql_select_db() with the database passed to this class
	//	['quote-numerics'] - default: false - if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...)
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

	//$table - the table name. ex: "Users"
	//$array_where - the where clause. ex: array( array("id"=>5, "col1"=>"foo"), array("col2"=>"bar") ) - ...WHERE (id=5 AND col1='foo') OR (col2='bar')
	//$limit - the amount of rows to limit the delete to (if any) from the beginning of the result set
	//$options: (array of key=>value pairs)
	//	['add-column-quotes'] - default: false - if true, overwrites any other 'column-quotes' options (below) and will add column quotes to everything
	//	['add-dot-notation'] - default: false - if true, overwrites any other 'dot-notation' options (below) and will add dot notation to everything
	//	['force-select-db'] - default: false - if true, will call mysql_select_db() with the database passed to this class
	//	['quote-numerics'] - default: false - if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...)
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

	// Query(): accept the query then run it
	//$options: (array of key=>value pairs)
	//	['force-select-db'] - default: false - set to true if writing a query without dot notation (database.table.field) AND your app uses multiple databases with 1 connection (ie not using the 'new_link' flag on any database connection)
	public function Query($query, $options=null) {
		if($GLOBALS['SQL_DEBUG_MODE']){
			echo "DbManager->Query - ".$query."<br>\n";	// Troubleshoot Query command
		}

		if(!$this->_isConnected){
			$this->OpenConnection(); //lazy connect
		}
		else if($options['force-select-db']){ //the reason for this else: if we just connected, database was just selected. no need to select it again here.
			$dbSelected = mysql_select_db($this->_databaseName, $this->_connection);
			if(!$dbSelected) throw new Exception("Couldn't select database '".$this->_databaseName."'. Please make sure it exists.");
		}

		$this->_lastResult = mysql_query($query, $this->_connection);

		if (!$this->_lastResult) {
			throw new Exception($this->Error()." SQL: ".$query);
		}
		return $this->_lastResult;
	}

	// FetchArrayList(): place the query results into an array
	public function FetchAssocList() {
   		for ($i = 0; $i < $this->NumRows(); $i++) {
       		$data[$i] = $this->FetchAssoc();
   		}
   		return $data;
	}

	// FetchArrayList(): place the query results into an array
	public function FetchArrayList() {
   		for ($i = 0; $i < $this->NumRows(); $i++) {
       		$data[$i] = $this->FetchArray();
   		}
   		return $data;
	}

	// FetchAssoc(): place the query results into an array
	public function FetchAssoc() {
		$array = mysql_fetch_assoc($this->_lastResult);
		return $array;
	}

	// FetchArray(): place the query results into an array
	public function FetchArray() {
		$array = mysql_fetch_array($this->_lastResult);
		return $array;
	}

	// NumRows(): return number of rows from a query
	public function NumRows() {
		$rows = mysql_num_rows($this->_lastResult);
		return $rows;
	}

	// Error() returns SQL error
	public function Error() {
		if($this->_isConnected) return mysql_error($this->_connection);
		else return false;
	}

	// InsertId() returns id from last insert
	public function InsertId() {
		if($this->_isConnected) return mysql_insert_id($this->_connection);
		else return false;
	}

	public function AffectedRows() {
		if($this->_isConnected) return mysql_affected_rows($this->_connection);
		else return false;
	}

	// EscapeString($string)
	public function EscapeString($string) {
		if ($string===null) return null;

		// Stripslashes
		//if (get_magic_quotes_gpc()) { //DEPRECIATED IN PHP 5.3.x ALWAYS RETURNED TRUE FOR US ANYWAY
		   $string = stripslashes($string);
		//}
		// Quote if not a number or a numeric string
		if (!is_numeric($string)) {
			if(!$this->_isConnected) $this->OpenConnection();
			$string = mysql_real_escape_string($string,$this->_connection);
		}
		return $string;
	}

	// CloseConnection(): close MySQL connection
	public function CloseConnection() {
		if($this->_isConnected){
			mysql_close($this->_connection);
			$this->_isConnected = false;
			return true;
		}
		return false;
	}

	//turns a sort array of structure array("id"=>"asc", "col1"=>"desc") into a string like  "ORDER BY id ASC, col1 DESC"
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

	//turns items in array into a comma separated value string
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
	 *  GenerateWhereClause($array_where) will prepare the where clause for an sql statement
	 *  $options = array(
	 *  	'quote-numerics' => false, //if true, numerics will always be quoted in the where clause (ie ...WHERE `price`='123'... instead of ... WHERE `price`=123...) 
	 *  )
	 */
	protected function GenerateWhereClause($table, $array_where, $dotNotation=false, $addColumnQuotes=false, $options=array()) {
		if( !is_array($array_where) || count($array_where)<=0 ) return '';
	
		$where_clause = '';
		foreach($array_where as $key=>$val){
			$thisWhere = $this->GenerateWhereRecursive($table, $key, $val, $dotNotation, $addColumnQuotes, '', '=', 'AND', $options);
			
			//need to add an " OR " in between clauses
			if($thisWhere){
				if($where_clause) $where_clause .= " OR ".$thisWhere;
				else $where_clause = $thisWhere;
			} 
		}
	
		if (!$where_clause) return '';
		else return "WHERE ".$where_clause;
	}

	
	/**
	 * @param $first - ignore this. only used for recursion
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
				
				//need to add an " OR " in between clauses
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
	
	//sourceguardian doesnt support PHP_INT_MAX
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
	 * @param string $keyword
	 */
	public function IsKeyword($keyword){
		if( ($operator = $this->IsOperator($keyword)) ) return $operator; //match operator
		if( ($condition = $this->IsCondition($keyword)) ) return $condition; //match condition
		return false; //no match
	}
	
	/**
	 * Returns the proper operator (AND or OR) if the $keyword is an operator (ie AND or OR). Otherwise returns false.
	 * @param string $keyword
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
	 * @param string $keyword
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
	public function DatabaseExists($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');

		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$databaseName'";
		$this->Query($sql);
		$results = $this->FetchAssocList();
		return (count($results)>0);
	}
	public function TableExists($databaseName,$tableName) {
		if (!$databaseName) throw new Exception('$databaseName not set');

		$sql = "SELECT count(*) FROM information_schema.tables WHERE table_schema = '".$databaseName."' AND table_name = '".$tableName."'";
		$this->Query($sql);
		$results = $this->FetchArray();
		return ($results[0]>0);
	}
	public function CreateDatabase($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');

		$sql = "CREATE DATABASE IF NOT EXISTS `$databaseName`";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function DropDatabase($databaseName){
		if(!$databaseName) throw new Exception('$databaseName not set');

		$sql = "DROP DATABASE IF EXISTS `$databaseName`";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function UserExists($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "SELECT `User` FROM `mysql`.`user` WHERE `User`='$username' AND `Host`='$host'";
		$this->Query($sql);
		$results = $this->FetchAssocList();
		return (count($results)>0);
	}
	public function CreateUser($username, $password, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$password) throw new Exception('$password not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "CREATE USER '$username'@'$host' IDENTIFIED BY '$password'";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function DropUser($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "DROP USER '$username'@'$host'";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function GrantUserPermissions($databaseName, $username, $host="localhost"){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "GRANT ALL PRIVILEGES ON `$databaseName`.* TO '$username'@'$host'";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function GrantGlobalFilePermissions($username, $host="localhost"){
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "GRANT FILE ON *.* TO '$username'@'$host'";
		$this->Query($sql);
		return $this->AffectedRows();
	}
	public function RevokeUserPermissions($databaseName, $username, $host="localhost"){
		if(!$databaseName) throw new Exception('$databaseName not set');
		if(!$username) throw new Exception('$username not set');
		if(!$host) throw new Exception('$host not set');

		$sql = "REVOKE ALL PRIVILEGES ON `$databaseName`.* FROM '$username'@'$host'";
		$this->Query($sql);

		return $this->AffectedRows();
	}
	
	//********************* password management ********************************
	//Encrypt Function
	private function Encrypt($encrypt,$key) {
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
		$passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt, MCRYPT_MODE_ECB, $iv);
		$encode = base64_encode($passcrypt);
		return trim($encode);
	}

	//Decrypt Function
	private function Decrypt($decrypt,$key) {
		$decoded = base64_decode($decrypt);
		$iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
		$decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_ECB, $iv);
		return trim($decrypted);
	}

}
?>