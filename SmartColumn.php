<?
/*!
 * PHP SmartDb
 * http://www.phpsmartdb.com/
 *
 * Copyright 2011, Cirkuit Networks
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://www.phpsmartdb.com/license
 */
/**
 * Columns contain all of the column properties and no user/db data. It's strictly structural. Data is contained in Cells
 */
/**
 * Columns contain all of the column properties and no user/db data. It's strictly structural. Data is contained in Cells
 * @package SmartDatabase
 */
class SmartColumn{
	
	/////////////////////////////// SERIALIZATION - At top so we don't forget to update these when we add new vars //////////////////////////
		/**
		 * Specify all variables that should be serialized
		 * @ignore
		 */
		public function __sleep(){
			return array(
				'Table',
				'ColumnName',
				'IsAlias',
				'DisplayName',
				'DataType',
				'Collation',
				'IsStringColumn',
				'IsDateColumn',
				'IsTimezoneColumn',
				'DefaultTimezone',
				'IsSerializedColumn',
				'IsASet',
				'PossibleValues',
				'MinSize',
				'MaxSize',
				'AllowGet',
				'AllowSet',
				'TrimAndStripTagsOnSet',
				'AllowLookup',
				'AllowGetAll',
				'DefaultValue',
				'Example',
				'IsUnique',
				'IsPrimaryKey',
				'IsAutoIncrement',
				'FulltextIndex',
				'NonuniqueIndex',
				'IndexPrefixLength',
				'DefaultFormType',
				'IsRequired',
				'IsRequiredMessage',
				'RegexCheck',
				'RegexFailMessage',
				'SortOrder',
				'_relations',
				'_aliases'
			);
		}
	//////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * @var SmartTable The Table that contains this Column
	 */
	public $Table;

	/**
	 * @var string The name of the column
	 */
	public $ColumnName;
	/**
	 * @var bool True if the column is an alias for another column, false if this is an actual column
	 */
	public $IsAlias;
	/**
	 * @var string The column's friendly display name
	 */
	public $DisplayName;
	/**
	 * @var string The SQL data type of this column. Check XmlSchema.xsd for all possible values of this column.
	 * Last I checked, it was:
	 * 
	 * "char|varchar|text|mediumtext|longtext|blob|mediumblob|longblob|tinyint|smallint|mediumint|int|bigint|float|double|decimal|date|datetime|timestamp|time|binary|enum\((\s*'[ a-zA-Z0-9/_-]+'\s*,)*(\s*'[ a-zA-Z0-9/_-]+'\s*)\)|set\((\s*'[ a-zA-Z0-9/_-]+'\s*,)*(\s*'[ a-zA-Z0-9/_-]+'\s*)\)|array|object";
	 */
	public $DataType;
	/**
	 * @var string The SQL collation for this column (ie "latin1_swedish_ci", "utf8_general_ci", "utf8mb4_unicode_ci", "utf8mb4_general_ci", etc)
	 */
	public $Collation;
	/**
	 * @var bool True if this is a string/text column, false otherwise. Should be computed based of the $DataType at Database initialization
	 */
	public $IsStringColumn;
	/**
	 * @var bool True if this is a date column, false otherwise. Should be computed based of the $DataType at Database initialization
	 */
	public $IsDateColumn;
	/**
	 * @var bool True Mostly for internal use, if this is a date column that supports timezones, false otherwise. Should be computed based of the $DataType at Database initialization.
	 */
	public $IsTimezoneColumn;
	/**
	 * @var string If this is a date column and a TimeZone is set on this column (or the database-level), then ALL dates values stored in this column will be converted to UTC time for storing in the DB, then returned in the set timezone.
	 * Empty will use system time and won't touch dates (not recommended)
	 * There is a SmartDatabase level $DefaultTimezone, and also a SmartColumn $DefaultTimezone. If both values are set, the column's default will take precedence.
	 * NOTE: Use LONG timezone names here, not shortened values like "EST". You can use "date_default_timezone_get()" to get current system timezone. Ref: http://php.net/manual/en/timezones.php
	 */
	public $DefaultTimezone;
	/**
	 * @var bool True if this is a serialized or compressed column, false otherwise. Should be computed based of the $DataType
	 */
	public $IsSerializedColumn;
	/**
	 * @var bool True if this is a 'set' datatype column, false otherwise. Should be computed based of the $DataType
	 */
	public $IsASet;
	/**
	 * @var array An array of all possible values that this column accepts. null or empty array means no values are restricted. In theory, the column does NOT have to be an 'enum' or 'set' data type for this to work... it will work with any column data type... but why would you do that?
	 */
	public $PossibleValues;
	/**
	 * @var int The minimum number of characters allowed for this column. Can be null.
	 */
	public $MinSize;
	
	/**
	 * @var mixed The size of the column. For DataType=="decimal" columns, this is "PRECISION,SCALE" (ie "14,4"). For other columns, this represents the maximum number of characters allowed for this column. Can be null.
	 * @see SmartColumn::GetMaxLength() SmartColumn::GetMaxLength()
	 */
	public $MaxSize;
	/**
	 * Uses the column's $MaxSize property to determine what that maximum number of characters that are allowed in this column, regardless of data type (i.e. decimals are different than regular MaxSize)
	 * @return int Returns the maximum number of characters this column can hold. If no MaxSize is set, returns null.
	 * @see SmartColumn::$MaxSize SmartColumn::$MaxSize
	 */
	public function GetMaxLength(){
		$maxSize = $this->MaxSize;
		if(!$maxSize) return null;
		
		if($this->DataType === "decimal"){
			//MaxSize is something like (14,4) for decimals. first number is precision, second number is scale
			$sizeParts = explode(",", $maxSize);
			return ((int)$sizeParts[0] + 1); //add 1 for a decimal point
		}
		else return (int)$maxSize;
	}
	
