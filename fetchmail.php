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

/* new sql table: fetchmail

create table fetchmail(
 id int(11) unsigned not null auto_increment,
 mailbox varchar(255) not null default '',
 src_server varchar(255) not null default '',
 src_auth enum('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any'),
 src_user varchar(255) not null default '',
 src_password varchar(255) not null default '',
 src_folder varchar(255) not null default '',
 poll_time int(11) unsigned not null default 10,
 fetchall tinyint(1) unsigned not null default 0,
 keep tinyint(1) unsigned not null default 0,
 protocol enum('POP3','IMAP','POP2','ETRN','AUTO'),
 extra_options text,
 returned_text text,
 mda varchar(255) not null default '',
 date timestamp(14),
 primary key(id)
);

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

$fm_struct=array(   //   list($editible,$view,$type,$title,$comment)
   # first column: allow editing?
   # second column: display field?
   # the others:                  type          title       help text (in edit form)
   "id"              => array(0,0,'id',         'ID',       'Record ID'),
   "mailbox"         => array(1,1,'enum',       'Mailbox',  'Local mailbox'),
   "src_server"      => array(1,1,'text',       'Server',   'Remote Server'),
   "src_auth"        => array(1,1,'enum',       'Auth Type','Mostly password'),
   "src_user"        => array(1,1,'text',       'User',     'Remote User'),
   "src_password"    => array(1,0,'password',   'Password', 'Remote Password'),
   "src_folder"      => array(1,1,'text',       'Folder',   'Remote Folder'),
   "poll_time"       => array(1,1,'num',        'Poll',     'Poll Time (min)'),
   "fetchall"        => array(1,1,'bool',       'Fetch All','Retrieve  both old (seen) and new messages'),
   "keep"            => array(1,1,'bool',       'Keep',     'Keep retrieved messages on the remote mailserver'),
   "protocol"        => array(1,1,'enum',       'Protocol', 'Protocol to use'),
   "extra_options"   => array($extra_options,$extra_options,'longtext', 'Extra Options','Extra fetchmail Options'),
   "mda"             => array($extra_options,$extra_options,'longtext', 'MDA',   'Mail Delivery Agent'),
   "date"            => array(0,$display_status,            'text',     'Date',  'Date of last polling/configuration change'),
   "returned_text"   => array(0,$display_status,            'longtext', 'Returned Text','Text message from last polling'),
);

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


$list_domains = list_domains_for_admin ($SESSID_USERNAME);
$user_domains=implode(", ",array_values($list_domains)); # for displaying
$user_domains_sql=implode("','",escape_string(array_values($list_domains))); # for SQL
$sql="SELECT username FROM mailbox WHERE domain in ('".$user_domains_sql."')"; # TODO: replace with domain selection dropdown

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

if ($row_id) {
   $result = db_query ("SELECT ".implode(",",escape_string(array_keys($fm_struct)))." FROM fetchmail WHERE id=" . $row_id);
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


if ($cancel) {
   $edit=0;
} elseif ($delete) {
   $result = db_query ("delete from fetchmail WHERE id=".$delete);
   if ($result['rows'] != 1)
   {
      flash_error($PALANG['pDelete_delete_error']) . '</span';
   } else {
      flash_info(sprintf($PALANG['pDelete_delete_success'],$account));
   }
   $delete=0;
} elseif ( ($edit || $new) && $save) {
   $formvars=array();
   foreach($fm_struct as $key=>$row){
      list($editible,$view,$type,$title,$comment)=$row;
      if ($editible != 0){
         $func="_inp_".$type;
         $val=safepost($key);
         if ($type!="password" || strlen($val) > 0) { # skip on empty (aka unchanged) password
            $formvars[$key]= escape_string( function_exists($func) ?$func($val) :$val);
         }
      }
   }
   $formvars['id'] = $edit; # results in 0 on $new

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
         $sql="INSERT fetchmail (".implode(",",escape_string(array_keys($formvars))).") VALUES ('".implode("','",escape_string($formvars))."')";
      } else { # $edit
         foreach(array_keys($formvars) as $key) {
            $formvars[$key] = escape_string($key) . "='" . escape_string($formvars[$key]) . "'";
         }
         $sql="UPDATE fetchmail SET ".implode(",",$formvars).",returned_text='', date=NOW() WHERE id=".$edit;
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

} elseif ($edit) {
   $formvars = $edit_row;
   $formvars['src_password'] = '';
} elseif ($new) {
   foreach (array_keys($fm_struct) as $value) {
      if (isset($fm_defaults[$value])) {
         $formvars[$value] = $fm_defaults[$value];
      } else {
         $formvars[$value] = '';
      }
   }
}

if ($edit + $new == 0) { # display list
   $res = db_query ("SELECT ".implode(",",escape_string(array_keys($fm_struct)))." FROM fetchmail order by id desc");
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
   return $val?db_get_boolean(true):db_get_boolean(false);
}

function _inp_password($val){
   return base64_encode($val);
}

include ("./templates/header.tpl");
include ("./templates/menu.tpl");
include ("./templates/fetchmail.tpl");
include ("./templates/footer.tpl");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
