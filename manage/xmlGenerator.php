<?
/*!
 * PHP SmartDb
 * http://www.phpsmartdb.com/
 *
 * Copyright 2011, Cirkuit Networks
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://www.phpsmartdb.com/license
 */
/**
 * @package SmartDatabase
 * Eventually, this script will be an HTML UI for creating/managing XML database schemas.
 * It will eventually be able to load and save XML schemas, and easily update them with a nice web UI.
 * It is far from completed. Currently it just programmatically defines a database structure's structure.
 * Must to do here.
 * @ignore
 */
$smartDbDir = dirname(__FILE__)."/..";
require_once($smartDbDir."/SmartDatabase.php");

//$db = new SmartDatabase(null, $smartDbDir."/tests/test.xml");
//echo $db->WriteXmlSchema("/home/lonestar/test1.xml");


$schemaDb = new SmartDatabase();



$sortOrder = 1;
$databaseTable = new SmartTable("Database");

$column = new SmartColumn("DatabaseId");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = true;
$column->IsPrimaryKey = true;
$column->IsAutoIncrement = true;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$databaseTable->AddColumn($column);

$column = new SmartColumn("DatabaseName");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = true;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$databaseTable->AddColumn($column);

$schemaDb->AddTable($databaseTable);





$sortOrder = 1;
$tableTable = new SmartTable("Table");

$column = new SmartColumn("TableId");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = true;
$column->IsPrimaryKey = true;
$column->IsAutoIncrement = true;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$tableTable->AddColumn($column);

$column = new SmartColumn("OwnerDatabaseId");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$tableTable->AddColumn($column);

$column = new SmartColumn("TableName");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$tableTable->AddColumn($column);

$column = new SmartColumn("ExtendedByClassName");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$tableTable->AddColumn($column);

$column = new SmartColumn("AutoCommit");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$tableTable->AddColumn($column);

$schemaDb->AddTable($tableTable);






$sortOrder = 1;
$columnTable = new SmartTable("Column");

$column = new SmartColumn("ColumnId");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = true;
$column->IsPrimaryKey = true;
$column->IsAutoIncrement = true;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("OwnerTableId");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("ColumnName");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet =  true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = true;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("DisplayName");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet =  true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("DataType");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 255;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet =  true;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = "varchar";
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = true;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("MinSize");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("MaxSize");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("AllowSet");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 1;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("TrimAndStripTagsOnSet");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("AllowGet");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 1;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("AllowLookup");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 1;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("AllowGetAll");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("DefaultValue");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1023;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("IsRequired");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("IsRequiredMessage");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1023;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("RegexCheck");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1023;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("RegexFailMessage");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1023;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("DefaultFormType");
$column->DisplayName = null;
$column->DataType = "varchar";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 31;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = "text";
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("IsUnique");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("SortOrder");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("IsPrimaryKey");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);

$column = new SmartColumn("IsAutoIncrement");
$column->DisplayName = null;
$column->DataType = "binary";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = 1;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = 0;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "checkbox";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);
/*
$column = new SmartColumn("ForeignKeyID");
$column->DisplayName = null;
$column->DataType = "int";
$column->IsDateColumn = false;
$column->MinSize = null;
$column->MaxSize = null;
$column->AllowGet = true;
$column->AllowSet =  true;
$column->TrimAndStripTagsOnSet = false;
$column->AllowLookup = true;
$column->AllowGetAll =  true;
$column->DefaultValue = null;
$column->IsUnique = false;
$column->IsPrimaryKey = false;
$column->IsAutoIncrement = false;
$column->DefaultFormType = "text";
$column->IsRequired = false;
$column->IsRequiredMessage = null;
$column->RegexCheck = null;
$column->RegexFailMessage = null;
$column->SortOrder = $sortOrder++;
$columnTable->AddColumn($column);
*/
$schemaDb->AddTable($columnTable);


//add column relations
$schemaDb['Table']['OwnerDatabaseId']->AddRelation('Database', 'DatabaseId');
$schemaDb['Column']['OwnerTableId']->AddRelation('Table', 'TableId');


print_nice($schemaDb);


if($_GET['table']){
	$tableName = $_GET['table'];
	if($_GET['column']){ //show column
		$columnName = $_GET['column'];
		$row = new SmartRow('Column', $schemaDb, array("OwnerTableName"=>$tableName, "ColumnName"=>$columnName));//, $columnName);
		$cells = $row->GetAllCells();
		foreach($cells as $columnName=>$Cell){
			echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject()."<br>\n";
		}
	}
	else{ //show table
		$row = new SmartRow('Table', $schemaDb);//, $tableName);
		$cells = $row->GetAllCells();
		foreach($cells as $columnName=>$Cell){
			echo $Cell->GetFormObjectLabel().' '.$Cell->GetFormObject()."<br>\n";
		}
	}
}
else{ //nothing specified to edit. show tables
	$allTableRows = $schemaDb['Table']->GetAllRows();
	echo '<h2>Select a table to edit</h2>';
	foreach($allTableRows as $Row){
		echo '<a href=?table="'.$Row['TableName'].'"><br>';
	}
}