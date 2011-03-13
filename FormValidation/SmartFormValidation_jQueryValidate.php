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
 */
/**
 * Provides javascript form validation rules for jQuery's Validation Plugin
 * A basic example (taken from testXml.php that comes with SmartDatabase):
 * <code>
 * <script type="text/javascript" src="/cirkuit/includes/js/jquery/core/1.3.2/jquery.min.js"></script>
 * <script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/validate/1.5.5/jquery.validate.min.js"></script>
 * <script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/validate/1.5.5/additional-methods.js"></script>
 * <script type="text/javascript">
 * $(function(){
 * 	//validate page properties form
 * 	$("form[name=test7]").validate({
 * 	<?
 * 		require_once(dirname(__FILE__)."/../FormValidation/SmartFormValidation_jQueryValidate.php");
 * 		$formValidation = new SmartFormValidation_jQuery($database);
 * 		$options = $formValidation->GetPluginOptions('Questionare');
 * 	?>
 * 		rules: <?=json_encode($options['rules'])?>,
 * 		messages: <?=json_encode($options['messages'])?>
 * 	});
 * });
 * </script>
 * <style type="text/css">
 * 	.error{
 * 		background-color:#FFD5D6;
 * 		border:1px dashed #CC0000;
 * 	}
 * 	label.error{
 * 		background:none;
 * 		border:none;
 * 		color: #cc0000;
 * 		font-size: 11px;
 * 		font-weight: bold;
 * 		margin-left: 1em;
 * 	}
 * </style>
 * </code>
 * @package SmartDatabase
 */
class SmartFormValidation_jQuery{

	/**
	 * @var SmartDatabase The Database that the validation rules will be created for
	 */
	public $Database;

	/**
	 * @param SmartDatabase $Database The Database that the validation rules will be created for
	 * @return SmartTable
	 */
	public function __construct($Database){
		if(!isset($Database)) throw new Exception('$Database must be set');

		$this->Database = $Database;
	}

	/**
	 * Returns the jQuery Validation Plugin's options for the given scope as an assoc array of formObjectName=>array(pluginOption=>value)
	 * @param string $tableName [optional] If set, returns ALL rules for the given $tableName OR optionally only the rule for the given $columnName
	 * @param string $columnName [optional] If set, returns the rule for the given $columnName within the given $Table
	 * @return assoc All rules in the database OR optionally all rules for the given $tableName OR optionally only the rule for the given $columnName within the given $tableName
	 */
	public function GetPluginOptions($tableName=null, $columnName=null){
		if($tableName) $Table = $this->Database->GetTable($tableName);
		if($columnName)	$Column = $this->Database->GetColumn($columnName);
		$options = array();

		if(empty($Table)){ //$Column doesnt matter if $Table isnt set, so no need to check it
			//return rules for the entire Database
			foreach($this->$Database->GetAllTables() as $tableName=>$Table){
				foreach($Table->GetAllColumns() as $columnName=>$Column){
					$formName = $Column->GetDefaultFormObjectName();
					if( ($rules = $this->GetRulesForColumn($Column)) ) $options['rules'][$formName] = $rules;
					if( ($messages = $this->GetMessagesForColumn($Column)) ) $options['messages'][$formName] = $messages;
				}
			}
		}
		else if(!empty($Column)){
			//return rules for the given $Column
			$formName = $Column->GetDefaultFormObjectName();
			if( ($rules = $this->GetRulesForColumn($Column)) ) $options['rules'][$formName] = $rules;
			if( ($messages = $this->GetMessagesForColumn($Column)) ) $options['messages'][$formName] = $messages;
		}
		else{ //if(!empty($Table)){
			//return rules for the given $Table
			foreach($Table->GetAllColumns() as $columnName=>$Column){
				$formName = $Column->GetDefaultFormObjectName();
				if( ($rules = $this->GetRulesForColumn($Column)) ) $options['rules'][$formName] = $rules;
				if( ($messages = $this->GetMessagesForColumn($Column)) ) $options['messages'][$formName] = $messages;
			}
		}
		return $options;
	}
	/**
	 * Returns the 'rules' for the given column
	 * @param object $Column
	 * @return Javascript for the 'rules' options of the jquery validation plugin
	 */
	private function GetRulesForColumn($Column){
		$js = null;
		if($Column->IsRequired){
			$js['required'] = true; //Makes the element always required.
		}
		if($Column->MinSize){
			$js['minlength'] = (int)$Column->MinSize; //Makes the element require a given minimum length.
		}
		if($Column->MaxSize){
			$js['maxlength'] = (int)$Column->MaxSize; //Makes the element require a given maxmimum length.
		}
		if($Column->RegexCheck){
			$js['regex'] = $Column->RegexCheck; //Makes the element require matching the regex
		}
		/**
		 * TODO make a 'number' rule for GetRulesForColumn()
		 */
		//if($Column->){
		//	$js .= "number: {$Column->MinSize},"; //Makes the element require a decimal number.
		//}
		/**
		 * TODO make a 'digits' rule for GetRulesForColumn()
		 */
		//if($Column->){
		//	$js .= "digits: {$Column->Digits},"; //Makes the element require digits only.
		//}

		return $js;
	}

	/**
	 * Returns the 'messages' for the given column. messages correspond to rules above
	 * @param object $Column
	 * @return string Javascript for the 'messages' options of the jquery validation plugin
	 */
	private function GetMessagesForColumn($Column){
		$js = array();
		if($Column->IsRequired){
			$js['required'] = "'{$Column->DisplayName}' field is required.";
		}
		if($Column->MinSize){
			$js['minlength'] = "Minimum of {$Column->MinSize} characters are required for '{$Column->DisplayName}'.";
		}
		if($Column->MaxSize){
			$js['maxlength'] = "Number of characters allowed for '{$Column->DisplayName}' exceeds the {$Column->MaxSize} character limit.";
		}
		if($Column->RegexCheck){
			$js['regex'] = $Column->RegexFailMessage;
		}

		return $js;
	}
}