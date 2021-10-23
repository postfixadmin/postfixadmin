#!/bin/bash

set -eu


COMPOSER_URL=https://getcomposer.org/download/latest-stable/composer.phar

cd "$(dirname "$0")"

# Check for $(pwd)/composer.phar

echo " * Checking for composer.phar "

if [ ! -f composer.phar ]; then

    echo " * Trying to download composer.phar from $COMPOSER_URL "
    # try and download it one way or another
    if [ -x /usr/bin/wget ]; then
        wget -q -O composer.phar $COMPOSER_URL
    else
        if [ -x /usr/bin/curl ]; then
            curl -o composer.phar $COMPOSER_URL
        else
            echo " ** Could not find wget or curl; please download $COMPOSER_URL to pwd" >/dev/stderr
            exit 1
        fi
    fi
fi

echo " * Running composer install --no-dev"

php composer.phar install --prefer-dist -n --no-dev


if [ ! -d templates_c ]; then

    echo " * Warning: templates_c didn't exist. I have created it, but you might want to change the ownership and reduce permissions"

    # should really fix ownership to be that of the webserver; is there a nice way to discover which ? (www-data ?)
    mkdir -p templates_c && chmod 777 templates_c
fi
echo
echo "Please continue configuration / setup within your web browser. "
echo "See also : https://github.com/postfixadmin/postfixadmin/blob/master/INSTALL.TXT#L58 "
echo
