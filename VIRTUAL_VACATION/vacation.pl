#!/usr/bin/perl -w
#
# Virtual Vacation 4.0r1
#
# $Revision$
# Originally by Mischa Peters <mischa at high5 dot net>
#
# Copyright (c) 2002 - 2005 High5!
# Licensed under GPL for more info check GPL-LICENSE.TXT
#
# Additions:
# 2004/07/13  David Osborn <ossdev at daocon.com>
#             strict, processes domain level aliases, more
#             subroutines, send reply from original to address
#
# 2004/11/09  David Osborn <ossdev at daocon.com>
#             Added syslog support
#             Slightly better logging which includes messageid
#             Avoid infinite loops with domain aliases
#
# 2005-01-19  Troels Arvin <troels at arvin.dk>
#             PostgreSQL-version.
#             Normalized DB schema from one vacation table ("vacation")
#             to two ("vacation", "vacation_notification"). Uses
#             referential integrity CASCADE action to simplify cleanup
#             when a user is no longer on vacation.
#             Inserting variables into queries stricly by prepare()
#             to try to avoid SQL injection.
#             International characters are now handled well.
#
# 2005-01-21  Troels Arvin <troels at arvin.dk>
#             Uses the Email::Valid package to avoid sending notices
#             to obviously invalid addresses.
#
# 2007-08-15  David Goodwin <david at palepurple.co.uk>
#             Use the Perl Mail::Sendmail module for sending mail
#             Check for headers that start with blank lines (patch from forum)
#
# 2007-08-20  Martin Ambroz <amsys at trustica.cz>
#             Added initial Unicode support
#
# 2008-05-09  Fabio Bonelli <fabiobonelli at libero.it>
#             Properly handle failed queries to vacation_notification.
#             Fixed log reporting.
#
# 2008-07-29  Patch from Luxten to add repeat notification after timeout. See:
#             https://sourceforge.net/tracker/index.php?func=detail&aid=2031631&group_id=191583&atid=937966
#
# 2008-08-01  Luigi Iotti <luigi at iotti dot biz>
#             Use envelope sender/recipient instead of using
#             From: and To: header fields;
#             Support to good vacation behavior as in
#             http://www.irbs.net/internet/postfix/0707/0954.html
#             (needs to be tested);
#
# 2008-08-04  David Goodwin <david at palepurple dot co dot uk>
#             Use Log4Perl
#             Added better testing (and -t option)
#
# 2009-06-29  Stevan Bajic <stevan at bajic.ch>
#             Add Mail::Sender for SMTP auth + more flexibility
#
# 2009-07-07  Stevan Bajic <stevan at bajic.ch>
#             Add better alias lookups
#             Check for more heades from Anti-Virus/Anti-Spam solutions
#
# 2009-08-10  Sebastian <reg9009 at yahoo dot de>
#             Adjust SQL query for vacation timeframe. It is now possible to set from/until date for vacation message.
#
# 2012-04-1   Nikolaos Topp <info at ichier.de>
#             Add configuration parameter $smtp_client in order to get mails through
#             postfix helo-checks, using check_helo_access whitelist without permitting 'localhost' default style stuff
#
# 2012-04-19  Jan Kruis <jan at crossreference dot nl>
#             change SQL query for vacation into function.
#             Add sub get_interval()
#             Gives the user the option to set the interval time ( 0 = one reply, 1 = autoreply, > 1 = Delay reply ) 
#             See https://sourceforge.net/tracker/?func=detail&aid=3508083&group_id=191583&atid=937966
#
# 2012-06-18  Christoph Lechleitner <christoph.lechleitner@iteg.at>
#             Add capability to include the subject of the original mail in the subject of the vacation message.
#             A good vacation subject could be: 'Re: $SUBJECT'
#             Also corrected log entry about "Already informed ..." to show the $orig_from, not $email
#

