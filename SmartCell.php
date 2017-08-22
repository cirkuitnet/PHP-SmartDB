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
 * Where a row and a column meet. Holds the actual data.
 */
/**
 * Where a row and a column meet. Holds the actual data.
 * <p><b>Note: All Column functions and properties are also available to a Cell.</b></p>
 * @see SmartColumn SmartColumn
 * @package SmartDatabase
 */
class SmartCell implements ArrayAccess, Countable, IteratorAggregate{

	/////////////////////////////// SERIALIZATION - At top so we don't forget to update these when we add new vars //////////////////////////
	/**
	 * Specify all variables that should be serialized
	 * @ignore
	 */
	public function __sleep(){
		return array(
			'_onAfterValueChanged',
			'_onBeforeValueChanged',
			'_onSetValue',
			'_value',
			'Column',
			'DisableCallbacks',
			'FakePasswordFormObjectValue',
			'Row'
		);
	}
	////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * @var SmartRow The row that contains this cell
	 */
	public $Row;
	/**
	 * @var SmartColumn The column that contains this cell
	 * @ignore
	 */
	public $Column;

	private $_value;

	/**
	 * Constructor
	 * @param SmartColumn $Column
	 * @param SmartRow $Row
	 * @return SmartCell
	 */
	public function __construct(SmartColumn $Column, SmartRow $Row){
		if(!isset($Column)) throw new Exception('$Column must be set');
		if(!isset($Row)) throw new Exception('$Row must be set');

		$this->Column = $Column;
		$this->Row = $Row;
		$this->_value = null;
	}

	/**
	 * Deprecated - Allows access to the value without having to explicitly use GetRawValue(). Example: echo $smartrow['columnname']
	 * 
	 * NOTE- Cell must be used in a string context so the value is returned as a string, otherwise you will be accessing the Cell directly.
	 * @return string The value of this cell through GetValue() as a string (PHP requires a string be returned)
	 * @see SmartCell::GetValue() SmartCell::GetValue()
	 */
	public function __toString(){
		return (string)$this->GetValue();
	}

	/**
	 * NEW WITH PHP 5.3.0, A shortcut for ->GetValue() that returns the actual value of the cell.
	 * Example usage: $smartrow['columnName']() instead of $smartrow['columnName']->GetValue(), $smartrow['columnName'](true) instead of $smartrow['columnName']->GetValue(true), and etc.
	 * @return mixed The value of this cell through GetValue()
	 * @see SmartCell::GetValue() SmartCell::GetValue()
	 * @ignore
	 */
	public function __invoke($returnOption1=false){
		return $this->GetValue($returnOption1);
	}


	/////////////////////////////// COLUMN WRAPPERS ///////////////////////////////
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __call($method, $args){
		return call_user_func_array(array($this->Column,$method), $args);
	}
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __set($key, $val){
		$this->Column->$key = $val;
	}
	/**
	 * Wraps up all public functionality of the containing Column
	 * @ignore
	 */
	public function __get($key){
		return $this->Column->$key;
	}
	///////////////////////////////////////////////////////////////////////////////

	/**
	 * Returns the value of the cell.
	 * 
	 * Note: if coming from a Row object named $row, you can use the shorthand array notation and invoke the Column/Cell directly:
	 * 
	 * ``` php
	 * //this shorthand is recommended:
	 * $columnValue = $row['YOUR_COLUMN_NAME'](); //same thing as $row['YOUR_COLUMN_NAME']->GetValue();
	 * $columnValue = $row['YOUR_COLUMN_NAME'](true); //same thing as $row['YOUR_COLUMN_NAME']->GetValue(true);
	 *
	 * //NOTE: This is not recommended, but you can skip the function call (i.e. $row['YOUR_COLUMN_NAME']) when the returned value is used as a STRING ONLY! Otherwise, $row['YOUR_COLUMN_NAME'] gives you the actual SmartCell object. For example:
	 * echo $row['YOUR_COLUMN_NAME']; //is fine and will echo the column value
	 * if($row['YOUR_COLUMN_NAME'] == "my data"){} //is fine and will compare the column value to "my data"
	 * if($row['YOUR_COLUMN_NAME'] == 3){} //is NOT fine since we're not doing string comparison. this statement will try to compare the actual SmartCell object with the int 3 and will lead to bad things.
	 * if((string)$row['YOUR_COLUMN_NAME'] == 3){} //is fine
	 * if($row['YOUR_COLUMN_NAME']() == 3){} //is fine and RECOMMENDED (just always invoke the Column/Cell directly like this)
	 * if($row['YOUR_COLUMN_NAME']->GetValue() == 3){} //is fine
	 * ```
	 * 
	 * Once PHP allows overriding operators or __toInt()/__toFloat()/__toBool() etc., we will be able to handle this situation automatically so you don't need to worry about the above anymore, and won't need to even do the shorthand invoke on the Column/Cell. We may be able to look in to the SPL_Types php package for some of this stuff down the road?
	 * @param bool $returnOption1 [optional] If this parameter is true, htmlspecialchars() will be executed before returning. If this column is a date column, this will run strtotime() before returning (with an optional DefaultTimezone value appended to the database value for use in strtotime(). See SmartColumn and SmartDatabase for setting defaults). If this column is an Array or Object column type, this parameter does nothing at all... you get the array/object as is.
	 * @return mixed The value of the cell
	 */
	public function GetValue($returnOption1=false){
		if(!$this->Column->AllowGet) throw new Exception("AllowGet is set to false for column '{$this->Column->ColumnName}' on table '{$this->Column->Table->TableName}'");
		if($this->Row->IsInitialized() == false && !$this->Column->IsPrimaryKey) $this->Row->ForceInitialization(); //initialize the row if not a primary key. may set $this->_value

		$value = $this->_value;
		
		//depending on the column's data type, we may need to normalize the raw database value for everyday use
		$value = $this->Column->NormalizeValue($value);
		
		//handle special return options (as described in the function comments)
		//note- serialized columns (arrays, objects) do not have special return options, so ignore that parameter for those types. 
		if($returnOption1 && !$this->Column->IsSerializedColumn){
			if($this->Column->IsDateColumn){ //is a date column
				if( $value ){
					$value = strtotime($value);
					if($value === false) $value = null; //if false, strtotime failed and couldn't parse a date
				}
			}
			else{ //not a date column
				$value = htmlspecialchars($value);
			}
		}
		
		return $value;
	}
	
