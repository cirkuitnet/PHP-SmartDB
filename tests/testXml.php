<?
/**
 * @package Tests
 * @ignore
 */
/**
 */
require_once(dirname(__FILE__).'/../DbManagers/DbManager_MySQL.php');
require_once(dirname(__FILE__).'/../SmartDatabase.php');

/**
 * @package Tests
 * @ignore
 */
class Setting extends SmartRow{
	public function __construct($Database, $id=null ,$opts=null){
		parent::__construct('Setting', $Database, $id);

		//inline callback in constructor
		$this->OnBeforeDelete(function($eventObject, $eventArgs){
			if($GLOBALS['cancel-setting-delete']){ //just a manual global trigger
				$eventArgs['cancel-event'] = true; //should not continue with delete
			}
		});
		
		$this->OnBeforeColumnValueChanged(function($eventObject, $eventArgs){
			if(!empty($GLOBALS['cancel-setting-change-val'])){ //just a manual global trigger
				$eventArgs['cancel-event'] = true; //should not continue with delete
			}
		});
	}
}
/**
 * @package Tests
 * @ignore
 */
class Log extends SmartRow{
	public function __construct($Database, $id=null ,$opts=null){
		parent::__construct('Log', $Database, $id);
		$x = $this['Name'](); //causes the row to be initialized. was a bug previously
	}
}
/**
 * @package Tests
 * @ignore
 */
class Questionare extends SmartRow{
	public function __construct($Database, $id=null ,$opts=null){
		parent::__construct('Questionare', $Database, $id);
	}
}
/**
 * @package Tests
 * Shows table inheritance. CommonOptions doesnt really exist as a table (because the table 'IsAbstract'),
 * but you still can add functionality and inherit from it
 * @ignore
 */
class CommonFields extends SmartRow{
	public function __construct($tableName, $Database, $id=null, $opts=null){
		//custom functionality with CommonOptions that get inherited
    	parent::__construct($tableName, $Database, $id, $opts); //note: $tableName is passed through the constructor
	}

	public function DoCommonFields($debug){
		if($debug) echo "DoCommonFields(): <br>";
		$this['CommonFieldsName'] = "DoCommonFields";
		if($debug) echo "DoCommonFields Val: {$this['CommonFieldsName']}<br>";
	}
}
/**
 * @package Tests
 * @ignore
 */
class Junk extends CommonFields{
	//constructor is either the standard ($tableName, $database, $id, $options)
	//or contstructor is ($database, $id, $options) if we should use the default tablename
	public function __construct($arg1, $arg2=null, $arg3=null, $arg4=null){
		if(is_string($arg1)) parent::__construct($arg1, $arg2, $arg3, $arg4); //can change the table name from "Junk" to something else if we inherit from this class
        else parent::__construct("Junk", $arg1, $arg2, $arg3);
	}

	public function DoJunk($debug){
		if($debug) echo "DoJunk(): <br>";
		$this->DoCommonFields($debug);
		$this['CommonFieldsName'] = "DoJunk";
		if($debug) echo "DoJunk Val: {$this['CommonFieldsName']}<br>";
	}
}
/**
 * @package Tests
 * @ignore
 */
class JunkExtended extends Junk{
	//constructor is either the standard ($tableName, $database, $id, $options)
	//or contstructor is ($database, $id, $options) if we should use the default tablename
	public function __construct($arg1, $arg2=null, $arg3=null, $arg4=null){
		if(is_string($arg1)) parent::__construct($arg1, $arg2, $arg3, $arg4); //can change the table name from "Junk" to something else if we inherit from this class
        else parent::__construct("JunkExtended", $arg1, $arg2, $arg3);
	}

	public function DoJunkExtended($debug){
		if($debug) echo "DoJunkExtended(): <br>";
		$this->DoJunk($debug);
		$this['CommonFieldsName'] = "DoJunkExtended";
		if($debug) echo "DoJunkExtended Val: {$this['CommonFieldsName']}<br>";
	}
}

/**
 * @package Tests
 * @ignore
 */
class TestClass{
	public $x = 0;
	public $y = "\\1";
	public $z = "some quote's\"";
	public $a = null;
	public function __toString(){
		return "TestClass object";
	}
}

//--create db manager
$dbManagerOptions = array(
	'driver'=>'mysqli',
	'timezone'=>'+0:00'
);
$dbManager = $t['dbManager'] = new DbManager_MySQL('localhost','smartdb','smartdb123','smartdb_test', $dbManagerOptions);

//verify timezone
$dbManager->Query('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP)');
$results = $dbManager->FetchArrayList();
if($results[0][0] != '00:00:00'){
	throw new Exception('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP) result "'.$results[0].'", expected "00:00:00"');
}

//--build the Database instance
$database = $t['database'] = new SmartDatabase($dbManager, dirname(__FILE__).'/test.xml');

//below test reading a database structure and creating xml
//$database->ReadDatabaseStructure();
//echo $database->WriteXmlSchema();
//die;

$database->DEV_MODE_WARNINGS = false; //turn off warnings for now
//print_nice($database);
//echo $database->WriteXmlSchema();
//die;

$t['database']['Setting']->TableName = "Settings"; //change name of database table "Settings"

$GLOBALS['SQL_DEBUG_MODE'] = false; //set to true to see all SQL commands run through the db manager

//if(!$t['dbManager']->TableExists("smartdb_test", "AllDataTypes")){
	SyncDbTables($t);
//}

//////////////////////////////////////
?>
<!DOCTYPE HTML>
<html>
	<head>
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/core/1.9.1/jquery.min.js"></script>
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/validate/1.11.1/jquery.validate.js"></script>
		<script type="text/javascript">
			jQuery.validator.addMethod("regex", function(value, element, param) {
				var regex = new RegExp(param, "i");
			    return this.optional(element) || (regex.test(value) !== false);
			}, "Invalid value.");
			
			$(function(){
				//validate page properties form
				$("form[name=test7]").validate({
<?
					require_once(dirname(__FILE__)."/../FormValidation/SmartFormValidation_jQueryValidate.php");
					$formValidation = new SmartFormValidation_jQuery($database);
					$options = $formValidation->GetPluginOptions('Questionare');
?>
					rules: <?=json_encode($options['rules'])?>,
					messages: <?=json_encode($options['messages'])?>

				});
			});
		</script>
		<style type="text/css">
			.error{
				background-color:#FFD5D6;
				border:1px dashed #CC0000;
			}
			label.error{
				background:none;
				border:none;
				color: #cc0000;
				font-size: 11px;
				font-weight: bold;
				margin-left: 1em;
			}
		</style>
	</head>
	<body>
<?
		$database['FastSetting']->DeleteAllRows();
		$database['Setting']->DeleteAllRows();
		Test1($t);
		Test2($t);
		Test3($t);
		Test4($t);
		Test5($t);
		Test6($t);
		Test7($t);
		Test8($t);
		Test9($t);
		Test10($t);
		Test11($t);
		Test12($t);
		Test13($t);
		Test14($t); //enumerations and sets
		Test15($t); //0/null
		Test16($t); //min, max, sum
		Test17($t); //data types
		Test18($t); //serialization
		Test19($t); //smart-cell array access
		Test20($t); //JsonSerializable
		Test21($t); //lookup-row inline callbacks
		Test22($t); //dates with timezones
		TestForm($t,true);

		Msg(true,'Tests passed on '.date('r'),null);
?>
	</body>
</html>
<?
die();

/////////////////////////////////////////////////////
/**
 * tests sync db tables
 * @ignore
 */
function SyncDbTables($t){
	$printResults = false;
	$results = $t['database']->SyncStructureToDatabase($printResults);
	if(strstr($results, "Fatal error") !== false || strstr($results, "exception") !== false){
		throw new Exception("SyncDbTablesFailed");
	}
	return $results;
}

function AssertIsDirty($i){
	if(!$i->IsDirty()) throw new Exception("Row is not dirty");
}
function AssertIsNotDirty($i){
	if($i->IsDirty()) throw new Exception("Row is dirty");
}
function AssertExists($i){
	if(!$i->Exists()) throw new Exception("Row doesn't exist");
}
function AssertNotExists($i){
	if($i->Exists()) throw new Exception("Row exists");
}
function AssertCellVal($c, $value){
	if(!$c) throw new Exception("Column not set");
	$rowVal = $c->GetValue();
	
	if(is_object($rowVal) || is_object($value)){ //check objects
		if( (is_object($rowVal) && !is_object($value)) || (!is_object($rowVal) && is_object($value)) ){
			throw new Exception("Row value '$rowVal' (".gettype($rowVal).") doensn't match '$value' (".gettype($value).")");
		}
	}
	else{ //not object
		if($rowVal !== $value) throw new Exception("Row value '$rowVal' (".gettype($rowVal).") doensn't match '$value' (".gettype($value).")");
	
		$rowVal = $c(); //GetValue() shortcut, new with PHP 5.3.0
		if($rowVal !== $value) throw new Exception("Row value '$rowVal' (".gettype($rowVal).") doensn't match '$value' (".gettype($rowVal).") when using GetValue shortcut");
	}
}
function Commit($i,$debug){
	if($debug) echo "<br>Before commit: <br>\n".$i;
	$i->Commit();
	if($debug) echo "<br>After commit: <br>\n".$i;
	AssertIsNotDirty($i);;
	AssertExists($i);
}
function Delete($i,$debug){
	if($debug) echo "<br>Before delete: <br>\n".$i;
	if(!$i->Delete()) Ex("Delete returned false");
	if($debug) echo "<br>After delete: <br>\n".$i;
	AssertIsNotDirty($i);;
	AssertNotExists($i);
}
function Msg($debug,$msg,$i){
	if($debug) echo "<br><b>$msg</b><br>\n".$i;
}
function Ex($msg){
	throw new Exception($msg);
}


/**
 * gets column properties
 * @ignore
 */
function Test1($t){
	$type = $t['database']['Setting']['Id']->DataType;
	if($type !== "int") Ex("Get column property failed.");
}

/**
 * changing non-autoincrement keys and values
 * @ignore
 */
function Test2($t, $debug=false){
	$i = new Setting($t['database'], 69);
	if($i->Exists()){ //row exists. delete it for our test
		Delete($i,$debug);
	}
	$i = new Setting($t['database'], 70);
	if($i->Exists()){ //row exists. delete it for our test
		Delete($i,$debug);
	}

	$i = new Setting($t['database']);
	Msg($debug,'Before set values:',$i);

	$i['Name'] = "test name";
	$i->Column('Id')->SetValue('69');

	AssertIsDirty($i);
	AssertNotExists($i);

	Commit($i,$debug);
	AssertCellVal($i['Id'], 69);
	AssertCellVal($i['Name'], "test name");
	if($i['datetime']() <= 0) throw new Exception("CURRENT_TIMESTAMP datetime did not save to database");
	if($i['timestamp']() <= 0) throw new Exception("CURRENT_TIMESTAMP timestamp did not save to database");

	//new instance
	$i = new Setting($t['database']);
	$i['Id'] = 69;
	Msg($debug,'Lookup instance:',$i);
	AssertIsNotDirty($i);;
	AssertExists($i);

	$i['Name'] = "test name numero dos";
	Msg($debug,'Change column on lookup instance:',$i);
	AssertIsDirty($i);

	Commit($i,$debug);
	AssertCellVal($i->Cell('Id'), 69);
	AssertCellVal($i['Name'], "test name numero dos");

	$i['Id'] = 70;
	Msg($debug,'Change Id:',$i);
	AssertNotExists($i);
	AssertIsNotDirty($i);

	Commit($i,$debug);

	$i['Id'] = 69;
	$i['Name'] = "test name 3";
	$i['Enabled'] = false;
	Msg($debug,'Change Id, Name, and Enabled:',$i);
	AssertCellVal($i['Name'], "test name 3");
	AssertExists($i);

	Commit($i,$debug);


	//bug fix check for false. was putting /0 in the database which evaluates to true when returned!
	$i = new Setting($t['database'], 69);
	$enabled = $i['Enabled']->GetValue();
	if($enabled) throw new Exception("Invalid boolean type returned");
	if($debug){
		var_dump($enabled);
		die;
	}
}

/**
 * autoincrement keys
 * @ignore
 */
function Test3($t, $debug=false){
	$i = new SmartRow('User', $t['database']);
	Msg($debug,'Before set values:',$i);

	AssertIsNotDirty($i);
	AssertNotExists($i);

	$threwException = false;
	try{
		$i->Column('Id')->SetValue('69');
	}
	catch(Exception $e){
		$threwException = true;
	}
	if(!$threwException) Ex("Setting an auto-increment key column did not error.");

	$i['Name'] = "user name";
	Msg($debug,'After set name:',$i);
	AssertCellVal($i->Column('Name'), "user name");
	AssertIsDirty($i);

	Commit($i,$debug);

	//new instance through lookup
	$i = new SmartRow('User', $t['database'], $i['Id']);
	Msg($debug,'Lookup instance:',$i);
	AssertIsNotDirty($i);;
	AssertExists($i);

	$i['Name'] = null;
	Msg($debug,'After set name to null:',$i);
	AssertCellVal($i['Name'], null);
	AssertIsDirty($i);

	Commit($i,$debug);

	Delete($i,$debug);
}

/**
 * invalid table
 * @ignore
 */
function Test4($t, $debug=false){
	$threwException = false;
	try{
		$i = new SmartRow('Logg', $t['database']);
		echo $i;
	}
	catch(Exception $e){
		$threwException = true;
	}
	if(!$threwException) Ex("Invalid table name did not error.");
}

/**
 * cell callbacks
 * @ignore
 */
