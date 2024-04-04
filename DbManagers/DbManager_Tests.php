<?
/**
 */
require_once(dirname(__FILE__).'/DbManager_MySQL.php');

/**
 * @package SmartDatabase
 * @ignore
 */
class DbManager_Tests extends DbManager_MySQL{

	public function TestAll(){
		$passed = 0; $failed = 0;
		$result = $this->TestWhereClause1();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause2();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause3();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause4();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause5();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause6();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause7();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause8();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause9();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause10();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause11();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause12();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause13();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause14();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause15();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause16();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause17();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause18();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause19();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause20();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause21();	if($result['passed']) $passed++; else $failed++;
		$result = $this->TestWhereClause22();	if($result['passed']) $passed++; else $failed++;
		
		$result = $this->TestMultiQuery();		if($result['passed']) $passed++; else $failed++;
		
		// test database copy. connected user should have permissions to drop/create tables and databases
		//this also tests creating and dropping databases and tables
		$success = $this->CopyDatabase('smartdb_test', 'smartdb_test_copy', array(
			'create-tables' => true,
			'create-database' => true,
			'copy-data' => true,
			'drop-existing-tables' => true,
			'drop-existing-database' => true, 
		));
		if($success){
			$passed++; 
			echo 'Copy database passed (create/drop tables and databases)';
		}
		else {$failed++; echo "Error: could not copy database 'smartdb_test' to 'smartdb_test_copy'<br>";}

		
		echo "<br><br> -- RESULTS -- ";
		echo "<br>Tests passed: $passed";
		echo "<br>Tests failed: $failed";
	}
	
	private function TestMultiQuery(){
		$failed = false;
		$failMsg = '';
		try{
			$sql = 'insert into `FastSetting` (`Id`,`Name`,`ShortName`) values (444, "4@44.44", "444!");';
			$sql .= 'select * from `FastSetting` where `Id`=444;';
			$sql .= 'delete from `FastSetting` where `Id`=444;';
			$bool = $this->Query($sql, array(
				'multi-query' => true
			));
			
			if(!$bool){
				throw new Exception("multi-query 1 failed");
			}
			
			$bool = $this->NextResult();
			if(!$bool){
				throw new Exception("multi-query 2 failed");
			}
			
			//get rows from query 2
			$rows = $this->FetchAssocList();
			//print_r($rows);
			if(count($rows)!=1) throw new Exception("1 row was expected for query: 'select * from `FastSetting` where `Id`=444;'");
			
			$bool = $this->NextResult();
			if(!$bool){
				throw new Exception("multi-query 3 failed");
			}
			
			$bool = $this->NextResult();
			if($bool !== false){
				throw new Exception('Next result did not return FALSE as expected. Returned: '.$bool);
			}
		}
		catch(Exception $e){
			if($this->_driver == self::MYSQL_DRIVER){ //not supported, so an exception here is a PASS
				$failed = false;
			}
			else{
				$failed = true;
				$failMsg = $e->getMessage();
			}
		}
		
		if($failed) echo ($msg = __FUNCTION__." <strong>FAILED</strong>:<br>".$failMsg);
		else echo ($msg = __FUNCTION__." passed.");
		
		echo "<br><br>";
		
		return array('passed'=>!$failMsg, 'message'=>$msg);
	}
	
	
	private function TestWhereClause($array_where, $expectedResult, $testFuncName=null, $options=array()){
		$failed = false;
		$failMsg = '';
		try{
			$actualResult = trim($this->GenerateWhereClause("TEST_TABLE", $array_where, false, false, $options));
			if(strcmp($actualResult,$expectedResult) != 0){
				throw new Exception("Expected Result: <strong>$expectedResult</strong><br>Actual Result: <strong>$actualResult</strong>");
			}
		}
		catch(Exception $e){
			$failed = true;
			$failMsg = $e->getMessage();
		}
		
		if($failed) echo ($msg = "$testFuncName <strong>FAILED</strong>:<br>".$failMsg);
		else echo ($msg = "$testFuncName passed.");
		
		echo "<br><br>";
		
		return array('passed'=>!$failMsg, 'message'=>$msg);
	}
	

