<?
/*
This class will update a database to match a given db structure schema (described below)

********* db structure layout guide *********
array(
	"<table name>"=>array(
		"<column name>"=>array(
			"Field"=>"<column name>",
			"Type"=>"<column type>",
			"Null"=>"<YES|NO>",
			"Key"=>"<PRI|UNI|MUL|empty>", //note: PRI=Primary Key, UNI=Unique index, MUL=multiple... seems to mean the same as UNI though
			"Default"=>"<default value|empty>",
			"Extra"=>"<auto_increment|empty>",
			"Collation=>"<utf8_general_ci|latin1_swedish_ci|empty>" //other collations can easily be added if needed
		),
		...(more columns)...
	),
	...(more tables)...
)
**********************************************

--- EXAMPLE ---
function getDbStructure(){
	return array(
		"Template"=>array(
			"TemplateId"=>array(
				"Field"=>"TemplateId",
				"Type"=>"int(1) unsigned",
				"Null"=>"NO",
				"Key"=>"PRI",
				"Default"=>"",
				"Extra"=>"auto_increment"
			),
			"Name"=>array(
				"Field"=>"Name",
				"Type"=>"varchar(255)",
				"Null"=>"NO",
				"Key"=>"UNI",
				"Default"=>"",
				"Extra"=>""
			),
			"Html"=>array(
				"Field"=>"Html",
				"Type"=>"text",
				"Null"=>"YES",
				"Key"=>"",
				"Default"=>"",
				"Extra"=>""
			),
			"__sqlCreateTable"=>"
				CREATE TABLE `Template` (
					 `TemplateId` int(1) UNSIGNED NOT NULL AUTO_INCREMENT
					,`Name` varchar(255) NOT NULL UNIQUE
					,`Html` text NULL
					,PRIMARY KEY (`TemplateId`)
				);
			"
		)
);

*/
class SyncDb{

	private $_dbManager;

	private $_tablesBackedUp;
	private $_tablesCreated;

	private $_keysToAdd;
	private $_indexesToAdd;
	private $_keysToDrop;
	private $_indexesToDrop;
	private $_fieldsToUpdate;

	private $_lastSyncResults;
	private $_lastSyncResultsMessage = "";

	private function Initialize(){
		$this->_tablesBackedUp = array();
		$this->_tablesCreated = array();
		$this->_keysToAdd = array();
		$this->_indexesToAdd = array();
		$this->_keysToDrop = array();
		$this->_indexesToDrop = array();
		$this->_fieldsToUpdate = array();
		$this->_lastSyncResults = array();
	}

	//options... all booleans
	private $_backupTables;
	private $_doInsert;
	private $_doUpdate;
	private $_doDelete;
	private $_debugMode;

	// Hold an instance of the class
    private static $instance;

	//only allow the singleton to access this class
	private function __construct(){
    }