function Test5($t, $debug=false){
	$i = new Log($t['database']);
	Msg($debug,'Before set values:',$i);
	$GLOBALS['test5']['row'] = $i;
	$i['Name'] = "Pumpkin Cakes";
	AssertCellVal($i['Name'], "Pumpkin Cakes");

	//after value changed
	$GLOBALS['test5']['AfterValueChanged'] = false;
	function AfterValueChanged($eventObject, $eventArgs){
		//Msg(true,"After Name ValueChanged callback",$eventObject);
		$GLOBALS['test5']['AfterValueChanged'] = true;
		//row instance object successfully passed through?
		if(count($eventArgs) != 3) Ex('Wrong arg count');
		if ($GLOBALS['test5']['row'] !== $eventObject) Ex("Instances do not match.");
		AssertCellVal($eventObject['Name'], "Test Name");
		AssertCellVal($GLOBALS['test5']['row']['Name'], $eventArgs['current-value']);
		if($eventArgs['old-value'] !== "Pumpkin Cakes") Ex('Old value doesnt match');
	}
	$i['Name']->OnAfterValueChanged("AfterValueChanged");
	$i['Name'] = " Test Name ";
	if(!$GLOBALS['test5']['AfterValueChanged']) Ex('Callback didnt run');
	Msg($debug,'After set name:',$i);
	Commit($i,$debug);

	//before value changed
	$GLOBALS['test5']['cell'] = $i['Level'];
	$GLOBALS['test5']['BeforeValueChanged'] = false;
	$GLOBALS['test5']['newVal1235'] = false;
	/*
	function BeforeValueChanged($eventObject, $eventArgs){
		//Msg(true,"Before Level ValueChanged callback",$eventObject);
		$GLOBALS['test5']['BeforeValueChanged'] = true;
		//row instance object successfully passed through?
		if(count($eventArgs) != 4) Ex('Wrong arg count');
		if ($GLOBALS['test5']['row'] !== $eventObject) Ex("Instances do not match.");
		if ($GLOBALS['test5']['cell'] !== $eventArgs['Cell']) Ex("Instances do not match.");
		AssertCellVal($eventObject['Name'], "Test Name");
		AssertCellVal($GLOBALS['test5']['row']['Level'], $eventArgs['current-value']);

		if($eventArgs['new-value'] === 12.35){
			$GLOBALS['test5']['newVal1235'] = true;
			$eventArgs['cancel-event'] = true;
		}
		else if($eventArgs['new-value'] !== 12.34) Ex('New value doesnt match');
	}
	$i['Level']->OnBeforeValueChanged("BeforeValueChanged",null);
	*/
	//test using anonymous functions in callbacks instead of the old way above
	$i['Level']->OnBeforeValueChanged(function($eventObject, $eventArgs){ //AS OF PHP 5.3.0
		//Msg(true,"Before Level ValueChanged callback",$eventObject);
		$GLOBALS['test5']['BeforeValueChanged'] = true;
		//row instance object successfully passed through?
		if(count($eventArgs) != 4) Ex('Wrong arg count');
		if ($GLOBALS['test5']['row'] !== $eventObject) Ex("Instances do not match.");
		if ($GLOBALS['test5']['cell'] !== $eventArgs['Cell']) Ex("Instances do not match.");
		AssertCellVal($eventObject['Name'], "Test Name");
		AssertCellVal($GLOBALS['test5']['row']['Level'], $eventArgs['current-value']);

		if($eventArgs['new-value'] === 12.35){
			$GLOBALS['test5']['newVal1235'] = true;
			$eventArgs['cancel-event'] = true;
		}
		else if($eventArgs['new-value'] !== 12.34) Ex('New value doesnt match');
	});
	$i['Level'] = 12.34;
	if(!$GLOBALS['test5']['BeforeValueChanged']) Ex('Callback didnt run');

	$GLOBALS['test5']['BeforeValueChanged'] = false;

	$i['Level'] = 12.34;
	if($GLOBALS['test5']['BeforeValueChanged']) Ex('Callback ran');
	Msg($debug,'After set level:',$i);
	Commit($i,$debug);

	$i['Level']->DisableCallbacks = true;
	$i['Level'] = 13;
	if($GLOBALS['test5']['BeforeValueChanged']) Ex('Callback ran');
	AssertCellVal($i['Level'], (double)13);

	$i['Level']->DisableCallbacks = false;
	$i->DisableCallbacks(true);
	$i['Level'] = '12.34';
	if($GLOBALS['test5']['BeforeValueChanged']) Ex('Callback ran');
	AssertCellVal($i['Level'], 12.34);

	$i->EnableCallbacks (true);
	$GLOBALS['test5']['BeforeValueChanged'] = false;
	$i['Level'] = 12.35;
	if(!$GLOBALS['test5']['BeforeValueChanged']) Ex('Callback didnt run');
	if(!$GLOBALS['test5']['newVal1235']) Ex('Wrong new value');
	AssertCellVal($i['Level'], 12.34);

	$GLOBALS['test5']['cell'] = $i['Timestamp'];
	/**
 	 * @package Tests
 	 * @ignore
	 */
	class TestStaticCallbackClass{
		public static function ValueSet($eventObject, $eventArgs){
			//Msg(true,"Timestamp ValueSet callback",$eventObject);
			$GLOBALS['test5']['ValueSet'] = true;
			//row instance object successfully passed through?
			if(count($eventArgs) != 4) Ex('Wrong arg count');
			if ($GLOBALS['test5']['row'] !== $eventObject) Ex("Instances do not match.");
			if ($GLOBALS['test5']['cell'] !== $eventArgs['Cell']) Ex("Instances do not match.");
			AssertCellVal($eventObject['Timestamp'], null);
			AssertCellVal($GLOBALS['test5']['row']['Timestamp'], $eventArgs['current-value']);

			if($eventArgs['new-value'] === $GLOBALS['test5']['timestamp1']){ //callbacks are RAW values, so won't have timezones 
				$eventArgs['cancel-event'] = true;
			}

		}
	}
	$i['Timestamp']->OnSetValue('ValueSet','TestStaticCallbackClass');
	Commit($i,$debug);
	$timestamp = gmdate("Y-m-d H:i:s");
	$GLOBALS['test5']['timestamp1'] = $timestamp;
	$i['Timestamp'] = $timestamp;
	Msg($debug,'After timestamp set and cancelled',$i);
	AssertCellVal($i['Timestamp'], null);

	$timestamp = gmdate("2008-m-d H:i:s");
	$GLOBALS['test5']['timestamp2'] = $timestamp;
	$i['Timestamp'] = $timestamp;
	Msg($debug,'After timestamp set',$i);
	AssertCellVal($i['Timestamp'], $timestamp);
	Commit($i,$debug);
	
	//date_default_timezone_set('America/Los_Angeles'); //test different tz

	$timestamp = gmdate("2008-m-d H:i:s");
	date_default_timezone_set('America/Chicago'); //test different tz
	$i->DisableCallbacks (true);
	$GLOBALS['test5']['timestamp2'] = $timestamp;
	$i['Timestamp'] = $timestamp;
	Msg($debug,'After timestamp set',$i);
	AssertCellVal($i['Timestamp'], $timestamp);
	Commit($i,$debug);
	$i->EnableCallbacks (true);
	Delete($i,$debug);
	
	date_default_timezone_set('America/Indiana/Indianapolis'); //test different tz
}
/**
 * Array row functions, cloning, lookup by set value
 * @ignore
 */
function Test6($t, $debug=false){
	$i = new Log($t['database']);
	$i['Name'] = 'Connection Interrupted';
	$timestamp = gmdate("2009-m-d H:i:s");
	$i['Timestamp'] = $timestamp;
	$i['Level'] = 6543.21;
	Commit($i, $debug);

	$id = $i['Id']();
	if(!isset($id)) Ex('Id is not set');

	$nonKeys = $i->GetNonKeyColumnValues();
	if(count($nonKeys['Log']) != 4) Ex('Wrong column count returned');
	if($nonKeys['Log']['Name'] !== 'Connection Interrupted') Ex('Log Name not equal');
	if($nonKeys['Log']['Timestamp'] !== $timestamp) Ex('Log timestamp ('.$nonKeys['Log']['Timestamp'].') not equal ('.$timestamp.')');
	if($nonKeys['Log']['Level'] !== 6543.21) Ex('Log level not equal');

	$keys = $i->GetKeyColumnValues();
	if(count($keys['Log']) != 1) Ex('Wrong column count returned');

	$keys = $i->GetColumnValues();
	if(count($keys['Log']) != 5) Ex('Wrong column count returned');


	$j = $i->GetShallowClone();
	AssertCellVal($j['Id'], NULL);
	AssertCellVal($j['Name'], 'Connection Interrupted');
	AssertCellVal($j['Timestamp'], $timestamp);
	AssertCellVal($j['Level'], 6543.21);
	AssertIsDirty($j);
	AssertIsNotDirty($i);
	AssertExists($i);
	AssertNotExists($j);

	$exceptionThrown = false;
	try{
		Delete($j, $debug);
	}
	catch(Exception $e){
		$exceptionThrown = true;
	}
	if(!$exceptionThrown) Ex('Exception not thrown');

	$keys = $j->LookupKeys(false);
	if(count($keys) !== 1) Ex('Wrong count returned');
	AssertCellVal($j['Id'], NULL);
	AssertNotExists($j);

	$keys = $j->LookupKeys(true);
	if(count($keys) !== 1) Ex('Wrong count returned');

	AssertCellVal($j['Id'], $id);
	AssertExists($j);

	Delete($i, $debug);
}
/**
 * Error checking
 * @ignore
 */
function Test7($t, $debug=false){
	$i = new SmartRow('FastSetting', $t['database']);
	Msg($debug, "New FastSetting", $i);
	if($i->HasErrors() === false) Ex('$i has no errors');
	if(count($i->GetColumnsInError())!=2) Ex('wrong number of cells in error');

	$i->Table->AutoCommit = false;
	$i['Name'] = "test123";
	$i['ShortName'] = "12345";
	Msg($debug, "Set invalid name", $i);
	if($i->HasErrors() === false) Ex('$i has no errors');
	if(count($i->GetColumnsInError())!=2) Ex('wrong number of cells in error');

	if($debug) echo "Cells in error case1: ".print_r(array_keys($i->GetColumnsInError()),true)."<br>\n";

	$i['Id'] = '420';
	if(count($i->GetColumnsInError())!=1) Ex('wrong number of cells in error');
	if($debug) echo "Cells in error case2: ".print_r(array_keys($i->GetColumnsInError()),true)."<br>\n";
	$i['Name'] = "test123@test.com";
	Msg($debug, "Set valid values", $i);
	if($i->HasErrors() !== false) Ex('$i has errors');

	Commit($i, $debug);

	$i['ShortName']='12345678901';
	Msg($debug, "Set invalid shortnname", $i);
	if(count($i->GetColumnsInError())!=1) Ex('wrong number of cells in error');

	Delete($i, $debug);
}

/**
 * column functions
 * @ignore
 */
function Test8($t, $debug=false){
	$db = $t['database'];
	$allvalues = $db['Setting']['Name']->GetAllValues(array('sort-by'=>'Name','get-unique'=>true,'return-count'=>&$count));
	if($count!=2) Ex("invalid count returned");
	if($debug) echo '$allvalues='.print_r($allvalues, true);
	if(count($allvalues)!=2) Ex("invalid count of values returned");
	
	//LookupColumnValues() with empty array or null as second parameter should work the same as GetAllValues()
	$allvalues = $db['Setting']->LookupColumnValues('Name', null, array('sort-by'=>'Name','get-unique'=>true,'return-count'=>&$count));
	if($count!=2) Ex("invalid count returned");
	if($debug) echo '$allvalues='.print_r($allvalues, true);
	if(count($allvalues)!=2) Ex("invalid count of values returned");
	
	//LookupColumnValues() with first and second parameters switched
	$allvalues = $db['Setting']->LookupColumnValues(null, 'Name', array('sort-by'=>'Name','get-unique'=>true,'return-count'=>&$count));
	if($count!=2) Ex("invalid count returned");
	if($debug) echo '$allvalues='.print_r($allvalues, true);
	if(count($allvalues)!=2) Ex("invalid count of values returned");

	$returnCount = $db['Setting']['Name']->GetAllValues(array('sort-by'=>array('Id'=>'desc'),'return-count'=>&$count,'return-count-only'=>true));
	if($count!=2) Ex("invalid count returned");
	if($debug) echo '$returnCount='.$returnCount;
	if($returnCount!=2) Ex("invalid count of values returned");

	$rows = $db['Setting']['Name']->LookupRows('test name 3');
	AssertExists($rows[0]);
	AssertIsNotDirty($rows[0]);
	Msg($debug,"count of rows is ".count($rows),$rows[0]);
	if(count($rows)!=1) Ex("wrong row count returned");
	
	$rows = $db['Setting']['Name']('test name 3');
	AssertExists($rows[0]);
	AssertIsNotDirty($rows[0]);
	Msg($debug,"count of rows is ".count($rows),$rows[0]);
	if(count($rows)!=1) Ex("wrong row count returned");
	
	$row = $db['Setting']->LookupRow('');
	AssertNotExists($row, $debug);
	
	$row = $db['Setting']->LookupRow('1');
	AssertNotExists($row, $debug);
	
	$row = $db['Setting']->LookupRow(1);
	AssertNotExists($row, $debug);

	$row = $db['FastSetting']['Name']->LookupRow('testname@numero.dos');
	AssertNotExists($row, $debug);
	AssertIsDirty($row, $debug);
	AssertCellVal($row['Name'],'testname@numero.dos');
	$row['Id'] = 23;
	$row->Table->AutoCommit = true; //set to false in test 7
	$row['ShortName'] = "ten  chars";
	AssertExists($row, $debug);
	
	//lookup row with a column alias
	$row = $db['FastSetting']->LookupRow(array("NotLongName"=>null));
	AssertNotExists($row, $debug);

	$row = null;
	$row = $db['FastSetting']['Name']->LookupRowWithValue('testname@numero.dos'); //LookupRow Alias
	AssertExists($row, $debug);
	AssertIsNotDirty($row, $debug);
	AssertCellVal($row['Name'],'testname@numero.dos');
	Commit($row, $debug);

	//column level delete
 	$db['FastSetting']['Name']->DeleteRows('testname@numero.dos');
	$row = $db['FastSetting']['Name']->LookupRow('testname@numero.dos');
	AssertNotExists($row, $debug);
	
	
	//insert some setting rows to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 1260;
	$Setting['Name'] = "Triple down.";
	Commit($Setting, $debug);
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']['Name']->DeleteRows('Double up.', ['skip-callbacks'=>false]); //SHOULD NOT be deleted
	if($rowsDeleted !== 0) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = false;
	$rowsDeleted = $db['Setting']['Name']->DeleteRows('Double up.', ['skip-callbacks'=>false]); //SHOULD be deleted
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']['Name']->DeleteRows('Triple down.', ['skip-callbacks'=>true]); //SHOULD be deleted because we're skipping callbacks
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	$GLOBALS['cancel-setting-delete'] = false;
	

	//SetAllValues()
	//should have 2 Setting rows at this point:
	
	$GLOBALS['cancel-setting-change-val'] = true;
	$rowsUpdated = $db['Setting']['Enabled']->SetAllValues(true, ['skip-callbacks'=>false]); //SHOULD NOT be updated
	if($rowsUpdated !== 0) Ex("Wrong number of Setting rows updated - $rowsUpdated");
	
	$GLOBALS['cancel-setting-change-val'] = false;
	$rowsUpdated = $db['Setting']['Enabled']->SetAllValues(true, ['skip-callbacks'=>false]); //SHOULD be updated
	if($rowsUpdated !== 2) Ex("Wrong number of Setting rows updated - $rowsUpdated");
	$rowsMatchingCount = $db['Setting']['Enabled']->LookupRows(true, ['return-count-only'=>true]); //verify values have been updated
	if($rowsMatchingCount !== 2) Ex("set all values, but wrong rowcount returned for values changed.");
	
	$GLOBALS['cancel-setting-change-val'] = true;
	$rowsUpdated = $db['Setting']['Enabled']->SetAllValues(false, ['skip-callbacks'=>true]); //SHOULD be updated because we're skipping callbacks
	if($rowsUpdated !== 2) Ex("Wrong number of Setting rows updated - $rowsUpdated");
	$GLOBALS['cancel-setting-change-val'] = false;
	
	$rows = $db['Setting']['Enabled']->LookupRows(false);
	if(count($rows) != 2)  Ex("Wrong number of Setting rows updated - $rowsUpdated");
}


