<center>
<?php print $tMessage . "\n"; ?>
<table class="form">
   <tr>
      <td align="center" colspan="3">
         <?php print $PALANG['pCreate_alias_welcome'] . "\n"; ?>
         <br />
         <br />
      </td>
   </tr>
   <tr>
      <td>
         <form name="alias" method="post">
         <?php print $PALANG['pCreate_alias_address'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fAddress" value="<?php print $tAddress; ?>" />
      </td>
      <td>
         <select name="fDomain">
         <?php
         for ($i = 0; $i < sizeof ($list_domains); $i++)
         {
            if ($tDomain == $list_domains[$i])
            {
               print "            <option value=\"$list_domains[$i]\" selected>$list_domains[$i]</option>\n";
            }
            else
            {
               print "            <option value=\"$list_domains[$i]\">$list_domains[$i]</option>\n";
            }
         }
         ?>
         </select>
         <?php print $pCreate_alias_address_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td>
         <?php print $PALANG['pCreate_alias_goto'] . ":\n"; ?>
      </td>
      <td>
         <input type="text" name="fGoto" value="<?php print $tGoto; ?>" />
      </td>
      <td>
         <?php print $pCreate_alias_goto_text . "\n"; ?>
      </td>
   </tr>
   <tr>
      <td align="center" colspan="3">
         <input type="submit" name="submit" value="<?php print $PALANG['pCreate_alias_button']; ?>" />
         </form>
      </td>
   </tr>
</table>
<p />
<?php print $PALANG['pCreate_alias_catchall_text'] . "\n"; ?>
