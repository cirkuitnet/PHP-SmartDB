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
 * @package SmartDatabase
 */
/**
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
				'IsDateColumn',
				'IsSerializedColumn',
				'PossibleValues',
				'MinSize',
				'MaxSize',
				'AllowGet',
				'AllowSet',
				'TrimAndStripTagsOnSet',
				'AllowLookup',
				'AllowGetAll',
				'DefaultValue',
				'IsUnique',
				'IsPrimaryKey',
				'IsAutoIncrement',
				'FulltextIndex',
				'NonuniqueIndex',
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
	 * @var string The SQL data type of this column. Check XmlSchema.xsd for all possible values of this column (last I check, it was: "char|varchar|text|mediumtext|longtext|blob|mediumblob|longblob|tinyint|smallint|mediumint|int|bigint|float|double|decimal|date|datetime|timestamp|time|binary|enum\((\s*'[ a-zA-Z0-9/_-]+'\s*,)*(\s*'[ a-zA-Z0-9/_-]+'\s*)\)")
	 */
	public $DataType;
	/**
	 * @var string The SQL collation for this column (ie "latin1_swedish_ci", "utf8_general_ci", etc)
	 */
	public $Collation;
	/**
	 * @var bool True if this is a date column, false otherwise. Should be computed based of the $DataType
	 */
	public $IsDateColumn;
	/**
	 * @var bool True if this is a serialized or compressed column, false otherwise. Should be computed based of the $DataType
	 */
	public $IsSerializedColumn;
	/**
	 * @var array An array of all possible values that this column accepts. null or empty array means no values are restricted. In theory, the column does NOT have to be an 'enum' data type for this to work... it will work with any column data type... but why would you do that?
	 */
	public $PossibleValues;
	/**
	 * @var int The minimum number of characters allowed for this column. Can be null.
	 */
	public $MinSize;
	
	/**
	 * @var mixed The size of the column. For DataType=="decimal" columns, this is "PRECISION,SCALE" (ie "14,4"). For other columns, this represents the maximum number of characters allowed for this column. Can be null.
	 * @see SmartColumn::GetMaxLength()
	 */
	public $MaxSize;
	/**
	 * Uses the column's $MaxSize property to determine what that maximum number of characters that are allowed in this column, regardless of data type (i.e. decimals are different than regular MaxSize)
	 * @return int Returns the maximum number of characters this column can hold. If no MaxSize is set, returns null.
	 * @see SmartColumn::$MaxSize
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
	 * @var string Default it "text". See XmlSchema.xsd for possible values (Last I checked, it was: "text", "password", "checkbox", "radio", "select", "textarea", "hidden", "colorpicker", "datepicker", "slider" ... the last 3, are for use with jQuery UI)
	 * @see SmartCell::GetFormObject()
	 */
	public $DefaultFormType = "text";

	/**
	 * @var bool True if data is required for this column, false if null is an acceptable value
	 * @see SmartColumn::$IsRequiredMessage
	 */
	public $IsRequired;
	/**
	 * @var string The error message to show if the field is required. If null, a decent default message will be used (i.e. "Phone Number" is required.).
	 * @see SmartColumn::$IsRequired
	 */
	public $IsRequiredMessage;

	/**
	 * @var string Regex the value of this column must match against. Can be null. Uses preg_match() in PHP... automatically wraps it with slashes in PHP (i.e. preg_match('/'.$this->RegexCheck.'/i')) and javascript error checking (SmartFormValidation_jQueryValidate.php)... so don't wrap your own! Both php and javascript can only do case-insensitive matching at the moment, but both are supported with a single value here.
	 * @see SmartColumn::$RegexFailMessage
	 */
	public $RegexCheck;
	/**
	 * @var string The error message to show if the column value does not match against $RegexCheck. If null, a decent default message will be used (i.e. Invalid value for "Phone Number").
	 * @see SmartColumn::$RegexCheck
	 */
	public $RegexFailMessage;
	/**
	 * @todo Implement the SortOrder property for columns. 1-indexed to distinguish 0 from null/not set?
	 * @var int Mostly for internal/backend nitty-gritties. This value represents where this column's position is in comparison with other columns in the table. Used when Synchronizing with the database. 1 is the first column, 2 is the second, etc.
	 */
	public $SortOrder;

	/**
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
	 * @see SmartColumn::RemoveRelation()
	 * @see SmartColumn::HasRelation()
	 */
	public function AddRelation($tableName, $columnName, $addRelationOnRelatedColumn=true){
		if(!$this->Table->Database->TableExists($tableName)) throw new Exception("Related table '$tableName' does not exist");
		if(!$this->Table->Database->GetTable($tableName)->ColumnExists($columnName)) throw new Exception("Related column '$columnName' does not exist on table $tableName");

		if(!$this->_relations[$tableName][$columnName]){
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
	 * @see SmartColumn::AddRelation()
	 * @see SmartColumn::HasRelation()
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
	 * @see SmartColumn::AddRelation()
	 * @see SmartColumn::RemoveRelation()
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
	 * <code>
	 * 	$options = array(
	 * 		'names-only' => false, //if true, the returned array will just be an array of alias names
	 * 	)
	 * </code>
	 * @return array All aliases of this Column
	 */
	public function GetAliases($options=null){
		if($options['names-only']){
			return array_keys($this->_aliases);
		}
		else return $this->_aliases;
	}

	/**
	 * Removes an alias from the column. Returns true if success, false if the column alias does not currently exist.
	 * @param string $alias An alias for this column
	 * @return bool true if success, false if the alias already exists
	 * @see SmartColumn::RemoveAlias()
	 * @see SmartColumn::HasAlias()
	 */
	public function AddAlias($alias){
		if(!$alias) throw new Exception('$alias must be set');

		if(!$this->_aliases[$alias]){
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
	 * @see SmartColumn::AddAlias()
	 * @see SmartColumn::HasAlias()
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
	 * @see SmartColumn::AddAlias()
	 * @see SmartColumn::RemoveAlias()
	 */
	public function HasAlias($alias){
		return ($this->_aliases[$alias] != null);
	}

/////////////////////////////// Column Data Functions ///////////////////////////////////
	/**
	 * Gets all values in all rows for this column, optionally unique and sorted. Optionally in an assoc with the primary key column value as the assoc's key value. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 *  'get-unique'=>false, //If true, only unique values will be returned. Note: array keys in the returned array will NOT be the key column when this is true)
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of values being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search.
	 *  }
	 * </code>
	 * @param array $options [optional] See description
	 * @return mixed An array of key-value pairs. The keys are either 1: nothing, or 2: the primary key (if 'return-assoc' option is TRUE and 'get-unique' option is false and the table has a primary key), and the values are the actual column values. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 */
	public function GetAllValues(array $options=null){
		return $this->Table->LookupColumnValues(null, $this->ColumnName, $options);
	}
	
	/**
	 * Returns the maximum value for this column as stored in the db. returns null if no rows are in the table. Shortcut for GetAggregate('max',...)
	 * @param array $lookupAssoc An assoc-array of column=>value to filter for max value. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] (none yet)
	 * @return mixed The maximum value of the column, optionally filtered by $lookupAssoc
	 * @see SmartColumn::GetAggregateValue()
	 */
	public function GetMaxValue(array $lookupAssoc=null, array $options=null){
		return $this->GetAggregateValue('max', $lookupAssoc, $options);
	}
	
	/**
	 * Returns the minimum value for this column as stored in the db. returns null if no rows are in the table. Shortcut for GetAggregate('min',...)
	 * @param array $lookupAssoc An assoc-array of column=>value to filter for max value. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] (none yet)
	 * @return mixed The minimum value of the column, optionally filtered by $lookupAssoc
	 * @see SmartColumn::GetAggregateValue()
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
	 * @see SmartColumn::GetMaxValue()
	 * @see SmartColumn::GetMinValue()
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
			return $row['val'];			
		}
	}

	/**
	 * Looks up an array of all Row instances that match the given column $value, or an empty array if there is no match. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * <p>To execute this function, this table must have a primary key.</p>
	 * <p>Options are as follows:</p>
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 *  'return-next-row'=>null, //OUT variable. integer. if you set this parameter in the $options array, then this function will return only 1 row of the result set at a time. If there are no rows selected or left to iterate over, null is returned.
	 *  						// THIS PARAMETER MUST BE PASSED BY REFERENCE - i.e. array( "return-next-row" => &$curCount ) - the incoming value of this parameter doesn't matter and will be overwritten)
	 *  						// After this function is executed, this OUT variable will be set with the number of rows that have been returned thus far.
	 *  						// Each consecutive call to this function with the 'return-next-row' option set will return the next row in the result set, and also increment the 'return-next-row' variable to the number of rows that have been returned thus far
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search.
	 *  }
	 * </code>
	 * @param mixed $value The $value to lookup in this column
	 * @param array $options [optional] See description
	 * @return mixed Gets an array of all Row instances that match the given $value, or an empty array if there is no match. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * @see SmartColumn::LookupRow()
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
	 * @see SmartColumn::LookupRows()
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
	 * @see SmartColumn::LookupRows()
	 * @see SmartRow::Exists()
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
	 * @see SmartColumn::LookupRow()
	 * @deprecated use SmartColumn::LookupRows()
	 * @ignore
	 */
	public function LookupRowWithValue($value){
		return $this->LookupRow($value);
	}
	
	/**
	 * Deletes all rows with where the column value matches the passed $value
	 * @param mixed $value the value to look-up in this column
	 * @return int the number of rows deleted
	 */
	public function DeleteRows($value){
		$dbManager = $this->Table->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");

		//normalize serialized data
		if($this->IsSerializedColumn) $value = $this->GetSerializedValue($value);
		
		return $dbManager->Delete($this->Table, array(array($this->ColumnName=>$value)), '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
	}
	/**
	 * Alias for DeleteRows. See SmartColumn::DeleteRows()
	 * @see SmartColumn::DeleteRows()
	 * @deprecated use SmartColumn::LookupRows()
	 * @ignore
	 */
	public function DeleteRowsWithValue($value){
		return $this->DeleteRows($value);
	}

	/**
	 * Sets the given $value to the column for every row in the table. Returns the number of rows affected.
	 * @param mixed $value The scalar value to set in this column for every row in the table
	 * @return int the number of rows affected
	 */
	public function SetAllValues($value){
		if($this->IsUnique || $this->IsPrimaryKey) throw new Exception("Cannot set all values for a column specified as Unique or Primary Key (table: {$this->Table->TableName}, column: {$this->ColumnName})");
		$dbManager = $this->Table->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		
		//may need to serialize the $value data
		if($this->IsSerializedColumn){
			$value = $this->GetSerializedValue($value);
		}
		
		return $dbManager->Update($this->Table, array($this->ColumnName=>$value), '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
	}
	
/////////////////////////////// Serialize/Unserialize Array/Object functions ///////////////////////////////////
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
	
	public static function UnserializeArray($serializedArray){
		if(!$serializedArray) return array(); //return empty array always instead of null
		$arrayValue = @unserialize($serializedArray);
		if($arrayValue===false || !is_array($arrayValue)){ //could not unserialize
			return array($serializedArray); //return value as is, inside an array
		}
		return $arrayValue;
	}
	
	public static function UnserializeObject($serializedObject){
		if(!$serializedObject) return null;
		$objValue = @unserialize($serializedObject);
		return $objValue; 
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
	 * @param string $nameSuffix [optional] A suffix to add the the end of the column name
	 * @return string The default name html ID attribute that will be used when getting a form object for this cell
	 */
	public function GetDefaultFormObjectId($nameSuffix=''){
		return $this->Table->TableName.'_'.$this->ColumnName.$nameSuffix;
	}
	
/////////////////////////////// Invoke ///////////////////////////////////
	/**
	 * NEW WITH PHP 5.3.0, A shortcut for ->LookupRows()
	 * Example usage: $smartdb['tablename']['columnname'](212) instead of $smartrow['tablename']['columnname']->LookupRow(212)
	 * @param mixed $lookupVal The value to lookup in this particular column
	 * @return array A an array of Row instance matching the criteria of $lookupVal.
	 * @see SmartTable::LookupRows()
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