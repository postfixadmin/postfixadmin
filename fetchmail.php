<?php
/** 
 * Postfix Admin 
 * 
 * LICENSE 
 * This source file is subject to the GPL license that is bundled with  
 * this package in the file LICENSE.TXT. 
 * 
 * Further details on the project are available at : 
 *     http://www.postfixadmin.com or http://postfixadmin.sf.net 
 * 
 * @version $Id$ 
 * @license GNU GPL v2 or later. 
 * 
 * File: fetchmail.php
 * Responsible for setting up fetchmail
 * template : fetchmail.tpl
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * Template Variables:
 *
 * Form POST \ GET Variables:
 *
 * GET:
 * - edit
 * - delete
 * - new
 *
 * POST:
 * - save
 * - cancel
 * - all editable form values, see $fm_struct
 */

require_once('common.php');

authentication_require_role('admin');

$extra_options = 0;
if ($CONF['fetchmail_extra_options'] == 'YES') $extra_options = 1;

# import control GET/POST variables. Form values are imported below.
$new     = (int) safeget ("new")     == 1  ? 1:0;
$edit    = (int) safeget ("edit");
$delete  = (int) safeget ("delete");
$save    =       safepost("save")   != "" ? 1:0;
$cancel  =       safepost("cancel") != "" ? 1:0;

$display_status = 1;
if ($new || $edit) $display_status = 0;

$fm_struct=array(   //   list($editible,$view,$type)
   # field name               allow editing?    display field?    type
   "id"              => array(0,                0,                'id'        ),
   "mailbox"         => array(1,                1,                'enum'      ),
   "src_server"      => array(1,                1,                'text'      ),
   "src_auth"        => array(1,                1,                'enum'      ),
   "src_user"        => array(1,                1,                'text'      ),
   "src_password"    => array(1,                0,                'password'  ),
   "src_folder"      => array(1,                1,                'text'      ),
   "poll_time"       => array(1,                1,                'num'       ),
   "fetchall"        => array(1,                1,                'bool'      ),
   "keep"            => array(1,                1,                'bool'      ),
   "protocol"        => array(1,                1,                'enum'      ),
   "usessl"          => array(1,                1,                'bool'      ),
   "extra_options"   => array($extra_options,   $extra_options,   'longtext'  ),
   "mda"             => array($extra_options,   $extra_options,   'longtext'  ),
   "date"            => array(0,                $display_status,  'text'      ),
   "returned_text"   => array(0,                $display_status,  'longtext'  ),
);
# labels and descriptions are taken from $PALANG['pFetchmail_field_xxx'] and $PALANG['pFetchmail_desc_xxx']

# TODO: After pressing save or cancel in edit form, date and returned text are not displayed in list view.
# TODO: Reason: $display_status is set before $new and $edit are reset to 0.
# TODO: Fix: split the "display field?" column into "display in list" and "display in edit mode".

$SESSID_USERNAME = authentication_get_username();
if (!$SESSID_USERNAME )
   exit;

$fm_defaults=array(
   "id"        =>0,
   "mailbox"   => array($SESSID_USERNAME),
   "poll_time" => 10,
   "src_auth"  =>
      array('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any'),
   "protocol"  =>
      array('POP3','IMAP','POP2','ETRN','AUTO'),
);

$table_fetchmail = table_by_key('fetchmail');
$table_mailbox = table_by_key('mailbox');

if (authentication_has_role('global-admin')) {
   $list_domains = list_domains ();
} else {
   $list_domains = list_domains_for_admin(authentication_get_username());
}

$user_domains=implode(", ",array_values($list_domains)); # for displaying
$user_domains_sql=implode("','",escape_string(array_values($list_domains))); # for SQL
$sql="SELECT username FROM $table_mailbox WHERE domain in ('".$user_domains_sql."')"; # TODO: replace with domain selection dropdown

