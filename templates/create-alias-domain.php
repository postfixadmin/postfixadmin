<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="edit_form">
<form name="alias_domain" method="post">
<table>
   <tr>
      <td colspan="3"><h3><?php print $PALANG['pCreate_alias_domain_welcome']; ?></h3></td>
   </tr>

<?php
if (count($alias_domains) > 0) {
?>

   <tr>
      <td><?php print $PALANG['pCreate_alias_domain_alias'] . ":"; ?></td>
      <td>
      <select class="flat" name="alias_domain">
      <?php
      foreach ($alias_domains as $dom)
      {
         print "<option value=\"$dom\"".(($fAliasDomain == $dom) ? ' selected' : '').">$dom</option>\n";
      }
      ?>
      </select>
      <td><?php print $PALANG['pCreate_alias_domain_alias_text']; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_alias_domain_target'] . ":"; ?></td>
      <td>
      <select class="flat" name="target_domain">
      <?php
      foreach ($target_domains as $dom)
      {
         print "<option value=\"$dom\"".(($fTargetDomain == $dom) ? ' selected' : '').">$dom</option>\n";
      }
      ?>
      </select>
      <td><?php print $PALANG['pCreate_alias_domain_target_text']; ?></td>
   </tr>
   <tr>
      <td><?php print $PALANG['pCreate_alias_domain_active'] . ":"; ?></td>
      <td><input class="flat" type="checkbox" name="active" value="1"<?php if ($fActive) { print ' checked'; } ?> /></td>
      <td>&nbsp;</td>
   </tr>
<?php
}
?>
   <tr>
      <td colspan="3" class="standout"><?php if ($error) { print '<span class="error_msg">'; } print $tMessage; if ($error) { print '</span>'; } ?></td>
   </tr>
<?php
if (count($alias_domains) > 0) {
?>
   <tr>
      <td colspan="3" class="hlp_center"><input class="button" type="submit" name="submit" value="<?php print $PALANG['pCreate_alias_domain_button']; ?>" /></td>
   </tr>
<?php
}
?>
</table>
</form>
</div>
<?php /* vim: set ft=php expandtab softtabstop=3 tabstop=3 shiftwidth=3: */ ?>