# Requirements - the following perl modules are required:
# DBD::Pg or DBD::mysql
# Mail::Sender, Email::Valid MIME::Charset, Log::Log4perl, Log::Dispatch, MIME::EncWords and GetOpt::Std
#
# You may install these via CPAN, or through your package tool.
# CPAN: 'perl -MCPAN -e shell', then 'install Module::Whatever'
#
# On Debian based systems :
#   libmail-sender-perl
#   libdbd-pg-perl
#   libemail-valid-perl
#   libmime-perl
#   liblog-log4perl-perl
#   liblog-dispatch-perl
#   libgetopt-argvfile-perl
#   libmime-charset-perl (currently in testing, see instructions below)
#   libmime-encwords-perl (currently in testing, see instructions below)
#
# Note: When you use this module, you may start seeing error messages
# like "Cannot insert a duplicate key into unique index
# vacation_notification_pkey" in your system logs. This is expected
# behavior, and not an indication of trouble (see the "already_notified"
# subroutine for an explanation).
#
# You must also have the Email::Valid and MIME-tools perl-packages
# installed. They are available in some package collections, under the
# names 'perl-Email-Valid' and 'perl-MIME-tools', respectively.
# One such package collection (for Linux) is:
# http://dag.wieers.com/home-made/apt/packages.php
#

use utf8;
use DBI;
use MIME::Base64 qw(encode_base64);
use Encode qw(encode decode);
use MIME::EncWords qw(:all);
use Email::Valid;
use strict;
use Mail::Sender;
use Getopt::Std;
use Log::Log4perl qw(get_logger :levels);
use File::Basename;

# ========== begin configuration ==========

# IMPORTANT: If you put passwords into this script, then remember
# to restrict access to the script, so that only the vacation user
# can read it.

# db_type - uncomment one of these
our $db_type = 'Pg';
#our $db_type = 'mysql';

# leave empty for connection via UNIX socket
our $db_host = '';

# connection details
our $db_username = 'user';
our $db_password = 'password';
our $db_name     = 'postfix';

our $vacation_domain = 'autoreply.example.org';

# smtp server used to send vacation e-mails
our $smtp_server = 'localhost';
our $smtp_server_port = 25;

# this is the helo we [the vacation script] use on connection; you may need to change this to your hostname or something,
# depending upon what smtp helo restrictions you have in place within Postfix. 
our $smtp_client = 'localhost';

# SMTP authentication protocol used for sending.
# Can be 'PLAIN', 'LOGIN', 'CRAM-MD5' or 'NTLM'
# see "perldoc Mail::Sender" (search for "auth") for more options and details
# Leave it blank if you don't use authentication
our $smtp_auth = undef;
# username used to login to the server
our $smtp_authid = 'someuser';
# password used to login to the server
our $smtp_authpwd = 'somepass';

# This specifies the mail 'from' name which is shown to recipients of vacation replies.
# If you leave it empty, the vacation mail will contain: 
# From: <original@recipient.domain>
# If you specify something here you'd instead see something like :
# From: Some Friendly Name <original@recipient.domain>
our $friendly_from = '';

# use TLS for the SMTP connection?
# while in general this would be a good idea, TLS with Mail::Sender 0.8.22 is buggy - https://rt.cpan.org/Public/Bug/Display.html?id=85438
our $smtp_tls_allowed = 0;

# Set to 1 to enable logging to syslog.
our $syslog = 0;

# path to logfile, when empty logging is suppressed
# change to e.g. /dev/null if you want nothing logged.
# if we can't write to this, and $log_to_file is 1 (below) the script will abort.
our $logfile='/var/log/vacation.log';
# 2 = debug + info, 1 = info only, 0 = error only
our $log_level = 2;
# Whether to log to file or not, 0 = do not write to a log file
our $log_to_file = 0;

# notification interval, in seconds
# set to 0 to notify only once
# e.g. 1 day ...
#our $interval = 60*60*24;
# disabled by default
our $interval = 0;

# Send vacation mails to do-not-reply email addresses.
# By default vacation email addresses will be sent.
# For now emails from bounce|do-not-reply|facebook|linkedin|list-|myspace|twitter won't
# be answered when $custom_noreply_pattern is set to 1.
# default = 0
our $custom_noreply_pattern = 0;
our $noreply_pattern = 'bounce|do-not-reply|facebook|linkedin|list-|myspace|twitter'; 


# instead of changing this script, you can put your settings to /etc/mail/postfixadmin/vacation.conf
# or /etc/postfixadmin/vacation.conf just use Perl syntax there to fill the variables listed above
# (without the "our" keyword). Example:
# $db_username = 'mail';
if (-f '/etc/mail/postfixadmin/vacation.conf') {
    require '/etc/mail/postfixadmin/vacation.conf';
} elsif (-f '/etc/postfixadmin/vacation.conf') {
    require '/etc/postfixadmin/vacation.conf';
}

