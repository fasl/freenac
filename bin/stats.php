#!/usr/bin/php
<?php
/**
 * /opt/nac/bin/stats
 *
 * This script counts the number of systems that were on the network in the last day. 
 *
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package                     FreeNAC
 * @author                      Héctor Ortiz (FreeNAC Core Team)
 * @copyright                   2006 FreeNAC
 * @license                     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version                     SVN: $Id$
 * @link                                http://www.freenac.net
 *
 */

require_once "funcs.inc.php";
db_connect();

$query="select id from vstatus";
$res=mysql_query($query);
if ($res)
{
   while ($result=mysql_fetch_array($res,MYSQL_ASSOC))
   {
      if ($result['id']==0)
         continue;
      $total=0;
      $total=v_sql_1_select("select count(*) from systems where status='".$result['id']."' and date_sub(CURDATE(),interval 1 day) <= LastSeen;");
      if ($total && ($total>0))
      {
         $query="insert into stat_systems set date=curdate(), vstatus='".$result['id']."', count='$total';";
         mysql_query($query);
      }
   } 
}

?>
