<?
/**
 * @package Tests
 * @ignore
 */
/**
 */
require_once(dirname(__FILE__).'/../../DbManager.php');
require_once(dirname(__FILE__).'/../SmartDatabase.php');

/**
 * @package Tests
 * @ignore
 */
class Setting extends SmartRow{
	public function __construct($Database, $id=null ,$opts=null){
		parent::__construct('Setting', $Database, $id);
	}
}
/**
 * @package Tests
 * @ignore
 */
class Log extends SmartRow{
	public function __construct($Database, $id=null ,$opts=null){
		parent::__construct('Log', $Database, $id);
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

//--create db manager
$dbManager = $t['dbManager'] = new DbManager_MySQL('SERVER','USERNAME','PASSWORD','DATABASE_NAME');

//--build the Database instance
$database = $t['database'] = new SmartDatabase($dbManager, dirname(__FILE__).'/test.xml');
$database->DEV_MODE_WARNINGS = false; //turn off warnings for now
//print_nice($database);
//$database->WriteXmlSchema('/home/adam/public_html/test.xml');
//die;

$t['database']['Setting']->TableName = "Settings"; //change name of database table "Settings"

$GLOBALS['SQL_DEBUG_MODE'] = false; //set to true to see all SQL commands run through the db manager
SyncDbTables($t);

//////////////////////////////////////
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/core/1.3.2/jquery.min.js"></script>
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/validate/1.5.5/jquery.validate.min.js"></script>
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/validate/1.5.5/additional-methods.js"></script>
		<script type="text/javascript">
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
		Test14($t); //enumerations
		Test15($t); //data types and 0/null
		Test16($t); //min, max, sum
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
	//$printResults = true;
	$results = $t['database']->SyncStructureToDatabase($printResults);
	if(strstr($results, "Fatal error") !== false || strstr($results, "exception") !== false){
		throw new Exception("SyncDbTablesFailed");
	}
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
	if($rowVal !== $value) throw new Exception("Row value '$rowVal' doensn't match '$value'");

	$rowVal = $c(); //GetValue() shortcut, new with PHP 5.3.0
	if($rowVal !== $value) throw new Exception("Row value '$rowVal' doensn't match '$value' when using GetValue shortcut");
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
	AssertCellVal($i['Id'], '69');
	AssertCellVal($i['Name'], "test name");

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
	AssertCellVal($i['Level'], 13);

	$i['Level']->DisableCallbacks = false;
	$i->DisableCallbacks(true);
	$i['Level'] = 12.34;
	if($GLOBALS['test5']['BeforeValueChanged']) Ex('Callback ran');
	AssertCellVal($i['Level'], 12.34);

	$i->EnableCallbacks (true);
	$GLOBALS['test5']['BeforeValueChanged'] = false;
	$i['Level'] = 12.35;
	if(!$GLOBALS['test5']['BeforeValueChanged']) Ex('Callback didnt run');
	if(!$GLOBALS['test5']['newVal1235']) Ex('Wrong new value');
	AssertCellVal($i['Level'], 12.34);

	$GLOBALS['test5']['cell'] = $i['Timestamp'];
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

			if($eventArgs['new-value'] === $GLOBALS['test5']['timestamp1']){
				$eventArgs['cancel-event'] = true;
			}

		}
	}
	$i['Timestamp']->OnSetValue('ValueSet','TestStaticCallbackClass');
	Commit($i,$debug);
	$timestamp = date("Y-m-d H:i:s");
	$GLOBALS['test5']['timestamp1'] = $timestamp;
	$i['Timestamp'] = $timestamp;
	Msg($debug,'After timestamp set and cancelled',$i);
	AssertCellVal($i['Timestamp'], null);

	$timestamp = date("2008-m-d H:i:s");
	$GLOBALS['test5']['timestamp2'] = $timestamp;
	$i['Timestamp'] = $timestamp;
	Msg($debug,'After timestamp set',$i);
	AssertCellVal($i['Timestamp'], $timestamp);
	Commit($i,$debug);

	Delete($i,$debug);
}
/**
 * Array row functions, cloning, lookup by set value
 * @ignore
 */
function Test6($t, $debug=false){
	$i = new Log($t['database']);
	$i['Name'] = 'Connection Interrupted';
	$timestamp = date("2009-m-d H:i:s");
	$i['Timestamp'] = $timestamp;
	$i['Level'] = 6543.21;
	Commit($i, $debug);

	$id = $i['Id']();
	if(!isset($id)) Ex('Id is not set');

	$nonKeys = $i->GetNonKeyColumnValues();
	if(count($nonKeys['Log']) != 4) Ex('Wrong column count returned');
	if($nonKeys['Log']['Name'] !== 'Connection Interrupted') Ex('Log Name not equal');
	if($nonKeys['Log']['Timestamp'] !== $timestamp) Ex('Log timestamp not equal');
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

	AssertCellVal($j['Id'], "$id"); //must be a string here
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
	$allvalues = $db['Setting']['Name']->GetAllValues(array('sort-by'=>'Id','get-unique'=>true,'return-count'=>&$count));
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

 	$db['FastSetting']['Name']->DeleteRowsWithValue('testname@numero.dos');
	$row = $db['FastSetting']['Name']->LookupRow('testname@numero.dos');
	AssertNotExists($row, $debug);
}


