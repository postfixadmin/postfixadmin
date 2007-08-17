<div id="admin_virtual">
<form name="overview" method="post">
<select name="fDomain" onChange="this.form.submit();">
<?php
for ($i = 0; $i < sizeof ($list_domains); $i++)
{
   if ($fDomain == $list_domains[$i])
   {
      print "<option value=\"$list_domains[$i]\" selected>$list_domains[$i]</option>\n";
   }
   else
   {
      print "<option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
   }
}
?>
</select>
<input type="hidden" name="limit" value="0" />
<input type="submit" name="go" value="<?php print $PALANG['pAdminList_virtual_button']; ?>" />
</form>
<h4><?php print $PALANG['pAdminList_virtual_welcome'] . $fDomain; ?></h4>
<p><?php print $PALANG['pAdminList_virtual_alias_alias_count'] . ": " . $limit['alias_count'] . " / " . $limit['aliases']; ?></p>
<p><?php print $PALANG['pAdminList_virtual_alias_mailbox_count'] . ": " . $limit['mailbox_count'] . " / " . $limit['mailboxes']; ?></p>
<form name="search" method="post" action="search.php"><?php print $PALANG['pSearch']; ?>:
<input type="textbox" name="search" size="10" />
</form>
</div>

<div id="nav_bar">
   <table width=730><colgroup span="1"><col width="550"></col></colgroup> 
   <tr><td align=left >
<?php
if ($limit['alias_pgindex_count'] ) print "<b>".$PALANG['pOverview_alias_title']."</b>&nbsp&nbsp";
($tDisplay_back_show == 1) ? $highlight_at = $tDisplay_back / $CONF['page_size'] + 1 : $highlight_at = 0;
for ($i = 0; $i < $limit['alias_pgindex_count']; $i++)
{
   if ( $i == $highlight_at )
   {
      print  "<a href=\"list-virtual.php?domain=$fDomain&limit=" . $i * $CONF['page_size'] . "\"><b>" . $limit['alias_pgindex'][$i] . "</b></a>\n";
   }
   else
   {
      print  "<a href=\"list-virtual.php?domain=$fDomain&limit=" . $i * $CONF['page_size'] . "\">" . $limit['alias_pgindex'][$i] . "</a>\n";
   }
}
print "</td><td valign=middle align=right>";

if ($tDisplay_back_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_back\"><img border=\"0\" src=\"../images/arrow-l.png\" title=\"" . $PALANG['pOverview_left_arrow'] . "\" alt=\"" . $PALANG['pOverview_left_arrow'] . "\" /></a>\n";
}
if ($tDisplay_up_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=0\"><img border=\"0\" src=\"../images/arrow-u.png\" title=\"" . $PALANG['pOverview_up_arrow'] . "\" alt=\"" . $PALANG['pOverview_up_arrow'] . "\" /></a>\n";
}
if ($tDisplay_next_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_next\"><img border=\"0\" src=\"../images/arrow-r.png\" title=\"" . $PALANG['pOverview_right_arrow'] . "\" alt=\"" . $PALANG['pOverview_right_arrow'] . "\" /></a>\n";
}
print "</td></tr></table></div>\n";


