<?php
//
// Postfix Admin
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2007 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: edit-vacation.php
//
// Template File: edit-vacation.tpl
//
// Template Variables:
//
// tMessage
// tSubject
// tBody
//
// Form POST \ GET Variables:
//
// fUsername
// fDomain
// fCanceltarget
// fChange
// fBack
// fQuota
// fActive
//
//
// This is a copy of the domain admin edit-vacation.php with
// template references changed
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . check_language () . ".lang");

$SESSID_USERNAME = check_session ();
(($CONF['vacation'] == 'NO') ? header("Location: " . $CONF['postfix_admin_url'] . "/main.php") && exit : '1');
$tmp = preg_split ('/@/', $SESSID_USERNAME);
$USERID_DOMAIN = $tmp[1];

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);
   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);

   if (check_admin($SESSID_USERNAME))
   {
      $fCanceltarget= $CONF['postfix_admin_url'] . "/admin/list-virtual.php?domain=$fDomain";
   }
   else
   {
     if (check_owner ($SESSID_USERNAME, $fDomain))
     {
        $fCanceltarget= $CONF['postfix_admin_url'] . "/overview.php?domain=$fDomain";
     }
     //unauthorized, exit
     else { exit; }
   }

   $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
   if ($result['rows'] == 1)
   {
      $row = db_array($result['result']);
      $tMessage = '';
      $tSubject = $row['subject'];
      $tBody = $row['body'];
   }

   $tUseremail = $fUsername;
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

   

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/edit-vacation.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   $vacation_domain = $CONF['vacation_domain'];

   if (isset ($_POST['fSubject'])) $fSubject = escape_string ($_POST['fSubject']);
   if (isset ($_POST['fBody'])) $fBody = escape_string ($_POST['fBody']);
   if (isset ($_POST['fChange'])) $fChange = escape_string ($_POST['fChange']);
   if (isset ($_POST['fBack'])) $fBack = escape_string ($_POST['fBack']);

   if (isset ($_GET['domain'])) $fDomain = escape_string ($_GET['domain']);
   if (isset ($_GET['username'])) $fUsername = escape_string ($_GET['username']);

   if (check_admin($SESSID_USERNAME))
   {
      $fCanceltarget= $CONF['postfix_admin_url'] . "/admin/list-virtual.php?domain=$fDomain";
   }
   else
   {
     if (check_owner ($SESSID_USERNAME, $fDomain))
     {
        $fCanceltarget= $CONF['postfix_admin_url'] . "/overview.php?domain=$fDomain";
     }
     //unauthorized, exit
     else { exit; }
   }

   $tUseremail = $fUsername;
   if ($tSubject == '') { $tSubject = $PALANG['pUsersVacation_subject_text']; }
   if ($tBody == '') { $tBody = $PALANG['pUsersVacation_body_text']; }

   //if change, remove old one, then set new one
   if (!empty ($fBack) || !empty ($fChange))
   {
     //if we find an existing vacation entry, delete it
     $result = db_query("SELECT * FROM $table_vacation WHERE email='$fUsername'");
     if ($result['rows'] == 1)
     {
      $result = db_query ("DELETE FROM $table_vacation WHERE email='$fUsername'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pVacation_result_error'];
         $tMessage = "cannot remove $fUsername from $table_vacation";
      }
      else
      {
         $tMessage = $PALANG['pVacation_result_success'];
      }

      $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $goto = $row['goto'];

         //only one of these will do something, first handles address at beginning and middle, second at end
         $goto= preg_replace ( "/$fUsername@$vacation_domain,/", '', $goto);
         $goto= preg_replace ( "/,$fUsername@$vacation_domain/", '', $goto);

         $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fUsername'");
         if ($result['rows'] != 1)
         {
            $error = 1;
            $tMessage = $PALANG['pVacation_result_error'];
         }
         else
         {
            $tMessage = $PALANG['pVacation_result_success'];
         }
      }
     }
   }

   //Set the vacation data for $fUsername
   if (!empty ($fChange))
   {
      $result = db_query ("SELECT * FROM $table_alias WHERE address='$fUsername'");
      if ($result['rows'] == 1)
      {
         $row = db_array ($result['result']);
         $goto = $row['goto'];
      }

      ($CONF['database_type']=='pgsql') ? $Active='true' : $Active=1;
      $result = db_query ("INSERT INTO $table_vacation (email,subject,body,domain,created,active) VALUES ('$fUsername','$fSubject','$fBody','$fDomain',NOW(),$Active)");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pVacation_result_error'];
      }

      $goto = $goto . "," . "$fUsername@$vacation_domain";

      $result = db_query ("UPDATE $table_alias SET goto='$goto',modified=NOW() WHERE address='$fUsername'");
      if ($result['rows'] != 1)
      {
         $error = 1;
         $tMessage = $PALANG['pVacation_result_error'];
      }
      else
      {
         header ("Location: $fCanceltarget");
         exit;
      }
   }

   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/edit-vacation.tpl");
   include ("../templates/footer.tpl");
}
?>
