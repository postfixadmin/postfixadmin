#!/bin/bash
#Adapt to your setup

POSTFIX_DB="postfix_test"
POSTFIX_USER="postfixadmin"
POSTFIX_PASSWORD="my_password_is_strong"

#All the rest should be OK
QUERY30DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 29 DAY AND pw_expires_on < now() + interval 30 day AND thirty = false;"
QUERY14DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 13 DAY AND pw_expires_on < now() + interval 14 day AND fourteen = false;"
QUERY7DAYS="SELECT username,pw_expires_on FROM mailbox WHERE pw_expires_on > now() + interval 6 DAY AND pw_expires_on < now() + interval 7 day AND seven = false;"

function notifyThirtyDays() {
  mysql -B -u "$POSTFIX_USER" -p"$POSTFIX_PASSWORD" "$POSTFIX_DB" -e "$QUERY30DAYS" | while read -a RESULT; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 30 days before expiration notication" -r noreply@eyetech.fr ${RESULT[0]}
  echo "UPDATE mailbox SET thirty = true WHERE username = '${RESULT[0]}';" | mysql -u postfix postfix_test;done
}

function notifyFourteenDays() {
  mysql -B -u "$POSTFIX_USER" -p"$POSTFIX_PASSWORD" "$POSTFIX_DB" -e "$QUERY14DAYS" | while read -a RESULT; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 14 days before expiration notication" -r noreply@eyetech.fr ${RESULT[0]}
  echo "UPDATE mailbox SET fourteen = true WHERE username = '${RESULT[0]}';" | mysql -u postfix postfix_test;done
}

function notifySevenDays() {
  mysql -B -u "$POSTFIX_USER" -p"$POSTFIX_PASSWORD" "$POSTFIX_DB" -e "$QUERY7DAYS" | while read -a RESULT; do
  echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 7 days before expiraiton notication" -r noreply@eyetech.fr ${RESULT[0]}
  echo "UPDATE mailbox SET seven = true WHERE username = '${RESULT[0]}';" | mysql -u postfix postfix_test;done
}

notifyThirtyDays # Execute the function for 30 day notices
notifyFourteenDays # Execute the function for 14 day notices
notifySevenDays # Execute the function for  7 day notices

