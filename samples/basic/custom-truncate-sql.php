<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("custom-truncate-sql.php");
DisplayHelper::PrintTitle("custom-truncate-sql.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//using "count" on a table will return the total number of rows in that table
$totalNumRows = count($GLOBALS["db"]["Customer"]);

// -- BEFORE TRUNCATE --

//Execute our custom SQL. You will almost never want to write custom SQL, as this
//will completely bypass table/syntax checking, not use software row caching, and 
//restrict your software to 1 particular back-end database that supports your SQL 
//syntax. You CAN use Select(), Update(), Insert(), and Delete() within the 
//DbManager to create completely custom queries. THOUGH, the SmartDatabase should
//be able to take care of 99%+ of your data-access though. DbManager queries are
//only recommended for the advanced.
$customerTablename = $GLOBALS["db"]["Customer"]->TableName; //i.e. "Customer"
$GLOBALS["dbManager"]->Query("TRUNCATE TABLE `$customerTablename`");

//Note- to remove all rows of a table, we should do this instead:
//$GLOBALS["db"]["Customer"]->DeleteAllRows();

//using "count" on a table will return the total number of rows in that table
$totalNumRows = count($GLOBALS["db"]["Customer"]);

// -- AFTER TRUNCATE --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/custom-truncate-sql.php");
DisplayHelper::PrintOutputTitle();

//using "count" on a table will return the total number of rows in that table
$totalNumRows = count($GLOBALS["db"]["Customer"]);

// -- BEFORE TRUNCATE --
DisplayHelper::PrintRowsAffected($totalNumRows, "Before Truncate", "Total 'Customer' Rows");

//Execute our custom SQL. You will almost never want to write custom SQL, as this
//will completely bypass table/syntax checking, not use software row caching, and 
//restrict your software to 1 particular back-end database that supports your SQL 
//syntax. You CAN use Select(), Update(), Insert(), and Delete() within the 
//DbManager to create completely custom queries. THOUGH, the SmartDatabase should
//be able to take care of 99%+ of your data-access though. DbManager queries are
//only recommended for the advanced.
$customerTablename = $GLOBALS["db"]["Customer"]->TableName; //i.e. "Customer" 
$GLOBALS['dbManager']->Query("TRUNCATE TABLE `$customerTablename`");

//Note- to remove all rows of a table, we should do this instead:
//$GLOBALS["db"]["Customer"]->DeleteAllRows();

//using "count" on a table will return the total number of rows in that table
$totalNumRows = count($GLOBALS["db"]["Customer"]);

// -- AFTER TRUNCATE --
DisplayHelper::PrintRowsAffected($totalNumRows, "After Truncate", "Total 'Customer' Rows");

DisplayHelper::PrintTemplateBottom();
?>