<?php
//Required files... the SmartDatabase and a DbManager
$smartDbPath = dirname(__FILE__)."/../../../SmartDatabase.php";
$dbManagerPath = dirname(__FILE__)."/../../../DbManagers/DbManager_MySQL.php";
require_once($smartDbPath);
require_once($dbManagerPath);

//-- create a DbManager object --
//The given username must have access to the MySQL server, and the database is assumed to already be 
//created. If the database is not created, we can create it after we have a DbManager object (see below)
$db_server = "localhost"; //mysql host
$db_username = "smartdb"; //mysql username
$db_password = "smartdb123"; //mysql password
$database_name = "smartdb_test"; //mysql database

//It is recommended that the dbManager be stored in a global for easy access from anywhere within the project
$GLOBALS['dbManager'] = new DbManager_MySQL($db_server, $db_username, $db_password, $database_name); //initialize DbManager

//If the database is not yet created, don't pass $database_name into the DbManager constructor, and uncomment this:
//$GLOBALS['dbManager']->CreateDatabase($database_name); //an "Access denied" here means your user is restriced on a SQL level
//$GLOBALS['dbManager']->GrantUserPermissions($database_name, $db_username); //an "Access denied" means user SQL restriction

//The location of the XML database schema file for this project
$dbSchemaPath = dirname(__FILE__)."/../database/db.xml";

//Initialize the SmartDatabase object. It is built from a DbManager connection and
//an XML schema document (both passed to the SmartDatabase constructor). It is recommended
//that the SmartDatabase be stored in a global for easy access from anywhere within the project
$GLOBALS['db'] = new SmartDatabase($GLOBALS['dbManager'], $dbSchemaPath);

//If we want to synchronize our XML schema to the actual database, uncomment this. Dont always leave it uncommented!
//$GLOBALS['db']->SyncStructureToDatabase(true); //true prints the results
//die; //die when synchronizing. we dont want to do anything else
?>
