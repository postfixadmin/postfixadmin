#!/usr/bin/perl -w

################################################################################
#
# cleanupdirs 1.2 by jared bell <jared@beol.net>
#
# display/remove maildir & domains directory tree's not listed in the postfix
# mysql database. currently setup for use with postfixadmin, but can be
# adapted. edit settings where it says 'change settings as needed.' by default
# this program will display a list of directories which need deleted, nothing
# is actually deleted. to change this behavior, look into the command line
# arguments.
#
# command line arguments:
#   --delete
#       force automatic deletion of directories. instead of displaying a list
#       of deleted directories, they will be logged in the specified logfile.
#   --print
#       display deleted directories as well as log them. only valid when
#       '--delete' has been specified.
#
# settings:
#   $root_path = "/home/vmail";
#       if maildir is '/home/vmail/domain.tld/user' then '/home/vmail' is the
#       $root_path. if your maildirs are '/home/vmail/user@domain.tld' then
#       this program will need to be modified in order to work right.
#   $logfile = "/var/log/removed_maildirs.log";
#       the logfile to use when $delete_old_dirs is set to 1
#   $db_* = "*";
#       sets the host, port, database, user and pass to your mysql server
#
# version history:
#   1.2 - removed uneeded settings. added '--print' command line argument
#   1.1 - added '--delete' command line argument
#   1.0 - initial release
#
################################################################################

use strict;
use DBI;
use File::Path;
use Getopt::Long;

### change settings as needed, see notes above #################################
my $root_path = "/home/vmail";
my $logfile = "/var/log/removed_maildirs.log";
my $db_hostname = "localhost";
my $db_port = "3306";
my $db_database = "postfix";
my $db_username = "someuser";
my $db_password = "somepass";
################################################################################

### begin program ##############################################################
my(@dirs_to_delete, $logfile_open);
my $delete_old_dirs = 0; # do not delete by default, use cmdline to change this
my $print_also = 0; # also print items when deleting, use cmdline to change this
GetOptions ('delete' => \$delete_old_dirs, 'print' => \$print_also);
my $conn_info = "DBI:mysql:database=$db_database;hostname=$db_hostname;port=$db_port";
my $dbh = DBI->connect($conn_info, $db_username, $db_password)
  or die $DBI::errstr;
opendir DOMAINDIR, $root_path
  or die "Unable to access directory '$root_path' ($!)";
foreach my $domain_dir (sort readdir DOMAINDIR) {
  next if $domain_dir =~ /^\./; # skip dotted dirs
  my $full_domain_dir = "$root_path/$domain_dir";
  opendir USERDIR, $full_domain_dir
    or die "Unable to access directory '$full_domain_dir' ($!)";
  foreach my $user_dir (sort readdir USERDIR) {
    next if $user_dir =~ /^\./; # skip dotted dirs
    push @dirs_to_delete, "$full_domain_dir/$user_dir"
      if &check_dir("SELECT maildir FROM mailbox WHERE maildir = ?",
        "$domain_dir/$user_dir/"); # end slash needed for checkdir
  }
  push @dirs_to_delete, $full_domain_dir
    if &check_dir("SELECT domain FROM domain WHERE domain = ?", $domain_dir);
}
closedir USERDIR;
closedir DOMAINDIR;
$dbh->disconnect;
if (@dirs_to_delete) {
  foreach my $to_delete (@dirs_to_delete) {
    if ($delete_old_dirs == 1) {
      $logfile_open = open LOGFILE, ">> $logfile"
        or die "Unable to append logfile '$logfile' ($!)"
          unless $logfile_open;
      rmtree $to_delete;
      print LOGFILE localtime() . " Deleting directory '$to_delete'\n";
      print localtime() . " Deleting directory '$to_delete'\n"
        if $print_also;
    } else {
      print localtime() . " Need to delete directory '$to_delete'\n";
    }
  }
}
close LOGFILE if $logfile_open;
sub check_dir {
  my($query, $dir) = @_;
  my $sth = $dbh->prepare($query);
  my $num_rows = $sth->execute($dir);
  $sth->finish;
  ($num_rows eq "0E0") ? 1 : 0;
}
