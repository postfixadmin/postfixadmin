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
#
# Requirements:
# You need to have the DBD::Pg or DBD::mysql perl-module installed.
# You need to have the Mail::Sendmail module installed. 
# You need to have the Email::Valid module installed.
# You need to have the MIME::Charset module installed.
# You need to have the MIME::EncWords module installed.
#
# On Debian based systems : 
#   libmail-sendmail-perl
#   libdbd-pg-perl
#   libemail-valid-perl
#   libmime-perl
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
my $db_username = 'vacation';
my $db_password = '';
my $db_name     = 'postfix';

my $syslog = 1;

# path to logfile, when empty logging is supressed
my $logfile='';
#my $logfile = "/var/log/vacation/vacation.log";
# path to file for debugging, debug supressed when empty
my $debugfile='';
#my $debugfile = "/var/log/vacation/vacation.debug";

# =========== end configuration ===========

use DBI;
use MIME::Base64;
use MIME::EncWords qw(:all);
use Email::Valid;
use strict;
use Mail::Sendmail;

binmode (STDIN,':utf8');

my $dbh;
if ($db_host) {
   $dbh = DBI->connect("DBI:$db_type:dbname=$db_name;host=$db_host","$db_username", "$db_password", { RaiseError => 1 });
} else {
   $dbh = DBI->connect("DBI:$db_type:dbname=$db_name","$db_username", "$db_password", { RaiseError => 1 });
}

if (!$dbh) {
   panic("Could not connect to database");
   exit(0);
}

my $db_true; # MySQL and PgSQL use different values for TRUE
if ($db_type eq "mysql") {
   $dbh->do("SET CHARACTER SET utf8;");
   $db_true = '1';
} else { # Pg
   # TODO: SET CHARACTER SET is mysql only, needs FIX for ALL databases
   $db_true = 'True';
}

# used to detect infinite address lookup loops
my $loopcount=0;

