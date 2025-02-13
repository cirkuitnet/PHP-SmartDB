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
 */
/**
 */
//PHP 5.3.0 is required. removing this check for performance. PHP <5.3 is considered EOL now
//if (strnatcmp( ($version=phpversion()), '5.3.0') < 0){ //check php constraints. eventually we'll get rid of this when everyone is 5.3.0+
//	throw new Exception("This version of SmartDatabase only works with PHP versions 5.3.0 and newer. You are using PHP version $version");
//}

require_once(dirname(__FILE__).'/SmartTable.php');
require_once(dirname(__FILE__).'/SmartRow.php');

/**
 * Manages a connection to the database and stores information about its table structure
 * @package SmartDatabase
 */
class SmartDatabase implements ArrayAccess, Countable{
	const Version = "1.47"; //should update this for ANY change to structure at least. used for determining if a serialized SmartDatabase object is invalid/out of date
	
	/////////////////////////////// SERIALIZATION - At top so we don't forget to update these when we add new vars //////////////////////////
		/**
		 * Specify all variables that should be serialized
		 * @ignore
		 */
		public function __sleep(){
			return array(
				'Version',
				'DEV_MODE',
				'DEV_MODE_WARNINGS',
				'DefaultTimezone',
				'_tables',
				'XmlSchemaDateModified'
			);
		}
	//////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * @var string Readonly. The current SmartDatabase version number. Set in constructor. Needed for storing version in serialized objects. Changes for each change made to the SmartDatabase code
	 */
	public $Version; //readonly. set in constructor 
	
	/**
	 * @var DbManager The DbManager instance, passed in the class constructor
	 * @see SmartDatabase::__construct() SmartDatabase::__construct()
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
	 * @var string  If this is a date column and a TimeZone is set on this column (or the database-level), then ALL datetime/timestamp values stored will be converted to UTC time for storing in the DB, then returned in the set timezone.
	 * Empty will use system time and won't touch dates (not recommended)
	 * There is a SmartDatabase level $DefaultTimezone, and also a SmartColumn $DefaultTimezone. If both values are set, the column's default will take precedence.
	 * NOTE: Use LONG timezone names here, not shortened values like "EST". You can use "date_default_timezone_get()" to get current system timezone. Ref: http://php.net/manual/en/timezones.php
	 */
	public $DefaultTimezone;

	/**
	 * Constructor for a new SmartDatabse object. Note that you can cache these objects within Memcached so we don't have to parse the XML and create the structure for every request (@see SmartDatabase::GetCached()).
	 * ``` php
	 * $options = array(
	 * 	'db-manager' => null, //DbManager - The DbManager instance that will be used to perform operations on the actual database. If null, no database operations can be performed (use for 'in-memory' instances)
	 * 	'xml-schema-file-path' => null, //string - the database schema file to load. If left null, you will need to either build the database yourself useing ->AddTable() or load a schema from XML using ->LoadSchema()
	 * 	'dev-mode' => true, //boolean - development mode toggle. When true, does extra verifications (ie data types, columns, etc) that are a must for development, but may slow things down a bit when running in production.
	 * 	'dev-mode-warnings' => true, //boolean. if true and in $DEV_MODE, warnings will be shown for like missing classes and etc.
	 * 	'default-timezone' => ''	//string (i.e. "America/Indiana/Indianapolis"). if set, sets the DefaultTimezone to this value. using "date_default_timezone_get()" will use php's default date timezone that is currently set and can be changed with a call to "date_default_timezone_set(...);". empty will use default system time (not recommended). Ref: http://php.net/manual/en/timezones.php
	 * )
	 * ```
	 * @see SmartDatabase::GetCached() SmartDatabase::GetCached()
	 * @param array $options [optional] see description above
	 * @param string $deprecated [optional] [deprecated] Use $options instead. This used to be the 'xml-schema-file-path' option
	 * @return SmartDatabase
	 */
	public function __construct($options=null, $deprecated=null){
		$defaultOptions = array( //default options
			//'db-manager' => null,
			'xml-schema-file-path' => $deprecated, //reverse compatibility
			'dev-mode' => true,
			'dev-mode-warnings' => true,
			'default-timezone' => ''
		);
		
		if(!is_array($options)){ //reverse compatibility. constructor used to be (DbManager $dbManager=null, $xmlSchemaFilePath=null). now it's an array. need to support old versions though
			$dbManager = $options;
			$options = array();
			$options['db-manager'] = $dbManager; //first parameter is DbManager
		}
		
		//merge in default options
		$options = array_merge($defaultOptions, $options);
		
		//set local variables in this object
		$this->Version = self::Version;
		$this->DbManager = $options['db-manager'];
		$this->DEV_MODE = $options['dev-mode'];
		$this->DEV_MODE_WARNINGS = ($options['dev-mode'] && $options['dev-mode-warnings']);
		
		//load schema, if given
		if( $options['xml-schema-file-path'] ) $this->LoadXmlSchema( $options['xml-schema-file-path'] );
		
		//use default timezone?
		if( $options['default-timezone'] ) $this->DefaultTimezone = $options['default-timezone'];
	}

/////////////////////////////// Table Management ///////////////////////////////////
	/**
	 * @var array Key is the table name. Value is the Table instance
	 * @see SmartDatabase::__construct() SmartDatabase::__construct()
	 * @ignore
	 */
	protected $_tables = array();

