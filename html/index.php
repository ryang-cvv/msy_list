<?php

require("config.inc.php");
require("Database.class.php");

function sortPrint($val) {
	$start = " <a class='arrows' href='?grp_id={$_GET['grp_id']}&sort=";
	return "{$start}{$val}&o=asc'>&darr;</a>{$start}{$val}&o=desc'>&uarr;</a>";
}

# Test known variables
$int_vars = array('grp_id', 'prod_id');
foreach($int_vars as $var_name) {
	if(isset($_GET[$var_name])) {
		if(strcmp(intval($_GET[$var_name]),$_GET[$var_name])) {
			header("Location: ?{$var_name}=".intval($_GET[$var_name]));
			exit;
		}
	}
}


if($_GET['sh_tog'] == 1) {
	if($_GET['sh'] == 'on') {
		// Toggled on, add cookie
		setcookie("sh",$_GET['sh']);
	} else {
		// Toggled off, delete cookie
		setcookie("sh",$_GET['sh'],time() - 3600);
	}
	header("Location: ".$_SERVER['HTTP_REFERER']);
}

$db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);

$db->connect();

$rows = $db->query("SELECT * FROM groups ORDER BY grp_order ASC");

while($record = $db->fetch_array($rows)) {
	if($record['par_id']) {
		$groups[$record['par_id']]['child'][$record['grp_id']] = $record['grp_desc'];
	} else {
		$groups[$record['grp_id']]['name'] = $record['grp_desc'];
	}
}

$grp_sel = ($_GET['grp_id'] ? "" : "<option selected>- Select group-</option>\n");
$raw_sel = "";
$ul_list = "<div class='menu'>\n<ul>\n";
foreach($groups as $parent => $info) {
	$grp_sel.= "<optgroup label='{$info['name']}'>\n";
	$raw_sel.= "<option value='{$parent}'>{$info['name']}</option>\n";
	$ul_list.= "<li><a href='#'>{$info['name']}</a>\n<ul>\n";
	if(is_array($info['child'])) {
		foreach($info['child'] as $ch_id => $ch_name) {
			$selected = '';
			if($_GET['grp_id'] == $ch_id) { $selected = ' selected'; }
			$grp_sel.= "<option value='{$ch_id}'{$selected}>{$ch_name}</option>\n";
			$raw_sel.= "<option value='{$ch_id}'{$selected}> - {$ch_name}</option>\n";
			$ul_list.= "<li><a href='?grp_id={$ch_id}'>{$ch_name}</a></li>\n";
		}
	}
	$grp_sel.= "</optgroup>\n";
	$ul_list.= "</ul>\n</li>\n";
}
$ul_list.= "</ul>\n</div>\n";