/**
 * helper function for test9 and test10 (at the least)
 * @ignore
 */
function InsretSomeFastSettingRows($db){
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

	AssertCellVal($allRows[0]['Id'], "70");
	AssertCellVal($allRows[1]['Id'], "69");

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}

	//GetAllRows()
	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'limit'=>1));
	$count=count($allRows);
	if($debug) echo "Get all rows, limit 1, count: $count<br>\n";
	if($count !== 1) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], "69");

	if($debug){
		foreach($allRows as $keyValue=>$Row){
			echo $keyValue.'='.$Row;
		}
	}

	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'limit'=>'1,1'));
	$count=count($allRows);
	if($debug) echo "Get all rows, limit 1, count: $count<br>\n";
	if($count !== 1) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[0]['Id'], "70");

	$allRows = $db['Setting']->GetAllRows(array('sort-by'=>array("Id"=>"asc"), 'return-assoc'=>true));
	$count=count($allRows);
	if($debug) echo "Get all rows, count: $count<br>\n";
	if($count !== 2) Ex("Wrong number of rows returned");

	AssertCellVal($allRows[70]['Id'], "70");
	AssertCellVal($allRows[69]['Id'], "69");

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
}

/**
 * table lookup functions
 * @ignore
 */
function Test10($t, $debug=false){
	$db = $t['database'];
	InsretSomeFastSettingRows($db);

	//LookupColumnValues
	$rows = $db['FastSetting']->LookupColumnValues(array("ShortName"=>"short name"),"Name",array('sort-by'=>'Id'));
	if(count($rows) !== 2) Ex("Wrong row count");
	if($rows[0] !== "my@name.com") Ex("Row name value not equal");

	$rows = $db['FastSetting']->LookupColumnValues(array("ShortName"=>"short name"),"Name",array('sort-by'=>array('Id'=>'DEsC'),'return-assoc'=>true));
	if(count($rows) !== 2) Ex("Wrong row count");
	if($rows[35] !== "my@name2.com") Ex("Row name value not equal");

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
	AssertCellVal($row['Id'], '35');
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
	
	$row = $db['FastSetting'](35); //lookup by primary key column
	Msg($debug, "Looked up row by id, match", $row);
	AssertCellVal($row['Id'], '35');
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

	//GetRelatedRows
	$setting = new SmartRow('FastSetting', $db, 35);
	$relatedRows = $setting['Id']->GetRelatedRows('Log','Id');
	if(count($relatedRows) !== 0) Ex("found related rows");

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
 * enumerations
 * @ignore
 */
function Test14($t, $debug=false){
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

	Delete($row, $debug);
}

/**
 * data types
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
		$i = new Questionare($t['database'],$_REQUEST['Questionare']['QuestionareId']);
		if(!$i->Exists() && $_POST['submit']!='save'){
			echo '<b>Id '.$_REQUEST['Questionare']['QuestionareId'].' does not exist</b><br>';
			$i['QuestionareId']->ForceUnset();
		}
		if($_POST){
			$i->SetNonKeyColumnValues($_POST);
			Msg($debug,"After set from POST",$i);
			if($debug) echo "<br>\n";
		}
		if($_POST['submit']==='delete'){
			if(!$i->Exists()) Msg($debug,"Row doesnt exist to delete",$i);
			else{
				if($debug) echo "<br>Before delete: <br>\n".$i;
				if(!$i->Delete()) Ex("Delete returned false");
				if($debug) echo "<br>After delete: <br>\n".$i;
				AssertIsNotDirty($i);;
				AssertNotExists($i);
			}
		}
		else if($_POST['submit']==='save'){
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
			else if(strtolower($Cell->DefaultFormType) === "select"){
				echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject('select',array("over18"=>"Over 18","under18"=>"Under 18"))."<br>\n";
			}
			else if(strtolower($Cell->DefaultFormType) === "radio"){
				echo $Cell->GetFormObjectLabel().' ';
				echo $Cell->GetRadioFormObject("Male","M")."<br>\n";
				echo $Cell->GetRadioFormObject("Female","F")."<br>\n";
				echo $Cell->GetRadioFormObject("Invalid","I")."<br>\n";
			}
			else if(strtolower($Cell->DefaultFormType) === "password"){
				function formatPassword($value){
					return 'callback-test';
				}
				echo $Cell->GetFormObjectLabel().' '.$Cell->GetPasswordFormObject(array('id'=>null),array('custom-formatter-callback'=>'formatPassword','show-required-marker'=>true))."<br>\n";
			}
			else echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject()."<br>\n";
		}
		?>
		<input type="submit" value="save" name="submit">
		<input type="submit" value="delete" name="submit">
	</form>
	<hr>
	<?
	if($debug) echo $debugTxt;
}