# =========== end configuration ===========

if($log_to_file == 1) {
    if (( ! -w $logfile ) && (! -w dirname($logfile))) {
        # Cannot log; no where to write to.
        die("Cannot create logfile : $logfile");
    }
}

my ($from, $to, $cc, $replyto , $subject, $messageid, $lastheader, $smtp_sender, $smtp_recipient, %opts, $test_mode, $logger);

$subject='';
$messageid='unknown';

# Setup a logger...
#
getopts('f:t:', \%opts) or die "Usage: $0 [-t yes] -f sender -- recipient\n\t-t for testing only\n";
$opts{f} and $smtp_sender = $opts{f} or die '-f sender not present on command line';
$test_mode = 0;
$opts{t} and $test_mode = 1;
$smtp_recipient = shift or die 'recipient not given on command line';

my $log_layout = Log::Log4perl::Layout::PatternLayout->new('%d %p> %F:%L %M - %m%n');

if($test_mode == 1) {
    $logger = get_logger();
    # log to stdout
    my $appender = Log::Log4perl::Appender->new('Log::Dispatch::Screen');
    $appender->layout($log_layout);
    $logger->add_appender($appender);
    $logger->debug('Test mode enabled');
} else {
    $logger = get_logger();
    if($log_to_file == 1) {
        # log to file
        my $appender = Log::Log4perl::Appender->new(
            'Log::Dispatch::File',
            filename => $logfile,
            mode => 'append');

        $appender->layout($log_layout);
        $logger->add_appender($appender);
    }

    if($syslog == 1) {
        my $syslog_appender = Log::Log4perl::Appender->new(
            'Log::Dispatch::Syslog',
            facility => 'mail',
        );
        $logger->add_appender($syslog_appender);
    }
}

# change to $DEBUG, $INFO or $ERROR depending on how much logging you want.
$logger->level($ERROR);
if($log_level == 1) {
    $logger->level($INFO);
}
if($log_level == 2) {
    $logger->level($DEBUG);
}

binmode (STDIN,':encoding(UTF-8)');

my $dbh;
if ($db_host) {
    $dbh = DBI->connect("DBI:$db_type:dbname=$db_name;host=$db_host","$db_username", "$db_password", { RaiseError => 1 });
} else {
    $dbh = DBI->connect("DBI:$db_type:dbname=$db_name","$db_username", "$db_password", { RaiseError => 1 });
}

if (!$dbh) {
    $logger->error('Could not connect to database'); # eval { } etc better here?
    exit(0);
}

my $db_true; # MySQL and PgSQL use different values for TRUE, and unicode support...
if ($db_type eq 'mysql') {
    $dbh->do('SET CHARACTER SET utf8;');
    $db_true = '1';
} else { # Pg
    $dbh->do("SET CLIENT_ENCODING TO 'UTF8'");
    $db_true = 'True';
}

# used to detect infinite address lookup loops
my $loopcount=0;

#
# Get interval_time for email user from the vacation table 
#
sub get_interval {
    my ($to) = @_;
    my $query = qq{SELECT interval_time  FROM vacation  WHERE  email=? };
    my $stm = $dbh->prepare($query) or panic_prepare($query);
    $stm->execute($to) or panic_execute($query," 'email='$to'");
    my $rv = $stm->rows;
    if ($rv == 1) {
        my @row = $stm->fetchrow_array;
        my $interval = $row[0] ;
        return $interval ;
    } else {
        return 0 ;
    }
}


