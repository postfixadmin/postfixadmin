<table class="auto">
   <tr>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="overview.php"><?php print $PALANG['pMenu_overview']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <?php $url = "create-alias.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
         <a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pMenu_create_alias']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <?php $url = "create-mailbox.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
         <a target="_top" href="<?php print $url; ?>"><?php print $PALANG['pMenu_create_mailbox']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="sendmail.php"><?php print $PALANG['pMenu_sendmail']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="password.php"><?php print $PALANG['pMenu_password']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="viewlog.php"><?php print $PALANG['pMenu_viewlog']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="logout.php"><?php print $PALANG['pMenu_logout']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
   </tr>
</table>
<hr />
<?php if (file_exists (realpath ("motd.txt"))) include ("motd.txt"); ?>
