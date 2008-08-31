#!/usr/bin/perl -w
#
# Virtual Vacation 3.1
# by Mischa Peters <mischa at high5 dot net>
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
# 2005-01-19  Troels Arvin <troels@arvin.dk>
#             PostgreSQL-version.
#             Normalized DB schema from one vacation table ("vacation")
#             to two ("vacation", "vacation_notification"). Uses
#             referential integrity CASCADE action to simplify cleanup
#             when a user is no longer on vacation.
#             Inserting variables into queries stricly by prepare()
#             to try to avoid SQL injection.
#             International characters are now handled well.
#
# 2005-01-21  Troels Arvin <troels@arvin.dk>
#             Uses the Email::Valid package to avoid sending notices
#             to obviously invalid addresses.
#
# 2007-08-15  David Goodwin <david@palepurple.co.uk>
#             Use the Perl Mail::Sendmail module for sending mail
#             Check for headers that start with blank lines (patch from forum)
#
# 2007-08-20  Martin Ambroz <amsys@trustica.cz>
#             Added initial Unicode support
#
# 2008-05-09  Fabio Bonelli <fabiobonelli@libero.it>
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
# Requirements - the following perl modules are required:
# DBD::Pg or DBD::mysql 
# Mail::Sendmail, Email::Valid MIME::Charset, Log::Log4perl, Log::Dispatch, MIME::EncWords and GetOpt::Std 
#
# You may install these via CPAN, or through your package tool.
# CPAN: 'perl -MCPAN -e shell', then 'install Module::Whatever'
#
# On Debian based systems : 
#   libmail-sendmail-perl
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

# ========== begin configuration ==========

# IMPORTANT: If you put passwords into this script, then remember
# to restrict access to the script, so that only the vacation user
# can read it.

# db_type - uncomment one of these
my $db_type = 'Pg';
#my $db_type = 'mysql';

# leave empty for connection via UNIX socket
my $db_host = '';

# connection details
my $db_username = 'dg';
my $db_password = 'gingerdog';
my $db_name     = 'postfix';

# smtp server used to send vacation e-mails
my $smtp_server = 'localhost';

# Set to 1 to enable logging to syslog.
my $syslog = 0;

# path to logfile, when empty logging is supressed
# change to e.g. /dev/null if you want nothing logged.
# if we can't write to this, we try /tmp/vacation.log instead
my $logfile='/var/spool/vacation/vacation.log';
# 2 = debug + info, 1 = info only, 0 = error only
my $log_level = 2;


# notification interval, in seconds
# set to 0 to notify only once
# e.g. 1 day ...
#my $interval = 60*60*24;
# disabled by default
my $interval = 0;

# =========== end configuration ===========

if ( ! -w $logfile ) {
    $logfile = "/tmp/vacation.log";
}

use DBI;
use MIME::Base64;
use MIME::EncWords qw(:all);
use Email::Valid;
use strict;
use Mail::Sendmail;
use Getopt::Std;
use Log::Log4perl qw(get_logger :levels);

my ($from, $to, $cc, $replyto , $subject, $messageid, $lastheader, $smtp_sender, $smtp_recipient, %opts, $spam, $test_mode, $logger);

$subject='';

# Setup a logger...
#
getopts('f:t:', \%opts) or die "Usage: $0 [-t yes] -f sender -- recipient\n    -t for testing only\n";
$opts{f} and $smtp_sender = $opts{f};
$test_mode = 0;
$opts{t} and $test_mode = 1;
$smtp_recipient = shift || $smtp_recipient || $ENV{"USER"} || "";


my $log_layout = Log::Log4perl::Layout::PatternLayout->new("%d %p> %F:%L %M - %m%n");