sub already_notified {
    my ($to, $from) = @_;
    my $logger = get_logger();
    my $query;

    # delete old notifications
    if ($db_type eq 'Pg') {
        $query = qq{DELETE FROM vacation_notification USING vacation WHERE vacation.email = vacation_notification.on_vacation AND on_vacation = ? AND notified = ? AND notified_at < vacation.activefrom;};
    } else { # mysql
        $query = qq{DELETE vacation_notification.* FROM vacation_notification LEFT JOIN vacation ON vacation.email = vacation_notification.on_vacation WHERE on_vacation = ? AND notified = ? AND notified_at < vacation.activefrom};
    }
    my $stm = $dbh->prepare($query);
    if (!$stm) {
        $logger->error("Could not prepare query (trying to delete old vacation notifications) :'$query' to: $to, from:$from");
        return 1;
    }
    $stm->execute($to,$from);

    $query = qq{INSERT into vacation_notification (on_vacation,notified) values (?,?)};
    $stm = $dbh->prepare($query);
    if (!$stm) {
        $logger->error("Could not prepare query '$query' to: $to, from:$from");
        return 1;
    }
    $stm->{'PrintError'} = 0;
    $stm->{'RaiseError'} = 0;
    if (!$stm->execute($to,$from)) {
        my $e=$dbh->errstr;

# Violation of a primary key constraint may happen here, and that's
# fine. All other error conditions are not fine, however.
        if ($e !~ /(?:_pkey|^Duplicate entry)/) {
            $logger->error("Failed to insert into vacation_notification table (to:$to from:$from error:'$e' query:'$query')");
            # Let's play safe and notify anyway
            return 1;
        }

        $interval = get_interval($to);

        if ($interval) {
            if ($db_type eq 'Pg') {
                $query = qq{SELECT extract( epoch from (NOW()-notified_at))::int FROM vacation_notification WHERE on_vacation=? AND notified=?};
            } else { # mysql
                $query = qq{SELECT NOW()-notified_at FROM vacation_notification WHERE on_vacation=? AND notified=?};
            }
            $stm = $dbh->prepare($query) or panic_prepare($query);
            $stm->execute($to,$from) or panic_execute($query,"on_vacation='$to', notified='$from'");
            my @row = $stm->fetchrow_array;
            my $int = $row[0];
            if ($int > $interval) {
                $logger->info("[Interval elapsed, sending the message]: From: $from To:$to");
                $query = qq{UPDATE vacation_notification SET notified_at=NOW() WHERE on_vacation=? AND notified=?};
                $stm = $dbh->prepare($query);
                if (!$stm) {
                    $logger->error("Could not prepare query '$query' (to: '$to', from: '$from')");
                    return 0;
                }
                if (!$stm->execute($to,$from)) {
                    $e=$dbh->errstr;
                    $logger->error("Error from running query '$query' (to: '$to', from: '$from', error: '$e')");
                }
                return 0;
            } else {
                $logger->debug("Notification interval not elapsed; not sending vacation reply (to: '$to', from: '$from')");
                return 1;
            }
        } else {
            return 1;
        }
    }
    return 0;
}

#
# Check to see if there is a vacation record against a specific email address. 
#
sub check_for_vacation {
    my ($email_to_check) =@_;
    my $query = qq{SELECT email FROM vacation WHERE email=? and active=$db_true and activefrom <= NOW() and activeuntil >= NOW()};
    my $stm = $dbh->prepare($query) or panic_prepare($query);
    $stm->execute($email_to_check) or panic_execute($query,"email='$email_to_check'");
    my $rv = $stm->rows;
    return $rv;
}


