#!/usr/bin/perl

use DBI;
use MIME::Base64;
# use Data::Dumper;

# the home dir of vmail user:
$vmail_dir="/home/maildirs";

# mysql settings
$database="mailadmin";
$hostname="127.0.0.1";
$user="mail";
$password="*****";
$dsn = "DBI:mysql:database=$database;host=$hostname";

#mysql connect
$dbh = DBI->connect($dsn, $user, $password) || die "cannot connect the database";

$sql=<<SQL;
SELECT id,mailbox,src_server,src_auth,src_user,src_password,src_folder,fetchall,keep,protocol,mda,extra_options 
FROM fetchmail
WHERE unix_timestamp(now())-unix_timestamp(date) > poll_time*60
SQL

my (%config);
map{
	my ($id,$mailbox,$src_server,$src_auth,$src_user,$src_password,$src_folder,$fetchall,$keep,$protocol,$mda,$extra_options)=@$_;
	
	$cmd="user '${src_user}' there with password '".decode_base64($src_password)."'";
	$cmd.=" folder '${src_folder}'" if ($src_folder);
	$cmd.=" mda ".$mda if ($mda);

#	$cmd.=" mda \"/usr/local/libexec/dovecot/deliver -m ${mailbox}\"";
	$cmd.=" is '${mailbox}' here";
	
	$cmd.=" keep" if ($keep);
	$cmd.=" fetchall" if ($fetchall);
	$cmd.=" ".$extra_options if ($extra_options);
	
	$text=<<TXT;
set postmaster "postmaster"
set nobouncemail
set no spambounce
set properties ""

poll ${src_server} with proto ${protocol}
	$cmd
	
TXT

	open X,"> ${vmail_dir}/.fetchmailrc" || die "cannot open/create ${vmail_dir}/.fetchmailrc";
	print X $text;
	close X;
	chmod 0600,"${vmail_dir}/.fetchmailrc";
	$ret=`/usr/bin/fetchmail`;
	$sql="UPDATE fetchmail SET returned_text=".$dbh->quote($ret).", date=now() WHERE id=".$id;
	$dbh->do($sql);

}@{$dbh->selectall_arrayref($sql)};

unlink "${vmail_dir}/.fetchmailrc";

