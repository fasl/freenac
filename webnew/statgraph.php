<?php
/**
 *
 * statgraph.php
 *
 * Long description for file:
 *
 * @package     FreeNAC
 * @author      Core team, Originally T.Dagonnier
 * @copyright   2008 FreeNAC
 * @license     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 3
 * @version     SVN: $Id: find.php,v 1.1 2008/02/22 13:04:57 root Exp root $
 * @link        http://freenac.net
 *
 */

## Initialise (standard header for all modules)
  dir(dirname(__FILE__)); set_include_path("./:../lib:../");
  require_once('webfuncs.inc');
  $logger=Logger::getInstance();
  $logger->setDebugLevel(3);

  ## Loggin in? User identified?
  include 'session.inc.php';
  check_login(); // logged in?
  #$logger->debug('Start, uid=' .$_SESSION['uid'], 3);
## end of standardc header ------


include_once('graphdefs.inc');
// Clean inputs from the web, (security)
   $_GET=array_map('validate_webinput',$_GET);
   $_POST=array_map('validate_webinput',$_POST);
   $_COOKIE=array_map('validate_webinput',$_COOKIE);


$graphtype = $_GET["graphtype"];
$stattype =  $_GET["stattype"];
$order =  $_GET["order"];

include_once($conf->web_jpgraph.'/jpgraph.php');
include_once($conf->web_jpgraph.'/jpgraph_'.$graphtype.'.php');


// 1. Check rights
if ($_SESSION['nac_rights']<1) {
  throw new InsufficientRightsException($_SESSION['nac_rights']);
}
else if ($_SESSION['nac_rights']==1) {
  $action_menu='';
}
else if ($_SESSION['nac_rights']==2) {
  $action_menu='';
  //$action_menu=array('Print','Edit');   // 'buttons' in action column
}
else if ($_SESSION['nac_rights']==99) {
  $action_menu='';
  //$action_menu=array('Print', 'Edit', 'Delete');   // 'buttons' in action column
}



function cbFmtPercentage($aVal) {
    	return sprintf("%.0f",$aVal); // Convert to string
};




  // Query the data
  $obj= new Common();
  $conn=$obj->getConnection();     //  make sure we have a DB connection
  $q = $sel[$stattype]['graph']." ORDER BY count(*) $order;";
  $res = $conn->query($q);
  if ($res === FALSE)
       throw new DatabaseErrorException($q .'; ' .$conn->error);

  while (($row = $res->fetch_assoc()) !== NULL) {
	      $data[] = $row["count"];
	      $data_names[] = $row["datax"]; //." (%.0f%%)";
  }
		

	if ($graphtype == 'bar') {
		$graph = new Graph(800,400);

		// Create the graph. 
		$bar1 = new BarPlot($data);

		$graph->SetScale("textlin");
		$graph->xaxis->SetFont(FF_VERDANA,FS_NORMAL,8);
		$graph->yaxis->SetFont(FF_VERDANA,FS_NORMAL,8);

		$graph->xaxis->SetTickLabels($data_names);
		$graph->xaxis->SetLabelAngle(45);

		$bar1->SetFillGradient("navy","lightsteelblue",GRAD_MIDVER);
		$bar1->value->SetFont(FF_VERDANA,FS_NORMAL,8);

		$bar1->value->SetFormatCallback("cbFmtPercentage");
		$bar1->value->Show();

		// Add the plot to the graph
		$graph->Add($bar1);

	} 
        else if ($graphtype == 'pie') {
	  	   $graph = new Graph(500,500);
		   $graph = new PieGraph(800,400);//,$filename,60);
		   $graph->SetShadow();

	//	   $graph->SetSize(0.4);
		// Set A title for the plot
	//	   $graph->title->Set($PIE_TITLE);
	//	   $graph->title->SetFont(FF_FONT1,FS_BOLD);

		// Create
		   $p1 = new PiePlot($data);
		   $p1->SetCenter(0.35,0.5);
	//	   $p1->SetLegends($data_names);
	//	   $p1->SetLabelType(PIE_VALUE_PER);
	 	   $p1->SetLabels($data_names);
		   $p1->SetTheme("sand");
		   $p1->value->SetFont(FF_VERDANA,FS_NORMAL,8);
	 
		   $graph->Add($p1);
	};

	$graph->Stroke();

?> 
