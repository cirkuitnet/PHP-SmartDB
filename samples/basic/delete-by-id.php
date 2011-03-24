<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("delete-by-id.php");
DisplayHelper::PrintTitle("delete-by-id.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//lookup the Customer row with a primay key (CustomerId) of 1
$Customer = $GLOBALS["db"]["Customer"]->LookupRow(1);

//note- as a shortcut to LookupRow($id) above, we could do any of the following:
//$Customer = $GLOBALS["db"]["Customer"](1);
//$Customer = $GLOBALS["db"]["Customer"]->LookupRow(array(
//	"CustomerId" => 1
//));
//$Customer = $GLOBALS["db"]["Customer"]["CustomerId"]->LookupRow(1);

// -- BEFORE DELETE --

//print a message if the customer does not exist
if(!$Customer->Exists()){
	echo "Customer with CustomerId=1 not found to delete.";
}

//delete the row from the database. It`s fine if the row doesn`t exist.
$numRowsDeleted = $Customer->Delete();

// -- AFTER DELETE --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/delete-by-id.php");
DisplayHelper::PrintOutputTitle();

//lookup the Customer row with the CustomerId of 1
$Customer = $GLOBALS['db']['Customer']->LookupRow(1);

//note- as a shortcut to LookupRow($id) above, we could do any of the following:
//$Customer = $GLOBALS['db']['Customer'](1);
//$Customer = $GLOBALS['db']['Customer']->LookupRow(array(
//	"CustomerId" => 1
//));
//$Customer = $GLOBALS['db']['Customer']['CustomerId']->LookupRow(1);

//let's take a peek at all the fields before we delete
DisplayHelper::PrintRow($Customer, "Row Before Delete");

//print a message if the customer does not exist
if(!$Customer->Exists()){
	DisplayHelper::PrintErrors("Customer with CustomerId=1 not found to delete.");
}

//delete the row from the database. It's fine if the row doesn't exist.
$numRowsDeleted = $Customer->Delete();

DisplayHelper::PrintRowsAffected($numRowsDeleted, "Delete()");

//let's take a peek at all the fields after the delete
DisplayHelper::PrintRow($Customer, "Row After Delete");

DisplayHelper::PrintTemplateBottom();
?>