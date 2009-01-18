#!/usr/bin/perl
# vim:ts=4:sw=4:et
#
# Contributed to Postfixadmin by Jose Nilton <jniltinho@gmail.com> 
#
# See also : http://www.russelldare.net/media/perl/dirsizeSource.pdf
# License: GPL v2.

# Usage:
# perl quota_usage.pl --list or  
# perl quota_usage.pl --addmysql for add mysql database postfix 
#
# Requires: perl perl-DBD-mysql perl-DBD (may be named differently depending on your platform).
#           and the 'du' binary in $ENV{'PATH'} (see below).
#
# You will need to modify the postfix DATABASE to add a quota_usage column. 
# Mysql:
# 	ALTER TABLE mailbox ADD quota_usage INT(11) NOT NULL DEFAULT '0' AFTER modified, 
# 	ADD quota_usage_date DATE NOT NULL DEFAULT '0000-00-00' AFTER quota_usage;
# PostgreSQL:
# 	ALTER TABLE mailbox ADD COLUMN quota_usage INTEGER NOT NULL DEFAULT 0;
# 	ALTER TABLE mailbox ADD COLUMN quota_usage_date DATE NOT NULL DEFAULT current_date;
#

use strict;
use warnings;
use File::Path;
use DBI;
use Getopt::Long;

##EDIT## 
my $db_host 	= 'localhost';
my $db_database = 'postfix';
my $db_user 	= 'postfix';
my $db_password = '123456';
my $root_path 	= '/home/vmail';
# Pg or mysql
my $db_type     = 'Pg'; 
##END EDIT##

$ENV{'PATH'} = "/sbin:/bin:/usr/sbin:/usr/bin";
my($domain_dir, $full_domain_dir, $user_dir, $usage, $email, $sql, $dbh);

GetOptions ('list' => \&list_quota_usage, 'addmysql' => \&insert_to_db);

sub list_quota_usage {
    opendir(DOMAINDIR, $root_path) or die ("Unable to access directory '$root_path' ($!)");

    foreach $domain_dir (sort readdir DOMAINDIR) {
        next if $domain_dir =~ /^\./;                # skip dotted dirs
            $full_domain_dir = "$root_path/$domain_dir"; #print "$full_domain_dir\n";

        opendir(USERDIR, $full_domain_dir) or die ("Unable to access directory '$full_domain_dir' ($!)");
        foreach $user_dir (sort readdir USERDIR) {
            next if $user_dir =~ /^\./; # skip dotted dirs
                $email = "$user_dir\@$domain_dir";

            my $i = `du -0 --summarize $full_domain_dir/$user_dir`;
            ($usage) = split(" ", $i);

            if ($usage < 100) {
                $usage = 0;
            } elsif ($usage < 1000) {
                $usage = 1;
            } else {
                $usage = $usage + 500;
                $usage = int $usage / 1000;
            }

            list_out();
        }
    }
    close(DOMAINDIR);
    close(USERDIR);
}


sub insert_to_db {
    opendir(DOMAINDIR, $root_path) or die ("Unable to access directory '$root_path' ($!)");

    $dbh = DBI->connect("DBI:$db_type:database=$db_database;host=$db_host", $db_user, $db_password) or die ("cannot connect the database");
    execSql("UPDATE mailbox set quota_usage = 0");


    foreach $domain_dir (sort readdir DOMAINDIR) {
        next if $domain_dir =~ /^\./; # skip dotted dirs
            $full_domain_dir = "$root_path/$domain_dir"; #print "$full_domain_dir\n";

        opendir(USERDIR, $full_domain_dir) or die ("Unable to access directory '$full_domain_dir' ($!)");
        foreach $user_dir (sort readdir USERDIR) {
            next if $user_dir =~ /^\./; # skip dotted dirs
                $email = "$user_dir\@$domain_dir";

            my $i = `du -0 --summarize $full_domain_dir/$user_dir`;
            ($usage) = split(" ", $i);

            if ($usage < 100) {
                $usage = 0;
            } elsif ($usage < 1000) {
                $usage = 1;
            } else {
                $usage = $usage + 500;
                $usage = int $usage / 1000;
            }

            execSql("UPDATE mailbox set quota_usage = $usage, quota_usage_date = CAST(NOW() AS DATE) WHERE username = '$email'");
#list_out(); #Debug
        }

    }

    close(DOMAINDIR);
    close(USERDIR);

}


sub execSql {
    my $sql = shift;
    my $ex;
    $ex = $dbh->do($sql) or die ("error when running $sql");
}

sub list_out {
format STDOUT_TOP =
Report of Quota Used
--------------------------
EMAIL                                         QUOTA USED
------------------------------------------------------------------
.


format = 
@<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<              @<<<<<<<<<<
$email,                                                "$usage\bMB"                         
.

    write;
}

