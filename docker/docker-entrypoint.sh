#!/bin/bash
set -ex

if [[ "$1" == apache2* ]] || [ "$1" == php-fpm ]; then
	if ! [ -e index.php -a -e scripts/postfixadmin-cli.php ]; then
		echo >&2 "Postfixadmin not found in $PWD - copying now..."
		if [ "$(ls -A)" ]; then
			echo >&2 "WARNING: $PWD is not empty - press Ctrl+C now if this is an error!"
			( set -x; ls -A; sleep 10 )
		fi
		tar cf - --one-file-system -C /usr/src/postfixadmin . | tar xf -
		echo >&2 "Complete! Postfixadmin has been successfully copied to $PWD"
	fi

	if [ -n "${!MYSQL_ENV_MYSQL_*}" ]; then
		: "${POSTFIXADMIN_DB_TYPE:=mysqli}"
		: "${POSTFIXADMIN_DB_HOST:=mysql}"
		: "${POSTFIXADMIN_DB_USER:=${MYSQL_ENV_MYSQL_USER:-root}}"
		if [ "$POSTFIXADMIN_DB_USER" = 'root' ]; then
			: "${POSTFIXADMIN_DB_PASSWORD:=${MYSQL_ENV_MYSQL_ROOT_PASSWORD}}"
		else
			: "${POSTFIXADMIN_DB_PASSWORD:=${MYSQL_ENV_MYSQL_PASSWORD}}"
		fi
		: "${POSTFIXADMIN_DB_NAME:=${MYSQL_ENV_MYSQL_DATABASE:postfix}}"
	fi

	if [ ! -e config.local.php ]; then
		touch config.local.php
		echo "Write config to $PWD/config.local.php"
		echo "<?php
		\$CONF['database_type'] = '${POSTFIXADMIN_DB_TYPE}';
		\$CONF['database_host'] = '${POSTFIXADMIN_DB_HOST}';
		\$CONF['database_user'] = '${POSTFIXADMIN_DB_USER}';
		\$CONF['database_password'] = '${POSTFIXADMIN_DB_PASSWORD}';
		\$CONF['database_name'] = '${POSTFIXADMIN_DB_NAME}';

		\$CONF['setup_password'] = '${POSTFIXADMIN_SETUP_PASSWORD}';

		\$CONF['configured'] = true;
		?>" | tee config.local.php
	else
		echo "WARNING: $PWD/config.local.php already exists."
		echo "Postfixadmin related environment variables have been ignored."
	fi
fi

exec "$@"
