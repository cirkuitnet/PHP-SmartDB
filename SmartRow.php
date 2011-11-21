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
require_once(dirname(__FILE__).'/SmartCell.php');
/**
 * Manages a single row within a table of the database.
 * <p>It is recommended that you use SmartTable::GetNewRow() and SmartTable::LookupRow() instead of calling the SmartRow constructor explicitly. SmartTable::GetNewRow() provides a bottom-up method for creating new rows (which matches the rest of the SmartDb). SmartTable::LookupRow() provides a method for returning a single row based on the primary key (or, optionally, other columns). Calling this constructor explicitly will work all the same though (ex: "$newrow = new Product($db);" to get a new row, or "$row = new Product($db, 4);" to get the row with id 4)</p>
 * <p>To invoke this constructor, you can either do:</p>
 * <code>
 * $row = new SmartRow("YOUR_TABLE_NAME", $Database, [$keyColumnValues=null], [$options=null]);
 * </code>
 * <br>
 * <p>Or extend your own class from the SmartRow. For example:</p>
 * <code>
 * <?
 * class YOUR_CLASS_NAME extends SmartRow{
 * 	public function __construct($Database, $keyColumnValues=null, $options=null){
 * 		parent::__construct('YOUR_TABLE_NAME', $Database, $keyColumnValues, $options);
 * 	}
 * 	//implement custom functionality using $this
 * 
 * 	//---------- START EXAMPLE CUSTOM FUNCTIONALITY ------------//
 * 	//formats an alpha-numeric "Price" column
 * 	//if we have any alpha characters, return the price as is. otherwise, we'll format it up  (i.e. could return "$12.43" or "CALL FOR PRICE")
 * 	public function GetFormattedPrice(){
 * 		$price = $this['Price'](); //$this is the current SmartRow instance, containing our row data for/from the database
 * 		if(strlen($price)==0) return "";
 * 		foreach(str_split($price) as $char){
 * 			if(ctype_alpha($char)){
 * 				return $price;
 * 			}
 * 		}
 * 		return '$'.number_format(self::ParseFloat($price), 2);
 * 	}
 * 	//helper function - turns something like "$12,300.50" or "$12300.50" or  "12300.50" into "12300.5", which can then be formatted exactly as needed.
 * 	private static function ParseFloat($value){
 * 		return floatval(preg_replace('#^([-]*[0-9\.,\' \$]+?)((\.|,){1}([0-9-]{1,2}))*$#e', "str_replace(array('.', ',', \"'\", ' ', '$'), '', '\\1') . '.\\4'", $value));
 * 	}
 * 	//---------- END EXAMPLE CUSTOM FUNCTIONALITY ------------//
 * }
 * ?>
 * </code>
 * <p>Or make your own class available to be extended upon further:</p>
 * <code>
 * <?
 * class YOUR_CLASS_NAME extends SmartRow{
 * 	// There are 2 signatures for this (which allows for extending on this class, but using a different table)
 * 	// 1. __construct($Database, $keyColumnValues=null, $options=null) //uses table "YOUR_TABLE_NAME"
 * 	// 2. __construct($tableName, $Database, $keyColumnValues=null, $options=null) //uses table $tableName
 * 	public function __construct($arg1, $arg2=null, $arg3=null, $arg4=null){
 * 		if(is_string($arg1)) parent::__construct($arg1, $arg2, $arg3, $arg4);
 * 		else parent::__construct("YOUR_TABLE_NAME", $arg1, $arg2, $arg3);
 * 	}
 *  //implement custom functionality using $this
 * }
 * ?>
 * </code>
 * <p>Note: if creating your own class that extends SmartRow, YOUR_CLASS_NAME should be the same on that is set for SmartTable::$ExtendedByClassName</p>
 * @see SmartRow::__construct()
 * @see SmartTable::GetNewRow()
 * @see SmartTable::LookupRow()
 * @see SmartTable::$ExtendedByClassName
 * @package SmartDatabase
 */
class SmartRow implements ArrayAccess{
	

	/////////////////////////////// SERIALIZATION - At top so we don't forget to update these when we add new vars //////////////////////////
		/**
		 * Specify all variables that should be serialized
		 * @ignore
		 */
		public function __sleep(){
			return array(
				'Database',
				'Table',
				'_cells',
				'_disableCallbacks',
				'_isDirty',
				'_existsInDb',
				'_initialized',
				'_onBeforeCommit',
				'_onAfterCommit',
				'_onBeforeInsert',
				'_onAfterInsert',
				'_onBeforeUpdate',
				'_onAfterUpdate',
				'_onBeforeDelete',
				'_onAfterDelete',
				'_onSetAnyCellValue',
				'_onBeforeAnyCellValueChanged',
				'_onAfterAnyCellValueChanged',
				'_onSetCellValueCallbackInitialized',
				'_onBeforeAnyCellValueChangedCallbackInitialized',
				'_onAfterAnyCellValueChangedCallbackInitialized'
			);
		}
	//////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * @var SmartDatabase Note: if setting the $Database, be sure to set the $DbManager is set appropriately
	 */
	public $Database;
	/**
	 * @var SmartTable The table that contains this row
	 */
	public $Table;

	/**
	 * @var DbManager The DbManager from the Database passed through the constructor, unless explicitly set otherwise
	 */
	public $DbManager;


	private $_cells = array(); //Key is the column name, value is the Cell instance.

	private $_isDirty; //true when changes need to be committed to the database
	private $_existsInDb; //true when this instance represents a row that exists in the database; false if this row is not yet in the database
	private $_initialized; //true if initial column values have been pulled from the database (if the row exist in the database. if it does not, this bool will be false)