	/**
	 * @var bool Default it true. Controls access to retrieving values from this column (defaults to true in XmlSchema.xsd if using an xml schema as your db structure)
	 */
	public $AllowGet = true;
	/**
	 * @var bool Default it true. Controls access to setting values from this column (defaults to true in XmlSchema.xsd if using an xml schema as your db structure)
	 */
	public $AllowSet = true;
	/**
	 * @var bool If true, will trim() and strip_tags() anytime a value is set in this column (defaults to false in XmlSchema.xsd if using an xml schema as your db structure)
	 */
	public $TrimAndStripTagsOnSet;
	/**
	 * @var bool Default it true. Controls access to looking up values from this column (defaults to true in XmlSchema.xsd if using an xml schema as your db structure)
	 * @todo implement this
	 */
	public $AllowLookup = true;
	/**
	 * @var bool Default it true. Controls access to getting all values from this column (defaults to false in XmlSchema.xsd if using an xml schema as your db structure (why false?))
	 * @todo implement this. and maybe default to true instead of false
	 */
	public $AllowGetAll = true;
	/**
	 * @var mixed The default value for this column. Can be null.
	 */
	public $DefaultValue;
	/**
	 * @var mixed The example value for this column. Can be null. Used for form object "placeholders"
	 */
	public $Example;
	/**
	 * @var bool True if this column is specified as UNIQUE
	 */
	public $IsUnique;
	/**
	 * @var bool True if this column is a primary key column
	 */
	public $IsPrimaryKey;
	/**
	 * @var bool True if this column is an auto-increment column
	 */
	public $IsAutoIncrement;
	/**
	 * @var bool True if this column is specified as a fulltext index for searching
	 */
	public $FulltextIndex;
	/**
	 * @var bool True if this column is specified as a non-unique index for searching
	 */
	public $NonuniqueIndex;
	/**
	 * @var int You can create an index that uses only the first N characters of the column. Indexing only a prefix of column values in this way can make the index file much smaller. When you index a BLOB or TEXT column, you must specify a prefix length for the index. Has max limits based on underlying db.
	 */
	public $IndexPrefixLength;
	/**
	 * @var string Default it "text". See XmlSchema.xsd for possible values (Last I checked, it was: "text", "password", "checkbox", "radio", "select", "textarea", "hidden", "colorpicker", "datepicker", "slider" ... the last 3, are for use with jQuery UI)
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 */
	public $DefaultFormType = "text";

	/**
	 * @var bool True if data is required for this column, false if null is an acceptable value
	 * @see SmartColumn::$IsRequiredMessage SmartColumn::$IsRequiredMessage
	 */
	public $IsRequired;
	/**
	 * @var string The error message to show if the field is required. If null, a decent default message will be used (i.e. "Phone Number" is required.).
	 * @see SmartColumn::$IsRequired SmartColumn::$IsRequired
	 */
	public $IsRequiredMessage;

	/**
	 * @var string Regex the value of this column must match against. Can be null. Uses preg_match() in PHP... automatically wraps it with slashes in PHP (i.e. preg_match('/'.$this->RegexCheck.'/i')) and javascript error checking (SmartFormValidation_jQueryValidate.php)... so don't wrap your own! Both php and javascript can only do case-insensitive matching at the moment, but both are supported with a single value here.
	 * @see SmartColumn::$RegexFailMessage SmartColumn::$RegexFailMessage
	 */
	public $RegexCheck;
	/**
	 * @var string The error message to show if the column value does not match against $RegexCheck. If null, a decent default message will be used (i.e. Invalid value for "Phone Number").
	 * @see SmartColumn::$RegexCheck SmartColumn::$RegexCheck
	 */
	public $RegexFailMessage;
	/**
	 * @todo Implement the SortOrder property for columns. 1-indexed to distinguish 0 from null/not set?
	 * @var int Mostly for internal/backend nitty-gritties. This value represents where this column's position is in comparison with other columns in the table. Used when Synchronizing with the database. 1 is the first column, 2 is the second, etc.
	 */
	public $SortOrder;

	/**
	 * Constructor
	 * @param string $columnName The name of the column. You should never really call this explicitly. Instead, do a $SmartDatabase['TABLE_NAME']['COLUMN_NAME'] to get the column (i.e. $db['Products']['Price']).
	 * @return SmartColumn
	 */
	public function __construct($columnName){
		if(!isset($columnName)) throw new Exception('$columnName must be set');

		$this->ColumnName = $columnName;
	}

/////////////////////////////// Column Relations ///////////////////////////////////
	/**
	 * @var array($key=tableName => $val=array($key=>columnName, $val=true);
	 * @ignore
	 */
	protected $_relations = array();

	/**
	 * Returns an array of all relations. array($key=tableName => $val=array($key=>columnName, $val=true);
	 * @return array All relations of this Column
	 */
	public function GetRelations(){
		return $this->_relations;
	}