sub do_debug {
   if ( $debugfile ) {
      my $date;
      open (DEBUG, ">> $debugfile") or die ("Unable to open debug file");
      binmode (DEBUG, ':utf8');
      chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
      print DEBUG "====== $date ======\n";
      my $i;
      for ($i=0;$i<$#_;$i++) {
         print DEBUG $_[$i], ' | ';
      }
      print DEBUG $_[($#_)], "\n";
      close (DEBUG);
   }
}

sub already_notified {
   my ($to, $from) = @_;
   my $query = qq{INSERT into vacation_notification (on_vacation,notified) values (?,?)};
   my $stm = $dbh->prepare($query);
   if (!$stm) {
      do_log('',$to,$from,'','',"Could not prepare query $query");
      return 1;
   }
   $stm->{'PrintError'} = 0;
   $stm->{'RaiseError'} = 0;
   if (!$stm->execute($to,$from)) {
      my $e=$dbh->errstr;

# Violation of a primay key constraint may happen here, and that's
# fine. All other error conditions are not fine, however.
      if (!$e =~ /_pkey/) {
         do_log('',$to,$from,'','',"Unexpected error: '$e' from query '$query'");
      }
      return 1;
   }
   return 0;
}

sub do_log {
   my ($messageid, $to, $from, $subject, $logmessage) = @_;
   my $date;
   if ( $syslog ) {
      open (SYSLOG, "|/usr/bin/logger -p mail.info -t Vacation") or die ("Unable to open logger"); 
      binmode(SYSLOG, ':utf8');
      if ($logmessage) {
         printf SYSLOG "Orig-To: %s From: %s MessageID: %s Subject: %s. Log message: $%s", $to, $from, $messageid, $subject, $logmessage;
      } else {
         printf SYSLOG "Orig-To: %s From: %s MessageID: %s Subject: %s", $to, $from, $messageid, $subject;
      }
      close (SYSLOG); 
   }
   if ( $logfile ) {
      open (LOG, ">> $logfile") or die ("Unable to open log file");
      binmode (LOG, ':utf8');
      chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
      if ($logmessage) {
         print LOG "$date: To: $to From: $from Subject: $subject MessageID: $messageid. Log message: $logmessage\n";
      } else {
         print LOG "$date: To: $to From: $from Subject: $subject MessageID: $messageid\n";
      }
      close (LOG);
   }
}

sub do_mail {
   # from, to, subject, body
   my ($from, $to, $subject, $body) = @_;
   my $vacation_subject = encode_mimewords($subject, 'Encoding'=> 'q', 'Charset'=>'utf-8', 'Field'=>'Subject');
   my %mail;
   %mail = (
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
   sendmail(%mail) or do_log($Mail::Sendmail::error);
   do_debug('Mail::Sendmail said :' . $Mail::Sendmail::log);
}

sub panic {
   my ($arg) = @_;
   do_log('','','','','',"$arg");
   exit(0);
}

sub panic_prepare {
   my ($arg) = @_;
   do_log('','','','','',"Could not prepare '$arg'");
   exit(0);
}

sub panic_execute {
   my ($arg,$param) = @_;
   do_log('','','','','',"Could not execute '$arg' with parameters $param");
   exit(0);
}

sub find_real_address {
   my ($email) = @_;
   if (++$loopcount > 20) {
      do_log ("find_real_address loop!", "currently: $email", "ERROR", "ERROR"); 
      panic("possible infinite loop in find_real_address for <$email>. Check for alias loop\n");
   }
   my $realemail;
   my $query = qq{SELECT email FROM vacation WHERE email=? and active=$db_true};
   my $stm = $dbh->prepare($query) or panic_prepare($query);
   $stm->execute($email) or panic_execute($query,"email='$email'");
   my $rv = $stm->rows;

# Recipient has vacation
   if ($rv == 1) {
      $realemail = $email;
   } else {
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
      }
   }
   return ($rv, $realemail);
}

sub send_vacation_email {
   my ($email, $orig_from, $orig_to, $orig_messageid) = @_;
   my $query = qq{SELECT subject,body FROM vacation WHERE email=?};
   my $stm = $dbh->prepare($query) or panic_prepare($query);
   $stm->execute($email) or panic_execute($query,"email='$email'");
   my $rv = $stm->rows;
   if ($rv == 1) {
      my @row = $stm->fetchrow_array;
      if (already_notified($email, $orig_from)) { return; }
      do_debug ("[SEND RESPONSE] for $orig_messageid:\n", "FROM: $email (orig_to: $orig_to)\n", "TO: $orig_from\n", "VACATION SUBJECT: $row[0]\n", "VACATION BODY: $row[1]\n");

      # do_mail(from, to, subject, body);
      do_mail ($email, $orig_from, $row[0], $row[1]);
      do_log ($orig_messageid, $orig_to, $orig_from, ''); 
   }

}

########################### main #################################

my ($from, $to, $cc, $subject, $messageid, $lastheader);

$subject='';

# Take headers apart
while (<STDIN>) {
   last if (/^$/);
   if (/^\s+(.*)/ and $lastheader) { $$lastheader .= " $1"; }  
   elsif (/^from:\s+(.*)\n$/i) { $from = $1; $lastheader = \$from; }  
   elsif (/^to:\s+(.*)\n$/i) { $to = $1; $lastheader = \$to; }  
   elsif (/^cc:\s+(.*)\n$/i) { $cc = $1; $lastheader = \$cc; }  
   elsif (/^subject:\s+(.*)\n$/i) { $subject = $1; $lastheader = \$subject; }  
   elsif (/^message-id:\s+(.*)\n$/i) { $messageid = $1; $lastheader = \$messageid; }  
   elsif (/^x-spam-(flag|status):\s+yes/i) { exit (0); }  
   elsif (/^precedence:\s+(bulk|list|junk)/i) { exit (0); }  
   elsif (/^x-loop:\s+postfix\ admin\ virtual\ vacation/i) { exit (0); }  
   else {$lastheader = "" ; }
}

# If either From: or To: are not set, exit
if (!$from || !$to || !$messageid) { exit (0); }

$from = lc ($from);

if (!Email::Valid->address($from,-mxcheck => 1)) { do_debug("Invalid from email address: $from; exiting."); exit(0); }

# Check if it's an obvious sender, exit
if ($from =~ /([\w\-.%]+\@[\w.-]+)/) { $from = $1; }
if ($from eq "" || $from =~ /^owner-|-(request|owner)\@|^(mailer-daemon|postmaster)\@/i) { exit (0); }

# Strip To: and Cc: and push them in array
my @strip_cc_array; 
my @strip_to_array = split(/, */, lc ($to) );
if (defined $cc) { @strip_cc_array = split(/, */, lc ($cc) ); }
push (@strip_to_array, @strip_cc_array);

my @search_array;

# Strip email address from headers
for (@strip_to_array) {
   if ($_ =~ /([\w\-.%]+\@[\w.-]+)/) { 
      push (@search_array, $1); 
      do_debug ("[STRIP RECIPIENTS]: ", $messageid, $1);
   }
}

# Search for email address which has vacation
for (@search_array) {
   /([\w\-.%]+\@[\w.-]+)/ or next; 
   my $addr = $1;
   my ($rv, $email) = find_real_address ($addr);
   if ($rv == 1) {
      do_debug ("[FOUND VACATION]: ", $messageid, $from, $to, $email);
      send_vacation_email( $email, $from, $to, $messageid);
   }
}

0;

#/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
