<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("delete-by-email.php");
DisplayHelper::PrintTitle("delete-by-email.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//lookup the Customer row with the EmailAddress = "queen@muppets.com"
$Customer = $GLOBALS["db"]["Customer"]->LookupRow(array(
	"EmailAddress" => "queen@muppets.com"
));

//note- instead of LookupRow() on the table, we can LookupRow() from the column:
//$Customer
//	= $GLOBALS["db"]["Customer"]["EmailAddress"]->LookupRow("queen@muppets.com");

// -- BEFORE DELETE --

//print a message if the customer does not exist
if(!$Customer->Exists()){
	echo "Customer with EmailAddress=queen@muppets.com not found to delete.";
}

//delete the row from the database. It`s fine if the row doesn`t exist.
$numRowsDeleted = $Customer->Delete();

// -- AFTER DELETE --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/delete-by-email.php");
DisplayHelper::PrintOutputTitle();

//lookup the Customer row with the EmailAddress = "queen@muppets.com"
$Customer = $GLOBALS['db']['Customer']->LookupRow(array(
	'EmailAddress' => "queen@muppets.com"
));

//note- instead of LookupRow() on the table, we can LookupRow() from the column:
//$Customer = $$GLOBALS['db']['Customer']['EmailAddress']->LookupRow("queen@muppets.com");

//let's take a peek at all the fields before we delete
DisplayHelper::PrintRow($Customer, "Row Before Delete");

//print a message if the customer does not exist
if(!$Customer->Exists()){
	DisplayHelper::PrintErrors("Customer with EmailAddress=queen@muppets.com not found to delete.");
}

//delete the row from the database. It's fine if the row doesn't exist.
$numRowsDeleted = $Customer->Delete();

DisplayHelper::PrintRowsAffected($numRowsDeleted, "Delete()");

//let's take a peek at all the fields after the delete
DisplayHelper::PrintRow($Customer, "Row After Delete");

DisplayHelper::PrintTemplateBottom();
?>