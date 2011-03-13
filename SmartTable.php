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
 */
require_once(dirname(__FILE__).'/SmartColumn.php');
/**
 * @package SmartDatabase
 */
class SmartTable implements ArrayAccess, Countable{
	/**
	 * @var SmartDatabase The Database that contains this Table
	 */
	public $Database;

	/**
	 * @var SmartTable The name of the Table
	 */
	public $TableName;

	/**
	 * @var bool Defaults to false. If true, this table will not actually be created in the database, but will be available to be inherited from using the $table->InheritColumnsFromTable() method.
	 * @see SmartTable::InheritColumnsFromTable()
	 * @see SmartTable::GetInheritedTableName()
	 */
	public $IsAbstract = false;

	/**
	 * @var bool Defaults to false. If true, anytime a column value is set within this table, the row is automatically committed ( $row->Commit() ). Otherwise, you need to commit by hand.
	 */
	public $AutoCommit = false; //default to false

	/**
	 * @var string The class that extends from a row within this table to implement custom functionality
	 */
	public $ExtendedByClassName;

	/**
	 * @param string $tableName The name of the table
	 * @return SmartTable
	 */
	public function __construct($tableName){
		if(!isset($tableName)) throw new Exception('$tableName must be set');

		$this->TableName = $tableName;
	}

/////////////////////////////// Table Structure  ///////////////////////////////////
	private $_columns = array(); //Key is the column name, Value is the Column instance.
	private $_columnAliases = array(); //Key is the column alias name, value is the actual column name

	private $_keyColumns = array(); //computed from $this->_columns
	private $_nonKeyColumns = array(); //computed from $this->_columns
	private $_autoIncrementKeyColumns = array(); //computed from $this->_columns
	private $_nonAutoIncrementKeyColumns = array(); //computed from $this->_columns

	private $_primaryKeyExists;
	private $_primaryKeyIsComposite;
	private $_primaryKeyIsNonComposite;
	private $_primaryKeyIsNonCompositeAutoIncrement;
	private $_primaryKeyIsNonCompositeNonAutoIncrement;

	/**
	 * Returns an assoc of all Columns. The returned array's key=$columnName, value=$Column
	 * @return array An assoc of all Columns. The returned array's key=$columnName, value=$Column
	 * @see SmartColumn
	 */
	public function GetAllColumns(){
		return $this->_columns;
	}

	/**
	 * Returns an assoc of all column aliases. The returned array's key=$columnAlias, value=$realColumnName
	 * @return array An assoc of all column aliases. The returned array's key=$columnAlias, value=$realColumnName
	 * @see SmartColumn
	 */
	public function GetAllColumnAliases(){
		return $this->_columnAliases;
	}

	/**
	 *
	 * @return array
	 * @ignore
	 */
	public function GetKeyColumns(){
		return $this->_keyColumns;
	}
	/**
	 *
	 * @return array
	 * @ignore
	 */
	public function GetNonKeyColumns(){
		return $this->_nonKeyColumns;
	}
	/**
	 *
	 * @return array
	 * @ignore
	 */
	public function GetAutoIncrementKeyColumns(){
		return $this->_autoIncrementKeyColumns;
	}
	/**
	 *
	 * @return array
	 * @ignore
	 */
	public function GetNonAutoIncrementKeyColumns(){
		return $this->_nonAutoIncrementKeyColumns;
	}

	/**
	 * Returns TRUE if this table contains a primary key, FALSE otherwise.
	 * @return bool TRUE if this table contains a primary key, FALSE otherwise.
	 */
	public function PrimaryKeyExists(){
		return $this->_primaryKeyExists;
	}
	/**
	 *
	 * @return bool
	 * @ignore
	 */
	public function PrimaryKeyIsComposite(){
		return $this->_primaryKeyIsComposite;
	}
	/**
	 *
	 * @return bool
	 * @ignore
	 */
	public function PrimaryKeyIsNonComposite(){
		return $this->_primaryKeyIsNonComposite;
	}
	/**
	 *
	 * @return bool
	 * @ignore
	 */
	public function PrimaryKeyIsNonCompositeAutoIncrement(){
		return $this->_primaryKeyIsNonCompositeAutoIncrement;
	}
	/**
	 *
	 * @return bool
	 * @ignore
	 */
	public function PrimaryKeyIsNonCompositeNonAutoIncrement(){
		return $this->_primaryKeyIsNonCompositeNonAutoIncrement;
	}

