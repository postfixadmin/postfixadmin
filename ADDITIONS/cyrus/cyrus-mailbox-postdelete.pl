#!/usr/bin/perl 

# Cyrus Mailbox deletion
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

$client->setacl($mailbox,$cyrus_user => 'all');
die_on_error($client);

$client->deletemailbox($mailbox);
die_on_error($client);

