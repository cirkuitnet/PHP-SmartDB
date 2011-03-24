<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("lookup-update-by-email.php");
DisplayHelper::PrintTitle("lookup-update-by-email.php");
DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//lookup the Customer row with the EmailAddress = "jfrost@winter.com"
$Customer = $GLOBALS["db"]["Customer"]->LookupRow(array(
	"EmailAddress" => "jfrost@winter.com"
));

//note- instead of LookupRow() on the table, we can LookupRow() from the column:
//$Customer
//	= $GLOBALS["db"]["Customer"]["EmailAddress"]->LookupRow("jfrost@winter.com");

//lets print a message if the customer does not exist
if(!$Customer->Exists()){
	echo "Customer with EmailAddress=jfrost@winter.com not found to update!";
}

// -- BEFORE UPDATE AND COMMIT --

//update the customer`s gender (we will just toggle it every time)
if($Customer["Gender"]() == "Male"){
	$Customer["Gender"] = "Female";
}
else{
	$Customer["Gender"] = "Male";
}

// -- AFTER UPDATE, BEFORE COMMIT --

//check for errors before we commit
//HasErrors() returns nothing when there are no errors
if( ($errors=$Customer->HasErrors()) ){
	echo "Errors found: ".$errors;	
}
else{
	//commit this row to the database
	$numRowsUpdated = $Customer->Commit();
}

//-- AFTER UPDATE AND COMMIT --
');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/lookup-update-by-email.php");
DisplayHelper::PrintOutputTitle();

//lookup the Customer row with the EmailAddress = "jfrost@winter.com"
$Customer = $GLOBALS['db']['Customer']->LookupRow(array(
	'EmailAddress' => "jfrost@winter.com"
));

//note- instead of LookupRow() on the table above, we LookupRow from the column:
//$Customer = $$GLOBALS['db']['Customer']['EmailAddress']->LookupRow("jfrost@winter.com");

//lets print a message if the customer does not exist
if(!$Customer->Exists()){
	DisplayHelper::PrintErrors("Customer with EmailAddress=jfrost@winter.com not found to update!");
}

// -- BEFORE UPDATE AND COMMIT -- show that the row exists in the database and the CustomerId is set
DisplayHelper::PrintRow($Customer, "Row Before Update And Commit");

//update the customer's gender (we will just toggle it every time)
if($Customer['Gender']() == "Male"){ //"$Customer['Gender']()" is shorthand for "$Customer['Gender']->GetValue()"
	$Customer['Gender'] = "Female";
}
else{
	$Customer['Gender'] = "Male";
}

// -- AFTER UPDATE, BEFORE COMMIT --
DisplayHelper::PrintRow($Customer, "Row After Update, Before Commit");

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

// -- AFTER UPDATE AND COMMIT -- let's take a peek at all the fields after the commit
DisplayHelper::PrintRow($Customer, "Row After Update and Commit");

DisplayHelper::PrintTemplateBottom();
?>
