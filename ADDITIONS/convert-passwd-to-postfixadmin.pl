#!/usr/bin/perl -w
#
# Postfix Admin 
# 
# LICENSE 
# This source file is subject to the GPL license that is bundled with  
# this package in the file LICENSE.TXT. 
# 
# Further details on the project are available at http://postfixadmin.sf.net 
# 
# @version $Id$ 
# @license GNU GPL v2 or later. 
#
#
# Really crude attempt at taking all users from a local 
# passwd file (/etc/shadow) and creating postfixadmin mailboxes for them.
#
# The script outputs some SQL, which you need to then insert into your database
# as appropriate.
#
# Notes:
#  1) Change $mydomain and $true as required.
#  2) Ideally it should parse /etc/passwd, or call the getpw()? function and
#     populate someone's name if known.
#  3) There's plenty of room for improvement.
#
# Original author: David Goodwin <david at palepurple-co-uk> - 2007/10/05.
#
use strict;

open(FH, '</etc/shadow') or die ('Cannot open shadow file; you need to be root - ' . $!);
my $mydomain = "test.com";
my $true = "t"; # t for pgsql; 1 for mysql
foreach(<FH>) { 
    my ($username, $password) = split(':', $_);
    next if $password eq '!';
    next if $password eq '*';
    my $maildir = "$username\@$mydomain/";
    print "insert into mailbox (username, password, domain, active, maildir) values ('$username', '$password', '$mydomain', $true, '$maildir');\n";
}
