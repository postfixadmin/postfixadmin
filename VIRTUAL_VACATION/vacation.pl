#!/usr/bin/perl -w
#
# Virtual Vacation
# Version 2.6
# 2003 (c) High5!
# Created by: Mischa Peters <mischa at high5 dot net>
#
use DBI;

$db_name = "postfix";
$db_user = "postfixadmin";
$db_pass = "postfixadmin";
$sendmail = "/usr/sbin/sendmail";
$logfile = "";    # specify a file name here for example: vacation.log
$debugfile = "";  # sepcify a file name here for example: vacation.debug

@ignore_sender = (
   '-OUTGOING',
   '-OWNER',
   '-RELAY',
   '-REQUEST',
   'LISTSERV',
   'MAILER',
   'MAILER-DAEMON',
   'OWNER-',
   'postmaster',
   'UUCP',
   );

@ignore_precedence = (
   'bulk',
   'junk',
   );

$dbh = DBI->connect("DBI:mysql:$db_name", "$db_user", "$db_pass", { RaiseError => 1});
sub do_query {
   my ($query) = @_;
   my $sth = $dbh->prepare($query) or die "Can't prepare $query: $dbh->errstr\n";
   $sth->execute or die "Can't execute the query: $sth->errstr";
   $sth;
}

sub do_debug {
   my ($to, $from, $subject, $row0, $row1) = @_;
   open (DEBUG, ">> $debugfile") or die ("Unable to open debug file");
   chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
   print DEBUG "====== $date ======\n";
   print DEBUG "$to - $from - $subject\n";
   print DEBUG "Out of Office Subject: $row0\n";
   print DEBUG "Out of Office Body:\n$row1\n\n";
   close (DEBUG);
}

sub do_cache {
   my ($to, $from, $cache) = @_;
   $query = qq{SELECT cache FROM vacation WHERE email='$to' AND FIND_IN_SET('$from',cache)};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 0) {
      $query = qq{UPDATE vacation SET cache=CONCAT(cache,',','$from') WHERE email='$to'};
      $sth = do_query ($query);
   }
   return $rv;
}
                              
sub do_mail {
   my ($to, $from, $subject, $body) = @_;
   open (MAIL, "| $sendmail -t -f $to") or die ("Unable to open sendmail");
   print MAIL "From: $to\n";
   print MAIL "To: $from\n";
   print MAIL "Subject: $subject\n";
   print MAIL "X-Loop: Postfix Vacation\n\n";
   print MAIL "$body";
   close (MAIL);
}

sub do_log {
   my ($to, $from, $subject) = @_;
   open (LOG, ">> $logfile") or die ("Unable to open log file");
   chop ($date = `date "+%Y/%m/%d %H:%M:%S"`);
   print LOG "$date: $to - $from - $subject\n";
   close (LOG);
}

@input = <>;

for ($i = 0; $i <= $#input; $i++) {
   if ($input[$i] =~ /^From: (.*)\n$/) { $from = lc($1); }
   if ($input[$i] =~ /^To: (.*)\n$/) { $to = lc($1); }
   if ($input[$i] =~ /^Cc: (.*)\n$/) { $cc = lc($1); }
   if ($input[$i] =~ /^Subject: (.*)\n$/) { $subject = $1; }
   if ($input[$i] =~ /^Precedence: (.*)\n$/) { $precedence = lc($1); }
}

if ($from =~ /(\S*@\S*)/) { $from = $1; }
if ($from =~ /\<(.*)\>/) { $from = $1; }
if ($from =~ /\'(.*)\'/) { $from = $1; }

for (@ignore_sender) {
   exit if $from =~ /$_/i;
} 

if (defined $precedence) {
   for (@ignore_precedence) {
      exit if $precedence =~ /$_/i;
   }
}
    
@strip_to_array = split(/,/, $to);
if (defined $cc) { @strip_cc_array = split(/,/, $cc); }
push (@strip_to_array, @strip_cc_array);

for (@strip_to_array) {
   if ($_ =~ /(\S*@\S*)/) { $_ = $1; }
   if ($_ =~ /\<(.*)\>/) { $_ = $1; }
   if ($_ =~ /\'(.*)\'/) { $_ = $1; }
   push (@search_array, $_);
}

for (@search_array) {
   $query = qq{SELECT email FROM vacation WHERE email='$_'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      push (@found_array, $_);
   }
}

for (@found_array) {
   $query = qq{SELECT subject,body FROM vacation WHERE email='$_'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      @row = $sth->fetchrow_array;
      if ($debugfile) { do_debug ($_, $from, $subject, $row[0], $row[1]); }
      if (do_cache ($_, $from, $row[2])) { next; }
      do_mail ($_, $from, $row[0], $row[1]);
      if ($logfile) { do_log ($_, $from, $subject); }
   }
}
1;