/**
 * helper function for test9 and test10 (at the least)
 * @ignore
 */
function InsretSomeFastSettingRows($db, $debug=false){
		$i = new SmartRow('FastSetting', $db); //add some rows
		$i->Table->AutoCommit=false;
		$i['Id'] = 34;
		$i['Name'] = "my@name.com";
		$i['ShortName'] = "short name";
		Commit($i, $debug);

		$i = $i->GetShallowClone();
		$i['Id'] = 35;
		$i['Name'] = "my@name2.com";
		Commit($i, $debug);
		AssertCellVal($i['ShortName'], "short name");
	}

/**
 * table get/delete row functions
 * @ignore
 */
function Test9($t, $debug=false){
	$db = $t['database'];
	$count=count($db['Setting']);
	if($debug) echo "Get row count using count():$count<br>\n";
	if($count !== 2) Ex("Wrong row count ({$count}) returned");

	//GetAllRows()
	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"desc")));
	$count=count($allRows);
	if($debug) echo "Get all rows, count: $count<br>\n";
	if($count !== 2) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], 70);
	AssertCellVal($allRows[1]['Id'], 69);

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}
	
	//GetAllRows() with 'return-next-row' option set
	$lastRow = null;
	$totalIterations = 0;
	$totalCallbackIterations = 0;
	while( $row = $db['Setting']->GetAllRows(array(
		'return-next-row'=>&$curCount,
		'return-count'=>&$totalCount,
		'callback'=>function($row,$i) use(&$totalCallbackIterations){ //test inline callbacks here too
			$totalCallbackIterations++;
		}
	))){
		//echo "$curCount - $totalCount - $row<br>";
		$totalIterations++;
		$lastRow = $row;
	}
	if($debug) echo "Get all rows, count: $totalCount<br>\n";
	if($totalCount !== 2) Ex("Wrong number of rows returned");
	if($totalCallbackIterations !== 2) Ex("Wrong number of callback iterations");
	if($totalCount != $totalIterations) Ex("Wrong number of iterations: $totalCount");

	AssertCellVal($lastRow['Id'], 70);
	
	//LookupRows() with empty array, should be idential to GetAllRows() (as above)
	$allRows = $db['Setting']->LookupRows(array(), array('sort-by'=>array("AliasName"=>"desc"))); //sort-by with alias name
	$count=count($allRows);
	if($debug) echo "Get all rows, count: $count<br>\n";
	if($count !== 2) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], 70);
	AssertCellVal($allRows[1]['Id'], 69);

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}
	
	//LookupRows() with 'return-next-row' option set, should be idential to GetAllRows() (as above)
	$lastRow = null;
	$totalIterations = 0;
	$totalCallbackIterations = 0;
	while( $row = $db['Setting']->LookupRows(array(), array(
		'return-next-row'=>&$curCount,
		'return-count'=>&$totalCount,
		'callback'=>function($row,$i) use(&$totalCallbackIterations){ //test inline callbacks here too
			$totalCallbackIterations++;
		}
	))){
		//echo "$curCount - $totalCount - $row<br>";
		$totalIterations++;
		$lastRow = $row;
	}
	if($debug) echo "Get all rows, count: $totalCount<br>\n";
	if($totalCount !== 2) Ex("Wrong number of rows returned");
	if($totalCallbackIterations !== 2) Ex("Wrong number of callback iterations");
	if($totalCount != $totalIterations) Ex("Wrong number of iterations: $totalCount");

	AssertCellVal($lastRow['Id'], 70);

	//GetAllRows()
	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'limit'=>1));
	$count=count($allRows);
	if($debug) echo "Get all rows, limit 1, count: $count<br>\n";
	if($count !== 1) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], 69);

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}

	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'limit'=>'1,1'));
	$count=count($allRows);
	if($debug) echo "Get all rows, limit 1, count: $count<br>\n";
	if($count !== 1) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], 70);

	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'return-assoc'=>true));
	$count=count($allRows);
	if($debug) echo "Get all rows, count: $count<br>\n";
	if($count !== 2) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[70]['Id'], 70);
	AssertCellVal($allRows[69]['Id'], 69);

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}

	//DeleteAllRows()
	$rowsDeleted = $db['Setting']->DeleteAllRows();
	if($debug) echo("Setting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 2) Ex("Wrong number of Setting rows deleted");
	
	//DeleteRow()
	InsretSomeFastSettingRows($db);
	
	try{
		$exceptionHit=false;
		$rowsDeleted = $db['FastSetting']->DeleteRow(array("ShortName"=>"short name"));
	}
	catch(Exception $e){
		$exceptionHit = true;
	}
	if(!$exceptionHit) Ex("DeleteRow didn't throw exception for deleting with a non-unique/key column");
	
	$rowsDeleted = $db['FastSetting']->DeleteRow(array("Id"=>37));
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 0) Ex("Wrong number of FastSetting rows deleted");
	
	$rowsDeleted = $db['FastSetting']->DeleteRow(array("Id"=>35));
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 1) Ex("Wrong number of FastSetting rows deleted");

	$rowsDeleted = $db['FastSetting']->DeleteRow(34);
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 1) Ex("Wrong number of FastSetting rows deleted");
	
	$rowsDeleted = $db['FastSetting']->DeleteRow(34);
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 0) Ex("Wrong number of FastSetting rows deleted");
	
	//DeleteRows()
	InsretSomeFastSettingRows($db);

	$rowsDeleted = $db['FastSetting']->DeleteRows(array("ShortName"=>"short name"));
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 2) Ex("Wrong number of FastSetting rows deleted");

	InsretSomeFastSettingRows($db);
	$rowsDeleted = $db['FastSetting']->DeleteRows(array("ShortName"=>"short name", "Name"=>"my@name.com"));
	if($debug) echo("FastSetting rows deleted: $rowsDeleted<br>\n");
	if($rowsDeleted !== 1) Ex("Wrong number of FastSetting rows deleted");
	
	
	//insert a setting row to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteRow(840, ['skip-callbacks'=>false]); //SHOULD NOT be deleted
	if($rowsDeleted !== 0) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = false;
	$rowsDeleted = $db['Setting']->DeleteRow(840, ['skip-callbacks'=>false]); //SHOULD be deleted
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	
	
	//insert a setting row to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);

	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteRow(840, ['skip-callbacks'=>true]); //SHOULD be deleted because we're skipping callbacks
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	$GLOBALS['cancel-setting-delete'] = false;
	
	
	//insert some setting rows to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 1260;
	$Setting['Name'] = "Triple down.";
	Commit($Setting, $debug);
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteRows(['Name'=>'Double up.'], ['skip-callbacks'=>false]); //SHOULD NOT be deleted
	if($rowsDeleted !== 0) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = false;
	$rowsDeleted = $db['Setting']->DeleteRows(['Name'=>'Double up.'], ['skip-callbacks'=>false]); //SHOULD be deleted
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteRow(1260, ['skip-callbacks'=>true]); //SHOULD be deleted because we're skipping callbacks
	if($rowsDeleted !== 1) Ex("Wrong number of Setting rows deleted");
	$GLOBALS['cancel-setting-delete'] = false;
	
	
	//insert some setting rows to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 1260;
	$Setting['Name'] = "Triple down.";
	Commit($Setting, $debug);
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteAllRows(['skip-callbacks'=>false]); //SHOULD NOT be deleted
	if($rowsDeleted !== 0) Ex("Wrong number of Setting rows deleted");
	
	$GLOBALS['cancel-setting-delete'] = false;
	$rowsDeleted = $db['Setting']->DeleteAllRows(['skip-callbacks'=>false]); //SHOULD be deleted
	if($rowsDeleted !== 2) Ex("Wrong number of Setting rows deleted");
	
	
	//insert some setting rows to delete
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 840;
	$Setting['Name'] = "Double up.";
	Commit($Setting, $debug);
	$Setting = $db['Setting']->GetNewRow();
	$Setting['Id'] = 1260;
	$Setting['Name'] = "Triple down.";
	Commit($Setting, $debug);
	
	$GLOBALS['cancel-setting-delete'] = true;
	$rowsDeleted = $db['Setting']->DeleteAllRows(['skip-callbacks'=>true]); //SHOULD be deleted because we're skipping callbacks
	if($rowsDeleted !== 2) Ex("Wrong number of Setting rows deleted");
	$GLOBALS['cancel-setting-delete'] = false;
}

/**
 * table lookup functions
 * @ignore
 */
function Test10($t, $debug=false){
	$db = $t['database'];
	InsretSomeFastSettingRows($db);

	//LookupColumnValues. first and second parameter switched
	$values = $db['FastSetting']->LookupColumnValues(array("ShortName"=>"short name"),"Name",array('sort-by'=>'Id'));
	if(count($values) !== 2) Ex("Wrong return count");
	if($values[0] !== "my@name.com") Ex("Row ShortName value not correct");

	$values = $db['FastSetting']->LookupColumnValues("Name",array("ShortName"=>"short name"),array('sort-by'=>array('Id'=>'DEsC'),'return-assoc'=>true));
	if(count($values) !== 2) Ex("Wrong return count");
	if($values[35] !== "my@name2.com") Ex("Row ShortName value not correct");
	
	$values = $db['FastSetting']->LookupColumnValues("ShortName",array("Name"=>array('or'=>array('my@name2.com','my@name.com'))),array('sort-by'=>array('ShortName'=>'DEsC'),'get-unique'=>true));
	if(count($values) !== 1) Ex("Wrong return count");
	if($values[0] !== "short name") Ex("Row ShortName value not correct");

	//LookupRow
	$caughtError = false;
	try{
		$db['FastSetting']->LookupRow(array("ShortName"=>"short name"));
	} catch(Exception $e) {
		$caughtError = true;
	}
	if(!$caughtError) Ex("Did not catch error. Multiple rows matched search critria");

	$row = $db['FastSetting']->LookupRow(array("ShortName"=>"short name", "Name"=>"my@name.com"));
	Msg($debug, "Looked up row, match", $row);
	AssertExists($row);
	AssertIsNotDirty($row);

	$row = $db['FastSetting']->LookupRow(array("ShortName"=>"short name", "Name"=>"my@names.com"));
	Msg($debug, "Looked up row, no match", $row);
	AssertNotExists($row);
	AssertIsDirty($row);
	AssertCellVal($row['ShortName'], "short name");
	AssertCellVal($row['Name'], "my@names.com");

	$row = $db['FastSetting']->LookupRow(35); //lookup by primary key column
	Msg($debug, "Looked up row by id, match", $row);
	AssertCellVal($row['Id'], 35);
	AssertExists($row);
	AssertIsNotDirty($row);
	
	//LookupRow shortcut
	$row = $db['FastSetting'](array("ShortName"=>"short name", "Name"=>"my@name.com"));
	Msg($debug, "Looked up row, match", $row);
	AssertExists($row);
	AssertIsNotDirty($row);
	AssertCellVal($row['ShortName'], "short name");
	AssertCellVal($row['Name'], "my@name.com");
	
	$row = $db['FastSetting'](array("ShortName"=>"short name", "Name"=>"my@names.com"));
	Msg($debug, "Looked up row, no match", $row);
	AssertNotExists($row);
	AssertIsDirty($row);
	AssertCellVal($row['ShortName'], "short name");
	AssertCellVal($row['Name'], "my@names.com");
	
	//multiple dimension lookup row.
	$row = $db['FastSetting'](array("AND" => array("ShortName"=>"short name", "Name"=>array("my@names.com","something@else.com"))));
	Msg($debug, "Looked up row, no match", $row);
	AssertNotExists($row);
	AssertIsDirty($row);
	AssertCellVal($row['ShortName'], "short name");
	AssertCellVal($row['Name'], null);
	
	$row = $db['FastSetting']('35'); //lookup by primary key column
	Msg($debug, "Looked up row by id, match", $row);
	AssertCellVal($row['Id'], 35);
	AssertExists($row);
	AssertIsNotDirty($row);
	
	//Get New Row
	$row = $db['FastSetting']->GetNewRow();
	Msg($debug, "Get new row", $row);
	AssertNotExists($row);
	AssertIsNotDirty($row);
	
	//Get new row shortcut
	$row = $db['FastSetting']();
	Msg($debug, "Get new row", $row);
	AssertNotExists($row);
	AssertIsNotDirty($row);

	//Lookup Rows
	$rows = $db['FastSetting']->LookupRows(array("ShortName"=>"short name"));
	if(count($rows) !== 2) Ex("Wrong count of rows returned");

	//Lookup Rows
	//$GLOBALS['SQL_DEBUG_MODE'] = true;
	//$rows = $db['FastSetting']->LookupRows(array("Name"=>"my@name2.com","Name"=>"my@name.com"));
	//if(count($rows) !== 2) Ex("Wrong count of rows returned");
	//$GLOBALS['SQL_DEBUG_MODE'] = false;

	//Lookup Rows
	$rows = $db['FastSetting']->LookupRows(array("ShortName"=>"short name not exist"));
	if(count($rows) !== 0) Ex("Wrong count of rows returned");
	
	//Lookup Rows in loop with 'return-next-row' option set
	$lastRow = null;
	$totalIterations = 0;
	$totalCallbackIterations = 0;
	while( $row = $db['FastSetting']->LookupRows(array("ShortName"=>"short name"), array(
		'return-next-row'=>&$curCount,
		'return-count'=>&$totalCount,
		'callback'=>function($row,$i) use(&$totalCallbackIterations){ //test inline callbacks here too
			$totalCallbackIterations++;
		},
		'sort-by'=>["Id"=>"DESC"]
	))){
		//echo "$curCount - $totalCount - $row<br>";
		$totalIterations++;
		$lastRow = $row;
	}
	if($totalCount !== 2) Ex("Wrong number of rows returned");
	if($totalCallbackIterations !== 2) Ex("Wrong number of callback iterations");
	if($totalCount != $totalIterations) Ex("Wrong number of iterations: $totalCount");
	AssertCellVal($lastRow['Id'], 34);
}

/**
 * related row functions and invalid objects
 * @ignore
 */
