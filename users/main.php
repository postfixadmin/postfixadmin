<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: main.php
 * 'Home page' for logged in users.
 * Template File: main.php
 *
 * Template Variables:
 *
 * tummVacationtext
 *
 * Form POST \ GET Variables: -none-
 */

require_once('../common.php');
authentication_require_role('user');
$USERID_USERNAME = authentication_get_username();

$db_active = db_get_boolean(True);
$result = db_query("SELECT * FROM $table_vacation WHERE email='$USERID_USERNAME' AND active='$db_active'");
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
   include ("../templates/header.php");
   include ("../templates/users_menu.php");
   include ("../templates/users_main.php");
   include ("../templates/footer.php");
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
   include ("../templates/header.php");
   include ("../templates/users_menu.php");
   include ("../templates/users_main.php");
   include ("../templates/footer.php");
}
?>
