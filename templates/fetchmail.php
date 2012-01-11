<?php if( !defined('POSTFIXADMIN') ) die( "This file cannot be used standalone." ); ?>
<?php 

$headers=array();
foreach(array_keys($fm_struct) as $row){
   list($editible,$view,$type)=$fm_struct[$row];
   $title = $PALANG['pFetchmail_field_' . $row];
   $comment = $PALANG['pFetchmail_desc_' . $row];
   if ($view){
      $headers[]=array($editible, $view, $type, $title, $comment);
   }
}

if ($edit || $new) { # edit mode
   echo '<div id="edit_form">';
   echo '<form name="fetchmail" method="post">';
   print fetchmail_edit_row($formvars);

} else { # display mode
   print '<div id="overview">';
   print '<form name="overview" method="post">';
   print "<table id=\"log_table\" border=0>\n";
   print "   <tr>\n";
   print "      <td colspan=\"".(sizeof($headers)+2)."\"><h3>".$PALANG['pFetchmail_welcome'].$user_domains."</h3></td>\n";
   print "   </tr>\n";
   print "   <tr class=\"header\">\n";
   foreach($headers as $row){
      list($editible,$view,$type,$title,$comment)=$row;
      print "      <td>" . $title . "</td>\n";
   }
   print "<td>&nbsp;</td>";
   print "<td>&nbsp;</td>";
   print "   </tr>\n";
   
    if (sizeof ($tFmail) > 0){
       foreach($tFmail as $row){
         print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
         foreach($row as $key=>$val){

            if (!isset($fm_struct[$key])) continue; # TODO: not really nice, but avoids undefined index warnings ;-)
            list($editible,$view,$type)=$fm_struct[$key];
            if ($view){
               $func="_listview_".$type;
               print "      <td nowrap>" . htmlentities(function_exists($func)?$func($val):$val) . "</td>\n";
            }

         }
         print "<td><a href=\"fetchmail.php?edit=" . $row['id'] . "\">" . $PALANG['edit'] . "</a></td>";
         print "      <td><a href=\"fetchmail.php?delete=" . $row['id'] . "\"onclick=\"return confirm ('" 
            . $PALANG['confirm'] . $PALANG['pMenu_fetchmail'] . ": ". htmlentities($row['src_user']) . " @ " 
            . htmlentities($row['src_server'])  . "')\">" . $PALANG['del'] . "</a></td>\n";
         print "   </tr>\n";
       }
    }
   print "</table>";
   print "<p />\n";
   print "</form>\n";
   print "</div>\n";

   print "<p><a href='?new=1'>".$PALANG['pFetchmail_new_entry']."</a></p>\n";

} # end display mode

function fetchmail_edit_row($data=array()){
   global $fm_struct,$fm_defaults,$PALANG;
   $id=$data["id"];
   $_id=$data["id"]*100+1;
   $ret="<table>";
   $ret .= '<tr><td colspan="3"><h3>' . $PALANG['pMenu_fetchmail'] . '</h3></td></tr>';
   # TODO: $formvars possibly contains db-specific boolean values
   # TODO: no problems with MySQL, to be tested with PgSQL
   # TODO: undefined values may also occour
   foreach($fm_struct as $key=>$struct){
      list($editible,$view,$type)=$struct;
      $title = $PALANG['pFetchmail_field_' . $key];
      $comment = $PALANG['pFetchmail_desc_' . $key];
      if ($editible){
         $ret.="<tr><td align=left valign=top><label for=${_id} style='width:20em;'>${title}:&nbsp;</label></td>";
         $ret.="<td align=left style='padding-left:.25em;padding-right:.25em;background-color:white;'>";
         $func="_edit_".$type;
         if (! function_exists($func))
            $func="_edit_text";
         $val=isset($data[$key])
            ?$data[$key]
            :(! is_array($fm_defaults[$key])
               ?$fm_defaults[$key]
               :''
            );
         $fm_defaults_key = ""; if (isset($fm_defaults[$key])) $fm_defaults_key = $fm_defaults[$key];
         $ret.=$func($_id++,$key,$fm_defaults_key,$val);
         $ret.="</td><td align=left valign=top><i>&nbsp;${comment}</i></td></tr>\n";
      }
      elseif($view){
         $func="_view_".$type;
         $val=isset($data[$key])
            ?(function_exists($func)
               ?$func($data[$key])
               :nl2br($data[$key])
            )
            :"--x--";
         $ret.="<tr><td align=left valign=top>${title}:&nbsp;</label></td>";
         $ret.="<td align=left valign=top style='padding-left:.25em;padding-right:.25em;background-color:white;'>".$val;
         $ret.="</td><td align=left valign=top><i>&nbsp;${comment}</i></td></tr>\n";
      }
   }
   $ret.="<tr><td align=center colspan=3>
      <input type=submit name=save value='" . $PALANG['save'] . "'> &nbsp;
      <input type=submit name=cancel value='" . $PALANG['cancel'] . "'>
   ";
   if ($id){
      $ret.="<input type=hidden name=edit value='${id}'>";
   }
   $ret.="</td></tr>\n";
   $ret.="</table>\n";
   $ret.="<p />\n";
   $ret.="</form>\n";
   $ret.="</div>\n";
   return $ret;
}

function _edit_text($id,$key,$def_vals,$val=""){
   $val=htmlspecialchars($val);
   return "<input type=text name=${key} id=${id} value='${val}'>";
}

function _edit_password($id,$key,$def_vals,$val=""){
   $val=preg_replace("{.}","*",$val);
   return "<input type=password name=${key} id=${id} value='${val}'>";
}

function _edit_num($id,$key,$def_vals,$val=""){
   $val=(int)($val);
   return "<input type=text name=${key} id=${id} value='${val}'>";
}

function _edit_bool($id,$key,$def_vals,$val=""){
   $ret="<input type=checkbox name=${key} id=${id}";
   if ($val)
      $ret.=" checked";
   $ret.=">";
   return $ret;
}

function _edit_longtext($id,$key,$def_vals,$val=""){
   $val=htmlspecialchars($val);
   return "<textarea name=${key} id=${id}  rows=2 style='width:20em;'>${val}</textarea>";
}

function _edit_enum($id,$key,$def_vals,$val=""){
   $ret="<select name=${key} id=${id}>";
      foreach($def_vals as $opt_val){
         $ret.="<option";
         if ($opt_val==$val)
            $ret.=" selected";
         $ret.=">${opt_val}</option>\n";
      }
   $ret.="</select>\n";
   return $ret;
}

function _listview_id($val){
   return "<a href='?edit=${val}'>&nbsp;${val}&nbsp;</a>";
}

function _listview_bool($val){
   return $val?"+":"";
}

function _listview_longtext($val){
   return strlen($val)?"Text - ".strlen($val)." chars":"--x--";
}

function _listview_text($val){
   return sizeof($val)?$val:"--x--";
}

function _listview_password($val){
   return preg_replace("{.}","*",$val);
}

/* vim: set ft=php expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
