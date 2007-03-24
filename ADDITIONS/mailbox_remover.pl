#!/usr/bin/perl
#
# by Petr Znojemsky (c) 2004
# Mailbox remover 0.1a 23/10/2004 - the very first version for MySQL
# removes maildirs from disk when they are not found in a database
# Run program and read the $logfile before uncommenting the "rmtree" line!
# All your maildirs or other directories could be accidentally removed.
# Use it at own risk. No warranties!

use DBI;
use File::Path;

##########
# Set these variables according to your configuration
$maildir_path="/var/mail/virtual/";
$logfile="/var/log/mail/removed_maildirs";

# database information
$host="localhost";
$port="3306";
$userid="postfix";
$passwd="postfix";
$db="postfix";
############

$connectionInfo="DBI:mysql:database=$db;$host:$port";
# make connection to database
$dbh = DBI->connect($connectionInfo,$userid,$passwd);
# prepare and execute query
$query = "SELECT username FROM mailbox";
$sth = $dbh->prepare($query);
$sth->execute();
# assign fields to variables
$sth->bind_columns(\$username);
# output computer list to the browser
while($sth->fetch()) {
push(@usernames, $username);
}
$sth->finish();
# disconnect from database
$dbh->disconnect;

# store maildir list to @directories
opendir(DIRHANDLE, $maildir_path) || die "Cannot open dir $maildir_path: $!";
foreach $name (sort readdir(DIRHANDLE))
{
   push (@directories, $name);
}
closedir(DIRHANDLE);
# eliminate "." and ".." from the maildir list
($dot, $doubledot, @directories) = @directories;


# compare two arrays and erase maildirs not found in database
foreach $maildir (@directories)
{
   if ((grep { $_ eq $maildir} @usernames)==0)
   {
      # username not found, delete maildir.
      # Please read $logfile before uncommenting following line!
      # rmtree($maildir_path.$maildir);
      open(INFO, ">>$logfile") || die "Cannot write to the logfile: $logfile.";
      print INFO localtime()." Maildir ".$maildir_path.$maildir." has been deleted.\n";
      close(INFO);
   }
}
