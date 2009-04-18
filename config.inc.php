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
 * File: config.inc.php
 * Contains configuration options.
 */

if (ereg ("config.inc.php", $_SERVER['PHP_SELF']))
{
   header ("Location: login.php");
   exit;
}

/*****************************************************************
 *  !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! 
 * The following line needs commenting out or removing before the
 * application will run!
 * Doing this implies you have changed this file as required.
 * i.e. configuring database etc; specifying setup.php password etc.
 */
$CONF['configured'] = false;

// In order to setup Postfixadmin, you MUST specify a hashed password here.
// To create the hash, visit setup.php in a browser and type a password into the field,
// on submission it will be echoed out to you as a hashed value.
$CONF['setup_password'] = 'changeme';

// Postfix Admin Path
// Set the location of your Postfix Admin installation here.
// YOU MUST ENTER THE COMPLETE URL e.g. http://domain.tld/postfixadmin
$CONF['postfix_admin_url'] = '';

// shouldn't need changing.
$CONF['postfix_admin_path'] = dirname(__FILE__);

// Language config
// Language files are located in './languages', change as required..
$CONF['default_language'] = 'en';

// Database Config
// mysql = MySQL 3.23 and 4.0, 4.1 or 5
// mysqli = MySQL 4.1+ 
// pgsql = PostgreSQL
$CONF['database_type'] = 'mysql';
$CONF['database_host'] = 'localhost';
$CONF['database_user'] = 'postfix';
$CONF['database_password'] = 'postfixadmin';
$CONF['database_name'] = 'postfix';
$CONF['database_prefix'] = '';

// Here, if you need, you can customize table names.
$CONF['database_prefix'] = '';
$CONF['database_tables'] = array (
    'admin' => 'admin',
    'alias' => 'alias',
    'alias_domain' => 'alias_domain',
    'config' => 'config',
    'domain' => 'domain',
    'domain_admins' => 'domain_admins',
    'fetchmail' => 'fetchmail',
    'log' => 'log',
    'mailbox' => 'mailbox',
    'vacation' => 'vacation',
    'vacation_notification' => 'vacation_notification',
    'quota' => 'quota',
);

// Site Admin
// Define the Site Admins email address below.
// This will be used to send emails from to create mailboxes.
$CONF['admin_email'] = 'postmaster@change-this-to-your.domain.tld';

// Mail Server
// Hostname (FQDN) of your mail server.
// This is used to send email to Postfix in order to create mailboxes.
$CONF['smtp_server'] = 'localhost';
$CONF['smtp_port'] = '25';

// Encrypt
// In what way do you want the passwords to be crypted?
// md5crypt = internal postfix admin md5
// md5 = md5 sum of the password
// system = whatever you have set as your PHP system default
// cleartext = clear text passwords (ouch!)
// mysql_encrypt = useful for PAM integration
// authlib = support for courier-authlib style passwords
// dovecot:CRYPT-METHOD = use dovecotpw -s 'CRYPT-METHOD'. Example: dovecot:CRAM-MD5
$CONF['encrypt'] = 'md5crypt';

// In what flavor should courier-authlib style passwords be enrypted?
// md5 = {md5} + base64 encoded md5 hash
// md5raw = {md5raw} + plain encoded md5 hash
// crypt = {crypt} + Standard UNIX DES-enrypted with 2-character salt
$CONF['authlib_default_flavor'] = 'md5raw';

// If you use the dovecot encryption method: where is the dovecotpw binary located?
$CONF['dovecotpw'] = "/usr/sbin/dovecotpw";

// Minimum length required for passwords. Postfixadmin will not
// allow users to set passwords which are shorter than this value.
$CONF['min_password_length'] = 5;

// Generate Password
// Generate a random password for a mailbox or admin and display it.
// If you want to automagically generate paswords set this to 'YES'.
$CONF['generate_password'] = 'NO';

// Show Password
// Always show password after adding a mailbox or admin.
// If you want to always see what password was set set this to 'YES'.
$CONF['show_password'] = 'NO';

// Page Size
// Set the number of entries that you would like to see
// in one page.
$CONF['page_size'] = '10';

// Default Aliases
// The default aliases that need to be created for all domains.
$CONF['default_aliases'] = array (
    'abuse' => 'abuse@change-this-to-your.domain.tld',
    'hostmaster' => 'hostmaster@change-this-to-your.domain.tld',
    'postmaster' => 'postmaster@change-this-to-your.domain.tld',
    'webmaster' => 'webmaster@change-this-to-your.domain.tld'
);

// Mailboxes
// If you want to store the mailboxes per domain set this to 'YES'.
// Examples:
//   YES: /usr/local/virtual/domain.tld/username@domain.tld
//   NO:  /usr/local/virtual/username@domain.tld
$CONF['domain_path'] = 'NO';
// If you don't want to have the domain in your mailbox set this to 'NO'.
// Examples: 
//   YES: /usr/local/virtual/domain.tld/username@domain.tld
//   NO:  /usr/local/virtual/domain.tld/username
// Note: If $CONF['domain_path'] is set to NO, this setting will be forced to YES.
$CONF['domain_in_mailbox'] = 'YES';

