#!/usr/bin/perl -w
#
# Virtual Vacation
# Version 2.7
# 2003 (c) High5!
# Created by: Mischa Peters <mischa at high5 dot net>
#
use DBI;

my $db_name = "postfix";
my $db_user = "postfixadmin";
my $db_pass = "postfixadmin";
my $sendmail = "/usr/sbin/sendmail";
my $logfile = "";    # specify a file name here for example: vacation.log
my $debugfile = "";  # sepcify a file name here for example: vacation.debug

$dbh = DBI->connect("DBI:mysql:$db_name", "$db_user", "$db_pass", { RaiseError => 1});
sub do_query {
   my ($query) = @_;
   my $sth = $dbh->prepare($query) or die "Can't prepare $query: $dbh->errstr\n";
   $sth->execute or die "Can't execute the query: $sth->errstr";
   $sth;
}

sub do_debug {
   my ($in1, $in2, $in3, $in4, $in5, $in6) = @_;
   open (DEBUG, ">> $debugfile") or die ("Unable to open debug file");
   chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
   print DEBUG "====== $date ======\n";
   print DEBUG "$in1 $in2 $in3 $in4 $in5 $in6\n";
   close (DEBUG);
}

sub do_cache {
   my ($to, $from) = @_;
   $query = qq{SELECT cache FROM vacation WHERE email='$to' AND FIND_IN_SET('$from',cache)};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 0) {
      $query = qq{UPDATE vacation SET cache=CONCAT(cache,',','$from') WHERE email='$to'};
      $sth = do_query ($query);
   }
   return $rv;
}

sub do_log {
   my ($to, $from, $subject) = @_;
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

while (<STDIN>) {
   last if (/^$/);

   if (/^from:\s+(.*)\n$/i) { $from = lc($1); }
   if (/^to:\s+(.*)\n$/i) { $to = lc($1); }
   if (/^cc:\s+(.*)\n$/i) { $cc = lc($1); }
   if (/^subject:\s+(.*)\n$/i) { $subject = $1; }

   exit (0) if (/^precedence:\s+(bulk|list|junk)/i);
   exit (0) if (/^x-loop:\s+postfix\ admin\ virtual\ vacation/i);
}

if ($from =~ /([\w\-.%]+\@[\w.-]+)/) {
   $from = $1;
}

if ($from eq "" || $from =~ /^owner-|-(request|owner)\@|^(mailer-daemon|postmaster)\@/i) {
   exit (0);
}

@strip_to_array = split(/, */, $to);
if (defined $cc) { @strip_cc_array = split(/, */, $cc); }
push (@strip_to_array, @strip_cc_array);

for (@strip_to_array) {
   if ($debugfile) { do_debug ("[STRIP RECIPIENTS]: ", $_, "-", "-", "-", "-"); }
   if ($_ =~ /([\w\-.%]+\@[\w.-]+)/) { push (@search_array, $1); }
}

for (@search_array) {
   $query = qq{SELECT email FROM vacation WHERE email='$_'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      if ($debugfile) { do_debug ("[FOUND VACATION]: ", "$_", "-", "-", "-", "-"); }
      push (@found_array, $_);
   }
}

for (@found_array) {
   $query = qq{SELECT subject,body FROM vacation WHERE email='$_'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      @row = $sth->fetchrow_array;
      if ($debugfile) { do_debug ("[SEND RESPONSE]:\n", "TO: $_\n", "FROM: $from\n", "SUBJECT: $subject\n", "VACATION SUBJECT: $row[0]\n", "VACATION BODY: $row[1]\n"); }
      if (do_cache ($_, $from)) { next; }
      do_mail ($_, $from, $row[0], $row[1]);
      if ($logfile) { do_log ($_, $from, $subject); }
   }
}

0;
