<?php
/**
 * A helper class for displaying the samples
 * @ignore
 */
class DisplayHelper{
	/**
	 * @ignore
	 */
	public static function PrintTemplateTop($pageTitle){
	?><!doctype html>
	<!--[if lt IE 7 ]> <html class="ie6" lang="en"> <![endif]-->
	<!--[if IE 7 ]>    <html class="ie7" lang="en"> <![endif]-->
	<!--[if IE 8 ]>    <html class="ie8" lang="en"> <![endif]-->
	<!--[if (gte IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<title><?=$pageTitle?> - PHP SmartDB Basic Examples</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script type="text/javascript" src="http://code.jquery.com/jquery-latest.min.js"></script> 
		<script type="text/javascript" src="/cirkuit/includes/js/jquery/plugins/chili/2.2/jquery.chili-2.2.js"></script>
        <script type="text/javascript">
            ChiliBook.recipeFolder = "/cirkuit/includes/js/jquery/plugins/chili/2.2/";
        </script>
		<link rel="shortcut icon" href="/favicon.ico">
		<link rel="stylesheet" href="css/style.css?v=2">
	</head>
	<body>
		<div class="page">
			<div class="nav">
				<ul>
					<li><a href="insert.php">insert.php</a></li>
					<li><a href="insert-callback.php">insert-callback.php</a></li>
					<li><a href="list-all-rows.php">list-all-rows.php</a></li>
					<li><a href="lookup-update-by-id.php">lookup-update-by-id.php</a></li>
					<li><a href="lookup-update-by-email.php">lookup-update-by-email.php</a></li>
					<li><a href="delete-by-id.php">delete-by-id.php</a></li>
					<li><a href="delete-by-email.php">delete-by-email.php</a></li>
					<li><a href="custom-truncate-sql.php">custom-truncate-sql.php</a></li>
				</ul>
			</div>
			<div class="content">
	<?php
	} //end PrintTemplateHead()
	
	/**
	 * @ignore
	 */
	public static function PrintTemplateBottom(){
	?>
	
			</div> <?//end .content ?>
		</div> <?//end .page ?>
	</body>
</html>

	<?php
	} //end PrintTemplateBottom()

	
	/**
	 * @ignore
	 */
	public static function PrintTitle($title){
		if(!$title) return;
		echo '<h1 class="title">';
		echo 'Sample File: '.$title;
		echo '</h1>';
	}
	
	public static function PrintOutputTitle($title="Debugging Output"){
		if(!$title) return;
		echo '<h2 class="outputTitle">';
		echo $title;
		echo '</h2>';
	}
	
	public static function PrintSourceLink($url){
		if(!$url) return;
		echo '<a href="'.$url.'" class="sourceLink">'.$url.'</a>';
	}
	
	/**
	 * @ignore
	 */
	public static function PrintCode($code, $type="php"){
		if(!$code) return;
		
		$code = htmlspecialchars($code);
		
		echo '<h2>The Code</h2>';
		echo '<pre class="section code">';
		echo "<span class='php__dec'>&lt;&#63;php</span>\n";
		echo '<code class="'.$type.'">';
		echo $code;
		echo '</code>';
		echo "<span class='php__dec'>&#63;&gt;</span>";
		echo '</pre>';
	}
	
	/**
	 * @ignore
	 */
	public static function PrintRow($row, $title=""){
		if(!$row) return;
		echo '<div class="section row">';
		if($title) echo "<h3>$title</h3>";
		echo "<span>$row</span>";
		echo '</div>';
	}
	
	/**
	 * @ignore
	 */
	public static function PrintErrors($errors, $title="Errors Found"){
		if(!$errors) return;
		echo '<div class="section errors">';
		if($title) echo "<h3>$title</h3>";
		echo "<span>$errors</span>";
		echo '</div>';
	}
	
	/**
	 * @ignore
	 */
	public static function PrintRowsAffected($numRowsAffected, $titlePrefix="", $title="Number Of Rows Affected"){
		echo '<div class="section rowsAffected">';
		if($title || $titlePrefix){
			echo "<h3>";
			echo $titlePrefix;
			if($titlePrefix && $title) echo " - ";
			echo $title;
			echo "</h3>";
		}
		echo "<span>".(int)$numRowsAffected."</span>";
		echo '</div>';
	}
}
?>