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
 * Manages a connection to the database and stores information about its table structure
 * @package SmartDatabase
 */
/**
 */
if (strnatcmp( ($version=phpversion()), '5.3.0') < 0){ //check php constraints. eventually we'll get rid of this when everyone is 5.3.0+
	throw new Exception("This version of SmartDatabase only works with PHP versions 5.3.0 and newer. You are using PHP version $version");
}
require_once(dirname(__FILE__).'/SmartTable.php');
require_once(dirname(__FILE__).'/SmartRow.php');
/**
 * @package SmartDatabase
 */
class SmartDatabase implements ArrayAccess, Countable{
	/**
	 * @var DbManager The DbManager instance, passed in the class constructor
	 * @see SmartDatabase::__construct()
	 */
	public $DbManager;

	/**
	 * Development mode toggle. When true, does extra verifications (ie data types, columns, etc) that are a must for development, but may slow things down a bit when running in production.
	 *
	 * In DEV mode, extra type checking takes place to verify you're setting values correctly. This
	 * helps with mostly with debugging, but also helps to avoid data corruption if you do something wrong. Once
	 * things seem to be up and going pretty well, you can set this to false for some performance improvement.
	 * @var bool $DEV_MODE
	 * @todo fully implement this. already has some stuff in SmartCell and SmartRow
	 */
	public $DEV_MODE = true;

	/**
	 * @var bool if true and in $DEV_MODE, warnings will be shown for like missing classes and etc.
	 */
	public $DEV_MODE_WARNINGS = true;

	/**
	 * @param DbManager $dbManager [optional] The DbManager instance that will be used to perform operations on the actual database. If null, no database operations can be performed (use for 'in-memory' instances)
	 * @param string $xmlSchemaFilePath [optional] The database schema file to load. If left null, you will need to either build the database yourself useing ->AddTable() or load a schema from XML using ->LoadSchema()
	 * @return SmartDatabase
	 */
	public function __construct(DbManager $dbManager=null, $xmlSchemaFilePath=null){
		$this->DbManager = $dbManager;
		if($xmlSchemaFilePath) $this->LoadXmlSchema($xmlSchemaFilePath);
	}

/////////////////////////////// Table Management ///////////////////////////////////
	/**
	 * @var array Key is the table name. Value is the Table instance
	 * @see SmartDatabase::__construct()
	 * @ignore
	 */
	protected $_tables = array();

	/**
	 * Returns an assoc of all tables. The returned array's key=$tableName, value=$Table
	 * @return array an assoc of all tables. The returned array's key=$tableName, value=$Table
	 * @see SmartTable
	 */
	public function GetAllTables(){
		return $this->_tables;
	}

	/**
	 * Returns the Table instance matching the given $tableName. An exception is thrown if the table does not exist. Shortcut: use array notation- $databse['YOUR_TABLE_NAME']
	 * @param string $tableName The name of the table to get.
	 * @return SmartTable The Table instance matching the given $tableName. An exception is thrown if the table does not exist.
	 */
	public function GetTable($tableName){
		if(!$this->_tables[$tableName]) throw new Exception("Invalid table: '$tableName'");
		return $this->_tables[$tableName];
	}

	/**
	 * Adds a table to be managed by this Database. Replaces any table with the same name
	 * @param SmartTable $Table
	 */
	public function AddTable(SmartTable $Table){
		$Table->Database = $this; //make $this Databsae the column's Database
		$this->_tables[$Table->TableName] = $Table; //save this table with the table name as key
	}

	/**
	 * Removes a table from being managed by this Database.
	 * @param string $tableName The name of the table to remove
	 */
	public function RemoveTable($tableName){
		if(!$this->_tables[$tableName]) throw new Exception("Invalid table: '$tableName'");

		$this->_tables[$tableName]->Database = null; //$this database is no longer the table's database
		unset($this->_tables[$tableName]);
	}

	/**
	 * Removes all tables from being managed by this Database.
	 */
	public function RemoveAllTables(){
		foreach($this->_tables as $tableName=>$Table){
			$this->RemoveTable($tableName);
		}
	}

	/**
	 * Returns true if the table exists, false otherwise.
	 * @param string $tableName the name of the table to look for
	 * @return bool true if the table exists, false otherwise.
	 */
	public function TableExists($tableName){
		return ($this->_tables[$tableName] != null);
	}

/////////////////////////////// LoadXmlSchema ///////////////////////////////////
	/**
	 * Loads a database schema from an XML file. This schema will replace any tables that are currently being managed by the Database instance.
	 * <p><b>$options is an assoc-array, as follows:</b></p>
	 * <code>
	 * $options = array(
	 * 	'clear-current-schema'=false, //if set to true, all tables currently loaded within this instance will be removed (unmanaged. the actual table still exists in the real db). if false, the given $xmlSchemaFilePath is simply added to whatever is currently in this database instance (tables/properties with the same name are overwritten with the new schema)
	 * );
	 * </code>
	 * @param string $xmlSchemaFilePath The path of the XML database schema file to load.
	 * @param array $options [optional] See description
	 */
	public function LoadXmlSchema($xmlSchemaFilePath, array $options=null){
		if($options['clear-current-schema']){
			$this->RemoveAllTables(); //clear old tables... new database schema coming in.
		}

		if(!file_exists($xmlSchemaFilePath)) throw new Exception("XML file does not exist at location: '$xmlSchemaFilePath'");

		$xsdFilePath = dirname(__FILE__).'/XmlSchema.xsd'; //default schema path
		if(!file_exists($xsdFilePath)) throw new Exception("XmlSchema.xsd file does not exist at location: '{$xsdFilePath}'");

		//validate the xml with the schema
		if(!$this->IsXmlFileValid($xmlSchemaFilePath, $xsdFilePath)) throw new Exception("XML file ($xmlSchemaFilePath) does not validate with XSD schema ($xsdFilePath)");

		$xmlAssoc = $this->GetAssocFromXml($xmlSchemaFilePath, $xsdFilePath);
		$this->BuildDatabase($xmlAssoc);
	}

