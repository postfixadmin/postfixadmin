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
 * TODO
 *
 * Form POST \ GET Variables:
 *
 * TODO
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
 pool_time int(11) unsigned not null default 10,
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

$fm_struct=array(	//	list($editible,$view,$type,$title,$comment)
	"id"		=>array(0,1,'id',		'ID','Record ID'),
	"mailbox"	=>array(1,1,'enum',	'Mailbox','Local mailbox'),
	"src_server"	=>array(1,1,'text',		'Server','Remote Server'),
	"src_auth"	=>array(1,1,'enum',	'Auth Type','Mostly password'),
	"src_user"	=>array(1,1,'text',		'User','Remote User'),
	"src_password"	=>array(1,1,'password',	'Password','Remote Password'),
	"src_folder"	=>array(1,1,'text',	'Folder','Remote Folder'),
	"pool_time"		=>array(1,1,'num',	'Poll','Poll Time (min)'),
	"fetchall"	=>array(1,1,'bool',	'Fetch All','Retrieve  both old (seen) and new messages'),
	"keep"		=>array(1,1,'bool',	'Keep','Keep retrieved messages on the remote mailserver'),
	"protocol"	=>array(1,1,'enum',	'Protocol','Protocol to use'),
	"extra_options"	=>array(1,1,'longtext',	'Extra Options','Extra fetchmail Options'),
	"mda"		=>array(1,1,'longtext',	'MDA','Mail Delivery Agent'),
	"date"		=>array(0,1,'text',	'Date','Date of last pooling/configuration change'),
	"returned_text"	=>array(0,1,'longtext',	'Returned Text','Text message from last pooling'),
);

$SESSID_USERNAME = authentication_get_username();
if (!$SESSID_USERNAME )
	exit;

$fm_defaults=array(
	"id"		=>0,
	"mailbox"	=> array($SESSID_USERNAME),
	"pool_time"	=>10,
	"src_auth"	=>
		array('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any'),
	"protocol"	=>
		array('POP3','IMAP','POP2','ETRN','AUTO'),
);

	 
$list_domains = list_domains_for_admin ($SESSID_USERNAME);
$user_domains=implode("','",array_values($list_domains));
$sql="SELECT username FROM mailbox WHERE domain in ('".$user_domains."')";

$res = db_query ($sql);
if ($res['rows'] > 0){
	$fm_defaults["mailbox"]=array();
	while ($name = db_array ($res['result'])){
		$fm_defaults["mailbox"][] = $name["username"];
	}
}
else{
	$fm_defaults["mailbox"]=array();
	$fm_defaults["mailbox"][]=$SESSID_USERNAME;
}

$new=$_REQUEST["new"];
$edit=(int)$_REQUEST["edit"];
$delete=$_REQUEST["delete"];
$save=$_REQUEST["save"];
$cancel=$_REQUEST["cancel"];

if ($cancel){
	$edit=0;
}
elseif($edit && $save){
	$_vals=array();
	foreach($fm_struct as $key=>$row){
		list($editible,$view,$type,$title,$comment)=$row;
		if ($editible){
			$func="_inp_".$type;
			$val=$_REQUEST[$key];
			if ($type!="password" || substr($val,0,1)!="*"){
				$_vals[]=$key."='".mysql_escape_string(
					function_exists($func)
						?$func($val)
						:$val)."'";
				}
		}
	}
	$sql="UPDATE fetchmail SET ".implode(",",$_vals).",returned_text='' WHERE id=".$edit;
	$res= db_query ($sql);
}
elseif($delete){
	db_query ("delete from fetchmail WHERE id=".$edit);
}
elseif ($new){
	$_keys=array();
	$_vals=array();
	foreach($fm_defaults as $key=>$val){
		$_keys[]=$key;
		$_vals[]="'".(is_array($val)?$val[0]:mysql_escape_string($val))."'";
	}
	$sql="INSERT fetchmail (".implode(",",$_keys).") VALUES (".implode(",",$_vals).")";
	$res= db_query ($sql);
	$sql="SELECT id FROM fetchmail order by id desc limit 1";
	$res= db_query ($sql);
	list($edit)=mysql_fetch_row($res['result']);
}

$res = db_query ("SELECT ".implode(",",array_keys($fm_struct))." FROM fetchmail order by id desc");
if ($res['rows'] > 0){
	while ($row = db_array ($res['result'])){
		$tFmail[] = $row;
	}
}

function _inp_num($val){
	return (int)($val);
}

function _inp_bool($val){
	return $val?1:0;
}

function _inp_password($val){
	return base64_encode($val);
}

   include ("./templates/header.tpl");
   include ("./templates/menu.tpl");
   include ("./templates/fetchmail.tpl");
   include ("./templates/footer.tpl");

?>