	/**
	 * Manages a single row within the table specified in $tableName
	 * <p>If $dbManager is null, exceptions will be thrown anytime this class attempts to access the database. This should almost never be null except under special circumstances when you will not use the database at all, but need an 'in-memory' instance only</p>
	 * <p>If the key column values are not passed to the constructor, the default will be null and this is assumed to be a new row to be inserted into the database.</p>
	 * <p>If the key column values are passed, the row in the database with that key value will be looked up and used.</p>
	 * <p>However, if the table's key is auto-increment and the passed key value does not exist in the database, an exception will be thrown whenever data is read or modified.</p>
	 * <p><b>$options is an assoc-array, as follows:</b></p>
	 * <code>
	 * $options = array(
	 * 	'use-default-values'=true, //if set to false, the default values will not be set on any Cell of the new row
	 * );
	 * </code>
	 * @param string $tableName
	 * @param SmartDatabase $Database
	 * @param mixed $keyColumnValues [optional] If null, this row is assumed to be a new row. If not null: (1) For tables with multiple key columns, must be an assoc-array of columnName=>value of each key column. (2) For tables with 1 key column, this can be a single value of the key column to lookup (though the array will still work)
	 * @param array $options [optional] See description
	 * @return SmartRow
	 */
	public function __construct($tableName, SmartDatabase $Database, $keyColumnValues=null, array $options=null){
		if(!$tableName) throw new Exception('$tableName is not set.');
		if(!$Database) throw new Exception('$Database is not set.');

		$this->Database = $Database; //save the Database
		$this->DbManager = $Database->DbManager; //save the dbManager for easy access
		$this->Table = $Database->GetTable($tableName);

		//initialize all Cells of this row
		$useDefaultValues = (!isset($options['use-default-values']) ? true : $options['use-default-values'] );
		foreach($this->Table->GetAllColumns() as $columnName=>$Column){
			$this->_cells[$columnName] = new SmartCell($Column, $this);

			if(isset($Column->DefaultValue) && $useDefaultValues) { //this column has a default value
				$this->_cells[$columnName]->ForceValue($Column->DefaultValue);
			}
		}

		//handle $keyColumnValues that may have been passed through
		if($keyColumnValues){
			if(is_array($keyColumnValues)){ //ARRAY
				foreach($keyColumnValues as $columnName=>$value){
					$Column = $this->Cell($columnName);
					if(!$Column->IsPrimaryKey) throw new Exception("Column '$columnName' is not a key column for table '$tableName'.");

					$Column->ForceValue($value);
				}
			}
			else{ //1 NON-ARRAY
				$keyColumns = $this->Table->GetKeyColumns();
				if(count($keyColumns) === 0) throw new Exception("'keyColumnValues' must not be set for '$tableName' since it contains no key columns.");
				if(count($keyColumns) > 1) throw new Exception("'keyColumnValues' must be an assoc-array when passed to Row constructor for table '$tableName' since it contains '".count($keyColumns)."' key columns.");

				$keyColumnNameInArray = array_keys($keyColumns);
				$keyColumnName = $keyColumnNameInArray[0];
				$this->Cell($keyColumnName)->ForceValue($keyColumnValues);
			}
		}

		$this->_isDirty = false;
		$this->_initialized = false;
	}

	/**
	 * Returns true if changes have been made to this instance's data that need to be committed to the database; false otherwise
	 * @return bool true if changes have been made to this instance's data that need to be committed to the database; false otherwise
	 */
	public function IsDirty(){
		return $this->_isDirty;
	}

	/**
	 * Returns true if initial column values have been pulled from the database (if the row exist in the database. if it does not, this will return false)
	 * @return bool true if initial column values have been pulled from the database (if the row exist in the database. if it does not, this will return false)
	 */
	public function IsInitialized(){
		return $this->_initialized;
	}
	/**
	 * DEPRECATED - Used SmartRow::Refresh(). Forces re-initialization of this Row (pulling values from the database).
	 * <p>Good to use if you are storing a serialized version of this row and need to make sure all DB values are set.</p>
	 * <p>You shouldn't need to use this much; it is mostly for internal use. Initialization is made to be completely automatic.</p>
	 * @return bool	<b>true</b> if successfully retreived all columns from database, if this row has no key columns, or if this is a new row to insert later.<br><b>false</b> if this is not a new row and values were not retreived because the key column(s) was/were not found in database table
	 * @see SmartRow::Refresh()
	 * @deprecated
	 * @ignore;
	 */
	public function ForceInitialization(){
		if($this->_initialized == false) $this->TryGetDatabaseValues();
	}
	/**
	 * Forces re-initialization of this Row (pulling all values from the database for this id).
	 * <p>Good to use if you are storing a serialized version of this row and need to make sure all DB values are set.</p>
	 * <p>You shouldn't need to use this much; it is mostly for internal use. Initialization is made to be completely automatic.</p>
	 * @return bool	<b>true</b> if successfully retreived all columns from database, if this row has no key columns, or if this is a new row to insert later.<br><b>false</b> if this is not a new row and values were not retreived because the key column(s) was/were not found in database table
	 */
	public function Refresh(){
		if($this->_initialized == false) $this->TryGetDatabaseValues();
	}


	/**
	 * Returns true if the key column(s) represents a row that exists in the database; false if this row is not yet in the database
	 * @return bool true if the key column(s) represents a row that exists in the database; false if this row is not yet in the database
	 */
	public function Exists() {
		if($this->_initialized == false) {
			try{
				$this->TryGetDatabaseValues();
			} catch(Exception $e){
				return false;
			}
		}
		return $this->_existsInDb;
	}

