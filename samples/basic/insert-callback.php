<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("insert-callback.php");
DisplayHelper::PrintTitle("insert-callback.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//create a new Customer row
$Customer = $GLOBALS["db"]["Customer"]->GetNewRow();

//note- as a shortcut to GetNewRow(), we could simply do:
//$Customer = $GLOBALS["db"]["Customer"]();

// -- NEW ROW -- 

//set the customer row cells with data
$Customer["Name"] = "Miss Piggy";
$Customer["EmailAddress"] = "queen@muppets.com";
$Customer["Gender"] = "Female";
$Customer["EmailVerified"] = 1;

//attach customer row events to auto-update our date cells
//it really doesn`t make sense to use callbacks like this. see advanced examples
$Customer->OnBeforeInsert( function($eventObject, $eventArgs){
	$eventObject["DateCreated"] = gmdate("Y-m-d H:i:s T");
});
$Customer->OnBeforeCommit( function($eventObject, $eventArgs){
	$eventObject["DateLastModified"] = gmdate("Y-m-d H:i:s T");
});

// -- AFTER SET FIELDS, BEFORE COMMIT --

//check for errors before we commit
//HasErrors() returns nothing when there are no errors
if( ($errors=$Customer->HasErrors()) ){
	echo "Errors found: ".$errors;	
}
else{
	//commit this row to the database
	$numRowsUpdated = $Customer->Commit();
}

// -- AFTER COMMIT --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/insert-callback.php");
DisplayHelper::PrintOutputTitle();

//create a new Customer row
$Customer = $GLOBALS['db']['Customer']->GetNewRow();

//note- as a shortcut to GetNewRow(), we could simply do:
//$Customer = $GLOBALS['db']['Customer']();

// -- NEW ROW -- 
DisplayHelper::PrintRow($Customer, "New Row");

//set our Customer information
$Customer['Name'] = "Miss Piggy";
$Customer['EmailAddress'] = "queen@muppets.com";
$Customer['Gender'] = "Female";
$Customer['EmailVerified'] = 1;

//attach customer row events to auto-update our date cells 
//it really doesn`t make sense to use callbacks like this. see advanced examples 
$Customer->OnBeforeInsert( function($eventObject, $eventArgs){
	$eventObject['DateCreated'] = gmdate("Y-m-d H:i:s T");
});
$Customer->OnBeforeCommit( function($eventObject, $eventArgs){
	$eventObject['DateLastModified'] = gmdate("Y-m-d H:i:s T");
});

// -- AFTER SET FIELDS, BEFORE COMMIT -- 
DisplayHelper::PrintRow($Customer, "Row After Set Fields, Before Commit");

//check for errors before we commit 
//HasErrors() returns nothing when there are no errors 
if( ($errors=$Customer->HasErrors()) ){
	DisplayHelper::PrintErrors($errors);
}
else{
	//commit this row to the database
	$numRowsUpdated = $Customer->Commit();
}

DisplayHelper::PrintRowsAffected($numRowsUpdated, "Commit()");

// -- AFTER COMMIT -- let's take a peek at all the fields after the commit
DisplayHelper::PrintRow($Customer, "Row After Commit");

DisplayHelper::PrintTemplateBottom();
?>
