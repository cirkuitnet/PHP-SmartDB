<?php
require_once("include/include.php");

/*
BEFORE YOU BEGIN, DO THE FOLLOWING TO CUSTOMIZE YOUR APP:
   1. Update the 'database/db.xml' XML file. This schema defines the database structure of your application.
          * Add/edit tables, columns, and foreign keys as needed. You can always tweak these as you work.
          * See Overview and Basics for specifics on this XML file.
   2. Update 'include/include.php' as follows:
          * Set $smartDbPath and $dbManagerPath to the locations of the SmartDB Framework's SmartDatabase class and a DbManager.
          * Set $db_server, $db_username, $db_password, and $database_name for your database connection.
                o Note - the user and database should already exist! Contact your database admin if you need credentials.
                o Make sure this 'include/include.php' file is secure and not world-readable! Keep your credentials safe.
   3. Execute 'include/_sync.php' to sync your database schema to the actual backend database.
          * Anytime you make a change to your 'database/db.xml' xml file, you should re-sync with your backend database.
   4. Update/rename/edit/delete 'include/MyObject1.php' and 'include/MyObject2.php'
          * You should have 1 include file/class for each table defined in your 'database/db.xml'XML schema.
                o If you have tables "Customer", "Invoice", and "Transaction", you should have 3 of these files created: "Customer.php", "Invoice.php", and "Transaction.php". These names should match the class name you have set in your 'database/db.xml' XML schema. Ex: <Class Name="Customer"> (so the autoloader will work!)
                o Each of these files should contain classes named the same as in your 'database/db.xml' XML schema. Ex: <Class Name="Customer"> (namespaces are supported)
          * You should edit these quick-start classes. The TableName constants will need to be set to your table names, and these classes have some basic example functionality in them that should be altered or removed.


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