// Default Domain Values
// Specify your default values below. Quota in MB.
$CONF['aliases'] = '10';
$CONF['mailboxes'] = '10';
$CONF['maxquota'] = '10';

// Quota
// When you want to enforce quota for your mailbox users set this to 'YES'.
$CONF['quota'] = 'NO';
// You can either use '1024000' or '1048576'
$CONF['quota_multiplier'] = '1024000';

// Transport
// If you want to define additional transport options for a domain set this to 'YES'.
// Read the transport file of the Postfix documentation.
$CONF['transport'] = 'NO';
// Transport options
// If you want to define additional transport options put them in array below.
$CONF['transport_options'] = array (
    'virtual',  // for virtual accounts
    'local',    // for system accounts
    'relay'     // for backup mx
);
// Transport default
// You should define default transport. It must be in array above.
$CONF['transport_default'] = 'virtual';

// Virtual Vacation
// If you want to use virtual vacation for you mailbox users set this to 'YES'.
// NOTE: Make sure that you install the vacation module. http://high5.net/postfixadmin/
$CONF['vacation'] = 'NO';
// This is the autoreply domain that you will need to set in your Postfix
// transport maps to handle virtual vacations. It does not need to be a
// real domain (i.e. you don't need to setup DNS for it).
$CONF['vacation_domain'] = 'autoreply.change-this-to-your.domain.tld';

// Vacation Control
// If you want users to take control of vacation set this to 'YES'.
$CONF['vacation_control'] ='YES';

// Vacation Control for admins
// Set to 'YES' if your domain admins should be able to edit user vacation.
$CONF['vacation_control_admin'] = 'YES';

// Alias Control
// Postfix Admin inserts an alias in the alias table for every mailbox it creates.
// The reason for this is that when you want catch-all and normal mailboxes
// to work you need to have the mailbox replicated in the alias table.
// If you want to take control of these aliases as well set this to 'YES'.
$CONF['alias_control'] = 'NO';

// Alias Control for admins
// Set to 'NO' if your domain admins shouldn't be able to edit user aliases.
$CONF['alias_control_admin'] = 'NO';

// Special Alias Control
// Set to 'NO' if your domain admins shouldn't be able to edit default aliases.
$CONF['special_alias_control'] = 'NO';

// Alias Goto Field Limit
// Set the max number of entries that you would like to see
// in one 'goto' field in overview, the rest will be hidden and "[and X more...]" will be added.
// '0' means no limits.
$CONF['alias_goto_limit'] = '0';

// Alias Domains
// Alias domains allow to "mirror" aliases and mailboxes to another domain. This makes 
// configuration easier if you need the same set of aliases on multiple domains, but
// also requires postfix to do more database queries.
// Note: If you update from 2.2.x or earlier, you will have to update your postfix configuration.
// Set to 'NO' to disable alias domains.
$CONF['alias_domain'] = 'YES';

// Backup
// If you don't want backup tab set this to 'NO';
$CONF['backup'] = 'YES';

// Send Mail
// If you don't want sendmail tab set this to 'NO';
$CONF['sendmail'] = 'YES';

// Logging
// If you don't want logging set this to 'NO';
$CONF['logging'] = 'YES';

// Fetchmail
// If you don't want fetchmail tab set this to 'NO';
$CONF['fetchmail'] = 'YES';

// fetchmail_extra_options allows users to specify any fetchmail options and any MDA
// (it will even accept 'rm -rf /' as MDA!)
// This should be set to NO, except if you *really* trust *all* your users.
$CONF['fetchmail_extra_options'] = 'NO';

// Header
$CONF['show_header_text'] = 'NO';
$CONF['header_text'] = ':: Postfix Admin ::';

// link to display under 'Main' menu when logged in as a user.
$CONF['user_footer_link'] = "http://change-this-to-your.domain.tld/main";

// Footer
// Below information will be on all pages.
// If you don't want the footer information to appear set this to 'NO'.
$CONF['show_footer_text'] = 'YES';
$CONF['footer_text'] = 'Return to change-this-to-your.domain.tld';
$CONF['footer_link'] = 'http://change-this-to-your.domain.tld';

// Welcome Message
// This message is send to every newly created mailbox.
// Change the text between EOM.
$CONF['welcome_text'] = <<<EOM
Hi,

Welcome to your new account.
EOM;

// When creating mailboxes or aliases, check that the domain-part of the
// address is legal by performing a name server look-up.
$CONF['emailcheck_resolve_domain']='YES';


