#!/usr/bin/perl
#
##
## pfa_maildir_cleanup.pl
##
## (c) 2004 by Stephen Fulton (sfulton@esoteric.ca)
##
## based on a script by Petr Znojemsky (thanks!) 
##
## Simple script to remove maildirs/domains not listed in a MySQL database.
## Set up for use with those using PostfixAdmin, but can be adapted.
##
## Edit the variables between the ##EDIT## to match your setup.
##
## USE AT YOUR OWN RISK. I ASSUME NO RESPONSIBILITY.
##

use DBI;
use File::Path;

##EDIT## 

$root_path = "/home/mail";
$logfile = "/var/log/removed_maildirs.log";

$db_host = "localhost";
$db_database = "database";
$db_user = "username";
$db_password = 'password';

##END EDIT##


$connectionInfo = "DBI:mysql:database=$db_database;$db_host:3306";

## Read a list of domain directories in the root path /remote/mail1

opendir(DIRHANDLE, $root_path) || die "Cannot access directory $maildir_path: $!";

my @directories = ();

foreach $directory (sort readdir(DIRHANDLE)) {
   push (@directories, $directory);
}

closedir(DIRHANDLE);

## Strip the "." and ".." from the directories array

($dot, $doubledot, @directories) = @directories;

## For each of the domain directories..

foreach $domain_dir (@directories) {
   $complete_domain_path = "$root_path/$domain_dir";

   ## Get a list of user directories within each domain directory...

   opendir(DOMAINHANDLE, $complete_domain_path) || die "Cannot access directory $complete_domain_path: $!";

   my @user_directories = ();

   foreach $dir (sort readdir(DOMAINHANDLE)) {
      push(@user_directories, $dir);
   }
   close(DOMAINHANDLE);

   ## Now remove any "." or ".." directory entries and construct a domain/maildir variable
   ## valid for one iteration of loop.

   foreach $user_directory (@user_directories) {
      if( not($user_directory eq '..') && not($user_directory eq '.') ) {
         $short_user_dir = "$domain_dir/$user_directory/";

         ## Here is where the $short_user_dir is compared against the DB entry.

         $dbh = DBI->connect($connectionInfo,$db_user,$db_password);
         $user_query = "SELECT maildir FROM mailbox WHERE maildir = '$short_user_dir'";
         $sth = $dbh->prepare($user_query);
         $rows = $sth->execute();

         ## If there are no rows that match, then directory is orphaned and can
         ## be deleted.

         if($rows == 0) {
            $maildir_path = "$root_path/$short_user_dir";
            open(INFO, ">>$logfile") || die "Cannot write to the logfile: $logfile.";
            rmtree($maildir_path);
            print INFO localtime()." Maildir ".$maildir_path." has been deleted.\n";
            (INFO);
         }
         $sth->finish;
         $dbh->disconnect;
      }
   }

   $dbh2 = DBI->connect($connectionInfo,$db_user,$db_password);
   $domain_query = "SELECT domain FROM domain WHERE domain = '$domain_dir'";
   $sth2 = $dbh2->prepare($domain_query);
   $domain_rows = $sth2->execute();

   if($domain_rows == 0) {
      open(INFO, ">>$logfile") || die "Cannot write to the logfile: $logfile.";
      rmtree($complete_domain_path);
      print INFO localtime()." Domain directory ".$complete_domain_path." has been deleted.\n";
      close(INFO);
   }

$sth2->finish;
$dbh2->disconnect;
}
