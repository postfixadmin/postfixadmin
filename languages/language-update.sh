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
 # english texts into the language file.
 # Can also rename a $PALANG string.
 #
 # written by Christian Boltz


function update_string_list() {
	for file in en.lang $filelist ; do
		echo "<?php include('$file'); print join(\"\\n\", array_keys(\$PALANG)) . \"\\n\"; ?>" | php > $file.strings
	done

	for file in $filelist ; do
		test "$file" = "en.lang" && continue
		LANG=C diff -U2 $file.strings en.lang.strings > $file.diff && echo "*** $file: no difference ***"

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
		test $patch = 1 && patch --fuzz=1 $file < $file.patch
	done
} # end update_string_list()


function forcepatch() {
	for i in `seq 1 10` ; do 
		for file in $filelist ; do
			test "$file" = "en.lang" && { echo "*** skipping en.lang ***"; continue ; } >&2
			"$0" "$file" | head -n7  | recountdiff | patch "$file"
		done
	done
} # end forcepatch


function rename_string() {
	for file in $filelist ; do
		line="$(grep "PALANG\['$rename_old'\]" "$file")" || {
			echo "*** $file does not contain \$PALANG['$rename_old'] ***" >&2
			continue
		}

		newline="$(echo "$line" | sed "s/'$rename_old'/'$rename_new'/")"

		# create patch
		echo "
--- $file.old
+++ $file
@@ -1,1 +1,1 @@
-$line
+$newline
		" > "$file.patch"

		test $patch = 0 && cat $file.patch
		test $patch = 1 && patch $file < $file.patch
	done
} # end rename_string()


function addcomment() {
	for file in $filelist ; do
		test "$file" = "en.lang" && { echo "*** skipping en.lang ***"; continue ; } >&2

		line="$(grep "PALANG\['$text'\]" "$file")" || {
			echo "*** $file does not contain \$PALANG['$text'] ***" >&2
			continue
		}

		newline="$line # XXX $comment"

		# create patch
		echo "
--- $file.old
+++ $file
@@ -1,1 +1,1 @@
-$line
+$newline
		" > "$file.patch"

		test $patch = 0 && cat $file.patch
		test $patch = 1 && patch $file < $file.patch
	done
} # end add_comment



function cleanup() {
	# check for duplicated strings
	for file in $filelist ; do
		sed -n "/PALANG/ s/[  ]*\$PALANG\['// ; s/'.*//p" $file |sort |uniq -c |grep -v " *1 " >&2 && \
		echo "*** duplicated string in $file, see above for details ***" >&2
	done

	# cleanup tempfiles
	test $nocleanup = 0 && for file in $filelist ; do
		rm -f $file.patch $file.strings $file.diff
	done
} # end cleanup()


statistics() {
	(
	cat << 'EOF'
Postfixadmin - translation statistics
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Translating is easy:
- download your language file from SVN
  http://postfixadmin.svn.sourceforge.net/viewvc/postfixadmin/trunk/languages/
- search for lines with '# XXX' comments and
  - translate the line
  - remove the '# XXX'
  Note: The file is utf-8 encoded. You can also use htmlentities.
- post your translation to the tracker
  http://sourceforge.net/tracker/?group_id=191583&atid=937966


Number of missing translations:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

EOF

	grep -c XXX *.lang |sed 's/:/: /'

	cat << 'EOF'


Statistics based on:
EOF

	LANG=C svn info |grep 'Revision:\|Last Changed Date:'
	) > postfixadmin-languages.txt

	echo "Translation statistics have been saved as postfixadmin-languages.txt"

} # end statistics()


usage() {
echo '
    Usage:
    ~~~~~~


'"$0"' [--notext | --patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    List missing translations in language files and optionally patch the
    english texts into the language file

    --notext 
        only list the translation keys (useful for a quick overview)

    Note for translators: untranslated entries have a comment
        # XXX
    attached.


'"$0"' --rename old_string new_string [--patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    Rename $PALANG['"'"'old_string'"'"'] to $PALANG['"'"'new_string'"'"']


'"$0"' --addcomment string comment [--patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    Add a comment to $PALANG['"'"'string'"'"']

	Useful if a string needs to be translated again.


'"$0"' --forcepatch [foo.lang [bar.lang [...] ] ]

    Similar to --patch, but applies the patch line by line. Useful if --patch
    fails because of empty lines etc., but much slower.

    --forcepatch patches 10 lines per run. When you only see messages like
    "patch: **** Only garbage was found in the patch input.", take it as 
    success message :-)  (no difference remaining)


'"$0"' --stats

    Print translation statistics to postfixadmin-languages.txt


Common parameters:

    --patch
        patch the language file directly (instead of displaying the patch)
    --nocleanup 
        keep all temp files (for debugging)

    You can give any number of langugage files as parameter.
    If no files are given, all *.lang files will be used.

'
} # end usage()


# main script

notext=0 # output full lines by default
patch=0  # do not patch by default
forcepatch=0  # no forcepatch by default
nocleanup=0 # don't delete tempfiles
rename=0 # rename a string
stats=0  # create translation statistics
addcomment=0 # add translation comment
text=''
comment=''
rename_old=''
renane_new=''
filelist=''

while [ -n "$1" ] ; do
	case "$1" in
		--help)
			usage
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
		--rename)
			rename=1
			shift ; rename_old="$1"
			shift ; rename_new="$1"
			echo "$rename_old" | grep '^[a-z_-]*\.lang$' && rename_new='' # error out on *.lang - probably a filename
			echo "$rename_new" | grep '^[a-z_-]*\.lang$' && rename_new='' # error out on *.lang - probably a filename
			test -z "$rename_new" && { echo '--rename needs two parameters' >&2 ; exit 1 ; }
			;;
		--addcomment)
			addcomment=1
			shift ; text="$1"
			shift ; comment="$1"
			echo "$text" | grep '^[a-z_-]*\.lang$' && comment='' # error out on *.lang - probably a filename
			echo "$comment" | grep '^[a-z_-]*\.lang$' && comment='' # error out on *.lang - probably a filename
			test -z "$comment" && { echo '--addcomment needs two parameters' >&2 ; exit 1 ; }
			;;
		--forcepatch)
			forcepatch=1
			;;
		--stats)
			stats=1
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
done # end $@ loop

test $notext = 1 && test $patch = 1 && echo "ERROR: You can't use --notext AND --patch at the same time." >&2 && exit 2
test $notext = 1 && test $rename = 1 && echo "ERROR: You can't use --notext AND --rename at the same time." >&2 && exit 2

test "$filelist" = "" && filelist="`ls -1 *.lang`"

test "$addcomment" = 1 && { addcomment ; cleanup ; exit 0 ; }
test "$rename" = 1 && { rename_string ; cleanup ; exit 0 ; }
test "$forcepatch" = 1 && { forcepatch ; cleanup ; exit 0 ; }

test "$stats" = 1 && { statistics ; exit 0 ; }

update_string_list ; cleanup # default operation
