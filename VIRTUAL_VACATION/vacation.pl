#!/usr/bin/perl
#
# Virtual Vacation 4.2
#
# See Contributions.txt for a list of contributions.
# https://github.com/postfixadmin/postfixadmin/blob/master/VIRTUAL_VACATION/Contributions.txt

# See INSTALL.txt for help installing (and lists of dependent packages etc)
# https://github.com/postfixadmin/postfixadmin/blob/master/VIRTUAL_VACATION/INSTALL.md

#
# Note: When you use this module, you may start seeing error messages
# like "Cannot insert a duplicate key into unique index
# vacation_notification_pkey" in your system logs. This is expected
# behavior, and not an indication of trouble (see the "already_notified"
# subroutine for an explanation).
#

use utf8;
use DBI;
use Encode qw(decode);
use MIME::EncWords qw(:all);
use Email::Valid;
use strict;
use Getopt::Std;
use Email::Sender::Simple qw(sendmail);
use Email::Sender::Transport::SMTP;
use Email::Simple;
use Email::Simple::Creator;
use Try::Tiny;
use Log::Log4perl qw(get_logger :levels);
use File::Basename;
use Net::DNS;
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

our $recipient_delimiter = '+';

# port to connect to; defaults to 25 for non-SSL, 465 for 'ssl', 587 for 'starttls'
our $smtp_server_port = 25;

# this is the local address from which to connect
our $smtp_client = 'localhost';

# this is the helo we [the vacation script] use on connection; you may need to change this to your hostname or something,
# depending upon what smtp helo restrictions you have in place within Postfix.
our $smtp_helo = 'localhost.localdomain';

# send mail encrypted or plaintext
# if 'starttls', use STARTTLS; if 'ssl' (or 1), connect securely; otherwise, no security
our $smtp_ssl = 'starttls';

# maximum time in secs to wait for server; default is 120
our $smtp_timeout = '120';

# sasl_username: the username to use for auth; optional
our $smtp_authid = '';
# sasl_password: the password to use for auth; required if username is provided
our $smtp_authpwd = '';

# This specifies the mail 'from' name which is shown to recipients of vacation replies.
# If you leave it empty, the vacation mail will contain: 
# From: <original@recipient.domain>
# If you specify something here you'd instead see something like :
# From: Some Friendly Name <original@recipient.domain>
our $friendly_from = '';

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

# Never send vacation mails for the following recipient email addresses.
# Useful for e.g. aliases pointing to multiple recipients which have vacation active
# hence an email to the alias should not trigger vacation messages.
# By default vacation email addresses will be sent for all recipients.
# default = ''
# preventing vacation notifications for recipient info@example.org would look like this:
# our $no_vacation_pattern = 'info\@example\.org';
our $no_vacation_pattern = 'info\@example\.org'; 


# instead of changing this script, you can put your settings to /etc/mail/postfixadmin/vacation.conf
# or /etc/postfixadmin/vacation.conf just use Perl syntax there to fill the variables listed above
# (without the "our" keyword). Example:
# $db_username = 'mail';
if (-f '/etc/mail/postfixadmin/vacation.conf') {
    require '/etc/mail/postfixadmin/vacation.conf';
} elsif (-f '/etc/postfixadmin/vacation.conf') {
    require '/etc/postfixadmin/vacation.conf';
} elsif (-f './vacation.conf') {
    require './vacation.conf';
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
            ident => 'vacation',
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
    $dbh->{pg_enable_utf8} = 1;
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
                $query = qq{SELECT UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(notified_at) FROM vacation_notification WHERE on_vacation=? AND notified=?};
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
        $subject = Encode::decode_utf8( $subject ) if( !Encode::is_utf8( $subject ) );
        $orig_subject = decode("mime-header", $orig_subject);
        $subject =~ s/\$SUBJECT/$orig_subject/g;
        if ($subject ne $row[0]) {
          $logger->debug("Patched Subject of vacation message to: $subject");
        }

        my $body = $row[1];
        $body = Encode::decode_utf8( $body ) if( !Encode::is_utf8( $body ) );

        my $from = $email;
        my $to = $orig_from;

        # part of the username in the email && part of the domain in the email
        my ($email_username_part, $email_domain_part) = split(/@/, $email);

        my $resolver  = Net::DNS::Resolver->new;
        my @mx   = mx($resolver, $email_domain_part);
        my $smtp_server; 
        if (@mx) {
            $smtp_server = @mx[0]->exchange;
            $logger->debug("Found MX record <$smtp_server> for user <$email>!");
        } else {
            $logger->error("Unable to find MX record for user <$email>, error message: ".$resolver->errorstring);
            exit(0); 
        }

        my $smtp_params = {
            host => $smtp_server,
            port => $smtp_server_port,
            ssl_options => {
                SSL_verifycn_name => $smtp_server
            },
            ssl  => $smtp_ssl,
            timeout => $smtp_timeout,
            localaddr => $smtp_client,
            helo => $smtp_helo,
            debug => 0,
        };

        if($smtp_authid ne ''){
            $smtp_params->{sasl_username}=$smtp_authid;
            $smtp_params->{sasl_password}=$smtp_authpwd;
            $logger->info("Doing SASL Authentication with user $smtp_params->{sasl_username}\n");
        };

        my $transport = Email::Sender::Transport::SMTP->new($smtp_params);

	$subject = Encode::encode_utf8( $subject ) if( Encode::is_utf8( $subject ) );
	$body = Encode::encode_utf8( $body ) if( Encode::is_utf8( $body ) );
        $email = Email::Simple->create(
            header => [
                To      => $to,
                From    => $from,
                Subject => encode_mimewords($subject, 'Charset', 'UTF-8'),
                Precedence => 'junk',
                'Content-Type' => "text/plain; charset=utf-8",
                'X-Loop' => 'Postfix Admin Virtual Vacation',
            ],
            body => $body,
        );

        if($test_mode == 1) {
            $logger->info("** TEST MODE ** : Vacation response sent to $to from $from subject $subject (not) sent\n");
            $logger->info($email);
            return 0;
        }

        try {
            sendmail($email, { transport => $transport });
        } finally {
            if (@_) {
                $logger->error("Failed to send vacation response to $to from $from subject $subject: @_");
            } else {
             $logger->debug("Vacation response sent to $to from $from subject $subject sent\n");
            }
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
    elsif (/^x\-autogenerated:\s*reply/i) { $logger->debug('x-autogenerated found; exiting'); exit (0); }
    elsif (/^x\-auto\-response\-suppress:\s*oof/i) { $logger->debug('x-auto-response-suppress: oof found; exiting'); exit (0); }
    else {$lastheader = '' ; }
}

if($smtp_recipient =~ /\@$vacation_domain/) {
    # the regexp used here could probably be improved somewhat, for now hope that people won't use # as a valid mailbox character.
    my $tmp = $smtp_recipient;
    $tmp =~ s/\@$vacation_domain//;
    $tmp =~ s/#/\@/;
    if ($recipient_delimiter) {
        $tmp =~ s/[\Q$recipient_delimiter\E].+$//;
    }
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

if ($to =~ /^.*($no_vacation_pattern).*/i) { 
   $logger->debug("Will not send vacation reply for messages to $to");
   exit(0); 
}

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