$last_upd = $db->query_first("SELECT date FROM prices ORDER BY date DESC LIMIT 1");

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>MSY Price Dump</title>
<link rel='stylesheet' href='style.css' type='text/css' /> 
<script type="text/javascript" src="scripts.js"></script> 
</head>
<body>
<center>
<form method='put'>
<div class='search'>
<input name='search' type='text' />
</div>
</form>
<? echo $ul_list; ?>
<br />
<?
if($_GET['prod_id']) {
	/* ### PRODUCT DISPLAY ### */

$prod_id = intval($_GET['prod_id']);

$date_hist = date("Y-m-d",strtotime("-".MAX_HISTORY));

$max_hist_txt = MAX_HISTORY;

$rows = $db->query("SELECT * FROM models LEFT JOIN prices on prices.prod_id = models.prod_id
	WHERE models.prod_id = ".$db->escape($prod_id)." AND date > '".$db->escape($date_hist)."' 
	ORDER BY date ASC, price ASC ");

if($db->affected_rows == 0) {
	$max_hist_txt = "All";
	$rows = $db->query("SELECT * FROM models LEFT JOIN prices on prices.prod_id = models.prod_id
		WHERE models.prod_id = ".$db->escape($prod_id)."
		ORDER BY date ASC, price ASC ");
}

$price_history = "";
while($record = $db->fetch_array($rows)) {
	$date = $record['date'];
	if($record['notes']) {
		#$notes = "<sup>* {$record['notes']}</sup>";
		$notes = "*";
		if($date == $last_upd['date']) { 
			# Make a list of current clearance outlets
			$clear_list.= (empty($clear_list) ? "" : "<br />\n")."{$record['notes']}";
		}
	} else {
		$notes = "";
	}

	if($record['price'] == $prev_price) {
		continue;
	} else {
		if($record['price'] > $prev_price) {
			# Increased
			$pr_colour = 'red';
		} else {
			# Decreased
			$pr_colour = 'green';
		}
		$prev_price = $record['price'];
	}

	if(empty($price_history)) {
		$prod_name = $record['prod_desc'];
		$pr_colour = 'black';
	}

	$price_history.= "<tr><td>{$date}</td><td><font color='{$pr_colour}'>\${$prev_price}</font> {$notes}</td></tr>\n";
}

if(!empty($clear_list)) { $notes = "<a href='#' class='info'>{$notes}<span>{$clear_list}</span></a>"; }

$text_price = "Current price: \${$prev_price} {$notes} @ {$date}";

echo "<h2>{$prod_name}</h2>\n<hr />
<h3>".($date == $last_upd['date'] ? "{$text_price}" : "<font color='red' title='Not Current!!'>{$text_price}</font>")."</h3>
<h3>Price history [".$max_hist_txt."]:</h3>
<div style='float:left;width:50%;'><img src='graph.php?prod_id={$prod_id}' border='1' /></div>
<div style='float:right;width:50%;'><table>\n<tr><th style='width:160px;'>Date</th><th>Price</th></tr>
{$price_history}
</table></div>\n";

} else {

if($_GET['grp_id']) {
	/* ### GROUP DISPLAY ### */
	$grp_id = intval($_GET['grp_id']);

	$grp_info = $db->query_first("SELECT grp_desc FROM groups WHERE grp_id=".$db->escape($grp_id));

	if($_COOKIE['sh'] == 'on') {
		// Show hidden products
		$sh_state = "checked";
		$prod_query = "";
	} else {
		// Leave hidden...
		$sh_state = "";
		$prod_query = " AND prices.date = '".$last_upd['date']."'";
	}

	echo "<div class='sh'>\n<form method='put'>\n<input type='hidden' name='sh_tog' value='1' />\n<input type='checkbox' name='sh' ".$sh_state." onchange='form.submit();' title='Show Hidden' />\n</form>\n</div>\n";
	echo "<h2>{$grp_info['grp_desc']}</h2>\n<hr />\n";

	if($_GET['sort'] && $_GET['o']) {
		$order_by = $db->escape($_GET['sort'])." ".(strtolower($_GET['o']) == "asc" ? "ASC" : "DESC");
	} else {
		$order_by = "price ASC";
	}

	$rows = $db->query("SELECT prices.prod_id, models.prod_desc, prices.price, prices.date
		FROM (SELECT max(price_id) as max_id FROM prices GROUP BY prod_id) as tmax
		LEFT JOIN prices ON prices.price_id = tmax.max_id
		LEFT JOIN models ON models.prod_id = prices.prod_id
		WHERE models.grp_id = ".$db->escape($_GET['grp_id'])." ".$prod_query."
		ORDER BY {$order_by}");

	echo "<table>\n";
	echo "<tr><th>Description ".sortPrint('prod_desc')."</th><th>Price ".sortPrint('price')."</th></tr>\n";
	while($record = $db->fetch_array($rows)) {
		$current = 1;
		if($last_upd['date'] != $record['date']) {
			# Not a current price, indicate
			$current = 0;
		}
	        echo "<tr".(!$current ? " class='old'" : "")."><td><a href='?prod_id={$record['prod_id']}'>{$record['prod_desc']}</a></td><td>\${$record['price']}</td></tr>\n";
	}
	echo "</table>\n";

} elseif($_GET['search']) {

	/* ### SEARCH BLOCK ### */
	/* Same layout as grp_id results */
	$search_text = trim($db->escape($_GET['search']));
	$search_str = "";
	echo "<h2>Search results for: '{$search_text}' </h2>\n";

	if(strpos($search_text," ") !== false) {
		foreach(explode(" ",$search_text) as $txt) {
			$search_str.= (empty($search_str) ? "" : "AND ")."models.prod_desc LIKE '%{$txt}%' ";
		}
	} else {
		$search_str = "models.prod_desc LIKE '%{$search_text}%' ";
	}

	if($_COOKIE['sh'] == 'on') {
		// Show hidden products
		$sh_state = "checked";
		$prod_query = "";
	} else {
		// Leave hidden...
		$sh_state = "";
		$prod_query = " AND prices.date = '".$last_upd['date']."'";
	}

	echo "<div class='sh'>\n<form method='put'>\n<input type='hidden' name='sh_tog' value='1' />\n<input type='checkbox' name='sh' ".$sh_state." onchange='form.submit();' title='Show Hidden' />\n</form>\n</div>\n";
	echo "<h2>{$grp_info['grp_desc']}</h2>\n<hr />\n";

	if($_GET['sort'] && $_GET['o']) {
		$order_by = $db->escape($_GET['sort'])." ".(strtolower($_GET['o']) == "asc" ? "ASC" : "DESC");
	} else {
		$order_by = "price ASC";
	}

	$rows = $db->query("SELECT prices.prod_id, models.prod_desc, prices.price, prices.date
		FROM (SELECT max(price_id) as max_id FROM prices GROUP BY prod_id) as tmax
		LEFT JOIN prices ON prices.price_id = tmax.max_id
		LEFT JOIN models ON models.prod_id = prices.prod_id
		WHERE ".$search_str.$prod_query."
		ORDER BY {$order_by}");

	echo "<table>\n";
	echo "<tr><th>Description ".sortPrint('prod_desc')."</th><th>Price ".sortPrint('price')."</th></tr>\n";
	while($record = $db->fetch_array($rows)) {
		$current = 1;
		if($last_upd['date'] != $record['date']) {
			# Not a current price, indicate
			$current = 0;
		}
	        echo "<tr".(!$current ? " class='old'" : "")."><td><a href='?prod_id={$record['prod_id']}'>{$record['prod_desc']}</a></td><td>\${$record['price']}</td></tr>\n";
	}
	echo "</table>\n";
} else {
	echo "<h2>MSY Price History</h2>\n<hr />\n<h3>Last updated: {$last_upd['date']}</h3>\n\n";
	echo "<h1> ** NOTE: ** Groups are no longer being managed, I don't have the time.<br />\nI have added a search option though, which should provide some added functionality to offset the group death..</h1>\n";
}

# Close prod_id if-else
}

?>
</center>
<br />
</body>
</html>
<?php

$db->close();

?>
