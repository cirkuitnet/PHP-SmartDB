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
 * Tables contain table properties and Columns (which contain column properties). No user/db data is on a Table level. See Rows and Cells.
 */
/**
 */
require_once(dirname(__FILE__).'/SmartColumn.php');
/**
 * Tables contain table properties and Columns (which contain column properties). No user/db data is on a Table level. See Rows and Cells.
 * @package SmartDatabase
 */
class SmartTable implements ArrayAccess, Countable{
	
	
	/////////////////////////////// SERIALIZATION - At top so we don't forget to update these when we add new vars //////////////////////////
		/**
		 * Specify all variables that should be serialized
		 * @ignore
		 */
		public function __sleep(){
			return array(
				'Database',
				'TableName',
				'IsAbstract',
				'AutoCommit',
				'ExtendedByClassName',
				'_inheritsTableNames',
				'_columns',
				'_columnAliases',
				'_keyColumns',
				'_nonKeyColumns',
				'_autoIncrementKeyColumns',
				'_nonAutoIncrementKeyColumns',
				'_primaryKeyExists',
				'_primaryKeyIsComposite',
				'_primaryKeyIsNonComposite',
				'_primaryKeyIsNonCompositeAutoIncrement',
				'_primaryKeyIsNonCompositeNonAutoIncrement',
				'AutoRefresh'
			);
		}
	//////////////////////////////////////////////////////////////////////////////////////
	
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
	 * @see SmartTable::InheritColumnsFromTable() SmartTable::InheritColumnsFromTable()
	 * @see SmartTable::GetInheritedTableNames() SmartTable::GetInheritedTableNames()
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
	 * Constructor
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
		
	private $_storedDbManagers = array(); //key is the function name, value is the DbManager results to cache

	private $_primaryKeyExists;
	private $_primaryKeyIsComposite;
	private $_primaryKeyIsNonComposite;
	private $_primaryKeyIsNonCompositeAutoIncrement;
	private $_primaryKeyIsNonCompositeNonAutoIncrement;

	/**
	 * Returns an assoc of all Columns. The returned array's key=$columnName, value=$Column
	 * @return array An assoc of all Columns. The returned array's key=$columnName, value=$Column
	 * @see SmartColumn SmartColumn
	 */
	public function GetAllColumns(){
		return $this->_columns;
	}

	/**
	 * Returns an assoc of all column aliases. The returned array's key=$columnAlias, value=$realColumnName
	 * @return array An assoc of all column aliases. The returned array's key=$columnAlias, value=$realColumnName
	 * @see SmartColumn SmartColumn
	 */
	public function GetAllColumnAliases(){
		return $this->_columnAliases;
	}

	/**
	 * Returns an assoc of all key columns. The returned array's key=$columnAlias, value=$realColumnName
	 * @return array
	 */
	public function GetKeyColumns(){
		return $this->_keyColumns;
	}
	/**
	 * Returns an assoc of all non key columns. The returned array's key=$columnAlias, value=$realColumnName
	 * @return array
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
	 * @see SmartTable::Refresh() SmartTable::Refresh()
	 * @see SmartTable::AddColumn() SmartTable::AddColumn()
	 * @see SmartTable::RemoveColumn() SmartTable::RemoveColumn()
	 * @var bool
	 */
	public $AutoRefresh = true;

	/**
	 * Mostly for internal use. Re-computes column aliases, key columns, and etc from $this->_columns.
	 * 
	 * This function gets called automatically from AddColumn() and RemoveColumn() unless $AutoRefresh is set to false (in which case, you'll need to call this function yourself when you have finished adding/removing columns).
	 * 
 	 * As an optimization, you may want to disable AutoRefresh when you are adding/removing bulk columns to a table, then make a single call to Refresh() once all column management is complete.
	 * @see SmartTable::$AutoRefresh SmartTable::$AutoRefresh
	 * @see SmartTable::AddColumn() SmartTable::AddColumn()
	 * @see SmartTable::RemoveColumn() SmartTable::RemoveColumn()
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
	 * @see SmartTable::Refresh() SmartTable::Refresh()
	 * @see SmartTable::$AutoRefresh SmartTable::$AutoRefresh
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
	 * @see SmartTable::Refresh() SmartTable::Refresh()
	 * @see SmartTable::$AutoRefresh SmartTable::$AutoRefresh
	 * @param string $columnName The name of the column to remove.
	 * @return bool Always returns true. If the given $columnName does not exist, an exception is thrown.
	 * @see SmartTable::ColumnExists() SmartTable::ColumnExists()
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
	 * If this table inherits other tables, this array will contain the names of the other table
	 * @see SmartTable::$IsAbstract SmartTable::$IsAbstract
	 * @var string The name of the table that $this table inherits from.
	 */
	private $_inheritsTableNames = [];