	/**
	 * Returns a string of all current errors that exist within this row, or FALSE if no errors were found.
	 * Row should not be committed to the database until this function returns false.
	 * <code>
	 * $options = array(
	 *  'ignore-key-errors'=>false //If true: does not validate the key columns. If false: validates all columns
	 *  'only-verify-set-cells'=>false //If true: only cells that have been set (i.e. isset()==true) will be verified (not recommended if this info will be committed to db). If false: all cells will be verified (should be used if this info will be committed to db).
	 *  'error-message-suffix'=>"<br>\n" //appended to each error message
	 * </code>
	 * @see SmartRow::GetColumnsInError()
	 * @param array $options [optional] See description.
	 * @return mixed A string of current errors that exist within this cell, or FALSE if no errors were found
	 */
	public function HasErrors(array $options=null){
		$defaultOptions = array( //default options
			"error-message-suffix"=>"<br>\n",
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		$allErrorMsgs = false;
		foreach($this->_cells as $columnName=>$Cell){
			if(($errMsg = $Cell->HasErrors($options)) !== false) $allErrorMsgs .= $errMsg;
		}
		return $allErrorMsgs;
	}

	/**
	 * Returns an array of all cells that are currently have an erroneous value or an empty array if no errors exist in any cells. The returned array's Key=$columnName, Value=$Cell.
	 * Row should not be committed to the database until this function returns an empty array.
	 * <code>
	 * $options = array(
	 *  'ignore-key-errors'=>false //If true: does not validate the key columns. If false: validates all columns
	 *  'only-verify-set-cells'=>false //If true: only cells that have been set (i.e. isset()==true) will be verified (not recommended if this info will be committed to db). If false: all cells will be verified (should be used if this info will be committed to db).
	 * </code>
	 * @param array $options [optional] See description
	 * @see SmartRow::HasErrors()
	 * @return array an array of all cells that are currently have an erroneous value or an empty array if no errors exist in any cells. The returned array's Key=$columnName, Value=$Cell.
	 */
	public function GetColumnsInError(array $options=null){
		$errorCells = array();
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell->HasErrors($options)) $errorCells[$columnName] = $Cell;
		}
		return $errorCells;
	}

	/**
	 * Returns the Cell instance of this row from the specified $columnName. Shortcut: use array notation- $row['YOUR_COLUMN_NAME']
	 * Alias for ->Cell()
	 * @param string $columnName
	 * @return SmartCell The Cell of this row with the given $columnName
	 */
	public function Column($columnName){
		return $this->Cell($columnName);
	}
	/**
	 * Returns the Cell instance of this row from the specified $columnName. Can also use Column($columnName). Shortcut: use array notation- $row['YOUR_COLUMN_NAME']
	 * @see SmartRow::Column()
	 * @param string $columnName
	 * @return SmartCell The Cell of this row with the given $columnName
	 * @ignore
	 */
	public function Cell($columnName){
		if($this->_cells[$columnName]){ //the actual column exists
			return $this->_cells[$columnName];
		}
		else{
			$columnAliases = $this->Table->GetAllColumnAliases();
			if( ($realColumnName = $columnAliases[$columnName]) ){ //a column alias exists
				return $this->_cells[$realColumnName];
			}
			else throw new Exception("Invalid column: '$columnName'");
		}
	}
	/**
	 * Alias for GetAllCells()
	 * @see SmartRow::GetAllCells()
	 * @return array An array of all cell instances contained in this row. The array's key=$columnName, value=$Cell
	 */
	public function GetAllColumns(){
		return $this->_cells;
	}
	/**
	 * Returns an array of all cell instances of this row. The array's key=$columnName, value=$Cell
	 * @see SmartRow::GetAllColumns()
	 * @return array An array of all cell instances contained in this row. The array's key=$columnName, value=$Cell
	 * @ignore
	 */
	public function GetAllCells(){
		return $this->_cells;
	}


	/**
	 * Unsets all values in all cells of this row (doens't touch the keys columns though).
	 * This is good to use before using LookupKeys() to search for values because this will clear default value that any Column may have.
	 * @see SmartRow::LookupKeys()
	 */
	public function UnsetNonKeyColumnValues(){
		foreach($this->_cells as $columnName=>$Cell){
			if(!$Cell->IsPrimaryKey){ //dont touch the key columns
				$Cell->ForceUnset();
			}
		}

		$this->_isDirty = true;
		$this->_initialized = true;
	}

	/**
	 * Attempts to get all key columns from the assoc-array (including POST, GET, SESSION, etc.) that belong to this Row/Table and sets them as values in this Row (for columns in which setting is allowed)
	 * <p>NOTE- Any key column that is not set in the array will be set to NULL as part of the key!!!!! So be careful about leaving out key columns.</p>
	 * <p>$keyColumnValuesAssoc structure:</p>
	 * <code>
	 * $keyColumnValuesAssoc = array('TableName'=>array('KeyColumn1'=>value, ...))
	 * </code>
	 * @param array $keyColumnValuesAssoc The array("TableName"=>array("KeyColumn1"=>"value", "keyColumn2"=>"value",...)) that will be the new key values for this instance.
	 * @param bool $updateNewKeyRowIfAlreadyExists <b>If true</b>: if the newly defined key exists in the database, the row will be updated with the current values of this instance upon calling Commit()... but if the newly defined key does not exist, this row will be inserted as a new row.<br><b>If false</b>: if the newly defined key exists in the database, an exception is thrown and should be handled by the application... but if the newly defined key does not exist, the row will be inserted as a new row.
	 * @param bool $deleteOldKeyRowIfExists <b>If true</b>: if a row with the old key exists in the database (the one used in this Row prior to calling this method), it will be deleted (NOTE: old key row is deleted immediately if this is true, new row is not created in database until call to Commit()) .<br><b>If false</b>: if a row with the old key exists in the database (the one used in this Row prior to calling this method), it will not be deleted
	 * @return bool	true if successfully changed the key of this instance, false if key was not changed (only if an existing row has been found for the new key defined and parameter updateNewKeyRowIfAlreadyExists==false)
	 */
	public function SetKeyColumnValues(array $keyColumnValuesAssoc, $updateNewKeyRowIfAlreadyExists, $deleteOldKeyRowIfExists){
		if(count($this->Table->GetNonAutoIncrementKeyColumns()) <= 0) throw new Exception("Can only set keys for tables that have any non auto increment key columns. Table: '{$this->Table->TableName}' has none specified.");

		$keyChanged = false; //true if changes have been made to the key from what was previously defined before calling this function
		$tableName = $this->Table->TableName;
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell->IsPrimaryKey && !$Cell->IsAutoIncrement){ //only working with NON auto-increment key columns
				//get the passed value... either with the real column name or the first found alias (ignores multiple matches)
				$passedVal = $this->GetPassedValueForCell($tableName, $columnName, $Cell, $keyColumnValuesAssoc);

				//compare the passed key value to the current key value
				if($Cell->GetValue() != $passedVal){
					$keyChanged = true;
					break;
				}
			}
		}
		if ($keyChanged) {
			$nullKeyFound = false;
			foreach($this->_cells as $columnName=>$Cell){
				if($Cell->IsPrimaryKey && !$Cell->IsAutoIncrement){ //only working with NON auto-increment key columns
					if($Cell->GetValue() == null){
						$nullKeyFound = true;
					}
				}
			}
			$numRowsSelected = 0;
			if (!$nullKeyFound) {//check if this new key already exists in database
				//build the where clause array
				$whereArray = array();
				foreach($this->_cells as $columnName=>$Cell){
					if($Cell->IsPrimaryKey && !$Cell->IsAutoIncrement){ //only working with NON auto-increment key columns
						//get the passed value... either with the real column name or the first found alias (ignores multiple matches)
						$passedVal = $this->GetPassedValueForCell($tableName, $columnName, $Cell, $keyColumnValuesAssoc);
						$whereArray[0][$columnName] = $passedVal;
					}
				}

				if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
				$numRowsSelected = $this->DbManager->Select(array("1"), $this->Table, $whereArray, null, 1, array('add-where-clause-column-quotes'=>true, 'add-where-clause-dot-notation'=>true) );
			}
			$existingRowFound = ($numRowsSelected > 0);
			if ($existingRowFound && $updateNewKeyRowIfAlreadyExists==false) { // a row with this key already exists
				throw new Exception("An existing row has been found with new key defined in call to SetKeyColumnValues(). Erroring since parameter updateNewKeyRowIfAlreadyExists=false. Table: '{$this->Table->TableName}', Attempted New Key Column Values: ".print_r($keyColumnValuesAssoc, true));
			}

			if($deleteOldKeyRowIfExists){ //delete old row from database with old key from before calling this method
				$this->Delete();
			}

			//set new key columns
			foreach($this->_cells as $columnName=>$Cell){
				if($Cell->IsPrimaryKey && !$Cell->IsAutoIncrement){ //only working with NON auto-increment key columns
					//get the passed value... either with the real column name or the first found alias (ignores multiple matches)
					$passedVal = $this->GetPassedValueForCell($tableName, $columnName, $Cell, $keyColumnValuesAssoc);
					$Cell->ForceValue($passedVal);
				}
			}

			$this->_initialized = !$nullKeyFound;
			$this->_existsInDb = $existingRowFound;
			$this->_isDirty = ($this->_isDirty || $existingRowFound);

			if($this->Table->AutoCommit == true) $this->Commit(); //automatically commit changes now?
		}
	}
	/**
	 * Helper function for SetKeyColumnValues() above
	 * Get the passed value within $keyColumnValuesAssoc... either matches the real column name or the first found alias (ignores multiple matches)
	 * @param string $tableName
	 * @param string $columnName
	 * @param SmartCell $Cell
	 * @param array $keyColumnValuesAssoc
	 * @return mixed The matching object for this $Cell, found within $keyColumnValuesAssoc
	 */
	private function GetPassedValueForCell($tableName, $columnName, SmartCell $Cell, array $keyColumnValuesAssoc){
		//get the passed value... either with the real column name or first found alias (ignores multiple matches)
		if($keyColumnValuesAssoc[$tableName][$columnName]){ //real column name
			return $keyColumnValuesAssoc[$tableName][$columnName];
		}
		else{ //check aliased columns
			$aliases = $Cell->GetAliases();
			foreach($aliases as $alias=>$nothing){
				if($keyColumnValuesAssoc[$tableName][$alias]){ //real column name
					return $keyColumnValuesAssoc[$tableName][$alias];
				}
			}
		}
		return null;
	}

	/**
	 * Attempts to get all NON KEY columns from the assoc-array (including POST, GET, SESSION, etc.) that belong to this Row and sets them as values in this Row (for columns in which setting is allowed)
	 * <p>Any column that is not set in array will not be modified</p>
	 * <p>$assocArray structure:</p>
	 * <code>
	 * $assocArray = array('TableName'=>array('ColumnName1'=>value, ...))
	 * </code>
	 * @param array $assocArray see function description
	 */
	public function SetNonKeyColumnValues(array $assocArray){
		if($assocArray == null || count($assocArray) == 0) return;

		//check real columns
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell->AllowSet==false || $Cell->IsPrimaryKey) continue;

			if(isset($assocArray[$this->Table->TableName][$columnName])){ //real columns
				$Cell->SetValue($assocArray[$this->Table->TableName][$columnName]);
			}
			else if(isset($assocArray[$this->Table->TableName][$columnName.'_Notifier'])){ //real column notifier
				$Cell->SetValue($assocArray[$this->Table->TableName][$columnName.'_Notifier']); //notifier let us know this column was in use on the page, but as a checkbox, its POST does not get sent if the checkbox is not checked. so set the 'not checked' value
			}
			else{ //check column alias only as a last resort if the real column name was not found
				$aliases = $Cell->GetAliases();
				foreach($aliases as $alias=>$nothing){
					if(isset($assocArray[$this->Table->TableName][$alias])){ //aliased column
						$Cell->SetValue($assocArray[$this->Table->TableName][$alias]);
					}
					else if(isset($assocArray[$this->Table->TableName][$alias.'_Notifier'])){ //aliased column notifier
						$Cell->SetValue($assocArray[$this->Table->TableName][$alias.'_Notifier']); //notifier let us know this column was in use on the page, but as a checkbox, its POST does not get sent if the checkbox is not checked. so set the 'not checked' value
					}
				}
			}
		}
	}

	/**
	 * Attempts to get all key and non-key columns from the assoc-array (including POST, GET, SESSION, etc.) that belong to this Row and sets them as values in this Row (for columns in which setting is allowed)
	 * <p>NOTE- Any non-key column that is not set in the array will not have its value changed, but any key column that is not set in the array will be set to NULL as part of the key!!!!! So be careful about leaving out key columns.</p>
	 * <p>$assocArray structure:</p>
	 * <code>
	 * $assocArray = array('TableName'=>array('ColumnName1'=>value, 'ColumnName2'=>value, ...))
	 * </code>
	 * @param bool $updateNewKeyRowIfAlreadyExists <b>If true</b>: if the newly defined key exists in the database, the row will be updated with the current values of this instance upon calling Commit()... but if the newly defined key does not exist, this row will be inserted as a new row.<br><b>If false</b>: if the newly defined key exists in the database, an exception is thrown and should be handled by the application... but if the newly defined key does not exist, the row will be inserted as a new row.
	 * @param bool $deleteOldKeyRowIfExists <b>If true</b>: if a row with the old key exists in the database (the one used in this Row prior to calling this method), it will be deleted (NOTE: old key row is deleted immediately if this is true, new row is not created in database until call to Commit()) .<br><b>If false</b>: if a row with the old key exists in the database (the one used in this Row prior to calling this method), it will not be deleted
	 * @param array $assocArray see function description
	*/
	public function SetColumnValues(array $assocArray, $updateNewKeyRowIfAlreadyExists, $deleteOldKeyRowIfExists){
		$this->SetKeyColumnValues($assocArray, $updateNewKeyRowIfAlreadyExists, $deleteOldKeyRowIfExists);
		$this->SetNonKeyColumnValues($assocArray);
	}

	/**
	 * Returns an array of all KEY columns and thier current values: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * @param array &$assocArray [optional] If provided, the column values will be added to this array and returned. Will be populated as such: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * @param bool $onlyAddSetColumns [optional] <b>If false</b>, populates the provided assoc-array with ALL get-able key columns.<br><b>If true</b>, populates the provided assoc-array with only the get-able columns that have been set (i.e. isset()==true)
	 * @return array columns and values from this Row as an assoc array
	 */
	public function GetKeyColumnValues(array &$assocArray=null, $onlyAddSetColumns=false){
		if($assocArray == null){ $assocArray=array(); }
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell.AllowGet == false || !$Cell->IsPrimaryKey) continue;
			if(!$onlyAddSetColumns || ($onlyAddSetColumns && $Cell->IsValueSet())) $assocArray[$this->Table->TableName][$columnName] = $Cell->GetValue();
		}
		return $assocArray;
	}

	/**
	 * Returns an array of all NON-KEY columns and thier current values: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * <p>Internally: forces initialization</p>
	 * @param array &$assocArray [optional] If provided, the column values will be added to this array and returned. Will be populated as such: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * @param bool $onlyAddSetColumns [optional] <b>If false</b>, populates the provided assoc-array with ALL get-able columns.<br><b>If true</b>, populates the provided assoc-array with only the get-able key columns that have been set (i.e. isset()==true)
	 * @return array columns and values from this Row as an assoc array
	 */
	public function GetNonKeyColumnValues(array &$assocArray=null, $onlyAddSetColumns=false){
		if($assocArray == null){ $assocArray=array(); }
		if($this->_initialized == false) $this->TryGetDatabaseValues();
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell.AllowGet == false || $Cell->IsPrimaryKey) continue;
			if(!$onlyAddSetColumns || ($onlyAddSetColumns && $Cell->IsValueSet())) $assocArray[$this->Table->TableName][$columnName] = $Cell->GetValue();
		}
		return $assocArray;
	}

	/**
	 * Returns an array of columns and their current values: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * <p>ALL columns are returned by default (keys and non-key columns). Can filter these... see $options below</p>
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'only-add-set-columns'=>false, //If false, populates the provided assoc-array with ALL get-able key columns. If true, populates the provided assoc-array with only the get-able columns that have been set (i.e. isset()==true)
	 *  'get-key-columns'=>true, //if true, key columns are returned in the array. if false, key columns are not returned in the array
	 *  'get-non-key-columns'=>true, //if true, non-key columns are returned in the array. if false, non-key columns are not returned in the array
	 *  }
	 * </code>
	 * @param array &$assocArray [optional] If provided, the column values will be added to this array and returned. Will be populated as such: array("TableName"=>array("ColumnName"=>currentValue,...))
	 * @param array $options [optional] See description
	 * @return array columns and values from this Row as an assoc array
	 */
	public function GetColumnValues(array &$assocArray=null, $options=null){
		if($options && !is_array($options)){ //for reverse compatibility. the $options variable used to be a variable for $onlyAddSetColumns. false by default.
			$options = array();
			$options['only-add-set-columns'] = true;;
		}
		$defaultOptions = array( //default options
			'get-key-columns'=>true,
			'get-non-key-columns'=>true,
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		if($assocArray == null){ $assocArray=array(); }
		if($this->_initialized == false) $this->TryGetDatabaseValues();
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell.AllowGet == false) continue;
			if($options['get-key-columns'] == false && $Cell->IsPrimaryKey) continue; //ignore key columns
			if($options['get-non-key-columns'] == false && !$Cell->IsPrimaryKey) continue; //ignore non-key columns
			if(!$options['only-add-set-columns'] || ($options['only-add-set-columns'] && $Cell->IsValueSet())) $assocArray[$this->Table->TableName][$columnName] = $Cell->GetValue();
		}
		return $assocArray;
	}


	/**
	 * Looks up any column values that has been set on this row and returns an array of the key values that matched, or if the option 'return-count-only'=true, returns an integer of number of rows selected
	 * Options are as follows:
	 * <code>
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 *  'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   // With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 *  'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of values being returned. Usage ex: array('return-count'=>&$count)
	 *  'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search
	 *  }
	 * </code>
	 * @param bool $updateRowIfOneRowFound [optional] If true and only 1 row is found when looking for rows with matching column values, this row instance will be updated to manage the matching row. (if true, take precedence over the 'return-count-only' option)
	 * @param array $options [optional] See description
	 * @return mixed An array of key values that match the columns that have been set in this Row or if the option 'return-count-only'=true, returns an integer of number of rows selected
	 */
	public function LookupKeys($updateRowIfOneRowFound=true, array $options=null){
		$whereArray = array();
		$selectArray = array();
		$keyColumns = array();
		$keyColumnCount = 0;
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell->IsValueSet()) {
				$whereArray[0][$columnName] = $Cell->GetValue();
			}
			if($Cell->IsPrimaryKey){
				$selectArray[] = $columnName;
				$keyColumns[$columnName] = $Cell;
				$keyColumnCount++;
			}
		}

		if($keyColumnCount == 0) throw new Exception("Table {$this->Table->TableName} does not have any key columns defined. Cannot run this function.");
		if($keyColumnCount > 1) throw new Exception("Composite Keys not yet implemented. Shouldn't take much to do though...");

		$limit = trim($options['limit']);
		$sortByFinal = $this->Table->BuildSortArray($options['sort-by']);
		if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		$numRowsSelected = $this->DbManager->Select($selectArray, $this->Table, $whereArray, $sortByFinal, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true) );
		$options['return-count'] = $numRowsSelected;

		if ($updateRowIfOneRowFound && $numRowsSelected==1) { //1 row found
			$resultArray = $this->DbManager->FetchAssoc();

			//check if the 1 found row has the same key as $this row
			$keyDiffers = false;
			foreach($keyColumns as $columnName=>$Cell){
				if($Cell->GetValue() !== $resultArray[$columnName]) {
					$keyDiffers = true;
					break;
				}
			}

			if ($keyDiffers){ //different row. update this $row with the data from the database
				if(count($this->Table->GetNonAutoIncrementKeyColumns()) > 0) {
					$this->SetKeyColumnValues(array($this->Table->TableName=>$resultArray), true, false);
				} else {
					foreach($keyColumns as $columnName=>$Cell){
						$Cell->ForceValue($resultArray[$columnName]);
					}
					$this->_isDirty = false;
					$this->_initialized = false;
				}
			}

			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			return $resultArray;
		}
		else { //num or rows selected != 1
			//check the 'return-count-only' option
			if($options['return-count-only']) return $numRowsSelected;

			$resultArray = array();
			for($i=0; $i<$numRowsSelected; $i++){
				$thisResult = $this->DbManager->FetchArray();
				$resultArray[] = $thisResult[0];
			}
			return $resultArray;
		}
	}

	/**
	 * Creates a new row instance with the same column values set as the instance you are cloning (key column values are not set).
	 * @return SmartRow A new row instance with the same column values set as the instance you are cloning (key column values are not set).
	 */
	public function GetShallowClone(){
		$columnValuesArray = $this->GetNonKeyColumnValues();
		if($this->Table->ExtendedByClassName && class_exists($this->Table->ExtendedByClassName,true)) { //get the extended version of the class if exists
			$row = new $this->Table->ExtendedByClassName($this->Database);
		}
		else { //the extended version doesnt exist or is not in scope, so just return the Base class
			if($this->Table->ExtendedByClassName && $this->Database->DEV_MODE_WARNINGS)
				trigger_error("Warning: no class reference found for Table '{$this->Table->TableName}'. ExtendedByClassName = '{$this->Table->ExtendedByClassName}'. Make sure this value is not empty and that the file containing that class is included.", E_USER_WARNING);

			$row = new SmartRow($this->Table->TableName, $this->Database);
		}
		$row->SetNonKeyColumnValues($columnValuesArray);
		return $row;
	}

