<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="menu">
<ul>
   <li><a target="_top" href="<?php print $CONF['user_footer_link']; ?>"><?php print $PALANG['pMenu_main']; ?></a></li>
   <?php if ($CONF['vacation'] == "YES") { ?>
   <li><a target="_top" href="vacation.php"><?php print $PALANG['pUsersMenu_vacation']; ?></a></li>
   <?php } ?>
   <li><a target="_top" href="edit-alias.php"><?php print $PALANG['pUsersMenu_edit_alias']; ?></a></li>
   <li><a target="_top" href="password.php"><?php print $PALANG['pUsersMenu_password']; ?></a></li>
   <li><a target="_top" href="logout.php"><?php print $PALANG['pMenu_logout']; ?></a></li>
</ul>
</div>

<br clear='all' /><br>

<?php 
if (file_exists (realpath ("../motd-users.txt"))) 
{
   print "<div id=\"motd\">\n";
   include ("../motd-users.txt");
   print "</div>";
}
?>