	/**
	 * Mostly for internal use. If true, Refresh() will be called automatically whenever a column is added or removed to this table.
	 * As an optimization, you may want to disable $AutoRefresh when you are adding/removing bulk columns to a table, then make a single call to Refresh() once all column management is complete.
	 * @see SmartTable::Refresh()
	 * @see SmartTable::AddColumn()
	 * @see SmartTable::RemoveColumn()
	 * @var bool
	 */
	public $AutoRefresh = true;

	/**
	 * Mostly for internal use. Re-computes column aliases, key columns, and etc from $this->_columns.
	 * <p>This function gets called automatically from AddColumn() and RemoveColumn() unless $AutoRefresh is set to false (in which case, you'll need to call this function yourself when you have finished adding/removing columns).</p>
 	 * <p>As an optimization, you may want to disable AutoRefresh when you are adding/removing bulk columns to a table, then make a single call to Refresh() once all column management is complete.</p>
	 * @see SmartTable::$AutoRefresh
	 * @see SmartTable::AddColumn()
	 * @see SmartTable::RemoveColumn()
	 */
	public function Refresh(){
		$this->_keyColumns = array();
		$this->_nonKeyColumns = array();
		$this->_autoIncrementKeyColumns = array();
		$this->_nonAutoIncrementKeyColumns = array();

		$this->_columnAliases = array(); //clear column aliases

		foreach($this->_columns as $columnName=>$Column){
			if($Column->IsPrimaryKey){
				$this->_keyColumns[$columnName] = $Column;

				if($Column->IsAutoIncrement){
					$this->_autoIncrementKeyColumns[$columnName] = $Column;
				}
				else{
					$this->_nonAutoIncrementKeyColumns[$columnName] = $Column;
				}
			}
			else{
				$this->_nonKeyColumns[$columnName] = $Column;
			}

			//handle columns with aliases
			foreach($Column->GetAliases() as $alias=>$nothing){
				if($this->_columnAliases[$alias] && $this->_columnAliases[$alias] != $columnName)
					throw new Exception("Column alias '$alias' is used more than once on table '{$this->TableName}': Columns: '$columnName' and '{$this->_columnAliases[$alias]}'.");

				$this->_columnAliases[$alias] = $columnName;
			}
		}

		$this->_primaryKeyExists = (count($this->_keyColumns) > 0);
		$this->_primaryKeyIsComposite = (count($this->_keyColumns) > 1);
		$this->_primaryKeyIsNonComposite = (count($this->_keyColumns) == 1);

		$keyColumnNamesInArray = array_keys($this->_keyColumns);
		$keyColumnName = $keyColumnNamesInArray[0];
		$this->_primaryKeyIsNonCompositeAutoIncrement = (count($this->_keyColumns) == 1 && $this->_keyColumns[$keyColumnName]->IsAutoIncrement );
		$this->_primaryKeyIsNonCompositeNonAutoIncrement = (count($this->_keyColumns) == 1 && !($this->_keyColumns[$keyColumnName]->IsAutoIncrement));

		//a little error checking to make sure the structure checks out
		if(count($this->_autoIncrementKeyColumns) > 1) throw new Exception("Cannot have more than 1 AutoIncrement Key Column defined for a single table.");
		if(count($this->_autoIncrementKeyColumns) > 0 && count($this->_nonAutoIncrementKeyColumns) > 0) throw new Exception("Cannot have both AutoIncrement and Non-AutoIncrement Key Columns defined for a single table.");
	}

/////////////////////////////// Column Management ///////////////////////////////////
	/**
	 * Adds a column to be managed by this Table. Replaces any column with the same name.
	 * If adding bulk columns, you may want to disable $AutoRefresh until all columns have been added, then make a single call to Refresh()
	 * @see SmartTable::Refresh()
	 * @see SmartTable::$AutoRefresh
	 * @param SmartColumn $Column The Column to add.
	 * @param bool $replaceExisting If a column with the same name already exists on this table, should we replace it with this new Column?
	 * @return bool True if the column was added successfully, false otherwise (may happen if the column exists and $replaceExisting == false)
	 */
	public function AddColumn(SmartColumn $Column, $replaceExisting=true){
		if($replaceExisting || !$this->ColumnExists($Column->ColumnName)){
			$Column->Table = $this; //make $this table the column's table
			$this->_columns[$Column->ColumnName] = $Column; //save this column with the column name as key

			if($this->AutoRefresh) $this->Refresh();
			return true;
		}
		return false;
	}

