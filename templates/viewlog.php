<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<div id="overview">
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
<input class="button" type="submit" name="go" value="<?php print $PALANG['pViewlog_button']; ?>" />
</form>
</div>

<?php

    if (sizeof ($tLog) > 0)
    {
       print "<table id=\"log_table\">\n";
       print "   <tr>\n";
       print "      <td colspan=\"5\"><h3>".$PALANG['pViewlog_welcome']." ".$fDomain."</h3></td>\n";
       print "   </tr>\n";
       print "   <tr class=\"header\">\n";
       print "      <td>" . $PALANG['pViewlog_timestamp'] . "</td>\n";
       print "      <td>" . $PALANG['pViewlog_username'] . "</td>\n";
       print "      <td>" . $PALANG['pViewlog_domain'] . "</td>\n";
       print "      <td>" . $PALANG['pViewlog_action'] . "</td>\n";
       print "      <td>" . $PALANG['pViewlog_data'] . "</td>\n";
       print "   </tr>\n";

       for ($i = 0; $i < sizeof ($tLog); $i++)
       {
          if ((is_array ($tLog) and sizeof ($tLog) > 0))
          {
             $log_data = $tLog[$i]['data'];
             $data_length = strlen ($log_data);
             if ($data_length > 35) $log_data = substr ($log_data, 0, 35) . " ...";

             print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\" onclick=\"alert('" . $PALANG['pViewlog_data'] . " = " . $tLog[$i]['data'] . "')\">\n";
             print "      <td nowrap>" . $tLog[$i]['timestamp'] . "</td>\n";
             print "      <td nowrap>" . $tLog[$i]['username'] . "</td>\n";
             print "      <td nowrap>" . $tLog[$i]['domain'] . "</td>\n";
             print "      <td nowrap>" . $PALANG['pViewlog_action_'.$tLog[$i]['action'] ] . "</td>\n";
             print "      <td nowrap>" . $log_data . "</td>\n";
             print "   </tr>\n";
          }
       }

       print "</table>\n";
    }
?>
