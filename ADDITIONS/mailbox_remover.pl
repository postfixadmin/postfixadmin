#!/usr/bin/perl
#
# by Petr Znojemsky (c) 2004
# Mailbox remover 0.1a 23/10/2004 - the very first version for MySQL
# removes maildirs from disk when they are not found in a database
#
# Added subdir support and pause --- Alan Batie 2007
# Lists directories to be deleted then pauses for 5 seconds for chance to abort
# $Id$
#
# All your maildirs or other directories could be accidentally removed.
# Use it at own risk. No warranties!

use strict;
use DBI;
use File::Path;

##########
# Set these variables according to your configuration

# when mailboxes are removed, save their tarballs here
my $archdir="/var/archive/mailboxes";

# expected to support z option, tweak invocation if you want different
my $archcmd="/usr/bin/tar";

# trailing slash not needed
my $maildir_path="/var/mail";
# find out if we need to check subdirs for mailboxes or just maildir_path
# $CONF['domain_path'] = 'YES';
my $pfadmin_config="/usr/local/www/postfixadmin/config.inc.php";

# database information
my $host="localhost";
my $port="3306";
my $userid="dbuser";
my $passwd="dbpw";
my $db="dbname";
############

my $connectionInfo="DBI:mysql:database=$db;$host:$port";
# make connection to database
my $dbh = DBI->connect($connectionInfo,$userid,$passwd);
# prepare and execute query
my $query = "SELECT maildir FROM mailbox";
my $sth = $dbh->prepare($query);
$sth->execute();

# assign fields to variables
my ($db_maildir, %db_maildirs);
$sth->bind_columns(\$db_maildir);

# load up directory list
while($sth->fetch()) {
    $db_maildirs{$db_maildir} = 1;
}

$sth->finish();
# disconnect from database
$dbh->disconnect;

# 
# find out if we need to check subdirs for mailboxes or just maildir_path
# $CONF['domain_path'] = 'YES';
#
my $use_subdirs = 0;
open(CONFIG, "<$pfadmin_config") || die "Can't open '$pfadmin_config': $!\n";
while(<CONFIG>) {
    if (/\$CONF\['domain_path'\] *= *'([^']*)'/) {
	$use_subdirs = ($1 =~ /yes/i);
    }
}
close(CONFIG);

# store maildir list to %directories
# key is path, value is username to use in archive file
my %directories;
opendir(DIR, $maildir_path) || die "Cannot open dir $maildir_path: $!\n";
foreach my $name (readdir(DIR)) {
    next if ($name eq '.' || $name eq '..' || ! -d "$maildir_path/$name");

    if ($use_subdirs) {
	opendir(SUBDIR, "$maildir_path/$name") || die "Cannot open dir $maildir_path/$name: $!\n";
	foreach my $subname (readdir(SUBDIR)) {
	    next if ($subname eq '.' || $subname eq '..' || ! -d "$maildir_path/$name/$subname");
	    # db entry has trailing slash...
	    if (!defined($db_maildirs{"$name/$subname/"})) {
	        print "marking $maildir_path/$name/$subname for deletion.\n";
		$directories{"$name/$subname"} = "$name-$subname";
	    }
	}
	closedir(SUBDIR);
    } else {
	# db entry has trailing slash...
	if (!defined($db_maildirs{"$name/"})) {
	    print "marking $maildir_path/$name for deletion.\n";
	    $directories{"$name"} = $name;
	}
    }
}
closedir(DIR);

print "Ctrl-C in 5 seconds to abort before removal starts...\n";
sleep 5;

my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time);
# yyyymmddhhmm
my $tstamp = sprintf("%04d%02d%02d%02d%02d", $year+1900, $mon+1, $mday, $hour, $min);

# compare two arrays and erase maildirs not found in database
chdir $maildir_path || die "Can't change to maildir '$maildir_path': $!\n";;
my @args;
foreach my $maildir (keys(%directories)) {
    my $archive = "$archdir/$directories{$maildir}-$tstamp.tgz";
    # quick permissions check
    open(TOUCH, ">$archive") || die "Can't create archive file $archive: $!\n";
    close(TOUCH);
    print "Archiving $maildir\n";
    @args = ($archcmd, "cvzf", $archive, $maildir);
    system(@args) == 0 or die "Creating archive for $maildir failed: $?";

    rmtree($maildir);
    print localtime() . " $maildir has been deleted.\n";
}