	/**
	 * Returns the $columnName Column. Shortcut: use array notation- $table['YOUR_COLUMN_NAME']
	 * @param string $columnName The name of the column to get.
	 * @return SmartColumn The column requested.
	 */
	public function GetColumn($columnName){
		if($this->_columns[$columnName]){ //the actual column exists
			return $this->_columns[$columnName];
		}
		else if($this->_columnAliases[$columnName]){ //a column alias exists
			$realColumnName = $this->_columnAliases[$columnName];
			return $this->_columns[$realColumnName];
		}
		else throw new Exception("Invalid column: '$columnName'");
	}

	/**
	 * Removes a column from this table. If the given $columnName does not exist, an exception is thrown.
	 * If removing bulk columns, you may want to disable $AutoRefresh until all column management is complete, then make a single call to Refresh()
	 * @see SmartTable::Refresh()
	 * @see SmartTable::$AutoRefresh
	 * @param string $columnName The name of the column to remove.
	 * @return bool Always returns true. If the given $columnName does not exist, an exception is thrown.
	 * @see SmartTable::ColumnExists()
	 */
	public function RemoveColumn($columnName){
		if(!$this->_columns[$columnName]) throw new Exception("Invalid column: '$columnName'");

		$this->_columns[$columnName]->Table = null; //$this table is no longer the column's table
		unset($this->_columns[$columnName]);
		if($this->AutoRefresh) $this->Refresh();
		return true;
	}

	/**
	 * Returns true if the column exists, false otherwise.
	 * @param string $columnName
	 * @return bool true if the column exists, false otherwise.
	 */
	public function ColumnExists($columnName){
		return ($this->_columns[$columnName] != null || $this->_columnAliases[$columnName] != null);
	}

/////////////////////////////// Table Inheritance ///////////////////////////////////
	/**
	 * If this table inherits another table, this is the name of that other table
	 * <p>NOTE - Currently only supports inheriting 1 table! This will likely change this at some point down the road</p>
	 * @todo Figure out a way to inherit from multiple tables across the board
	 * @see SmartTable::$IsAbstract
	 * @var string The name of the table that $this table inherits from.
	 */
	private $_inheritsTableName = null;