    // The singleton method
    public static function Instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new SyncDb();
        }

        return self::$instance;
    }

	public function DoSync($dbManager, $newDbStructure, $backupTables=true, $doInsert=true, $doUpdate=true, $doDelete=true, $debugMode=false){
		$this->_dbManager = $dbManager;
		$this->_backupTables = $backupTables;
		$this->_doInsert = $doInsert;
		$this->_doUpdate = $doUpdate;
		$this->_doDelete = $doDelete;
		$this->_debugMode = $debugMode;
		$this->_lastSyncResultsMessage = "";
		$this->Initialize();

		foreach($newDbStructure as $tableName=>$newFields){ //for each table
			$this->_dbManager->Query("show table status like '$tableName'");
			if ($this->_dbManager->NumRows() == 0) { //table does not exist
				$this->Message("Table '<b>$tableName</b>' does not exist.");
				$this->CreateTable($tableName, $newFields);
			}
			else { //table exists
			    $this->Message("Table '<b>$tableName</b>' exists. Comparing all fields and properties...");

				$this->_dbManager->Query("SHOW FULL FIELDS FROM $tableName");
			    $result = $this->_dbManager->FetchAssocList();
			    
				//first- delete fields and 'track' updates, removal of keys, and removal of indexes
				$fieldsDeleted=0;
				foreach ($result as $currentField) { //for each field in this table
					$fieldName = $currentField['Field'];
					if(!isset($newFields[$fieldName])){
						$this->Message(" ---- Field '<b>$fieldName</b>' does not exist in new structure.");
						$fieldsDeleted += $this->DeleteField($tableName, $fieldName); //delete rows in current db table that are not in new db structure
					}
					else {
						//Message(" ---- Field '<b>".$fieldName."</b>' exists. Comparing properties...");
						$this->CheckForFieldUpdates($tableName, $currentField, $newFields[$fieldName]);
						$newDbStructure[$tableName][$fieldName]['verified']=true; //mark field as found in current db table
					}
				}
				$this->_lastSyncResults[$tableName]['deleted']=$fieldsDeleted;

				//drop keys and indexes
				if(count($this->_keysToDrop[$tableName])>0 || count($this->_indexesToDrop[$tableName])>0){

					//update fields that already exist to remove auto_increment so we can drop keys and indexes
					if(is_array($this->_fieldsToUpdate[$tableName])) {
						foreach($this->_fieldsToUpdate[$tableName] as $newField){
							$this->UpdateField($tableName, $newField, false);
						}
					}

					//drop indexes
					if(is_array($this->_indexesToDrop[$tableName])){
						foreach($this->_indexesToDrop[$tableName] as $indexName){
							$this->DropIndex($tableName, $indexName);
						}
					}

					//drop keys
					if(count($this->_keysToDrop[$tableName])>0){
						$this->DropPrimaryKey($tableName);
					}
				}

				//then add indexes on fields that already exist
				if(is_array($this->_indexesToAdd[$tableName])){
					foreach($this->_indexesToAdd[$tableName] as $indexName){
						$this->AddIndex($tableName, $indexName);
					}
				}

  				//add new keys on fields that already exist
				if(is_array($this->_keysToAdd[$tableName])){
					foreach($this->_keysToAdd[$tableName] as $keyName){
						$this->AddPrimaryKey($tableName, $keyName);
					}
				}

				//update fields that already exist to their final structure (includes auto-increment)
				$fieldsUpdated=0;
				if(is_array($this->_fieldsToUpdate[$tableName])){
					foreach($this->_fieldsToUpdate[$tableName] as $newField){
						$fieldsUpdated += $this->UpdateField($tableName, $newField, true);
					}
				}
				$this->_lastSyncResults[$tableName]['updated']=$fieldsUpdated;

				//insert new fields (including keys and indexes on those fields)
				$this->InsertNewFields($newDbStructure, $tableName);
			}
		}

		$this->BuildResults();

		return $this->_lastSyncResultsMessage;
	}

	private function BuildResults(){
		$this->Message();
		$this->Message(" **** TABLE SYNC RESULTS **** ");
		foreach($this->_lastSyncResults as $tableName=>$statuses){
			$this->Message("'<b>$tableName</b>': <b>".$statuses['inserted']."</b> fields inserted - <b>".$statuses['updated']."</b> fields updated - <b>".$statuses['deleted']."</b> fields deleted");
		}
		foreach($this->_tablesCreated as $tableName){
			$this->Message("'<b>$tableName</b>' created.");
		}
	}

	//inserts any fields in $newDbStructure that do not have the ['verified'] flag set
	private function InsertNewFields(&$newDbStructure, $tableName){
		if(array_search($tableName, $this->_tablesCreated)!==false) continue; //table was created by us now. no need to check it

		$numFieldsInserted = 0;
		foreach($newDbStructure[$tableName] as $fieldName=>$properties){
			if(strcmp($fieldName,"__sqlCreateTable")==0) continue; //skip this entry
			if(!isset($properties['verified'])){ //if verified flag is not set, field doesnt exist in db
				$this->Message(" ---- Field '<b>$fieldName</b>' does not exist in database.");
				$numFieldsInserted += $this->InsertField($tableName, $properties);
			}
		}
		$this->_lastSyncResults[$tableName]['inserted']=$numFieldsInserted;
	}


	//compares the two fields, adding the fields to $this->_fieldsToUpdate[], $this->_indexesToDrop[], and/or $this->_keysToDrop[] if necessary
	private function CheckForFieldUpdates($tableName, $currentField, $newField){
		//print_r($currentField);
		//print_r($newField);

		$fieldName = $currentField['Field'];
		$updateField = false;
		$dropIndex = false;
		$dropPrimaryKey = false;
		$addIndex = false;
		$addPrimaryKey = false;

		//go through all properties to see what (if anything) needs updated
	    foreach ($currentField as $property=>$val) {
	    	switch ($property){
	    		//properties to ignore
	    		case "Privileges":
	    		case "Comment":
	    			continue;

	    		//Collation is a special case. Ignore it always unless it's set in the new structure
	    		case "Collation":
	    			if($newField["Collation"] && strcmp($currentField["Collation"], $newField["Collation"])!=0) {
	    				$this->Message(" ---- Field '<b>$fieldName</b>' :: Property '<b>$property</b>' difference. Current: '<b>".($currentField[$property]!==""?$currentField[$property]:"(not set)")."</b>', New: '<b>".($newField[$property]!==""?$newField[$property]:"(not set)")."</b>'");
						$updateField=true;
	    			}
	    			continue;

	    		default:
		    		if (strcmp($currentField[$property], $newField[$property])!=0) {
			        	if($property=="Key" && $currentField[$property]=="MUL" && $newField[$property]=="UNI") continue; //special case where MUL==UNI
		
			            $this->Message(" ---- Field '<b>$fieldName</b>' :: Property '<b>$property</b>' difference. Current: '<b>".($currentField[$property]!==""?$currentField[$property]:"(not set)")."</b>', New: '<b>".($newField[$property]!==""?$newField[$property]:"(not set)")."</b>'");
						$updateField=true;
		
						//check for primary/unique key changes
						if($property=="Key"){ //remove primary key
							if($currentField[$property]=="PRI" && $newField[$property]!="PRI")
								$dropPrimaryKey = true;
							if(($currentField[$property]=="UNI" || $currentField[$property]=="MUL") && $newField[$property]!="UNI")
								$dropIndex = true;
							if($currentField[$property]!="PRI" && $newField[$property]=="PRI")
								$addPrimaryKey = true;
							if(($currentField[$property]!="UNI" && $currentField[$property]!="MUL") && $newField[$property]=="UNI")
								$addIndex = true;
						}
			        }
			        
	    	} //end switch
	    } //end foreach

		if($updateField){ //regular update
			$this->_fieldsToUpdate[$tableName][] = $newField;
		}
		if($dropIndex){ //remove index
			$this->_indexesToDrop[$tableName][] = $fieldName;
		}
		if($dropPrimaryKey){ //remove primary key
			$this->_keysToDrop[$tableName][] = $fieldName;
		}
		if($addIndex){ //remove index
			$this->_indexesToAdd[$tableName][] = $fieldName;
		}
		if($addPrimaryKey){ //remove primary key
			$this->_keysToAdd[$tableName][] = $fieldName;
		}
	}

	/************* SQL ***************/
	private function BackupTable($tableName){
		if($this->_backupTables == false ) {
			$this->Message(" -------- <i>Skipping backing up table</i>");
			return 0;
		}
		$backupTableName = "backup_".$tableName."_".date('Ymd_His');
	    $this->Message("-------- <i>Backing up table '$tableName' to '$backupTableName'...</i>");
		$this->Query("CREATE TABLE `$backupTableName` SELECT * FROM $tableName");
		return 1;
	}

	private function AddPrimaryKey($tableName, $fieldName){
		if($this->_doInsert == false ) {
			$this->Message(" -------- <i>Skipping adding primary key</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Adding Primary Key '$fieldName' on Table '$tableName'</i>");
		$this->Query("ALTER TABLE `$tableName` ADD PRIMARY KEY (`$fieldName`)");
		return 1;
	}

	private function AddIndex($tableName, $fieldName){
		if($this->_doInsert == false ) {
			$this->Message(" -------- <i>Skipping adding index</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Adding Index '$fieldName' on Table '$tableName'</i>");
		$this->Query("ALTER TABLE `$tableName` ADD INDEX (`$fieldName`)");
		return 1;
	}

	private function DropPrimaryKey($tableName){
		if($this->_doDelete == false ) {
			$this->Message(" -------- <i>Skipping droping primary key</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Dropping Primary Key on Table '$tableName'</i>");
		$this->Query("ALTER TABLE `$tableName` DROP PRIMARY KEY");
		return 1;
	}

	private function DropIndex($tableName, $fieldName){
		if($this->_doDelete == false ) {
			$this->Message(" -------- <i>Skipping dropping index</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Dropping Index '$fieldName' on Table '$tableName'</i>");
		$this->Query("ALTER TABLE `$tableName` DROP INDEX `$fieldName`");
		return 1;
	}

	private function CreateTable($tableName, $fields){
		if($this->_doInsert == false ) {
			$this->Message(" -------- <i>Skipping creating table</i>");
			return 0;
		}
		$this->Message(" -------- <i>Creating Table '$tableName'</i>");
		$this->Query($fields['__sqlCreateTable']);
		$this->_tablesCreated[] = $tableName;
		return 1;
	}

	private function InsertField($tableName, $newDbField){
		if($this->_doInsert == false ) {
			$this->Message(" -------- <i>Skipping inserting field</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Inserting '".$newDbField['Field']."'".($newDbField['Key']=="PRI"?" as Primary Key":"")."</i>");
		$null = ($newDbField['Null']=="YES"?"NULL":"NOT NULL");
		$default = ($newDbField['Default']!="" && (strcasecmp($newDbField['Type'], 'text')!=0) ? "DEFAULT '{$newDbField['Default']}'":""); // blob/text types cant have default values in mysql
		$unique = ($newDbField['Key']=="UNI"?"UNIQUE":"");
		$primary = ($newDbField['Key']=="PRI"?", ADD PRIMARY KEY ({$newDbField['Field']})":"");
		$this->Query("ALTER TABLE `$tableName` ADD `{$newDbField['Field']}` {$newDbField['Type']} $null $default {$newDbField['Extra']} $unique $primary");
		return 1;
	}

	private function DeleteField($tableName, $fieldName){
		if($this->_doDelete == false ) {
			$this->Message(" -------- <i>Skipping deleting field</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		$this->Message(" -------- <i>Deleting '$fieldName'</i>");
		$this->Query("ALTER TABLE `$tableName` DROP `$fieldName`");
		return 1;
	}

	//does not update indexes/primary keys. use AddPrimaryKey and AddIndex for that...
	private function UpdateField($tableName, $newDbField, $includeAutoIncrement=true){
		if($this->_doUpdate == false ) {
			if($includeAutoIncrement) $this->Message(" -------- <i>Skipping updating field</i>");
			return 0;
		}
		$this->BackupTableIfNeeded($tableName);
		if($includeAutoIncrement) $this->Message(" -------- <i>Updating '".$newDbField['Field']."'</i>");

		$collation = $this->GetCollationSql($newDbField);
		$null = ($newDbField['Null']=="YES"?"NULL":"NOT NULL");
		$default = ($newDbField['Default']!="" && (strcasecmp($newDbField['Type'], 'text')!=0) ? "DEFAULT '{$newDbField['Default']}'":""); // blob/text types cant have default values in mysql
		if($includeAutoIncrement){
			$auto_increment = $newDbField['Extra'];
		}
		$this->Query("ALTER TABLE `$tableName` CHANGE `{$newDbField['Field']}` `{$newDbField['Field']}` {$newDbField['Type']} $collation $null $default $auto_increment");
		return 1;
	}
	
	private function GetCollationSql($dbField){
		if(!$dbField["Collation"]) return '';
		
		//forced character set
		//reference: http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html
		switch($dbField["Collation"]){
			case "utf8_general_ci":
				return "CHARACTER SET utf8 COLLATE utf8_general_ci";

			case "latin1_swedish_ci":
				return "CHARACTER SET latin1 COLLATE latin1_swedish_ci";

			//no need for more yet. also may need to add a collation option
			default:
				throw new Exception("Unsupported Collation '".$dbField["Collation"]."' set for column: '".$dbField['Field']."'");
		}
	}

	/************* UTIL ***************/
	private function BackupTableIfNeeded($tableName){
		if(array_search($tableName,$this->_tablesBackedUp)===false){ //table not yet backed up
			$this->BackupTable($tableName, $tableName);
			array_push($this->_tablesBackedUp, $tableName);
		}
	}
	private function Query($sql){
		if($this->_debugMode){ $this->Message(">>> SQL: $sql"); }
		else{ $this->_dbManager->Query($sql); }
	}
	private function Message($str=""){
		//echo $str."<br>\n";
		$this->_lastSyncResultsMessage .= $str."<br>\n";
	}
}
?>