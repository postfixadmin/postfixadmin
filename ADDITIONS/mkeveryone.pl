#!/usr/bin/perl
#
# Generate an 'everybody' alias for a domain.
#
# Create the file /etc/mkeveryone.conf
# chmod 640 /etc/mkeveryone.conf
# Example of mkeveryone.conf
#
# userid=postfix
# passwd=postfix
# db=postfix
# host=localhost
# port=3306
# domain=domain.tld
# target=everybody@domain.tld
# ignore=vacation@domain.tld
# ignore=spam@domain.tld
# ignore=newsletter@domain.tld
# ignore=root@domain.tld
#
# Save this file in, for example, /usr/local/sbin/mkeveryone.pl
# chmod 750 /usr/local/sbin/mkeveryone.pl
#
# Run the script!
#
use DBI;
use Time::Local;
use POSIX qw(EAGAIN);
use Fcntl;
use IO;
use IO::File;

my $timeNow=time();

my $DATFILE     = "/etc/mkeveryone.conf";
my $FILEHANDLE  = "";

# database information
my $db="postfix";
my $host="localhost";
my $port="3306";
my $userid="postfix";
my $passwd="postfix";
my $domain="domain.tld";
my $target="everyone@$domain";
my @ignore;
my @dest;

open (FILEHANDLE, $DATFILE);

while ( $LINE = <FILEHANDLE> ) {

       if ( length $LINE > 0 ) {
       chomp $LINE;

           $RETURNCODE = 0;

           SWITCH: {

               $LINE =~ /^ignore/i and do {
                   $LINE =~ s/^ignore// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   @ignore = (@ignore,$LINE);
                };

               $LINE =~ /^userid/i and do {
                   # Userid found.";
                   $LINE =~ s/^userid// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $userid = $LINE;
               };

               $LINE =~ /^passwd/i and do {
                   # Passwd found.";
                   $LINE =~ s/^passwd// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $passwd = $LINE;
               };

               $LINE =~ /^db/i and do {
                   # Database found.";
                   $LINE =~ s/^db// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $db = $LINE;
               };

               $LINE =~ /^host/i and do {
                   # Database host found.";
                   $LINE =~ s/^host// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $host = $LINE;
               };

               $LINE =~ /^port/i and do {
                   # Database host found.";
                   $LINE =~ s/^port// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $port = $LINE;
               };

               $LINE =~ /^target/i and do {
                   # Database host found.";
                   $LINE =~ s/^target// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $target = $LINE;
               };

               $LINE =~ /^domain/i and do {
                   # Database host found.";
                   $LINE =~ s/^domain// && $LINE =~ s/=// && $LINE =~ s/^ //g;
                   $domain = $LINE;
               };
          }
       }
}

print "Connecting to database $db on $host:$port...\n\r";

print "Target email address is $target...\n\r";

my $connectionInfo="DBI:mysql:database=$db;$host:$port";

# make connection to database
$dbh = DBI->connect($connectionInfo,$userid,$passwd);

# Delete the old message...prepare and execute query
$query = "SELECT username FROM mailbox WHERE domain='$domain';";
$sth = $dbh->prepare($query);
$sth->execute();

# assign fields to variables
$sth->bind_columns(\$username);

my $ign="false";
while($sth->fetch()) {
  $ign = "false";

  foreach $ignored ( @ignore ) {
     if ( $username eq $ignored ){
          $ign = "true";
          }
  }

  if ( $ign eq "false" ) {
       @dest = (@dest,$username);
  }
}

# Delete the old aliases...prepare and execute query
$query = "DELETE FROM alias WHERE address='$target';";
$sth = $dbh->prepare($query);
$sth->execute();

print "Record deleted from the database.\r\n";

$sth->finish();

$goto = join(",",@dest);
print "$goto\n\r\n\r";


# Insert the new message...prepare and execute query
$query = "INSERT INTO alias (address,goto,domain,created,modified) VALUES ('$target','$goto','$domain',now(),now());";

$sth = $dbh->prepare($query);
$sth->execute();

print "Record added to the database.\r\n";

$sth->finish();

# disconnect from databse
$dbh->disconnect;

