#!/usr/bin/php -- -f
<?php
/**
 * contrib/snmp_scan.php
 *
 * Long description for file:
 * This script will scan all existing switches & routers using SNMP
 * It focuses on getting informations about systems who are not managed
 * or/and on static access ports. 
 * Such systems are typically critical servers, network equipment, ...
 * It is complementary to the snmp_import script
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package			FreeNAC
 * @author			Thomas Dagonnier - Sean Boran (FreeNAC Core Team)
 * @copyright			2006 FreeNAC
 * @license			http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version			CVS: $Id:$
 * @link			http://www.freenac.net
 *
 */


# Php weirdness: change to script dir, then look for includes
chdir(dirname(__FILE__));
set_include_path("../:./");
require_once "bin/funcs.inc";               # Load settings & common functions
require_once "snmp_defs.inc";

define_syslog_variables();              # not used yet, but anyway..
openlog("snmp_import.php", LOG_PID, LOG_LOCAL5);

db_connect();


// Enable debugging to understand how the script works
  $debug_flag1=false;
  $debug_flag2=false;
  $debug_to_syslog=FALSE;
// allow performance measurements
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $starttime = $mtime;


if ($snmp_dryrun) {
  $debug_flag2=true;
  $domysql=false;
} else {
  $domysql=true;
};


function print_usage() {
	echo "snmp_scan.php - Usage\n";
	echo " -switch name - only scan given switch (require switch name)\n";
	echo " -vlan name - only scan given vlan (require vlan name)\n";
	echo " -help - print usage\n";
	echo " (no args) : will scan all switches and all vlans\n";
	echo "\n";
};
	$newswitch = FALSE;

	for ($i=0;$i<$argc;$i++) {
	   if ($argv[$i]=='-switch') {   // even if user gives --switch we see -switch
		  $singlesw = TRUE;
		  $singleswitch = mysql_real_escape_string($argv[$i+1]);
		};
           if ($argv[$i]=='-vlan') {   
                  $singlevl = TRUE;
                  $singlevlan = mysql_real_escape_string($argv[$i+1]);
                };
	   if ($argv[$i]=='-help') {   // even if user gives --switch we see -switch
			print_usage();
			exit();
		};
	};

if (!$singlesw) {
    $switches =  mysql_fetch_all("SELECT * FROM switch");
	debug1("Scanning all switches in the Database");
} else {
    $switches =  mysql_fetch_all("SELECT * FROM switch WHERE name='$singleswitch'");
	debug1("Scanning one switch: $singleswitch");
};
if (!$singlevl) {
	$vlans =  mysql_fetch_all("SELECT * FROM vlan WHERE id != 0");
	debug1("Scanning all VLAN");
} else {
	$vlans = mysql_fetch_all("SELECT * FROM vlan WHERE value='$singlevlan'");
	debug1("Scanning one vlan : $singlevlan");
};



	foreach ($switches as $switchrow) {
		$switch = $switchrow['ip'];
		$switch_ifaces = walk_ports($switch,'public');
		foreach ($vlans as $vlan) {
			$vlanid = $vlan['id'];
			$macs = walk_macs($switch,$vlanid,$snmp_ro);
			foreach ($macs as $idx => $mac) {
				if (($mac['trunk'] != 1) && !(preg_match("$router_mac_ip_ignore_mac", $mac['mac']))) {
					if (mac_exist($mac['mac'])) {
						$query = "UPDATE systems SET switch='$switch', port='".$mac['port']."', LastSeen=NOW() ";
						$query .= "WHERE mac='".$mac['mac']."';";
						debug2($switch." - ".$mac['port']." - ".$mac['mac']." - Insert host ");
					} else {
						$query = 'INSERT INTO systems (name, mac, switch, port, vlan, status) VALUES ';
						$query .= "('unknown','".$mac['mac']."','$switch','".$mac['port']."',$vlanid,3);";
						debug2($switch." - ".$mac['port']." - ".$mac['mac']." - Update host ");
					};
					if($domysql) { mysql_query($query) or die("unable to query"); };
					unset($query);
				};
			};
		};
	};
# $router_mac_ip_ignore_mac
 // measure performance
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $endtime = $mtime;
   $totaltime = ($endtime - $starttime);
   debug1("Time taken= ".$totaltime." seconds\n");
   #logit("Time taken= ".$totaltime." seconds\n");
?>