function Test11($t, $debug=false){
	$db = $t['database'];
	InsretSomeFastSettingRows($db);

	if(!$db['Log']['Id']->HasRelation('FastSetting','Id')) Ex("No relation found between columns");
	if(!$db['FastSetting']['Id']->HasRelation('Log','Id')) Ex("No relation found between columns");
	
	$newLogRow = $t['database']['Log'](34);
	if(!$newLogRow->Exists()){
		$t['database']->DbManager->Insert("Log", array(
			"Id"=>34,
			"Name"=>"Test",
			"Timestamp"=>"2009-08-21 00:59:11",
		));
	}

	//GetRelatedRows
	$setting = new SmartRow('FastSetting', $db, 35);
	$relatedRows = $setting['Id']->GetRelatedRows('Log','Id');
	if(count($relatedRows) !== 0) Ex("found related rows");
	
	//GetRelatedRows with 'return-next-row' option set
	$setting = new SmartRow('FastSetting', $db, 34);
	$lastRow = null;
	$totalIterations = 0;
	$totalCallbackIterations = 0;
	while( $row = $setting['Id']->GetRelatedRows('Log', 'Id', array(
		'return-next-row'=>&$curCount,
		'return-count'=>&$totalCount,
		'callback'=>function($row,$i) use(&$totalCallbackIterations){ //test inline callbacks here too
			$totalCallbackIterations++;
		}
	))){
		//echo "$curCount - $totalCount - $row<br>";
		$totalIterations++;
		$lastRow = $row;
	}
	if($totalCount !== 1) Ex("Wrong number of rows returned");
	if($totalCallbackIterations !== 1) Ex("Wrong number of callback iterations");
	if($totalCount != $totalIterations) Ex("Wrong number of iterations: $totalCount");

	//more GetRelatedRows
	$setting = new SmartRow('FastSetting', $db, 34);
	$relatedRows = $setting['Id']->GetRelatedRows('Log'); //column "Id" should be assumed
	if(count($relatedRows) === 0) Ex("found no related rows");
	$row = $relatedRows[0];
	AssertExists($row);
	AssertIsNotDirty($row);
	AssertCellVal($row['Timestamp'], '2009-08-21 00:59:11');

	//GetRelatedRow
	$setting = new SmartRow('FastSetting', $db, 35);
	$row = $setting['Id']->GetRelatedRow('Log','Id');
	AssertNotExists($row);

	$setting = new SmartRow('FastSetting', $db, 34);
	$row = $setting['Id']->GetRelatedRow('Log'); //column "Id" should be assumed
	AssertExists($row);
	
	//get related row through a column alias
	$setting = new SmartRow('FastSetting', $db, 34);
	$row = $setting['Id']->GetRelatedRow('Log','LogId'); //column "Id" should be assumed
	AssertExists($row);

	//set cell value to array
	$exceptionHit = false;
	try{
		$row['Name'] = array('wtf');
	} catch (Exception $e){
		$exceptionHit = true;
	}
	if(!$exceptionHit) Ex("Set cell value as array");

	//set cell value to some class
	$exceptionHit = false;
	try{
		$row['Name'] = $db;
	} catch (Exception $e){
		$exceptionHit = true;
	}
	if(!$exceptionHit) Ex("Set cell value as object with no tostring");

	//set cell value to some class with toString defined. should be cool
	$row['Name'] = $row['Level'];

	//print_nice($row);

	//echo $row;
}
/**
 * table inheritance
 * @ignore
 */
function Test12($t, $debug=false){
	$db = $t['database'];
	if( !$db->GetTable('Junk')->ColumnExists('CommonFieldsName') ) Ex("Inherited column CommonFieldsName doesnt exist");
	if( !$db->GetTable('Junk')->ColumnExists('CommonFieldsId') ) Ex("Inherited column CommonFieldsId doesnt exist");
	if( !$db->GetTable('Junk')->ColumnExists('JunkName') ) Ex("Regular column JunkName doesnt exist");

	//verify relations
	if( !$db['Junk']['CommonFieldsId']->HasRelation('Questionare', 'QuestionareId') ) Ex("Inherited relation does not exist");
	if( !$db['Junk']['CommonFieldsId']->HasRelation('JunkExtended', 'CommonFieldsId') ) Ex("Inherited relation does not exist");
	if( !$db['JunkExtended']['CommonFieldsId']->HasRelation('Questionare', 'QuestionareId') ) Ex("Inherited relation does not exist");
	if( !$db['JunkExtended']['CommonFieldsId']->HasRelation('Junk', 'CommonFieldsId') ) Ex("Inherited relation does not exist");
	if( !$db['Questionare']['QuestionareId']->HasRelation('Junk', 'CommonFieldsId') ) Ex("Inherited relation does not exist");
	if( !$db['Questionare']['QuestionareId']->HasRelation('JunkExtended', 'CommonFieldsId') ) Ex("Inherited relation does not exist");

	$junkRow = new SmartRow("Junk", $db);
	$junkRow['CommonFieldsName'] = "My Name is Fred";
	$junkRow['JunkName'] = "On the weekends, I'm known as Susan";
	Commit($junkRow, $debug);

	if($junkRow['CommonFieldsId'] <= 0) Ex('CommonFieldsId did not get set on commit');
	AssertCellVal($junkRow['CommonFieldsName'], "My Name is Fred");
	AssertCellVal($junkRow['JunkName'], "On the weekends, I'm known as Susan");

	Delete($junkRow, $debug);

	$junkRow = new Junk($db);
	$junkRow->DoCommonFields($debug);
	AssertCellVal($junkRow['CommonFieldsName'], "DoCommonFields");
	$junkRow->DoJunk($debug);
	AssertCellVal($junkRow['CommonFieldsName'], "DoJunk");

	$junkExtendedRow = new JunkExtended($db);
	$junkExtendedRow->DoCommonFields($debug);
	AssertCellVal($junkExtendedRow['CommonFieldsName'], "DoCommonFields");
	$junkExtendedRow->DoJunk($debug);
	AssertCellVal($junkExtendedRow['CommonFieldsName'], "DoJunk");
	$junkExtendedRow->DoJunkExtended($debug);
	AssertCellVal($junkExtendedRow['CommonFieldsName'], "DoJunkExtended");
}
/**
 * column aliases (within an inherited table... so it's 2x the test!)
 * @ignore
 */
function Test13($t, $debug=false){
	$db = $t['database'];
	if( !$db->GetTable('Junk')->ColumnExists('JunkName2') ) Ex("Aliased column JunkName2 doesnt exist");
	if( !$db->GetTable('Junk')->ColumnExists('Junk3') ) Ex("Aliased column Junk3 doesnt exist");

	if($db['Junk']['JunkName2']->ColumnName !== "JunkName") Ex("Invalid aliased column");
	if($db['Junk']['Junk3']->ColumnName !== "JunkName") Ex("Invalid aliased column");

	$junkExtendedRow = new SmartRow("JunkExtended", $db);
	$junkExtendedRow['JunkName2'] = "Aliased set 1";
	$junkExtendedRow['ShortName2'] = "SET 2";
	Commit($junkExtendedRow, $debug);

	AssertCellVal($junkExtendedRow['JunkName'], "Aliased set 1");
	AssertCellVal($junkExtendedRow->Column('JunkName2'), "Aliased set 1");
	AssertCellVal($junkExtendedRow->Cell('Junk3'), "Aliased set 1");
	AssertCellVal($junkExtendedRow->Cell('ShortName'), "SET 2");
	AssertCellVal($junkExtendedRow['ShortName2'], "SET 2");

	$junkExtendedRow['Junk3'] = "Aliased set 2";
	Commit($junkExtendedRow, $debug);

	AssertCellVal($junkExtendedRow->Column('JunkName'), "Aliased set 2");
	AssertCellVal($junkExtendedRow['JunkName2'], "Aliased set 2");
	AssertCellVal($junkExtendedRow['Junk3'], "Aliased set 2");
	AssertCellVal($junkExtendedRow['ShortName'], "SET 2");
	AssertCellVal($junkExtendedRow->Cell('ShortName2'), "SET 2");

	$junkExtendedRow['JunkName'] = "Regular JunkName name set";
	$junkExtendedRow['ShortName'] = "Regs";
	Commit($junkExtendedRow, $debug);

	AssertCellVal($junkExtendedRow['Junk3'], "Regular JunkName name set");
	AssertCellVal($junkExtendedRow->Cell('ShortName2'), "Regs");

	Delete($junkExtendedRow, $debug);
}

/**
 * enumerations + sets
 * @ignore
 */
function Test14($t, $debug=false){
	$t['database']['User']->DeleteAllRows();
	
	//create some SET rows for testing
	$row = $t['database']['User']();	$row['UserTypeSet'] = ['A'];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ['B '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ' b';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = 'c/d (e.)';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = [' a ',' B '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = 'a,b';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ['B  ',' C/d (E.)   '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ' c/d (e.) ,B';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = 'c/D (e.), a ';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = [' a ',' b ','c/d (e.)'];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ' a , b ,c/d (e.)';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = [' a ','c/D (e.) '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ['B ','c/d (e.) '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = ['B ','C/D (E.) '];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = [''];	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = '   ';	Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = null;		Commit($row, $debug);
	$row = $t['database']['User']();	$row['UserTypeSet'] = [null];	Commit($row, $debug);
	
	$row = new SmartRow('User', $t['database']);
	Msg($debug,'Before set values:',$row);
	$row['UserType'] = 'a'; //valid enum type
	Msg($debug,'After set UserType:',$row);

	Commit($row, $debug);
	//Delete($row, $debug);

	$row['UserType'] = 'aa'; //invalid enum type

	$exceptionHit = false;
	try{
		Commit($row, $debug);
	} catch (Exception $e){
		$exceptionHit = true;
	}
	$hasErrors = $row['UserType']->HasErrors();
	if(!$hasErrors && !$exceptionHit) Ex("Was able to set enum column to invalid enum value");
	
	$row['UserType'] = 'a'; //valid enum type so we can commit again. spaces auto trimmed
	
	Msg($debug,'Before set values:',$row);
	$row['UserTypeSet'] = [' a ',' b ']; //valid enum type. spaces should get auto-trimmed (as mysql does by default)
	Msg($debug,'After set UserType:',$row);

	Commit($row, $debug);
	//Delete($row, $debug);

	$exceptionHit = false;
	try{
		$row['UserTypeSet'] = 'aa'; //invalid enum type
	} catch (Exception $e){
		$exceptionHit = true;
	}
	$hasErrors = $row['UserType']->HasErrors();
	if(!$hasErrors && !$exceptionHit) Ex("Was able to set enum column to invalid set value");

	//ONLY a AND c
	//	$lookupAssoc = "a,c"
	//	$lookupAssoc = "AND"=>['a','c']
	//		...WHERE set = 'a,c' 
	$row['UserTypeSet'] = 'c/d (e.),b'; //valid type
	Commit($row, $debug);
	if($row['UserTypeSet']() != ['b','c/d (e.)']) Ex('set array doesnt match expected');
	//echo '1: '.json_encode($row).'<br>';
	
	$row['UserTypeSet'] = ['c/d (e.)','a']; //valid type
	Commit($row, $debug);
	if($row['UserTypeSet']() != ['a','c/d (e.)']) Ex('set array doesnt match expected');
	//echo '2: '.json_encode($row).'<br>';

	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => 'c/D (e.) ,a '
	]);
	if(count($rows)!=3 || $rows[2]['UserTypeSet']() != ['a','c/d (e.)']) Ex('set array doesnt match expected');
	//echo '2.5: '.json_encode($rows).'<br>';

	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['c/D (e.) ','a ']
	]);
	if(count($rows)!=3 || $rows[1]['UserTypeSet']() != ['a','c/d (e.)']) Ex('set array doesnt match expected');
	//echo '3: '.json_encode($rows).'<br>';
	
	$row['UserTypeSet'] = ['b','a']; //valid type
	Commit($row, $debug);
	if($row['UserTypeSet']() != ['a','b']) Ex('set array doesnt match expected');
	//echo '3: '.json_encode($row).'<br>';
	
	$row['UserTypeSet'] = ['a']; //valid type
	Commit($row, $debug);
	if($row['UserTypeSet']() != ['a']) Ex('set array doesnt match expected');
	//echo '4: '.json_encode($row).'<br>';
	
	$row2 = new SmartRow('User', $t['database']);
	$row2['UserTypeSet'] = 'C/d (e.) '; //valid type
	Commit($row2, $debug);
	if($row2['UserTypeSet']() != ['c/d (e.)']) Ex('set array doesnt match expected');
	//echo '5: '.json_encode($row2).'<br>';

	//ONLY a OR ONLY c
	//	$lookupAssoc = "OR"=>['a','c']
	//		...WHERE set = 'a' OR set = 'c'
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['OR'=>['c/D (e.) ','a ']]
	]);
	if(count($rows)!=4) Ex('set array doesnt match expected');
	//echo '6: '.json_encode($rows).'<br>';
	
	//ANY WITH a AND c
	//	$lookupAssoc = "LIKE"=>'%a%,%c%'	//BETTER OPTION
	//		...WHERE set LIKE '%a%,%c%'; 	//BETTER OPTION
	//	$lookupAssoc = "LIKE"=>["AND"=>['%a%','%c%']]
	//		...WHERE set LIKE '%a%' and set LIKE '%c%'
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['like'=>'c/D (e.) ,a ']
	]);
	if(count($rows)!=4) Ex('set array doesnt match expected');
	//echo '7: '.json_encode($rows).'<br>';
	
	//invalid lookup assoc  with no column name set (this wa an accident, but it's not a test)
	$exceptionHit = false;
	try{
		$rows = $t['database']['User']->LookupRows([
			"LIKE"=>["OR"=>['a']]
		]);
	} catch (Exception $e){
		$exceptionHit = true;
	}
	if(!$exceptionHit) Ex("Was able to set a lookup assoc with no column name set");
	
	//ANY WITH a OR c
	//	...WHERE set LIKE '%a%' OR set LIKE '%c%';
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["LIKE"=>["OR"=>['a','b']]]
	]);
	//echo '8: '.json_encode($rows).'<br>';
	if(count($rows)!=14) Ex('set array doesnt match expected');
	
	//	...WHERE set LIKE '%a%' OR set LIKE '%c%';
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["LIKE"=>["OR"=>'b,a ']]
	]);
	//echo '8: '.json_encode($rows).'<br>';
	if(count($rows)!=14) Ex('set array doesnt match expected');
	
	//null set 
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => null
	]);
	//echo '9: '.json_encode($rows).'<br>';
	if(count($rows)!=4 || $rows[3]['UserTypeSet']() != []) Ex('set array doesnt match expected');
	
	//null set 
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ''
	]);
	//echo '10: '.json_encode($rows).'<br>';
	if(count($rows)!=4 || $rows[3]['UserTypeSet']()) Ex('set array doesnt match expected');
	
	//null set
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['',null]
	]);
	//echo '11: '.json_encode($rows).'<br>';
	if(count($rows)!=4 || $rows[3]['UserTypeSet']()) Ex('set array doesnt match expected');
	
	//delete null
	//$deleted = $t['database']['User']->DeleteRows([
	//	'UserTypeSet' => '' //or null
	//]);
	//if($deleted!=4)Ex('set deleted wrong number of rows: '.(int)$deleted);
	
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['!=' => 'a']
	]);
	if(count($rows)!=18) Ex('wrong rowcount returned for lookup');

	//UserTypeSet is not null / empty set
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['!=' => []]
	]);
	if(count($rows)!=16) Ex('wrong rowcount returned for lookup');
	
	$exceptionHit = false;
	try{
		$rows = $t['database']['User']->LookupRows([
			'UserTypeSet' => ['!=' => ['a',null]]
		]);
	} catch (Exception $e){
		$exceptionHit = true;
	}
	if(!$exceptionHit) Ex("Was able to use a set with a null value");

	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['not like' => 'a']
	]);
	if(count($rows)!=12) Ex('wrong rowcount returned for lookup');

	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ['not like' => ['a']]
	]);
	if(count($rows)!=12) Ex('wrong rowcount returned for lookup');

	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["AND"=>['!=' => ['a','b']]]
	]);
	if(count($rows)!=16) Ex('set array doesnt match expected');
	
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["OR"=>['!=' => ['a','b']]]
	]);
	if(count($rows)!=18) Ex('set array doesnt match expected');
	
	$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["AND"=>['NOT LIKE' => ['a','b']]]
	]);
	if(count($rows)!=6) Ex('set array doesnt match expected');
	
		$rows = $t['database']['User']->LookupRows([
		'UserTypeSet' => ["OR"=>['NOT LIKE' => ['a','b']]]
	]);
	if(count($rows)!=16) Ex('set array doesnt match expected');
	

	//required sets	
	$t['database']['User']['UserTypeSet']->IsRequired = true;
	$row = $t['database']['User']();
	$row['UserTypeSet'] = [''];
	$exceptionHit = false;
	try{
		Commit($row, $debug);
	} catch (Exception $e){
		$exceptionHit = true;
	}
	$hasErrors = $row['UserTypeSet']->HasErrors();
	if(!$hasErrors || !$exceptionHit) Ex("Was able to set SET column to null value when required");
	
	$t['database']['User']['UserTypeSet']->IsRequired = true;
	$row = $t['database']['User']();
	$row['UserTypeSet'] = '';
	$exceptionHit = false;
	try{
		Commit($row, $debug);
	} catch (Exception $e){
		$exceptionHit = true;
	}
	$hasErrors = $row['UserTypeSet']->HasErrors();
	if(!$hasErrors || !$exceptionHit) Ex("Was able to set SET column to null value when required");
	

}

