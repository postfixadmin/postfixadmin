<div id="overview">
<form name="overview" method="post">

<?php 
	
	$headers=array();
	foreach($fm_struct as $row){
		list($editible,$view,$type,$title,$comment)=$row;
		if ($view){
			$headers[]=$row;
		}
	}

	print "<table id=\"log_table\" border=0>\n";
	print "   <tr>\n";
	print "      <td colspan=\"".(sizeof($headers)-1)."\"><h3>".$PALANG['pFetchmail_welcome'].$user_domains."</h3></td>\n";
	print "      <td align=right><a href='?new=1'>&gt;&gt;&nbsp;".$PALANG['pFetchmail_new_entry']."</a></td>\n";
	print "   </tr>\n";
	print "   <tr class=\"header\">\n";
	foreach($headers as $row){
		list($editible,$view,$type,$title,$comment)=$row;
		print "      <td>" . $title . "</td>\n";
	}
	print "   </tr>\n";
	
    if (sizeof ($tFmail) > 0){
       foreach($tFmail as $row){
		if ($edit && $edit==$row["id"]){
			print "<tr><td colspan=".sizeof($headers).">".fetchmail_edit_row($row)."</td></tr>\n";
		}
		else{
			print "   <tr class=\"hilightoff\" onMouseOver=\"className='hilighton';\" onMouseOut=\"className='hilightoff';\">\n";
			foreach($row as $key=>$val){
				list($editible,$view,$type,$title,$comment)=$fm_struct[$key];
				if ($view){
					$func="_listview_".$type;
					print "      <td nowrap>" . (function_exists($func)?$func($val):$val) . "</td>\n";
				}
			}
			print "   </tr>\n";
		}
       }

    }

function fetchmail_edit_row($data=array()){
	global $fm_struct,$fm_defaults;
	$id=$data["id"];
	$_id=$data["id"]*100+1;
	$ret="<table cellspacing=1 cellpadding=0 border=0 width=100%>";
	foreach($fm_struct as $key=>$struct){
		list($editible,$view,$type,$title,$comment)=$struct;
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
			$ret.=$func($_id++,$key,$fm_defaults[$key],$val);
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
	$ret.="<tr><td align=left><input type=submit name=cancel value='Abbrechen'></td><td align=right><input type=submit name=save value='Save'></td><td align=right><input type=submit name=delete value='Delete'>";
	if ($id){
		$ret.="<input type=hidden name=edit value='${id}'>";
	}
	$ret.="</td></tr>\n";
	$ret.="</table>\n";
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


?>
</table>
<p />
</form>
</div>
