#!/bin/bash

# This is an extremely simplistic test harness for the vacation functionality.
# To judge success or failure you (unfortunately) need to tail -f the log file, which sucks a little,
# but hey - it's better than no tests, right?

# Original author: David Goodwin (hence all the palepurple.co.uk references!)
# It would be nice if we could get some sort of status back from the vacation.pl script to indicate mail being sent, or not.



export PGPASSWORD=gingerdog
export PGUSER=dg
export PGDATABASE=postfix
export PGHOST=pgsqlserver


echo "DELETE FROM vacation WHERE email = 'david@example.org'" | psql

# First time around, there should be no vacation record for david@example.org, so these should all not cause mail to be sent.
# some will trip up spam/mailing list protection etc though
echo
echo "NONE OF THESE SHOULD RESULT IN MAIL BEING SENT"
echo 

#echo "On: mailing-list.txt:"
# cat mailing-list.txt | perl ../vacation.pl -t yes -f fw-general-return-20540-david=example.org@lists.zend.com -- david\#example.org@autoreply.example.org 
echo "On: test-email.txt:"
cat test-email.txt | perl ../vacation.pl -t yes -f david1@example.org -- david\#example.org@autoreply.example.org 
echo "On: spam.txt:"
cat spam.txt | perl ../vacation.pl -t yes -f mary@ccr.org -- david\#example.org@autoreply.example.org 
echo "On: asterisk-email.txt:"
cat asterisk-email.txt | perl ../vacation.pl -t yes -f www-data@palepurple.net -- david\#example.org@autoreply.example.org 
# do not reply to facebook
echo "On: facebook.txt:"
cat facebook.txt | perl ../vacation.pl -t yes -f notification+meynbxsa@facebookmail.com -- david\#example.org@autoreply.example.org 
# do not send yourself a vacation notice.
echo "On: mail-myself.txt:"
cat mail-myself.txt | perl ../vacation.pl -t yes -f david@example.org -- david\#example.org@autoreply.example.org 
# do not send yourself a vacation notice.
echo "On: teodor-smtp-envelope-headers.txt:"
cat teodor-smtp-envelope-headers.txt | perl ../vacation.pl -t yes -f david@example.org -- david\#example.org@autoreply.example.org


echo "INSERT INTO vacation (email, subject, body, created, active, domain) VALUES ('david@example.org', 'I am on holiday', 'Yeah, that is right', NOW(), true, 'example.org')" | psql 


echo 
echo "VACATION TURNED ON "
echo 
echo "Still ignore mailing list"
cat mailing-list.txt | perl ../vacation.pl -t yes -f fw-general-return-20540-david=example.org@lists.zend.com -- david\#example.org@autoreply.example.org
echo " * Should send vacation message for this *"
cat test-email.txt | perl ../vacation.pl -t yes -f david1@example.org -- david\#example.org@autoreply.example.org
echo " * Spam - no vacation message for this"
cat spam.txt | perl ../vacation.pl -t yes -f mary@xxccr.org -- david\#example.org@autoreply.example.org
echo " * OK - should send vacation message for this"
cat asterisk-email.txt | perl ../vacation.pl -t yes -f www-data@palepurple.net -- david\#example.org@autoreply.example.org
echo " * Facebook - should not send vacation message for"
cat facebook.txt | perl ../vacation.pl -t yes -f notification+meynbxsa@facebookmail.com -- david\#example.org@autoreply.example.org
echo " * Mailing myself - should not send vacation message"
cat mail-myself.txt | perl ../vacation.pl -t yes -f david@example.org -- david\#example.org@autoreply.example.org
echo
