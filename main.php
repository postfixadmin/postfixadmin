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
 * Displays a menu/home page.
 * Template File: main.tpl
 *
 * Template Variables: -none-
 *
 * Form POST \ GET Variables: -none-
 */

require_once('common.php');

$SESSID_USERNAME = authentication_get_username();

authentication_require_role('admin');

include ("./templates/header.tpl");
include ("./templates/menu.tpl");
include ("./templates/main.tpl");
include ("./templates/footer.tpl");
?>
