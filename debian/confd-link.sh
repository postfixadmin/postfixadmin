#!/bin/sh
# File:		confd-link.sh
# Changes:
#       20081109 Norman Messtorff <normes@normes.org>
#               Initial version.
#
# Needs:	$servers - the servers to link configurations in.
#		$linkname	 - specify the link name
#		$linkdestination_apache	- specify the link destination (Apache config)
#		$linkdestination_lighttpd - specify the link destination (lighttpd config)
# Description:	Linking configurations into Webservers conf.d
# Sets:		$status = {error, nothing, linked, removed}
#		$error = error message (if $status = error)

status="nothing"
error=""

#
# Checking needed settings...
#
if [ -z "$servers" ]; then
    status="error"
    error="No servers specified in confd-link.sh"
elif [ -z "$linkname" ]; then
	status="error"
	error="No linkname specified in confd-link.sh"
else
	
	#
	# The link removal part...
	#
	if [ "$1" = "remove" ] || [ "$1" = "purge" ]; then
		for A in $servers ; do
			A=${A%,}
			#
			# lighttpd has no conf.d
			#
			if [ "$A" = "lighttpd" ]; then
				linkpath="/etc/lighttpd/conf-available"
			else
				linkpath="/etc/$A/conf.d"
			fi
			
			#
			# Is it existing and a symbolic link or are we going to do some unwished things?
			#
			if [ -L $linkpath/$linkname ]; then
				if rm -f $linkpath/$linkname 2>&1 ; then
					status="removed"
				else
					status="error"
					error="ERROR! Couln't remove $linkpath/$linkname "
				fi
			else
				status="error"
				error="ERROR! $linkpath/$linkname is no symbolic link or doesn't exists."
			fi
		done
	else
		for A in $servers ; do
			A=${A%,}
			#
			# lighttpd has no conf.d
			#
			if [ "$A" = "lighttpd" ]; then
				linkpath="/etc/lighttpd/conf-available"
				linkdestination=$linkdestination_lighttpd
			else
				linkpath="/etc/$A/conf.d"
				linkdestination=$linkdestination_apache
			fi
			
			if [ -d $linkpath ]; then
				if ln -s $linkdestination $linkpath/$linkname >/dev/null 2>&1 ; then
					status="linked"
				else
					status="error"
					error="ERROR! 'ln -s' returned an error. Could not create link in $linkpath"
				fi
			else
				status="error"
				error="ERROR! $linkpath doesn't exists. Could not create link in $linkpath"
			fi
		done
	fi
fi
