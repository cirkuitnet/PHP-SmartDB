<?php
require_once(dirname(__FILE__)."/_Common.php");

/**
 * The custom, extended implementation of the 'MyObject2' Row.
 * Note - we extend from _Common so DateLastModified and DateCreated cells get updated automatically
 */
class MyObject2 extends _Common { //TODO: update the classname for your object
	const TableName = 'MyObject2'; //TODO: update the tablename for your object

	public function __construct($Database=null, $MyObject2Id=null, $options=null) {
		parent::__construct(self::TableName, $Database, $MyObject2Id, $options);
		
		//attach callbacks to the row or particular cells. See documentation for all available callbacks
		//Examples:
		//$this->OnAfterDelete(...) -- row callback
		//$this->OnBeforeInsert(...) -- row callback
		//$this['MyDecimal']->OnSetValue(...) -- cell callback
	}

	//---------- START EXAMPLE CUSTOM FUNCTIONALITY ------------//
	//TODO: REMOVE/MODIFY THIS FOR YOUR PROJECT
	/**
	 * Formats an alpha-numeric "Price" column ("MyDecimal"). If we have any alpha characters, return the price as is.
	 * Otherwise, we'll format it up  (i.e. could return "$12.43" or "CALL FOR PRICE")
	 * If we have a SmartRow object, this allows us to do $row->GetFormattedPrice() instead of trying to format $row['MyDecimal']() every time we need it
	 */
     public function GetFormattedPrice(){
         $price = $this['MyDecimal'](); //$this is the current SmartRow instance, containing our row data for/from the database
         if(strlen($price)==0) return "";
         foreach(str_split($price) as $char){
             if(ctype_alpha($char)){
                 return $price;
             }
         }
         return '$'.number_format(self::ParseFloat($price), 2);
     }
     /**
      * Helper function - turns something like "$12,300.50" or "$12300.50" or  "12300.50" into "12300.5", which can then be formatted exactly as needed.
      * Can be called from anywhere with MyObject2::ParseFloat($value)
      */
     public static function ParseFloat($value){
         return floatval(preg_replace('#^([-]*[0-9\.,\' \$]+?)((\.|,){1}([0-9-]{1,2}))*$#e', "str_replace(array('.', ',', \"'\", ' ', '$'), '', '\\1') . '.\\4'", $value));
     }
     //---------- END EXAMPLE CUSTOM FUNCTIONALITY ------------//

	//implement more custom functionality below, using $this to reference the SmartRow itself
}
?>

