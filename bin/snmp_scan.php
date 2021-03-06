#!/usr/bin/php
<?php
/**
 * contrib/snmp_scan.php
 *
 * Long description for file:
 * This script will scan all existing switches & routers using SNMP
 * It focuses on getting information about systems who are not managed
 * or/and on static access ports. With this information, NAC then has
 * an overview of all systems on the network, providing the Network
 * manager with a more complete picture. This non-vmps-managed systems
 * can still be scanned, and their Anti-Virus status shown.
 * Such non-managed are typically critical servers, network equipment,
 * VirtualServers, systems with static vlan ports ...
 *
 * Newly discovered devices are inserted into the systems table
 *   as status=3, name=unknown, and update mac,lastseen,switch,port
 *   For existing systems; lastseen,switch,port is updated.
 *
 * USAGE :
 *   -switch name - only scan given switch (require switch name)
 *   -vlan name - only scan given vlan (require vlan name)
 *   -help - print usage
 *   (no args) : will scan all switches and all vlans
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
set_include_path("../:./:/opt/nac/");
require_once "./funcs.inc.php";               # Load settings & common functions
require_once "./snmp_defs.inc.php";

$logger->setDebugLevel(0);
$logger->setLogToStdOut(false);

db_connect();

$singlesw = NULL;
$singlevl = NULL;
// Enable debugging to understand how the script works
$debug_to_syslog=true;
// allow performance measurements
$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;


function print_usage()
{
   global $logger;
   $logger->logit("snmp_scan.php - Usage\n");
   $logger->logit(" -switch [name] - only scan a given switch (require switch name)\n");
   $logger->logit(" -vlan [name]   - only scan a given vlan (require vlan default_name)\n");
   $logger->logit(" -listvlans     - list all vlans\n");
   $logger->logit(" -help          - print usage\n");
   $logger->logit(" (no args) : will scan all switches and all vlans\n");
   $logger->logit("\n");
};

function print_vlans() 
{
   global $snmp_ro;
   global $logger;
   $switches =  mysql_fetch_all("SELECT * FROM switch");
   $vlan = array();
   if ( ! $switches )
      return false;
   foreach ($switches as $switchrow) 
   {
      $switchid = $switchrow['id'];
      $switchip = $switchrow['ip'];
      $switch_name = $switchrow['name'];
      $switch_vlans = walk_vlans($switch,$snmp_ro);
      if ( ! is_array($switch_vlans) )
         continue;
      foreach ($switch_vlans as $idx => $svalue) 
      {
         if ( ! isset($switch_vlans[$idx]['type']) || ! isset($switch_vlans[$idx]['state']) || ! isset($switch_vlans[$idx]['name']) )
            continue;
         if (($switch_vlans[$idx]['type'] == 1) && ($switch_vlans[$idx]['state'] == 1)) 
         {
            $vlan_name = $switch_vlans[$idx]['name'];
            $vlan[$vlan_name][$idx] .= $switch_name.',';
         };
      }; //foreach ($switch_vlans as $idx => $svalue)
   }; //foreach ($switches as $switchrow)
   foreach ($vlan as $vlan_name => $vlan_ids) 
   {
      $first = TRUE;
      foreach ($vlan_ids as $vlan_id => $switches) 
      {
         if (count($vlan_ids) == 1) 
         {
            $logger->logit("$vlan_id\t");
            printf("%-16s",  substr($vlan_name,0,14));
            $logger->logit("\t- Switches : ".rtrim($switches,',')."\n");
         }
         else
         {
            if (!$first) 
            {
               $logger->logit("VLAN $vlan_name :\n");
               $first = FALSE;
            };
            $logger->logit(" :\n");
            $logger->logit("\t - $vlan_id : ".rtrim($switches,',')."\n");
         }; //if (count($vlan_ids) == 1)
      }; //foreach ($vlan_ids as $vlan_id => $switches)
   }; //foreach ($vlan as $vlan_name => $vlan_ids)
}; //function print_vlans()

$newswitch = FALSE;

for ($i=0;$i<$argc;$i++) 
{
   if ($argv[$i]=='-switch') 
   {   // even if user gives --switch we see -switch
      $singlesw = TRUE;
      $singleswitch = mysql_real_escape_string($argv[$i+1]);
   };
   if ($argv[$i]=='-listvlans') 
   {
      print_vlans();
      exit();
   };
   if ($argv[$i]=='-vlan') 
   {   
      $singlevl = TRUE;
      $singlevlan = mysql_real_escape_string($argv[$i+1]);
   };
   if (($argv[$i]=='-help') || ($argv[$i]=='-h') ) 
   {   // even if user gives --switch we see -switch
      print_usage();
      exit();
   };
};

if (!$singlesw) 
{
   $switches =  mysql_fetch_all("SELECT * FROM switch WHERE scan=1");
   $logger->logit("Scanning all switches in the Database");
   log2db("info", "Scanning all switches in the Database");
} 
else 
{
   $switches =  mysql_fetch_all("SELECT * FROM switch WHERE name='$singleswitch'");
   $logger->logit("Scanning one switch: $singleswitch");
   log2db('info', "Scanning only one switch: $singleswitch");
};

if (!$singlevl) 
{
   $vlans =  mysql_fetch_all("SELECT * FROM vlan WHERE default_id != 0");
   # default, don't need to log
   $logger->debug("Scanning all VLANs");
} 
else
{
   $vlans = mysql_fetch_all("SELECT * FROM vlan WHERE default_name='$singlevlan'");
   $logger->logit("Scanning only one vlan : $singlevlan");
   log2db('info', "Scanning only one vlan : $singlevlan");
};


if (is_array($switches))
{
   foreach ($switches as $switchrow) 
   {
		
      $switchid = $switchrow['id'];
      $switchip = $switchrow['ip'];

      $logger->logit("Start scanning  ($switchid) $switchip ");
      $switch_ifaces = walk_ports($switchip,$snmp_ro);
      if (!$switch_ifaces)
         continue;
      // first, switch details
      foreach ($switch_ifaces as $if)
      {
         $port_type='';
         if ($if['phys']==1)		//Port type?
         {
            if ($if['trunk']==1)	//Trunk
               $port_type='trunk';
            else if ($if['type']==1)	//Static
               $port_type='static'; 
            else if ($if['type']==2)	//Dynamic
               $port_type='dynamic';
            $type_id=v_sql_1_select("select * from auth_profile where method='$port_type'");	//Get the id from auth_profile
            if (!$type_id)		//If we didn't get an id, set it to 0
               $type_id=0;
            $vlan='';
            if ($if['vlan']&&($if['vlan']>1))
            {
               $vlan=v_sql_1_select("select id from vlan where default_id='".$if['vlan']."'");
               if ( ! $vlan )
                  $vlan = '';
            }
            $query="select * from port where name='".$if['name']."' and switch='$switchid';";
            $res=mysql_query($query);
            if ($res)		 	//Is this port in the DB?
            {
               $result=mysql_fetch_array($res,MYSQL_ASSOC);
               if ($result['id'])	//Yes, update its comment and its auth_profile
               {
                  if ($result['comment'])
                     $comment=$result['comment'];
                  else 
                     $comment=mysql_real_escape_string($if['description']);
                  if (($port_type=='static')&&($vlan))
                     $query="update port set last_auth_profile='$type_id',comment='$comment', up='{$if['ad_status']}', last_vlan='$vlan' where id='".$result['id']."';";
                  else
                     $query="update port set last_auth_profile='$type_id', up='{$if['ad_status']}', comment='$comment' where id='".$result['id']."';";
                  $res=mysql_query($query);
                  if (!$res)
                  {
                     $logger->debug("Port ".$if['name']." on switch $switchid couldn't be updated");
                  }
               }
               else			//No, insert it
               {
                  $comment=mysql_real_escape_string($if['description']);
                  if (($port_type=='static')&&($vlan))
                     $query="insert into port set switch='$switchid', name='".$if['name']."',comment='$comment',last_auth_profile='$type_id',last_vlan='$vlan',up='{$if['ad_status']}'";
                  else
                     $query="insert into port set switch='$switchid', name='".$if['name']."',comment='$comment',last_auth_profile='$type_id',up='{$if['ad_status']}'";
                  $res=mysql_query($query);
                  if (!$res)
                  {
                     $logger->debug("Port ".$if['name']." on switch $switchid couldn't be inserted");
                  } // if (!$res)
               } // if ($result['id'])
            } //  if ($res)
         }; // if ($if['phys']==1)
      } //foreach ($switch_ifaces as $if)
      $sw = mysql_real_escape_string(walk_switchsw($switchip,$snmp_ro));
      $hw = mysql_real_escape_string(walk_switchhw($switchip,$snmp_ro));
      if ($hw || $sw) 
      { 
         //In some switches, the string 'WS' is not found. This string tells us what the hardware is
         //If we don't find the hardware, at least let's update the software we found
         $logger->debug("($switchid) $switchip : HW = $hw / SW = $sw");
         $query = "UPDATE switch SET last_monitored=now(), hw='$hw',sw='$sw' WHERE id=$switchid;";
         $success=mysql_query($query);
         if (! $success)
         {
            $logger->logit("Unable to update switch info", LOG_ERR);
            exit(1);
         } 
      } 
      else 
      {
         $logger->debug("($switchid) $switchip  impossible to get HW or SW");
      };

      // then get hosts
      if ($vlans)
      {
         foreach ($vlans as $vlan) 
         {
            $vlanid = $vlan['default_id'];
            $macs = walk_macs($switchip,$vlanid,$snmp_ro);
            if (count($macs) > 0) 
            {
               foreach ($macs as $idx => $mac) 
               {
                  if ( ! isset($mac['trunk']) || ! isset($mac['mac']) || ! isset($mac['port']) )
                     continue;
                  if (($mac['trunk'] != 1) && !(preg_match($conf->router_mac_ip_ignore_mac, $mac['mac'])) && ($mac['port'] != '')) 
                  {
                     $portid = iface_exist($switchid,$mac['port']);
                     if ($portid) 
                     {
                        $sid = mac_exist($mac['mac']);
                        if ($sid) 
                        {
                           #$query = "UPDATE systems SET LastPort='$portid', LastSeen=NOW() WHERE id=$sid;";
                           $query = "UPDATE systems SET LastPort='$portid', lastvlan='" .get_vlanid($vlanid) ."', LastSeen=NOW() WHERE id=$sid;";
                           $logger->debug("($switchid) ". $switchrow['name'] ." - ".$mac['port']." - ".$mac['mac']." - update host ");
                        } 
                        else
                        {
                           $query = 'INSERT INTO systems (name, mac, LastPort, vlan, status,LastSeen,description) VALUES ';
                           $query .= "('unknown','".$mac['mac']."',$portid,".get_vlanid($vlanid).",3,NOW(),'{$conf->default_user_unknown}');";
                           $logger->debug("($switchid) ". $switchrow['name'] ." - ".$mac['port']." - ".$mac['mac']." - insert new host ");
                        };
                        $success=mysql_query($query);
                        if (! $success)
                        {
                           $logger->logit("Unable to query $query", LOG_ERR);
                           exit(1);
                        } 
                        unset($query);
                     }; // if ($sid)
                  }; // if ($portid)
               }; // foreach ($macs as $idx => $mac)
            }; // if (count($macs) > 0)
         }; //foreach ($vlans as $vlan)
      }; //if ($vlans) 
   }; // foreach ($switches as $switchrow)
} // if (is_array($switches))
else 
{
   $logger->logit("Error - no switch scanned");
};
# $router_mac_ip_ignore_mac
 // measure performance
$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$endtime = $mtime;
$totaltime = ($endtime - $starttime);
$logger->debug("Time taken= ".$totaltime." seconds\n");
#$logger->logit("Time taken= ".$totaltime." seconds\n");
?>
