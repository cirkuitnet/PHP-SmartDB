<?php
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
 */
/**
 * This class will read a database structure into the SmartDatabase. This structure can then be written to XML using the SmartDatabase::WriteXmlSchema() function
 * 
 * -- db structure layout guide --
 * <code>
 * array(
 * 	"<table name>"=>array(
 * 		"<column name>"=>array(
 * 			"Field"=>"<column name>",
 * 			"Type"=>"<column type>",
 * 			"Null"=>"<YES|NO>",
 * 			"Key"=>"<PRI|UNI|MUL|empty>", //note: PRI=Primary Key, UNI=Unique index, MUL=multiple... seems to mean the same as UNI though
 * 			"Default"=>"<default value|empty>",
 * 			"Extra"=>"<auto_increment|empty>",
 * 			"Collation"=>"<utf8_general_ci|latin1_swedish_ci|empty>", //other collations can easily be added if needed
 * 			"IndexType"=>"<UNIQUE|NONUNIQUE|FULLTEXT|empty>", //UNIQUE when Key=PRI,UNI, or MUL. FULLTEXT for fulltext index
 * 		),
 * 		...(more columns)...
 * 	),
 * 	...(more tables)...
 * )
 * </code>
 * 
 * --- EXAMPLE ---
 * <code>
 * function getDbStructure(){
 * 	return array(
 * 		"Template"=>array(
 * 			"TemplateId"=>array(
 * 				"Field"=>"TemplateId",
 * 				"Type"=>"int(1) unsigned",
 * 				"Null"=>"NO",
 * 				"Key"=>"PRI",
 * 				"Default"=>"",
 * 				"Extra"=>"auto_increment",
 *				"Collation"=>"",
 *				"IndexType"=>"UNIQUE",
 * 			),
 * 			"Name"=>array(
 * 				"Field"=>"Name",
 * 				"Type"=>"varchar(255)",
 * 				"Null"=>"NO",
 * 				"Key"=>"UNI",
 * 				"Default"=>"",
 * 				"Extra"=>"",
 *				"Collation"=>"",
 *				"IndexType=>"",
 * 			),
 * 			"Html"=>array(
 * 				"Field"=>"Html",
 * 				"Type"=>"text",
 * 				"Null"=>"YES",
 * 				"Key"=>"",
 * 				"Default"=>"",
 * 				"Extra"=>"",
 *				"Collation"=>"utf8_general_ci",
 *				"IndexType"=>"FULLTEXT", //i.e. fulltext index on this column
 * 			),
 * 		)
 * );
 * </code>
 * Reads a database structure properties that the SmartDatabase can use to load/write XML schemas
 * @see SmartDatabase::WriteXmlSchema()
 * @package SmartDatabase
 */
class ReadDb_MySQL{
	
	// Hold an instance of the class
    private static $instance;

	//only allow the singleton to access this class
	private function __construct(){
    }

    // The singleton method
    public static function Instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
	
	/**
	 * Returns detailed array with all columns for given table in database, or all tables/databases
	 * Only works with PMA_MYSQL_INT_VERSION >= 50002!
	 * ORIGINAL SOURCE - phpMyAdmin-2.11.11.3 - function PMA_DBI_get_columns_full()
	 * 
	 * <code>
	 * $return = ReadDb_MySQL::Instance()->GetArray($dbManager, "DATABASE_NAME"); //call function from the singleton
	 * 
	 * //the returned array will contain at least this information
	 * $return = array(
	 * 	"TABLE NAME" => array(
	 * 		"COLUMN NAME 1" => array(
 	 * 			"Field"=>"<column name>",
 	 * 			"Type"=>"<column type>",
 	 * 			"Null"=>"<YES|NO>",
 	 * 			"Key"=>"<PRI|UNI|MUL|empty>", //note: PRI=Primary Key, UNI=Unique index, MUL=multiple... seems to mean the same as UNI though
 	 * 			"Default"=>"<default value|empty>",
	 * 			"Extra"=>"<auto_increment|empty>",
 	 * 			"Collation"=>"<utf8_general_ci|latin1_swedish_ci|empty>", //other collations can easily be added if needed
 	 * 			"IndexType"=>"UNIQUE|NONUNIQUE|FULLTEXT|empty",
	 *		),
	 *		"COLUMN NAME 2" => array(
	 *		...etc...
	 *	),
	 * );
	 * </code>
	 *
	 * @param   string  $database   name of database
	 * @param   string  $table      name of table to retrieve columns from
	 * @param   string  $column     name of specific column
	 * @see SmartDatabase::WriteXmlSchema()
	 */
	function GetArray($dbManager, $database = null, $table = null, $column = null){
	    $columns = array();
	
        $sql_wheres = array();
        $array_keys = array();

        // get columns information from information_schema
        if (null !== $database) {
            $sql_wheres[] = '`TABLE_SCHEMA` = \'' . addslashes($database) . '\' ';
        } else {
            $array_keys[] = 'TABLE_SCHEMA';
        }
        if (null !== $table) {
            $sql_wheres[] = '`TABLE_NAME` = \'' . addslashes($table) . '\' ';
        } else {
            $array_keys[] = 'TABLE_NAME';
        }
        if (null !== $column) {
            $sql_wheres[] = '`COLUMN_NAME` = \'' . addslashes($column) . '\' ';
        } else {
            $array_keys[] = 'COLUMN_NAME';
        }

        // for PMA bc:
        // `[SCHEMA_FIELD_NAME]` AS `[SHOW_FULL_COLUMNS_FIELD_NAME]`
        $sql = 'SELECT `TABLE_SCHEMA`		AS `TableSchema`,
             		`TABLE_NAME`		AS `TableName`,
                    `COLUMN_NAME`       AS `Field`,
                    `COLUMN_TYPE`       AS `Type`,
                    `COLLATION_NAME`    AS `Collation`,
                    `IS_NULLABLE`       AS `Null`,
                    `COLUMN_KEY`        AS `Key`,
                    `COLUMN_DEFAULT`    AS `Default`,
                    `EXTRA`             AS `Extra`,
                    `PRIVILEGES`        AS `Privileges`,
                    `COLUMN_COMMENT`    AS `Comment`
               FROM `information_schema`.`COLUMNS`';
        if (count($sql_wheres)) {
            $sql .= "\n" . ' WHERE ' . implode(' AND ', $sql_wheres);
        }

        $dbManager->Query($sql);
        $rows = $dbManager->FetchAssocList();
        
        $results = array();
        foreach($rows as $row){
        	$tableName = $row['TableName'];
        	$columnName = $row['Field'];
        	$results[$tableName][$columnName] = $row;
        }
        
        //lookup indexes on all tables
        foreach($results as $tableName => $colArr){
        	$dbManager->Query('SHOW INDEX FROM `'.$tableName.'`');
        	$rows = $dbManager->FetchAssocList();
        	foreach($rows as $row){
        		$colName = $row['Column_name'];
        		
        		if($row['Index_type'] == "FULLTEXT"){
        			$results[$tableName][$colName]['IndexType'] = "FULLTEXT";
        		}
        		else{ //i.e. $row['Index_type']=="BTREE"
        			if($row['Non_unique']){ //non-unique index
        				$results[$tableName][$colName]['IndexType'] = "NONUNIQUE";
        			}
        			else{ //unique index
	        			if(!$results[$tableName][$colName]['Key']){ //if key is not yet set, we need it
	       					$results[$tableName][$colName]['Key'] = "UNI";
	        			}
	        			$results[$tableName][$colName]['IndexType'] = "UNIQUE";
        			}
        		}
        	}
        }
	
	    return $results;
	}
}