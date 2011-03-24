<?php
require_once(dirname(__FILE__)."/_Common.php");

/**
 * The custom, extended implementation of the 'MyObject1' Row.
 * Note - we extend from _Common so DateLastModified and DateCreated cells get updated automatically
 */
class MyObject1 extends _Common { //TODO: update the classname for your object
	const TableName = 'MyObject1'; //TODO: update the tablename for your object

	public function __construct($Database=null, $MyObject1Id=null, $options=null) {
		parent::__construct(self::TableName, $Database, $MyObject1Id, $options);
		
		//attach callbacks to the row or particular cells. See documentation for all available callbacks
		//Examples:
		//$this->OnAfterDelete(...) -- row callback
		//$this->OnBeforeInsert(...) -- row callback
		//$this['MyEnum']->OnSetValue(...) -- cell callback
		
		//---------- START EXAMPLE CUSTOM FUNCTIONALITY ------------//
		//TODO: REMOVE/MODIFY THIS FOR YOUR PROJECT
		//example SmartCell callback. Invoked anytime the "MyString" cell value on this row has been changed
		$this['MyString']->OnBeforeValueChanged("OnMyStringSet", $this);
		//---------- END EXAMPLE CUSTOM FUNCTIONALITY ------------//
	}

	//---------- START EXAMPLE CUSTOM FUNCTIONALITY ------------//
	//TODO: REMOVE/MODIFY THIS FOR YOUR PROJECT
	/**
	 * Invoked anytime $this row's 'MyString' cell value has been changed.
	 * For the example, we'll assume a URL is stored in this field. This function makes sure the URL contains http:// always
	 */
	public function OnMyStringSet($eventObject, $eventArgs){
		$url = trim($eventArgs['new-value']); //see documentation for all available arguments
		if($url && !strstr($url,"://")){
			$url = "http://".$url; //assume http
		}
		$eventArgs['new-value'] = $url;
	}
	//---------- END EXAMPLE CUSTOM FUNCTIONALITY ------------//

	//implement more custom functionality below, using $this to reference the SmartRow itself
}
?>