# try and determine if email address has vacation turned on; we
# have to do alias searching, and domain aliasing resolution for this.
# If found, return ($num_matches, $real_email);
sub find_real_address {
    my ($email) = @_;
    my $logger = get_logger();
    if (++$loopcount > 20) {
        $logger->error("find_real_address loop! (more than 20 attempts!) currently: $email");
        exit(1);
    }
    my $realemail = '';
    my $rv = check_for_vacation($email);

# Recipient has vacation
    if ($rv == 1) {
        $realemail = $email;
        $logger->debug("Found '$email' has vacation active");
    } else {
        my $vemail = $email;
        $vemail =~ s/\@/#/g;
        $vemail = $vemail . "\@" . $vacation_domain;
        $logger->debug("Looking for alias records that '$email' resolves to with vacation turned on");
        my $query = qq{SELECT goto FROM alias WHERE address=? AND (goto LIKE ? OR goto LIKE ? OR goto LIKE ? OR goto = ?)};
        my $stm = $dbh->prepare($query) or panic_prepare($query);
        $stm->execute($email,"$vemail,%","%,$vemail","%,$vemail,%", "$vemail") or panic_execute($query,"address='$email'");
        $rv = $stm->rows;


# Recipient is an alias, check if mailbox has vacation
        if ($rv == 1) {
            my @row = $stm->fetchrow_array;
            my $alias = $row[0];
            if ($alias =~ /,/) {
                for (split(/\s*,\s*/, lc($alias))) {
                    my $singlealias = $_;
                    $logger->debug("Found alias \'$singlealias\' for email \'$email\'. Looking if vacation is on for alias.");
                    $rv = check_for_vacation($singlealias);
# Alias has vacation
                    if ($rv == 1) {
                        $realemail = $singlealias;
                        last;
                    }
                }
            } else {
                $rv = check_for_vacation($alias);
# Alias has vacation
                if ($rv == 1) {
                    $realemail = $alias;
                }
            }

# We have to look for alias domain (domain1 -> domain2)
        } else {
            my ($user, $domain) = split(/@/, $email);
            $logger->debug("Looking for alias domain for $domain / $email / $user");
            $query = qq{SELECT target_domain FROM alias_domain WHERE alias_domain=?};
            $stm = $dbh->prepare($query) or panic_prepare($query);
            $stm->execute($domain) or panic_execute($query,"alias_domain='$domain'");
            $rv = $stm->rows;

# The domain has a alias domain level alias
            if ($rv == 1) {
                my @row = $stm->fetchrow_array;
                my $alias_domain_dest = $row[0];
                ($rv, $realemail) = find_real_address ("$user\@$alias_domain_dest");

# We still have to look for domain level aliases...
            } else {
                my ($user, $domain) = split(/@/, $email);
                $logger->debug("Looking for domain level aliases for $domain / $email / $user");
                $query = qq{SELECT goto FROM alias WHERE address=?};
                $stm = $dbh->prepare($query) or panic_prepare($query);
                $stm->execute("\@$domain") or panic_execute($query,"address='\@$domain'");
                $rv = $stm->rows;

# The recipient has a domain level alias
                if ($rv == 1) {
                    my @row = $stm->fetchrow_array;
                    my $wildcard_dest = $row[0];
                    my ($wilduser, $wilddomain) = split(/@/, $wildcard_dest);

# Check domain alias
                    if ($wilduser) {
                        ($rv, $realemail) = find_real_address ($wildcard_dest);
                    } else {
                        ($rv, $realemail) = find_real_address ("$user\@$wilddomain");
                    }
                } else {
                    $logger->debug("No domain level alias present for $domain / $email / $user");
                }
            }
        }
    }
    return ($rv, $realemail);
}

# sends the vacation mail to the original sender.
#
sub send_vacation_email {
    my ($email, $orig_from, $orig_to, $orig_messageid, $orig_subject, $test_mode) = @_;
    my $logger = get_logger();
    $logger->debug("Asked to send vacation reply to $email thanks to $orig_messageid");
    my $query = qq{SELECT subject,body FROM vacation WHERE email=?};
    my $stm = $dbh->prepare($query) or panic_prepare($query);
    $stm->execute($email) or panic_execute($query,"email='$email'");
    my $rv = $stm->rows;
    if ($rv == 1) {
        my @row = $stm->fetchrow_array;
        if (already_notified($email, $orig_from) == 1) {
            $logger->debug("Already notified $orig_from, or some error prevented us from doing so");
            return;
        }

        $logger->debug("Will send vacation response for $orig_messageid: FROM: $email (orig_to: $orig_to), TO: $orig_from; VACATION SUBJECT: $row[0] ; VACATION BODY: $row[1]");

        my $subject = $row[0];
        $orig_subject = decode("mime-header", $orig_subject);
        $subject =~ s/\$SUBJECT/$orig_subject/g;
        if ($subject ne $row[0]) {
          $logger->debug("Patched Subject of vacation message to: $subject");
        }

        my $body = $row[1];
        my $from = $email;
        my $to = $orig_from;
        my %smtp_connection;
        %smtp_connection = (
            'smtp' => $smtp_server,
            'port' => $smtp_server_port,
            'auth' => $smtp_auth,
            'authid' => $smtp_authid,
            'authpwd' => $smtp_authpwd,
            'tls_allowed' => $smtp_tls_allowed,
            'smtp_client' => $smtp_client,
            'skip_bad_recipients' => 'true',
            'encoding' => 'Base64',
            'ctype' => 'text/plain; charset=UTF-8',
            'headers' => 'Precedence: junk',
            'headers' => 'X-Loop: Postfix Admin Virtual Vacation',
            'on_errors' => 'die', # raise exception on error
        );
        my %mail;
        %mail = (
            'subject' => encode_mimewords($subject, 'Charset', 'UTF-8'),
            'from' => $from,
            'fake_from' => $friendly_from . " <$from>",
            'to' => $to,
            'msg' => encode_base64(encode("UTF-8", $body))
        );
        if($test_mode == 1) {
            $logger->info("** TEST MODE ** : Vacation response sent to $to from $from subject $subject (not) sent\n");
            $logger->info(%mail);
            return 0;
        }
        eval {
            $Mail::Sender::NO_X_MAILER = 1;
            my $sender = new Mail::Sender({%smtp_connection});
            $sender->Open({%mail});
            $sender->SendLineEnc($body);
            $sender->Close();
            $logger->debug("Vacation response sent to $to, from $from");
        };
        if ($@) {
            $logger->error("Failed to send vacation response: $@ / " . $Mail::Sender::Error);
        }
    }
}