/**
 * 0/null
 * @ignore
 */
function Test15($t, $debug=false){
	$row = new SmartRow('Log', $t['database']);

	$row['NumViews'] = null;
	if(($var=$row['NumViews']()) !== null) Ex("null invalid set: ".var_dump($var));

	$row['NumViews'] = 0;
	if(($var=$row['NumViews']()) !== 0) Ex("0 (zero) invalid set: ".var_dump($var));
	
	$row['NumViews'] = "0";
	if(($var=$row['NumViews']()) !== 0) Ex("'0' (zero string) invalid set: ".var_dump($var));
	
	$row['NumViews'] = null;
	if(($var=$row['NumViews']()) !== null) Ex("null invalid set: ".var_dump($var));
}

/**
 * min, max, sum, avg, etc
 * @ignore
 */
function Test16($t, $debug=false){
	//lookup max regular
	$val = $t['database']['FastSetting']['Id']->GetMaxValue();
	if($val != 35) Ex("Invalid max value: '$val' != 35");
	
	//lookup max with WHERE clause
	$val = $t['database']['FastSetting']['Id']->GetMaxValue(array(
		'Id'=>array("!="=>35)
	));
	if($val != 34) Ex("Invalid max value: '$val' != 34");
	
	//empty table
	$val = $t['database']['Junk']['CommonFieldsId']->GetMaxValue();
	if($val !== null) Ex("Invalid max value: '$val' !== null");
	
	//lookup min regular
	$val = $t['database']['FastSetting']['Id']->GetMinValue();
	if($val != 34) Ex("Invalid min value: '$val' != 34");
	
	//lookup min with WHERE clause
	$val = $t['database']['FastSetting']['Id']->GetMinValue(array(
		'Id'=>array(">"=>34)
	));
	if($val != 35) Ex("Invalid min value: '$val' != 35");
	
	//lookup average regular
	$val = $t['database']['FastSetting']['Id']->GetAggregateValue('avg');
	if($val != 34.5) Ex("Invalid avg value: '$val' != 34.5");
	
	//lookup sum regular
	$val = $t['database']['FastSetting']['Id']->GetAggregateValue('sum');
	if($val != 69) Ex("Invalid sum value: '$val' != 69");
	
	//lookup count distinct regular
	$val = $t['database']['FastSetting']['Id']->GetAggregateValue('count-distinct');
	if($val != 2) Ex("Invalid count-distinct: '$val' != 2");
	
	//lookup count distinct with WHERE clause
	$val = $t['database']['FastSetting']['Id']->GetAggregateValue('count-distinct', array(
		'Id'=>array(">"=>34)
	));
	if($val != 1) Ex("Invalid count-distinct: '$val' != 1");
	
	//lookup count distinct with WHERE clause
	$val = $t['database']['FastSetting']['Id']->GetAggregateValue('count-distinct', array(
		'Id'=>array(">"=>35)
	));
	if($val != 0) Ex("Invalid count-distinct: '$val' != 0");
}

//types
function Test17($t, $debug=false){
	$db = $t['database'];
	
	//---- CHECK EXACT DATA TYPES AFTER COMMIT ----
		$testClass = new TestClass();
		
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 'char';
		$row['varchar'] = 'varchar';
		$row['text'] = 'text';
		$row['mediumtext'] = 'mediumtext';
		$row['longtext'] = 'longtext';
		$row['blob'] = 'blob';
		$row['mediumblob'] = 'mediumblob';
		$row['longblob'] = 'longblob';
		$row['tinyint'] = 2;
		$row['smallint'] = 8;
		$row['mediumint'] = 99;
		$row['int'] = 399;
		$row['bigint'] = 6999;
		$row['float'] = 4.2024;
		$row['double'] = 56.78;
		$row['decimal'] = 100.001;
		$row['date'] = '2020-12-25';
		$row['datetime'] = '1999-12-31 23:59:59';
		$row['timestamp'] = '2000-01-01 00:00:00';
		$row['time'] = '16:20:01';
		$row['binary'] = '1';
		$row['binary8'] = 'abcd1234'; //gets padded with null characters (\0) to the length of the column
		$row['enum'] = 'Option. 2';
		$row['array'] = array();
		$row['object'] = null;
		$row['array-notnull'] = array(1,2,3);
		$row['object-notnull'] = $testClass;
		$row['bool'] = 0;
		$row['boolreq'] = 1;
	
		AssertIsDirty($row);
		Commit($row,$debug);
		
		$rowId = $row['Id']();
		
		$row = $db['AllDataTypes']($rowId);
		AssertCellVal($row['char'], 'char');
		AssertCellVal($row['varchar'], 'varchar');
		AssertCellVal($row['text'], 'text');
		AssertCellVal($row['mediumtext'], 'mediumtext');
		AssertCellVal($row['longtext'], 'longtext');
		AssertCellVal($row['blob'], 'blob');
		AssertCellVal($row['mediumblob'], 'mediumblob');
		AssertCellVal($row['longblob'], 'longblob');
		AssertCellVal($row['tinyint'], 2);
		AssertCellVal($row['smallint'], 8);
		AssertCellVal($row['mediumint'], 99);
		AssertCellVal($row['int'], 399);
		AssertCellVal($row['bigint'], 6999);
		AssertCellVal($row['float'], 4.2024);
		AssertCellVal($row['double'], 56.78);
		AssertCellVal($row['decimal'], 100.001);
		AssertCellVal($row['date'], '2020-12-25');
		AssertCellVal($row['datetime'], '1999-12-31 23:59:59');
		AssertCellVal($row['timestamp'], '2000-01-01 00:00:00');
		AssertCellVal($row['time'], '16:20:01');
		AssertCellVal($row['binary'], '1');
		AssertCellVal($row['binary8'], 'abcd1234');
		AssertCellVal($row['enum'], 'Option. 2');
		AssertCellVal($row['array'], array());
		AssertCellVal($row['object'], null);
		AssertCellVal($row['array-notnull'], array(1,2,3));
		AssertCellVal($row['object-notnull'], $testClass);
		AssertCellVal($row['bool'], 0);
		AssertCellVal($row['boolreq'], 1);
	
		Delete($row,$debug);
	
	//---- CHECK MIXED DATA TYPES AFTER COMMIT ----
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 1;
		$row['varchar'] = 2;
		$row['text'] = 03.00;
		$row['mediumtext'] =  4;
		$row['longtext'] = 5;
		$row['blob'] = 6;
		$row['mediumblob'] = 007;
		$row['longblob'] = 8.0;
		$row['tinyint'] = '02';
		$row['smallint'] = '08';
		$row['mediumint'] = '099.0000';
		$row['int'] = '$ 	00399.0$'; //leading whitespace and dollar signs. should be valid
		$row['bigint'] = '00006999';
		$row['float'] = '	 $04.20240'; //leading whitespace and dollar signs. should be valid
		$row['double'] = '056.780';
		$row['decimal'] = '0100.0010';
		$row['binary'] = true;
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		$row['bool'] = false;
		$row['boolreq'] = true;
	
		AssertIsDirty($row);
		Commit($row,$debug);
		
		$rowId = $row['Id']();
		
		$row = $db['AllDataTypes']($rowId);
		AssertCellVal($row['char'], '1');
		AssertCellVal($row['varchar'], '2');
		AssertCellVal($row['text'], '3');
		AssertCellVal($row['mediumtext'], '4');
		AssertCellVal($row['longtext'], '5');
		AssertCellVal($row['blob'], '6');
		AssertCellVal($row['mediumblob'], '7');
		AssertCellVal($row['longblob'], '8');
		AssertCellVal($row['tinyint'], 2);
		AssertCellVal($row['smallint'], 8);
		AssertCellVal($row['mediumint'], 99);
		AssertCellVal($row['int'], 399);
		AssertCellVal($row['bigint'], 6999);
		AssertCellVal($row['float'], 4.2024);
		AssertCellVal($row['double'], 56.78);
		AssertCellVal($row['decimal'], 100.001);
		AssertCellVal($row['binary'], '1');
		AssertCellVal($row['array-notnull'], array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4"));
		AssertCellVal($row['object-notnull'], $testClass);
		AssertCellVal($row['bool'], 0);
		AssertCellVal($row['boolreq'], 1);
		
		Delete($row,$debug);
		
	//---- CHECK EXACT DATA TYPES BEFORE COMMIT ----
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 'char';
		$row['varchar'] = 'varchar';
		$row['text'] = 'text';
		$row['mediumtext'] = 'mediumtext';
		$row['longtext'] = 'longtext';
		$row['blob'] = 'blob';
		$row['mediumblob'] = 'mediumblob';
		$row['longblob'] = 'longblob';
		$row['tinyint'] = 2;
		$row['smallint'] = 8;
		$row['mediumint'] = 99;
		$row['int'] = 399;
		$row['bigint'] = 6999;
		$row['float'] = 4.2024;
		$row['double'] = 56.78;
		$row['decimal'] = 100.001;
		$row['date'] = "2020-12-25";
		$row['datetime'] = '1999-12-31 23:59:59';
		$row['timestamp'] = '2000-01-01 00:00:00';
		$row['time'] = '16:20:01';
		$row['binary'] = '1';
		$row['binary8'] = 'abcd1234'; //gets padded with null characters (\0) to the length of the column
		$row['enum'] = 'Option. 2';
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		$row['bool'] = '0';
		$row['boolreq'] = '1';
	
		AssertIsDirty($row);

		AssertCellVal($row['char'], 'char');
		AssertCellVal($row['varchar'], 'varchar');
		AssertCellVal($row['text'], 'text');
		AssertCellVal($row['mediumtext'], 'mediumtext');
		AssertCellVal($row['longtext'], 'longtext');
		AssertCellVal($row['blob'], 'blob');
		AssertCellVal($row['mediumblob'], 'mediumblob');
		AssertCellVal($row['longblob'], 'longblob');
		AssertCellVal($row['tinyint'], 2);
		AssertCellVal($row['smallint'], 8);
		AssertCellVal($row['mediumint'], 99);
		AssertCellVal($row['int'], 399);
		AssertCellVal($row['bigint'], 6999);
		AssertCellVal($row['float'], 4.2024);
		AssertCellVal($row['double'], 56.78);
		AssertCellVal($row['decimal'], 100.001);
		AssertCellVal($row['date'], '2020-12-25');
		AssertCellVal($row['datetime'], '1999-12-31 23:59:59');
		AssertCellVal($row['timestamp'], '2000-01-01 00:00:00');
		AssertCellVal($row['time'], '16:20:01');
		AssertCellVal($row['binary'], '1');
		AssertCellVal($row['binary8'], 'abcd1234');
		AssertCellVal($row['enum'], 'Option. 2');
		AssertCellVal($row['bool'], 0);
		AssertCellVal($row['boolreq'], 1);
	
	//---- CHECK MIXED DATA TYPES BEFORE COMMIT ----
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 1;
		$row['varchar'] = 2;
		$row['text'] = 03.00;
		$row['mediumtext'] =  4;
		$row['longtext'] = 5;
		$row['blob'] = 6;
		$row['mediumblob'] = 007;
		$row['longblob'] = 8.0;
		$row['tinyint'] = '02';
		$row['smallint'] = '08';
		$row['mediumint'] = '099.0000';
		$row['int'] = '00399.0';
		$row['bigint'] = '00006999';
		$row['float'] = '04.20240';
		$row['double'] = '056.780';
		$row['decimal'] = '0100.0010';
		$row['binary'] = true;
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		$row['bool'] = false;
		$row['boolreq'] = true;
		
		AssertIsDirty($row);
		
		AssertCellVal($row['char'], '1');
		AssertCellVal($row['varchar'], '2');
		AssertCellVal($row['text'], '3');
		AssertCellVal($row['mediumtext'], '4');
		AssertCellVal($row['longtext'], '5');
		AssertCellVal($row['blob'], '6');
		AssertCellVal($row['mediumblob'], '7');
		AssertCellVal($row['longblob'], '8');
		AssertCellVal($row['tinyint'], 2);
		AssertCellVal($row['smallint'], 8);
		AssertCellVal($row['mediumint'], 99);
		AssertCellVal($row['int'], 399);
		AssertCellVal($row['bigint'], 6999);
		AssertCellVal($row['float'], 4.2024);
		AssertCellVal($row['double'], 56.78);
		AssertCellVal($row['decimal'], 100.001);
		AssertCellVal($row['binary'], '1');
		AssertCellVal($row['bool'], 0);
		AssertCellVal($row['boolreq'], 1);
		
	//---- null,0,"",false,etc input required checks
		//--test 1
		//insert1
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 0; //"0" - ok
		$row['varchar'] = 0;
		$row['int'] = 0; //ok
		$row['bigint'] = 0;
		$row['binary'] = 0;
		$row['binaryreq'] = 0; //error
		$row['bool'] = 0;
		$row['boolreq'] = 0; //error
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=2) Ex('wrong number of cells in error');
		if(!$row['binaryreq']->HasErrors()) Ex('binaryreq field should be required!');
		if(!$row['boolreq']->HasErrors()) Ex('boolreq field should be required!');

		//--test 2
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = "0"; //ok
		$row['varchar'] = "0";
		$row['int'] = "0"; //0 - ok
		$row['bigint'] = "0";
		$row['binary'] = "0";
		$row['binaryreq'] = "0"; //error
		$row['bool'] = "0";
		$row['boolreq'] = "0"; //error
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=2) Ex('wrong number of cells in error');
		if(!$row['binaryreq']->HasErrors()) Ex('binaryreq field should be required!');
		if(!$row['boolreq']->HasErrors()) Ex('boolreq field should be required!');
		
		//--test 3
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = false; //"" - error
		$row['varchar'] = false;
		$row['int'] = false; //0 - ok
		$row['bigint'] = false;
		$row['binary'] = false;
		$row['binaryreq'] = false; //error
		$row['bool'] = false;
		$row['boolreq'] = false; //error
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=3) Ex('wrong number of cells in error');
		if($row['int']->HasErrors()) Ex('int field should not have errors!');
		if(!$row['binaryreq']->HasErrors()) Ex('binaryreq field should be required!');
		if(!$row['boolreq']->HasErrors()) Ex('boolreq field should be required!');
		
		//test 4
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = null; //error
		$row['varchar'] = null;
		$row['int'] = null; //error
		$row['bigint'] = null;
		$row['binary'] = null;
		$row['binaryreq'] = null; //error
		$row['bool'] = null;
		$row['boolreq'] = null; //error
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=4) Ex('wrong number of cells in error');
		if(!$row['boolreq']->HasErrors()) Ex('boolreq field should be required!');
		
		//test 5
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = ""; //error
		$row['varchar'] = "";
		$row['int'] = ""; //null - error
		$row['bigint'] = "";
		$row['binary'] = "";
		$row['binaryreq'] = ""; //error
		$row['bool'] = "";
		$row['boolreq'] = ""; //error
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=4) Ex('wrong number of cells in error');
		
		//test 6
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = true; //"1" - ok
		$row['varchar'] = true;
		$row['int'] = true; //1 - ok
		$row['bigint'] = true;
		$row['binary'] = true;
		$row['binaryreq'] = true; //1 - ok
		$row['bool'] = true;
		$row['boolreq'] = true; //1 - ok
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=0) Ex('wrong number of cells in error');
		
		//test 7
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 1; //"1" - ok
		$row['varchar'] = 1;
		$row['int'] = "1"; //1 - ok
		$row['bigint'] = "1";
		$row['binary'] = "1";
		$row['binaryreq'] = "1"; //1 - ok
		$row['bool'] = "1";
		$row['boolreq'] = "1"; //1 - ok
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		if(count($row->GetColumnsInError())!=0) Ex('wrong number of cells in error');
	
	//--- TEST LOOKUP ASSOC WITH TYPES
		$row = $db['AllDataTypes']->GetNewRow();
		
		$row['char'] = 1;
		$row['varchar'] = 2;
		$row['text'] = 03.00;
		$row['mediumtext'] =  4;
		$row['longtext'] = 5;
		$row['blob'] = 6;
		$row['mediumblob'] = 007;
		$row['longblob'] = 8.0;
		$row['tinyint'] = '02';
		$row['smallint'] = '08';
		$row['mediumint'] = '099.0000';
		$row['int'] = 0933.3;
		$row['bigint'] = null;
		$row['float'] = '04.20240';
		$row['double'] = '056.780';
		$row['decimal'] = '0100.0010';
		$row['binary'] = true;
		$row['boolreq'] = true;
		$row['date'] = '2020-12-25';
		$row['datetime'] = '1999-12-31 23:59:59';
		$row['timestamp'] = strtotime('2000-01-01 00:00:00'); //using an integer timestamp directly
		$row['time'] = '16:20:01';
		$row['binary8'] = null;
		$row['enum'] = 'Option. 2';
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		AssertIsDirty($row);
		Commit($row,$debug);
		
		$row = $db['AllDataTypes']->LookupRow(array(
			'char' => 1,
			'varchar' => 2,
			'text' => 3.00,
			'mediumtext' => array("!=" => null),
			'mediumblob' => 007.0,
			'longblob' => 8,
			'tinyint' => '02',
			'smallint' => '08',
			'mediumint' => '099.000',
			'int' => 933,
			'bigint' => array("!=" => '00006999'),
			'float' => array("!=" => 0),
			'double' => 56.780,
			'decimal' => '0100.0010',
			'binary' => true,
			'boolreq' => true,
			'date' => '2020-12-25',
			'datetime' => '1999-12-31 23:59:59',
			'timestamp' => '2000-01-01 00:00:00',
			'time' => '16:20:01',
			'binary8' => null,
			'enum' => 'Option. 2'
		));
		
		AssertExists($row);
		Delete($row,$debug);
		
		//lookup with null,0,false,etc
		//insert
		$row = $db['AllDataTypes']->GetNewRow();
		$row['char'] = 0;
		$row['varchar'] = false;
		$row['text'] = "0";
		$row['mediumtext'] = null;
		$row['mediumblob'] = true;
		$row['longblob'] = 1;
		$row['tinyint'] = 0;
		$row['smallint'] = "1";
		$row['mediumint'] = "5abc";
		$row['int'] = true;
		$row['bigint'] = false;
		$row['float'] = "0";
		$row['double'] = null;
		$row['decimal'] = true;
		$row['binary'] = 1;
		$row['binaryreq'] = true; 
		$row['bool'] = 1;
		$row['boolreq'] = true;
		$row['array-notnull'] = array('1'=>11,'a2'=>'a"33','b'=>"b'z\\4");
		$row['object-notnull'] = $testClass;
		Commit($row,$debug);
 
		//lookup1 - same data as insert
		$row = $db['AllDataTypes']->LookupRow(array(
			'char' => 0,
			'varchar' => false,
			'text' => "0",
			'mediumtext' => null,
			'mediumblob' => true,
			'longblob' => 1,
			'tinyint' => 0,
			'smallint' => "1",
			'mediumint' => "5abc",
			'int' => true,
			'bigint' => false,
			'float' => "0",
			'double' => null,
			'decimal' => true,
			'binary' => 1,
			'binaryreq' => true,
			'bool' => 1,
			'boolreq' => true
		));
		AssertExists($row);
		
		//lookup2 - mixing data types
		$row = $db['AllDataTypes']->LookupRow(array(
			'char' => "0",
			'varchar' => "",
			'text' => 0,
			'mediumtext' => array("!=" => 0),
			'mediumblob' => 1,
			'longblob' => true,
			'tinyint' => false,
			'smallint' => 1,
			'mediumint' => 5,
			'int' => "1ab",
			'bigint' => 0,
			'float' => false,
			'double' => null,
			'decimal' => "1",
			'binary' => true,
			'binaryreq' => "1",
			'bool' => true,
			'boolreq' => "1"
		));
		AssertExists($row);
		
		Delete($row,$debug);
}