	/**
	 * Returns an assoc of all tables. The returned array's key=$tableName, value=$Table
	 * @return array an assoc of all tables. The returned array's key=$tableName, value=$Table
	 * @see SmartTable SmartTable
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
		if(empty($this->_tables[$tableName])) throw new Exception("Invalid table: '$tableName'");
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
		if(empty($this->_tables[$tableName])) throw new Exception("Invalid table: '$tableName'");

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
	 * The date the XML file used in LoadXmlSchema() was modified. If multiple XML Schemas are loaded, uses the latest of all.
	 * Useful for serializing objects, storing them somewhere ($_SESSION, memcached, etc), then retrieving without needing to completely rebuild the database from xml
	 * @var date
	 */
	public $XmlSchemaDateModified = null;
	
	/**
	 * Loads a database schema from an XML file. This schema will replace any tables that are currently being managed by the Database instance.
	 * <p><b>$options is an assoc-array, as follows:</b></p>
	 * ``` php
	 * $options = array(
	 * 	'clear-current-schema'=false, //if set to true, all tables currently loaded within this instance will be removed (unmanaged. the actual table still exists in the real db). if false, the given $xmlSchemaFilePath is simply added to whatever is currently in this database instance (tables/properties with the same name are overwritten with the new schema)
	 * );
	 * ```
	 * @param string $xmlSchemaFilePath The path of the XML database schema file to load.
	 * @param array $options [optional] See description
	 */
	public function LoadXmlSchema($xmlSchemaFilePath, array $options=null){
		if(!empty($options['clear-current-schema'])){
			$this->RemoveAllTables(); //clear old tables... new database schema coming in.
		}

		if(!file_exists($xmlSchemaFilePath)) throw new Exception("XML file does not exist at location: '$xmlSchemaFilePath'");

		$xsdFilePath = dirname(__FILE__).'/XmlSchema.xsd'; //default schema path
		if(!file_exists($xsdFilePath)) throw new Exception("XmlSchema.xsd file does not exist at location: '{$xsdFilePath}'");

		//validate the xml with the schema
		if(!$this->IsXmlFileValid($xmlSchemaFilePath, $xsdFilePath)) throw new Exception("XML file ($xmlSchemaFilePath) does not validate with XSD schema ($xsdFilePath)");

		$xmlAssoc = $this->GetAssocFromXml($xmlSchemaFilePath, $xsdFilePath);
		$this->BuildDatabase($xmlAssoc);
		
		//store schema's last mod time. Useful for serializing objects, storing them somewhere ($_SESSION, memcached, etc), then retrieving without needing to completely rebuild the database from xml
		$schemaModTime = filemtime($xmlSchemaFilePath);
		if($this->XmlSchemaDateModified == null || $schemaModTime > $this->XmlSchemaDateModified){
			$this->XmlSchemaDateModified = $schemaModTime;
		}
	}

