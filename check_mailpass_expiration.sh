#!/bin/bash
#Adapt to your setup

POSTFIX_DB="postfix_test"
MYSQL_CREDENTIALS_FILE="postfixadmin.my.cnf"

#All the rest should be OK
QUERY30DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 29 DAY AND pw_expires_on < now() + interval 30 day"
QUERY14DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 13 DAY AND pw_expires_on < now() + interval 14 day"
QUERY7DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 6 DAY AND pw_expires_on < now() + interval 7 day"

function notifyThirtyDays() {
  mysql --defaults-extra-file="$MYSQL_CREDENTIALS_FILE" "$POSTFIX_DB" -B -e "$QUERY30DAYS" | while read -a RESULT ; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 30 days before expiration notication" -r noreply@eyetech.fr ${RESULT[0]} ; done
}

function notifyFourteenDays() {
  mysql --defaults-extra-file="$MYSQL_CREDENTIALS_FILE" "$POSTFIX_DB" -B -e "$QUERY14DAYS" | while read -a RESULT ; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 14 days before expiration notication" -r noreply@eyetech.fr ${RESULT[0]} ; done
}

function notifySevenDays() {
  mysql --defaults-extra-file="$MYSQL_CREDENTIALS_FILE" "$POSTFIX_DB" -B -e "$QUERY7DAYS" | while read -a RESULT ; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 7 days before expiraiton notication" -r noreply@eyetech.fr ${RESULT[0]} ; done
}

notifyThirtyDays # Execute the function for 30 day notices
notifyFourteenDays # Execute the function for 14 day notices
notifySevenDays # Execute the function for  7 day notices
