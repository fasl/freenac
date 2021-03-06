<?php
/**
 * Common.php
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package                     FreeNAC
 * @author                      Hector Ortiz (FreeNAC Core Team)
 * @copyright           	2007 FreeNAC
 * @license                     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version                     SVN: $Id$
 * @link                        http://www.freenac.net
 */

/**
 * Define this common parent class to ensure consistent logging and access to configuration settings.
 * This class can be extended with 'common' code to simplify derived classes and ensure consistency.
*/
class Common {
   public $conf, $logger, $db_conn;      // allow access outside derived classes, ideally would be read-only
   
   /**
   * Get the current instance of our Settings and Logger classes
   */
   public function __construct()
   {
      $this->conf=Settings::getInstance();
      $this->logger=Logger::getInstance();
   }	

   /**
   * Connect to database via mysqli
   */
  public function getConnection()
  {
    global $dbhost, $dbuser, $dbpass, $dbname;
    try {
      $this->db_conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
      if (mysqli_connect_errno() !== 0) {
        throw new DatabaseErrorException(mysqli_connect_error());
      }
      //$this->logger->debug( get_class($this). " getConnection() to {$dbname} on $dbhost.", 3);

    } catch (Exception $e) {
      if ($in_db_conn === NULL and isset($conn))
        $conn->close();
      throw $e;
    }
    return $this->db_conn;
  }


  /**
   * htmlescape: escape date before outputting to a Browser
   */
  public function htmlescape($in_string)
  {
    #return htmlentities($in_string, ENT_QUOTES, 'UTF-8');
    return htmlentities($in_string, ENT_QUOTES, 'ISO-8859-1');
  }

  /**
   * sqlescape: call real_escape_string1 with DB connection
   */
  public function sqlescape ($in_string, $in_removePct=FALSE)
  {
    $conn=$this->getConnection();     //  make sure we have a DB connection
    return $this->real_escape_string1($in_string, $conn, $in_removePct);
  }


  /**
   * Escape possibly dangerous characters, to prevent SQL injection
   * Trim leading/trailing spaces too
   * TBD: what about ';'?
   */
  public function real_escape_string1 ($in_string, $in_conn, $in_removePct=FALSE)
  {
    $this->logger->debug("real_escape_string1: in_string=$in_string,", 3);
    $str=$in_string;

    if (!is_numeric($str)){
      // mysqli: prepends backslashes to: \x00, \n, \r, \, ', " and \x1a
      $str = $in_conn->real_escape_string($str);

      if ($in_removePct)     // escape %
        $str = ereg_replace('(%)', "\\\1'", $str);
    }

    $this->logger->debug("real_escape_string1: ret=$str", 3);
    #return rtrim($str);
    return trim($str);
  }

/**
 * Write key events to naclog which is visible from the GUI
 * To view recent entries:
 *   select * from naclog ORDER BY datetime DESC LIMIT 5;
 * @param mixed $level   Level of severity of the message
 * @param mixed $msg     Message to log
*/
function log2db($level='info', $msg='')
{
     $conn=$this->getConnection();     //  make sure we have a DB connection

     $msg=rtrim($msg);

       if (isset($_SERVER['HOSTNAME']))
          $host=$_SERVER['HOSTNAME'];
       else if (isset($_ENV['HOSTNAME']))
          $host=$_ENV['HOSTNAME'];     
       //else do uname -n?     
       else
          $host='';
          
       $q="insert into naclog set what='" .sqlescape($msg)
         ."', host='$host', priority='$level'";
         $this->debug($q, 3);
         $res = $conn->query($q);
       if ($res === FALSE)
          throw new DatabaseErrorException($conn->error);
         
       return true;
}

}