	/**
	 * Gets the current database schema in XML format and optionally writes the XML to a file (specified in $xmlSchemaFilePath). The XML string is returned regardless.
	 * @param string $xmlSchemaFilePath [optional] If provided, the new XML will overwrite the file at the specified path
	 * @return string The current database schema in XML format
	 * @see ReadDb_MySQL::GetArray() ReadDb_MySQL::GetArray()
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
			if($Table->GetInheritedTableNames()){
				$csvTableNames = implode($Table->GetInheritedTableNames(), ',');
				$xml .= ' InheritsTableNames="'.$csvTableNames.'"';
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

				if (stripos($Column->DataType,"enum")===0 || stripos($Column->DataType,"set")===0){ //enum and set data types
					if(count($Column->PossibleValues) <= 0) throw new Exception("'{$Column->DataType}' column type has no PossibleValues set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$xml .= ' DataType="'.$Column->DataType."('".htmlspecialchars(implode("','", $Column->PossibleValues))."')\"";
				}
				else { //non-enum or set data type
					$xml .= ' DataType="'.$Column->DataType.'"';
					if( $Column->PossibleValues && ($possibleVals = implode(",", $Column->PossibleValues)) ){ //only print if we need to
						$xml .= ' PossibleValues="'.htmlspecialchars($possibleVals).'"'; //only save PossibleValues to its own attribute if not an enum or set data type... otherwise, it's part of the data type
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
				if($Column->Example || $Column->Example===0 || $Column->Example==="0"){
					$xml .= ' Example="'.htmlspecialchars($Column->Example).'"';
				}
				if($Column->IsRequired && !$Column->IsAutoIncrement){ //auto-increment shouldnt be required, otherwise we dont get the autoincrement value
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
				if($Column->NonuniqueIndex){
					$xml .= ' NonuniqueIndex="'.bs($Column->NonuniqueIndex).'"';
				}
				if($Column->IndexPrefixLength){
					$xml .= ' IndexPrefixLength="'.$Column->IndexPrefixLength.'"';
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
	 * @param array $xmlAssoc the xml database structure
	 */
	private function BuildDatabase(array $xmlAssoc){
		//database table/column structure
		$allXmlClasses = $xmlAssoc['CodeGenSchema'][0]['v']['Class'];
		$allInheritedTables = array(); //key is the table name, value is an array containing all inhertied table names. (ie key table inherits value tables)
		if(is_array($allXmlClasses)){
			foreach($allXmlClasses as $xmlClass){
				$xmlDatabase = $xmlClass['v']['Database'][0];
				$tableName = $xmlDatabase['a']['TableName'];

				$table = new SmartTable($tableName);

				$table->ExtendedByClassName = ($xmlClass['a']['Name'] ?? '');
				$table->AutoCommit = (!empty($xmlDatabase['a']['CommitChangesAutomatically']) && strtolower($xmlDatabase['a']['CommitChangesAutomatically']) === 'true' ? true : false);
				$table->IsAbstract = (!empty($xmlDatabase['a']['IsAbstract']) && strtolower($xmlDatabase['a']['IsAbstract']) === 'true' ? true : false);

				//track inherited tables. will handle adding inherited columns after all regular tables/column have been added
				//"InheritsTableName" attribute is old and deprecated. use the plural "InheritsTableNames" since it is supported now
				$inheritsTableNames = null;
				if (!empty($xmlDatabase['a']['InheritsTableNames'])) {
				    $inheritsTableNames = $xmlDatabase['a']['InheritsTableNames'];
				}
				else if (!empty($xmlDatabase['a']['InheritsTableName'])) {
				    $inheritsTableNames = $xmlDatabase['a']['InheritsTableName'];
				}
				if($inheritsTableNames){
					//$inheritsTableNames could be CSV of multiple table names
					$inheritTableNamesArr = explode(',', $inheritsTableNames);
					foreach($inheritTableNamesArr as $inheritTableName){
						$allInheritedTables[$tableName][] = trim($inheritTableName);
					}
				}

				$table->AutoRefresh = false; //optimization. we'll manually call $table->Refresh() after all columns have been added

				$xmlColumns = $xmlDatabase['v']['Field'];
				foreach($xmlColumns as $xmlColumn){
					$xmlColumnName = $xmlColumn['a']['Name'];

					$column = new SmartColumn($xmlColumnName);

					//add column aliases
					if(!empty($xmlColumn['a']['Aliases']) && ($aliases = $xmlColumn['a']['Aliases']) ){ //if the "Aliases" attribute exists..
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
					else if( stripos($xmlColumn['a']['DataType'], "set") === 0 ){ //set data type
						$column->DataType = "set";
						preg_match_all("/'(.*?)'/", $xmlColumn['a']['DataType'], $matches); //parse out the set values
						$column->PossibleValues = $matches[1];
					}
					else $column->DataType = strtolower($xmlColumn['a']['DataType']); //non-enum or set data type

					$column->IsStringColumn = (stripos($column->DataType, "char") !== false || stripos($column->DataType, "text") !== false);
					$column->IsDateColumn = (stripos($column->DataType, "date") !== false || $column->DataType==='timestamp');
					$column->IsTimezoneColumn = ($column->DataType==='datetime' || $column->DataType==='timestamp');
					$column->IsSerializedColumn = ($column->DataType==='array' || $column->DataType==='object');
					$column->IsASet = ($column->DataType==='set');
					$column->DisplayName = (!empty($xmlColumn['a']['DisplayName']) ? $xmlColumn['a']['DisplayName'] : $xmlColumnName);
					$column->Collation = $xmlColumn['a']['Collation'] ?? null;
					$column->MinSize = $xmlColumn['a']['MinSize'] ?? null;
					$column->MaxSize = $xmlColumn['a']['MaxSize'] ?? null;
					$column->AllowGet = (isset($xmlColumn['a']['AllowGet']) && strtolower($xmlColumn['a']['AllowGet']) === 'false' ? false : true); //default value is true
					$column->AllowSet = (isset($xmlColumn['a']['AllowSet']) && strtolower($xmlColumn['a']['AllowSet']) === 'false' ? false : true); //default value is true
					$column->TrimAndStripTagsOnSet = (isset($xmlColumn['a']['TrimAndStripTagsOnSet']) && strtolower($xmlColumn['a']['TrimAndStripTagsOnSet']) === 'true' ? true : false);
					$column->AllowLookup =(isset($xmlColumn['a']['AllowLookup']) && strtolower($xmlColumn['a']['AllowLookup']) === 'false' ? false : true); //default value is true
					$column->AllowGetAll = (isset($xmlColumn['a']['AllowGetAll']) && strtolower($xmlColumn['a']['AllowGetAll']) === 'false' ? false : true); //default value is true
					$column->DefaultValue = $xmlColumn['a']['DefaultValue'] ?? null;
					$column->Example = $xmlColumn['a']['Example'] ?? null;
					$column->IsUnique = (!empty($xmlColumn['a']['IsUnique']) && strtolower($xmlColumn['a']['IsUnique']) === 'true' ? true : false);
					$column->FulltextIndex = (!empty($xmlColumn['a']['FulltextIndex']) && strtolower($xmlColumn['a']['FulltextIndex']) === 'true' ? true : false);
					$column->NonuniqueIndex = (!empty($xmlColumn['a']['NonuniqueIndex']) && strtolower($xmlColumn['a']['NonuniqueIndex']) === 'true' ? true : false);
					$column->IndexPrefixLength = $xmlColumn['a']['IndexPrefixLength'] ?? null;
					$column->IsPrimaryKey = (!empty($xmlColumn['a']['PrimaryKey']) && strtolower($xmlColumn['a']['PrimaryKey']) === 'true' ? true : false);
					$column->IsAutoIncrement = (!empty($xmlColumn['a']['AutoIncrement']) && strtolower($xmlColumn['a']['AutoIncrement']) === 'true' ? true : false);
					$column->DefaultFormType = (!empty($xmlColumn['a']['FormType']) && $xmlColumn['a']['FormType'] ? $xmlColumn['a']['FormType'] : "text"); //"text" is default value
					$column->IsRequired = (!$column->IsAutoIncrement && !empty($xmlColumn['a']['InputRequired']) && strtolower($xmlColumn['a']['InputRequired']) === 'true' ? true : false); //auto-increment shouldnt be required, otherwise we dont get the autoincrement value
					$column->IsRequiredMessage = $xmlColumn['a']['InputEmptyError'] ?? null;
					$column->RegexCheck = $xmlColumn['a']['InputRegexCheck'] ?? null;
					$column->RegexFailMessage = $xmlColumn['a']['InputRegexFailError'] ?? null;
					$column->SortOrder = $xmlColumn['a']['SortOrder'] ?? null;
					
					//add column possible values. if set and the data type is enum or set, these will be used as the only possible values instead of the enum/set values (ie you can use a subset of the possible values specified in the enum/set data type)
					$possibleValues = trim($xmlColumn['a']['PossibleValues'] ?? '');
					if( $possibleValues ){ //if the "PossibleValues" attribute exists..
						if( ($possibleValues = explode(',', $possibleValues)) ){ //convert CSV string into array or FALSE if it's an empty string
							$column->PossibleValues = $possibleValues;
						}
					}
					
					//if the Column is a set data type, we need to verify each of the PossibleValues isn't a subset of a different PossibleValue.
					//ex: "tree" and "apple tree" will not be accepted because MySQL uses %tree% to search set like a CSV, so %tree% would match the 'apple tree' element as well
					//well just check for it and not allow it to avoid problems ahead of time
					//this is the behavior of MySQL - https://dev.mysql.com/doc/refman/5.7/en/set.html
					if($column->IsASet){
						if(!$column->PossibleValues) throw new Exception("SET column '".$xmlColumnName."' requires PossibleValues to be defined");
						foreach($column->PossibleValues as $i=>$possibleVal1){
							foreach($column->PossibleValues as $j=>$possibleVal2){
								if($i!=$j && strpos($possibleVal1, $possibleVal2)!==false)
									throw new Exception('SET column `'.$xmlColumnName.'` requires PossibleValues to be unique (i.e. no value can be a subset of another value. Check PossibleValues "'.$possibleVal1.'" and "'.$possibleVal2.'"');
							}							
						}
					}
					

					$table->AddColumn($column);
				}
				$table->Refresh(); //optimization by doing this manually instead of using $table->AutoRefresh
				$table->AutoRefresh = true; //re-enable auto refresh

				$this->AddTable($table);
			} //foreach($allXmlClasses

		} //is_array($allXmlClasses)

		//related columns
		$allXmlForeignKeys = $xmlAssoc['CodeGenSchema'][0]['v']['ForeignKey'] ?? null;
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
		foreach($allInheritedTables as $tableName=>$inheritTableNamesArr){
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
		if(!empty($completedTables[$tableName])) return; //this table is already ready to go. move along

		if(!empty($queuedTables[$tableName])) throw new Exception("Table inheritance loop: ".print_r($queuedTables,true));
		$queuedTables[$tableName] = true;

		$inheritTableNamesArr = $allInheritedTables[$tableName] ?? [];
		if( $inheritTableNamesArr ){
			foreach($inheritTableNamesArr as $inheritTablename){
				if(empty($completedTables[$inheritTablename])){ //inherited table is not ready yet, build it first
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
		}

		$queuedTables[$tableName] = false;
		$completedTables[$tableName] = true;
	}

	/**
	 * parses the XML file into a structured assoc array
	 * @param string $xmlPath path of the xml file to parse
	 * @param string $xsdFilePath path of the xsd schema file for verifying the xml is valid
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
	 * @param string $xml xml to parse
	 * @return array helper, recursive function for GetAssocFromXml
	 */
	private function Xml2assoc($xml) {
		$assoc = null;
		while($xml->read()){
		  switch ($xml->nodeType) {
		    case XMLReader::END_ELEMENT: return $assoc;
		    case XMLReader::ELEMENT:
		      $assoc[$xml->name][] = array('v' => $xml->isEmptyElement ? ($GLOBALS['EMPTY_ELEMENT_PLACEHOLDER'] ?? null) : $this->Xml2assoc($xml)); //v=value)
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
	 * @param string $xmlFilePath path of the xml file to parse
	 * @param string $schemaFilePath path of the xsd schema file for verifying the xml is valid
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
	 * 
	 * ``` php
	 * 	$options = array(
	 * 		'debug-mode' => false, //if true, prints all Sync SQL instead of executing it
	 * 		'backup-tables' => true, //if true, creates a backup table before altering any existing tables
	 * 		'create' => true, //if true, tables and columns will be created if they do not exist
	 * 		'update' => true, //if true, existing SQL columns will be updated to match properties defined in the SmartDatabase
	 * 		'delete' => true, //if true, columns that do not exist on a SmartDb managed table will be removed (note: unmanaged TABLES are never deleted!)
	 * 	);
	 * ```
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
			'delete' => true
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
		//mysql handles the sync structure differently based on version
		$serverVersion = $this->DbManager->GetServerVersion(); //can use: version_compare($serverVersion,'1.2.3','>=');
		$isServerVersion5 = (strpos($serverVersion,'5.') === 0);
		//$isServerVersion8 = (strpos($serverVersion,'8.') === 0);

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
				if (strpos($dataTypeLower,"enum")===0 || strpos($dataTypeLower,"set")===0){ //enum.. and set.. types
					if(count($Column->PossibleValues) <= 0) throw new Exception("'{$Column->DataType}' column type has no PossibleValues set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$structure[$tableName][$columnName]["Type"] .= "('".implode("','", $Column->PossibleValues)."')";
				}
				else if(strpos($dataTypeLower,"char")!==false){ //..char.. type
					if(empty($Column->MaxSize)) throw new Exception("'{$Column->DataType}' column type has no size set. Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$structure[$tableName][$columnName]["Type"] .= "({$Column->MaxSize})";
				}
				else if (strpos($dataTypeLower,"int")!==false){ //..int.. type
					if ($isServerVersion5) { //older mysql adds sizes to int, not newer mysql, even if you specify one
						$size = (empty($Column->MaxSize) ? "1" : $Column->MaxSize);
						$structure[$tableName][$columnName]["Type"] .= "($size)";
					}
					if($Column->IsPrimaryKey){ //..int.. primary key
						$structure[$tableName][$columnName]["Type"] .= " unsigned";
					}
				}
				else if (strpos($dataTypeLower,"bool")!==false){ //..bool.. type
					if($Column->MaxSize && $Column->MaxSize != "1") throw new Exception("'{$Column->DataType}' column type must be MaxSize 1 (or not set, which defaults to 1). Column: '{$Column->ColumnName}', Table: '{$Table->TableName}'");
					$structure[$tableName][$columnName]["Type"] = "tinyint(1)"; //bool is actually tinyint(1). this is also the case in mysql
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
				else if ($dataTypeLower == "array" || $dataTypeLower == "object"){ //array, object types
					$structure[$tableName][$columnName]["Type"] = "text"; //use text for array/object types. we can make the size configurable somehow if we need to down the road
				}

				//Null
				if($Column->IsRequired || $Column->IsPrimaryKey)
					$structure[$tableName][$columnName]["Null"] = "NO";
				else $structure[$tableName][$columnName]["Null"] = "YES";

				//Key
				if($Column->IsPrimaryKey){
					$structure[$tableName][$columnName]["Key"] = "PRI";
					$structure[$tableName][$columnName]["IndexType"] = "UNIQUE";
					$structure[$tableName][$columnName]["IndexPrefixLength"] = "";
				}
				else if($Column->IsUnique){
					$structure[$tableName][$columnName]["Key"] = "UNI";
					$structure[$tableName][$columnName]["IndexType"] = "UNIQUE";
					$structure[$tableName][$columnName]["IndexPrefixLength"] = $Column->IndexPrefixLength;
				}
				else if($Column->FulltextIndex){ //Fulltext index
					$structure[$tableName][$columnName]["Key"] = "MUL";
					$structure[$tableName][$columnName]["IndexType"] = "FULLTEXT";
					$structure[$tableName][$columnName]["IndexPrefixLength"] = ""; //FULLTEXT Indexing always takes place over the entire column and column prefix indexing is not supported. - https://dev.mysql.com/doc/refman/8.4/en/column-indexes.html
				}
				else if($Column->NonuniqueIndex){ //Nonunique index
					$structure[$tableName][$columnName]["Key"] = "MUL";
					$structure[$tableName][$columnName]["IndexType"] = "NONUNIQUE";
					$structure[$tableName][$columnName]["IndexPrefixLength"] = $Column->IndexPrefixLength;
				}
				else{
					$structure[$tableName][$columnName]["Key"] = "";
					$structure[$tableName][$columnName]["IndexType"] = "";
					$structure[$tableName][$columnName]["IndexPrefixLength"] = "";
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
			$uniqueIndexColumns = array();
			$nonuniqueIndexColumns = array();
			$fulltextColumnNames = array();
			$first = true;
			foreach($columns as $columnName=>$columnProps){
				if(!$first) $sqlCreateTable .= ", ";
				$sqlCreateTable .= "`{$columnProps['Field']}` {$columnProps['Type']}";
				
				//forced character set
				//reference: http://dev.mysql.com/doc/refman/5.0/en/charset-charsets.html
				if($columnProps["Collation"]){
					switch($columnProps["Collation"]){
						case "utf8_general_ci":
							$sqlCreateTable .= " CHARACTER SET utf8 COLLATE utf8_general_ci";
							break;
						case "utf8mb4_unicode_ci":
							$sqlCreateTable .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
							break;
						case "utf8mb4_general_ci":
							$sqlCreateTable .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
							break;
						case "latin1_swedish_ci":
							$sqlCreateTable .= " CHARACTER SET latin1 COLLATE latin1_swedish_ci";
							break;
						//no need for more yet. also may need to add a collation option
						default:
							throw new Exception("Unsupported Collation '".$columnProps["Collation"]."' set for table: '$tableName', column: '$columnName'");
					}
				}

				//null
				if($columnProps["Null"] === "NO"){
					$sqlCreateTable .= " NOT NULL";
				}
				else $sqlCreateTable .= " NULL";

				//default value
				if($columnProps['Default']!=""){
					$dataTypeLower = strtolower($columnProps['Type']);
					if( strpos($dataTypeLower,'enum')!==0 && strpos($dataTypeLower,'set')!==0 && (strpos($dataTypeLower,'text')!==false || strpos($dataTypeLower,'blob')!==false) ){
						// blob/text types cant have default values in mysql. make sure it's not an enum/set though that may contain the text 'text' or 'blob'
						error_log("WARNING: '".$columnProps['Field']."' is type '".$columnProps['Type']." which does not support default values. Default value '".$columnProps['Default']."' ignored.");
						//$sqlCreateTable .= ""; //no default allowed
					}
					else if( ($dataTypeLower == 'timestamp' || $dataTypeLower == 'datetime') && strtoupper($columnProps['Default']) == 'CURRENT_TIMESTAMP'){
						//no quotes around default value
						$sqlCreateTable .= " DEFAULT {$columnProps['Default']} ";
					}
					else{
						//quote default value
						$sqlCreateTable .= " DEFAULT '{$columnProps['Default']}' ";
					}
				}

				//auto increment
				if(stripos($columnProps["Extra"],"auto_increment") !== false){
					$sqlCreateTable .= " AUTO_INCREMENT";
				}

				//primary key
				$isPrimarykey = $columnProps["Key"] === "PRI";
				if($isPrimarykey){
					$primaryKeyColumnNames[] = $columnName;
				}
				
				//unique
				if($columnProps["Key"] === "UNI" || $columnProps["IndexType"] === "UNIQUE"){
					if(!$isPrimarykey){ //no need to specify as UNIQUE if it's a primary key. this will make 2 indexes
						$uniqueIndexColumns[$columnName] = $columnProps["IndexPrefixLength"];
					}
				}
				
				//fulltext index
				if($columnProps["IndexType"] === "FULLTEXT"){
					$fulltextColumnNames[] = $columnName;
				}
				
				//nonunique index
				if($columnProps["IndexType"] === "NONUNIQUE"){
					$nonuniqueIndexColumns[$columnName] = $columnProps["IndexPrefixLength"];
				}
				
				$first = false;
			}

			//key columns
			if(count($primaryKeyColumnNames)>0){
				$sqlCreateTable .= ",PRIMARY KEY (`";
				$sqlCreateTable .= implode("`, `", $primaryKeyColumnNames);
				$sqlCreateTable .= "`)";
			}
			
			//unique columns
			foreach($uniqueIndexColumns as $uiColumnName=>$indexPrefixLength){
				$sqlCreateTable .= ",UNIQUE `".$uiColumnName."` (`".$uiColumnName."`" . ($indexPrefixLength ? "(".$indexPrefixLength.")" : "") . ")";
			}
			
			//nonunique columns
			foreach($nonuniqueIndexColumns as $nuiColumnName=>$indexPrefixLength){
				$sqlCreateTable .= ",KEY `".$nuiColumnName."` (`".$nuiColumnName."`" . ($indexPrefixLength ? "(".$indexPrefixLength.")" : "") . ")";
			}
			
			//fulltext columns
			foreach($fulltextColumnNames as $ftColumnName){
				$sqlCreateTable .= ",FULLTEXT KEY `".$ftColumnName."` (`".$ftColumnName."`)";
			}
			
			$sqlCreateTable .= ");";
			$structure[$tableName]["__sqlCreateTable"] = $sqlCreateTable;
		}

		return $structure;
	}
/////////////////////////////// ReadDatabase //////////////////////////////////
	/**
	 * Reads the current connected database's structure ($this->DbManager)
	 * 
	 * ``` php
	 * 	$options = array(
	 * 		'preserve-current' => false, //if true, the current smart database structure will be preserved. existing tables/column will be overwritten by the db definitions
	 * 		'ignore-table-prefix' => 'backup_', //will not import tables that start with the given prefix
	 * 		'table' => null, //string - if provided, will only read the structure of the given table name
	 * 	)
	 * ``` 
	 * @param array $options
	 */
	public function ReadDatabaseStructure($options=null){
		$defaultOptions = array( //default options
			'preserve-current' => false,
			'ignore-table-prefix' => 'backup_',
			'table' => null
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		if(!$this->DbManager) throw new Exception("DbManager is not set. DbManager must be set to use function '".__FUNCTION__."'. ");
		
		//reset current database structure store in smartdb
		if(empty($options['preserve-current'])){
			$this->RemoveAllTables();
		}

		//TODO: support other databases
		require_once (dirname(__FILE__)."/ReadDb/ReadDb_MySQL.php"); //currently only works with PMA_MYSQL_INT_VERSION >= 50002
		
		$sqlStructure = ReadDb_MySQL::Instance()->GetArray($this->DbManager, $this->DbManager->GetDatabaseName(), $options['table']);
		//print_nice($sqlStructure);
		
		foreach($sqlStructure as $tableName=>$tableProps){
			if(!empty($options['ignore-table-prefix']) && strpos($tableName, $options['ignore-table-prefix']) === 0){
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
				$indexPrefixLength = $columnProps['IndexPrefixLength'];
				*/ 
				
				//use datatype to determine each of the following vars: 
				$dataType = "";
				$isDate = false;
				$extraInfo = "";
				$unsigned = false;
				$size = "";
				$possibleValues = array();
				
				//check for ENUM and SET data types
				$isASet = false;
				if( ($pos = strpos($columnProps['Type'], "(")) !== false) {  //find parenthesis in column type
					//$pos contains parenthesis position
					//ex type: "int(1) unsigned"
					$dataType = substr($columnProps['Type'], 0, $pos); //"int"
					$extraInfo = substr($columnProps['Type'], $pos); //"(1) unsigned"
					$size = str_replace(" unsigned","",$extraInfo, $unsigned);//"(1)" - $unsigned will be 0 or 1
					$size = trim($size, "() "); //1
					$isASet = ($dataType==='set');
					
					if($dataType==="enum" || $isASet){
						//parse out the enum/set values
						preg_match_all("/'(.*?)'/", $size, $matches); 
						$possibleValues = $matches[1];
						$size = "";	//size just holds the possible values. dont keep it around
					}
					
				}
				else{ //no parenthesis in column's data type
					$dataType = $columnProps['Type'];
				}
				
				$isString = false;
				if(stripos($dataType, 'char') !== false || $dataType==='text'){
					$isString = true;
				}
				
				$isDate = false;
				if(stripos($dataType, "date") !== false || $dataType==='timestamp'){
					$isDate = true;
				}

				$isTimezoneColumn = false;
				if($dataType==='datetime' || $dataType==='timestamp'){
					$isTimezoneColumn = true;
				}
				
				//these datatypes will never really be set in SQL, so this IF statement is pretty much worthless
				$isSerialized = false;
				if($dataType === 'array' || $dataType === 'object'){
					$isSerialized = true;
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
				$column->IsStringColumn = $isString;
				$column->IsDateColumn = $isDate;
				$column->IsTimezoneColumn = $isTimezoneColumn;
				$column->IsSerializedColumn = $isSerialized;
				$column->IsASet = $isASet;
				$column->PossibleValues = $possibleValues;
				$column->MaxSize = $size;
				
				$column->Collation = $columnProps['Collation'];
				$column->DefaultValue = $columnProps['Default'];
				$column->IsUnique = ( ($columnProps['Key']==="UNI" || $columnProps['IndexType']==="UNIQUE") ? true : false);
				$column->IsPrimaryKey = ( ($columnProps['Key']==="PRI") ? true : false);
				$column->IsAutoIncrement = ( (strpos($columnProps['Extra'], "auto_increment") !== false) ? true : false);
				$column->FulltextIndex = ( ($columnProps['IndexType'] === "FULLTEXT") ? true : false);
				$column->NonuniqueIndex = (!$column->IsUnique && ($columnProps['IndexType'] === "NONUNIQUE") ? true : false);
				$column->IndexPrefixLength = $columnProps['IndexPrefixLength'];
				$column->IsRequired = ( !$column->IsAutoIncrement && ($columnProps['Null']==="NO" || $column->IsPrimaryKey) ? true : false); //auto-increment shouldnt be required, otherwise we dont get the autoincrement value
				$column->SortOrder = $colNum++;
				
				if(!$column->DisplayName){
					//use the column name to come up with something display friendly
					$displayName = "";
					$lastLetterUpper = false;
					$lastWasLetter = false;
					$lastWasNumber = false;
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

//////////////////////////////// STATIC - GET SmartDatabase OBJECT FROM MEMCACHED ////////////////////////////////
	/**
	 * An alternative to calling "new SmartDatabase($options)" - uses memcached to get/save an entire SmartDb structure within cache. You must pass in at least the 'memcached-key' and 'xml-schema-file-path' options. Also, the returned SmartDb will not have it's DbManager set unless you pass the 'db-manager' option, so you may need to set it manually.
	 * ``` php
	 * 	//options are same as SmartDatabase constructor, plus a few extra for memcached
	 * 	$options = array(
	 * 		'db-manager' => null, //DbManager - The DbManager instance that will be used to perform operations on the actual database. If null, no database operations can be performed (use for 'in-memory' instances)
	 * 		'xml-schema-file-path' => null, //string - REQUIRED for caching - the database schema file to load. If left null, you will need to either build the database yourself useing ->AddTable() or load a schema from XML using ->LoadSchema()
	 * 		'default-timezone' => '' //if set, $SmartDb->DefaultTimezone will be set to this value
	 * 		'dev-mode' => true, //boolean - development mode toggle. When true, does extra verifications (ie data types, columns, etc) that are a must for development, but may slow things down a bit when running in production.
	 * 		'dev-mode-warnings' => true, //boolean. if true and in $DEV_MODE, warnings will be shown for like missing classes and etc.
	 * 		'memcached-key' => null, //string. REQUIRED for caching. memcached lookup key
	 * 		'memcached-host' => 'localhost', //string. memcached connection host
	 * 		'memcached-port' => 11211, //int. memcached connection port
	 * 		'memcached-timeout' => 250, //int. failover fast-ish
	 * 		'memcached-serializer' => Memcached::SERIALIZER_IGBINARY, //a much better serializer than the default one in PHP
	 * 		'memcached-compression' => true, //actually makes things faster too
	 * 	)
	 * ```
	 * @param array $options [optional] see description above
	 * @return SmartDatabase
	 */
	public static function GetCached(array $options = null){
		$memcachedExists = class_exists('MemCached'); //we'll make sure memcached exists before using it
		
		$defaultOptions = array( //default options
			//'db-manager' => null,
			//'xml-schema-file-path' => null, //REQUIRED for caching to work properly!
			'default-timezone' => '', //if set, $SmartDb->DefaultTimezone will be set to this value
			'dev-mode' => true,
			'dev-mode-warnings' => true,
			'memcached-key' => null, //REQUIRED for caching to work properly
			'memcached-host' => 'localhost',
			'memcached-port' => 11211,
			'memcached-timeout' => 250, //override default. make failover fast-ish
			'memcached-serializer' => ($memcachedExists ? Memcached::SERIALIZER_IGBINARY : null), //a much better serializer than the default one in PHP
			'memcached-compression' => true //actually makes things faster too
		);
		if(is_array($options)){ //overwrite $defaultOptions with any $options specified
			$options = array_merge($defaultOptions, $options);
		}
		else $options = $defaultOptions;
		
		//check for all required arguments
		if(empty($options['memcached-key']) || empty($options['xml-schema-file-path'])){
			throw new \Exception("'memcached-key' and 'xml-schema-file-path' are both required options for SmartDatabase::GetCached()");
		}
		
		if( $memcachedExists ){
			try{
				//check for cached smartdb
				$mc = new MemCached();
				$mc->addServer($options['memcached-host'], $options['memcached-port']);
				
				//set options
				$mc->setOption( Memcached::OPT_SERIALIZER, $options['memcached-serializer'] );
				$mc->setOption( Memcached::OPT_CONNECT_TIMEOUT, $options['memcached-timeout'] ); 
				$mc->setOption( Memcached::OPT_COMPRESSION, $options['memcached-compression'] );
				
				//try to get the key from cache
				$cachedDb = $mc->get( $options['memcached-key'] );
				
				//log an error if we didn't get a key. we'll see a lot of these if the cache server is down
				$resultCode = $mc->getResultCode();
				if($resultCode != Memcached::RES_SUCCESS){
					error_log("SmartDb - MemCached Error - Could not get SmartDb object '".$options['memcached-key']."'. Error Code: ".$resultCode.", Error Msg: ".$mc->getResultMessage());
				}
			}
			catch(\Exception $e){
				error_log("SmartDb - MemCached Exception - Could not get SmartDb object '".$options['memcached-key']."'. Exception Msg: ".$e->getMessage());
				$mc = null;
			}
		}
		else{
			error_log("SmartDb - MemCached class does not exist - Could not get cached SmartDb object '".$options['memcached-key']."'");
		}
		
		//check if cachedDb is found and valid
		if( !empty($cachedDb) ){
			//compare XML dates on cached schema to make sure cache is not expired. also make sure we're using the same version of the SmartDatabase
			$xmlLastMod = filemtime($options['xml-schema-file-path']);
			if( ($cachedDb->XmlSchemaDateModified && $cachedDb->XmlSchemaDateModified == $xmlLastMod)
					&& ($cachedDb->Version == self::Version)
				){
					//dates and smartdb version match. valid cached db
					$cachedDb->DbManager = ($options['db-manager'] ?? null); //update the db manager. this can't be cached
					
					//timezone could change from what was cached
					if($cachedDb->DefaultTimezone != $options['default-timezone']){
						$cachedDb->DefaultTimezone = $options['default-timezone'];
					}
					
					return $cachedDb;  //set our global to the cached db
			}
		}
	
		//if no valid cache db found. create a new smartdb and store it in cache
		$smartDb = new self($options);
		
		//update cache (if no errors trying to fetch from memcached earlier)
		if($mc){
			$mc->set($options['memcached-key'], $smartDb);
			error_log("SmartDb - MemCached - New cache for SmartDb object '".$options['memcached-key']."'");
		}
		
		return $smartDb;
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