//serialization
function Test18($t, $debug=false){
	$db = $t['database'];

	$sDb = serialize($db);
	$uDb = unserialize($sDb);

	$origTables = $db->GetAllTables();
	$uTables = $uDb->GetAllTables();
	
	if(count($origTables) != count($uTables)){
		Ex('Invalid table count between serialized and unserialized smartdbs');
	}
	
	$origColumns = $db['Log']->GetAllColumns();
	$uColumns = $uDb['Log']->GetAllColumns();
	
	if(count($origColumns) != count($uColumns)){
		Ex('Invalid column count between serialized and unserialized smartdbs');
	}
}

//smart cells accessible via ArrayAccess, Countable, IteratorAggregate (if the cell's datatype is 'array')
function Test19($t, $debug=false){
	$db = $t['database'];
	$row = $db['AllDataTypes']();

	if( !is_array( $row['array-notnull']() ) ){
		Ex('Column is not array type, as expected');
	}
	
	//get array value
	$arrayVal = $row['array-notnull'][0];
	if($arrayVal !== null){
		Ex('Invalid empty array smartcell value');
	}
	AssertIsNotDirty($row);
	
	//check array count
	$count = count($row['array-notnull']);
	if($count !== 0){
		Ex('Invalid array count from smartcell');
	}
	
	//isset
	if( isset($row['array-notnull'][0]) ){
		Ex('array value from smartcell should not be set');
	}
	
	//foreach to test IteratorAggregate
	$exceptionCaught = false;
	try{
		foreach($row['int'] as $key=>$val){
		}
	}
	catch(Exception $e){
		$exceptionCaught = true;
	}
	if(!$exceptionCaught) Ex('IteratorAggregate exception should have been thrown for non-array column.');
	
	//foreach to test IteratorAggregate
	foreach($row['array-notnull'] as $key=>$val){
		Ex('IteratorAggregate should not have any values.');
	}
	
	//add some data to array
	$row['array-notnull'][0] = 'smartcell array set';
	$row['array-notnull'][2] = 'smartcell array set2';
	
	//isset
	if( !isset($row['array-notnull'][0]) ){
		Ex('array value from smartcell should be set');
	}
	
	
	//check array count
	$count = count($row['array-notnull']);
	if($count !== 2){
		Ex('Invalid array count from smartcell');
	}
	
	//check array data
	if($row['array-notnull'][0] !== 'smartcell array set'){
		Ex('Invalid smartcell array value');
	}
	if($row['array-notnull'][2] !== 'smartcell array set2'){
		Ex('Invalid smartcell array value');
	}
	
	if( !is_array( $row['array-notnull']() ) ){
		Ex('Column is not array type, as expected');
	}
	
	//foreach to test IteratorAggregate
	$count = 0;
	foreach($row['array-notnull'] as $key=>$val){
		$count++;
		if($key==0 && $val='smartcell array set') continue;
		else if($key==2 && $val='smartcell array set2') continue;
		else throw new Ex('IteratorAggregate failed.');
	}
	if($count!=2) Ex("IteratorAggregate didn't count all array values.");
}

//JsonSerializable
function Test20($t, $debug=false){
	$db = $t['database'];
	
	$row = $db['AllDataTypes']->GetNewRow();
	$row['char'] = 'char';
	$row['varchar'] = 'varchar';
	$row['text'] = 'text';
	$row['mediumtext'] = 'mediumtext';
	$row['longtext'] = 'longtext';
	$row['blob'] = 'blob';
	$row['mediumblob'] = 'mediumblob';
	$row['longblob'] = 'longblob';
	$row['tinyint'] = 2;
	$row['smallint'] = 8;
	$row['mediumint'] = 99;
	$row['int'] = 399;
	$row['bigint'] = 6999;
	$row['float'] = 4.2024;
	$row['double'] = 56.78;
	$row['decimal'] = 100.001;
	$row['date'] = '2020-12-25';
	$row['datetime'] = '1999-12-31 23:59:59';
	$row['timestamp'] = '2000-01-01 00:00:00';
	$row['time'] = '16:20:01';
	$row['binary'] = '1';
	$row['bool'] = '1';
	$row['binary8'] = 'abcd1234'; //gets padded with null characters (\0) to the length of the column
	$row['enum'] = 'Option. 2';
	$row['array'] = array();
	$row['object'] = null;
	$row['array-notnull'] = array(1,2,3);
	$row['object-notnull'] = new TestClass();;
	
	$json = json_encode($row);
	$decodedArr = json_decode($json, true);
	if($decodedArr['AllDataTypes']['varchar'] != 'varchar' || $decodedArr['AllDataTypes']['decimal'] != 100.001){
		Ex('JsonSerializable failed. Bad values decoded.');
	}
}


//lookup-row inline callbacks
function Test21($t, $debug=false){
	$db = $t['database'];

	//-------- table level callback -----------------------
	//0-row table level LookupRow callback
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']->LookupRows(['Id'=>999111], [
		'callback' => function($row, $i) use(&$callbackRowsReturned){
			$callbackRowsReturned++;
			Ex('Inline callback should not be called because 0 rows should be found.');
		}
	]);
	
	if($callbackRowsReturned!=0){
		Ex('Inline callback called.');
	}
	if($finalRow){
		Ex('Inline callback returned something when none should have been found.');
	}
	
	//1-row table level LookupRow callback
	$row = $db['Setting']->LookupRow(['Id'=>8811857]);
	$row['Name'] = 'callback test val';
	$row->Commit();
	
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']->LookupRows(['Id'=>8811857], [
		'callback' => function($row, $i) use(&$callbackRowsReturned, $debug){
			$callbackRowsReturned++;
			if($i != $callbackRowsReturned){
				Ex('Inline callback received wrong row number.');
			} 
			Delete($row,$debug);
		}
	]);
	
	if($callbackRowsReturned!=1){
		Ex('Inline callback was not called (or called more than once).');
	}
	if(!$finalRow){
		Ex('Last row not returned by callback.');
	}
	if($finalRow->Exists()){
		Ex('Last row was deleted, but reference still exists');
	}
	
	//2-rows table level LookupRow callback
	$row1 = $db['Setting']->LookupRow(['Id'=>777111]);
	$row2 = $db['Setting']->LookupRow(['Id'=>777112]);
	$row1['Name'] = '777111';
	$row2['Name'] = '777112';
	$row1->Commit();
	$row2->Commit();
	
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']->LookupRows(['Id'=>['OR'=>[777111,777112]]], [
		'callback' => function($row, $i) use(&$callbackRowsReturned, $debug){
			$callbackRowsReturned++;
			if($i != $callbackRowsReturned){
				Ex('Inline callback received wrong row number.');
			}
			Delete($row,$debug);
		}
	]);
	
	if($callbackRowsReturned!=2){
		Ex('Inline callback was not called 2 times, once for each row.');
	}
	if(!$finalRow){
		Ex('Last row not returned by callback.');
	}
	if($finalRow->Exists()){
		if($finalRow['Name'] != '777112'){
			Ex('Last row Name was not 777112 as it should be');
		}
		Ex('Last row was deleted, but reference still exists');
	}
	
	//2-rows table level GetAllRows callback
	$row1 = $db['Setting']->DeleteAllRows();
	$row1 = $db['Setting']->LookupRow(['Id'=>666111]);
	$row2 = $db['Setting']->LookupRow(['Id'=>666112]);
	$row1['Name'] = '666111';
	$row2['Name'] = '666112';
	$row1->Commit();
	$row2->Commit();
	
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']->GetAllRows([
		'callback' => function($row, $i) use(&$callbackRowsReturned, $debug){
			$callbackRowsReturned++;
			if($i != $callbackRowsReturned){
				Ex('Inline callback received wrong row number.');
			}
			Delete($row,$debug);
		}
	]);
	
	if($callbackRowsReturned!=2){
		Ex('Inline callback was not called 2 times, once for each row.');
	}
	if(!$finalRow){
		Ex('Last row not returned by callback.');
	}
	if($finalRow->Exists()){
		if($finalRow['Name'] != '777112'){
			Ex('Last row Name was not 777112 as it should be');
		}
		Ex('Last row was deleted, but reference still exists');
	}
	//-------- end table level callback -----------------------
	
	
	//-------- table level callback -----------------------
	//0-column table level LookupRow callback
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']['Id']->LookupRows(999111, [
		'callback' => function($row, $i) use(&$callbackRowsReturned){
			$callbackRowsReturned++;
			Ex('Inline callback should not be called because 0 rows should be found.');
		}
	]);
	
	if($callbackRowsReturned!=0){
		Ex('Inline callback called.');
	}
	if($finalRow){
		Ex('Inline callback returned something when none should have been found.');
	}
	
	//1-row table level LookupRow callback
	$row = $db['Setting']->LookupRow(['Id'=>8811857]);
	$row['Name'] = 'callback test val';
	$row->Commit();
	
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']['Id']->LookupRows(8811857, [
			'callback' => function($row, $i) use(&$callbackRowsReturned, $debug){
				$callbackRowsReturned++;
				if($i != $callbackRowsReturned){
					Ex('Inline callback received wrong row number.');
				}
				Delete($row,$debug);
			}
	]);
	
	if($callbackRowsReturned!=1){
		Ex('Inline callback was not called (or called more than once).');
	}
	if(!$finalRow){
		Ex('Last row not returned by callback.');
	}
	if($finalRow->Exists()){
		Ex('Last row was deleted, but reference still exists');
	}
	
	//2-rows table level LookupRow callback
	$row1 = $db['Setting']->LookupRow(['Id'=>777111]);
	$row2 = $db['Setting']->LookupRow(['Id'=>777112]);
	$row1['Name'] = 'TEST-NAME';
	$row2['Name'] = 'TEST-NAME';
	$row1->Commit();
	$row2->Commit();
	
	$callbackRowsReturned = 0;
	$finalRow = $db['Setting']['Name']->LookupRows('TEST-NAME', [
		'callback' => function($row, $i) use(&$callbackRowsReturned, $debug){
			$callbackRowsReturned++;
			if($i != $callbackRowsReturned){
				Ex('Inline callback received wrong row number.');
			}
			Delete($row,$debug);
		}
	]);
	
	if($callbackRowsReturned!=2){
		Ex('Inline callback was not called 2 times, once for each row.');
	}
	if(!$finalRow){
		Ex('Last row not returned by callback.');
	}
	if($finalRow->Exists()){
		if($finalRow['Name'] != '777112'){
			Ex('Last row Name was not 777112 as it should be');
		}
		Ex('Last row was deleted, but reference still exists');
	}

}

