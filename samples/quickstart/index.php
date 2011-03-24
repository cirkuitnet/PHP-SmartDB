<?php
require_once("include/include.php");

/*
BEFORE YOU BEGIN, DO THE FOLLOWING TO CUSTOMIZE YOUR APP:
	- UPDATE THE 'database/db.xml' XML FILE TO THE DATABASE SCHEMA YOU NEED FOR YOUR APP
		- ADD/EDIT TABLES, COLUMNS, AND FOREIGN KEYS AS NEEDED. YOU CAN ALWAYS TWEAK THESE AS YOU WORK.
	- UPDATE 'include/include.php' AS FOLLOWS:
		- SET $smartDbPath AND $dbManagerPath TO THE LOCATIONS OF THE SmartDatabase AND YOUR DbManager
		- SET $db_server, $db_username, $db_password, AND $database_name FOR YOUR DATABASE CONNECTION
			- NOTE - THE USER AND DATABASE SHOULD ALREADY EXIST! CONTACT YOUR DATABASE ADMIN IF YOU NEED CREDENTIALS
			- MAKE SURE THIS 'include/include.php' FILE IS SECURE AND NOT WORLD-READABLE! KEEP YOUR CREDENTIALS SAFE.
	- EXECUTE 'include/_sync.php' TO SYNC YOUR DATABASE SCHEMA TO THE ACTUAL BACKEND DATABASE
		- ANYTIME YOU MAKE A CHANGE TO YOUR 'database/db.xml' XML FILE, YOU SHOULD RE-SYNC YOUR DATABASE
 	- UPDATE/RENAME 'include/MyObject1.php' and 'include/MyObject2.php' TO MATCH YOUR TABLE NAMES
 		- FOR EXAMPLE, IF YOU HAVE TABLES "Customer" and "Invoice", THESE WILL BE CALLED "Customer.php" AND "Invoice.php".
 		  THESE NAMES SHOULD MATCH THE CLASS NAME YOU HAVE SET IN YOUR db.xml XML SCHEMA. EX: <Class Name="Customer"> 
 		- YOU SHOULD HAVE 1 INCLUDE FILE/CLASS FOR EACH TABLE IN YOUR db.xml XML SCHEMA
 		- EACH OF THESE CLASSES SHOULD BE NAMED THE SAME AS IN YOUR db.xml XML SCHEMA. EX: <Class Name="Customer">


IMPLEMENT YOUR APPLICATION HERE. $GLOBALS['db'] IS AVAILABLE FROM THE ABOVE require_once("include/include.php");
*/

//EXAMPLE - BASIC DATABASE INTERACTION - REMOVE THIS CODE AND START WRITING YOUR APP!
//only run this example code if 100 rows are not yet inserted
if(count($GLOBALS['db']['MyObject1']) < 100){
	$myObj1 = $GLOBALS['db']['MyObject1']->GetNewRow();
	$myObj1['MyString'] = "www.phpsmartdb.com"; //will be saved as "http://www.phpsmartdb.com" because of a SmartCell callback. see include/MyObject1.php
	$myObj1['MyEnum'] = "Value 2";
	$myObj1->Commit();
	
	$myObj2 = $GLOBALS['db']['MyObject2']->GetNewRow();
	$myObj2['MyObject1Id'] = $myObj1['MyObject1Id']();
	$myObj2['MyDecimal'] = "16000.2";
	$myObj2->Commit();
	
	echo "<h2>Sample 'MyObject1' Inserted:</h2>".$myObj1;
	echo "<h2>Sample 'MyObject2' Inserted:</h2>".$myObj2;
	echo '<br>$myObj2->GetFormattedPrice(): '.$myObj2->GetFormattedPrice(); //custom SmartRow functionality. see include/MyObject2.php
}
else{
	echo '100 example rows have been inserted... not doing this anymore. Clear the example tables if you want to continue.';
}
?>