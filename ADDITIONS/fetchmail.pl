#!/usr/bin/perl

use DBI;
use MIME::Base64;
# use Data::Dumper;
use File::Temp qw/ mkstemp /;
use Sys::Syslog;
# require liblockfile-simple-perl
use LockFile::Simple qw(lock trylock unlock);

openlog("fetchmail-all", "pid", "mail");

sub log_and_die {
	my($message) = @_;
  syslog("err", $message);
  die $message;
}

# read options and arguments

$configfile = "/etc/fetchmail-all/config";

@ARGS1 = @ARGV;

while ($_ = shift @ARGS1) {
    if (/^-/) {
        if (/^--config$/) {
            $configfile = shift @ARGS1
        }
    }
}

# mysql settings
$database="mailadmin";
$hostname="127.0.0.1";
$user="mail";

$run_dir="/var/run/fetchmail";

# use specified config file
if (-e $configfile) {
    do $configfile;
}

$dsn = "DBI:mysql:database=$database;host=$hostname";
$lock_file=$run_dir . "/fetchmail-all.lock";

$lockmgr = LockFile::Simple->make(-autoclean => 1, -max => 1);
$lockmgr->lock($lock_file) || log_and_die "can't lock ${lock_file}";

#mysql connect
$dbh = DBI->connect($dsn, $user, $password) || log_and_die "cannot connect the database";

$sql=<<SQL;
SELECT id,mailbox,src_server,src_auth,src_user,src_password,src_folder,fetchall,keep,protocol,mda,extra_options,usessl 
FROM fetchmail
WHERE unix_timestamp(now())-unix_timestamp(date) > poll_time*60
SQL

my (%config);
map{
	my ($id,$mailbox,$src_server,$src_auth,$src_user,$src_password,$src_folder,$fetchall,$keep,$protocol,$mda,$extra_options,$usessl)=@$_;

  syslog("info","fetch ${src_user}@${src_server} for ${mailbox}");
	
	$cmd="user '${src_user}' there with password '".decode_base64($src_password)."'";
	$cmd.=" folder '${src_folder}'" if ($src_folder);
	$cmd.=" mda ".$mda if ($mda);

#	$cmd.=" mda \"/usr/local/libexec/dovecot/deliver -m ${mailbox}\"";
	$cmd.=" is '${mailbox}' here";
	
	$cmd.=" keep" if ($keep);
	$cmd.=" fetchall" if ($fetchall);
	$cmd.=" ssl" if ($usessl);
	$cmd.=" ".$extra_options if ($extra_options);
	
	$text=<<TXT;
set postmaster "postmaster"
set nobouncemail
set no spambounce
set properties ""
set syslog

poll ${src_server} with proto ${protocol}
	$cmd
	
TXT

  ($file_handler, $filename) = mkstemp( "/tmp/fetchmail-all-XXXXX" ) or log_and_die "cannot open/create fetchmail temp file";
  print $file_handler $text;
  close $file_handler;

  $ret=`/usr/bin/fetchmail -f $filename -i $run_dir/fetchmail.pid`;

  unlink $filename;

  $sql="UPDATE fetchmail SET returned_text=".$dbh->quote($ret).", date=now() WHERE id=".$id;
  $dbh->do($sql);
}@{$dbh->selectall_arrayref($sql)};

$lockmgr->unlock($lock_file);
closelog();
