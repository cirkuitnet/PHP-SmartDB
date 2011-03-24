<?
/**
 * If the class is not defined, this function will be called.
 * It attempts to include the file with the same name as the undefined class.
 * Searches the directory that this autoloader file resides.
 * 
 * Make your classes named the same as your files containing those classes
 * and you never need to worry about requiring them again!
 */
spl_autoload_register(function($classname){
	$includeFile = dirname(__FILE__).'/'.$classname.'.php';
					
	if(file_exists($includeFile)){
		require_once($includeFile);
	}
});
?>