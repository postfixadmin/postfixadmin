#!/bin/bash
#Adapt to your setup

POSTFIX_DB="postfix_test"
MYSQL_CREDENTIALS_FILE="postfixadmin.my.cnf"

REPLY_ADDRESS=noreply@example.com

# Change this list to change notification times and when ...
for INTERVAL in 30 14 7
do
    LOWER=$(( $INTERVAL - 1 ))

    QUERY="SELECT username,password_expiry FROM mailbox WHERE password_expiry > now() + interval $LOWER DAY AND password_expiry < NOW() + interval $INTERVAL DAY"

    mysql --defaults-extra-file="$MYSQL_CREDENTIALS_FILE" "$POSTFIX_DB" -B -e "$QUERY" | while read -a RESULT ; do
        echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 30 days before expiration notication" -r $REPLY_ADDRESS  ${RESULT[0]} 
    done

done