	/**
	 * If this table inherits another table, this is the name of that other table.
	 * <p>NOTE - Currently only supports inheriting 1 table! This will likely change this at some point down the road</p>
	 * @see SmartTable::$IsAbstract
	 * @return string The name of the table that $this table inherits from.
	 */
	public function GetInheritedTableName(){
		return $this->_inheritsTableName;
	}
	/**
	 * All columns (and relations associated with those columns) from the given $Table are added to this $Table (structure only, no data!)
	 * @see SmartTable::$IsAbstract
	 * @see SmartTable::GetInheritedTableName()
	 * @param SmartTable $Table
	 */
	public function InheritColumnsFromTable(SmartTable $Table){
		if($this->_inheritsTableName) throw new Exception("Table {$this->TableName} can only inherit from 1 table! Currently inherits table {$this->_inheritsTableName}, trying to also inherit table {$Table->TableName}");
		$this->_inheritsTableName = $Table->TableName;

		$inheritCols = $Table->GetAllColumns();
		foreach($inheritCols as $columnName=>$Column){
			$inheritCol = clone $Column; //clone so when AddColumn() changes the Table of the Column, it doesnt affect the original Column's Table reference
			$columnAdded = $this->AddColumn($inheritCol, false); //false so we don't replace existing columns. Need this for table inheritance since the regular columns are added first, then the inherited columns. dont want to replace a regular column wiht an inherited one

			if($columnAdded){ //inherit relations from $Column
				$relations = $Column->GetRelations();
				if(count($relations)>0){ //this column has relations
					$inheritCol->AddRelation($Table->TableName, $columnName); //add a relation between this table and the original table
					foreach($relations as $tableName=>$relColumns){
						foreach($relColumns as $relColumnName=>$nothing){ //add relation between this table and the inherited columns that were set from the original table
							$this->Database->GetTable($tableName)->GetColumn($relColumnName)->AddRelation($this->TableName, $inheritCol->ColumnName);
						}
					}
				}
			}
		}
	}

/////////////////////////////// Invoke ///////////////////////////////////
	/**
	 * NEW WITH PHP 5.3.0, A shortcut for ->GetNewRow() (if no parameters are passed) and ->LookupRow() (if parameters are passed)
	 * Example usage: $smartdb['tablename'](212) instead of $smartrow['tablename']->LookupRow(212)
	 * Example usage: $smartdb['tablename']() instead of $smartrow['tablename']->GetNewRow()
	 * @param mixed $lookupVals Either 1) empty (will return a new row in this case), 2) An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). OR 3) As a shorthand, if the table contains a single primary key column, $lookupVals can be the value of that column to lookup instead of an array, ie 421
	 * @return SmartRow A Row instance matching the criteria of $lookupVals or a new row if $lookupVals was not given. The returned lookup row may or may not Exist()
	 * @see SmartTable::LookupRow()
	 * @see SmartTable::GetNewRow()
	 * @ignore
	 */
	public function __invoke($lookupVals=null){
		if($lookupVals===null) return $this->GetNewRow();
		else return $this->LookupRow($lookupVals);
	}

/////////////////////////////// ArrayAccess ///////////////////////////////////
	/**
	 * Adds a column to the table. $key doesnt matter at all. Uses the $Column->ColumnName as the column name always.
	 * So $Table[]=$Column; ... $Table['columnname']=$Column; ... $Table[123]=$Column; --- are all the same!
	 * @ignore
	 */
    public function offsetSet($key,$Column){
		if(!($Column instanceof SmartColumn)) throw new Exception("Can only add Column instances to a Table using array notation.");
		$this->AddColumn($Column);
	}
	/**
	 * @ignore
	 */
	public function offsetGet($columnName){
	    return $this->GetColumn($columnName);
	}
	/**
	 * @ignore
	 */
	public function offsetUnset($columnName){
	    $this->RemoveColumn($columnName);
	}
	/**
	 * @ignore
	 */
	public function offsetExists($columnName){
	    return $this->ColumnExists($columnName);
	}

/////////////////////////////// Countable ///////////////////////////////////
	/**
	 * For the Countable interface, this allows us to do count($table) to return the number of rows in the table.
	 * @return int The number of rows in this table.
	 */
	public function count() {
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
        return $dbManager->Select(array('1'), $this->TableName, '', '', '', array('add-column-quotes'=>false, 'add-dot-notation'=>false, 'force-select-db'=>true));
    }

/////////////////////////////// Table Data Functions ///////////////////////////////////

	private $_arrayMaxDepth; //used internally to track max depth of the lookupAssoc in VerifyLookupAssoc()
    /**
     * Verifies the lookup assoc is valid (column names are correct). If column name aliases are used... aliases will be updated with their actual value.
     * @param array $lookupAssoc The lookup assoc to work with
     * @param unknown_type $functionName The function that called this function... use __FUNCTION__. For debugging.
     * @ignore
     */
	public function VerifyLookupAssoc(array $lookupAssoc, $functionName){
		if(count($lookupAssoc) <= 0) throw new Exception('$lookupAssoc array is required function "'.$functionName.'", but could probably be changed to not be required to get all rows.');

		$this->_arrayMaxDepth = 0; //INTERNAL GLOBAL! this is determined within VerifyLookupAssocHelper

		try{ 
			foreach($lookupAssoc as $key=>$val){
				$this->VerifyLookupAssocHelper($val, $key, $lookupAssoc);
			}
		}
		catch(Exception $e){
			throw new Exception("Bad lookup values for function '".$functionName."': ".$e->getMessage());		
		}

		if($this->_arrayMaxDepth <= 1){ //not a multi-dimension array... wrap another array around this one so we AND by default instead of OR (as the DbManager does for backwards compatibility)
			$lookupAssoc = array($lookupAssoc); //extra array needed for the DbManager
		}
		
		return $lookupAssoc;
	}
	
