<?php
// 
// Postfix Admin 
// by Mischa Peters <mischa at high5 dot net>
// Copyright (c) 2002 - 2005 High5!
// Licensed under GPL for more info check GPL-LICENSE.TXT
//
// File: main.php
//
// Template File: main.tpl
//
// Template Variables:
//
// tummVacationtext
//
// Form POST \ GET Variables:
//
// -none-
//

require_once('../common.php');
authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$result = db_query("SELECT * FROM $table_vacation WHERE email='$USERID_USERNAME'");
if ($result['rows'] == 1)
{
   $row = db_array($result['result']);
   $tummVacationtext = $PALANG['pUsersMain_vacationSet'];
}
else
{
   $tummVacationtext = $PALANG['pUsersMain_vacation'];
}

if ($_SERVER["REQUEST_METHOD"] == "GET")
{
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_main.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
   include ("../templates/header.tpl");
   include ("../templates/users_menu.tpl");
   include ("../templates/users_main.tpl");
   include ("../templates/footer.tpl");
}
?>