if (sizeof ($tAlias) > 0)
{
   print "<table id=\"alias_table\">\n";
   print "   <tr>\n";
   print "      <td colspan=\"6\"><h3>" . $PALANG['pOverview_alias_title'] . "</h3></td>";
   print "   </tr>";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pAdminList_virtual_alias_address'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_alias_goto'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_alias_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_alias_active'] . "</td>\n";
   print "      <td colspan=\"2\">&nbsp;</td>\n";
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tAlias); $i++)
   {
      if ((is_array ($tAlias) and sizeof ($tAlias) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "      <td>" . $tAlias[$i]['address'] . "</td>\n";
         if ($CONF['alias_goto_limit'] > 0) {
         print "      <td>" . ereg_replace (",", "<br>", preg_replace('/^(([^,]+,){'.$CONF['alias_goto_limit'].'})[^,]+,.*/','$1[and '. (substr_count ($tAlias[$i]['goto'], ',') - $CONF['alias_goto_limit'] + 1) .' more...]',$tAlias[$i]['goto'])) . "</td>\n";
         } else {
            print "      <td>" . ereg_replace (",", "<br>", $tAlias[$i]['goto']) . "</td>\n";
         }
         print "      <td>" . $tAlias[$i]['modified'] . "</td>\n";
         $active = ($tAlias[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
         print "      <td><a href=\"edit-active.php?alias=" . urlencode ($tAlias[$i]['address']) . "&domain=$fDomain" . "\">" . $active . "</a></td>\n";
         print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tAlias[$i]['address']) . "&domain=$fDomain" . "\">" . $PALANG['edit'] . "</a></td>\n";
         print "      <td><a href=\"delete.php?table=alias" . "&delete=" . urlencode ($tAlias[$i]['address']) . "&domain=$fDomain" . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_aliases'] . ": ". $tAlias[$i]['address'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
      }
   }
   print "</table>\n";
}
print "<p><a href=\"create-alias.php?domain=$fDomain\">" . $PALANG['pMenu_create_alias'] . "</a>\n";

   print "<div id=\"nav_bar\"><a name=\"MidArrow\" /a>\n<table width=730><colgroup span=\"1\"> <col width=\"550\"></col></colgroup> <tr><td align=left >";
   if ( $limit['mbox_pgindex_count'] ) print "<b>".$PALANG['pOverview_mailbox_title']."</b>&nbsp&nbsp";
   ($tDisplay_back_show == 1) ? $highlight_at = $tDisplay_back / $CONF['page_size'] + 1 : $highlight_at = 0;
   for ($i = 0; $i < $limit['mbox_pgindex_count']; $i++)
   {
      if ( $i == $highlight_at )
      {
         print  "<a href=\"list-virtual.php?domain=$fDomain&limit=" . $i * $CONF['page_size'] . "#MidArrow\"><b>" . $limit['mbox_pgindex'][$i] . "</b></a>\n";
      }
      else
      {
         print  "<a href=\"list-virtual.php?domain=$fDomain&limit=" . $i * $CONF['page_size'] . "#MidArrow\">" . $limit['mbox_pgindex'][$i] . "</a>\n";
      }
   }
   print "</td><td valign=middle align=right>";


if ($tDisplay_back_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_back#MidArrow\"><img border=\"0\" src=\"../images/arrow-l.png\" title=\"" . $PALANG['pOverview_left_arrow'] . "\" alt=\"" . $PALANG['pOverview_left_arrow'] . "\" /></a>\n";
}
if ($tDisplay_up_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=0#MidArrow\"><img border=\"0\" src=\"../images/arrow-u.png\" title=\"" . $PALANG['pOverview_up_arrow'] . "\" alt=\"" . $PALANG['pOverview_up_arrow'] . "\" /></a>\n";
}
if ($tDisplay_next_show == 1)
{
   print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_next#MidArrow\"><img border=\"0\" src=\"../images/arrow-r.png\" title=\"" . $PALANG['pOverview_right_arrow'] . "\" alt=\"" . $PALANG['pOverview_right_arrow'] . "\" /></a>\n";
}
print "</td></tr></table></div>\n";


if (sizeof ($tMailbox) > 0)
{
   print "<table id=\"mailbox_table\">\n";
   print "   <tr>\n";
   print "      <td colspan=\"7\"><h3>" . $PALANG['pOverview_mailbox_title'] . "</h3></td>";
   print "   </tr>";
   print "   <tr class=\"header\">\n";
   print "      <td>" . $PALANG['pAdminList_virtual_mailbox_username'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_mailbox_name'] . "</td>\n";
   if ($CONF['quota'] == 'YES') print "      <td>" . $PALANG['pAdminList_virtual_mailbox_quota'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_mailbox_modified'] . "</td>\n";
   print "      <td>" . $PALANG['pAdminList_virtual_mailbox_active'] . "</td>\n";
   if ($CONF['alias_control'] == 'YES')
   {
      print "      <td colspan=\"3\">&nbsp;</td>\n";
   }
   else
   {
      print "      <td colspan=\"2\">&nbsp;</td>\n";
   }
   print "   </tr>\n";

   for ($i = 0; $i < sizeof ($tMailbox); $i++)
   {
      if ((is_array ($tMailbox) and sizeof ($tMailbox) > 0))
      {
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         print "      <td>" . $tMailbox[$i]['username'] . "</td>\n";
         print "      <td>" . $tMailbox[$i]['name'] . "</td>\n";
         if ($CONF['quota'] == 'YES')
         {
            print "      <td>";
            if ($tMailbox[$i]['quota'] == 0)
            {
               print $PALANG['pOverview_unlimited'];
            }
            elseif ($tMailbox[$i]['quota'] < 0)
            {
               print $PALANG['pOverview_disabled'];
            }
            else
            {
               print divide_quota ($tMailbox[$i]['quota']);
            }
            print "</td>\n";
         }
         print "      <td>" . $tMailbox[$i]['modified'] . "</td>\n";
         $active = ($tMailbox[$i]['active'] == 1) ? $PALANG['YES'] : $PALANG['NO'];
         print "      <td><a href=\"edit-active.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" . $active . "</a></td>\n";

         if ($CONF['vacation_control_admin'] == 'YES')
         {
            $v_active = ($tMailbox[$i]['v_active'] == 1) ? $PALANG['pOverview_vacation_edit'] : $PALANG['pOverview_vacation_option'];
            print "      <td><a href=\"edit-vacation.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" .$v_active . "</a></td>\n";
         }

         if ($CONF['alias_control'] == 'YES')
         {
            print "      <td><a href=\"edit-alias.php?address=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" . $PALANG['pOverview_alias_edit'] . "</a></td>\n";
         }
         print "      <td><a href=\"edit-mailbox.php?username=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\">" . $PALANG['edit'] . "</a></td>\n";
         print "      <td><a href=\"delete.php?table=mailbox" . "&delete=" . urlencode ($tMailbox[$i]['username']) . "&domain=$fDomain" . "\"onclick=\"return confirm ('" . $PALANG['confirm'] . $PALANG['pOverview_get_mailboxes'] . ": ". $tMailbox[$i]['username'] . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
      }
   }
   print "</table>\n";
   print "<div id=\"nav_bar\"><a name=\"LowArrow\" /a>\n";
   if ($tDisplay_back_show == 1)
   {
      print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_back#LowArrow\"><img border=\"0\" src=\"../images/arrow-l.png\" title=\"" . $PALANG['pOverview_left_arrow'] . "\" alt=\"" . $PALANG['pOverview_left_arrow'] . "\" /></a>\n";
   }
   if ($tDisplay_up_show == 1)
   {
      print "<a href=\"list-virtual.php?domain=$fDomain&limit=0#LowArrow\"><img border=\"0\" src=\"../images/arrow-u.png\" title=\"" . $PALANG['pOverview_up_arrow'] . "\" alt=\"" . $PALANG['pOverview_up_arrow'] . "\" /></a>\n";
   }
   if ($tDisplay_next_show == 1)
   {
      print "<a href=\"list-virtual.php?domain=$fDomain&limit=$tDisplay_next#LowArrow\"><img border=\"0\" src=\"../images/arrow-r.png\" title=\"" . $PALANG['pOverview_right_arrow'] . "\" alt=\"" . $PALANG['pOverview_right_arrow'] . "\" /></a>\n";
   }
   print "</div>\n";

   print "<p><a href=\"create-mailbox.php?domain=$fDomain\">" . $PALANG['pMenu_create_mailbox'] . "</a>\n";
}
/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
