#!/usr/bin/perl
# vim:ts=4:sw=4:et
# Virtual quota_usage 0.3
# Contributed to Postfixadmin by Jose Nilton <jniltinho@gmail.com> 
#
# See also : http://www.russelldare.net/media/perl/dirsizeSource.pdf
# License: GPL v2.

# Usage:
# perl quota_usage.pl --list 
# perl quota_usage.pl --list --addmysql 
#                                      for add mysql database postfix 
#
# Requirements - the following perl modules are required:
# DBD::Pg or DBD::mysql; perl perl-DBD-mysql perl-DBD (may be named differently depending on your platform).
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
my $db_type     = 'mysql'; 
##END EDIT##

(help()) if (!$ARGV[0]);

$ENV{'PATH'} = "/sbin:/bin:/usr/sbin:/usr/bin";
my($domain_dir, $full_domain_dir, $user_dir, $usage, $email, $sql, $dbh);

my $list = 0;
my $insert_db = 0;
my $total_mailbox = 0;
my $total_domain = 0;
GetOptions ('l|list' => \$list, 'i|addmysql' => \$insert_db, 'help|h|man' => \&help) or (help());


(list_quota_usage()) if ($list == 1 || $insert_db == 1 );



sub list_quota_usage {
    opendir(DOMAINDIR, $root_path) or die ("Unable to access directory '$root_path' ($!)");

    if($insert_db == 1){
        $dbh = DBI->connect("DBI:$db_type:database=$db_database;host=$db_host", $db_user, $db_password) or die ("cannot connect the database");
        execSql("UPDATE mailbox set quota_usage = 0");
    }

    foreach $domain_dir (sort readdir DOMAINDIR) {
        next if $domain_dir =~ /^\./;                    # skip dotted dirs
        $full_domain_dir = "$root_path/$domain_dir"; #print "$full_domain_dir\n";
        $total_domain++;            

        opendir(USERDIR, $full_domain_dir) or die ("Unable to access directory '$full_domain_dir' ($!)");
        foreach $user_dir (sort readdir USERDIR) {
            next if $user_dir =~ /^\./; # skip dotted dirs
            $email = "$user_dir\@$domain_dir";
            $total_mailbox++;

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

            if($insert_db == 1)
            {
                execSql("INSERT INTO quota2 (username, bytes) values ('$email', $usage) ON DUPLICATE KEY UPDATE bytes = VALUES(bytes)"); 
            }
            print_list() if ($list == 1);

        }
    }
    close(DOMAINDIR);
    close(USERDIR);

    (print_total()) if ($list == 1);

}





sub execSql {
    my $sql = shift;
    my $ex;
    $ex = $dbh->do($sql) or die ("error when running $sql");
}



sub print_total{
    print "---------------------------------------------------------\n";
    print "TOTAL DOMAIN\t\t\t\tTOTAL MAILBOX\n";
    print "---------------------------------------------------------\n";
    print "$total_domain\t\t\t\t\t\t$total_mailbox\n";
}



sub print_list {
format STDOUT_TOP =
Report of Quota Used
---------------------------------------------------------
EMAIL                                         QUOTA USED
---------------------------------------------------------
.


format = 
@<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<         @<<<<<<<<<<
$email,                                           "$usage MB"                         
.

    write;
}





sub help {
    print "$0 [options...]\n";
    print "-l|--list                     List quota used\n";
    print "-i|--addmysql                 For insert quota used in database mysql\n";
}