$res = db_query ($sql);
if ($res['rows'] > 0){
   $fm_defaults["mailbox"]=array();
   while ($name = db_array ($res['result'])){
      $fm_defaults["mailbox"][] = $name["username"];
   }
}
else{
   $fm_defaults["mailbox"]=array();
   $fm_defaults["mailbox"][]=$SESSID_USERNAME; # TODO: Does this really make sense? Or should we display a message "please create a mailbox first!"?
}

$row_id = 0;
if ($delete) {
   $row_id = $delete;
} elseif ($edit) {
   $row_id = $edit;
}

$user_mailboxes_sql= "'" . implode("','",escape_string(array_values($fm_defaults["mailbox"]))) . "'"; # mailboxes as SQL
if ($row_id) {
   $result = db_query ("SELECT ".implode(",",escape_string(array_keys($fm_struct)))." FROM $table_fetchmail WHERE id=$row_id AND mailbox IN ($user_mailboxes_sql)");
   # TODO: the "AND mailbox IN ..." part should obsolete the check_owner call. Remove it after checking again.
   if ($result['rows'] > 0) {
      $edit_row = db_array ($result['result']);
      $account = $edit_row['src_user'] . " @ " . $edit_row['src_server'];
   }
   
   $edit_row_domain = explode('@', $edit_row['mailbox']);
   if ($result['rows'] <= 0 || !check_owner($SESSID_USERNAME, $edit_row_domain[1])) { # owner check for $edit and $delete
      flash_error(sprintf($PALANG['pFetchmail_error_invalid_id'], $row_id));
      $edit = 0; $delete = 0;
   }
}


if ($cancel) { # cancel $new or $edit
   $edit=0;
   $new=0;
} elseif ($delete) { # delete an entry
   $result = db_query ("delete from $table_fetchmail WHERE id=".$delete);
   if ($result['rows'] != 1)
   {
      flash_error($PALANG['pDelete_delete_error']) . '</span>';
   } else {
      flash_info(sprintf($PALANG['pDelete_delete_success'],$account));
   }
   $delete=0;
} elseif ( ($edit || $new) && $save) { # $edit or $new AND save button pressed
   $formvars=array();
   foreach($fm_struct as $key=>$row){
      list($editible,$view,$type)=$row;
      if ($editible != 0){
         $func="_inp_".$type;
         $val=safepost($key);
         if ($type!="password" || strlen($val) > 0) { # skip on empty (aka unchanged) password
            $formvars[$key]= escape_string( function_exists($func) ?$func($val) :$val);
         }
      }
   }
   $formvars['id'] = $edit; # results in 0 on $new
   if($CONF['database_type'] == 'pgsql' && $new) {
      // skip - shouldn't need to specify this as it will default to the next available value anyway.
      unset($formvars['id']);
   }

   if (!in_array($formvars['mailbox'], $fm_defaults['mailbox'])) {
      flash_error($PALANG['pFetchmail_invalid_mailbox']);
      $save = 0; 
   }
   if ($formvars['src_server']   == '') {
      flash_error($PALANG['pFetchmail_server_missing']);
      # TODO: validate domain name
      $save = 0; 
   }
   if (empty($formvars['src_user']) ) {
      flash_error($PALANG['pFetchmail_user_missing']); 
      $save = 0; 
   }
   if ($new && empty($formvars['src_password']) ) {
      flash_error($PALANG['pFetchmail_password_missing']);
      $save = 0; 
   }

   if ($save) {
       if ($new) {
         $sql="INSERT INTO $table_fetchmail (".implode(",",escape_string(array_keys($formvars))).") VALUES ('".implode("','",escape_string($formvars))."')";
      } else { # $edit
         foreach(array_keys($formvars) as $key) {
            $formvars[$key] = escape_string($key) . "='" . escape_string($formvars[$key]) . "'";
         }
         $sql="UPDATE $table_fetchmail SET ".implode(",",$formvars).",returned_text='', date=NOW() WHERE id=".$edit;
      }
      $result = db_query ($sql);
      if ($result['rows'] != 1)
      {
         flash_error($PALANG['pFetchmail_database_save_error']);
      } else {
         flash_info($PALANG['pFetchmail_database_save_success']);
         $edit = 0; $new = 0; # display list after saving
      }
   } else {
      $formvars['src_password'] = ''; # never display password
   }

} elseif ($edit) { # edit entry form
   $formvars = $edit_row;
   $formvars['src_password'] = '';
} elseif ($new) { # create entry form
   foreach (array_keys($fm_struct) as $value) {
      if (isset($fm_defaults[$value])) {
         $formvars[$value] = $fm_defaults[$value];
      } else {
         $formvars[$value] = '';
      }
   }
}

