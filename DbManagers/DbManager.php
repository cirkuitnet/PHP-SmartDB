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
 * Handles the actual database communication. This DbManager is for MySQL. (Set $GLOBALS['SQL_DEBUG_MODE'] to true to print all SQL requests.)
 * @package SmartDatabase
 */
interface DbManager {
	public function __construct($server, $user, $password, $databaseName=null, $extra_params = array());

	public function SetDatabaseName($databaseName, $options=null);
	public function GetDatabaseName();

    public function Select($array_select_fields, $table, $array_where='', $array_order='', $limit = '', $options=null);
    public function Insert($table, $array, $options=null);
    public function Update($table, $array, $array_where='', $limit = '', $options=null);
    public function Delete($table, $array_where='', $limit = '', $options=null);
    public function Query($query, $options=null);
	public function FetchAssocList();
	public function FetchArrayList();
	public function FetchAssoc();
    public function FetchArray();
	public function NumRows();
	public function NextResult();
	public function Error();
	public function InsertId();
	public function AffectedRows();
	public function OpenConnection($options = null);
    public function CloseConnection();
    
    //utility functions
    public function IsOperator($keyword);
    public function IsCondition($keyword);
    public function IsKeyword($keyword);

	//database management
	public function DatabaseExists($databaseName);
	public function CreateDatabase($databaseName);
	public function DropDatabase($databaseName);
	public function CopyDatabase($srouceDatabaseName, $destDatabaseName, $options=null);
	public function UserExists($username, $host="localhost");
	public function CreateUser($username, $password, $host="localhost");
	public function DropUser($username);
	public function TableExists($databaseName, $tableName);
	public function DropTable($databaseName, $tableName);
	public function GrantUserPermissions($databaseName, $uesrname, $host="localhost");
	public function GrantGlobalFilePermissions($username, $host="localhost");
	public function RevokeUserPermissions($databaseName, $uesrname, $host="localhost");
}
?>