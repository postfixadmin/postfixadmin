#!/bin/bash

# 
# The logs are in the mailog/ direcory.
# You need to execute (add in the crontab) this script and set the $CONF['maillogging'] to 'YES' in the config.inc.php
# 
cd "$(dirname $0)"

pwd=$(pwd)

set -u
set -e


#check if maillog directory exists
if [ ! -d ../maillog ]
then
    #create direcotry
    mkdir ../maillog || ( echo "Couldn't create $pwd/../maillog." && exit 1 )
fi

cd ../maillog

#delete logs older than 30 days
#find . -type f -mtime +30 -delete
echo "Checking for old files to delete ..."
find . -type f -mtime +30 -print

data=$(date --date='1 day ago' +%F)

MYSQL_CONFIG="-u root -psomething -h myserver"
# or even better.... mysql --defaults-extra-file=/path/to/my.cnf 
for domain in $(mysql "$MYSQL_CONFIG" --skip-column-names -e "SELECT domain FROM domain" postfix)
do

    #check if folder for domain doesn t exists
    if [ ! -d "$domain" ]
    then
        echo "Directory does not exists for $domain"
        #create direcotry
        mkdir "$domain"
    fi

    grep "@$domain" /var/log/mail.log.1 | grep -v "postfix-policyd" | grep -v postgrey > "$domain/$data-bulk.log"
    #pflogsumm -i --problems_first --detail 5 ../maillog/$domain/$data-bulk.log > $domain/$data-pflogsum.log 
    grep "@$domain" /var/log/mail.log.1 | grep "Password mismatch" > "$domain/$data-failed-auth.log"

    gzip "$domain/$data-bulk.log"
    #gzip $domain/$data-pflogsum.log
    gzip "$domain/$data-failed-auth.log"
    echo "$domain"

done