//fun with dates and timestamps
function Test22($t, $debug=false){
	$db = $t['database'];
	
	//just some cleanup
	$db['TimeLog']->DeleteAllRows();
	$savedTimestamps = [];
	$savedDatetimes = [];

	$singleDate = '2012-12-12';
	$singleTime = '12:12:12';
	
	//INDY TIME
	date_default_timezone_set('America/Indiana/Indianapolis');
	
		//----- test1 - with no timezones in use anywhere, dates should be completely untouched. date() used everywhere
		//test with no timezone set. server time assumed
		$db->DefaultTimezone = '';
	
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s"); //no timezone
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $date);
		AssertCellVal($row['Datetime'], $date);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);
		
		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		//----- test1.5  - using time(). should be completely untouched
		$row = $db['TimeLog']();
		
		$time = time();
		$row['Timestamp'] = $time;
		$row['Datetime'] = $time;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		if($row['Timestamp'](true) != $time) Ex("Timestamp '".$row['Timestamp'](true)."' does not match submitted time '".$time."'");
		if($row['Datetime'](true) != $time) Ex("Timestamp '".$row['Timestamp'](true)."' does not match submitted time '".$time."'");
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);
		
		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$time, 'Datetime'=>$time, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$time, 'Datetime'=>$time, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		
		//----- test2 - with default timezone
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s T"); //with timezone
		$dateWithoutTz = str_replace(' '.date('T'), '', $date);
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $dateWithoutTz);
		AssertCellVal($row['Datetime'], $dateWithoutTz);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		//$row->Delete();

	
	//CHI TIME
	date_default_timezone_set('America/Chicago');
	//no timezones in use anywhere
		//----- test1 -  dates should be completely untouched. date() used everywhere
		//test with no timezone set. server time assumed
		$db->DefaultTimezone = '';
	
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s"); //no timezone
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $date);
		AssertCellVal($row['Datetime'], $date);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);
		
		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		//$row->Delete();
		
		//----- test1.5  - using time(). should be completely untouched
		$row = $db['TimeLog']();
		
		$time = time();
		$row['Timestamp'] = $time;
		$row['Datetime'] = $time;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		if($row['Timestamp'](true) != $time) Ex("Timestamp '".$row['Timestamp'](true)."' does not match submitted time '".$time."'");
		if($row['Datetime'](true) != $time) Ex("Timestamp '".$row['Timestamp'](true)."' does not match submitted time '".$time."'");
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$time, 'Datetime'=>$time, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$time, 'Datetime'=>$time, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		
		//----- test2 - with default timezone
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s T"); //with timezone
		$dateWithoutTz = str_replace(' '.date('T'), '', $date); //will this fail wien it's CDT and not CST?
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $dateWithoutTz);
		AssertCellVal($row['Datetime'], $dateWithoutTz);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		

	
	//INDY TIME
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
		//----- test3 - with no timezones in use anywhere, dates should be completely untouched. date() used everywhere
		//test with no timezone set. server time assumed
	
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s T");
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $date);
		AssertCellVal($row['Datetime'], $date);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);
		
		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s");
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $date.' '.date('T'));
		AssertCellVal($row['Datetime'], $date.' '.date('T'));
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
	
	
	//INDY TIME
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';	
		//----- test3 - with no timezones in use anywhere, dates should be completely untouched. date() used everywhere
		//test with no timezone set. server time assumed
	
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s T");
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $date);
		AssertCellVal($row['Datetime'], $date);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		
		$row = $db['TimeLog']();
		date_default_timezone_set('America/Chicago');
		$tz = date('T');
		date_default_timezone_set('America/Indiana/Indianapolis');
		$cstdate = date("Y-m-d H:i:s").' '.$tz;
		
		//commit a row
		$row['Timestamp'] = $cstdate;
		$row['Datetime'] = $cstdate;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$db->DefaultTimezone = 'America/Chicago';
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $cstdate);
		AssertCellVal($row['Datetime'], $cstdate);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$cstdate, 'Datetime'=>$cstdate, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$cstdate, 'Datetime'=>$cstdate, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
	
	//INDY TIME
	$db->DefaultTimezone = 'UTC';	
		//----- test3 - with no timezones in use anywhere, dates should be completely untouched. date() used everywhere
		//test with no timezone set. server time assumed
	
		$row = $db['TimeLog']();
		$date = date("Y-m-d H:i:s T");
		$gmdate = gmdate("Y-m-d H:i:s ").'UTC'; //for comparison of current timezone
		
		//commit a row
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $gmdate);
		AssertCellVal($row['Datetime'], $gmdate);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row[0]);
		
		$row = $db['TimeLog']->LookupRows(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row[0]);
		
		
		
		$row = $db['TimeLog']();		
		date_default_timezone_set('America/Chicago');
		$tz = date('T');
		date_default_timezone_set('America/Indiana/Indianapolis');
		$cstdate = date("Y-m-d H:i:s").' '.$tz;
		
		//commit a row
		$row['Timestamp'] = $cstdate;
		$row['Datetime'] = $cstdate;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
	
		$row->Commit();
		$id = $row['TimeLogId']();
		if(!$id) Ex('New row not added');
		
		//lookup that row
		$db->DefaultTimezone = 'America/Chicago';
		$row = $db['TimeLog']($id);
		AssertCellVal($row['Timestamp'], $cstdate);
		AssertCellVal($row['Datetime'], $cstdate);
		AssertCellVal($row['Date'], $singleDate);
		AssertCellVal($row['Time'], $singleTime);

		$savedTimestamps[] = $savedTimestamp = $row['Timestamp']();
		$savedDatetimes[] = $savedDatetime = $row['Datetime']();
		$savedDate = $row['Date']();
		$savedTime = $row['Time']();
		
		//lookup that row again
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$savedTimestamp, 'Datetime'=>$savedDatetime, 'Date'=>$savedDate, 'Time'=>$savedTime]]);
		AssertExists($row);
		
		$row = $db['TimeLog']->LookupRow(['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$cstdate, 'Datetime'=>$cstdate, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		AssertExists($row);
		
		
		
	//COMPARE saved datetimes with GetAllValues()
		//print_r($savedDatetimes);
		date_default_timezone_set('America/Indiana/Indianapolis');
		
		$db->DefaultTimezone = '';	
		$datetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		for($i=0; $i<=5; $i++){
			if($datetimes[$i] != $savedDatetimes[$i]) throw new Exception('Date mismatch: '.$datetimes[$i].' != '.$savedDatetimes[$i]);
		}
	
		$db->DefaultTimezone = 'America/Indiana/Indianapolis';	
		$estDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($estDatetimes[6] != $savedDatetimes[6]) throw new Exception('Date mismatch: '.$estDatetimes[6].' != '.$savedDatetimes[6]);
		if($estDatetimes[7] != $savedDatetimes[7]) throw new Exception('Date mismatch: '.$estDatetimes[7].' != '.$savedDatetimes[7]);
		if($estDatetimes[8] != $savedDatetimes[8]) throw new Exception('Date mismatch: '.$estDatetimes[8].' != '.$savedDatetimes[8]);
		
		$db->DefaultTimezone = 'America/Chicago';	
		$cstDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($cstDatetimes[9] != $savedDatetimes[9]) throw new Exception('Date mismatch: '.$cstDatetimes[9].' != '.$savedDatetimes[9]);
		if($cstDatetimes[11] != $savedDatetimes[11]) throw new Exception('Date mismatch: '.$cstDatetimes[11].' != '.$savedDatetimes[11]);
		
		$db->DefaultTimezone = 'UTC';	
		$gmtDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($gmtDatetimes[10] != $savedDatetimes[10]) throw new Exception('Date mismatch: '.$gmtDatetimes[10].' != '.$savedDatetimes[10]);
		
		date_default_timezone_set('America/Chicago');
		$db->DefaultTimezone = 'America/Indiana/Indianapolis';	
		$estDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($estDatetimes[6] != $savedDatetimes[6]) throw new Exception('Date mismatch: '.$estDatetimes[6].' != '.$savedDatetimes[6]);
		if($estDatetimes[7] != $savedDatetimes[7]) throw new Exception('Date mismatch: '.$estDatetimes[7].' != '.$savedDatetimes[7]);
		if($estDatetimes[8] != $savedDatetimes[8]) throw new Exception('Date mismatch: '.$estDatetimes[8].' != '.$savedDatetimes[8]);
		
		$db->DefaultTimezone = 'America/Chicago';	
		$cstDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($cstDatetimes[9] != $savedDatetimes[9]) throw new Exception('Date mismatch: '.$cstDatetimes[9].' != '.$savedDatetimes[9]);
		if($cstDatetimes[11] != $savedDatetimes[11]) throw new Exception('Date mismatch: '.$cstDatetimes[11].' != '.$savedDatetimes[11]);
		
		$db->DefaultTimezone = 'UTC';	
		$gmtDatetimes = $db['TimeLog']['Datetime']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($gmtDatetimes[10] != $savedDatetimes[10]) throw new Exception('Date mismatch: '.$gmtDatetimes[10].' != '.$savedDatetimes[10]);

	
	//COMPARE saved timestamps with GetAllValues()
		date_default_timezone_set('America/Indiana/Indianapolis');
		
		$db->DefaultTimezone = '';	
		$timestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		for($i=0; $i<=5; $i++){
			if($timestamps[$i] != $savedTimestamps[$i]) throw new Exception('Date mismatch: '.$timestamps[$i].' != '.$savedTimestamps[$i]);
		}
	
		$db->DefaultTimezone = 'America/Indiana/Indianapolis';	
		$estTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($estTimestamps[6] != $savedTimestamps[6]) throw new Exception('Date mismatch: '.$estTimestamps[6].' != '.$savedTimestamps[6]);
		if($estTimestamps[7] != $savedTimestamps[7]) throw new Exception('Date mismatch: '.$estTimestamps[7].' != '.$savedTimestamps[7]);
		if($estTimestamps[8] != $savedTimestamps[8]) throw new Exception('Date mismatch: '.$estTimestamps[8].' != '.$savedTimestamps[8]);
		
		$db->DefaultTimezone = 'America/Chicago';	
		$cstTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($cstTimestamps[9] != $savedTimestamps[9]) throw new Exception('Date mismatch: '.$cstTimestamps[9].' != '.$savedTimestamps[9]);
		if($cstTimestamps[11] != $savedTimestamps[11]) throw new Exception('Date mismatch: '.$cstTimestamps[11].' != '.$savedTimestamps[11]);
		
		$db->DefaultTimezone = 'UTC';	
		$gmtTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($gmtTimestamps[10] != $savedTimestamps[10]) throw new Exception('Date mismatch: '.$gmtTimestamps[10].' != '.$savedTimestamps[10]);
		
		date_default_timezone_set('America/Chicago');
		$db->DefaultTimezone = 'America/Indiana/Indianapolis';	
		$estTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($estTimestamps[6] != $savedTimestamps[6]) throw new Exception('Date mismatch: '.$estTimestamps[6].' != '.$savedTimestamps[6]);
		if($estTimestamps[7] != $savedTimestamps[7]) throw new Exception('Date mismatch: '.$estTimestamps[7].' != '.$savedTimestamps[7]);
		if($estTimestamps[8] != $savedTimestamps[8]) throw new Exception('Date mismatch: '.$estTimestamps[8].' != '.$savedTimestamps[8]);
		
		$db->DefaultTimezone = 'America/Chicago';	
		$cstTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($cstTimestamps[9] != $savedTimestamps[9]) throw new Exception('Date mismatch: '.$cstTimestamps[9].' != '.$savedTimestamps[9]);
		if($cstTimestamps[11] != $savedTimestamps[11]) throw new Exception('Date mismatch: '.$cstTimestamps[11].' != '.$savedTimestamps[11]);
		
		$db->DefaultTimezone = 'UTC';	
		$gmtTimestamps = $db['TimeLog']['Timestamp']->GetAllValues(['sort-by'=>['TimeLogId'=>'ASC']]);
		if($gmtTimestamps[10] != $savedTimestamps[10]) throw new Exception('Date mismatch: '.$gmtTimestamps[10].' != '.$savedTimestamps[10]);

	
	$times = $db['TimeLog']['Time']->GetAllValues();
	foreach($times as $time){
		if($time != $singleTime) throw new Exception('Time mismatch: '.$time.' != '.$singleTime);
	}
	
	$dates = $db['TimeLog']['Date']->GetAllValues();
	foreach($dates as $date){
		if($date != $singleDate) throw new Exception('Time mismatch: '.$date.' != '.$singleDate);
	}
		
	//LOOKUP ROWS with dates
	date_default_timezone_set('America/Indiana/Indianapolis');

	$db->DefaultTimezone = '';
		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[0],
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount1 = count($rows);
		if($rowcount1==0) throw new Exception('Dates not found using LookupRows');

		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[6],
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount2 = count($rows);
		if($rowcount2 != $rowcount1) throw new Exception('Different rowcount returned when searching with timezone set');

	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[0], //assumes this is an EST date, but assumes UTC dates in the db.
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount3 = count($rows);
		if($rowcount3 == $rowcount1) throw new Exception('Same rowcount found when timezone set.');

		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[6],
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount4 = count($rows);
		if($rowcount3 != $rowcount4) throw new Exception('Different rowcount returned when searching with timezone set');
		
	$db->DefaultTimezone = 'America/Chicago';
		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[0], //assumes this is an EST date, but assumes UTC dates in the db.
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount3 = count($rows);
		if($rowcount3 == $rowcount1) throw new Exception('Same rowcount found when timezone set.');

		$rows = $db['TimeLog']->LookupRows([
			'Datetime' => $savedDatetimes[6],
			'Date'=>$singleDate,
			'Time'=>$singleTime
		]);
		$rowcount4 = count($rows);
		if($rowcount3 != $rowcount4) throw new Exception('Different rowcount returned when searching with timezone set');
	

	
	
	//LOOKUP COL VALUES
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = '';
	$date = "2000-01-01 00:00:00";
	$expectedRetDate = $date;
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
		
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = '';
	$date = "2000-01-01 00:00:00";
	$expectedRetDate = $date;
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
		
	date_default_timezone_set('UTC'); 
	$db->DefaultTimezone = '';
	$date = "2000-01-01 00:00:00";
	$expectedRetDate = $date;
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');

		
		
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'America/Chicago';
	$date = "2000-01-01 00:00:00"; //CST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT CDT) 
	$expectedRetDate = $date." CST";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
	$date = "2000-01-01 00:00:00"; //EST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT EDT) 
	$expectedRetDate = "2000-01-01 01:00:00 EST";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'UTC';
	$date = "2000-01-01 00:00:00"; //CST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT CDT) 
	$expectedRetDate = "2000-01-01 06:00:00 UTC";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	
	
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'America/Chicago';
	$date = "2000-01-01 00:00:00"; //CST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT CDT) 
	$expectedRetDate = $date." CST";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
	$date = "2000-01-01 00:00:00"; //EST IS ASSUME WITH NO GIVEN TZ SET because it's JANUARY (NOT EDT) 
	$expectedRetDate = $date." EST";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('UTC'); 
	$db->DefaultTimezone = 'UTC';
	$date = "2000-01-01 00:00:00"; //UTC IS ASSUME WITH NO GIVEN TZ SET
	$expectedRetDate = $date." UTC";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	
	
	date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'America/Chicago';
	$date = "2000-01-01 00:00:00 CST"; 
	$expectedRetDate = "2000-01-01 00:00:00 CST"; //CST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT CDT) 
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
	$date = "2000-01-01 00:00:00 CST"; 
	$expectedRetDate = "2000-01-01 01:00:00 EST"; //EST IS ASSUMED WITH NO GIVEN because it's JANUARY (NOT EDT) 
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	date_default_timezone_set('UTC'); 
	$db->DefaultTimezone = 'UTC';
	$date = date("2000-01-01 00:00:00 T"); 
	$expectedRetDate = "2000-01-01 00:00:00 UTC";
		$row = $db['TimeLog']();
		$row['Timestamp'] = $date;
		$row['Datetime'] = $date;
		$row['Date'] = $singleDate;
		$row['Time'] = $singleTime;
		$row->Commit();
		$id = $row['TimeLogId']();
		
		$foundVals = $db['TimeLog']->LookupColumnValue('Datetime',['AND'=>['TimeLogId'=>$id, 'Timestamp'=>$date, 'Datetime'=>$date, 'Date'=>$singleDate, 'Time'=>$singleTime]]);
		if($foundVals !== $expectedRetDate) throw new Exception('$foundVals ('.$foundVals.') !== $expectedRetDate ('.$expectedRetDate.')');
	
	
	
	
	//AGGREGATE VALUES
	$biggerDate = '2020-02-02';
	$biggerTime = '20:20:20';
	$row = $db['TimeLog']();
		$row['Timestamp'] = gmdate("2030-01-01 00:00:00"); 
		$row['Datetime'] = gmdate("2030-01-01 00:00:00");
		$row['Date'] = $biggerDate;
		$row['Time'] = $biggerTime;
		$row->Commit();
		
	date_default_timezone_set('UTC'); 
	$db->DefaultTimezone = 'UTC';
		$maxDatetime = $db['TimeLog']['Datetime']->GetMaxValue();
		$maxTimestamp = $db['TimeLog']['Timestamp']->GetMaxValue();
		$maxDate = $db['TimeLog']['Date']->GetMaxValue();
		$maxTime = $db['TimeLog']['Time']->GetMaxValue();
		$expectedVal = '2030-01-01 00:00:00 UTC';
		if($maxDatetime != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxTimestamp != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxDate != $biggerDate) throw new Exception('$maxDate ('.$maxDate.') != $biggerDate ('.$biggerDate.')');
		if($maxTime != $biggerTime) throw new Exception('$maxDatetime ('.$maxTime.') != $expectedVal ('.$biggerTime.')');

	//date_default_timezone_set('America/Chicago');
	$db->DefaultTimezone = 'America/Chicago';
		$maxDatetime = $db['TimeLog']['Datetime']->GetMaxValue();
		$maxTimestamp = $db['TimeLog']['Timestamp']->GetMaxValue();
		$maxDate = $db['TimeLog']['Date']->GetMaxValue();
		$maxTime = $db['TimeLog']['Time']->GetMaxValue();
		date_default_timezone_set('America/Chicago');
		$expectedVal = '2029-12-31 18:00:00 CST'; //expected CST not CDT because december
		if($maxDatetime != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxTimestamp != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxDate != $biggerDate) throw new Exception('$maxDate ('.$maxDate.') != $biggerDate ('.$biggerDate.')');
		if($maxTime != $biggerTime) throw new Exception('$maxDatetime ('.$maxTime.') != $expectedVal ('.$biggerTime.')');
		
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
		$maxDatetime = $db['TimeLog']['Datetime']->GetMaxValue();
		$maxTimestamp = $db['TimeLog']['Timestamp']->GetMaxValue();
		$maxDate = $db['TimeLog']['Date']->GetMaxValue();
		$maxTime = $db['TimeLog']['Time']->GetMaxValue();
		$expectedVal = '2029-12-31 19:00:00 EST'; //expected EST not EDT because december
		if($maxDatetime != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxTimestamp != $expectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') != $expectedVal ('.$expectedVal.')');
		if($maxDate != $biggerDate) throw new Exception('$maxDate ('.$maxDate.') != $biggerDate ('.$biggerDate.')');
		if($maxTime != $biggerTime) throw new Exception('$maxDatetime ('.$maxTime.') != $expectedVal ('.$biggerTime.')');
	
	date_default_timezone_set('America/Indiana/Indianapolis');
	$db->DefaultTimezone = 'America/Indiana/Indianapolis';
		$maxDatetime = $db['TimeLog']['Datetime']->GetMaxValue(['Datetime' => ['!=' => '2029-12-31 19:00:00 EST']]);
		$maxTimestamp = $db['TimeLog']['Timestamp']->GetMaxValue(['Timestamp' => ['!=' => '2029-12-31 19:00:00 EST']]);
		$maxDate = $db['TimeLog']['Date']->GetMaxValue(['Date' => ['!=' => $biggerDate]]);
		$maxTime = $db['TimeLog']['Time']->GetMaxValue(['Time' => ['!=' => $biggerTime]]);
		$UNexpectedVal = '2029-12-31 19:00:00 EST'; //expected EST not EDT because december
		if($maxDatetime == $UNexpectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') == $UNexpectedVal ('.$UNexpectedVal.')');
		if($maxTimestamp == $UNexpectedVal) throw new Exception('$maxDatetime ('.$maxDatetime.') == $UNexpectedVal ('.$UNexpectedVal.')');
		if($maxDate == $biggerDate) throw new Exception('$maxDate ('.$maxDate.') == $UNbiggerDate ('.$biggerDate.')');
		if($maxTime == $biggerTime) throw new Exception('$maxDatetime ('.$maxTime.') == $UNexpectedVal ('.$biggerTime.')');
		
	
	
	//CALLBACKS VALUES
	date_default_timezone_set('America/Chicago'); 
	$db->DefaultTimezone = 'America/Chicago';
	$row = $db['TimeLog']();
	$obcCallbacksCount = 0;
	$osvCallbacksCount = 0;
	$oacCallbacksCount = 0;
	$row->OnBeforeColumnValueChanged(function($eventObject, $eventArgs) use (&$obcCallbacksCount){
		//echo 'OnBeforeColumnValueChanged<br>';
		//echo $eventArgs['current-value'].'<br>';
		//echo $eventArgs['new-value'].'<br>';
		$obcCallbacksCount++;
	});
	$row->OnSetColumnValue(function($eventObject, $eventArgs) use (&$osvCallbacksCount){
		//echo 'OnSetColumnValue<br>';
		//echo $eventArgs['current-value'].'<br>';
		//echo $eventArgs['new-value'].'<br>';
		$osvCallbacksCount++;
	});
	$row->OnAfterColumnValueChanged(function($eventObject, $eventArgs) use (&$oacCallbacksCount){
		//echo 'OnAfterColumnValueChanged<br>';
		//echo $eventArgs['current-value'].'<br>';
		//echo $eventArgs['old-value'].'<br>';
		$oacCallbacksCount++;
	});	
	$row['Datetime'] = "2006-06-06 00:00:00 UTC";
	$row['Datetime'] = "2006-06-06 00:00:00 UTC";
	$row['Datetime'] = "2006-06-05 19:00:00 EST"; 
	if($osvCallbacksCount != 3) throw new Exception('OnSetColumnValue not called 3 times');
	if($obcCallbacksCount != 1) throw new Exception('OnBeforeColumnValueChanged not called 1 times');
	if($oacCallbacksCount != 1) throw new Exception('OnAfterColumnValueChanged not called 1 times');
	
	$obcCallbacksCount = 0;
	$osvCallbacksCount = 0;
	$oacCallbacksCount = 0;
	$row['Timestamp'] = "2006-06-06 00:00:00 UTC";
	$row['Timestamp'] = "2006-06-06 00:00:00 UTC";
	$row['Timestamp'] = "2006-06-05 19:00:00 EST"; 
	if($osvCallbacksCount != 3) throw new Exception('OnSetColumnValue not called 3 times');
	if($obcCallbacksCount != 1) throw new Exception('OnBeforeColumnValueChanged not called 1 times');
	if($oacCallbacksCount != 1) throw new Exception('OnAfterColumnValueChanged not called 1 times');
}
	



/**
 * forms
 * @ignore
 */
function TestForm($t, $debug=false){
	?>
	<form action="" method="get">
		Select ID:
		<input type="text" name="Questionare[QuestionareId]"><br>
		<input type="submit" value="select" name="submit">
	</form>
	<?
	$validIds = $t['database']['Questionare']['QuestionareId']->GetAllValues();
	echo '<a href="'.$_SERVER['PHP_SELF'].'">clear</a><br>'."\n";
	foreach($validIds as $id){
		echo '<a href="?Questionare[QuestionareId]='.$id.'&submit=select">Get Id '.$id.'</a><br>'."\n";
	}
	?>
	<hr>
	<form name="test7" action="" method="post">
		<?
		ob_start();
		if($debug && $_POST) echo "POST:<br>".print_r($_POST,true)."<br>";
		$i = new Questionare($t['database'], ($_REQUEST['Questionare']['QuestionareId'] ?? null) );
		if(!$i->Exists() && !empty($_POST['submit']) && $_POST['submit']!='save'){
			echo '<b>Id '.$_REQUEST['Questionare']['QuestionareId'].' does not exist</b><br>';
			$i['QuestionareId']->ForceUnset();
		}
		if($_POST){
			$i->SetNonKeyColumnValues($_POST);
			Msg($debug,"After set from POST",$i);
			if($debug) echo "<br>\n";
		}
		if(!empty($_POST['submit']) && $_POST['submit']==='delete'){
			if(!$i->Exists()) Msg($debug,"Row doesnt exist to delete",$i);
			else{
				if($debug) echo "<br>Before delete: <br>\n".$i;
				if(!$i->Delete()) Ex("Delete returned false");
				if($debug) echo "<br>After delete: <br>\n".$i;
				AssertIsNotDirty($i);;
				AssertNotExists($i);
			}
		}
		else if(!empty($_POST['submit']) && $_POST['submit']==='save'){
			if($errors=$i->HasErrors()){
				if($debug) echo "Errors: $errors<br>\n";
			}
			else if(!$i->IsDirty()){
				Msg($debug, 'No changes need saved', $i);
			}
			else Commit($i,$debug);
		}
		$debugTxt = ob_get_contents();
		ob_end_clean();
		$allCells = $i->GetAllCells();
		foreach($allCells as $columnName=>$Cell){
			if($Cell->IsPrimaryKey){
				echo $Cell->GetFormObjectLabel().' '.$Cell->GetTextFormObject(array('disabled'=>'disabled') )."<br>\n";
				echo $Cell->GetFormObject();
			}
			if($Cell->IsDateColumn){
				echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject()."<br>\n";
			}
			else if(strtolower($Cell->DefaultFormType) === "select"){
				if($Cell->Column->ColumnName == 'AgeGroup'){
					echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject('select',array("over18"=>"Over 18","under18"=>"Under 18"))."<br>\n";
				}
				else if($Cell->Column->ColumnName == 'Hobbies'){
					echo $Cell->GetFormObjectLabel().' '.$Cell->GetSelectFormObject(true);
				}
			}
			else if(strtolower($Cell->DefaultFormType) === "radio"){
				if($Cell->PossibleValues){
					echo $Cell->GetFormObjectLabel().' ';
					if(!$Cell->Column->IsRequired) echo $Cell->GetRadioFormObject("- not set -","")."<br>\n";
					foreach($Cell->PossibleValues as $possibleValue){
						echo $Cell->GetRadioFormObject($possibleValue,$possibleValue)."<br>\n";
					}
				}
				else{
					echo $Cell->GetFormObjectLabel().' ';
					echo $Cell->GetRadioFormObject("None","")."<br>\n";
					echo $Cell->GetRadioFormObject("Male","M")."<br>\n";
					echo $Cell->GetRadioFormObject("Female","F")."<br>\n";
					echo $Cell->GetRadioFormObject("Invalid","I")."<br>\n";
				}
			}
			else if(strtolower($Cell->DefaultFormType) === "password"){
				function formatPassword($value){
					return 'callback-test';
				}
				echo $Cell->GetFormObjectLabel().' '.$Cell->GetPasswordFormObject(array('id'=>null),array('custom-formatter-callback'=>'formatPassword','show-required-marker'=>true))."<br>\n";
			}
			else echo $Cell->GetFormObjectLabel(['for-id-suffix'=>'-suffixtest']).' '.$Cell->GetFormObject(null,null,['id-suffix'=>'-suffixtest'])."<br>\n";
		}
		?>
		<input type="submit" value="save" name="submit">
		<input type="submit" value="delete" name="submit">
	</form>
	<hr>
	<?
	if($debug) echo $debugTxt;
}
