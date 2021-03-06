#!/usr/bin/php
<?php
/**
 * /opt/nac/bin/updates
 *
 * Long description for file:
 * Informs system administrator if an update is available
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package                     FreeNAC
 * @author                      Héctor Ortiz (FreeNAC Core Team)
 * @copyright                   2007 FreeNAC
 * @license                     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 *. @version                    SVN: $Id$
 * @link                        http://www.freenac.net
 *
 */

chdir(dirname(__FILE__));
set_include_path("./:../");

require_once('bin/funcs.inc.php');

$logger->setDebugLevel(0);
#$logger->setLogToStdErr();

$new_revision=trim(@file_get_contents('http://www.freenac.net/updates/revision'));

if ($new_revision)
{
   $file_loaded=file_get_contents('../.svn/entries');
   if ($file_loaded)
   {
      $nac_root=realpath('..');
      $xml = @simplexml_load_string($file_loaded); 
      $installed_revision=$xml->entry['revision'];
      if ($new_revision > $installed_revision)
      {
         $subject="New revision $new_revision is available. Please go to http://www.freenac.net to see the list of changes";
         logit($subject);
         if ($send_mail_if_updates)
         {
            $message="To upgrade your system to the newest revision, go to the directory $nac_root and type:\n\n\tsvn update";
            mail($mail_user,$subject,$message);
         }
      }
   }
}


?>
