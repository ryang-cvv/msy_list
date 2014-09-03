<?php

require("../config.inc.php");
require("../Database.class.php");

$db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);

$db->connect();

if($_GET['ord_grp']) {
	$rows = $db->query("SELECT * FROM groups WHERE par_id = {$_GET['ord_grp']} OR grp_id = {$_GET['ord_grp']} ORDER BY grp_order ASC");
} else {
	$rows = $db->query("SELECT * FROM groups ORDER BY grp_order ASC");
}

while($record = $db->fetch_array($rows)) {
	if($record['par_id']) {
		$groups[$record['par_id']]['child'][$record['grp_id']] = $record['grp_desc'];
	} else {
		$groups[$record['grp_id']]['name'] = $record['grp_desc'];
	}
	$raw_grp[$record['grp_id']] = $record;
}

$grp_sel = "";
$raw_sel = "";
$tbl_inp = "<table>\n";
foreach($groups as $parent => $info) {
	$grp_sel.= "<optgroup label='{$info['name']}'>\n";
	$raw_sel.= "<option value='{$parent}'>{$info['name']}</option>\n";
	$tbl_inp.= "<tr><th>{$info['name']}</th><td><input type='text' name='grp_ord[{$parent}]' value='{$raw_grp[$parent]['grp_order']}' /></td></tr>\n";
	if(is_array($info['child'])) {
		foreach($info['child'] as $ch_id => $ch_name) {
			$grp_sel.= "<option value='{$ch_id}'>{$ch_name}</option>\n";
			$raw_sel.= "<option class='child' value='{$ch_id}'>{$ch_name}</option>\n";
			$tbl_inp.= "<tr><td>{$ch_name}</th><td><input type='text' name='grp_ord[{$ch_id}]' value='{$raw_grp[$ch_id]['grp_order']}' /></td></tr>\n";
		}
	}
	$grp_sel.= "</optgroup>\n";
}
$tbl_inp.= "</table>\n";

// Reorder groups
if($_POST['grp_order']) {
	foreach($_POST['grp_ord'] as $grp_id => $grp_order) {
		$db->query("UPDATE groups SET grp_order = {$grp_order} WHERE grp_id = {$grp_id}");
	}
	header("Location: {$_SERVER["SCRIPT_URI"]}");
}

// Add group
if($_POST['grp_desc']) {
	$par_id = $db->escape($_POST['par_id']);
	#$par_info = $db->query_first("SELECT * FROM groups WHERE grp_id = {$par_id}");
	$ord_row = $db->query_first("SELECT grp_order FROM groups WHERE par_id = {$par_id} OR grp_id = {$par_id} ORDER BY grp_order DESC LIMIT 1");

	if(!empty($_POST['par_id'])) {
		$ins_data['grp_order'] = $ord_row['grp_order']+1;
	} else {
		$ins_data['grp_order'] = $ord_row['grp_order']+100;
	}
	$ins_data['par_id'] = $par_id;
	$ins_data['grp_desc'] = $_POST['grp_desc'];
	
	$ins_id = $db->query_insert('groups',$ins_data);
	header("Location: {$_SERVER["SCRIPT_URI"]}");
}

// Delete group
if($_GET['del_grp']) {
	$ins_id = $db->query("DELETE FROM groups WHERE grp_id = ".$db->escape($_GET['del_grp']));
	$row = $db->query("UPDATE models SET grp_id = 0 WHERE grp_id = ".$db->escape($_GET['del_grp']));
	header("Location: {$_SERVER["SCRIPT_URI"]}");
}

// Rename group
if($_GET['ren_grp']) {
	$upd_data['grp_desc'] = $_GET['name'];
	$upd_id = $db->query_update("groups", $upd_data, "grp_id = ".$db->escape($_GET['ren_grp']));
	header("Location: {$_SERVER["SCRIPT_URI"]}");
}

