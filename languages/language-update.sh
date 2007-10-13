#!/bin/bash

 # Postfix Admin
 #
 # LICENSE
 # This source file is subject to the GPL license that is bundled with
 # this package in the file LICENSE.TXT.
 #
 # Further details on the project are available at :
 #     http://www.postfixadmin.com or http://postfixadmin.sf.net
 #
 # @version $Id$
 # @license GNU GPL v2 or later.
 #
 # File: language-update.sh
 # Lists missing translations in language files and optionally patches the
 # english texts into the language file
 #
 # written by Christian Boltz


notext=0 # output full lines by default
patch=0  # do not patch by default
nocleanup=0 # don't delete tempfiles
filelist="en.lang" # always needed for comparison

while [ -n "$1" ] ; do
	case "$1" in
		--help)
			echo '
Lists missing translations in language files and optionally patches the
english texts into the language file

    Usage:

    '"$0"' [--notext | --patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    --notext will only list the translation keys (useful for a quick overview)
    --patch will patch the language file directly
    --nocleanup will keep all temp files (for debugging only)

    You can give any number of langugage files as parameter.
    If no files are given, all *.lang files will be used.

    Note for translators: untranslated entries have a comment
        # XXX
    attached.
'
			exit 0;
			;;
		--notext)
			notext=1
			;;
		--patch)
			patch=1
			;;
		--nocleanup)
			nocleanup=1
			;;
		-*)
			echo 'unknown option. Try --help ;-)' >&2
			exit 1
			;;
		*)
			filelist="$filelist $1"
			;;
	esac
	shift
done

test $notext = 1 && test $patch = 1 && echo "ERROR: You can't use --notext AND --patch at the same time." >&2 && exit 2

test "$filelist" = "en.lang" && filelist="`ls -1 *.lang`"

for file in $filelist ; do
	echo "<?php include('$file'); print join(\"\\n\", array_keys(\$PALANG)) . \"\\n\"; ?>" | php > $file.strings
done


for file in $filelist ; do
	test "$file" = "en.lang" && continue
	LANG=C diff -U1 $file.strings en.lang.strings > $file.diff && echo "*** $file: no difference ***"

	test $notext = 1 && cat $file.diff && continue

	grep -v 'No newline at end of file' "$file.diff" | while read line ; do
		greptext="$(echo $line | sed 's/^[+ 	-]//')"
		grepresult=$(grep "'$greptext'" en.lang) || grepresult="***DEFAULT*** $line"
		grepresult2=$(grep "'$greptext'" $file)  || grepresult2="$grepresult"
		case "$line" in
			---*)
				echo "$line"
				;;
			+++*)
				echo "$line"
				;;
			@*)
				echo "$line"
				;;
			-*)
				echo "-$grepresult"
				;;
			+*)
				# needs translation
				# already added as comment?
				test "$grepresult" = "$grepresult2" && {
					echo "+$grepresult # XXX" # english
				} || {
					echo " $grepresult2" # translated
					echo "keeping line $grepresult2" >&2
					echo "This will result in a malformed patch." >&2
				}
				;;
			*)
				echo " $grepresult2"
				;;
		esac
	done > $file.patch

	test $patch = 0 && cat $file.patch
	test $patch = 1 && patch $file < $file.patch
done
###############################################################################

# check for duplicated strings
for file in $filelist ; do
	sed -n "/PALANG/ s/[  ]*\$PALANG\['// ; s/'.*//p" $file |sort |uniq -c |grep -v " *1 " >&2 && \
	echo "*** duplicated string in $file, see above for details ***" >&2
done

test $nocleanup = 0 && for file in $filelist ; do
	rm -f $file.patch $file.strings $file.diff
done
