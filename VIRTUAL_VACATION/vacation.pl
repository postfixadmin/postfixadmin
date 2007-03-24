#!/usr/bin/perl -w
#
# Virtual Vacation
# Version 2.5
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

@input = <>;

for ($i = 0; $i <= $#input; $i++) {
   if ($input[$i] =~ /^From: (.*)\n$/) { $from = lc($1); }
   if ($input[$i] =~ /^To: (.*)\n$/) { $to = lc($1); }
   if ($input[$i] =~ /^Cc: (.*)\n$/) { $cc = lc($1); }
   if ($input[$i] =~ /^Subject: (.*)\n$/) { $subject = $1; }
}

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

@strip_to_array = split(/,/, $to);
if (defined $cc) { @strip_cc_array = split(/,/, $cc); }
push (@strip_to_array, @strip_cc_array);

foreach $strip_element (@strip_to_array) {
   if ($strip_element =~ /(\S*@\S*)/) { $strip_element = $1; }
   if ($strip_element =~ /\<(.*)\>/) { $strip_element = $1; }
   push (@search_array, $strip_element);
}

foreach $search_element (@search_array) {
   $query = qq{SELECT email FROM vacation WHERE email='$search_element'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      push (@found_array, $search_element);
   }
}

foreach $found_element (@found_array) {
   $query = qq{SELECT subject,body FROM vacation WHERE email='$found_element'};
   $sth = do_query ($query);
   $rv = $sth->rows;
   if ($rv == 1) {
      @row = $sth->fetchrow_array;
      if ($debugfile) { do_debug ($found_element, $from, $subject, $row[0], $row[1]); }
      if (do_cache ($found_element, $from, $row[2])) { next; }
      do_mail ($found_element, $from, $row[0], $row[1]);
      if ($logfile) { do_log ($found_element, $from, $subject); }
   }
}
1;