# Convert a (list of) email address(es) from RFC 822 style addressing to
# RFC 821 style addressing. e.g. convert:
#   "John Jones" <JJones@acme.com>, "Jane Doe/Sales/ACME" <JDoe@acme.com>
# to:
#   jjones@acme.com, jdoe@acme.com
sub strip_address {
    my ($arg) = @_;
    if(!$arg) {
        return '';
    }
    my @ok;
    $logger = get_logger();
    my @list;
    @list = $arg =~ m/([\w\.\-\+\'\=_\^\|\$\/\{\}~\?\*\\&\!`\%]+\@[\w\.\-]+\w+)/g;
    foreach(@list) {
        #$logger->debug("Checking: $_");
        my $temp = Email::Valid->address( -address => $_, -mxcheck => 0);
        if($temp) {
            push(@ok, $temp);
        } else {
            $logger->debug("Email not valid : $Email::Valid::Details");
        }
    }
    # remove duplicates
    my %seen = ();
    my @uniq;
    foreach my $item (@ok) {
        push(@uniq, $item) unless $seen{$item}++
    }

    my $result = lc(join(', ', @uniq));
    #$logger->debug("Result: $result");
    return $result;
}

sub panic_prepare {
    my ($arg) = @_;
    my $logger = get_logger();
    $logger->error("Could not prepare sql statement: '$arg'");
    exit(0);
}

sub panic_execute {
    my ($arg,$param) = @_;
    my $logger = get_logger();
    $logger->error("Could not execute sql statement - '$arg' with parameters '$param'");
    exit(0);
}

# Make sure the email wasn't sent by someone who could be a mailing list etc; if it was,
# then we abort after appropriate logging.
sub check_and_clean_from_address {
    my ($address) = @_;
    my $logger = get_logger();

    if($address =~ /^(noreply|postmaster|mailer\-daemon|listserv|majordomo|owner\-|request\-|bounces\-)/i ||
        $address =~ /\-(owner|request|bounces)\@/i ||
        ($custom_noreply_pattern == 1 && $address =~ /^.*($noreply_pattern).*/i) ) {
            $logger->debug("sender $address contains $1 - will not send vacation message");
            exit(0);
        }
    $address = strip_address($address);
    if($address eq '') {
        $logger->error("Address $address is not valid; exiting");
        exit(0);
    }
    #$logger->debug("Address cleaned up to $address");
    return $address;
}
########################### main #################################

# Take headers apart
$cc = '';
$replyto = '';

$logger->debug("Script argument SMTP recipient is : '$smtp_recipient' and smtp_sender : '$smtp_sender'");
while (<STDIN>) {
    last if (/^$/);
    if (/^\s+(.*)/ and $lastheader) { $$lastheader .= " $1"; next; }
    elsif (/^from:\s*(.*)\n$/i) { $from = $1; $lastheader = \$from; }
    elsif (/^to:\s*(.*)\n$/i) { $to = $1; $lastheader = \$to; }
    elsif (/^cc:\s*(.*)\n$/i) { $cc = $1; $lastheader = \$cc; }
    elsif (/^Reply\-to:\s*(.*)\s*\n$/i) { $replyto = $1; $lastheader = \$replyto; }
    elsif (/^subject:\s*(.*)\n$/i) { $subject = $1; $lastheader = \$subject; }
    elsif (/^message\-id:\s*(.*)\s*\n$/i) { $messageid = $1; $lastheader = \$messageid; }
    elsif (/^x\-spam\-(flag|status):\s+yes/i) { $logger->debug("x-spam-$1: yes found; exiting"); exit (0); }
    elsif (/^x\-facebook\-notify:/i) { $logger->debug('Mail from facebook, ignoring'); exit(0); }
    elsif (/^precedence:\s+(bulk|list|junk)/i) { $logger->debug("precedence: $1 found; exiting"); exit (0); }
    elsif (/^x\-loop:\s+postfix\ admin\ virtual\ vacation/i) { $logger->debug('x-loop: postfix admin virtual vacation found; exiting'); exit (0); }
    elsif (/^Auto\-Submitted:\s*no/i) { next; }
    elsif (/^Auto\-Submitted:/i) { $logger->debug('Auto-Submitted: something found; exiting'); exit (0); }
    elsif (/^List\-(Id|Post|Unsubscribe):/i) { $logger->debug("List-$1: found; exiting"); exit (0); }
    elsif (/^(x\-(barracuda\-)?spam\-status):\s+(yes)/i) { $logger->debug("$1: $3 found; exiting"); exit (0); }
    elsif (/^(x\-dspam\-result):\s+(spam|bl[ao]cklisted)/i) { $logger->debug("$1: $2 found; exiting"); exit (0); }
    elsif (/^(x\-(anti|avas\-)?virus\-status):\s+(infected)/i) { $logger->debug("$1: $3 found; exiting"); exit (0); }
    elsif (/^(x\-(avas\-spam|spamtest|crm114|razor|pyzor)\-status):\s+(spam)/i) { $logger->debug("$1: $3 found; exiting"); exit (0); }
    elsif (/^(x\-osbf\-lua\-score):\s+[0-9\/\.\-\+]+\s+\[([-S])\]/i) { $logger->debug("$1: $2 found; exiting"); exit (0); }
    else {$lastheader = '' ; }
}

if($smtp_recipient =~ /\@$vacation_domain/) {
    # the regexp used here could probably be improved somewhat, for now hope that people won't use # as a valid mailbox character.
    my $tmp = $smtp_recipient;
    $tmp =~ s/\@$vacation_domain//;
    $tmp =~ s/#/\@/;
    $logger->debug("Converted autoreply mailbox back to normal style - from $smtp_recipient to $tmp");
    $smtp_recipient = $tmp;
    undef $tmp;
}

# If either From: or To: are not set, exit
if(!$from || !$to || !$messageid || !$smtp_sender || !$smtp_recipient) {
    $logger->info("One of from=$from, to=$to, messageid=$messageid, smtp sender=$smtp_sender, smtp recipient=$smtp_recipient empty");
    exit(0);
}
$logger->debug("Email headers have to: '$to' and From: '$from'");
$to = strip_address($to);
$cc = strip_address($cc);
$from = check_and_clean_from_address($from);
if($replyto ne '') {
    # if reply-to is invalid, or looks like a mailing list, then we probably don't want to send a reply.
    $replyto = check_and_clean_from_address($replyto);
}
$smtp_sender = check_and_clean_from_address($smtp_sender);
$smtp_recipient = check_and_clean_from_address($smtp_recipient);

if ($smtp_sender eq $smtp_recipient) {
    $logger->debug("smtp sender $smtp_sender and recipient $smtp_recipient are the same; aborting");
    exit(0);
}

for (split(/,\s*/, lc($to)), split(/,\s*/, lc($cc))) {
    my $header_recipient = strip_address($_);
    if ($smtp_sender eq $header_recipient) {
        $logger->debug("sender header $smtp_sender contains recipient $header_recipient (mailing myself?)");
        exit(0);
    }
}

my ($rv, $email) = find_real_address($smtp_recipient);
if ($rv == 1) {
    $logger->debug("Attempting to send vacation response for: $messageid to: $smtp_sender, $smtp_recipient, $email (test_mode = $test_mode)");
    send_vacation_email($email, $smtp_sender, $smtp_recipient, $messageid, $subject, $test_mode);
} else {
    $logger->debug("SMTP recipient $smtp_recipient which resolves to $email does not have an active vacation (rv: $rv, email: $email)");
}

0;

#/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