	/**
	 * You should almost always use GetValue() instead of this. GetRawValue() will return the RAW data of the cell (i.e. serialized/compressed data for array, object columns).
	 * This is mostly used internally for db queries
	 * @return mixed The raw value of the cell
	 * @see SmartCell::GetValue() SmartCell::GetValue()
	 */
	public function GetRawValue(){
		if(!$this->Column->AllowGet) throw new Exception("AllowGet is set to false for column '{$this->Column->ColumnName}' on table '{$this->Column->Table->TableName}'");
		if($this->Row->IsInitialized() == false && !$this->Column->IsPrimaryKey) $this->Row->ForceInitialization(); //initialize the row if not a primary key. may set $this->_value
		
		return $this->_value;
	}

	/**
	 * Sets the value of the Cell.
	 * 
	 * Date column values are run through PHP's strtotime() function by default to expand possible date input values
	 * 
	 * Note: if coming from a Row object named $row, you set the value with the shorthand array notation:
	 * ``` php
	 * $row['YOUR_COLUMN_NAME'] = $yourNewValue;
	 * ```
	 * @param mixed $value The new value for the cell
	 */
	public function SetValue($value){
		if(!$this->Column->AllowSet) throw new Exception("AllowSet is set to false for column '{$this->Column->ColumnName}' on table '{$this->Column->Table->TableName}'");
		$value = $this->Column->VerifyValueType($value);

		if($this->Column->IsPrimaryKey){ //changing a primary key column value?
			if($this->Column->IsAutoIncrement) throw new Exception("Setting an auto-increment key column is not allowed: column '{$this->Column->ColumnName}', table '{$this->Column->Table->TableName}'");
			else if($this->Column->Table->PrimaryKeyIsNonCompositeNonAutoIncrement()){ //setting a non-autoincrement, non composite key
				$this->Row->SetKeyColumnValues(array($this->Column->Table->TableName=>array($this->Column->ColumnName=>$value)),true,false);
				return;
			}
			else if($this->Column->Table->PrimaryKeyIsComposite()) throw new Exception("Setting individual key column '{$this->Column->ColumnName}' in composite key table '{$this->Column->Table->TableName}' is not allowed. Use the SetKeyColumnValues() function on the Row.");
		}
		else { //changing a non primary key column value
			if($this->Row->IsInitialized() == false) $this->Row->ForceInitialization();
			$valueBeforeChanged = $this->_value;
			if($this->_onSetValue) $this->FireCallback($this->_onSetValue, $e=array('cancel-event'=>&$cancelEvent, 'Cell'=>&$this, 'current-value'=>$valueBeforeChanged, 'new-value'=>&$value ) );
			if($cancelEvent) return; //event cancelled, do not set the value

			if($this->Column->TrimAndStripTagsOnSet && $value!==null){
				$value = strip_tags(trim($value));
			}

			if( (!isset($this->_value) && isset($value)) || $this->ValueDiffers($value)){
				if($this->_onBeforeValueChanged) $this->FireCallback($this->_onBeforeValueChanged, $e=array('cancel-event'=>&$cancelEvent, 'Cell'=>&$this, 'current-value'=>$valueBeforeChanged, 'new-value'=>&$value ) );
				if(!$cancelEvent){
					$this->_value = $value;
					$this->Row->OnCellValueChanged(); //notify the row that a cell's value had changed
					if($this->_onAfterValueChanged) $this->FireCallback($this->_onAfterValueChanged, $e=array('Cell'=>&$this, 'current-value'=>$this->_value, 'old-value'=>$valueBeforeChanged ));
				}
			}
		}
	}

	/**
	 * Returns true if this Cell's value has been set (i.e. isset()==true)
	 * 
	 * NOTE- this function is meant mostly for internal use. It is NOT to be used to determine if a Cell's value is empty or null.
	 * @return bool true if this Cell's value has been set (i.e. isset()==true)
	 * @ignore
	 */
	public function IsValueSet(){
		return isset($this->_value);
	}

	/**
	 * Internal use only. Using this can seriously mess up the current row state and jack shit up. Don't do it.
	 * @ignore
	 */
	public function ForceValue($value){
		$value = $this->Column->VerifyValueType($value);
		$this->_value = $value;
	}
	/**
	 * Internal use only. Using this can seriously mess up the current row state and jack shit up. Don't do it.
	 * @ignore
	 */
	public function ForceUnset(){
		$this->_value = null; //do this instead of unset. unset loses our private $_value and forces the value to be set on the Column level next time through, thanks to magic functions
	}
	
	//TODO: may need these Compress() and Uncompress() functions at that point?
	/**
	 * Compresses a $txt for db store. If compressing and serializing: 1. Serialize, 2. Compress
	 * @param string $txt The text to compress. Use SmartCell::Uncompress() to get the original $txt
	 * @return mixed The serialized $obj. Use SmartCell::Uncompress() to uncompress this $txt.
	 * @see SmartCell::Uncompress() SmartCell::Uncompress()
	 */
	//private function Compress($txt){
	//	return base64_encode(gzcompress($txt));
	//}
	/**
	 * Compresses a $txt for db store. If compressing and serializing: 1. Unserialize, 2. Uncompress
	 * @param string $txt The text to uncompress. Use SmartCell::Compress() to compress the $txt
	 * @return mixed The uncompressed $txt. Should have used SmartCell::Compress() to compress this original $txt.
	 * @see SmartCell::Compress() SmartCell::Compress()
	 */
	//private function Uncompress($txt){
	//	return gzuncompress(base64_decode($txt));
	//}

	/**
	 * Checks the given $compareValue against the current value to see if the value is different. Returns true if the $compareValue is different from the current value, false if they are the same. Uses type casting.
	 * @param $compareValue mixed the value to comare $this->_value to
	 * @return bool true if the $compareValue is different from the current value, false if they are the same.
	 */
	private function ValueDiffers($compareValue){
		//check null first. only allow null special if this column is NOT required (ie NULL sf a valid value)
		if( !$this->Column->IsRequired && $compareValue===null && $this->_value !== null){
			return true;
		}

		$dataType = strtolower($this->Column->DataType);
		switch($dataType){
			case("float"):
				return ((float)$this->_value !== (float)$compareValue);
			case("binary"):
				$thisCompareVal = $this->_value;
				if($thisCompareVal==="\0"){ //special case with \0 not recognized as null/false
					$thisCompareVal = 0;
				}
				if($compareValue==="\0"){ //special case with \0 not recognized as null/false
					$compareValue = 0;
				}
				return ((bool)$thisCompareVal !== (bool)$compareValue);
			case("int"):
			case("bigint"):
			case("tinyint"):
			case("smallint"):
				return ((int)$this->_value !== (int)$compareValue);
			case("double"):
			case("decimal"):
				return ((double)$this->_value !== (double)$compareValue);
			case("varchar"):
			case("char"):
			case("text"):
			case("enum"):
			case("set"): //$compareValues will be a CSV at this point, as is _value
			case("array"): //$compareValue will be serialized at this point, as is _value
			case("object"): //$compareValue will be serialized at this point, as is _value
				return ((string)$this->_value !== (string)$compareValue);
			default:
				return ($this->_value !== $compareValue);
		}
	}