    /**
     * Recursive function for VerifyLookupAssoc() that verifies all keys in the array are column names or keywords (ie "AND", "OR", "<", "!=", etc)
     * @ignore
     */
    private function VerifyLookupAssocHelper($val, $key, &$lookupAssoc, $depth=0){
    	if(++$depth > $this->_arrayMaxDepth) $this->_arrayMaxDepth = $depth; //track array's depth
		if(is_array($val)){
			foreach($val as $subKey=>$subVal){
				$this->VerifyLookupAssocHelper($subVal, $subKey, $lookupAssoc, $depth);
			}
		}
		
		//verify $key is numeric, a column name, or a keyword (ie "AND", "OR", "<", "!=", etc)
		if( !is_numeric($key) && !$this->Database->DbManager->IsKeyword($key) ){ //not numeric or a keyword. must be a column
			if(!$this->ColumnExists($key)) throw new Exception("Column '{$key}' does not exist in table {$this->TableName}");
			
			//check aliases
			$realColumnName = $this->GetColumn($key)->ColumnName; //gets the real column name in case the $key is a column alias
			if($key != $realColumnName){ //if these are different, we have a column alias
				//transfer alias lookup settings to the actual column
				$lookupAssoc[$realColumnName] = $lookupAssoc[$key];
				unset($lookupAssoc[$key]); //unset alias
			}		
		}
    }

	/**
	 * Returns an array of all Row instances that match the given $lookupAssoc column value, or an empty array if there is no match. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key.
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key (if a non-composite primary key exists on the table. otherwise this option is ignored) and the Row instance as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 *  }
	 * </code>
	 * @param array $lookupAssoc An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] See description
	 * @return mixed An array of all Row instances matching the criteria of $lookupAssoc, or if the option 'return-count-only'=true, returns an integer of number of rows selected
	 * @see SmartColumn::LookupRows()
	 * @see SmartTable::LookupRow()
	 * @see SmartTable::GetAllRows()
	 */
	public function LookupRows(array $lookupAssoc, array $options=null){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key");

		$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);

		//table must have a single primary key column
		if($this->PrimaryKeyIsComposite()) throw new Exception("Function '".__FUNCTION__."' not yet implemented for composite keys.");
		$keyColumnNames = array_keys($this->GetKeyColumns());
		$keyColumnName = $keyColumnNames[0];

		$limit = trim($options['limit']);
		$sortByFinal = $this->BuildSortArray($options['sort-by']);
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		$numRowsSelected = $dbManager->Select(array($keyColumnName), $this->TableName, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		$options['return-count'] = $numRowsSelected;

		//check the 'return-count-only' option
		if($options['return-count-only']) return $numRowsSelected;

		$returnVals = array();
		if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[$row[$keyColumnName]] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
			else{ //return a regular array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
		}
		else {
			if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
				trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[$row[$keyColumnName]] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
			else{ //return a regular array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
		}
		return $returnVals;
	}

	/**
	 * Looks up a row instance matching the criteria of $lookupVals. The returned row may or may not Exist
	 * As a shortcut, invoking the SmartTable directly will call LookupRow, i.e., $smartdb['tablename'](212) instead of $smartdb['tablename']->LookupRow(212) or $smartdb['tablename']->LookupRow(array('id'=>212))
	 * @param mixed $lookupVals Either 1) An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). OR 2) As a shorthand, if the table contains a single primary key column, $lookupVals can be the value of that column to lookup instead of an array, ie 421
	 * @return SmartRow A Row instance matching the criteria of $lookupVals. The returned row may or may not Exist
	 * @see SmartRow::Exists()
	 * @see SmartColumn::LookupRows()
	 * @see SmartTable::LookupRows()
	 */
	public function LookupRow($lookupVals){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key");

		//handle $lookupVals as a non-array... only works if column is a non-composite primary key
		if(!is_array($lookupVals)){ // NON-ARRAY. special case where $lookupVals is just the key column value
			if(!$this->PrimaryKeyExists() || $this->PrimaryKeyIsComposite()) throw new Exception('$lookupVals can only be a non-array if the table contains a single primary key column.');

			$keyColumnValue = $lookupVals;
			$keyColumns = $this->GetKeyColumns();
			$keyColumnNameInArray = array_keys($keyColumns);
			$keyColumnName = $keyColumnNameInArray[0];
			$lookupVals = array($keyColumnName=>$keyColumnValue);
		}

		$lookupVals = $this->VerifyLookupAssoc($lookupVals, __FUNCTION__);

		//table must have a single primary key column
		if($this->PrimaryKeyIsComposite()) throw new Exception("Function '".__FUNCTION__."' not yet implemented for composite keys.");
		$keyColumnNames = array_keys($this->GetKeyColumns());
		$keyColumnName = $keyColumnNames[0];

		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		$numRowsSelected = $dbManager->Select(array($keyColumnName), $this->TableName, $lookupVals, '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));

		if($numRowsSelected > 1) throw new Exception("Returned more than 1 row when looking up a single row.");

		$returnVal = null;
		if ($row = $dbManager->FetchAssoc()) { //match
			$returnVal = $row[$keyColumnName];
			if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
				return new $this->ExtendedByClassName($this->Database, $returnVal);
			}
			else {
				if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
					trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

				return new SmartRow($this->TableName, $this->Database, $returnVal);
			}
		}
		else { //no match. return a new instance with this value set.
			if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
				$row = new $this->ExtendedByClassName($this->Database);
			}
			else {
				if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
					trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

				$row = new SmartRow($this->TableName, $this->Database);
			}
			
			//try to set the column values to what was looked up since the row was not found
			//force disable commit changes immediately in this case
			$defaultCommitVal = $this->AutoCommit;
			$this->AutoCommit = false;
			
			//filter column names in the lookup assoc
			$flattenedLookupVals = array();
			foreach($lookupVals as $key=>$val){
				$this->FlattenLookupAssoc($flattenedLookupVals, $key, $val);
			}

			//set values on the row to be returned
			foreach($flattenedLookupVals as $columnName=>$value){
				$column = $this->GetColumn($columnName);
				if($column->AllowSet && !$column->IsAutoIncrement){
					$row->Cell($columnName)->SetValue($value);
				}
			}

			//set commit changes immediately back to its old value
			$this->AutoCommit = $defaultCommitVal;
			return $row;
		}
	}
	