// Optional:
// Analyze alias gotos and display a colored block in the first column
// indicating if an alias or mailbox appears to deliver to a non-existent
// account.  Also, display indications, for POP/IMAP mailboxes and
// for custom destinations (such as mailboxes that forward to a UNIX shell
// account or mail that is sent to a MS exchange server, or any other
// domain or subdomain you use)
// See http://www.w3schools.com/html/html_colornames.asp for a list of
// color names available on most browsers

//set to YES to enable this feature
$CONF['show_status']='NO';
//display a guide to what these colors mean
$CONF['show_status_key']='NO';
// 'show_status_text' will be displayed with the background colors
// associated with each status, you can customize it here
$CONF['show_status_text']='&nbsp;&nbsp;';
// show_undeliverable is useful if most accounts are delivered to this
// postfix system.  If many aliases and mailboxes are forwarded
// elsewhere, you will probably want to disable this.
$CONF['show_undeliverable']='NO';
$CONF['show_undeliverable_color']='tomato';
// mails to these domains will never be flagged as undeliverable
$CONF['show_undeliverable_exceptions']=array("unixmail.domain.ext","exchangeserver.domain.ext","gmail.com");
$CONF['show_popimap']='NO';
$CONF['show_popimap_color']='darkgrey';
// you can assign special colors to some domains. To do this,
// - add the domain to show_custom_domains
// - add the corresponding color to show_custom_colors
$CONF['show_custom_domains']=array("subdomain.domain.ext","domain2.ext");
$CONF['show_custom_colors']=array("lightgreen","lightblue");


// Optional:
// Script to run after creation of mailboxes.
// Note that this may fail if PHP is run in "safe mode", or if
// operating system features (such as SELinux) or limitations
// prevent the web-server from executing external scripts.
// $CONF['mailbox_postcreation_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postcreation.sh';

// Optional:
// Script to run after alteration of mailboxes.
// Note that this may fail if PHP is run in "safe mode", or if
// operating system features (such as SELinux) or limitations
// prevent the web-server from executing external scripts.
// $CONF['mailbox_postedit_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postedit.sh';

// Optional:
// Script to run after deletion of mailboxes.
// Note that this may fail if PHP is run in "safe mode", or if
// operating system features (such as SELinux) or limitations
// prevent the web-server from executing external scripts.
// $CONF['mailbox_postdeletion_script']='sudo -u courier /usr/local/bin/postfixadmin-mailbox-postdeletion.sh';

// Optional:
// Script to run after creation of domains.
// Note that this may fail if PHP is run in "safe mode", or if
// operating system features (such as SELinux) or limitations
// prevent the web-server from executing external scripts.
//$CONF['domain_postcreation_script']='sudo -u courier /usr/local/bin/postfixadmin-domain-postcreation.sh';

// Optional:
// Script to run after deletion of domains.
// Note that this may fail if PHP is run in "safe mode", or if
// operating system features (such as SELinux) or limitations
// prevent the web-server from executing external scripts.
// $CONF['domain_postdeletion_script']='sudo -u courier /usr/local/bin/postfixadmin-domain-postdeletion.sh';

// Optional:
// Sub-folders which should automatically be created for new users.
// The sub-folders will also be subscribed to automatically.
// Will only work with IMAP server which implement sub-folders.
// Will not work with POP3.
// If you define create_mailbox_subdirs, then the
// create_mailbox_subdirs_host must also be defined.
//
// $CONF['create_mailbox_subdirs']=array('Spam');
// $CONF['create_mailbox_subdirs_host']='localhost';
//
// Specify '' for Dovecot and 'INBOX.' for Courier.
$CONF['create_mailbox_subdirs_prefix']='INBOX.';

// Optional:
// Show used quotas from Dovecot dictionary backend in virtual
// mailbox listing.
// See: DOCUMENTATION/DOVECOT.txt
//      http://wiki.dovecot.org/Quota/Dict
//
// $CONF['used_quotas'] = 'YES';

//
// Normally, the TCP port number does not have to be specified.
// $CONF['create_mailbox_subdirs_hostport']=143;
//
// If you have trouble connecting to the IMAP-server, then specify
// a value for $CONF['create_mailbox_subdirs_hostoptions']. These
// are some examples to experiment with:
// $CONF['create_mailbox_subdirs_hostoptions']=array('notls');
// $CONF['create_mailbox_subdirs_hostoptions']=array('novalidate-cert','norsh');
// See also the "Optional flags for names" table at
// http://www.php.net/manual/en/function.imap-open.php


// Theme Config
// Specify your own logo and CSS file
$CONF['theme_logo'] = 'images/logo-default.png';
$CONF['theme_css'] = 'css/default.css';

// If you want to keep most settings at default values and/or want to ensure 
// that future updates work without problems, you can use a separate config 
// file (config.local.php) instead of editing this file and override some
// settings there.
if (file_exists(dirname(__FILE__) . '/config.local.php')) { # for /
    include(dirname(__FILE__) . '/config.local.php');
}

//
// END OF CONFIG FILE
//
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
