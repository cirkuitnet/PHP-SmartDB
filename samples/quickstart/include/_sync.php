<?php
require_once("include.php");
//we now have a $GLOBALS['db'] and a $GLOBALS['dbManager'] to work with

//synchronize our XML schema to the actual database
$GLOBALS['db']->SyncStructureToDatabase(true); //true prints the results

die; //die when synchronizing. we typically won't ever want to do anything afterwards
?>