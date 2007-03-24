<table class="auto">
   <tr>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="list-admin.php"><?php print $PALANG['pAdminMenu_list_admin']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="list-domain.php"><?php print $PALANG['pAdminMenu_list_domain']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="list-virtual.php"><?php print $PALANG['pAdminMenu_list_virtual']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="viewlog.php"><?php print $PALANG['pAdminMenu_viewlog']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="backup.php"><?php print $PALANG['pAdminMenu_backup']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="create-domain.php"><?php print $PALANG['pAdminMenu_create_domain']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <a target="_top" href="create-admin.php"><?php print $PALANG['pAdminMenu_create_admin']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <?php $url = "create-alias.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
         <a target=""_top"" href="<?php print $url; ?>"><?php print $PALANG['pAdminMenu_create_alias']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
      <td class="menu">
         <?php $url = "create-mailbox.php"; if (isset ($_GET['domain'])) $url .= "?domain=" . $_GET['domain']; ?>
         <a target=""_top"" href="<?php print $url; ?>"><?php print $PALANG['pAdminMenu_create_mailbox']; ?></a>
      </td>
      <td width="8">
         &nbsp;
      </td>
   </tr>
</table>
<hr />
<?php if (file_exists (realpath ("../motd-admin.txt"))) include ("../motd-admin.txt"); ?>
