<?php

require("config.inc.php");
require("Database.class.php");

# Include jpgraph files
require_once ('include/jpgraph/jpgraph.php');
require_once ('include/jpgraph/jpgraph_line.php');
require_once ('include/jpgraph/jpgraph_date.php');


if(isset($_GET['prod_id'])) {
	$prod_id = intval($_GET['prod_id']);
        if(strcmp($prod_id,$_GET['prod_id'])) {
        	# Invalid prod_id, not int
                exit;
        }
} else {
	# No prod_id, exit
	exit;
}

# Assusming safe ID, check if grf exists..

$graph = new Graph(540,300,"auto",60);

$db = new Database(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);

$db->connect();

$date_hist = date("Y-m-d",strtotime("-".MAX_HISTORY));

$rows = $db->query("SELECT * FROM models LEFT JOIN prices on prices.prod_id = models.prod_id
        WHERE models.prod_id = ".$db->escape($prod_id)." AND date > '".$db->escape($date_hist)."'
        ORDER BY date ASC, price ASC ");

if($db->affected_rows == 0) {
$rows = $db->query("SELECT * FROM models LEFT JOIN prices on prices.prod_id = models.prod_id
        WHERE models.prod_id = ".$db->escape($prod_id)." 
        ORDER BY date ASC, price ASC ");
}

$grf_rows = $db->affected_rows;

while($record = $db->fetch_array($rows)) {
        # Each row = price, in reverse date order
	$prod_name = $record['prod_desc'];
	$grf_date[] = strtotime($record['date']);
	$grf_price[] = $record['price'];
	#echo strtotime($record['date']).",".$record['price']."<br />\n";
}

#list($tickPositions,$minTickPositions) = DateScaleUtils::GetTicks($grf_date);

$grace = ($grf_date[$grf_rows-1] - $grf_date[0])*.01;
$xmin = $grf_date[0]-$grace;
$xmax = $grf_date[$grf_rows-1]+$grace;

$ymax = ceil((max($grf_price)*1.1)/10)*10;
$ymin = floor((min($grf_price)*0.9)/10)*10;


$graph->SetMargin(40,40,30,130);

$graph->SetScale('datlin',$ymin,$ymax,$xmin,$xmax);
$graph->SetTickDensity(TICKD_SPARSE,TICKD_VERYSPARSE);
$graph->title->Set(html_entity_decode($prod_name));
 
$graph->xaxis->SetLabelAngle(90);
$graph->xaxis->scale->SetDateFormat('M-d');

$graph->yaxis->SetLabelFormat("$%d");

$line = new LinePlot($grf_price,$grf_date);
$line->mark->SetType(MARK_X);
$line->SetLegend('Price History');
$line->SetFillColor('lightblue@0.5');

$graph->Add($line);
$graph->Stroke();

?>