	/**
	 * Adds a relation to this column. Returns true if success, false if the relation already exists.
	 * @param string $tableName The related table's name
	 * @param string $columnName The related column's name
	 * @param bool $addRelationOnRelatedColumn [optional] If true (default), the related column will be updated with the relation too. If false, you will need to add this relation to the related column by hand.
	 * @return bool true if success, false if the relation already existed.
	 * @see SmartColumn::RemoveRelation() SmartColumn::RemoveRelation()
	 * @see SmartColumn::HasRelation() SmartColumn::HasRelation()
	 */
	public function AddRelation($tableName, $columnName, $addRelationOnRelatedColumn=true){
		if(!$this->Table->Database->TableExists($tableName)) throw new Exception("Related table '$tableName' does not exist");
		if(!$this->Table->Database->GetTable($tableName)->ColumnExists($columnName)) throw new Exception("Related column '$columnName' does not exist on table $tableName");

		if(empty($this->_relations[$tableName][$columnName])){
			//set relation on $this column
			$this->_relations[$tableName][$columnName] = true;

			if($addRelationOnRelatedColumn){ //set relation on related column
				$this->Table->Database->GetTable($tableName)->GetColumn($columnName)->AddRelation($this->Table->TableName, $this->ColumnName, false);
			}
			return true;
		}
		else return false;
	}
	/**
	 * Removes a relation from this column. Returns true if success, false if the column was not found as a current relation.
	 * @param string $tableName The related table's name
	 * @param string $columnName The related column's name
	 * @param bool $removeRelationOnRelatedColumn [optional] If true (default), the related column will have this relation removed as well. If false, you will need to remove the relation on the related column by hand.
	 * @return bool true if success, false if the column was not found as a current relation.
	 * @see SmartColumn::AddRelation() SmartColumn::AddRelation()
	 * @see SmartColumn::HasRelation() SmartColumn::HasRelation()
	 */
	public function RemoveRelation($tableName, $columnName, $removeRelationOnRelatedColumn=true){
		if(!isset($tableName)) throw new Exception('$tableName must be set');
		if(!isset($columnName)) throw new Exception('$columnName must be set');

		if($this->_relations[$tableName][$columnName]){
			unset($this->_relations[$tableName][$columnName]);

			if($addRelationOnRelatedColumn){ //unset relation on related column
				$this->Table->Database->GetTable($tableName)->GetColumn($columnName)->RemoveRelation($this->Table->TableName, $this->ColumnName, false);
			}
			return true;
		}
		else return false;
	}

	/**
	 * Returns true if a relation exists between this column and the passed $tableName, $columnName. false otherwise
	 * @param string $tableName
	 * @param string $columnName
	 * @return bool true if a relation exists between this column and the passed $tableName, $columnName. false otherwise
	 * @see SmartColumn::AddRelation() SmartColumn::AddRelation()
	 * @see SmartColumn::RemoveRelation() SmartColumn::RemoveRelation()
	 */
	public function HasRelation($tableName, $columnName){
		if(!$this->Table->Database->TableExists($tableName)) throw new Exception("Related table '$tableName' does not exist");
		$relatedTable = $this->Table->Database->GetTable($tableName);
		if(!$relatedTable->ColumnExists($columnName)) throw new Exception("Related column '$columnName' does not exist on table $tableName");

		//make sure $columnName isnt an alias
		$columnName = $relatedTable->GetColumn($columnName)->ColumnName; //gets the real name of the column if $columnName is an alias
		
		return ($this->_relations[$tableName][$columnName] != null);
	}

/////////////////////////////// Column Aliases ///////////////////////////////////
	/**
	 * @var an array of all aliases. array($key=alias => $val=true);
	 * @ignore
	 */
	protected $_aliases = array();

	/**
	 * Returns an array of all aliases. array($key=alias => $val=true);
	 * 
	 * ``` php
	 * 	$options = array(
	 * 		'names-only' => false, //if true, the returned array will just be an array of alias names
	 * 	)
	 * ```
	 * @param array $options [optional] See description
	 * @return array All aliases of this Column
	 */
	public function GetAliases($options=null){
		if(!empty($options['names-only'])){
			return array_keys($this->_aliases);
		}
		else return $this->_aliases;
	}

	/**
	 * Removes an alias from the column. Returns true if success, false if the column alias does not currently exist.
	 * @param string $alias An alias for this column
	 * @return bool true if success, false if the alias already exists
	 * @see SmartColumn::RemoveAlias() SmartColumn::RemoveAlias()
	 * @see SmartColumn::HasAlias() SmartColumn::HasAlias()
	 */
	public function AddAlias($alias){
		if(!$alias) throw new Exception('$alias must be set');

		if(empty($this->_aliases[$alias])){
			//set relation on $this column
			$this->_aliases[$alias] = true;
			return true;
		}
		else return false;
	}
	/**
	 * Removes an alias from the column. Returns true if success, false if the column alias does not currently exist.
	 * @param string $alias The alias to remove from this column
	 * @return bool true if success, false if the column alias does not exist.
	 * @see SmartColumn::AddAlias() SmartColumn::AddAlias()
	 * @see SmartColumn::HasAlias() SmartColumn::HasAlias()
	 */
	public function RemoveAlias($alias){
		if(!$alias) throw new Exception('$alias must be set');

		if($this->_aliases[$alias]){
			unset($this->_aliases[$alias]);
			return true;
		}
		else return false;
	}

	/**
	 * Returns true if a this column is alised by the given $alias, false otherwise.
	 * @param string $alias The alias to check
	 * @return bool true if a this column is alised by the given $alias, false otherwise.
	 * @see SmartColumn::AddAlias() SmartColumn::AddAlias()
	 * @see SmartColumn::RemoveAlias() SmartColumn::RemoveAlias()
	 */
	public function HasAlias($alias){
		return ($this->_aliases[$alias] != null);
	}

/////////////////////////////// Column Data Functions ///////////////////////////////////

	/**
	 * If the column is a special data type, the value from the database may need to be normalized for use.
	 * Serialized Columns are unserialized.
	 * 'Set' datatype columns are converted from CSV (internal) to a PHP array 
	 * Timestamp Columns will have the timestamp abbreviation appended to the database value (only if a $DefaultTimezone is set on the Database or Column level).
	 * @param mixed $value
	 * @return mixed the normalized value based on the Column's data type
	 * @see SmartColumn::$IsSerializedColumn SmartColumn::$IsSerializedColumn
	 * @see SmartColumn::$IsASet SmartColumn::$IsASet
	 * @see SmartDatabase::$DefaultTimezone SmartDatabase::$DefaultTimezone
	 * @see SmartColumn::$DefaultTimezone SmartColumn::$DefaultTimezone
	 */
	public function NormalizeValue($value){
		//may need to transform the value to it's original type for array and object types
		if($this->IsSerializedColumn){
			$value = $this->GetUnserializedValue($value);
		}
		
		if($this->IsASet){ //'set' type is CSV internally, but we work with arrays
			if(!$value) $value = []; //empty array if not set
			else if(!is_array($value)) $value = explode(',', $value);
		}
		
		//is a date column
		if($value && $this->IsDateColumn){
			//match. special check for "0000-00-00...". different OS's and PHP versions handle strtotim("0000-00-00 00:00:00") differently
			if( strcmp($value, '0000-00-00 00:00:00')==0 || strcmp($value, '0000-00-00')==0 ){
				$value = null;
			}
		}
		
		//is a timezone column
		if($value && $this->IsTimezoneColumn){
			$value = $this->AppendTimezone($value);
		}
		
		return $value;
	}
	
