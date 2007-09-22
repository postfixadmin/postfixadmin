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
// -none-
//
// Form POST \ GET Variables:
//
// -none-
//

require_once('common.php');

$SESSID_USERNAME = authentication_get_username();

authentication_require_role('admin');

if ($_SERVER["REQUEST_METHOD"] == "GET")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/main.tpl");
   include ("./templates/footer.tpl");
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/main.tpl");
   include ("./templates/footer.tpl");
}
?>
