<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<!-- 'breadcrumb' -->
<div id="edit_form">
<form name="alias" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pCreate_alias_welcome']; ?></h3></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_alias_address']; ?></td>
      <td><input class="flat" type="text" name="fAddress" value="<?php print $tAddress; ?>" /></td>
      <td>@
      <select class="flat" name="fDomain">
      <?php
      for ($i = 0; $i < sizeof ($list_domains); $i++)
      {
         if ($tDomain == $list_domains[$i])
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
      <?php print $pCreate_alias_address_text; ?>
      </td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_alias_goto'] . ":"; ?></td>
      <td colspan="2"><textarea class="flat" rows="10" cols="60" name="fGoto"><?php print $tGoto; ?></textarea></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_alias_active'] . ":"; ?></td>
      <td><input class="flat" type="checkbox" name="fActive" checked /></td>
      <td>&nbsp;</td>
   </tr>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pCreate_alias_button']; ?>" /></td>
   </tr>
   <tr>
      <td colspan="3" class="standout"><?php print $tMessage; ?></td>
   </tr>
   <tr>
      <td colspan="3" class="help_text"><?php print $PALANG['pCreate_alias_catchall_text']; ?></td>
   </tr>
</table>
</form>
</div>