	public function AppendTimezone($value){
		//is a date column
		if($this->IsTimezoneColumn){
			//check for a default timezone
			if($value && strcmp($value, '0000-00-00 00:00:00')!=0){ //if value is set...
				//column level defaults take precedence over database level, if both defaults are set.
				$defaultTimezone = $this->DefaultTimezone ?: $this->Table->Database->DefaultTimezone;
				if($defaultTimezone){
					$dt = new DateTime($value, new DateTimeZone('UTC')); //always stored as UTC when timezone in use
					$dt->setTimezone(new DateTimeZone($defaultTimezone)); //convert to that timezone
					$value = $dt->format('Y-m-d H:i:s T');
				}
			}
		}
		return $value;
	}
	
	/**
	 * Gets all values in all rows for this column, optionally unique and sorted. Optionally in an assoc with the primary key column value as the assoc's key value. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'get-unique'=>false, //If true, only unique values will be returned. Note: array keys in the returned array will NOT be the key column when this is true)
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 * 	'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *						// With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of values being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search.
	 *  }
	 * ```
	 * @param array $options [optional] See description
	 * @return mixed An array of key-value pairs. The keys are either 1: nothing, or 2: the primary key (if 'return-assoc' option is TRUE and 'get-unique' option is false and the table has a primary key), and the values are the actual column values. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 */
	public function GetAllValues(array $options=null){
		return $this->Table->LookupColumnValues($this->ColumnName, null, $options);
	}
	
	/**
	 * Returns the maximum value for this column as stored in the db. returns null if no rows are in the table. Shortcut for GetAggregate('max',...)
	 * @param array $lookupAssoc An assoc-array of column=>value to filter for max value. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] (none yet)
	 * @return mixed The maximum value of the column, optionally filtered by $lookupAssoc
	 * @see SmartColumn::GetAggregateValue() SmartColumn::GetAggregateValue()
	 */
	public function GetMaxValue(array $lookupAssoc=null, array $options=null){
		return $this->GetAggregateValue('max', $lookupAssoc, $options);
	}
	
	/**
	 * Returns the minimum value for this column as stored in the db. returns null if no rows are in the table. Shortcut for GetAggregate('min',...)
	 * @param array $lookupAssoc An assoc-array of column=>value to filter for max value. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] (none yet)
	 * @return mixed The minimum value of the column, optionally filtered by $lookupAssoc
	 * @see SmartColumn::GetAggregateValue() SmartColumn::GetAggregateValue()
	 */
	public function GetMinValue(array $lookupAssoc=null, array $options=null){
		return $this->GetAggregateValue('min', $lookupAssoc, $options);
	}
	
	/**
	 * Runs an aggregate function over a column and returns the value. Function names include: "max", "min", "sum", "avg", "count-distinct"
	 * TODO: Currently has hard coded SQL for MySQL... need to move this to the DbManager at some point, somehow
	 * @param string $aggregateFunction The function to run on the column: "max", "min", "sum", "avg", "num-distinct"
	 * @param array $lookupAssoc An assoc-array of column=>value to filter for max value. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] (none yet)
	 * @return mixed The aggregate value of the column, optionally filtered by $lookupAssoc
	 * @see SmartColumn::GetMaxValue() SmartColumn::GetMaxValue()
	 * @see SmartColumn::GetMinValue() SmartColumn::GetMinValue()
	 */
	public function GetAggregateValue($aggregateFunction, array $lookupAssoc=null, array $options=null){
		if($this->IsSerializedColumn) throw new Exception("Function '".__FUNCTION__."' does not work with serialized column types (array or object) (table: {$this->Table->TableName}, column: {$this->ColumnName})");
		$aggregateFunction = strtolower($aggregateFunction);
		switch($aggregateFunction){
			//MySQL reference - http://dev.mysql.com/doc/refman/5.0/en/group-by-functions.html
			case 'max':
			case 'min':
			case 'sum':
			case 'avg':
				$colSql = $aggregateFunction.'(`'.$this->ColumnName.'`)';
				break;
			case 'count-distinct':
				$colSql = 'count(distinct `'.$this->ColumnName.'`)';
				break;
			default:
				throw new Exception("Invalid aggregate function: ".$aggregateFunction);
		}
		if($lookupAssoc){ //make sure lookup assoc is valid, if given
			$lookupAssoc = $this->Table->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);
		}
		
