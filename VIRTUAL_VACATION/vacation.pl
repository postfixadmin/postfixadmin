#!/usr/bin/perl -w
#
# Virtual Vacation
# Version 3.0
# Copyright (c) 2002, 2003, 2004 High5!
# Created by: Mischa Peters <mischa at high5 dot net>
# Changes:
# Jul 13 2004   David Osborn <ossdev at daocon.com>
#               Uses strict, processes domain level aliases, more
#               subroutines, send reply from original to address
#               
#
use DBI;
use strict;

my $db_type = 'mysql';
my $db_host = 'localhost';
my $db_user = 'postfixadmin';
my $db_pass = 'postfixadmin';
my $db_name = 'postfix';
my $sendmail = "/usr/sbin/sendmail";
my $logfile = "";    # specify a file name here for example: vacation.log
my $debugfile = "";  # sepcify a file name here for example: vacation.debug

my $dbh = DBI->connect("DBI:$db_type:$db_name:$db_host", "$db_user", "$db_pass", { RaiseError => 1 });

sub do_query {
   my ($query) = @_;
   my $sth = $dbh->prepare($query) or die "Can't prepare $query: $dbh->errstr\n";
   $sth->execute or die "Can't execute the query: $sth->errstr";
   return $sth;
}

sub do_debug {
   my ($in1, $in2, $in3, $in4, $in5, $in6) = @_;
   my $date;
   open (DEBUG, ">> $debugfile") or die ("Unable to open debug file");
   chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
   print DEBUG "====== $date ======\n";
   print DEBUG "$in1 $in2 $in3 $in4 $in5 $in6\n";
   close (DEBUG);
}

sub do_cache {
   my ($to, $from) = @_;
   my $query = qq{SELECT cache FROM vacation WHERE email='$to' AND FIND_IN_SET('$from',cache)};
   my $sth = do_query ($query);
   my $rv = $sth->rows;
   if ($rv == 0) {
      $query = qq{UPDATE vacation SET cache=CONCAT(cache,',','$from') WHERE email='$to'};
      $sth = do_query ($query);
   }
   return $rv;
}

sub do_log {
   my ($to, $from, $subject) = @_;
   my $date;
   open (LOG, ">> $logfile") or die ("Unable to open log file");
   chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
   print LOG "$date: $to - $from - $subject\n";
   close (LOG);
}

sub do_mail {
   my ($to, $from, $subject, $body) = @_;
   open (MAIL, "| $sendmail -t -f $to") or die ("Unable to open sendmail");
   print MAIL "From: $to\n";
   print MAIL "To: $from\n";
   print MAIL "Subject: $subject\n";
   print MAIL "X-Loop: Postfix Admin Virtual Vacation\n\n";
   print MAIL "$body";
   close (MAIL);
}

sub find_real_address {
   my ($email) = @_;
   my $realemail;
   my $query = qq{SELECT email FROM vacation WHERE email='$email' and active=1};
   my $sth = do_query ($query);
   my $rv = $sth->rows;

   # Recipient has vacation
   if ($rv == 1) {
      $realemail = $email;

   } else {
      $query = qq{SELECT goto FROM alias WHERE address='$email'};
      $sth = do_query ($query);
      $rv = $sth->rows;

      # Recipient is an alias, check if mailbox has vacation
      if ($rv == 1) { 
         my @row = $sth->fetchrow_array;
         my $alias = $row[0];
         $query = qq{SELECT email FROM vacation WHERE email='$alias' and active=1};
         $sth = do_query ($query);
         $rv = $sth->rows;

         # Alias has vacation
         if ($rv == 1) {
            $realemail = $alias;
         }

      # We still have to look for domain level aliases...
      } else { 
         my ($user, $domain) = split(/@/, $email);
         $query = qq{SELECT goto FROM alias WHERE address='\@$domain'};
         $sth = do_query ($query);
         $rv = $sth->rows;
         
         # The receipient has a domain level alias
         if ($rv == 1) { 
            my @row = $sth->fetchrow_array;
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
   my ($email, $orig_subject, $orig_from, $orig_to) = @_;
   my $query = qq{SELECT subject,body FROM vacation WHERE email='$email'};
   my $sth = do_query ($query);
   my $rv = $sth->rows;
   if ($rv == 1) {
      my @row = $sth->fetchrow_array;
      if (do_cache ($email, $orig_from)) { return; }
      if ($debugfile) { do_debug ("[SEND RESPONSE]:\n", "FROM: $email (orig-to: $orig_to)\n", "TO: $orig_from\n", "SUBJECT: $orig_subject\n", "VACATION SUBJECT: $row[0]\n", "VACATION BODY: $row[1]\n"); }
      do_mail ($email, $orig_from, $row[0], $row[1]);
      if ($logfile) { do_log ($email, $orig_from, $orig_subject); }
   }

}

########################### main #################################

my ($from, $to, $cc, $subject);

# Take headers apart
while (<STDIN>) {
   last if (/^$/);
   if (/^from:\s+(.*)\n$/i) { $from = $1; }
   if (/^to:\s+(.*)\n$/i) { $to = $1; }
   if (/^cc:\s+(.*)\n$/i) { $cc = $1; }
   if (/^subject:\s+(.*)\n$/i) { $subject = $1; }
   if (/^precedence:\s+(bulk|list|junk)/i) { exit (0); }
   if (/^x-loop:\s+postfix\ admin\ virtual\ vacation/i) { exit (0); }
}

# If either From: or To: are not set, exit
if (!$from || !$to) { exit (0); }

$from = lc ($from);

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
   if ($debugfile) { do_debug ("[STRIP RECIPIENTS]: ", $_, "-", "-", "-", "-"); }
   if ($_ =~ /([\w\-.%]+\@[\w.-]+)/) { push (@search_array, $1); }
}

# Search for email address which has vacation
for (@search_array) {
   my ($rv, $email) = find_real_address ($_);
   if ($rv == 1) {
      if ($debugfile) { do_debug ("[FOUND VACATION]: ", "$email", "-", "-", "-", "-"); }
      send_vacation_email ($email, $subject, $from, $to);
   }
}

0;