	/**
	 * Returns an array of all rows from $tableName whose $columnName value matches the value of this Cell. If there are no matching rows, an empty array is returned.
	 * 
	 * To execute this function, the related table must have a primary key.
	 * 
	 * Options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'sort-by'=>null, //Either a string of the column to sort ASC by, or an assoc array of "ColumnName"=>"ASC"|"DESC" to sort by. An exception will be thrown if a column does not exist.
	 * 	'return-assoc'=>false, //if true, the returned assoc-array will have the row's primary key column value as its key and the row as its value. ie array("2"=>$row,...) instead of just array($row,...);
	 * 	'return-next-row'=>null, //OUT variable. integer. if you set this parameter in the $options array, then this function will return only 1 row of the result set at a time. If there are no rows selected or left to iterate over, null is returned.
	 *  						 // THIS PARAMETER MUST BE PASSED BY REFERENCE - i.e. array( "return-next-row" => &$curCount ) - the incoming value of this parameter doesn't matter and will be overwritten)
	 *  						 // After this function is executed, this OUT variable will be set with the number of rows that have been returned thus far.
	 *  						 // Each consecutive call to this function with the 'return-next-row' option set will return the next row in the result set, and also increment the 'return-next-row' variable to the number of rows that have been returned thus far
	 * 	'limit'=>null, // With one argument (ie $limit="10"), the value specifies the number of rows to return from the beginning of the result set
	 *				   	// With two arguments (ie $limit="100,10"), the first argument specifies the offset of the first row to return, and the second specifies the maximum number of rows to return. The offset of the initial row is 0 (not 1):
	 * 	'return-count'=>null, //OUT variable only. integer. after this function is executed, this variable will be set with the number of rows being returned. Usage ex: array('return-count'=>&$count)
	 * 	'return-count-only'=>false, //if true, the return-count will be returned instead of the rows. A good optimization if you dont need to read any data from the rows and just need the rowcount of the search.
	 *  }
	 * ```
	 * @param string $tableName The table containing the related data
	 * @param string $columnName The column within given $tableName that contains the data related to this cell
	 * @param array $options [optional] See description
	 * @return array An array of all rows from $tableName whose $columnName value matches the value of this Cell. If there are no matching rows, an empty array is returned.
	 */
	public function GetRelatedRows($tableName, $columnName=null, array $options=null){
		if(is_array($columnName)){ //shortcut support for GetRelatedRows(tableName, options)
			$options = $columnName;
			$columnName = $this->Column->ColumnName;
		}
		else if($columnName == null){ //shortcut support for GetRelatedRows(tableName)
			$columnName = $this->Column->ColumnName;
		}
		if(!$this->Column->HasRelation($tableName, $columnName)) throw new Exception("No relation specified between table '$tableName', column '$columnName' and table '{$this->Column->Table->TableName}', column '{$this->Column->ColumnName}'. ");

		$relatedTable = $this->Column->Table->Database->GetTable($tableName);
		if(!$relatedTable->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' can only get related Rows from Tables that contain a primary key. Related Table '$tableName' has no primary key specified.");

		return $relatedTable->GetColumn($columnName)->LookupRows($this->GetRawValue(), $options);
	}

	/**
	 * Returns a Row instance from $tableName whose $columnName value matches the value of this Cell. If there is no match, an instance is still returned but ->Exists() will be false. The returned row will have the searched column=>value set by default (excluding auto-increment primary key columns)
	 * 
	 * To execute this function, the related table must have a primary key and the column $columnName must be unique.
	 * 
	 * Options are as follows:
	 * @param string $tableName The table containing the related data
	 * @param string $columnName The column within given $tableName that contains the data related to this cell
	 * @return SmartRow A Row instance from $tableName whose $columnName value matches the value of this Cell. If there is no match, an instance is still returned but ->Exists() will be false. The returned row will have the searched column=>value set by default (excluding auto-increment primary key columns)
	 * @see SmartRow::Exists() SmartRow::Exists()
	 */
	public function GetRelatedRow($tableName, $columnName=null){
		if(!$columnName) $columnName = $this->Column->ColumnName;
		if(!$this->Column->HasRelation($tableName, $columnName)) throw new Exception("No relation specified between table '$tableName', column '$columnName' and table '{$this->Column->Table->TableName}', column '{$this->Column->ColumnName}'. ");

		$relatedTable = $this->Column->Table->Database->GetTable($tableName);
		if(!$relatedTable->PrimaryKeyExists()) throw new Exception("Function '".__FUNCTION__."' can only get related Rows from Tables that contain a primary key. Related Table '$tableName' has no primary key specified.");

		$relatedColumn = $relatedTable->GetColumn($columnName);
		if(!$relatedColumn->IsUnique) throw new Exception("Function '".__FUNCTION__."' only works on columns specified as Unique. Table '$tableName', column '$columnName' is not specified as unique.");

		return $relatedColumn->LookupRow($this->GetRawValue(), $options);
	}


	/////////////////////////////// CALLBACK STUFF ///////////////////////////////////
	//event callback arrays
	private $_onSetValue; //callbacks to be fired when this column has been set (not necessarily 'changed')
	private $_onBeforeValueChanged; //callbacks to be fired before this column has been changed
	private $_onAfterValueChanged; //callbacks to be fired after this column has been changed

	/**
	 * @var bool If set to true, no callbacks will be fired for this Cell.
	 */
	public $DisableCallbacks = false;

	/**
	 * Fires all callbacks for the given callback array
	 * @param array $callbackArr array of callback functions to be invoked
	 * @param array $eventArgs event args passed to each callback function. passed by ref so callbacks can easily communicate between layers
	 */
	private function FireCallback($callbackArr, &$eventArgs=null){
		if($this->DisableCallbacks || count($callbackArr)<=0) return; //no callbacks defined or callbacks disabled
		foreach($callbackArr as $callback){
			call_user_func($callback, $this->Row, $eventArgs); //pass $this->Row reference back through to the callback
		}
	}

	/**
	 * Adds a $callbackFunction to be fired when this column has been set (though not necessarily 'changed')
	 * 
	 * The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)
	 * 
	 * $eventObject in your $callbackFunction will be the ROW containing the Cell that is firing the event callback
	 * 
	 * $eventArgs in your $callbackFunction will have the following keys:
	 * ``` php
	 * array(
	 * 	'cancel-event'=>&false,  //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * 	'Cell'=>&$this, //a reference to the Cell that fired the event
	 * 	'current-value'=>object, //the current value of this column, BEFORE it has changed to 'new-value'
	 * 	'new-value'=>&object, //the value that this column is going to be set to, replacing the 'current-value'. Changing this value in your $callbackFunction will change the value that the column will be set to.
	 * );
	 * ```
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	 */
	public function OnSetValue($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onSetValue[] = $callbackFunction;
		}
		else {
			$this->_onSetValue[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired right before this column has been changed.
	 * The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)
	 * $eventObject in your $callbackFunction will be the ROW containing the Cell that is firing the event callback
	 * $eventArgs in your $callbackFunction will have the following keys:
	 * ``` php
	 * array(
	 * 	'cancel-event'=>&false,  //setting 'cancel-event' to true within your $callbackFunction will prevent the event from continuing
	 * 	'Cell'=>&$this, //a reference to the Cell that fired the event
	 * 	'current-value'=>object, //the current value of this column, BEFORE it has changed to 'new-value'
	 * 	'new-value'=>&object, //the value that this column is going to be set to, replacing the 'current-value'. Changing this value in your $callbackFunction will change the value that the column will be set to.
	 * );
	 * ```
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	 */
	public function OnBeforeValueChanged($callbackFunction, $functionScope=null){
		if(!$functionScope){
			$this->_onBeforeValueChanged[] = $callbackFunction;
		}
		else {
			$this->_onBeforeValueChanged[] = array($functionScope, $callbackFunction);
		}
	}
	/**
	 * Adds a $callbackFunction to be fired right after this column has been changed.
	 * 
	 * The signature of your $callbackFunction should be as follows: yourCallbackFunctionName($eventObject, $eventArgs)
	 * 
	 * $eventObject in your $callbackFunction will be the ROW containing the Cell that is firing the event callback
	 * 
	 * $eventArgs in your $callbackFunction will have the following keys:
	 * ``` php
	 * array(
	 * 	'Cell'=>&$this, //a reference to the Cell that fired the event
	 * 	'current-value'=>object, //the current value of this column, AFTER it has been changed from 'old-value'
	 * 	'old-value'=>object, //the value that this column was set to before it was updated with the 'current-value'
	 * );
	 * ```
	 * @param mixed $callbackFunction the name of the function to callback that exists within the given $functionScope
	 * @param mixed $functionScope [optional] the scope of the $callbackFunction, this may be an instance reference (a class that the function is within), a string that specifies the name of a class (that contains the static $callbackFunction), , or NULL if the function is in global scope
	 */
	public function OnAfterValueChanged($callbackFunction, $functionScope=null){
		if(!$functionScope) {
			$this->_onAfterValueChanged[] = $callbackFunction;
		}
		else {
			$this->_onAfterValueChanged[] = array($functionScope, $callbackFunction);
		}
	}

	/////////////////////////////// FORM STUFF ///////////////////////////////////
	/**
	 * Returns a string of HTML representing a form textbox object for this Cell.
	 * @param string $formObjectType [optional] Will use the column's default form object type if not specified or NULL. Can be:
	 * 	"text" | "password" | "checkbox" | "select" | "textarea" | "hidden" | "radio" | "colorpicker" | "datepicker" | "slider"
	 * @param mixed $param1 [optional] Depends on the $formObjectType you use. See references for details.
	 * @param mixed $param2 [optional] Depends on the $formObjectType you use. See references for details.
	 * @param mixed $param3 [optional] Depends on the $formObjectType you use. See references for details.
	 * @see SmartCell::GetTextFormObject() SmartCell::GetTextFormObject()
	 * @see SmartCell::GetPasswordFormObject() SmartCell::GetPasswordFormObject()
	 * @see SmartCell::GetCheckboxFormObject() SmartCell::GetCheckboxFormObject()
	 * @see SmartCell::GetSelectFormObject() SmartCell::GetSelectFormObject()
	 * @see SmartCell::GetTextareaFormObject() SmartCell::GetTextareaFormObject()
	 * @see SmartCell::GetHiddenFormObject() SmartCell::GetHiddenFormObject()
	 * @see SmartCell::GetRadioFormObject() SmartCell::GetRadioFormObject()
	 * @see SmartCell::GetColorpickerFormObject() SmartCell::GetColorpickerFormObject()
	 * @see SmartCell::GetDatepickerFormObject() SmartCell::GetDatepickerFormObject()
	 * @see SmartCell::GetSliderFormObject() SmartCell::GetSliderFormObject()
	 * @return string string of HTML representing a form textbox object for this cell
	 */
	public function GetFormObject($formObjectType=null, $param1=null, $param2=null, $param3=null){
		if(!$formObjectType){
			$formObjectType = $this->Column->DefaultFormType;
		}
		$formObjectType = strtolower($formObjectType);
		switch($formObjectType){
			case 'text': return $this->GetTextFormObject($param1, $param2, $param3);
			case 'password': return $this->GetPasswordFormObject($param1, $param2, $param3);
			case 'checkbox': return $this->GetCheckboxFormObject($param1, $param2, $param3);
			case 'select': return $this->GetSelectFormObject($param1, $param2, $param3);
			case 'textarea': return $this->GetTextareaFormObject($param1, $param2, $param3);
			case 'hidden': return $this->GetHiddenFormObject($param1, $param2, $param3);
			case 'radio': return $this->GetRadioFormObject($param1, $param2, $param3);
			case 'colorpicker': return $this->GetColorpickerFormObject($param1, $param2, $param3);
			case 'datepicker': return $this->GetDatepickerFormObject($param1, $param2, $param3);
			case 'slider': return $this->GetSliderFormObject($param1, $param2, $param3);
			default: throw new Exception("Invalid Form Object Type: $formObjectType");
		}
	}

	/**
	 * Returns a string of HTML representing a form textbox object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form textbox object for this cell
	 */
	public function GetTextFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'text',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText',
			'value'=>$currentValue,
			'size'=>$this->GetMaxLength(),
			'maxlength'=>$this->GetMaxLength(),
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'pattern'=>($this->Column->RegexCheck ?: null),
			'required'=>($this->Column->IsRequired ? 'required' : null),
			'placeholder'=>($this->Column->Example ?: null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}

	/**
	 * @var string The value that is used as a password form object value when a password cell is set.
	 */
	public $FakePasswordFormObjectValue = "__fake__"; //should be something unique, doesnt matter what
	/**
	 * Returns a string of HTML representing a form password input object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form password input object for this cell
	 */
	public function GetPasswordFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$pwd = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$pwd = $this->GetSerializedValue($pwd);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}
		
		if ($pwd) $pwd = $this->FakePasswordFormObjectValue;
		else $pwd = "";


		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'password',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText inputPassword',
			'value'=>$pwd,
			'size'=>$this->GetMaxLength(),
			'maxlength'=>$this->GetMaxLength(),
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'autocomplete'=>'off',
			'pattern'=>($this->Column->RegexCheck ?: null),
			'required'=>($this->Column->IsRequired ? 'required' : null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}


	/**
	 * Returns a string of HTML representing a form checkbox input object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $hiddenNotifierCustomAttribs [optional] An array of custom attributes for this checkbox's corresponding hidden field. (the hidden field must exist so if this checkbox is not checked, POST still contains information that will let Codegen know that the checkbox value should be updated to 'not checked')
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form checkbox input object for this cell
	 */
	public function GetCheckboxFormObject(array $customAttribs=null, $hiddenNotifierCustomAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}
		
		$checked = null;
		if(!$currentValue || ($currentValue==="\0")){
			//not checked. but need a value for the checkbox in case user checks it
			if($this->Column->PossibleValues){ //set columns should present the 'raw' csv text data
				$currentValue = (string)$this->Column->PossibleValues[0]; //use first item, if we have one
			}
			else{
				$currentValue = "1";
			}
		}
		else{ //current value is set
			$checked = 'checked';
		}


		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'checkbox',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputCheckbox',
			'value'=>$currentValue,
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'checked'=>$checked,
			'required'=>($this->Column->IsRequired ? 'required' : null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);


		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';


		//HIDDEN NOTIFIER ATTRIBS
		$defaultNotifierAttribs = array(
			'type'=>'hidden',
			'id'=>$this->GetDefaultFormObjectId('_Notifier'),
			'name'=>$this->GetDefaultFormObjectName('_Notifier'),
			'class'=>'inputHidden',
			'value'=>'0',
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'checked'=>($this->GetValue() ? 'checked' : null)
		);
		if(is_array($hiddenNotifierCustomAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$hiddenNotifierCustomAttribs = array_change_key_case($hiddenNotifierCustomAttribs, CASE_LOWER);
			$hiddenNotifierCustomAttribs = array_merge($defaultNotifierAttribs, $hiddenNotifierCustomAttribs);
		}
		else $hiddenNotifierCustomAttribs = $defaultNotifierAttribs;

		$notifierAttribsHtml = $this->BuildAttribsHtml($hiddenNotifierCustomAttribs);
		$hiddenNotifier = '<input'.$notifierAttribsHtml.'>';


		return $formObjHtml . $hiddenNotifier;
	}

	/**
	 * Returns a string of HTML representing a select dropdown input object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * 	'print-empty-option' => !$this->IsRequired, //if true, an empty option will be the first option printed
	 * 	'force-selected-key' => null, //string. if set, the given key within $keyValuePairs will be forced as the selected option (if found. if not found, the browser's default choice will be selected, probably the first in the list)
	 * 	'use-possible-values' => false, //if true, this will populate the select object with the "PossibleValues" for this particular column (as defined in the xml db schema)
	 * );
	 * ```
	 * @param mixed $keyValuePairs [optional] (see below)
	 * 	- If $keyValuePairs is "true", this will populate the select object with the "PossibleValues" for this particular column (as defined in the xml db schema)
	 * 	- If $keyValuePairs is an array- If an array- The key-value pairs that set the values for the input dropdown html tag. ie each key-value pair generates &lt;option name="KEY"&gt;VALUE&lt;/option&gt;
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a select dropdown input object for this cell
	 */
	public function GetSelectFormObject($keyValuePairs=null, array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired,
			"print-empty-option"=>!$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if($keyValuePairs===true){ //set the 'use-possible-values' option to true if this parameter is true. it's a shortcut
			$options['use-possible-values'] = true;
		}
		
		$multiple = ($this->Column->IsASet || $customAttribs['multiple'] ? 'multiple' : null);

		//add brackets (array notation) for multi-selects going into php
		$defaultName = $this->GetDefaultFormObjectName();
		$defaultName .= ($multiple ? '[]' : '');
		
		//ATTRIBS
		$defaultAttribs = array(
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$defaultName,
			'class'=>'inputSelect',
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'multiple'=>$multiple //sets allow multiple select
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;
		
		//SELECT TAG
		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<select'.$attribsHtml.'>';

		//get $currentValue
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		
		//OPTIONS		
		if($options['print-empty-option']){
			$formObjHtml .= '<option value=""';
			if( !$currentValue && !$options['force-selected-key'] ) { $formObjHtml .= ' selected="selected"'; }
			$formObjHtml.='> </option>';
		}
		
		if($options['use-possible-values']){
			$keyValuePairs = array_combine(($pv=$this->Column->PossibleValues), $pv);	//populate select values from xml
		}

		if($keyValuePairs){
			if($multiple){
				//multi select (multiple current values)
				if(!is_array($currentValue)){ //currentValue should be array here, but might be a CSV
					$currentValue = explode(',', $currentValue);
				}

				foreach ($keyValuePairs as $key => $value){
					$key = htmlspecialchars($key);
					$value = htmlspecialchars($value);
					$formObjHtml .= '<option value="'.$key.'"';
					foreach ($currentValue as $thisCurrentValue){
						//get formatted $currentValue
						$thisCurrentValue = htmlspecialchars($thisCurrentValue);
						
						if( (!$options['force-selected-key'] && $key==$thisCurrentValue) || ($options['force-selected-key']==$key) ){
							$formObjHtml .= ' selected="selected"';
							break;
						}
					}
					$formObjHtml .= '>'.$value.'</option>';
				}
			}
			else{
				//get formatted $currentValue
				$currentValue = htmlspecialchars($currentValue);
				
				//non-multi select (1 current value)
				foreach ($keyValuePairs as $key => $value){
					$key = htmlspecialchars($key);
					$value = htmlspecialchars($value);
					$formObjHtml .= '<option value="'.$key.'"';
				
					if( (!$options['force-selected-key'] && $key==$currentValue) || ($options['force-selected-key']==$key) ) $formObjHtml .= ' selected="selected"';
					$formObjHtml .= '>'.$value.'</option>';
				}
			}
		}
		$formObjHtml .= '</select>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}

	/**
	 * Returns a string of HTML representing a form textarea input object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * 	'value' => null, //the actual shown value of the text area (if not using the default value from this cell)
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form textarea input object for this cell
	 */
	public function GetTextareaFormObject(array $customAttribs=null, array $options=null){
		//get $currentValue
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}
		
		//get formatted $currentValue
		$currentValue = htmlspecialchars($currentValue); 

		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired,
			"value"=>$currentValue
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;


		//ATTRIBS
		$defaultAttribs = array(
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText inputTextarea',
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'maxlength'=>$this->GetMaxLength(),
			'required'=>($this->Column->IsRequired ? 'required' : null),
			'placeholder'=>($this->Column->Example ?: null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;


		if($options['custom-formatter-callback']) $options['value'] = call_user_func($options['custom-formatter-callback'], $options['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<textarea '.$attribsHtml.'>'.$options['value'].'</textarea>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}

	/**
	 * Returns a string of HTML representing a form hidden input object for this cell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form hidden input object for this cell
	 */
	public function GetHiddenFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		/*
		 $defaultOptions = array( //default options
		 );
		 if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		 }
		 else $options = $defaultOptions;
		*/

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'hidden',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputHidden',
			'value'=>$currentValue,
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);

		return '<input'.$attribsHtml.'>';
	}

	/**
	 * Returns string string of HTML representing a radio button input object for this cell.
	 * 
	 * If $customAttribs contains 'name', a custom name will be set for this form object. 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * 
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'force-checked' => false, //if true, this radio button will be checked regardless of the current Cell value. if false, this radio button will be checked only if the value matches the one currently in the Cell
	 * 	'checked-if-null' => false, //if true, this radio button will be checked only if the current Cell value for thi column is null. defaults to true if the current value is 0 or empty string
	 * 	'label-text' => "", //a string for the text of the label next to the radio button. if this field is left empty, no label will be included with the button
	 * 	'label-position' => "left", //can be "left" or "right", relative to the radio button (only if ['include-label']=true)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param string $labelText [optional] The text for the html label that is included. Empty string or null will not print a label.
	 * @param string $formValue [optional] The html value that this radio button will have
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] See function description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a radio button input object for this cell
	 */
	public function GetRadioFormObject($labelText="", $formValue="", array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"force-checked"=>false,
			"checked-if-null"=>false, //defaults to true if the current value is 0 or empty string
			"checked-if-dbtable-value-is-null"=>false, //deprecated. alias to checked-if-null
			"label-text"=>$labelText,
			"label-position"=>"left"
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
			$options['checked-if-null'] = $options['checked-if-null'] || $options['checked-if-dbtable-value-is-null']; //support legacy options
		}
		else $options = $defaultOptions;
		
		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		
		
		if($this->Column->IsASet){ //set columns need to check if this $formValue is part of the active set
			//$currentValue is an array 
			$foundAtKey = array_search($formValue,$currentValue); 
			if( $foundAtKey !== false && $currentValue ){
				$options['force-checked'] = true;
			}
		}
		
		//"checked-if-null" radio option should default to true if the current value is 0 or empty string
		//if(!$formValue && ($currentValue === 0 || $currentValue === '')){
		//	$options['checked-if-null'] = true;
		//}

		$checked=null;
		if ($options['force-checked'] || ($currentValue==$formValue) || (!$currentValue && !$formValue) || (!$currentValue && $options['checked-if-null'])){
			$checked='checked';
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'radio',
			'id'=>$this->GetDefaultFormObjectId("_".self::MakeValidHtmlId($formValue)),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputRadio',
			'value'=>$formValue,
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'checked'=>$checked
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		if ($options['label-text']){
			$labelTag = '<label for="'.$customAttribs['id'].'">'.$options['label-text'].'</label>';
		}

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if ($options['label-position']!="right") return $labelTag.' '.$formObjHtml;
		else return $formObjHtml.' '.$labelTag;
	}

	/**
	 * Strips out and returns any characters that are not valid for use as an HTML ID attribute
	 * @param $str string The string to cleanup
	 */
	public static function MakeValidHtmlId($str){ //strips any characters not valid for an HTML ID
		$lastIsUnderscore=false;
		$strlen = strlen($str);
		$final = "";
		for($i=0; $i<$strlen; $i++){
			if(!ctype_alnum($str[$i])){
				if(!$lastIsUnderscore) { $final.="_"; $lastIsUnderscore=true; }
			}
			else { $final.=$str[$i]; $lastIsUnderscore=false; }
		}
		return trim($final,"_ \n\r");
	}

	/**
	 * Returns a string of HTML for use with the jQuery UI colorpicker (which may have been removed from jquery ui?). Uses appropriate the name/value for this SmartCell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form color picker input object for this cell
	 */
	public function GetColorpickerFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired,
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'text',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText inputColorpicker',
			'value'=>$currentValue,
			'size'=>$this->GetMaxLength(),
			'maxlength'=>$this->GetMaxLength(),
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'pattern'=>($this->Column->RegexCheck ?: null),
			'required'=>($this->Column->IsRequired ? 'required' : null),
			'placeholder'=>($this->Column->Example ?: null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}

	/**
	 * Returns a string of HTML for use with the jQuery UI date picker. Uses appropriate the name/value for this SmartCell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form date picker input object for this cell
	 */
	public function GetDatepickerFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'text',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText inputDatepicker',
			'value'=>$currentValue,
			'size'=>$this->GetMaxLength(),
			'maxlength'=>$this->GetMaxLength(),
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null),
			'pattern'=>($this->Column->RegexCheck ?: null),
			'required'=>($this->Column->IsRequired ? 'required' : null),
			'placeholder'=>($this->Column->Example ?: null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		if($options['show-required-marker']) $formObjHtml .= '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml;
	}

	/**
	 * Returns a string of HTML for use with the jQuery UI slider. Uses appropriate the name/value for this SmartCell.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'show-required-marker' => $this->IsRequired, //if true, a '*' will be appended to the end of the input field (note: default value may be set on the Column. use this field to overwrite the default value)
	 * 	'custom-formatter-callback' =>null, //can be either: 1. array("functionName", $obj) if function belongs to $obj, 2. array("functionName", "className") if the function is static within class "classname", or 3. just "functionName" if function is in global scope. this function will be called when getting the form object and the value returned by it will be used as the form object's value. the callback's signiture is functionName($value), where $value is the current cell value
	 * );
	 * ```
	 * @param array $customAttribs [optional] An assoc-array of attributes to set in this form object's html (ie 'class'=>'yourClass').
	 * 	If this array contains 'name', a custom name will be set for this form object, though 'name' should be left blank on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle getting/setting POST/GET values
	 * @param array $options [optional] Array of key-value pairs, see description
	 * @see SmartCell::GetFormObject() SmartCell::GetFormObject()
	 * @return string string of HTML representing a form slider input object for this cell
	 */
	public function GetSliderFormObject(array $customAttribs=null, array $options=null){
		//OPTIONS
		$defaultOptions = array( //default options
			"show-required-marker"=>$this->Column->IsRequired
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		//get $currentValue (note: BuildAttribsHtml() does htmlspecialchars)
		$currentValue = $this->GetValue();
		if($this->Column->IsSerializedColumn){ //serialized columns should present the 'raw' serialized text data
			$currentValue = $this->GetSerializedValue($currentValue);
		}
		if($this->Column->IsASet){ //set columns should present the 'raw' csv text data
			$currentValue = implode(',', $currentValue);
		}

		//ATTRIBS
		$defaultAttribs = array(
			'type'=>'text',
			'id'=>$this->GetDefaultFormObjectId(),
			'name'=>$this->GetDefaultFormObjectName(),
			'class'=>'inputText inputSlider',
			'value'=>$currentValue,
			'size'=>$this->GetMaxLength(),
			'maxlength'=>$this->GetMaxLength(),
			'disabled'=>(!$this->Column->AllowSet ? 'disabled' : null)
		);
		if(is_array($customAttribs)){ //overwrite $defaultAttribs with any $customAttribs specified
			$customAttribs = array_change_key_case($customAttribs, CASE_LOWER);
			$customAttribs = array_merge($defaultAttribs, $customAttribs);
		}
		else $customAttribs = $defaultAttribs;

		//formatter callback
		if($options['custom-formatter-callback']) $customAttribs['value'] = call_user_func($options['custom-formatter-callback'], $customAttribs['value']);

		$attribsHtml = $this->BuildAttribsHtml($customAttribs);
		$formObjHtml = '<input'.$attribsHtml.'>';
		$slider = '<div class="ui-slider"><div class="ui-slider-handle"></div></div>';
		if($options['show-required-marker']) $requiredMark = '<span class="formFieldRequiredMarker">*</span>';
		return $formObjHtml.$slider.$requiredMark;
	}

	/**
	 * Builds a string of HTML of the giben attribtutes. ie ' class="myclass" id="someId" disable="disabled"'
	 * @param array $attribsAssoc [optional]
	 * @return string HTML of the given attributes
	 */
	private function BuildAttribsHtml(array $attribsAssoc){
		$html = '';
		foreach($attribsAssoc as $key=>$val){
			if($val !== null){
				$key = htmlspecialchars($key);
				$val = htmlspecialchars($val);
				$html .= " $key=\"$val\"";
			}
		}
		return $html;
	}

	/**
	 * Returns a string of HTML representing a label for this cell's form input object.
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'for' => $this->Table->TableName.$this->ColumnName, //the id of the form object that this label is for. this should be left as default on most cases as the default name will be generated so it can be tracked by this class; custom names require you to handle organization of matching label names to form object ids
	 * 	'label-text' => $this->DisplayName, //the text or html of the label. empty will use the column's DisplayName (if set), otherwise uses the ColumnName
	 * 	'prefix' => "", //adds a text or html prefix to the label
	 * 	'suffix' => ": ", //adds a text or html suffix to the label
	 *  'html-special-chars' => false //if true, will html-special-chars the prefix, label-text, and suffix
	 * );
	 * ```
	 * @param array $options [optional] See description
	 * @return string a string of HTML representing a label for this cell's form input object
	 */
	public function GetFormObjectLabel(array $options=null){
		if($options['for-form-obj-name']) $options['for'] = $options['for-form-obj-name']; //reverse compatible
		$defaultOptions = array( //default options
			"for"=>$this->GetDefaultFormObjectId(),
			"label-text"=>( $this->Column->DisplayName ? $this->Column->DisplayName : $this->Column->ColumnName ),
			"prefix"=>"",
			"suffix"=>": ",
			"html-special-chars"=>false
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		//if true, will html-special-chars the prefix, label-text, and suffix
		if($options['html-special-chars']){
			$options['prefix'] = htmlspecialchars( $options['prefix'] );
			$options['label-text'] = htmlspecialchars( $options['label-text'] );
			$options['suffix'] = htmlspecialchars( $options['suffix'] );
		}

		return '<label for="'.$options['for'].'">'.$options['prefix'].$options['label-text'].$options['suffix'].'</label>';
	}

	/////////////////////////////// INPUT VALIDATION ///////////////////////////////////
	/**
	 * Returns a string of current errors that exist within this cell, or FALSE if no errors were found.
	 * 
	 * The row should not be committed until there are no more errors on any cell of the row
	 * 
	 * $options are as follows:
	 * ``` php
	 * $options = array(
	 * 	'ignore-key-errors'=>false, //If true: does not validate the key columns. If false: validates all columns
	 * 	'only-verify-set-cells'=>false, //If true: only cells that have been set (i.e. isset()==true) will be verified (not recommended if this info will be committed to db). If false: all cells will be verified (should be used if this info will be committed to db).
	 * 	'error-message-suffix'=>"<br>\n" //appended to each error message
	 * );
	 * ```
	 * @param array $options [optional] See description.
	 * @returns mixed A string of current errors that exist within this cell, or FALSE if no errors were found
	 */
	public function HasErrors(array $options=null){
		$defaultOptions = array( //default options
			"error-message-suffix"=>"<br>\n"
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;

		$value = $this->GetRawValue();

		if ($this->Column->IsPrimaryKey && $options['ignore-key-errors']) return false;
		if($options['only-verify-set-cells'] && !isset($value)) return false;

		$errors = false;

		if($this->Column->PossibleValues){ //need to validate that the value is valid (mostly for enumerations and sets)
			//for mysql, enum has null and "" as separate valid values and "" is ALWAYS valid... wtf? make it so "" and null are equal and always valid. will be caught later if null is not allowed
			if($value !== '0' && !$value){
				$value = null;
			}
			else{ //non-null value
				if($this->Column->IsASet){ //all set values must be within the list of PossibleValues
					$isValidValue = $this->Column->VerifySet($value);
					if(!$isValidValue){
						//invalid. show error message
						$displayValue = $value;
						if(is_array($displayValue)){
							$displayValue = implode(',', $displayValue);
						}
						$errorMsg = "Invalid value '$displayValue' specified for this column. Table: '{$this->Column->Table->TableName}', Column: '{$this->Column->ColumnName}'";
						$errors .= $errorMsg . $options['error-message-suffix'];
					}
				}
				else{ //enum (and other types thta might have PossibleValues set, but why would you do that?)
					//for mysql, enums are case-insensetitive! "AAA"=="aAa" as valid enum values
					$isValidValue = in_array(strtolower($value), array_map('strtolower', $this->Column->PossibleValues)); //case-insensitive array search
					if(!$isValidValue){
						$errorMsg = "Invalid value '$value' specified for Table: '{$this->Column->Table->TableName}', Column: '{$this->Column->ColumnName}'";
						$errors .= $errorMsg . $options['error-message-suffix'];
					}
				}
			}
		}

		if ($this->Column->IsRequired){
			//int 0 is different from "0" (http://php.net/manual/en/types.comparisons.php). int 0 will consider the column as set for all datatypes except binary
			//so.. if the column is binary and the value is int 0, this is an error. if the column is anything else besides binary, this is NOT an error
			$dontAllowZero = ($this->Column->DataType == "binary");
			$valueIsZero = ($value === 0 || $value === "0");
			if( ($value==null && !$valueIsZero) || ($valueIsZero && $dontAllowZero) || strlen($value)<=0 ){
				$errorMsg = ( trim($this->Column->IsRequiredMessage)!="" ? $this->Column->IsRequiredMessage : "'{$this->Column->DisplayName}' field is required." );
				$errors .= $errorMsg . $options['error-message-suffix'];
				$inputRequiredErrorFound = true;
			}
		}

		$maxLength = $this->Column->GetMaxLength();
		if($maxLength) {
			if (strlen($value) > $maxLength){
				$errors .= "Number of characters allowed for '{$this->Column->DisplayName}' exceeds the $maxLength character limit.";
				$errors .= $options['error-message-suffix'];
			}
		}

		if($this->Column->MinSize) {
			if(!$inputRequiredErrorFound){ //ignore this if the field is required and empty. already have that error message
				$strlen = strlen($value);
				if(!$this->Column->IsRequired && $strlen == 0){
					//let this case pass because input is not required
				}
				else if ($strlen < $this->Column->MinSize){
					$errors .= "Minimum of {$this->Column->MinSize} characters are required for '{$this->Column->DisplayName}'.";
					$errors .= $options['error-message-suffix'];
				}
			}
		}

		if($this->Column->RegexCheck) {
			if(!$inputRequiredErrorFound){ //ignore regex if the field is required and empty. already have that error message
				if(!$this->Column->IsRequired && strlen($value) == 0){
					//let this case pass because input is not required
				}
				else if (!preg_match('/'.$this->Column->RegexCheck.'/i', $value)){ //both PHP and javascript do case-insensitive regex checking
					$errorMsg = ( trim($this->Column->RegexFailMessage)!="" ? $this->Column->RegexFailMessage : "Invalid valid for '{$this->Column->DisplayName}'." );
					$errors .= $errorMsg . $options['error-message-suffix'];
					$inputRegexCheckErrorFound = true;
				}
			}
		}

		if ($this->Column->IsUnique && !$this->Column->IsPrimaryKey) {
			if(!$inputRequiredErrorFound && !$inputRegexCheckErrorFound){
				if($value !== null){ //ignore 'null' values when checking uniqueness
					$dbManager = $this->Row->Database->DbManager;
					if(!$dbManager) throw new Exception("DbManager is not set. DbManager must be set to verify column value uniqueness within function '".__FUNCTION__."'. ");
					$numRowsFound = $dbManager->Select(array("*"), $this->Column->Table, array( array($this->Column->ColumnName => $value) ), '', 1);
					if ($numRowsFound > 0 ){
						if(!$this->Row->Exists()){ //$this row doesnt exist, so the found value is in use in another row
							
							//get formatted $currentValue
							$currentValue = htmlspecialchars($this->GetRawValue()); 
							
							$errors .= "Selected '{$this->Column->DisplayName}' (".$currentValue.") is already in use. Please select another value.";
							$errors .= $options['error-message-suffix'];
						}
						else if($this->Column->Table->PrimaryKeyExists()){ //$this row does exist. see if the found value is part of $this row by comparing key column(s)
							$row = $dbManager->FetchArray();
							$keyColumns = $this->Column->Table->GetKeyColumns();
							foreach($keyColumns as $columnName=>$Column){
								if($row[$columnName] != $this->Row->Cell($columnName)->GetRawValue()){
									//found row is not $this row, so found value is in use in another row
									
									//get formatted $currentValue
									$currentValue = htmlspecialchars($this->GetRawValue()); 
									
									$errors .= "Selected '{$this->Column->DisplayName}' (".$currentValue.") is already in use. Please select another value.";
									$errors .= $options['error-message-suffix'];
									break;
								}
							}
						} else {
							//no other way to automatically determine if the found value is part of this row. leave it to the programmer/mysql errors for duplicates
						}
					}
				}
			}
		}

		return $errors;
	}
	
	/////////////////////////////// ArrayAccess ///////////////////////////////////
	
	/**
	 * Sets the value in the Cell at the given column name ($key)
	 * @param string $key The column name
	 * @param object $value The new value for the cell at the given column name ($key)
	 * @ignore
	 */
	public function offsetSet($key,$value){
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		$array[$key] = $value;
		$this->SetValue($array);
	}
	/**
	 * Gets the Cell at the given column name ($key)
	 * @param string $key The column name
	 * @return The value in the cell at the given column name ($key)
	 * @ignore
	 */
	public function offsetGet($key){
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		return $array[$key];
	}
	/**
	 * Unsets the value in the cell at the given column name ($key)
	 * @param string $key The column name
	 * @ignore
	 */
	public function offsetUnset($key){
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		unset($array[$key]);
		$this->SetValue($array);
	}
	/**
	 * Checks if the cell at the given column name ($key) is set
	 * @param string $offset The column name
	 * @ignore
	 */
	public function offsetExists($offset){
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		return isset($array[$offset]);
	}
	
	/////////////////////////////// Countable ///////////////////////////////////
	/**
	 * For the Countable interface, this allows us to do count($table) to return the number of rows in the table.
	 * @return int The number of rows in this table.
	 */
	public function count() {
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		return count($array);
	}
	
	//////////////////////////// IteratorAggregate /////////////////////////////
	/**
	 * For the IteratorAggregate interface, this allows us to do foreach($SmartCell as $i=>$val)
	 * @return Iterator
	 */
	public function getIterator() {
		if($this->Column->DataType !== 'array') throw new \Exception('Cannot use SmartCell "'.$this->Column->ColumnName.'" ('.$this->Column->DataType.') as array');
		$array = $this->GetValue();
		return new \ArrayIterator($array);
	}

} //end class