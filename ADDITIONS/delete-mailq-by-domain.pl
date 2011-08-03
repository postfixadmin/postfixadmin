#!/usr/bin/perl


use strict;
use warnings;
use Getopt::Long;


$ENV{'PATH'} = "/sbin:/bin:/usr/sbin:/usr/bin";

my ($domain);
my $list = 0;

(help()) if (!$ARGV[0]);
GetOptions ('l' => \$list, 'd=s' => \$domain) or (help());


(list_queue()) if ($list == 1);

(delete_queue()) if ($domain);


sub delete_queue {
my $ids = `postqueue -p`;
my @ids = split /\n/, $ids;

for my $id (@ids) {
        next if $id =~ /^[\s\(-]/;
        chomp $id;
        next unless $id;
        $id =~ s/(.*?)\**\s.*/$1/;
        #print "$id\n";
        my $match = `postcat -q $id | grep '$domain'`;
        next unless $match;
        #print "Deleting ID: $id\n";
        my $saida = `postsuper -d $id`;
        print $saida;
}

}




sub list_queue {
my %hash_mail = ();
my @queue = `postqueue -p`;
my($queue,$key,$total);


foreach $queue(@queue) {
        chomp $queue;
        if ( $queue =~ /^\s+.*\@(.*)/ ) {
                $hash_mail{$1}++;
        }
}
print"\nTOTAL\tTO\n";
print"-----
----------------------------------------------------------------\n";
foreach $key (reverse sort { $hash_mail{$a} <=> $hash_mail{$b}} keys
%hash_mail) {
        $total += $hash_mail{$key};
        print"$hash_mail{$key} - $key\n";
}
print"\n$total -> TOTAL QUEUE\n";

}


sub help {
print "Usage $0 -l	            To list a row of E-mail
Usage $0 -d domain.com   To delete the mensgens the Domain\n"; 
}