	/**
	 * Takes a lookupAssoc array and flattens it to an array of just columnName => value for all elements of the array
	 * Only sets columnName => value if the given lookupAssoc sets the column equal to a particular value. if it sets the column to a condition or tries to lookup multiple values, the final returned array will not include that column
	 */
	private function FlattenLookupAssoc(&$flattenedLookupAssoc, $key, $val, $column='', $condition='=', $operator='AND', $first=true){
		$key = trim($key);
		$keyIsKeyword = false; //if the key is not a keyword and not numeric, it is assumed to be the column name
	 
		if( ($newCondition = $this->Database->DbManager->IsCondition($key)) ){ //check if key is a condition
			$condition = $newCondition;
			$keyIsKeyword = true;
		}
		else if( ($newOperator = $this->Database->DbManager->IsOperator($key)) ){ //check if key is an operator
			$operator = $newOperator;
			$keyIsKeyword = true;
		}
		else if(!is_numeric($key)){ //if the key is not a keyword and not numeric, it is assumed to be the column name
			$column = $key;
		}
		
		//$val can either be a scalar or an array
		if( is_array($val) ){ //value is an array, recurse.
			foreach($val as $nextKey=>$nextVal){
				$this->FlattenLookupAssoc($flattenedLookupAssoc, $nextKey, $nextVal, $column, $condition, $operator, false);
			}
		}
		else{ //$val is a scalar. this is the end of the recursion
			$column = trim($column,"` "); //clean up field name
			if(!$column) throw new Exception("No column has been defined");
	
			//only track columns with an '=' condition and columns that are not defined multiple times
			if($condition == "=" && !array_key_exists($column, $flattenedLookupAssoc) ){
				$flattenedLookupAssoc[$column] = $val; 
			}
			else {
				$flattenedLookupAssoc[$column] = "_~-+!ignore!+-~_"; //hopefully this is unique enough :)
			}
		}
		
		if($first){
			//on final pass, remove any ___ignore___ columns
			foreach($flattenedLookupAssoc as $key=>$val){
				if($val === "_~-+!ignore!+-~_") unset($flattenedLookupAssoc[$key]);
			}
		}
	}

	/**
	 * Returns an assoc array of [id of table's key column (if exists. otherwise values are simply pushed onto the array with no key specified)]=>[value of the column specified in $returnColumn]. Or if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key (if a non-composite primary key exists on the table. otherwise this option is ignored) and the $returnColumn's value as its value. ie array("2"=>$returnColumnValue,...) instead of just array($returnColumnValue,...);
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of values being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 *  }
	 * </code>
	 * @param array $lookupAssoc An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param string $returnColumn The name of the column to return the values of
	 * @param array $options [optional] See description
	 * @return mixed An assoc array of [id of table's key column (if exists. otherwise values are simply pushed onto the array with no key specified)]=>[value of the column specified in $returnColumn]. Or if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 */
	public function LookupColumnValues(array $lookupAssoc, $returnColumn, array $options=null){
		if(!$this->ColumnExists($returnColumn)) throw new Exception("Bad return column for function '".__FUNCTION__."': Column '{$returnColumn}' does not exist in table {$this->TableName}");

		$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);