// Move item to group
if($_POST['grp_id'] && $_POST['prod_id']) {
	$upd_ids = join(',',$_POST['prod_id']);
	$upd_data['grp_id'] = $_POST['grp_id'];
	if($_POST['grp_id'] == '9999') {
		// Delete item
		$del_id = $db->query("DELETE models,prices FROM models,prices WHERE models.prod_id IN (".$db->escape($upd_ids).") AND prices.prod_id IN (".$db->escape($upd_ids).")");
	} else {
		// Normal group, move
		$upd_lot = $db->query_update("models", $upd_data, "prod_id IN (".$db->escape($upd_ids).")");
	}
	header("Location: {$_SERVER["SCRIPT_URI"]}");
}



?>
<html>
<head>
<title>MSY Price Dump</title>
<link rel="stylesheet" type="text/css" href="style.css" />
<script src="http://code.jquery.com/jquery-latest.min.js"></script>
<script type="text/javascript">
<!--
$(document).ready(function() {
	$('#rowClick tr')
		.filter(':has(:checkbox:checked)')
		.addClass('selected')
		.end()
	.click(function(event) {
		$(this).toggleClass('selected');
		if (event.target.type !== 'checkbox') {
			$(':checkbox', this).attr('checked', function() {
				return !this.checked;
			});
		}
	});
});

function delGroup() {
	grp = document.new_grp.par_id
	grp_name = grp.options[grp.selectedIndex].text
	grp_id = grp.options[grp.selectedIndex].value
	if(grp_id == 0) {
		alert("Unable to terminate patient zero.")
	} else {
		var del = confirm("Are you sure you wish to delete '" + grp_name + "' and all of it's children?")
		if(del) {
			window.location="?del_grp="+grp_id
		}
	}
}
function renGroup() {
	grp = document.new_grp.par_id
	grp_name = grp.options[grp.selectedIndex].text
	grp_id = grp.options[grp.selectedIndex].value
	var ren = prompt("Enter the new name for this group:",grp_name)
	if(ren!=null && ren!="" && ren!=grp_name) {
		window.location="?ren_grp="+grp_id+"&name="+ren
	}
}
function ordGroup() {
	grp = document.new_grp.par_id
	grp_id = grp.options[grp.selectedIndex].value
	window.location="?ord_grp="+grp_id
}

//-->
</script>
</head>
<body>
<?
if($_GET['ord_grp']) {
?>
<form name='grp_order' method='post'>
<? echo $tbl_inp; ?>
<input type='submit' name='grp_order' />
</form>
<?
} else {
?>
<h2>Groups</h2>
<form name='new_grp' method='post'>
<select name='par_id'>
<option value='0'> - None - </option>
<? echo $raw_sel; ?>
</select>
<a href='javascript:renGroup()'>E</a>
<a href='javascript:delGroup()'>X</a>
<a href='javascript:ordGroup()'>O</a>
<input type='text' name='grp_desc' />
<input type='submit' />
</form>
<hr />
<h2>Products</h2>
<form name='prod_picker' method='post'>
Dump to group: 
<select name='grp_id'>
<option value='9999'>- Delete -</option>
<? echo $grp_sel; ?>
</select>
<table id='rowClick'>
<?

$rows = $db->query("SELECT * FROM models WHERE grp_id = 0 LIMIT 20");

while($record = $db->fetch_array($rows)) {
	echo "<tr><td><input type='checkbox' name='prod_id[]' value='{$record['prod_id']}' class='checkBox' /></td><td>{$record['prod_desc']}</td></tr>\n";
}

?>
</table>
 <input type='checkbox' name='prod_all' id='checkAll' /> <input type='submit' name='add_prods' value='Add To Group' />
</form>
<?

# Close ord_grp if
}
?>
</body>
<script type="text/javascript">
	$('#checkAll').click(function () {
		if (this.checked == false) {
			$('.checkBox:checked').attr('checked', false);
			$('#rowClick tr').removeClass('selected');
		} else {
			$('.checkBox:not(:checked)').attr('checked', true);
			$('#rowClick tr').addClass('selected');
		}
	});
</script>
</html>
<?php

$db->close();

?>
