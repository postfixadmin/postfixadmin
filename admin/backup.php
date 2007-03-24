<?php
//
// File: backup.php
//
// Template File: -none-
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// -none-
//
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   $path = "/tmp/";
   $filename = "postfixadmin-" . date ("Ymd") . ".sql";
   $backup = $path . $filename;

   $header = "#\n# Postfix Admin $version\n# Date: " . date ("D M j G:i:s T Y") . "\n#\n";

   if (!$fh = fopen ($backup, 'w'))
   {
      $tMessage = "<div class=\"standout\">Cannot open file ($backup)</div>";
      include ("../templates/header.tpl");
      include ("../templates/admin_menu.tpl");
      include ("../templates/message.tpl");
      include ("../templates/footer.tpl");
   } 
   else
   {
      fwrite ($fh, $header);
      
      $tables = array('admin','alias','domain','domain_admins','log','mailbox','vacation');

      for ($i = 0 ; $i < sizeof ($tables) ; ++$i)
      {
         $result = db_query ("SHOW CREATE TABLE $tables[$i]");
         if ($result['rows'] > 0)
         {
            while ($row = db_array ($result['result']))
            {
               fwrite ($fh, "$row[1]\n\n");
            }
         }
      }   

      for ($i = 0 ; $i < sizeof ($tables) ; ++$i)
      {
         $result = db_query ("SELECT * FROM $tables[$i]");
         if ($result['rows'] > 0)
         {
            while ($row = db_assoc ($result['result']))
            {
               foreach ($row as $key=>$val)
               {
                  $fields[] = $key;
                  $values[] = $val;
               }

               fwrite ($fh, "INSERT INTO ". $tables[$i] . " (". implode (',',$fields) . ") VALUES ('" . implode ('\',\'',$values) . "')\n");
               $fields = "";
               $values = "";
            }
         }
      }
   }
   header ("Content-Type: application/octet-stream");
   header ("Content-Disposition: attachment; filename=\"$filename\"");
   header ("Content-Transfer-Encoding: binary");
   header ("Content-Length: " . filesize("$backup"));
   header ("Content-Description: Postfix Admin");
   $download_backup = fopen ("$backup", "r");
   fpassthru ($download_backup);
}
?>