		$limit = trim($options['limit']);
		$sortByFinal = $this->BuildSortArray($options['sort-by']);
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");

		$returnVals = array();
		if($this->PrimaryKeyIsNonComposite()){
			//table must have a single primary key column
			$keyColumnNames = array_keys($this->GetKeyColumns());
			$keyColumnName = $keyColumnNames[0];

			$numRowsSelected = $dbManager->Select(array($keyColumnName, $returnColumn), $this->TableName, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
			$options['return-count'] = $numRowsSelected;

			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[$row[$keyColumnName]] = $row[$returnColumn];
				}
			}
			else{ //return a regular array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[] = $row[$returnColumn];
				}
			}
		}
		else{ // no primary key
			$numRowsSelected = $dbManager->Select(array($returnColumn), $this->TableName, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
			$options['return-count'] = $numRowsSelected;

			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			while ($row = $dbManager->FetchAssoc()) {
				$returnVals[] = $row[$returnColumn];
			}
		}
		return $returnVals;
	}

	/**
	 * Returns the value of the column found, or FALSE if no row exists matching the criteria of $lookupAssoc
	 * Note: this function will throw an exception if more than 1 row is found matching the criteria of $lookupAssoc
	 * @param array $lookupAssoc An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param string $returnColumn The name of the column to return the value of
	 * @return mixed The value of the column found, or FALSE if no row exists matching the criteria of $lookupAssoc
	 */
	public function LookupColumnValue(array $lookupAssoc, $returnColumn){
		$foundColumns = $this->LookupColumnValues($lookupAssoc, $returnColumn);
		if(count($foundColumns)==0) return false; //row not found
		if(count($foundColumns)>1) throw new Exception("Returned more than 1 row when looking up a single row.");

		$vals = array_values($foundColumns);
		return $vals[0];
	}
	
	/**
	 * Deletes the row instance matching the criteria of $lookupVals. Returns number of rows deleted (1 or 0)
	 * NOTE: any columns in $lookupAssoc must be a key or unique!!! We need to ensure that we'll only have 1 row.
	 * @param mixed $lookupAssoc Either 1) An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). OR 2) As a shorthand, if the table contains a single primary key column, $lookupVals can be the value of that column to lookup instead of an array, ie 421
	 * @return int number of rows deleted (1 or 0)
	 * @see SmartRow::Delete()
	 * @see SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteRows()
	 * @see SmartTable::DeleteAllRows()
	 */
	public function DeleteRow($lookupAssoc){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key");

		//handle $lookupAssoc as a non-array... only works if column is a non-composite primary key
		if($lookupAssoc && !is_array($lookupAssoc)){ // NON-ARRAY. special case where $lookupAssoc is just the key column value
			if($this->PrimaryKeyIsComposite()) throw new Exception('$lookupAssoc can only be a non-array if the table contains a single primary key column.');

			$keyColumnValue = $lookupAssoc;
			$keyColumns = $this->GetKeyColumns();
			$keyColumnNameInArray = array_keys($keyColumns);
			$keyColumnName = $keyColumnNameInArray[0];
			$lookupAssoc = array($keyColumnName=>$keyColumnValue);
		}
		else if(is_array($lookupAssoc)){
			//make sure we're only looking up with key/unique columns
			foreach($lookupAssoc as $columnName=>$lookupVal){
				$thisCol = $this->GetColumn($columnName);
				if( !$thisCol->IsUnique && !$thisCol->IsPrimaryKey ){
					throw new Exception('The $lookupAssoc parameter passed to function "'.__FUNCTION__.'" must only contain unique columns or key columns. Column "'.$columnName.'" is neither.');
				}
			}
		}

		$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);

		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		return $dbManager->Delete($this->TableName, $lookupAssoc, 1, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
	}

	/**
	 * Deletes all rows with where the column values matches the passed $lookupAssoc
	 * @param array $lookupAssoc An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @return int the number of rows deleted
	 * @see SmartRow::Delete()
	 * @see SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteAllRows()
	 * @see SmartTable::DeleteRow()
	 */
	public function DeleteRows(array $lookupAssoc){
		$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);

		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		return $dbManager->Delete($this->TableName, $lookupAssoc, '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
	}

	/**
	 * Deletes all rows in the table
	 * @return int the number of rows deleted
	 * @see SmartRow::Delete()
	 * @see SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteRow()
	 * @see SmartTable::DeleteRows()
	 */
	public function DeleteAllRows(){
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		return $dbManager->Delete($this->TableName, '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
	}

	/**
	 * Looks up an array of all Row instances that belong to this table, or an empty array if there are no matches. The returned array's keys=$primaryKeyValue, value=$Row. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key, but this could probably be changed.
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 *  }
	 * </code>
	 * @param array $options [optional] See description
	 * @return mixed An array of Row instances that belong to this table, or an empty array if there are no matches. The returned array's keys=$primaryKeyValue, value=$Row. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key, but this could probably be changed.
	 * @see SmartColumn::LookupRows()
	 * @see SmartTable::LookupRows()
	 * @todo Make all tables work with this function
	 */
	public function GetAllRows(array $options=null){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key, but could probably be changed to work for any table structure");

		//table must have a single primary key column
		if($this->PrimaryKeyIsComposite()) throw new Exception("Function '".__FUNCTION__."' not yet implemented for composite keys.");
		$keyColumnNames = array_keys($this->GetKeyColumns());
		$keyColumnName = $keyColumnNames[0];

		$limit = trim($options['limit']);
		$sortByFinal = $this->BuildSortArray($options['sort-by']);
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		$numRowsSelected = $dbManager->Select(array($keyColumnName), $this->TableName, '', $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		$options['return-count'] = $numRowsSelected;

		//check the 'return-count-only' option
		if($options['return-count-only']) return $numRowsSelected;

		$returnVals = array();
		if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[$row[$keyColumnName]] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
			else{ //return a regular array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
		}
		else {
			if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
				trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[$row[$keyColumnName]] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
			else{
				while ($row = $dbManager->FetchAssoc()) {
					$returnVals[] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
		}

		return $returnVals;
	}

	/**
	 * Returns a new row from this table that can be added to the table by ->Commit(). Equivalent to creating a new instance of $this->ExtendedByClassName (if defined) or "new SmartRow($this->TableName, $this->Database);"
	 * As a shortcut, invoking the SmartTable directly with no parameters will call GetNewRow, i.e., $smartdb['tablename']() instead of $smartdb['tablename']->GetNewRow()
	 * @return SmartRow A new row from this table
	 * @see SmartRow::__construct()
	 */
	public function GetNewRow(){
		if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
			return new $this->ExtendedByClassName($this->Database);
		}
		else {
			if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
				trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

			return new SmartRow($this->TableName, $this->Database);
		}
	}

////////////////////////// PRIVATE HELPER CLASSES //////////////////////////
	/**
	 * Mostly for internal use. Verifies columns exist and returns an array that works with the DbManager for sorting
	 * @param mixed $sortBy is either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * @ignore
	 */
	public function BuildSortArray($sortBy){
		$sortByFinal = null;
		if(is_array($sortBy)){
			$sortByFinal = array();
			foreach($sortBy as $key=>$val){
				if(!is_numeric($key)){ //$key is column name. 'asc' or 'desc' is the $val
					$sortBy = $key;
					$sortOrder = strtolower(trim($val));
					if(empty($sortOrder) || ($sortOrder != "asc" && $sortOrder != "desc") ){
						$sortOrder = "asc"; //default
					}
				}
				else{ //$val is column name ($key is nothing). default to 'asc'
					$sortBy = $val;
					$sortOrder = "asc"; //default
				}

				if($this->ColumnExists($sortBy)) { //column exists
					$sortByFinal[$sortBy] = $sortOrder;
				}
				else{
					throw new Exception("Invalid sort column: $sortBy");
				}
			}
		}
		else if (is_string($sortBy) && !empty($sortBy)){ //use a string as a single column name to sort by
			$sortBy = $sortBy;
			$sortOrder = "asc"; //default for a single string column
			if($this->ColumnExists($sortBy)) { //column exists. default to ASC
				$sortByFinal[$sortBy] = $sortOrder;
			}
			else{
				throw new Exception("Invalid sort column: $sortBy");
			}
		}
		return $sortByFinal;
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