	private function TestWhereClause1(){
		$array_where = array( "col1"=>"5" );
		$expectedResult = "WHERE col1 = 5";
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause2(){
		$expectedResult = "WHERE col1 = 5 AND col2 = 10 AND col3 = 15";
		$array_where = array( // (nested arrays default to "AND")
		    "col1"=>5,   //col1=5
		                 //AND
		    "col2"=>10,  //col2=10
		                 //AND
		    "col3"=>15   //col3=15
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause3(){
		$expectedResult = "WHERE (col1 = 5 OR col2 = 10 OR col3 = 15)";
		$array_where = array( // (outer array defaults to "AND")
			"OR" => array( //override with "OR"
				"col1"=>5,   //col1=5
							 //OR
				"col2"=>10,  //col2=10
							 //OR
				"col3"=>15   //col3=15
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause4(){
		$expectedResult = "WHERE foo2 = 'bar2' AND (foo3 = 'bar3' AND foo4 = 'bar4') AND foo5 = 'bar5'";
		$array_where = array( // (outer array defaults to "AND")
		  "foo2" => "bar2",     //foo2='bar2'
		                        //AND
		  array( // (nested arrays default to "AND")
		    "foo3" => "bar3",   //foo3='bar3'
		                        //AND
		    "foo4" => "bar4"    //foo4='bar4'
		  ),
		                        //AND
		  "foo5" => "bar5"      //foo5='bar5'
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause5(){
		$expectedResult = "WHERE (foo1 = 'bar1' AND foo2 = 'bar2') AND (foo3 = 'bar3' AND foo4 = 'bar4')";
		$array_where = array( // (outer array defaults to "AND")
		  array( 
		    "foo1" => "bar1",   //foo1='bar1'
		                        //AND
		    "foo2" => "bar2"    //foo2='bar2'
		  ),                    
		                        //AND 
		  array(
		    "foo3" => "bar3",   //foo3='bar3'
		                        //AND
		    "foo4" => "bar4"    //foo4='bar4'
		  )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause6(){
		$expectedResult = 'WHERE (col1 = 4 AND col3 = 5) AND (col1 = 6 OR col2 = 7)';
		$array_where = array( // (outer array defaults to "AND")
		  "AND"=>array(
		    "col1"=>4,         //col1=4
		                       //AND
		    "col3"=>5          //col3=5
		  ),
		                       //AND (from outer array)
		  "OR"=>array(         
		    "col1"=>6,         //col1=6
		                       //OR
		    "col2"=>7          //col2=7
		  )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause7(){
		$expectedResult = 'WHERE (col1 > 40) AND (col2 < 100)';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array( ">" => 40 ),     //col1 > 40
		                                    //AND
		  "col2" => array( "<" => 100 )     //col1 > 100
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause8(){
		$expectedResult = 'WHERE (col1 > 4) AND col3 = 5 AND (col1 = 6 OR col2 = 7)';
		$array_where = array( 
		    "col1"=>array( ">" => 4 ),  //col1 > 4
		                                //AND
		    "col3"=>5,                  //col3 = 5
		                                //AND
		    "OR"=>array(
		      "col1"=>6,                //col1 = 6
		                                //OR
		      "col2"=>7                 //col2 = 7
		    )
		
		    // (anything else added here is AND'ed)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause9(){
		$expectedResult = 'WHERE (col1 = 3 AND col1 = 5 AND col1 = 7)';
		$array_where = array( 
			"col1" => array("3","5","7")
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause10(){
		$expectedResult = 'WHERE ((col1 = 3 OR col1 = 5 OR col1 = 7))';
		$array_where = array( 
			"col1" => array( "OR" => array("3","5","7") )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause11(){
		$expectedResult = 'WHERE ((col1 = 3 OR col1 = 5 OR col1 = 7))';
		$array_where = array( 
		  "col1" => array(
		    "OR" => array(
		      3,5,7
		    )
		  )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause12(){
		$expectedResult = 'WHERE (col1 < 10 AND (col1 = 3 OR col1 = 5 OR col1 = 7)) AND col2 = 11';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array(
		    "<" => 10,                   //col1<10
		                                 //AND
		    "OR" => array("3","5","7")   //col1=3 OR col1=5 OR col1=7
		  ),
		                                 //AND
		  "col2" => 11                   //col2=11
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause13(){
		$expectedResult = 'WHERE (col1 > 40 AND col1 <= 50)';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array(
		    ">" => 40,                 //col1 > 40
		                               //AND
		    "<=" => 50                 //col1 <= 50
		  ),
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause14(){
		$expectedResult = 'WHERE ((col1 <= 40 OR col1 > 50))';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array(       
		    "OR" => array( // (override the outer "AND")
		      "<=" => 40,                //col1 <= 40
		                                 //OR
		      ">" => 50                  //col1 > 50
		    )
		  )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause15(){
		$expectedResult = 'WHERE ((col1 <= 40 OR col1 > 50) AND (col1 != 44 OR col1 is null))';
		$array_where = array(// (outer array defaults to "AND")
		  "col1" => array( 
		    "OR" => array( // (override the outer "AND" with "OR")
		      "<=" => 40,                //col1 <= 40
		                                 //OR
		      ">" => 50                  //col1 > 50
		    ),
		                                 //AND
		    "!=" => "44"                 //col1 != 44 OR col1 is null ("is null" case is special for MySQL)
		  ),
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause16(){
		$expectedResult = 'WHERE (((col1 > 4 OR col1 = 1) AND ((col1 != 9 OR col1 is null) AND col1 = 2)))';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array(
		    "AND" => array (
				"OR" => array(
			      ">" => 4,               //col1 > 4
			                              //OR
			      1,                      //col1 = 1
			    ), 
			    "AND" => array(
			      "!=" => 9,              //col1 != 9 OR col1 is null ("is null" case is special for MySQL)
			                              //OR
			      "=" => 2                //col1 = 2
			    )
			 )
		  )
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause17(){
		$expectedResult = 'WHERE ((col1 < 0 OR (col1 > 3 AND ((col1 != 10 OR col1 is null) AND (col1 != 12 OR col1 is null))))) AND col2 = 5';
		$array_where = array( // (outer array defaults to "AND")
		  "col1" => array(
		    "OR" => array(
		      "<" => 0,                   //col1 < 0
		                                  //OR
		      "AND" => array( // override outer "OR" with "AND" for the following
		        ">" => 3,                 //col1 > 3
		                                  //AND
		        "!=" => array(10,12)      //(col1 != 10 OR col1 is null) AND (col1 != 12 OR col1 is null)   ("is null" cases are special for MySQL)
		      )
		    )
		  ),
		                                  //AND
		  "col2" => 5                     //col2 = 5
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause18() {
		$expectedResult = "WHERE ((col1 = 5 OR (col2 = -1 AND col3 = 'yo')) AND (col4 LIKE 'yo again') AND ((col5 != 5 OR col5 is null)))";
		$array_where = array(
			"AND" => array(
				"OR" => array(
					"col1" => 5,
					"AND" => array(
						"col2"=>-1,
						"col3"=>"yo"
					)
				),
				"col4" => array(
					"LIKE"=>"yo again"
				),
				"col5" => array(
					"<>" => 5
				)
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause19() {
		$expectedResult = "WHERE ((col1 < 'z') AND col2 is null AND (col3 IS NOT 5))";
		$array_where = array(
			"AND" => array(
				"col1" => array("<" => "z"),
				"col2" => NULL,
				"col3" => array("IS NOT"=>5) 
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
	
	private function TestWhereClause20() {
		$expectedResult = "WHERE (col1 = 5 AND col2 = 6 AND col3 = '99999999999999999999999999999999999999999999999' AND col4 = '-99999999999999999999999999999999999999999999999')";
		$array_where = array(
			"AND" => array(
				"col1" => 5,
				"col2" => 6,
				"col3" => '99999999999999999999999999999999999999999999999',
				"col4" => '-99999999999999999999999999999999999999999999999',
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__, array(
			'quote-numerics'=>false
		));
	}
	
	private function TestWhereClause21() {
		$expectedResult = "WHERE (col1 = '5' AND col2 = '6' AND col3 = '99999999999999999999999999999999999999999999999' AND col4 = '-99999999999999999999999999999999999999999999999')";
		$array_where = array(
			"AND" => array(
				"col1" => 5,
				"col2" => 6,
				"col3" => '99999999999999999999999999999999999999999999999',
				"col4" => '-99999999999999999999999999999999999999999999999',
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__, array(
			'quote-numerics'=>true
		));
	}
	
	private function TestWhereClause22() {
		$expectedResult = "WHERE (((col1 != 0 OR col1 is null)) AND (col2 is null) AND (col3 is not null) AND (col4 is not null))";
		$array_where = array(
			"AND" => array(
				"col1" => array("!=" => "0"),
				"col2" => array("=" => NULL),
				"col3" => array("IS NOT"=>null),
				"col4" => array("!="=>null),
			)
		);
		return $this->TestWhereClause($array_where, $expectedResult, __FUNCTION__);
	}
}


//$tests = new DbManager_Tests('SERVER','USERNAME','PASSWORD','DATABASE_NAME');
//$tests = new DbManager_Tests('localhost','smartdb','smartdb123','smartdb_test', array(
//	'driver'=>'mysql'
//));
//$tests->TestAll();

$tests = new DbManager_Tests('localhost','smartdb','smartdb123','smartdb_test', array(
	'driver'=>'mysqli'
));
$tests->TestAll();