	/**
	 * Gets the current database schema in XML format and optionally writes the XML to a file (specified in $xmlSchemaFilePath). The XML string is returned regardless.
	 * @param string $xmlSchemaFilePath [optional] If provided, the new XML will overwrite the file at the specified path
	 * @return string The current database schema in XML format
	 * @see ReadDb_MySQL::GetArray()
	 */
	public function WriteXmlSchema($xmlSchemaFilePath=null){
		/**
		 * @ignore
		 */
		function bs($bool){ //bs-"boolean string"
			return ($bool ? "true" : "false");
		}

		$xml = '<?xml version="1.0" standalone="yes"?>'."\n";
		$xml .= '<CodeGenSchema xmlns="http://www.w3.org/2001/XMLSchema">'."\n";

		$foreignKeysByName = array();
		$foreignKeysByFkId = array();
		$fkId = 1;

		foreach($this->_tables as $tableName=>$Table){
			$xml .= '	<Class';
			if($Table->ExtendedByClassName){
				$xml .= ' Name="'.$Table->ExtendedByClassName.'"';
			}
			$xml .= ">\n";
			$xml .= '		<Database';
			$xml .= ' TableName="'.$Table->TableName.'"';
			if($Table->GetInheritedTableName()){
				$xml .= ' InheritsTableName="'.$Table->GetInheritedTableName().'"';
			}
			if($Table->IsAbstract){
				$xml .= ' IsAbstract="'.bs($Table->IsAbstract).'"';
			}
			if($Table->AutoCommit){
				$xml .= ' CommitChangesAutomatically="'.bs($Table->AutoCommit).'"';
			}
			$xml .= ">\n";
			$columns = $Table->GetAllColumns();
			foreach($columns as $columnName=>$Column){
				$xml .= '			<Field';
				$xml .= ' Name="'.$Column->ColumnName.'"';

				$aliasesArr = $Column->GetAliases(array('names-only'=>true));
				if( $aliasesArr && ($aliases = implode(",", $aliasesArr)) ){ //only print if we need to
					$xml .= ' Aliases="'.$aliases.'"';
				}
				
				if($Column->DisplayName && $Column->DisplayName != $Column->ColumnName){ //only set display name if it isnt the same as the column name
					$xml .= ' DisplayName="'.htmlspecialchars($Column->DisplayName).'"';
				}

				if (stripos($Column->DataType,"enum")===0){ //enum data type
					if(count($Column->PossibleValues) <= 0) throw new Exception("'{$Column->DataType}' column type has no PossibleValues set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$xml .= ' DataType="'.$Column->DataType."('".htmlspecialchars(implode("','", $Column->PossibleValues))."')\"";
				}
				else { //non-enum data type
					$xml .= ' DataType="'.$Column->DataType.'"';
					if( $Column->PossibleValues && ($possibleVals = implode(",", $Column->PossibleValues)) ){ //only print if we need to
						$xml .= ' PossibleValues="'.htmlspecialchars($possibleVals).'"'; //only save PossibleValues to its own attribute if not an enum data type... otherwise, it's part of the data type
					}
				}

				if($Column->Collation){
					$xml .= ' Collation="'.$Column->Collation.'"';
				}
				if($Column->MinSize){
					$xml .= ' MinSize="'.$Column->MinSize.'"';
				}
				if($Column->MaxSize){
					if((strpos($Column->DataType,"int")!==false) && $Column->MaxSize==1){ 
						//dont set a MaxSize for int with maxsize=1. this field doesn't matter anyway. it's for left-zero padding
					}
					else if( (strpos($Column->DataType,"binary")!==false) && $Column->MaxSize==1){
						//dont set MaxSize for binary with maxsize=1. this is default for binary columns
					}
					else if($Column->DataType === "decimal" && $Column->MaxSize=="14,4"){
						//dont set MaxSize for decimal with maxsize="14,4". this is default for binary columns
					}
					else{ //filters pass. print MaxSize
						$xml .= ' MaxSize="'.$Column->MaxSize.'"';
					}
				}
				if($Column->AllowGet == false){ //true is default, so only print if false
					$xml .= ' AllowGet="'.bs($Column->AllowGet).'"';
				}
				if($Column->AllowSet == false){ //true is default, so only print if false
					$xml .= ' AllowSet="'.bs($Column->AllowSet).'"';
				}
				if($Column->TrimAndStripTagsOnSet){
					$xml .= ' TrimAndStripTagsOnSet="'.bs($Column->TrimAndStripTagsOnSet).'"';
				}
				if($Column->AllowLookup == false){ //true is default, so only print if false
					$xml .= ' AllowLookup="'.bs($Column->AllowLookup).'"';
				}
				if($Column->AllowGetAll == false){ //true is default, so only print if false
					$xml .= ' AllowGetAll="'.bs($Column->AllowGetAll).'"';
				}
				if($Column->DefaultValue || $Column->DefaultValue===0 || $Column->DefaultValue==="0"){
					$xml .= ' DefaultValue="'.htmlspecialchars($Column->DefaultValue).'"';
				}
				if($Column->IsRequired && !($Column->IsPrimaryKey && $Column->IsAutoIncrement)){ //primary key's 
					$xml .= ' InputRequired="'.bs($Column->IsRequired).'"';
				}
				if($Column->IsRequiredMessage){
					$xml .= ' InputEmptyError="'.htmlspecialchars($Column->IsRequiredMessage).'"';
				}
				if($Column->RegexCheck){
					$xml .= ' InputRegexCheck="'.htmlspecialchars($Column->RegexCheck).'"';
				}
				if($Column->RegexFailMessage){
					$xml .= ' InputRegexFailError="'.htmlspecialchars($Column->RegexFailMessage).'"';
				}
				if($Column->DefaultFormType && $Column->DefaultFormType != "text"){ //"text" is default, so only print if something else
					$xml .= ' FormType="'.$Column->DefaultFormType.'"';
				}
				if($Column->IsUnique || $Column->IsPrimaryKey){
					$xml .= ' IsUnique="'.bs($Column->IsUnique || $Column->IsPrimaryKey).'"';
				}
				if($Column->FulltextIndex){
					$xml .= ' FulltextIndex="'.bs($Column->FulltextIndex).'"';
				}
				if($Column->SortOrder){
					//SortOrder not yet implemented
					//$xml .= ' SortOrder="'.$Column->SortOrder.'"';
				}
				if($Column->IsPrimaryKey){
					$xml .= ' PrimaryKey="'.bs($Column->IsPrimaryKey).'"';
				}
				if($Column->IsAutoIncrement){
					$xml .= ' AutoIncrement="'.bs($Column->IsAutoIncrement).'"';
				}

				//handle related columns (ie foreign keys)
				if(count($relations=$Column->GetRelations()) > 0){
					if(!$foreignKeysByName[$tableName][$columnName]){
						$foreignKeysByName[$tableName][$columnName] = $fkId;
						$foreignKeysByFkId[$fkId][$tableName][$columnName] = true;
						foreach($relations as $fkTableName=>$cols){
							foreach($cols as $fkColumnName=>$nothingYet){
								$foreignKeysByName[$fkTableName][$fkColumnName] = $fkId;
								$foreignKeysByFkId[$fkId][$fkTableName][$fkColumnName] = true;
							}
						}
						$fkId++;
					}
					$xml .= ' ForeignKeyID="'.$foreignKeysByName[$tableName][$columnName].'"';
				}

				$xml .= ' />'."\n";
			}
			$xml .= '		</Database>'."\n";
			$xml .= '	</Class>'."\n";
		}

		foreach($foreignKeysByFkId as $fkId=>$tables){
			$xml .= '	<ForeignKey ForeignKeyID="'.$fkId.'">'."\n";
			foreach($tables as $tableName=>$columns){
				foreach($columns as $columnName=>$nothingYet){
					$xml .= '		<Relation TableName="'.$tableName.'" FieldName="'.$columnName.'" />'."\n";
				}
			}
			$xml .= '	</ForeignKey>'."\n";
		}

		$xml .= '</CodeGenSchema>';

		//write xml to file?
		if($xmlSchemaFilePath){
			file_put_contents($xmlSchemaFilePath, $xml);
		}

		return $xml;
	}



	/**
	 * Builds the database from the given, validated, XML Assoc
	 */
	private function BuildDatabase(array $xmlAssoc){
		//database table/column structure
		$allXmlClasses = $xmlAssoc['CodeGenSchema'][0]['v']['Class'];
		$allInheritedTables = array(); //key is the table name, value is the inhertied table name. (ie key table inherits value table)
		if(is_array($allXmlClasses)){
			foreach($allXmlClasses as $xmlClass){
				$xmlDatabase = $xmlClass['v']['Database'][0];
				$tableName = $xmlDatabase['a']['TableName'];

				$table = new SmartTable($tableName);

				$table->ExtendedByClassName = $xmlClass['a']['Name'];
				$table->AutoCommit = (strtolower($xmlDatabase['a']['CommitChangesAutomatically']) === 'true' ? true : false);
				$table->IsAbstract = (strtolower($xmlDatabase['a']['IsAbstract']) === 'true' ? true : false);

				//track inherited tables. will handle adding inherited columns after all regular tables/column have been added
				if( ($inheritsTableName = $xmlDatabase['a']['InheritsTableName']) ){
					$allInheritedTables[$tableName] = $inheritsTableName;
				}

				$table->AutoRefresh = false; //optimization. we'll manually call $table->Refresh() after all columns have been added

				$xmlColumns = $xmlDatabase['v']['Field'];
				foreach($xmlColumns as $xmlColumn){
					$xmlColumnName = $xmlColumn['a']['Name'];

					$column = new SmartColumn($xmlColumnName);

					//add column aliases
					if( ($aliases = $xmlColumn['a']['Aliases']) ){ //if the "Aliases" attribute exists..
						if( ($aliases = explode(',', $aliases)) ){ //convert CSV string into array or FALSE if it's an empty string (false will avoid the foreach loop)
							foreach($aliases as $alias){
								$alias = trim($alias);
								if($alias) $column->AddAlias($alias);
							}
						}
					}

					if( stripos($xmlColumn['a']['DataType'], "enum") === 0 ){ //enum data type
						$column->DataType = "enum";
						preg_match_all("/'(.*?)'/", $xmlColumn['a']['DataType'], $matches); //parse out the enum values
						$column->PossibleValues = $matches[1];
					}
					else $column->DataType = strtolower($xmlColumn['a']['DataType']); //non-enum data type
					
					//add column possible values. if set and the data type is enum, these will be used as the only possible values instead of the enum values (ie you can use a subset of the possible values specified in the enum data type) 
					if( ($possibleValues = trim($xmlColumn['a']['PossibleValues'])) ){ //if the "PossibleValues" attribute exists..
						if( ($possibleValues = explode(',', $possibleValues)) ){ //convert CSV string into array or FALSE if it's an empty string
							$column->PossibleValues = $possibleValues;
						}
					}

					$column->IsDateColumn = (stripos($column->DataType, "date") !== false || $column->DataType==='timestamp');
					$column->DisplayName = (!empty($xmlColumn['a']['DisplayName']) ? $xmlColumn['a']['DisplayName'] : $xmlColumnName);
					$column->Collation = $xmlColumn['a']['Collation'];
					$column->MinSize = $xmlColumn['a']['MinSize'];
					$column->MaxSize = $xmlColumn['a']['MaxSize'];
					$column->AllowGet = (strtolower($xmlColumn['a']['AllowGet']) === 'false' ? false : true); //default value is true
					$column->AllowSet = (strtolower($xmlColumn['a']['AllowSet']) === 'false' ? false : true); //default value is true
					$column->TrimAndStripTagsOnSet = (strtolower($xmlColumn['a']['TrimAndStripTagsOnSet']) === 'true' ? true : false);
					$column->AllowLookup =(strtolower($xmlColumn['a']['AllowLookup']) === 'false' ? false : true); //default value is true
					$column->AllowGetAll = (strtolower($xmlColumn['a']['AllowGetAll']) === 'false' ? false : true); //default value is true
					$column->DefaultValue = $xmlColumn['a']['DefaultValue'];
					$column->IsUnique = (strtolower($xmlColumn['a']['IsUnique']) === 'true' ? true : false);
					$column->FulltextIndex = (strtolower($xmlColumn['a']['FulltextIndex']) === 'true' ? true : false);
					$column->IsPrimaryKey = (strtolower($xmlColumn['a']['PrimaryKey']) === 'true' ? true : false);
					$column->IsAutoIncrement = (strtolower($xmlColumn['a']['AutoIncrement']) === 'true' ? true : false);
					$column->DefaultFormType = ($xmlColumn['a']['FormType'] ? $xmlColumn['a']['FormType'] : "text"); //"text" is default value
					$column->IsRequired = (strtolower($xmlColumn['a']['InputRequired']) === 'true' ? true : false);
					$column->IsRequiredMessage = $xmlColumn['a']['InputEmptyError'];
					$column->RegexCheck = $xmlColumn['a']['InputRegexCheck'];
					$column->RegexFailMessage = $xmlColumn['a']['InputRegexFailError'];
					$column->SortOrder = $xmlColumn['a']['SortOrder'];

					$table->AddColumn($column);
				}
				$table->Refresh(); //optimization by doing this manually instead of using $table->AutoRefresh
				$table->AutoRefresh = true; //re-enable auto refresh

				$this->AddTable($table);
			} //foreach($allXmlClasses

		} //is_array($allXmlClasses)

		//related columns
		$allXmlForeignKeys = $xmlAssoc['CodeGenSchema'][0]['v']['ForeignKey'];
		if(is_array($allXmlForeignKeys)){
			foreach($allXmlForeignKeys as $foreignKey){
				$xmlRelations = $foreignKey['v']['Relation'];
				$relations = array();
				foreach($xmlRelations as $xmlRelation){ //compile all in this relation
					$tableName = $xmlRelation['a']['TableName'];
					$columnName = $xmlRelation['a']['FieldName'];
					$relations[$tableName][$columnName] = true;
				}

				foreach($relations as $tableName1=>$columnNameArray1){ //set all in this relation
					foreach($columnNameArray1 as $columnName1=>$nothing){
						$column1 = $this->GetTable($tableName1)->GetColumn($columnName1);

						foreach($relations as $tableName2=>$columnNameArray2){
							foreach($columnNameArray2 as $columnName2=>$nothing){
								if($tableName1 !== $tableName2 || $columnName1 !== $columnName2){
									$column1->AddRelation($tableName2, $columnName2);
								}
							}
						}

					}
				}

			}
		}

		//handle inherited tables
		$queuedTables=array();
		$completedTables=array();
		foreach($allInheritedTables as $tableName=>$inheritTablename){
			$this->BuildInheritedTables($tableName, $allInheritedTables, $queuedTables, $completedTables);
		}

	}

	/**
	 * Makes sure inherited tables get built in the correct order. Also assures there are no "inheritance loops"
	 * @param string $tableName
	 * @param array $allInheritedTables
	 * @param array $queuedTables [optional] Leave empty. Only used for recursions.
	 * @param array $completedTables [optional]  Leave empty. Only used for recursions.
	 * @return
	 */
	private function BuildInheritedTables($tableName, &$allInheritedTables, &$queuedTables, &$completedTables){
		if($completedTables[$tableName]) return; //this table is already ready to go. move along

		if($queuedTables[$tableName]) throw new Exception("Table inheritance loop: ".print_r($queuedTables,true));
		$queuedTables[$tableName] = true;

		if( ($inheritTablename = $allInheritedTables[$tableName]) ){
			if(!$completedTables[$inheritTablename]){ //inherited table is not ready yet, build it first
				$this->BuildInheritedTables($inheritTablename, $allInheritedTables, $queuedTables, $completedTables);
			}

			try {
				$inheritTable = $this->GetTable($inheritTablename);
			}
			catch(Exception $e){
				throw new Exception("Table '$tableName' is set to inherit from table '$inheritTablename', but '$inheritTablename' does not exist. ".$e->getMessage() );
			}

			$table = $this->GetTable($tableName);
			$table->InheritColumnsFromTable($inheritTable);
		}

		$queuedTables[$tableName] = false;
		$completedTables[$tableName] = true;
	}

	/**
	 * @return array parses the XML file into a structured assoc array
	 */
	private function GetAssocFromXml($xmlPath, $xsdFilePath=null) {
		//libxml_use_internal_errors(true); //uncomment to show xml parse errors
		
	    $reader = new XMLReader();
	    $reader->open($xmlPath, null, LIBXML_ERR_WARNING);
	    
	    //tried to pull in default values from the XSD document (for "AllowSet", "AllowGet", etc). could never get it to work. instead, we just set those default values in the XML parser and in the SmartColumn class itself
		//if($xsdFilePath){
	    //	$reader->setSchema($xsdFilePath);
	    //}

	    return $this->Xml2assoc($reader);
	}
	/**
	 * @return array helper, recursive function for GetAssocFromXml
	 */
	private function Xml2assoc($xml) {
		$assoc = null;
		while($xml->read()){
		  switch ($xml->nodeType) {
		    case XMLReader::END_ELEMENT: return $assoc;
		    case XMLReader::ELEMENT:
		      $assoc[$xml->name][] = array('v' => $xml->isEmptyElement ? $GLOBALS['EMPTY_ELEMENT_PLACEHOLDER'] : $this->Xml2assoc($xml)); //v=value)
		      if($xml->hasAttributes){
		        $el =& $assoc[$xml->name][count($assoc[$xml->name]) - 1];
		        while($xml->moveToNextAttribute()) $el['a'][$xml->name] = $xml->value; //a=attributes
		      }
		      break;
		    case XMLReader::TEXT:
		    case XMLReader::CDATA: $assoc .= $xml->value;
		  }
		}
		return $assoc;
	}

	/**
	 * Returns true if the XML file given in the constructor of this class validates with the schema, false if the XML is invalid.
	 * @return bool true if the XML file given in the constructor of this class validates with the schema, false if the XML is invalid.
	 */
	private function IsXmlFileValid($xmlFilePath, $schemaFilePath){
	    //when validating data, if any error is encountered, libxml will generate PHP Warnings,
	    //which is something I think is very annoying. to avoid that, you can disable libxml errors
	    //and get them yourself. remember! this function must be called before you instantiate any DomDocument object!
	    libxml_use_internal_errors(true);

	    $objDom = new DomDocument(); //creating a DomDocument object

	    $objDom->load($xmlFilePath);  //loading the xml data

	    //tries to validade the file
	    if(!$objDom->schemaValidate($schemaFilePath)){
	        $allErrors=libxml_get_errors(); //get errors
	        print_r($allErrors); //each element of the array $allErrors will be a LibXmlError Object
			return false;
	    }
	    else return true;
	}


/////////////////////////////// SyncDb ///////////////////////////////////
	/**
	 * Synchronizes the structure of this Database instance to the SQL database connection in the DbManager
	 * <code>
	 * 	$options = array(
	 * 		'debug-mode' => false, //if true, prints all Sync SQL instead of executing it
	 * 		'backup-tables' => true, //if true, creates a backup table before altering any existing tables
	 * 		'create' => true, //if true, tables and columns will be created if they do not exist
	 * 		'update' => true, //if true, existing SQL columns will be updated to match properties defined in the SmartDatabase
	 * 		'delete' => true, //if true, columns that do not exist on a SmartDb managed table will be removed (note: unmanaged TABLES are never deleted!)
	 * 	);
	 * </code>
	 * @param bool $printResults [optional] If true, the results will be printed to the screen. (Results are returned regardless.)
	 * @param array $options [optional] See description above
	 * @return string The results of the sync
	 */
	public function SyncStructureToDatabase($printResults=false, $options=null){
		$defaultOptions = array( //default options
			'debug-mode' => false,
			'backup-tables' => true,
			'create' => true,
			'update' => true,
			'delete' => true, 
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		
		if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		
		$syncStructure = $this->BuildSyncStructure();
		//print_nice($syncStructure);

		//TODO: support other databases
		require_once (dirname(__FILE__)."/SyncDb/SyncDb_MySQL.php");

		//parameters: DoSync($dbManager, $newDbStructure, $backupTables=true, $doInsert=true, $doUpdate=true, $doDelete=true, $debugMode=false)
		$results = SyncDb_MySQL::Instance()->DoSync($this->DbManager, $syncStructure, $options['backup-tables'], $options['create'], $options['update'], $options['delete'], $options['debug-mode']);
		
		if($printResults) echo $results;
		return $results;
	}

	private function BuildSyncStructure(){
		$structure = array();
		foreach($this->_tables as $tableName=>$Table) {
			if($Table->IsAbstract) continue; //abstract tables do not actually go in the database, but they can be inherited from
			
			$tableName = $Table->TableName; //if table name was updated programmatically, we need this to be right in our sync structure

			$allColumns = $Table->GetAllColumns();
			foreach($allColumns as $columnName=>$Column){
				$columnName = $Column->ColumnName; //if column name was updated programmatically, we need this to be right in our sync structure
				
				//Field
				$structure[$tableName][$columnName]["Field"] = $columnName;

				//Type
				$structure[$tableName][$columnName]["Type"] = $Column->DataType;
				$dataTypeLower = strtolower($Column->DataType);
				if (strpos($dataTypeLower,"enum")===0){ //enum.. type
					if(count($Column->PossibleValues) <= 0) throw new Exception("'{$Column->DataType}' column type has no PossibleValues set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$structure[$tableName][$columnName]["Type"] .= "('".implode("','", $Column->PossibleValues)."')";
				}
				else if(strpos($dataTypeLower,"char")!==false){ //..char.. type
					if(empty($Column->MaxSize)) throw new Exception("'{$Column->DataType}' column type has no size set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$structure[$tableName][$columnName]["Type"] .= "({$Column->MaxSize})";
				}
				else if (strpos($dataTypeLower,"int")!==false){ //..int.. type
					$size = (empty($Column->MaxSize) ? "1" : $Column->MaxSize);
					$structure[$tableName][$columnName]["Type"] .= "($size)";
					if($Column->IsPrimaryKey){ //..int.. primary key
						$structure[$tableName][$columnName]["Type"] .= " unsigned";
					}
				}
				else if (strpos($dataTypeLower,"binary")!==false){ //..binary.. type
					$size = (empty($Column->MaxSize) ? "1" : $Column->MaxSize);
					$structure[$tableName][$columnName]["Type"] .= "($size)";
				}
				else if (strpos($dataTypeLower,"decimal")!==false){ //..decimal.. type
					if(empty($Column->MaxSize)){
						$precision = "14,4"; //default precision
					}
					else{
						$precision = $Column->MaxSize;
					}
					$structure[$tableName][$columnName]["Type"] .= "(".$precision.")";
				}

				//Null
				if($Column->IsRequired || $Column->IsPrimaryKey)
					$structure[$tableName][$columnName]["Null"] = "NO";
				else $structure[$tableName][$columnName]["Null"] = "YES";

				//Key
				if($Column->IsPrimaryKey){
					$structure[$tableName][$columnName]["Key"] = "PRI";
					$structure[$tableName][$columnName]["IndexType"] = "UNIQUE";
				}
				else if($Column->IsUnique){
					$structure[$tableName][$columnName]["Key"] = "UNI";
					$structure[$tableName][$columnName]["IndexType"] = "UNIQUE";
				}
				else if($Column->FulltextIndex){ //Fulltext index
					$structure[$tableName][$columnName]["Key"] = "MUL";
					$structure[$tableName][$columnName]["IndexType"] = "FULLTEXT";
				}
				else{
					$structure[$tableName][$columnName]["Key"] = "";
					$structure[$tableName][$columnName]["IndexType"] = "";
				}
				
				//Default
				$structure[$tableName][$columnName]["Default"] = $Column->DefaultValue;

				//Extra
				if($Column->IsAutoIncrement)
					$structure[$tableName][$columnName]["Extra"] = "auto_increment";
				else $structure[$tableName][$columnName]["Extra"] = "";
				
				//Collation
				$structure[$tableName][$columnName]["Collation"] = $Column->Collation;
			}
		}

		//TODO: get this hard-coded SQL out of here... move to Sync class for a MySQL database
		foreach($structure as $tableName=>$columns){
			$sqlCreateTable = "CREATE TABLE `$tableName` (";

			//all columns
			$primaryKeyColumnNames = array();
			$fulltextColumnNames = array();
			$first = true;
			foreach($columns as $columnName=>$columnProps){
				if(!$first) $sqlCreateTable .= ", ";
				$sqlCreateTable .= "`{$columnProps['Field']}` {$columnProps['Type']}";
				
				//forced character set
				//reference: http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html
				if($structure[$tableName][$columnName]["Collation"]){
					switch($structure[$tableName][$columnName]["Collation"]){
						case "utf8_general_ci":
							$sqlCreateTable .= " CHARACTER SET utf8 COLLATE utf8_general_ci";
							break;
						case "latin1_swedish_ci":
							$sqlCreateTable .= " CHARACTER SET latin1 COLLATE latin1_swedish_ci";
							break;
						//no need for more yet. also may need to add a collation option
						default:
							throw new Exception("Unsupported Collation '".$structure[$tableName][$columnName]["Collation"]."' set for table: '$tableName', column: '$columnName'");
					}
				}

				//null
				if($structure[$tableName][$columnName]["Null"] === "NO"){
					$sqlCreateTable .= " NOT NULL";
				}
				else $sqlCreateTable .= " NULL";

				//default value
				if(isset($structure[$tableName][$columnName]["Default"]) && $structure[$tableName][$columnName]["Default"]!=="" && strcasecmp($columnProps['Type'], 'text')!=0 ){ // blob/text types cant have default values in mysql
					$sqlCreateTable .= " DEFAULT '".$structure[$tableName][$columnName]["Default"]."'";
				}

				//auto increment
				if(stripos($structure[$tableName][$columnName]["Extra"],"auto_increment") !== false){
					$sqlCreateTable .= " AUTO_INCREMENT";
				}

				//primary key
				$isPrimarykey = $structure[$tableName][$columnName]["Key"] === "PRI";
				if($isPrimarykey){
					$primaryKeyColumnNames[] = $columnName;
				}
				
				//unique
				if($structure[$tableName][$columnName]["Key"] === "UNI" || $structure[$tableName][$columnName]["IndexType"] === "UNIQUE"){
					if(!$isPrimarykey){ //no need to specify as UNIQUE if it's a primary key. this will make 2 indexes
						$sqlCreateTable .= " UNIQUE";
					}
				}
				
				//fulltext index
				if($structure[$tableName][$columnName]["IndexType"] === "FULLTEXT"){
					$fulltextColumnNames[] = $columnName;
				}
				$first = false;
			}

			//key columns
			if(count($primaryKeyColumnNames)>0){
				$sqlCreateTable .= ",PRIMARY KEY (";
				$sqlCreateTable .= implode(", ", $primaryKeyColumnNames);
				$sqlCreateTable .= ")";
			}
			
			//fulltext columns
			foreach($fulltextColumnNames as $ftColumnName){
				$sqlCreateTable .= ",FULLTEXT KEY `".$ftColumnName."` (".$ftColumnName.")";
			}
			
			$sqlCreateTable .= ");";
			$structure[$tableName]["__sqlCreateTable"] = $sqlCreateTable;
		}

		return $structure;
	}
/////////////////////////////// ReadDatabase //////////////////////////////////
	/**
	 * Reads the current connected database's structure ($this->DbManager)
	 * <code>
	 * 	$options = array(
	 * 		'preserve-current' => false, //if true, the current smart database structure will be preserved. existing tables/column will be overwritten by the db definitions
	 * 		'ignore-table-prefix' => 'backup_', //will not import tables that start with the given prefix
	 * 	)
	 * </code> 
	 * @param array $options
	 */
	public function ReadDatabaseStructure($options=null){
		$defaultOptions = array( //default options
			'preserve-current' => false,
			'ignore-table-prefix' => 'backup_',
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		
		//reset current database structure store in smartdb
		if(!$options['preserve-current']){
			$this->RemoveAllTables();
		}

		//TODO: support other databases
		require_once (dirname(__FILE__)."/ReadDb/ReadDb_MySQL.php"); //currently only works with PMA_MYSQL_INT_VERSION >= 50002
		
		$sqlStructure = ReadDb_MySQL::Instance()->GetArray($this->DbManager, $this->DbManager->GetDatabaseName());
		//print_nice($sqlStructure);
		
		foreach($sqlStructure as $tableName=>$tableProps){
			if($options['ignore-table-prefix'] && strpos($tableName, $options['ignore-table-prefix']) === 0){
				continue; //ignoring this table due to prefix match
			}
			
			if($this->TableExists($tableName)){ //existing table (likely preserve-current option set to true)
				$table = $this->GetTable($tableName);
			}
			else{ //new table (likely preserve-current option set to false)
				$table = new SmartTable($tableName);
			}
			
			$table->AutoRefresh = false; //optimization. we'll manually call $table->Refresh() after all columns have been added
			
			$colNum = 1; //track the column number
			foreach($tableProps as $columnName=>$columnProps){
				if($this->TableExists($tableName)){ //existing column (likely preserve-current option set to true)
					$table = $table->GetColumn($columnName);
				}
				else{ //new column (likely preserve-current option set to false)
					$column = new SmartColumn($columnName);
				}
				
				/*
				//$columnProps are the following:
				$type = $columnProps['Type']; //int(1) unsigned
				$collation = $columnProps['Collation'];
				$null = $columnProps['Null'];
				$key = $columnProps['Key'];
				$default = $columnProps['Default'];
				$extra = $columnProps['Extra'];
				$collation = $columnProps['Collation'];
				$indexType = $columnProps['IndexType'];
				*/ 
				
				//use datatype to determine each of the following vars: 
				$dataType = "";
				$extraInfo = "";
				$unsigned = false;
				$size = "";
				$possibleValues = array();
					
				if( ($pos = strpos($columnProps['Type'], "(")) !== false) {  //find parenthesis in column type
					//$pos contains parenthesis position
					//ex type: "int(1) unsigned"
					$dataType = substr($columnProps['Type'], 0, $pos); //"int"
					$extraInfo = substr($columnProps['Type'], $pos); //"(1) unsigned"
					$size = str_replace(" unsigned","",$extraInfo, &$unsigned);//"(1)" - $unsigned will be 0 or 1
					$size = trim($size, "() "); //1
					
					if($dataType == "enum"){
						//parse out the enum values
						preg_match_all("/'(.*?)'/", $size, $matches); 
						$possibleValues = $matches[1];
						$size = "";	//size just holds the possible values. dont keep it around
					}
				}
				else{
					$dataType = $columnProps['Type'];
				}
				
				if(stripos($dataType, "date") !== false || $dataType==='timestamp'){
					$isDate = true;
				}
				/*
				//for debugging
				echo 'TYPE: '.$dataType.'<br>';
				echo 'EXTRA: '.$extraInfo.'<br>';
				echo 'UNSIGNED: '.$unsigned.'<br>';
				echo 'SIZE: '.$size.'<br>';
				echo 'DATE: '.$isDate.'<br>';
				print_r($possibleValues);
				*/
				
				$column->DataType = $dataType;
				$column->IsDateColumn = $isDate;
				$column->PossibleValues = $possibleValues;
				$column->MaxSize = $size;
				
				$column->Collation = $columnProps['Collation'];
				$column->DefaultValue = $columnProps['Default'];
				$column->IsUnique = ( ($columnProps['Key']==="UNI" || $columnProps['IndexType']==="UNIQUE") ? true : false);
				$column->IsPrimaryKey = ( ($columnProps['Key']==="PRI") ? true : false);
				$column->IsAutoIncrement = ( (strpos($columnProps['Extra'], "auto_increment") !== false) ? true : false);
				$column->FulltextIndex = ( ($columnProps['IndexType'] === "FULLTEXT") ? true : false);
				$column->IsRequired = ( ($columnProps['Null']==="NO" || $column->IsPrimaryKey) ? true : false);
				$column->SortOrder = $colNum++;
				
				if(!$column->DisplayName){
					//use the column name to come up with something display friendly
					$displayName = "";
					$lastLetterUpper = false;
					$lastWasLetter = false;
					$lastWasValid = false;
					$strlen = strlen($columnName);
					for($i=0; $i<$strlen; $i++){
						$letter = $columnName[$i];

						$thisIsLetter = (preg_match("/[A-Z]/i", $letter) > 0);
						$thisIsNumber = is_numeric($letter);
						
						$thisLetterUpper = false;
						if($thisIsLetter && strtoupper($letter) === $letter){
							$thisLetterUpper = true;
						}
						
						if(($lastWasNumber || $lastWasLetter) && !$lastLetterUpper && $thisLetterUpper || $lastWasNumber != $thisIsNumber ){
							$displayName .= " ";
						}
						$displayName .= $letter;
						
						$lastLetterUpper = $thisLetterUpper;
						$lastWasLetter = $thisIsLetter;
						$lastWasNumber = $thisIsNumber;
					}
					$displayName = str_replace(array("_", "-")," ",$displayName); //replace hyphens and underscore with space
					$displayName = trim(ucwords($displayName)); //upper case the first words and trim
					
					//only set the display name if it's different from the column name, otherwise there's no need
					if($displayName != $columnName){
						$column->DisplayName = $displayName;
					}
				}
				
				$table->AddColumn($column);
			}
			
			$table->Refresh(); //optimization by doing this manually instead of using $table->AutoRefresh
			$table->AutoRefresh = true; //re-enable auto refresh

			$this->AddTable($table);
		}
		
		//$smartStructure = $this->BuildSyncStructure(); //current structure of the smartdb
		//print_nice($smartStructure);
	}
/////////////////////////////// ArrayAccess ///////////////////////////////////
	/**
	 * Adds a Table to the Database. $key doesnt matter at all. Uses the $Table->TableName as the table name always.
	 * So $Database[]=$Table; ... $Database['tablename']=$Table; ... $Database[123]=$Table; --- are all the same!
	 * @ignore
	 */
    public function offsetSet($key,$Table){
		if(!($Table instanceof SmartTable)) throw new Exception("Can only add Table instances to a Database using array notation.");
		$this->AddTable($Table);
	}
	/**
	 * @ignore
	 */
	public function offsetGet($tablename){
	    return $this->GetTable($tablename);
	}
	/**
	 * @ignore
	 */
	public function offsetUnset($tablename){
	    $this->RemoveTable($tablename);
	}
	/**
	 * @ignore
	 */
	public function offsetExists($tablename){
	    return isset($this->_tables[$tablename]);
	}

/////////////////////////////// Countable ///////////////////////////////////
	/**
	 * For the Countable interface, this allows us to do count($database) to return the number of tables.
	 * @return int The number of tables in this database.
	 */
	public function count() {
        return count($this->_tables);
    }

/////////////////////////////// ERROR ON INVALID FUNCTIONS/VARS //////////////////////////
	/**
	 * @ignore
	 */
	public function __call($method, $args){
		throw new Exception("Undefined method: $method. Passed args: ".print_r($args,true));
	}
	/**
	 * @ignore
	 */
	public function __set($key, $val){
		throw new Exception("Undefined var: $key. Attempted set value: $val");
	}
	/**
	 * @ignore
	 */
	public function __get($key){
		throw new Exception("Undefined var: $key");
	}

/////////////////////////////// PREVENT SERIALIZATION B/C IT IS RECURSIVELY HUGE //////////////////////////
	/**
	 * @ignore
	 */
	public function __sleep(){
		return array();
	}
} //end class

/**
 * Helps with debugging. Good to use for printing SmartDatabase objects or arrays containing SmartDatabase objects... much cleaner than print_r()
 * @param object $data The data to print
 */
function print_nice($data){
    // capture the output of print_r
    $out = print_r($data, true);

    // replace something like '[element] => <newline> (' with <a href="javascript:toggleDisplay('...');">...</a><div id="..." style="display: none;">
    $out = preg_replace('/([ \t]*)(\[[^\]]+\][ \t]*\=\>[ \t]*[a-z0-9 \t_]+)\n[ \t]*\(/iUe',"'\\1<div><a href=\"javascript:toggleDisplay(\''.(\$id = substr(md5(rand().'\\0'), 0, 7)).'\');\">\\2</a></div><div id=\"'.\$id.'\" style=\"display: none; margin-left:1em;\">'", $out);

    // replace ')' on its own on a new line (surrounded by whitespace is ok) with '</div>
    $out = preg_replace('/^\s*\)\s*$/m', '</div>', $out);

    // print the javascript function toggleDisplay() and then the transformed output
    echo '<script language="Javascript">function toggleDisplay(id) { document.getElementById(id).style.display = (document.getElementById(id).style.display == "block") ? "none" : "block"; }</script>'."\n$out";
}