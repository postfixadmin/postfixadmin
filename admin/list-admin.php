<?php
//
// File: list-admin.php
//
// Template File: list-admin.tpl
//
// Template Variables:
//
// -none-
//
// Form POST \ GET Variables:
//
// -none-
//
require ("../variables.inc.php");
require ("../config.inc.php");
require ("../functions.inc.php");
include ("../languages/" . $CONF['language'] . ".lang");

$list_admins = list_admins ();
for ($i = 0; $i < sizeof ($list_admins); $i++)
{
   $admin_properties[$i] = get_admin_properties ($list_admins[$i]);
}

if ($_SERVER['REQUEST_METHOD'] == "GET")
{
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-admin.tpl");
   include ("../templates/footer.tpl");
}

if ($_SERVER['REQUEST_METHOD'] == "POST")
{
   include ("../templates/header.tpl");
   include ("../templates/admin_menu.tpl");
   include ("../templates/admin_list-admin.tpl");
   include ("../templates/footer.tpl");
}
?>