	/**
	 * If this table inherits other tables, the returned array will contain the names of the other table.
	 * Will be an empty array if no inheritance
	 * @see SmartTable::$IsAbstract SmartTable::$IsAbstract
	 * @return array The name of the table that $this table inherits from.
	 */
	public function GetInheritedTableNames(){
		return $this->_inheritsTableNames;
	}
	/**
	 * DEPRECATED - Used GetInheritedTableNames()
	 * @deprecated
	 * @ignore;
	 */
	public function GetInheritedTableName(){
		return $this->GetInheritedTableNames();
	}
	/**
	 * All columns (and relations associated with those columns) from the given $Table are added to this $Table (structure only, no data!)
	 * @see SmartTable::$IsAbstract SmartTable::$IsAbstract
	 * @see SmartTable::GetInheritedTableNames() SmartTable::GetInheritedTableNames()
	 * @param SmartTable $Table
	 */
	public function InheritColumnsFromTable(SmartTable $Table){
		if( in_array($Table->TableName, $this->_inheritsTableNames) ) return true; //already inherited
		$this->_inheritsTableNames[] = $Table->TableName;

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
	 * @see SmartTable::LookupRow() SmartTable::LookupRow()
	 * @see SmartTable::GetNewRow() SmartTable::GetNewRow()
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
        return $dbManager->Select(array('1'), $this, '', '', '', array('add-column-quotes'=>false, 'add-dot-notation'=>false, 'force-select-db'=>true));
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

		//This extra array wrapping can be ignored once we are sure the DbManager will always AND by default for multi-elements within the first dimension of lookup arrays
		if($this->_arrayMaxDepth <= 1){ //not a multi-dimension array... wrap another array around this one so we AND by default instead of OR (as the DbManager does for backwards compatibility)
			$lookupAssoc = array($lookupAssoc); //extra array needed for the backwards compatibility within DbManager
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
	 * Checks if the "return-next-row" key is set in the given $options array and verifies that it is passed by reference.
	 * Returns true if the above is true, false if not, and throws an exception if the key is set but is not passed by reference.
	 * @ignore
	 */
	private function CheckReturnNextRow(array $options=null, $storedDbManager, $functionName){
		//check that the 'return-next-row' option is set
		if(!$options || !array_key_exists('return-next-row', $options)){
			return false; //option not not set
		}
		
		//verify the 'return-next-row' option is passed by reference. it MUST be
		$curVal = $options['return-next-row'];
		
		//copy options array and test if the 'return-next-row' is passed by reference
		$optionsCopy = $options;
		$optionsCopy['return-next-row'] = -1;
		if($options['return-next-row']!==-1){ //not passed by reference
			throw new \Exception('The "return-next-row" $option must be passed by reference to function "'.$functionName.'" - i.e. array( "return-next-row" => &$curCount )');
		}
		
		//'return-next-row' is passed by reference.
		//restore the original return-next-row option.
		if(!is_int($curVal)) $curVal = 0; //make sure the original is an int
		else if($curVal && !$storedDbManager){ //if $curVal is >0 here, then we're returning the NEXT row and should have a cached dbmanager to use
			$curVal = 0; //no cached dbmanager? assume this is the first call and we should return the first row of the result set
		}
		
		$options['return-next-row'] = $curVal;
		
		return $curVal;
	}

	/**
	 * Returns an array of all Row instances that match the given $lookupAssoc column values, or an empty array if there are no matches. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'callback'=>null,		//function - if set, this function will be invoked for each row and the full result set will NOT be returned- only the LAST ROW in the set will be returned (if there is one). the function's signature should be function($row, $i){} where $row is the actual SmartRow, and $i is the 1-based index of the row being returned
	 * 	'return-assoc'=>false, 	//if true, the returned assoc-array will have the row's primary key column value as its key (if a non-composite primary key exists on the table. otherwise this option is ignored) and the Row instance as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 * 	'return-next-row'=>null, //OUT variable. integer. if you set this parameter in the $options array, then this function will return only 1 row of the result set at a time. If there are no rows selected or left to iterate over, null is returned.
	 *  						// THIS PARAMETER MUST BE PASSED BY REFERENCE - i.e. array( "return-next-row" => &$curCount ) - the incoming value of this parameter doesn't matter and will be overwritten)
	 *  						// After this function is executed, this OUT variable will be set with the number of rows that have been returned thus far.
	 *  						// Each consecutive call to this function with the 'return-next-row' option set will return the next row in the result set, and also increment the 'return-next-row' variable to the number of rows that have been returned thus far
	 * 	'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 * )
	 * ```
	 * @param array $lookupAssoc [optional] An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). If this is left null or empty array, all rows will be returned.
	 * @param array $options [optional] See description
	 * @return mixed An array of all Row instances matching the criteria of $lookupAssoc, or if the option 'return-count-only'=true, returns an integer of number of rows selected
	 * @see SmartColumn::LookupRows() SmartColumn::LookupRows()
	 * @see SmartTable::LookupRow() SmartColumn::LookupRow()
	 * @see SmartTable::GetAllRows() SmartColumn::GetAllRows()
	 */
	public function LookupRows($lookupAssoc=null, array $options=null){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key, but could probably be changed to work for any table structure");
		
		//table must have a single primary key column
		if($this->PrimaryKeyIsComposite()) throw new Exception("Function '".__FUNCTION__."' not yet implemented for composite keys.");
		$keyColumnNames = array_keys($this->GetKeyColumns());
		$keyColumnName = $keyColumnNames[0];
		
		//get DbManager we need to use. could be a stored result set if $returnNextRow > 0
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");

		//check for the 'return-next-row' option to return 1 row at a time
		$returnNextRow = $this->CheckReturnNextRow($options, $this->_storedDbManagers['LookupRows'], __FUNCTION__); //returns INT >= 0 or FALSE
		
		//check the 'callback' option
		$callback = $options['callback'];
		if($callback && !is_callable($callback)){
			//verify we have an anonymous function or a function name in global scope
			throw new Exception("Callback function '$callback' does not exist.");
		}

		//do the query if we're not returning the next row from our result set
		if(!$returnNextRow || $returnNextRow==0){ //spelling out both cases for clarity - $returnNextRow could be 0 or false here. both should run the new query
			if($this->_storedDbManagers['LookupRows']){  //clear any cached result sets we may have
				$this->_storedDbManagers['LookupRows']->FlushResults();
				unset($this->_storedDbManagers['LookupRows']);
			}
			
			//$lookupAssoc is not required. if no $lookupAssoc is given, this will be identical to GetAllRows().
			if($lookupAssoc){
				$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);
			}
			
			//do the new query
			$limit = trim($options['limit']);
			$sortByFinal = $this->BuildSortArray($options['sort-by']);
			$numRowsSelected = $dbManager->Select(array($keyColumnName), $this, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
			$options['return-count'] = $numRowsSelected;
	
			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;
		}

		//fetch SQL results
		if($returnNextRow!==false){ //specifically check for FALSE here (zero is valid for returning the 1st row of the set)
			//if we're returning the first row of a result set, we need to store this particular result dbmanager to iterate over later, so clone our current DbManager and save it to this Table object
			if($returnNextRow===0){
				$this->_storedDbManagers['LookupRows'] = clone $dbManager;
			}

			//return just a single result at a time from the stored DbManager's result set
			$sqlResult = $this->_storedDbManagers['LookupRows']->FetchAssoc(); //just 1 row.
			if(!$sqlResult) return null; //no more rows to fetch in this result set
			
			$options['return-next-row']++; //track the number of records we've returned
			
			//take SQL results and return SmartRows
			$smartRow = $this->ReturnSmartRows($sqlResult, $keyColumnName, $options['return-assoc'], $returnNextRow);
			
			//callback?
			if($callback){
				//call the user's function
				call_user_func($callback, $smartRow, $options['return-next-row']);
			}
			
			return $smartRow;
		}
		else if($callback){
			$numRows = $dbManager->NumRows();
			$smartRow = null;
			
			//if we're returning one row at a time, we need to store this particular result dbmanager to iterate over later, so clone our current DbManager and save it to this Table object
			$this->_storedDbManagers['LookupRows-Callback'] = clone $dbManager;
			for ($i=1; $i <= $numRows; $i++) {
				//get row
				$sqlResult = $this->_storedDbManagers['LookupRows-Callback']->FetchAssoc();
				$smartRow = $this->ReturnSmartRows($sqlResult, $keyColumnName, $options['return-assoc'], true);
				
				//call the user's function
				call_user_func($callback, $smartRow, $i);
			}
			
			//clear cached result sets we may have
			if($this->_storedDbManagers['LookupRows-Callback']){  //clear any cached result sets we may have
				$this->_storedDbManagers['LookupRows-Callback']->FlushResults();
				unset($this->_storedDbManagers['LookupRows-Callback']);
			}
			
			return $smartRow;
		}
		else{ //'return-next-row' option is not set. return array of all results
			$sqlResult = $dbManager->FetchAssocList(); //get an array of all of the rows
			
			//take SQL results and return SmartRows
			return $this->ReturnSmartRows($sqlResult, $keyColumnName, $options['return-assoc'], $returnNextRow);
		}
		
	}
	
	private function ReturnSmartRows($sqlResult, $keyColumnName, $returnAssoc=false, $returnNextRow=false){
		//take SQL results and return SmartRows
		$smartRows = array();
		if($this->ExtendedByClassName && class_exists($this->ExtendedByClassName,true)){
			if($returnNextRow!==false){ //specifically check for FALSE here (zero is valid for returning the 1st row of the set)
				//return only a single row at a time
				$smartRows = new $this->ExtendedByClassName($this->Database, $sqlResult[$keyColumnName]);
			}
			else if($returnAssoc){ //return all rows as an assoc array with row-key as array-key
				foreach ($sqlResult as $row) {
					$smartRows[$row[$keyColumnName]] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
			else{ //return all rows pushed onto an array
				foreach ($sqlResult as $row) {
					$smartRows[] = new $this->ExtendedByClassName($this->Database, $row[$keyColumnName]);
				}
			}
		}
		else {
			if($this->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
				trigger_error("Warning: no class reference found for Table '{$this->TableName}'. ExtendedByClassName = '{$this->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);
		
			if($returnNextRow!==false){ //specifically check for FALSE here (zero is valid for returning the 1st row of the set)
				//return only a single row at a time
				$smartRows = new SmartRow($this->TableName, $this->Database, $sqlResult[$keyColumnName]);
			}
			else if($returnAssoc){ //return all rows as an assoc array with row-key as array-key
				foreach ($sqlResult as $row) {
					$smartRows[$row[$keyColumnName]] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
			else{ //return all rows pushed onto an array
				foreach ($sqlResult as $row) {
					$smartRows[] = new SmartRow($this->TableName, $this->Database,$row[$keyColumnName]);
				}
			}
		}
		return $smartRows;
	}

	/**
	 * Looks up an a row that matches the given column $value. If there is no match, an instance is still returned but ->Exists() will be false. The returned row will have the searched columns=>values set by default (excluding auto-increment primary key columns)
	 * To execute this function, this table must have a primary key. Throws an exception if more than 1 row is returned.
	 * As a shortcut, invoking the SmartTable directly will call LookupRow, i.e., $smartdb['tablename'](212) instead of $smartdb['tablename']->LookupRow(212) or $smartdb['tablename']->LookupRow(array('id'=>212))
	 * @param mixed $lookupVals Either 1) An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). OR 2) As a shorthand, if the table contains a single primary key column, $lookupVals can be the value of that column to lookup instead of an array, ie 421
	 * @return SmartRow A Row instance matching the criteria of $lookupVals. The returned row may or may not Exist
	 * @see SmartRow::Exists() SmartRow::Exists()
	 * @see SmartColumn::LookupRows() SmartRow::LookupRows()
	 * @see SmartTable::LookupRows() SmartRow::LookupRows()
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
		$numRowsSelected = $dbManager->Select(array($keyColumnName), $this, $lookupVals, '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));

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
	 * @param array $flattenedLookupAssoc internal
	 * @param string $key internal
	 * @param mixed $val internal
	 * @param string $column internal
	 * @param string $condition internal
	 * @param string $operator internal
	 * @param string $first internal
	 * @throws Exception throws exception if an invalid column name is included
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
	 * Gets all values in all rows for the given $returnColumn, optionally unique and sorted. Optionally in an assoc with the primary key column value as the assoc's key value. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'get-unique'=>false, //If true, only unique values will be returned. Note: array keys in the returned array will NOT be the key column when this is true)
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key (if a non-composite primary key exists on the table. otherwise this option is ignored) and the $returnColumn's value as its value. ie array("2"=>$returnColumnValue,...) instead of just array($returnColumnValue,...);
	 * 	'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of values being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 * }
	 * ```
	 * @param string $returnColumn The name of the column to return the values of (can be null only for reverse compatibility)
	 * @param array $lookupAssoc [optional] An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). If this is left null or empty array, all column values for all rows will be returned.
	 * @param array $options [optional] See description
	 * @return mixed An array of key-value pairs. The keys are either 1: nothing, or 2: the primary key (if 'return-assoc' option is TRUE and 'get-unique' option is false and the table has a primary key), and the values are the actual column values. Alternatively, if the option 'return-count-only'=true, returns an integer of number of rows selected.
	 */
	public function LookupColumnValues($returnColumn=null, $lookupAssoc=null, array $options=null){
		//reverse-compatibility: $lookupAssoc used to be first parameter, then $returnColumn was second. handle both situations
		if((!$returnColumn && $lookupAssoc) || is_array($returnColumn)){ //if this is true "$returnColumn" is actually "$lookupAssoc" (reverse compatibility). need to swap these parameters
			$swapParams = $lookupAssoc; //tmp store the actual $returnColumn (in $lookupAssoc) so we can overwrite $lookupAssoc
			$lookupAssoc = $returnColumn; //overwrite $lookupAssoc to be the actual lookup array
			$returnColumn = $swapParams; //overwrite $returnColumn with the actual column
		}
		
		if(!$this->ColumnExists($returnColumn)) throw new Exception("Bad return column for function '".__FUNCTION__."': Column '{$returnColumn}' does not exist in table {$this->TableName}");

		//column may be an alias and/or may need to unserialize the returned data. get the actual column and check if it's serialized
		$Column = $this->GetColumn($returnColumn);
		$returnColumn = $Column->ColumnName; //override $returnColumn name. handles aliases
		$isSerializedColumn = $Column->IsSerializedColumn;
		
		//$lookupAssoc is not required. if no $lookupAssoc is given, this will work similar to SmartColumn->GetAllValues().
		if($lookupAssoc){
			$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);
		}

		$limit = trim($options['limit']);
		$sortByFinal = $this->BuildSortArray($options['sort-by']);
		$dbManager = $this->Database->DbManager;
		if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");

		$returnVals = array();
		if($this->PrimaryKeyIsNonComposite() && !$options['get-unique']){
			//table must have a single primary key column
			$keyColumnNames = array_keys($this->GetKeyColumns());
			$keyColumnName = $keyColumnNames[0];

			$numRowsSelected = $dbManager->Select(array($keyColumnName, $returnColumn), $this, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
			$options['return-count'] = $numRowsSelected;

			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			if($options['return-assoc']){ //return an assoc array
				while ($row = $dbManager->FetchAssoc()) {
					$colValue = $row[$returnColumn];
					if($isSerializedColumn){ //unserialize serialized values
						$colValue = $Column->GetUnserializedValue($colValue); 
					}
					$returnVals[$row[$keyColumnName]] = $colValue;
				}
			}
			else{ //return a regular array
				while ($row = $dbManager->FetchAssoc()) {
					$colValue = $row[$returnColumn];
					if($isSerializedColumn){ //unserialize serialized values
						$colValue = $Column->GetUnserializedValue($colValue); 
					}
					$returnVals[] = $colValue;
				}
			}
		}
		else{ // no primary key or returning UNIQUE results only
			$numRowsSelected = $dbManager->Select(array($returnColumn), $this, $lookupAssoc, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true, 'distinct'=>$options['get-unique']));
			$options['return-count'] = $numRowsSelected;

			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			while ($row = $dbManager->FetchAssoc()) {
				$colValue = $row[$returnColumn];
				if($isSerializedColumn){ //unserialize serialized values
					$colValue = $Column->GetUnserializedValue($colValue); 
				}
				$returnVals[] = $colValue;
			}
		}
		return $returnVals;
	}

	/**
	 * Returns the value of the column found, or FALSE if no row exists matching the criteria of $lookupAssoc
	 * Note: this function will throw an exception if more than 1 row is found matching the criteria of $lookupAssoc
	 * @param string $returnColumn The name of the column to return the value of
	 * @param array $lookupAssoc [optional] An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). If empty, nothing is filtered (an exception is thrown if more than 1 value is returned) 
	 * @return mixed The value of the column found, or FALSE if no row exists matching the criteria of $lookupAssoc
	 */
	public function LookupColumnValue($returnColumn, $lookupAssoc=null){
		$foundColumns = $this->LookupColumnValues($returnColumn, $lookupAssoc);
		if(count($foundColumns)==0) return false; //row not found
		if(count($foundColumns)>1) throw new Exception("Returned more than 1 row when looking up a single row.");

		$vals = array_values($foundColumns);
		return $vals[0];
	}
	
	/**
	 * Deletes the row instance matching the criteria of $lookupVals. Returns number of rows deleted (1 or 0)
	 * NOTE: any columns in $lookupAssoc must be a key or unique!!! We need to ensure that we'll only have 1 row.
	 * Throws an exception if more than 1 row is returned.
	 *
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-callbacks'=>false //If true, all row-level "Delete" callbacks will be skipped. This can substantially improve the performance of very large bulk deletions.
	 * )
	 * ```
	 * @param mixed $lookupAssoc Either 1) An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...). OR 2) As a shorthand, if the table contains a single primary key column, $lookupVals can be the value of that column to lookup instead of an array, ie 421
	 * @param array $options [optional] See description
	 * @return int number of rows deleted (1 or 0)
	 * @see SmartRow::Delete() SmartRow::Delete()
	 * @see SmartColumn::DeleteRows() SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteRows() SmartTable::DeleteRows()
	 * @see SmartTable::DeleteAllRows() SmartTable::DeleteAllRows()
	 */
	public function DeleteRow($lookupAssoc, array $options=null){
		if(!$this->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' only works on Tables that contain a primary key");

		//skipping delete callbacks on the row-level will delete rows directly on the DB level for efficiency
		if($options['skip-callbacks']){ //yes, skip callbacks. faster.
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
			return $dbManager->Delete($this, $lookupAssoc, 1, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		}
		else{ //dont skip callbacks. need to lookup the row and delete the row directly. slower than the above
			$Row = $this->LookupRow($lookupAssoc);
			if( $Row->Delete() ) return 1; //1 row deleted
			else return 0; //0 rows deleted
		}
	}

	/**
	 * Deletes all rows with where the column values matches the passed $lookupAssoc
	 * 
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-callbacks'=>false //If true, all row-level "Delete" callbacks will be skipped. This can substantially improve the performance of very large bulk deletions.
	 * )
	 * ```
	 * @param array $lookupAssoc An assoc-array of column=>value to lookup. For example: array("column1"=>"lookupVal", "column2"=>"lookupVal", ...)
	 * @param array $options [optional] See description
	 * @return int the number of rows deleted
	 * @see SmartRow::Delete() SmartRow::Delete()
	 * @see SmartColumn::DeleteRows() SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteAllRows() SmartTable::DeleteAllRows()
	 * @see SmartTable::DeleteRow() SmartTable::DeleteRow()
	 */
	public function DeleteRows(array $lookupAssoc, array $options=null){
		//skipping delete callbacks on the row-level will delete rows directly on the DB level for efficiency
		if($options['skip-callbacks']){ //yes, skip callbacks. faster.
			$lookupAssoc = $this->VerifyLookupAssoc($lookupAssoc, __FUNCTION__);
	
			$dbManager = $this->Database->DbManager;
			if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
			return $dbManager->Delete($this, $lookupAssoc, '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		}
		else{ //dont skip callbacks. need to lookup each row and delete each row directly. slower than the above
			$deletedCount = 0;
			while($Row = $this->LookupRows($lookupAssoc, array('return-next-row'=>&$curCount) )){
				//delete row and increase count if successful
				if( $Row->Delete() ) $deletedCount++;
			}
			return $deletedCount;
		}
	}

	/**
	 * Deletes all rows in the table
	 * 
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'skip-callbacks'=>false //If true, all row-level "Delete" callbacks will be skipped. This can substantially improve the performance of very large bulk deletions.
	 * )
	 * ```
	 * @param array $options [optional] See description
	 * @return int the number of rows deleted
	 * @see SmartRow::Delete() SmartRow::Delete()
	 * @see SmartColumn::DeleteRows() SmartColumn::DeleteRows()
	 * @see SmartTable::DeleteRow() SmartTable::DeleteRow()
	 * @see SmartTable::DeleteRows() SmartTable::DeleteRows()
	 */
	public function DeleteAllRows(array $options=null){
		//skipping delete callbacks on the row-level will delete rows directly on the DB level for efficiency
		if($options['skip-callbacks']){ //yes, skip callbacks. faster.
			$dbManager = $this->Database->DbManager;
			if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
			return $dbManager->Delete($this, '', '', array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		}
		else{ //dont skip callbacks. need to lookup each row and delete each row directly. slower than the above
			$deletedCount = 0;
			while($Row = $this->GetAllRows( array('return-next-row'=>&$curCount)) ){
				//delete row and increase count if successful
				if( $Row->Delete() ) $deletedCount++;
			}
			return $deletedCount;
		}
	}

	/**
	 * Looks up an array of all Row instances that belong to this table, or an empty array if there are no matches. The returned array's keys=0-indexed iterator (or if the 'return-assoc' option is true, keys are the row's primary key value), value=$Row. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key, but this could probably be changed.
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'callback'=>null,		//function - if set, this function will be invoked for each row and the full result set will NOT be returned- only the LAST ROW in the set will be returned (if there is one). the function's signature should be function($row, $i){} where $row is the actual SmartRow, and $i is the 1-based index of the row being returned
	 * 	'return-assoc'=>false,	//if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 * 	'return-next-row'=>null, //OUT variable. integer. if you set this parameter in the $options array, then this function will return only 1 row of the result set at a time. If there are no rows selected or left to iterate over, null is returned.
	 *  						// THIS PARAMETER MUST BE PASSED BY REFERENCE - i.e. array( "return-next-row" => &$curCount ) - the incoming value of this parameter doesn't matter and will be overwritten)
	 *  						// After this function is executed, this OUT variable will be set with the number of rows that have been returned thus far.
	 *  						// Each consecutive call to this function with the 'return-next-row' option set will return the next row in the result set, and also increment the 'return-next-row' variable to the number of rows that have been returned thus far
	 * 	'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 *  }
	 * ```
	 * @param array $options [optional] See description
	 * @return mixed An array of Row instances that belong to this table, or an empty array if there are no matches. The returned array's keys=$primaryKeyValue, value=$Row. If the option 'return-count-only'=true, returns an integer of number of rows selected. To execute this function, this table must have a primary key, but this could probably be changed.
	 * @see SmartColumn::LookupRows() SmartColumn::LookupRows()
	 * @see SmartTable::LookupRows() SmartTable::LookupRows()
	 * @todo Make all tables work with this function
	 */
	public function GetAllRows(array $options=null){
		return $this->LookupRows(null, $options);
	}

	/**
	 * Returns a new row from this table that can be added to the table by ->Commit(). Equivalent to creating a new instance of $this->ExtendedByClassName (if defined) or "new SmartRow($this->TableName, $this->Database);"
	 * As a shortcut, invoking the SmartTable directly with no parameters will call GetNewRow, i.e., $smartdb['tablename']() instead of $smartdb['tablename']->GetNewRow()
	 * @return SmartRow A new row from this table
	 * @see SmartRow::__construct() SmartRow::__construct()
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
					//column may be an alias. get the actual column name
					$Column = $this->GetColumn($sortBy);
					$sortBy = $Column->ColumnName; //override $sortBy name with actual name
					
					//set final sort val
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
				//column may be an alias. get the actual column name
				$Column = $this->GetColumn($sortBy);
				$sortBy = $Column->ColumnName; //override $sortBy name with actual name
				
				//set final sort val
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