		$dbManager = $this->Table->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		
		//todo- move aggregate functions to DbManager somehow
		$numRowsSelected = $dbManager->Select(array($colSql.' as val'), $this->Table, $lookupAssoc, null, null, array('add-column-quotes'=>false, 'add-dot-notation'=>false, 'force-select-db'=>true));
		if($numRowsSelected == 0){
			return null;
		}
		else{
			$row = $dbManager->FetchAssoc();
			$val = $row['val'];
			$val = $this->NormalizeValue($val); //may need to normalize the raw data for use. depends on the column's data type (mostly dates, in this case)
			return $val;
		}
	}

	/**
	 * Looks up an array of all Row instances that match the given column $value, or an empty array if there is no match. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * 
	 * To execute this function, this table must have a primary key.
	 * 
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 * 	'callback'=>null,		//function - if set, this function will be invoked for each row and the full result set will NOT be returned- only the LAST ROW in the set will be returned (if there is one). the function's signature should be function($row, $i){} where $row is the actual SmartRow, and $i is the 1-based index of the row being returned
	 * 	'return-next-row'=>null, //OUT variable. integer. if you set this parameter in the $options array, then this function will return only 1 row of the result set at a time. If there are no rows selected or left to iterate over, null is returned.
	 *  						// THIS PARAMETER MUST BE PASSED BY REFERENCE - i.e. array( "return-next-row" => &$curCount ) - the incoming value of this parameter doesn't matter and will be overwritten)
	 *  						// After this function is executed, this OUT variable will be set with the number of rows that have been returned thus far.
	 *  						// Each consecutive call to this function with the 'return-next-row' option set will return the next row in the result set, and also increment the 'return-next-row' variable to the number of rows that have been returned thus far
	 * 	'limit'=>null,  // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *						// With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search.
	 *  }
	 * ```
	 * @param mixed $value The $value to lookup in this column
	 * @param array $options [optional] See description
	 * @return mixed Gets an array of all Row instances that match the given $value, or an empty array if there is no match. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * @see SmartColumn::LookupRow() SmartColumn::LookupRow()
	 * @todo Implement table's with composite keys into this function
	 */
	public function LookupRows($value, array $options=null){
		//normalize serialized data
		if($this->IsSerializedColumn) $value = $this->GetSerializedValue($value);
		$lookupAssoc = array($this->ColumnName => $value);
		return $this->Table->LookupRows($lookupAssoc, $options);
	}

	/**
	 * Alias for LookupRows. See SmartColumn::LookupRows()
	 * @see SmartColumn::LookupRows() SmartColumn::LookupRows()
	 * @deprecated use SmartColumn::LookupRows()
	 * @ignore
	 */
	public function LookupRowsWithValue($value, array $options=null){
		return $this->LookupRows($value, $options);
	}

	/**
	 * Looks up an a row that matches the given column $value. If there is no match, an instance is still returned but ->Exists() will be false. The returned row will have the searched column=>value set by default (excluding auto-increment primary key columns)
	 * To execute this function, this table must have a primary key. Throws an exception if more than 1 row is returned.
	 * @param mixed $value The $value to lookup in this column
	 * @return SmartRow Looks up an a row instance that matches the given column $value.  If there is no match, an instance is still returned but ->Exists() will be false. The returned row will have the searched column=>value set by default (excluding auto-increment primary key columns when the row does not exist)
	 * @see SmartColumn::LookupRows() SmartColumn::LookupRows()
	 * @see SmartRow::Exists() SmartRow::Exists()
	 * @todo Implement table's with composite keys into this function
	 */
	public function LookupRow($value){
		//normalize serialized data
		if($this->IsSerializedColumn) $value = $this->GetSerializedValue($value);
		$lookupAssoc = array($this->ColumnName => $value);
		return $this->Table->LookupRow($lookupAssoc);
	}
	/**
	 * Alias for LookupRow. See SmartColumn::LookupRow()
	 * @see SmartColumn::LookupRow() SmartColumn::LookupRow()
	 * @deprecated use SmartColumn::LookupRows()
	 * @ignore
	 */
	public function LookupRowWithValue($value){
		return $this->LookupRow($value);
	}
	
	/**
	 * Deletes all rows with where the column value matches the passed $value
	 * 
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-callbacks'=>false //If true, all row-level "Delete" callbacks will be skipped. This can substantially improve the performance of very large bulk deletions.
	 * }
	 * ```
	 * @param mixed $value the value to look-up in this column
	 * @param array $options [optional] See description
	 * @return int the number of rows deleted
	 */
	public function DeleteRows($value, array $options=null){
		//skipping delete callbacks on the row-level will delete rows directly on the DB level for efficiency
		if(!empty($options['skip-callbacks'])){ //yes, skip callbacks. faster.
			$dbManager = $this->Table->Database->DbManager;
			if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
	
			//normalize serialized data
			$value = $this->VerifyValueType($value);
			
			return $dbManager->Delete($this->Table, array(array($this->ColumnName=>$value)), '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		}
		else{ //dont skip callbacks. need to lookup each row and delete each row directly. slower than the above
			$deletedCount = 0;
			while($Row = $this->Table->LookupRows( array($this->ColumnName=>$value), array('return-next-row'=>&$curCount) )){
				//delete row and increase count if successful
				if( $Row->Delete() ) $deletedCount++;
			}
			return $deletedCount;
		}
	}
	/**
	 * Alias for DeleteRows. See SmartColumn::DeleteRows()
	 * @see SmartColumn::DeleteRows() SmartColumn::DeleteRows()
	 * @deprecated use SmartColumn::LookupRows()
	 * @ignore
	 */
	public function DeleteRowsWithValue($value, array $options=null){
		return $this->DeleteRows($value, $options);
	}

	/**
	 * Sets the given $value to the column for every row in the table. Returns the number of rows affected.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-callbacks'=>false, //If true, all row-level callbacks AND error-checking will be skipped. This can substantially improve the performance on very large tables.
	 * 	'skip-error-checking'=>false //Only available in conjunction with 'skip-callbacks'=true. If both of these options are true, all row-level error checking will be skipped when each updated Row is Commit()'ed.
	 * }
	 * ```
	 * @param mixed $value The scalar value to set in this column for every row in the table
	 * @param array $options [optional] See description
	 * @return int the number of rows affected
	 */
	public function SetAllValues($value, array $options=null){
		//OPTIONS
		$defaultOptions = [ //default options
			'skip-callbacks' => false,
			'skip-error-checking' => false
		];
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if($this->IsUnique || $this->IsPrimaryKey) throw new Exception("Cannot set all values for a column specified as Unique or Primary Key (table: {$this->Table->TableName}, column: {$this->ColumnName})");
		
		//skipping callbacks on the row-level will update rows directly on the DB level for efficiency
		if($options['skip-callbacks']){ //yes, skip callbacks. faster.
			$dbManager = $this->Table->Database->DbManager;
			if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
			
			//normalize serialized data
			$value = $this->VerifyValueType($value);
			
			return $dbManager->Update($this->Table, array($this->ColumnName=>$value), '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		}
		else{ //dont skip callbacks. need to lookup each row and update each row directly. slower than the above
			$updatedCount = 0;
			$skipErrorChecking = $options['skip-error-checking'];
			while($Row = $this->Table->GetAllRows( array('return-next-row'=>&$curCount) )){
				//update row and increase count if successful
				$Row[ $this->ColumnName ] = $value; //note: this will call VerifyValueType(), so no need to here.
				if( $Row->Commit($skipErrorChecking) === 1 ) $updatedCount++;
			}
			return $updatedCount;
		}
	}


/////////////////////////////// Data Normalization ///////////////////////////////////
	/**
	 * Anytime we set the value of this cell, we need to fix boolean types to '1' and '0' and make sure we're not setting to an object.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-serialized-data'=>false, //If true, serialized data will be left as-is (i.e. for using strings in lookup assocs)
	 * 	'skip-force-set-to-csv'=>false	//if true, SET data is returned as a CSV. SmartDb uses a CSV internally, as MySQL does basically.
	 * }
	 * ```
	 * @param object $value modifies this value BY REFERENCE
	 * @return nothing.
	 */
	public function VerifyValueType($value, $options=null){
		$columnDataType = $this->DataType;
	
		//handle special type conversions
		if($this->Table->Database->DEV_MODE){ //development mode
			//convert objects to strings if the column type is not an object
			if($columnDataType !== "object" && is_object($value)){
				if(method_exists($value, "__toString")){
					$value = $value->__toString();
					return $value;
				}
				else{
					$type = gettype($value);
					throw new Exception("Cannot set this Cell's value to an object of type $type. Table: '{$this->Table->TableName}', Column: '{$this->ColumnName}' ");
				}
			}
			else if( ($columnDataType !== "array" && !$this->IsASet && is_array($value)) || is_resource($value)){
				//"set" can be an array of all selected values, or a csv of selected values
				$type = gettype($value);
				throw new Exception("Cannot set a Cell's value to a '$type' type. You can only set Cell values with simple types (ie string, int, etc.). Table: '{$this->Table->TableName}', Column: '{$this->ColumnName}' ");
			}
		}
		else { //production mode
			//convert objects to strings if the column type is not an object
			if($columnDataType !== "object" && is_object($value)) $value = $value->__toString();
		}
	
		if($value === null){
			return null; //null is null. let it go
		}
	
		//handle booleans.
		$isBool = is_bool($value);
		if($isBool){ //boolean false is '\0'. make booleans default to 1 and 0
			if($value) $value = 1;
			else $value = 0;
		}
		
		if ($this->IsStringColumn && $this->IsUnique && !$this->IsRequired){ //unique string column data, but data is NOT required.
			//note that MySQL does not allow multiple empty string values in the DB if the column is set as 'umique', but NULL is allowed
			//so if we have an empty string "" in this case, set it to NULL
			if($this->TrimAndStripTagsOnSet){
				$value = strip_tags(trim($value));
			}
			if($value === ''){
				return null;
			}
		}
	
		//handle date columns
		if($this->IsDateColumn){ //can be a string of a date format or an int timestamp value since 1970
			
			if($value) {
				//match. special check for "0000-00-00...". different OS's and PHP versions handle strtotim("0000-00-00 00:00:00") differently
				if( strcmp($value, '0000-00-00 00:00:00')==0 || strcmp($value, '0000-00-00')==0 ){
					return null;
				}
				
				//value is set. if a timezone is in use, use gmdate(). otherwise use date()
				//"date" DataType has a slightly different format than other DateColumns
				if($this->DataType == "date") $dateFormat = "Y-m-d";
				else $dateFormat = "Y-m-d H:i:s";
								
				//column level defaults take precedence over database level, if both defaults are set.
				$defaultTimezone = $this->DefaultTimezone ?: $this->Table->Database->DefaultTimezone;
				if($this->IsTimezoneColumn && $defaultTimezone){ //a timezone is in use, always store a gmdate (not system time)
					//if value is int and < 100000000, we'll assume it's not a raw timestamp (100000000 puts us in 1970s somewhere)
					if( (is_int($value) && $value > 100000000) || (is_numeric($value) && (int)$value > 100000000) ){
						$value = gmdate($dateFormat, $value); // strtotime( <int> ) returns false. if <int> data type, we assume $value is the raw timestamp value we should use, and skip strtotime
					}
					else{
						$strtotime = strtotime( (string)$value ); //try to parse string. might return false if invalid
						if($strtotime === false){
							//invalid date format
							$value = null; 
						}
						else{
							//parsed successfully
							$value = gmdate($dateFormat, $strtotime);
						}
					}
				}
				else{ //no timezone in use, use date (system time)
					//if value is int and < 100000000, we'll assume it's not a raw timestamp (100000000 puts us in 1970s somewhere)
					if( (is_int($value) && $value > 100000000) || (is_numeric($value) && (int)$value > 100000000) ){
						$value = date($dateFormat, $value); // strtotime( <int> ) returns false. if <int> data type, we assume $value is the raw timestamp value we should use, and skip strtotime
					}
					else $value = date($dateFormat, strtotime( (string)$value ));
				}
			}
			
			if(!$value) $value = null; //must set to null since there is no "0" date (i.e. TIMESTAMP has a range of '1970-01-01 00:00:01' UTC to '2038-01-19 03:14:07' UTC)
			return $value;
		}
		
		if($this->IsASet){
			//set needs to be sorted accordingly (for mysql WHERE clauses)
			//also if $value is an array, make it a CSV
			$forceCsv = ( !empty($options['skip-force-set-to-csv']) ? false : true);
			$value = $this->GetSortedSet($value, $forceCsv);
			return $value;
		}

		if($this->PossibleValues){
			//for older mysql, enum has null and "" as separate valid values and "" is ALWAYS valid... wtf? make it so "" and null are equal. will be caught later if null is not allowed
			//newer mysql now throws a "data truncated" error if you try to set an enum as "" and it's not a valid value of the enum
			if($value !== '0' && !$value){
				return null;
			}
		}
		
		//strongly type the data
		switch($columnDataType){
			case 'bool': //aka tinyint with different casting rules
				if($value === "\0") $value = 0; //for converting from (incorrectly) typed "binary" to "bool", handle this case
				if($value === "" || $value === null) $value = null; //keep null when null. will evaluate to false ultimately
				else {
					$value = (bool)$value; //use php casting rules to determine the boolean value
					$value = (int)$value; //then cast to int (should be 0 or 1) for db storage
				}
				break;
			
			//dont quote numbers
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'int':
			case 'bigint':
				if(is_string($value)){ //trim leading $ before the hard cast to int (PHP will cast something like "$3.50" to 0 otherwise)
					$value = ltrim($value, " \t\n\r\0\x0B\$"); //regular whitespace trim PLUS dollar sign. ref: http://php.net/manual/en/function.trim.php
				}
				if($value === "") $value = null;
				else $value = (int)$value;
				break;
	
			case 'float':
			case 'double':
			case 'decimal':
				if(is_string($value)){ //trim leading $ before the hard cast to float (PHP will cast something like "$3.50" to 0 otherwise)
					$value = ltrim($value, " \t\n\r\0\x0B\$"); //regular whitespace trim PLUS dollar sign. ref: http://php.net/manual/en/function.trim.php
				}
				if($value === "") $value = null;
				else $value = (float)$value;
				break;
	
			case 'binary': //needs quotes. this data type stores binary strings that have no character set or collation (it is NOT strictly ones and zeros)
				if(empty($value) || $value == "\0") $value = '0'; //force binary to be 0 if nothing is set
				else $value = (string)$value;
				break;
			
			//case 'set':
				//handled above since we already have a bool if this is true
					
			case 'array':
				if(empty($options['skip-serialized-data'])){
					$value = self::SerializeArray($value);
				}
				break;
	
			case 'object':
				if(empty($options['skip-serialized-data'])){
					$value = self::SerializeObject($value);
				}
				break;
	
			default:
				if($isBool && !$value) $value = ""; //false should evalute to empty string
				else $value = (string)$value;
				break;
		}
		
		return $value;
	}
	
/////////////////////////////// Serialize/Unserialize Array/Object functions ///////////////////////////////////
	/**
	 * If the data type serializes data (array, object), this function convers the given $value to the serialized data
	 * @param mixed $value the value to serialize
	 * @return the serialized value
	 */
	public function GetSerializedValue($value){
		switch($this->DataType){
			case 'array':
				$value = self::SerializeArray($value);
				break;

			case 'object':
				$value = self::SerializeObject($value);
				break;
		}
		return $value;
	}
	
	/**
	 * If the data type unserializes data (array, object), this function convers the given $value to the unserialized data
	 * @param mixed $value the value to unserialize
	 * @return the unserialized value
	 */
	public function GetUnserializedValue($value){
		switch($this->DataType){
			case 'array':
				$value = self::UnserializeArray($value);
				break;

			case 'object':
				$value = self::UnserializeObject($value);
				break;
		}
		return $value;
	}
	
	/**
	 * Serializes the given $array
	 * @param array $array the array to serialize
	 * @return the serialized array
	 */
	public static function SerializeArray($array){
		if(!$array) $array = null; //force null if nothing is set. note, this includes an empty array
		else if(!is_array($array)){ //verify array type
			//not an array, though it may already be serialized. test it
			$arrayValue = @unserialize($array); //returns false if not serialized
			if($arrayValue === false || !is_array($arrayValue)){ //not valid array or serizlied array
				return serialize(array($array)); //make the given $array valid. return the passed value as the only item in the serialized array
			}
		}
		else $array = serialize($array);
		return $array;
	}
	
	/**
	 * Serializes the given $object
	 * @param object $object the object to serialize
	 * @return the serialized object
	 */
	public static function SerializeObject($object){
		if(!$object) $object = null; //force null if nothing is set
		else if(!is_object($object)){ //verify object type
			//not an object, though it may already be serialized. test it
			$objectValue = @unserialize($object); //returns false if not serialized
			if($objectValue === false || !is_object($objectValue)){ //not valid object or serizlied object
				throw new Exception("Value '$object' is not of object type, as expected.");
			}
		}
		else $object = serialize($object);
		return $object;
	}
	
	/**
	 * Unserializes the given $array
	 * @param array $serializedArray the array to unserialize
	 * @return the unserialized array
	 */
	public static function UnserializeArray($serializedArray){
		if(!$serializedArray) return array(); //return empty array always instead of null
		$arrayValue = @unserialize($serializedArray);
		if($arrayValue===false || !is_array($arrayValue)){ //could not unserialize
			return array($serializedArray); //return value as is, inside an array
		}
		return $arrayValue;
	}
	
	/**
	 * Unserializes the given $object
	 * @param object $serializedObject the object to unserialize
	 * @return the unserialized object
	 */
	public static function UnserializeObject($serializedObject){
		if(!$serializedObject) return null;
		$objValue = @unserialize($serializedObject);
		return $objValue; 
	}
	
/////////////////////////////// "SET" data type functions ///////////////////////////////////
	private $_possibleValuesCache;
	/**
	 * Orders the given $setData according to the column's PossibleValues array because
	 * SET ordering matters for mysql in WHERE clauses and etc. see mysql doc https://dev.mysql.com/doc/refman/5.7/en/set.html
	 * An EMPTY array set or empty string always becomes NULL!
	 * SETs are case-insensitive and auto-trim!
	 * @param mixed $setData can be an array of all selected values or a CSV of selected values from our PossibleValues
	 * @param bool $forceCsv if true, a CSV will be returned instead of array. smart db works with a CSV internally (as does MySQL)
	 * @throws \Exception if invalid set data is provided that is not in the column's PossibleValues array
	 * @return mixed the sorted set data as array or csv
	 */
	public function GetSortedSet($setData, $forceCsv=false){
	
		//simple cache
		if(!$this->_possibleValuesCache){
			foreach($this->PossibleValues as $i=>$possibleValue){
				$this->_possibleValuesCache[$i] = strtolower(trim($possibleValue));
			}
		}
		
		//turn a CSV to array so we can sort the data
		$inputDataIsArray = is_array($setData);
		if(!$inputDataIsArray){
			$setData = explode(',', $setData);
		}
		
		//sort (trimmed and case-insensitive just as mysql does with SETs)
		$finalArr = array();
		$emptyElementFound = false;
		foreach($setData as $i=>$thisVal){
			$thisValLower = strtolower(trim($thisVal));
			if(!$thisValLower) {
				$emptyElementFound = true;
				continue; //empty element. remove. consider it null
			}
			$foundAtKey = array_search($thisValLower, $this->_possibleValuesCache);
			
			if($foundAtKey === false){ //not found
				throw new \Exception("Invalid value: '$thisVal' for SET data column (table: {$this->Table->TableName}, column: {$this->ColumnName})");
			}
			$finalArr[$foundAtKey] = $this->PossibleValues[$foundAtKey]; //use the actual "PossibleValue"
		}

		//don't remove this case. handle the empty elemenet it in your app.
		if($emptyElementFound && $finalArr) throw new \Exception("SET data column cannot contain set data AND null/empty in same set (table: {$this->Table->TableName}, column: {$this->ColumnName})");
		
		ksort($finalArr); //array values need to be sorted. i thought php did this by default when working with <int> array keys, but that doesn't seem to be the case. so we sort.

		//sorted
		
		if(!$finalArr) return null; //if the array is empty, equate this to null across the board. null/not set is either allowed or not... it's all or nothing  

		if($forceCsv || !$inputDataIsArray){ //return csv if forced or if input data is in CSV format (keep it the same)
			return implode( ',',  $finalArr);
		}
		else return $finalArr;
	}
	/**
	 * Returns boolean, true if the passed $setData is in the column's PossibleValues array, false otherwise. These are case-insensitive and auto-trim!
	 * @param mixed $setData either an array of SET values to check, or a CSV of the values. These are case-insensitive and auto-trim!
	 * @return bool true if passed $setData is in the column's PossibleValues array, false otherwise.
	 */
	public function VerifySet($setData){
		//simple cache
		if(!$this->_possibleValuesCache){
			foreach($this->PossibleValues as $i=>$possibleValue){
				$this->_possibleValuesCache[$i] = strtolower(trim($possibleValue));
			}
		}
		
		//turn a CSV to array so we can sort the data
		if(!is_array($setData)){
			$setData = explode(',', $setData);
		}
		
		//sort (trimmed and case-insensitive just as mysql does with SETs)
		$emptyElementFound = false;
		$foundAtKey = false;
		foreach($setData as $i=>$thisVal){
			$thisValLower = strtolower(trim($thisVal));
			if(!$thisValLower) {
				$emptyElementFound = true;
				continue; //empty element. remove. consider it null
			}
			$foundAtKey = array_search($thisValLower, $this->_possibleValuesCache);
			if($foundAtKey === false){ //not found
				return false;
			}
		}
		if($emptyElementFound && $foundAtKey) return false; //SET data column cannot contain set data AND null/empty in same set
		return true;
	}

//////////////////////////// FORM STUFF //////////////////////////////////
	/**
	 * Returns the default html name attribute that will be used when getting a form object for this cell
	 * @param string $nameSuffix [optional] A suffix to add the the end of the column name
	 * @return string The default html name attribute that will be used when getting a form object for this cell
	 */
	public function GetDefaultFormObjectName($nameSuffix=''){
		return $this->Table->TableName.'['.$this->ColumnName.$nameSuffix.']';
	}
	/**
	 * Returns the default html ID attribute that will be used when getting a form object for this cell
	 * @param string $idSuffix [optional] A suffix to add the the end of the default form object id
	 * @return string The default name html ID attribute that will be used when getting a form object for this cell
	 */
	public function GetDefaultFormObjectId($idSuffix=''){
		return $this->Table->TableName.'_'.$this->ColumnName.$idSuffix;
	}
	
/////////////////////////////// Invoke ///////////////////////////////////
	/**
	 * NEW WITH PHP 5.3.0, A shortcut for ->LookupRows()
	 * Example usage: $smartdb['tablename']['columnname'](212) instead of $smartrow['tablename']['columnname']->LookupRow(212)
	 * @param mixed $lookupVal The value to lookup in this particular column
	 * @return array A an array of Row instance matching the criteria of $lookupVal.
	 * @see SmartTable::LookupRows() SmartTable::LookupRows()
	 * @ignore
	 */
	public function __invoke($lookupVal=null, $options=null){
		return $this->LookupRows($lookupVal, $options);
	}
	
/////////////////////////////// ERROR ON INVALID FUNCTIONS/VARS //////////////////////////
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __call($method, $args){
		throw new Exception("Undefined method: $method. Passed args: ".print_r($args,true));
	}
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __set($key, $val){
		throw new Exception("Undefined var: $key. Attempted set value: $val");
	}
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __get($key){
		throw new Exception("Undefined var: $key");
	}
} //end class