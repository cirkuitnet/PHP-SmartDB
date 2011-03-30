<?php
/**
 * @ignore
 */
//require our include.php file which does our database initialization
require_once("include/include.php");

//require our DisplayHelper class for printing page content
require_once("include/DisplayHelper.php");

DisplayHelper::PrintTemplateTop("list-all-rows.php");
DisplayHelper::PrintTitle("list-all-rows.php");

//shortcut to the Customer table so we don't have to keep writing it.
//typically wouldn't do this, but it can make things cleaner sometimes
$cTable = $GLOBALS['db']['Customer'];

//for example, these now are exactly the same:
//$cTable['CustomerId']->...
//$GLOBALS['db']['Customer']['CustomerId']->...

//get all rows in the Customer table
$allRows = $cTable->GetAllRows(array(
	'order-by'=>"CustomerId", //optional options. see documentation api
	'return-count'=>&$numRows //optional OUT parameter. $numRows will be set to the number of rows returned
	//other options available here... see documentation api
));
?>
<table cellspacing="1">
	<tbody>
		<tr>
			<th><?=$cTable['CustomerId']->DisplayName?></th>
			<th><?=$cTable['Name']->DisplayName?></th>
			<th><?=$cTable['EmailAddress']->DisplayName?></th>
			<th><?=$cTable['Gender']->DisplayName?></th>
			<th><?=$cTable['DateCreated']->DisplayName?></th>
			<th><?=$cTable['DateLastModified']->DisplayName?></th>
		</tr>
		<?
		if($numRows == 0){ 
			?> <tr>
				<td colspan="6"><strong>NO ROWS</strong></td>
			</tr> <?
		}
		else{ //loop over all rows and print the info
			foreach($allRows as $Customer){
				?>
				<tr>
					<td><?=$Customer['CustomerId']()?></td>
					<td><?=$Customer['Name'](true,true)?></td>
					<td><?=$Customer['EmailAddress'](true,true)?></td>
					<td><?=$Customer['Gender']()?></td>
					<td><?=$Customer["DateCreated"]() ? date("M d, Y - g:i:s a",$Customer["DateCreated"](true)) : ""?></td>
					<td><?=$Customer["DateLastModified"]()  ? date("M d, Y - g:i:s a",$Customer["DateLastModified"](true)) : ""?></td>
				</tr>
				<?
			}
		}
		?>
	</tbody>
</table>
<?

DisplayHelper::PrintCode('
//outline (see source for full code)
require_once("include/include.php");

//shortcut to the Customer table so we don`t have to keep writing it.
//typically wouldn`t do this, but it can make things cleaner sometimes
$cTable = $GLOBALS["db"]["Customer"];

//for example, these now are exactly the same:
//$cTable["CustomerId"]->...
//$GLOBALS["db"]["Customer"]["CustomerId"]->...

//get all rows in the Customer table
$allRows = $cTable->GetAllRows(array(
	"order-by"=>"CustomerId", //optional options. see documentation api
	"return-count"=>&$numRows //optional OUT parameter. $numRows will be SET
	//other options available here... see documentation api
));
?>
<table cellspacing="1">
	<tbody>
		<tr>
			<th><?=$cTable["CustomerId"]->DisplayName?></th>
			<th><?=$cTable["Name"]->DisplayName?></th>
			<th><?=$cTable["EmailAddress"]->DisplayName?></th>
			<th><?=$cTable["Gender"]->DisplayName?></th>
			<th><?=$cTable["DateCreated"]->DisplayName?></th>
			<th><?=$cTable["DateLastModified"]->DisplayName?></th>
		</tr>
		<?
		if($numRows == 0){ 
			?> <tr>
				<td colspan="6"><strong>NO ROWS</strong></td>
			</tr> <?
		}
		else{ //loop over all rows and print the info
			foreach($allRows as $Customer){
				?>
				<tr>
					<td><?=$Customer["CustomerId"]()?></td>
					<td><?=$Customer["Name"](true,true)?></td>
					<td><?=$Customer["EmailAddress"](true,true)?></td>
					<td><?=$Customer["Gender"]()?></td>
					<td><?=$Customer["DateCreated"]()
						? date("M d, Y - g:i:s a",
								$Customer["DateCreated"](true)) : ""?></td>
					<td><?=$Customer["DateLastModified"]()
						? date("M d, Y - g:i:s a"
								$Customer["DateLastModified"](true)) : ""?></td>
				</tr>
				<?
			}
		}
		?>
	</tbody>
</table>
<?

', 'java');
DisplayHelper::PrintSourceLink("https://github.com/cirkuitnet/PHP-SmartDB/blob/master/samples/basic/list-all-rows.php");
DisplayHelper::PrintTemplateBottom();
?>