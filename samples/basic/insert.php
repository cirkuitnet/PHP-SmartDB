<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("insert.php");
DisplayHelper::PrintTitle("insert.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//create a new Customer row
$Customer = $GLOBALS["db"]["Customer"]->GetNewRow();

//note- as a shortcut to GetNewRow(), we could simply do:
//$Customer = $GLOBALS["db"]["Customer"]();

// -- NEW ROW -- 

//set the customer row cells with data
$Customer["Name"] = "Jack Frost";
$Customer["EmailAddress"] = "jfrost@winter.com";
$Customer["Gender"] = "Male";
$Customer["DateCreated"] = gmdate("Y-m-d H:i:s T");
$Customer["DateLastModified"] = gmdate("Y-m-d H:i:s T");

// -- AFTER SET FIELDS, BEFORE COMMIT -- 

//check for errors before we commit
//HasErrors() returns nothing when there are no errors
if( ($errors=$Customer->HasErrors()) ){
	echo "Errors found: ".$errors;	
}
else{
	//commit this row to the database
	$numRowsInserted = $Customer->Commit();
}

// -- AFTER COMMIT --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/insert.php");
DisplayHelper::PrintOutputTitle();

//create a new Customer row
$Customer = $GLOBALS['db']['Customer']->GetNewRow();

//note- as a shortcut to GetNewRow(), we could simply do:
//$Customer = $GLOBALS['db']['Customer']();

// -- NEW ROW -- 
DisplayHelper::PrintRow($Customer, "New Row");

//set the customer row cells with data 
$Customer['Name'] = "Jack Frost";
$Customer['EmailAddress'] = "jfrost@winter.com";
$Customer['Gender'] = "Male";

// -- AFTER SET FIELDS, BEFORE COMMIT -- 
DisplayHelper::PrintRow($Customer, "Row After Set Fields, Before Commit");

//check for errors before we commit
//HasErrors() returns nothing when there are no errors
if( ($errors=$Customer->HasErrors()) ){
	DisplayHelper::PrintErrors($errors);
}
else{
	//commit this row to the database
	$numRowsInserted = $Customer->Commit();
}

DisplayHelper::PrintRowsAffected($numRowsInserted, "Commit()");

// -- AFTER COMMIT -- let's take a peek at all the fields after the commit
DisplayHelper::PrintRow($Customer, "Row After Commit");

DisplayHelper::PrintTemplateBottom();
?>
