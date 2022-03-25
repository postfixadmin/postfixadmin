#!/bin/bash

# 
# The logs are in the mailog/ direcory.
# You need to execute (add in the crontab) this script and set the $CONF['maillogging'] to 'YES' in the config.inc.php
# 
cd $(dirname $0)

#delete logs older than 30 days
find ../maillog/ -type f -mtime +30 -delete

#check if maillog directory exists
if [ ! -d ../maillog ]
then
        #create direcotry
	mkdir ../maillog
fi


data=`date --date='1 day ago' +%F`

mysql -u root --skip-column-names --execute="USE postfix; SELECT username,domain FROM domain_admins WHERE active=1 AND domain <> 'ALL';" | while read username domain
	    do

		#check if folder for domain doesn t exists
		if [ ! -d ../maillog/$domain ]
		then
    			echo "Directory does not exists for "$domain
			#create direcotry
			mkdir ../maillog/$domain			
		fi

		grep @$domain /var/log/mail.log.1 | grep -v "postfix-policyd" | grep -v postgrey > ../maillog/$domain/$data-bulk.log
		#pflogsumm -i --problems_first --detail 5 ../maillog/$domain/$data-bulk.log > ../maillog/$domain/$data-pflogsum.log 
		grep @$domain /var/log/mail.log.1 | grep "Password mismatch" > ../maillog/$domain/$data-failed-auth.log

		gzip ../maillog/$domain/$data-bulk.log
		#gzip ../maillog/$domain/$data-pflogsum.log
		gzip ../maillog/$domain/$data-failed-auth.log
		echo $domain
		
	    done