if($test_mode == 1) {
    $logger = get_logger();
    # log to stdout
    my $appender = Log::Log4perl::Appender->new('Log::Dispatch::Screen');
    $appender->layout($log_layout);
    $logger->add_appender($appender);
    $logger->debug("Test mode enabled");
}
else {
    # log to file.
    my $appender = Log::Log4perl::Appender->new(
        'Log::Dispatch::File', 
        filename => $logfile,
        mode => 'append');

    $logger = get_logger();
    $appender->layout($log_layout);
    $logger->add_appender($appender);

    if($syslog == 1) {
        my $syslog_appender = Log::Log4perl::Appender->new(
            'Log::Dispatch::Syslog',
            Facility => 'user',
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


binmode (STDIN,':utf8');

my $dbh;
if ($db_host) {
    $dbh = DBI->connect("DBI:$db_type:dbname=$db_name;host=$db_host","$db_username", "$db_password", { RaiseError => 1 });
} else {
    $dbh = DBI->connect("DBI:$db_type:dbname=$db_name","$db_username", "$db_password", { RaiseError => 1 });
}

if (!$dbh) {
    $logger->error("Could not connect to database"); # eval { } etc better here?
    exit(0);
}

my $db_true; # MySQL and PgSQL use different values for TRUE, and unicode support...
if ($db_type eq "mysql") {
    $dbh->do("SET CHARACTER SET utf8;");
    $db_true = '1';
} else { # Pg
    $dbh->do("SET CLIENT_ENCODING TO 'UTF8'");
    $db_true = 'True';
}

# used to detect infinite address lookup loops
my $loopcount=0;

sub already_notified {
    my ($to, $from) = @_;
    my $logger = get_logger();
    my $query = qq{INSERT into vacation_notification (on_vacation,notified) values (?,?)};
    my $stm = $dbh->prepare($query);
    if (!$stm) {
        $logger->error("Could not prepare query '$query' to: $to, from:$from");
        return 1;
    }
    $stm->{'PrintError'} = 0;
    $stm->{'RaiseError'} = 0;
    if (!$stm->execute($to,$from)) {
        my $e=$dbh->errstr;

# Violation of a primay key constraint may happen here, and that's
# fine. All other error conditions are not fine, however.
        if ($e !~ /(?:_pkey|^Duplicate entry)/) {
            $logger->error("Failed to insert into vacation_notification table (to:$to from:$from error:'$e' query:'$query')");
            # Let's play safe and notify anyway
            return 0;
        }
        if ($interval) {
            $query = qq{SELECT NOW()-notified_at FROM vacation_notification WHERE on_vacation=? AND notified=?};
            $stm = $dbh->prepare($query) or panic_prepare($query);
            $stm->execute($to,$from) or panic_execute($query,"on_vacation='$to', notified='$from'");
            my @row = $stm->fetchrow_array;
            my $int = $row[0];
            if ($int > $interval) {
                $logger->debug("[Interval elapsed, sending the message]: From: $from To:$to");
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
    my $query = qq{SELECT email FROM vacation WHERE email=? and active=$db_true};
    my $stm = $dbh->prepare($query) or panic_prepare($query);
    $stm->execute($email) or panic_execute($query,"email='$email'");
    my $rv = $stm->rows;

# Recipient has vacation
    if ($rv == 1) {
        $realemail = $email;
        $logger->debug("Found $email has vacation active");
    } else {
        # XXX why aren't we doing a join here?
        $logger->debug("Looking for alias records that $email resolves to with vacation turned on");
        $query = qq{SELECT goto FROM alias WHERE address=?};
        $stm = $dbh->prepare($query) or panic_prepare($query);
        $stm->execute($email) or panic_execute($query,"address='$email'");
        $rv = $stm->rows;

# Recipient is an alias, check if mailbox has vacation
        if ($rv == 1) { 
            my @row = $stm->fetchrow_array;
            my $alias = $row[0];
            $query = qq{SELECT email FROM vacation WHERE email=? and active=$db_true};
            $stm = $dbh->prepare($query) or panic_prepare($query);
            $stm->execute($alias) or panic_prepare($query,"email='$alias'");
            $rv = $stm->rows;

# Alias has vacation
            if ($rv == 1) {
                $realemail = $alias;
            }

# We still have to look for domain level aliases...
        } else { 
            my ($user, $domain) = split(/@/, $email);
            $query = qq{SELECT goto FROM alias WHERE address=?};
            $stm = $dbh->prepare($query) or panic_prepare($query);
            $stm->execute("\@$domain") or panic_execute($query,"address='\@$domain'");
            $rv = $stm->rows;
            $logger->debug("Looking for domain level aliases for $domain / $email / $user");
# The receipient has a domain level alias
            if ($rv == 1) { 
                my @row = $stm->fetchrow_array;
                my $wildcard_dest = $row[0];
                my ($wilduser, $wilddomain) = split(/@/, $wildcard_dest);

# Check domain alias
                if ($wilduser) { 
                    ($rv, $realemail) = find_real_address ($wildcard_dest);	
                } else {
                    my $new_email = $user . '@' . $wilddomain;
                    ($rv, $realemail) = find_real_address ($new_email);	
                }
            }
            else {
                $logger->debug("No domain level alias present for $domain / $email / $user");
            }
        }
    }
    return ($rv, $realemail);
}

# sends the vacation mail to the original sender.
#
sub send_vacation_email {
    my ($email, $orig_from, $orig_to, $orig_messageid, $test_mode) = @_;
    my $logger = get_logger();
    $logger->debug("Asked to send vacation reply to $email thanks to $orig_messageid");
    my $query = qq{SELECT subject,body FROM vacation WHERE email=?};
    my $stm = $dbh->prepare($query) or panic_prepare($query);
    $stm->execute($email) or panic_execute($query,"email='$email'");
    my $rv = $stm->rows;
    if ($rv == 1) {
        my @row = $stm->fetchrow_array;
        if (already_notified($email, $orig_from) == 1) { 
            $logger->debug("Already notified $email, or some error prevented us from doing so");
            return; 
        }

        $logger->debug("Will send vacation response for $orig_messageid: FROM: $email (orig_to: $orig_to), TO: $orig_from; VACATION SUBJECT: $row[0] ; VACATION BODY: $row[1]");
        my $subject = $row[0];
        my $body = $row[1];
        my $from = $email;
        my $to = $orig_from;
        my $vacation_subject = encode_mimewords($subject, 'Encoding'=> 'q', 'Charset'=>'utf-8', 'Field'=>'Subject');
        my %mail;
        %mail = (
            'smtp' => $smtp_server,
            'Subject' => $vacation_subject,
            'From' => $from,
            'To' => $to,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
            'Precedence' => 'junk',
            'X-Loop' => 'Postfix Admin Virtual Vacation',
            'Message' => encode_base64($body)
        );
        if($test_mode == 1) {
            $logger->info("** TEST MODE ** : Vacation response sent to $to from $from subject $subject - NOT sent\n");
            $logger->info(%mail);
            return 0;
        }
        sendmail(%mail) or $logger->error("Failed to send vacation response: " . $Mail::Sendmail::error);
        $logger->debug("Vacation response sent, Mail::Sendmail said : " . $Mail::Sendmail::log);
    }
}

# Remove textual stuff from a (list of) email address(es)
# e.g. convert: "aardvark" <a@b.com>, "Danger Mouse" <c@d.com>, e@f.com to 
#               a@b.com, c@d.com, e@f.com
sub strip_address {
    my ($arg) = @_;
    if(!$arg) {
        return '';
    }
    my @ok;
    $logger = get_logger();
    for (split(/,\s*/, lc($arg))) {
        my $temp = Email::Valid->address($_);
        if($temp) {
            push(@ok, $temp);
        }
    }
    my $result = join(", ", @ok);
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

    if($address =~ /^(noreply|postmaster|mailer-daemon|listserv|majordomo|owner-|request-|bounces-)/i || 
        $address =~ /-(owner|request|bounces)\@/i ) { 
        $logger->debug("sender $address contains $1 - will not send vacation message"); 
        exit(0); 
    }
    $address = strip_address($address);
    if($address eq "") {
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

while (<STDIN>) {
    last if (/^$/);
    if (/^\s+(.*)/ and $lastheader) { $$lastheader .= " $1"; next; }  
    elsif (/^from:\s*(.*)\s*\n$/i) { $from = $1; $lastheader = \$from; }  
    elsif (/^to:\s*(.*)\s*\n$/i) { $to = $1; $lastheader = \$to; }  
    elsif (/^cc:\s*(.*)\s*\n$/i) { $cc = $1; $lastheader = \$cc; }  
    elsif (/^Reply-to:\s*(.*)\s*\n$/i) { $replyto = $1; $lastheader = \$replyto; }  
    elsif (/^subject:\s*(.*)\s*\n$/i) { $subject = $1; $lastheader = \$subject; }  
    elsif (/^message-id:\s*(.*)\s*\n$/i) { $messageid = $1; $lastheader = \$messageid; }  
    elsif (/^x-spam-(flag|status):\s+yes/i) { $logger->debug("x-spam-$1: yes found; exiting"); exit (0); }  
    elsif (/^x-facebook-notify:/i) { $logger->debug('Mail from facebook, ignoring'); exit(0); }
    elsif (/^precedence:\s+(bulk|list|junk)/i) { $logger->debug("precedence: $1 found; exiting"); exit (0); }  
    elsif (/^x-loop:\s+postfix\ admin\ virtual\ vacation/i) { $logger->debug("x-loop: postfix admin virtual vacation found; exiting"); exit (0); }  
    elsif (/^Auto-Submitted:\s*no\s*/i) { next; }  
    elsif (/^Auto-Submitted:/i) { $logger->debug("Auto-Submitted: something found; exiting"); exit (0); }
    elsif (/^List-(Id|Post):/i) { $logger->debug("List-$1: found; exiting"); exit (0); }
    else {$lastheader = "" ; }
}



# If either From: or To: are not set, exit
if(!$from || !$to || !$messageid || !$smtp_sender || !$smtp_recipient) { 
    $logger->info("One of from=$from, to=$to, messageid=$messageid, smtp sender=$smtp_sender, smtp recipient=$smtp_recipient empty"); 
    exit(0); 
}

$to = strip_address($to);
$from = lc ($from);
$from = check_and_clean_from_address($from);
if($replyto ne "") {
    # if reply-to is invalid, or looks like a mailing list, then we probably don't want to send a reply.
    $replyto = check_and_clean_from_address($replyto);
}
$smtp_sender = check_and_clean_from_address($smtp_sender);
$smtp_recipient = check_and_clean_from_address($smtp_recipient);


if ($smtp_sender eq $smtp_recipient) { 
    $logger->debug("smtp sender $smtp_sender and recipient $smtp_recipient are the same; aborting"); 
    exit(0); 
}

my $recipfound = 0;
for (split(/,\s*/, lc($to)), split(/,\s*/, lc($cc))) {
    my $destinatario = strip_address($_);
    if ($smtp_sender eq $destinatario) { 
        $logger->debug("sender header $smtp_sender contains recipient $destinatario (mailing myself?)"); 
        exit(0); 
    }
    if ($smtp_recipient eq $destinatario) { $recipfound++; }
}
if (!$recipfound) { 
    $logger->debug("smtp envelope recipient $smtp_recipient not found in the header recipients ($to & $cc) (therefore they were bcc'ed, so won't send vacation message)"); 
    exit (0); 
}



my ($rv, $email) = find_real_address($smtp_recipient);
if ($rv == 1) {
    $logger->debug("Attempting to send vacation response for: $messageid to: $smtp_sender, $smtp_recipient, $email (test_mode = $test_mode)");
    send_vacation_email($email, $smtp_sender, $smtp_recipient, $messageid, $test_mode);
}
else {
    $logger->debug("SMTP recipient $smtp_recipient which resolves to $email does not have an active vacation (rv: $rv, email: $email)");
}

0;

#/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
