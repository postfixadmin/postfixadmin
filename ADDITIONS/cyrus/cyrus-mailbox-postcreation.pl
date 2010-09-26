#!/usr/bin/perl 

# Cyrus Mailbox creation
#
# Iñaki Rodriguez (irodriguez@virtualminds.es / irodriguez@ackstorm.es)
# 
#  LICENSE
#  This source file is subject to the GPL license that is bundled with
#  this package in the file LICENSE.TXT.
#
# (26/10/2009) 

use Cyrus::IMAP::Admin;
require '/etc/mail/postfixadmin/cyrus.conf';
use strict;
use vars qw($cyrus_user $cyrus_password $cyrus_host);

my %opts;

my $mailbox = mailbox_name($ARGV[0]);

my $client = Cyrus::IMAP::Admin->new($cyrus_host);
die_on_error($client);

$opts{-user} = $cyrus_user;
$opts{-password} = $cyrus_password;

$client->authenticate(%opts);
die_on_error($client);

$client->create($mailbox);
die_on_error($client);

$client->setquota($mailbox,'STORAGE',scalar $ARGV[3]) if ($ARGV[3] > 0);
die_on_error($client);