/////////////////////////////// The Major Row-To-Database Communications ///////////////////////////////////

	/**
	 * Gets all column values from the database and sets each as a local variable
	 * @todo add comments that explain what happens if no primary key exists
	 * @return bool	<b>true</b> if successfully retrieved all columns from database, if this Row/Table has no key columns, or if this is a new row to insert later.<br><b>false</b> if this is not a new row and values were not retreived because the key column(s) was/were not found in database table
	 */
	private function TryGetDatabaseValues() {
		if (!$this->Table->PrimaryKeyExists()) {
			//this row/table has no key columns, so nothing to look up
			$this->_initialized = true;
			$this->_existsInDb = false;
		}
		else { //some sort of primary key exists
			//make sure every primary key is set. if not, do not query the database
			if (count($this->Table->GetKeyColumns()) > 0) {
				//if key is not set, don't lookup row
				foreach($this->_cells as $columnName=>$Cell){
					if($Cell->IsPrimaryKey && $Cell->GetValue()==null){
						//primary key is not yet set in this row/table, so nothing is in the database to get.
						$this->_initialized = true;
						$this->_existsInDb = false;
						return $this->_initialized;
					}
				}
			}
			//primary key is set in this row/table, so get the record from the database that matches this primary key
			$whereArray = array();
			foreach($this->_cells as $columnName=>$Cell){
				if($Cell->IsPrimaryKey){
					$whereArray[0][$columnName] = $Cell->GetValue();
				}
			}
			if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
			$numRowsSelected = $this->DbManager->Select(array("*"), $this->Table, $whereArray, null, 1, array('add-column-quotes'=>true, 'add-dot-notation'=>true));

			if ($numRowsSelected == 1) { //successfully got matching row from database table
				$row = $this->DbManager->FetchAssoc();

				foreach($this->_cells as $columnName=>$Cell){
					if(!$Cell->IsPrimaryKey) { //set non-key column values
						$Cell->ForceValue($row[$Cell->ColumnName]);
					}
				}

				$this->_initialized = true;
				$this->_existsInDb = true;
			}
			else { //matching row not found in database table
				if ($this->Table->PrimaryKeyIsNonCompositeAutoIncrement()) { //primary key is auto-increment
					$this->_initialized = false;
					$this->_existsInDb = false;
					throw new Exception($numRowsSelected . " rows exist for primary key column(s) ".print_r($whereArray, true)." in table '".$this->Table->TableName."'. 1 row was expected.");
				}
				else { //primary key is not auto-increment
					$this->_initialized = true;
					$this->_existsInDb = false;
				}
			}
		}
		return $this->_initialized;
	}

	/**
	 * Commits all current column values of this row to the database
	 * @param bool $skipErrorChecking [optional] <b>If true</b>: the row will attempt to be committed to the database even if it has errors on the PHP level. Setting this to true is NEVER recommended unless you are absolutely certain you know what's up.
	 * @return int the number of rows affected by the update call. If the row was not dirty (if column values have not been modified), -1 will be returned
	 */
	public function Commit($skipErrorChecking=false){
		if(!$this->DbManager) throw new Exception("DbManager is null. Cannot commit changes to database when DbManager is null. Make sure you are passing an instance of DbManager within your Database to this row instance when you initialize it.");

		if($this->_initialized == false) $this->TryGetDatabaseValues();

		if(!$skipErrorChecking && ($errorMsg = $this->HasErrors())){
			throw new Exception("Cannot commit changes to database due to errors in input values:\n" . $errorMsg );
		}

		if($this->_existsInDb == false || !$this->Table->PrimaryKeyExists()){ //row is not in database yet. insert a new row
			if($this->_onBeforeCommit) $this->FireCallback($this->_onBeforeCommit, $e=array('cancel-event'=>&$cancelEvent));
			if($this->_onBeforeInsert) $this->FireCallback($this->_onBeforeInsert, $e=array('cancel-event'=>&$cancelEvent));
			if(!$cancelEvent){
				$numRowsInserted = $this->InsertAsNewRow(); //returns number of rows inserted. should be 1 on success, 0 on fail (or TRUE/FALSE)
				if($this->_onAfterInsert) $this->FireCallback($this->_onAfterInsert);
				if($this->_onAfterCommit) $this->FireCallback($this->_onAfterCommit);
			}
			return $numRowsInserted;
		}
		else{ //row exists in database. Update it
			if($this->_isDirty == false) return -1; //nothing to commit

			if($this->_onBeforeCommit) $this->FireCallback($this->_onBeforeCommit, $e=array('cancel-event'=>&$cancelEvent));
			if($this->_onBeforeUpdate) $this->FireCallback($this->_onBeforeUpdate, $e=array('cancel-event'=>&$cancelEvent));
			if(!$cancelEvent){
				$updateVals = array();
				$whereArray = array();
				foreach($this->_cells as $columnName=>$Cell){
					if(!$Cell->IsPrimaryKey && $Cell->AllowSet){
						$updateVals[$columnName] = $Cell->GetValue();
					}
					else if ($Cell->IsPrimaryKey) {
						$whereArray[0][$columnName] = $Cell->GetValue();
					}
				}

				if(count($whereArray[0]) <= 0) throw new Exception("WHERE clause is empty in an UPDATE statement");
				$numRowsUpdated = $this->DbManager->Update($this->Table, $updateVals, $whereArray, 1, array('add-column-quotes'=>true, 'add-dot-notation'=>true));

				$this->_isDirty = false; //all changes have been written to database. not dirty anymore

				if($this->_onAfterUpdate) $this->FireCallback($this->_onAfterUpdate);
				if($this->_onAfterCommit) $this->FireCallback($this->_onAfterCommit);
			}

			return $numRowsUpdated;
		}
	}

	/**
	 * Inserts the values of this instance as a new row in the database table
	 * @return int the number of rows affected by the insert statement (should be 1 on success, 0 if failure)
	 */
	private function InsertAsNewRow(){
		//build arrays
		$nonAutoIncrementKeyCells = array();
		$settableNonKeyCells = array();
		$keyCells = array();
		foreach($this->_cells as $columnName=>$Cell){
			if($Cell->IsPrimaryKey && !$Cell->IsAutoIncrement){
				if($Cell->GetValue() == null) throw new Exception("Non-AutoIncrement Key Column '{$columnName}' requires its value to be set before insertion to table");
				$nonAutoIncrementKeyCells[$columnName] = $Cell;
			}
			else if(!$Cell->IsPrimaryKey && $Cell->AllowSet){
				$settableNonKeyCells[$columnName] = $Cell;
			}

			if($Cell->IsPrimaryKey){
				$keyCells[] = $Cell;
			}
		}

		$insertValsArray = array();
		foreach ($nonAutoIncrementKeyCells as $columnName=>$Cell){
			$insertValsArray[$columnName] = $Cell->GetValue();
		}
		foreach ($settableNonKeyCells as $columnName=>$Cell){
			$insertValsArray[$columnName] = $Cell->GetValue();
		}

		if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		$numRowsInserted = $this->DbManager->Insert($this->Table, $insertValsArray, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
		if ($this->Table->PrimaryKeyIsNonCompositeAutoIncrement()) {
			$newColId = $this->DbManager->InsertId();
			if($numRowsInserted<=0 || $newColId==null || $newColId<0){ //error inserting new column into database
				$this->_existsInDb = false;
				$keyCells[0]->ForceValue(null);
				throw new Exception("Did not successfully insert row into ".$this->Table->TableName); //todo: handle this nicely?
			}
			else {
				//successfuly inserted column. set the new column unique key value
				$this->_existsInDb = true;
				$this->_isDirty = false;
				$keyCells[0]->ForceValue($newColId); //primary key now has an id that was chosen by auto_increment in database
			}
		}
		else { //no primary key or primary key exists and is composite or non-autoincrement
			if($numRowsInserted <= 0) throw new Exception("Did not successfully insert row into ".$this->Table->TableName); //todo: handle this nicely?
			$this->_existsInDb = true;
			$this->_isDirty = false;
		}
		return $numRowsInserted;
	}

	/**
	 * Deletes the row from the database with the key specified in this Row (if exists)
	 * Table must have a key column defined to use this function. There is no way we can be certain on which row to delete if no key columns are defined. Must use functions from the Table class to delete rows from tables with no key columns defined.
	 * @return bool true if the row was deleted, false if the row does not exist (if more than 1 row was deleted, an exception is thrown. this would indicate a severe problem with data consistency)
	 * @see SmartTable::DeleteRows()
	 */
	public function Delete(){
		//only delete if a key column is defined
		if(count($this->Table->GetKeyColumns()) <= 0) throw new Exception("Can only use this function if the defined table has at least key column defined. 0 defined for table: '{$this->Table->TableName}'");

		if ($this->_initialized == false) $this->TryGetDatabaseValues(); //not initialized yet. need to initialize to see if this row exists in database
		if ($this->_existsInDb == false) return false; //row does not exist to delete

		if($this->_onBeforeDelete) $this->FireCallback($this->_onBeforeDelete, $e=array('cancel-event'=>&$cancelEvent));

		if(!$cancelEvent){
			$whereArray = array();
			$keyCells = array();
			foreach($this->_cells as $columnName=>$Cell){
				if(!$Cell->IsPrimaryKey) continue;

				$whereArray[0][$columnName] = $Cell->GetValue();
				$keyCells[] = $Cell;
			}

			$limit = ($this->Table->PrimaryKeyExists()?"1":"");
			if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
			$numRowsDeleted = $this->DbManager->Delete($this->Table, $whereArray, $limit, array('add-column-quotes'=>true, 'add-dot-notation'=>true));
			if($numRowsDeleted==0){
				throw new Exception('$numRowsDeleted==0, expected 1.');
			}
			else if($numRowsDeleted > 1){
				throw new Exception('$numRowsDeleted>1, expected 1.');
			}

			$this->_existsInDb = ($numRowsDeleted<=0); //if 0 rows deleted, row still exists in database. this typically should not happen
			$this->_isDirty = ($this->_existsInDb ? $this->_isDirty : false); //if the row still exists in the database, the isDirty status will not have changed. otherwise the row doesnt exist, so not dirty

			//clear the values of auto-increment key columns
			foreach($keyCells as $columnName=>$Cell){
				if($Cell->IsAutoIncrement) $Cell->ForceUnset();
			}

			if($this->_onAfterDelete) $this->FireCallback($this->_onAfterDelete);
			return true;
		}
		else return false;
	}

/////////////////////////////// ArrayAccess ///////////////////////////////////

	/**
	 * Sets the value in the Cell at the given column name ($key)
	 * @param string $key The column name
	 * @param object $value The new value for the cell at the given column name ($key)
	 * @ignore
	 */
	public function offsetSet($key,$value){
		$this->Cell($key)->SetValue($value);
	}
	/**
	 * Gets the Cell at the given column name ($key)
	 * @param string $key The column name
	 * @return The value in the cell at the given column name ($key)
	 * @ignore
	 */
	public function offsetGet($key){
	    return $this->Cell($key);
	}
	/**
	 * Unsets the value in the cell at the given column name ($key)
	 * @param string $key The column name
	 * @ignore
	 */
	public function offsetUnset($key){
	    $this->Cell($key)->SetValue(null);
	}
	/**
	 * Checks if the cell at the given column name ($key) is set
	 * @param string $offset The column name
	 * @ignore
	 */
	public function offsetExists($offset){
	    return $this->Table->ColumnExists($offset);
	}

/////////////////////////////// TO STRING ///////////////////////////////////
	/**
	 * Used for debugging. Allows you to echo this instance directly to get the status. Wraps ToString().
	 * Gets this row's status and columns with current values. Will will need to print/echo to see results.
	 * @see SmartRow::ToString()
	 * @return string This row's status and columns with current values. Will will need to print/echo to see results.
	 */
	public function __toString(){
		try{
			return $this->ToString();
		}
		catch(Exception $e){
			return $e->getMessage();
		}
	}

	/**
	 * Used for debugging.
	 * Gets this row's status and columns with current values. Will will need to print/echo to see results.
	 * @return string This row's status and columns with current values. Will will need to print/echo to see results.
	 */
	public function ToString($emptyLinesBefore=0, $emptyLinesAfter=0){
		$str = "";
		for($i=0; $i<$emptyLinesBefore; $i++){ $str .= "&nbsp;<br>\n"; }
		$str .= $this->ToStringColumnValues() . $this->ToStringStatus();
		for($i=0; $i<$emptyLinesAfter; $i++){ $str .= "&nbsp;<br>\n"; }
		return $str;
	}

	/**
	 * Used for debugging. Gets this Row's columns with current values. You will need to print/echo to see results.
	 * @return string This Row's columns with current values. You will need to print/echo to see results.
	 * @ignore
	 */
	public function ToStringColumnValues(){
		$str = "";
		foreach($this->_cells as $columnName=>$Cell){
			if ($Cell->AllowGet==false) continue;
			$str .= $Cell->ColumnName.": ".$Cell->GetValue()."<br>\n";
		}
		return $str;
	}

	/**
	 * Used for debugging. Gets this Row's status. You will need to print/echo to see results.
	 * @return string This Row's status. You will need to print/echo to see results.
	 * @ignore
	 */
	public function ToStringStatus(){
		$str = "";
		$str .= "IsDirty: ".$this->IsDirty()."<br>\n";
		$str .= "Exists: ".$this->Exists()."<br>\n";
		$str .= "HasErrors: ".$this->HasErrors()."<br>\n";
		//$str .= $this->InputValidation->ToString();
		return $str;
	}

////////////////////////////// INTERNAL CALLBACKS //////////////////////////////

	/**
	 * Internal use only. Anytime a Cell of this Row is modified, this function is called directly from the Cell to change row-state's and etc.
	 * We don't use a regular callback because if the user disables callbacks, this callback would not get called and this is crutial to the Row working correclty with its Cells
	 * @ignore
	 */
	public function OnCellValueChanged(){
		$this->_isDirty = true;
		if($this->Table->AutoCommit == true) $this->Commit(); //automatically commit changes now?
	}

/////////////////////////////// PUBLIC CALLBACKS ///////////////////////////////////

	/**
	 * @var bool If set to true, no callbacks will be fired for this Row (though Cell callbacks will still be fired)
	 */
	private $_disableCallbacks = false;

	/**
	 * Disables all callbacks for this row and, optionally, this row's Cells.
	 * @param object $disableAllCellCallbacks [optional] If true, all callbacks for this row's Cells will also be disabled
	 */
	public function DisableCallbacks($disableAllCellCallbacks=false){
		$this->_disableCallbacks = true;
		if($disableAllCellCallbacks){
			foreach($this->_cells as $columnName=>$Cell){
				$Cell->DisableCallbacks = true;
			}
		}
	}
	/**
	 * Enables all callbacks for this row and, optionally, this row's Cells.
	 * @param object $enableAllCellCallbacks [optional] If true, all callbacks for this row's Cells will also be enabled
	 */
	public function EnableCallbacks($enableAllCellCallbacks=false){
		$this->_disableCallbacks = false;
		if($enableAllCellCallbacks){
			foreach($this->_cells as $columnName=>$Cell){
				$Cell->DisableCallbacks = false;
			}
		}
	}

	//row callback arrays
	private $_onBeforeCommit; //callbacks to be fired before the row has been committed to database (can be insert or update)
	private $_onAfterCommit; //callbacks to be fired after the row has been committed to database (can be insert or update)
	private $_onBeforeInsert; //callbacks to be fired before the has been insertted to database (doesnt include update)
	private $_onAfterInsert; //callbacks to be fired after the row has been insertted to database (doesnt include update)
	private $_onBeforeUpdate; //callbacks to be fired before the row has been updated to database (doesnt include insert)
	private $_onAfterUpdate; //callbacks to be fired after the row has been updated to database (doesnt include insert)
	private $_onBeforeDelete; //callbacks to be fired before the row has been deleted from database
	private $_onAfterDelete; //callbacks to be fired after the row has been deleted from database

	private $_onSetAnyCellValue; //callbacks to be fired before any Cell of this row has been set
	private $_onBeforeAnyCellValueChanged; //callbacks to be fired before any Cell of this row has been set
	private $_onAfterAnyCellValueChanged; //callbacks to be fired after any Cell of this row has been set

	/**
	 * Fires all callbacks for the given callback array
	 */
	private function FireCallback($callbackArr, &$eventArgs=null){
		if($this->_disableCallbacks || count($callbackArr)<=0) return; //no callbacks defined or callbacks disabled
		if($eventArgs==null) $eventArgs = array();
		foreach($callbackArr as $callback){
			call_user_func($callback, $this, &$eventArgs); //pass $this->Row reference back through to the callback
		}
	}


	/**
	 * Adds a $callbackFunction to be fired before the row has been committed to database (can be insert or update).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array('cancel-event'=>&false); //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnBeforeCommit($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onBeforeCommit[] = $callbackFunction;
		}
		else {
			$this->_onBeforeCommit[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired after the row has been committed to database (can be insert or update).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array();
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnAfterCommit($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onAfterCommit[] = $callbackFunction;
		}
		else {
			$this->_onAfterCommit[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired before the has been insertted to database (doesnt include update).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array('cancel-event'=>&false); //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnBeforeInsert($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onBeforeInsert[] = $callbackFunction;
		}
		else {
			$this->_onBeforeInsert[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired after the row has been insertted to database (doesnt include update).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array();
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnAfterInsert($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onAfterInsert[] = $callbackFunction;
		}
		else {
			$this->_onAfterInsert[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired before the row has been updated to database (doesnt include insert).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array('cancel-event'=>&false); //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnBeforeUpdate($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onBeforeUpdate[] = $callbackFunction;
		}
		else {
			$this->_onBeforeUpdate[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired after the row has been updated to database (doesnt include insert).
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array();
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnAfterUpdate($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onAfterUpdate[] = $callbackFunction;
		}
		else {
			$this->_onAfterUpdate[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired before the row has been deleted from database.
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array('cancel-event'=>&false); //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	 */
	public function OnBeforeDelete($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onBeforeDelete[] = $callbackFunction;
		}
		else {
			$this->_onBeforeDelete[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired after the row has been deleted from database.
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array();
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnAfterDelete($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onAfterDelete[] = $callbackFunction;
		}
		else {
			$this->_onAfterDelete[] = array($functionScope, $callbackFunction);
		}
	}


	///////// Cell callbacks /////////
	private $_onSetCellValueCallbackInitialized = false;
	private $_onBeforeAnyCellValueChangedCallbackInitialized = false;
	private $_onAfterAnyCellValueChangedCallbackInitialized = false;

	/**
	 * Internal use only. Wraps callbacks from the each of this row's cells into this callback.
	 * @ignore
	 */
	public function OnSetCellValue($eventObject, $eventArgs){
		if($this->_onSetAnyCellValue) $this->FireCallback($this->_onAnyCellValueSet, $eventArgs);
	}
	/**
	 * Internal use only. Wraps callbacks from the each of this row's cells into this callback.
	 * @ignore
	 */
	public function OnBeforeCellValueChanged($eventObject, $eventArgs) {
		if($this->_onBeforeAnyCellValueChanged) $this->FireCallback($this->_onBeforeAnyCellValueChanged, $eventArgs);
	}
	/**
	 * Internal use only. Wraps callbacks from the each of this row's cells into this callback.
	 * @ignore
	 */
	public function OnAfterCellValueChanged($eventObject, $eventArgs) {
		if($this->_onAfterAnyCellValueChanged) $this->FireCallback($this->_onAfterAnyCellValueChanged, $eventArgs);
	}

	/**
	 * Adds a $callbackFunction to be fired when <b>ANY</b> column of this row has been set (though not necessarily 'changed')
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array(
	 * 	'cancel-event'=>&false,  //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * 	'Cell'=>&object, //the Cell that fired the event (Cell's contain all Column functionality and properties)
	 * 	'current-value'=>object, //the current value of this column, BEFORE it has changed to 'new-value'
	 * 	'new-value'=>&object, //the value that this column is going to be set to, replacing the 'current-value'
	 * );
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnSetColumnValue($callbackFunction, $functionScope=null){
		if(!$this->_onSetCellValueCallbackInitialized){
			foreach($this->_cells as $columnName=>$Cell){
				$Cell->OnSetValue('OnSetCellValue', $this);
			}
			$this->_onSetCellValueCallbackInitialized = true;
		}

		if(!$functionScope){
			if(!function_exists($callbackFunction)) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onSetAnyCellValue[] = $callbackFunction;
		}
		else {
			if(!is_callable(array($functionScope, $callbackFunction))) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onSetAnyCellValue[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Alias for SmartRow::OnSetColumnValue()
	 * @see SmartRow::OnSetColumnValue()
	 * @ignore
	 * @deprecated
	 */
	public function OnSetAnyColumn($callbackFunction, $functionScope=null){
		$this->OnSetColumnValue($callbackFunction, $functionScope);
	}
	/**
	 * Adds a $callbackFunction to be fired before <b>ANY</b> column of this row has been changed
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array(
	 * 	'cancel-event'=>&false,  //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * 	'Cell'=>&object, //the Cell that fired the event
	 * 	'current-value'=>object, //the current value of this column, BEFORE it has changed to 'new-value'
	 * 	'new-value'=>&object, //the value that this column is going to be set to, replacing the 'current-value'
	 * );
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnBeforeColumnValueChanged($callbackFunction, $functionScope=null){
		if(!$this->_onBeforeAnyCellValueChangedCallbackInitialized){
			foreach($this->_cells as $columnName=>$Cell){
				$Cell->OnSetValue('OnBeforeCellValueChanged', $this);
			}
			$this->_onBeforeAnyCellValueChangedCallbackInitialized = true;
		}

		if(!$functionScope){
			if(!function_exists($callbackFunction)) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onBeforeAnyCellValueChanged[] = $callbackFunction;
		}
		else {
			if(!is_callable(array($functionScope, $callbackFunction))) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onBeforeAnyCellValueChanged[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Alias for SmartRow::OnBeforeColumnValueChanged()
	 * @see SmartRow::OnBeforeColumnValueChanged()
	 * @ignore
	 * @deprecated
	 */
	public function OnBeforeAnyColumnChanged($callbackFunction, $functionScope=null){
		$this->OnBeforeColumnValueChanged($callbackFunction, $functionScope);
	}
	/**
	 * Adds a $callbackFunction to be fired after <b>ANY</b> column of this row has been changed
	 * <p>The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)</p>
	 * <p>$eventObject in your $callbackFunction will be the Row instance that is firing the event callback</p>
	 * <p>$eventArgs in your $callbackFunction will have the following keys:</p>
	 * <code>
	 * array(
	 * 	'Cell'=>&object, //the Cell that fired the event
	 * 	'current-value'=>object, //the current value of this column, AFTER it has been changed from 'old-value'
	 * 	'old-value'=>&object, //the value that this column was set to before it was updated with the 'current-value'
	 * );
	 * </code>
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	*/
	public function OnAfterColumnValueChanged($callbackFunction, $functionScope=null){
		if(!$this->_onAfterAnyCellValueChangedCallbackInitialized){
			foreach($this->_cells as $columnName=>$Cell){
				$Cell->OnSetValue('OnAfterCellValueChanged', $this);
			}
			$this->_onAfterAnyCellValueChangedCallbackInitialized = true;
		}

		if(!$functionScope){
			if(!function_exists($callbackFunction)) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onAfterAnyCellValueChanged[] = $callbackFunction;
		}
		else {
			if(!is_callable(array($functionScope, $callbackFunction))) throw new Exception("Callback function '$callbackFunction' does not exist.");
			$this->_onAfterAnyCellValueChanged[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Alias for SmartRow::OnAfterColumnValueChanged()
	 * @see SmartRow::OnAfterColumnValueChanged()
	 * @ignore
	 * @deprecated
	 */
	public function OnAfterAnyColumnChanged($callbackFunction, $functionScope=null){
		$this->OnAfterColumnValueChanged($callbackFunction, $functionScope);
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