$tFmail = array();
if ($edit + $new == 0) { # display list
   # TODO: ORDER BY would even be better if it would order by the _domain_ of the target mailbox first
   $res = db_query ("SELECT ".implode(",",escape_string(array_keys($fm_struct)))." FROM $table_fetchmail WHERE mailbox IN ($user_mailboxes_sql) ORDER BY mailbox,src_server,src_user");
   if ($res['rows'] > 0) {
      while ($row = db_array ($res['result'])) {
         $tFmail[] = $row;
      }
   }
}

function _inp_num($val){
   return (int)($val);
}

function _inp_bool($val){
   return $val ? db_get_boolean(true): db_get_boolean(false);
}

function _inp_password($val){
   return base64_encode($val);
}
//*****
$headers=array();
foreach(array_keys($fm_struct) as $row){
   list($editible,$view,$type)=$fm_struct[$row];
   $title = $PALANG['pFetchmail_field_' . $row];
   $comment = $PALANG['pFetchmail_desc_' . $row];
   if ($view){
      $headers[]=$title;
//      $headers[]=array($editible, $view, $type, $title, $comment);
   }
}
function fetchmail_edit_row($data=array())
{
	global $fm_struct,$fm_defaults,$PALANG;
	$id = $data["id"];
	$_id = $data["id"] * 100 + 1;
	$ret = "<table>";
   $ret .= '<tr><td colspan="3"><h3>'.$PALANG['pMenu_fetchmail'] . '</h3></td></tr>';
   # TODO: $formvars possibly contains db-specific boolean values
   # TODO: no problems with MySQL, to be tested with PgSQL
   # TODO: undefined values may also occour
   foreach($fm_struct as $key=>$struct){
      list($editible,$view,$type)=$struct;
      $title = $PALANG['pFetchmail_field_' . $key];
      $comment = $PALANG['pFetchmail_desc_' . $key];
      if ($editible){
         $ret.="<tr><td align='left' valign='top'><label for='${_id}' style='width:20em;'>${title}:&nbsp;</label></td>";
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
   return "<input type=text name=${key} id=${id} value='${val}' />";
}

function _edit_password($id,$key,$def_vals,$val=""){
   $val=preg_replace("{.}","*",$val);
   return "<input type=password name=${key} id=${id} value='${val}' />";
}

function _edit_num($id,$key,$def_vals,$val=""){
   $val=(int)($val);
   return "<input type=text name=${key} id=${id} value='${val}' />";
}

function _edit_bool($id,$key,$def_vals,$val=""){
   $ret="<input type=checkbox name=${key} id=${id}";
   if ($val)
      $ret.=' checked="checked"';
   $ret.=" />";
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

$smarty->assign ('edit', $edit);
$smarty->assign ('new', $new);
$smarty->assign ('fetchmail_edit_row', fetchmail_edit_row($formvars),false);
$smarty->assign ('headers', $headers);
$smarty->assign ('user_domains', $user_domains);
$smarty->assign ('tFmail', $tFmail);

$smarty->assign ('smarty_template', 'fetchmail');
$smarty->display ('index.tpl');

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
