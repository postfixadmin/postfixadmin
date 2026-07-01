#!/bin/bash

 # Postfix Admin
 #
 # LICENSE
 # This source file is subject to the GPL license that is bundled with
 # this package in the file LICENSE.TXT.
 #
 # Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 #
 # @version $Id$
 # @license GNU GPL v2 or later.
 #
 # File: language-update.sh
 # Lists missing translations in language files and optionally patches the
 # english texts into the language file.
 # Can also do several other things that help handling the language files - see --help.
 #
 # written by Christian Boltz


function update_string_list() {
	# Find keys that exist in en.lang but are missing from each translation and
	# (with --patch) splice the English text in, marked "# XXX", in en.lang key
	# order. Implemented in PHP because the language files are PHP: this parses
	# them with the real tokenizer (handling quotes, concatenation, heredocs and
	# aliased values) and does NOT rely on both files listing keys in the same
	# order, which the old diff-based approach did.
	PA_NOTEXT="$notext" PA_PATCH="$patch" php /dev/stdin en.lang $filelist <<'ENDOFPHP'
<?php
# Parse $PALANG['key'] = ...; statements from a language file's SOURCE (never
# include()d, to avoid executing it) and return them in file order as
# [key, startLine, endLine, sourceText].
function parse_palang(string $src): array {
    $tokens = token_get_all($src);
    $n = count($tokens);
    $stmts = [];
    $i = 0;
    while ($i < $n) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$PALANG') {
            $startLine = $t[2];
            $text = '';
            $key = null;
            $j = $i;
            while ($j < $n) {
                $tj = $tokens[$j];
                $txt = is_array($tj) ? $tj[1] : $tj;
                $text .= $txt;
                if ($key === null && is_array($tj) && $tj[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $key = trim($tj[1], "'\"");
                }
                if (!is_array($tj) && $tj === ';') {
                    break;
                }
                $j++;
            }
            if ($key !== null) {
                $endLine = $startLine + substr_count($text, "\n");
                $stmts[] = [$key, $startLine, $endLine, rtrim($text)];
            }
            $i = $j + 1;
            continue;
        }
        $i++;
    }
    return $stmts;
}

$notext = getenv('PA_NOTEXT') === '1';
$patch  = getenv('PA_PATCH')  === '1';

$reference = 'en.lang';
if (!is_file($reference)) {
    fwrite(STDERR, "*** $reference not found in current directory ***\n");
    exit(1);
}
$enStmts = parse_palang(file_get_contents($reference));
$enOrder = [];      // ordered list of keys
$enSource = [];     // key => source text
foreach ($enStmts as [$key, , , $text]) {
    if (!isset($enSource[$key])) {
        $enOrder[] = $key;
    }
    $enSource[$key] = $text;
}

$files = array_slice($argv, 1);
foreach ($files as $file) {
    if ($file === $reference) {
        continue;
    }
    if (!is_file($file)) {
        fwrite(STDERR, "*** $file not found, skipping ***\n");
        continue;
    }

    $src = file_get_contents($file);
    $transStmts = parse_palang($src);
    $transKeys = [];    // key => endLine (last definition wins)
    foreach ($transStmts as [$key, , $endLine, ]) {
        $transKeys[$key] = $endLine;
    }

    // missing = in en.lang, not in translation (kept in en.lang order, each
    // anchored to the nearest preceding key that DOES exist in the translation)
    $missing = [];      // list of [key, anchorKey|null]
    $lastShared = null;
    foreach ($enOrder as $key) {
        if (isset($transKeys[$key])) {
            $lastShared = $key;
        } else {
            $missing[] = [$key, $lastShared];
        }
    }
    // obsolete = in translation, not in en.lang
    $obsolete = array_values(array_diff(array_keys($transKeys), $enOrder));

    if (!$missing && !$obsolete) {
        echo "*** $file: no missing translations ***\n";
        continue;
    }

    if ($notext) {
        echo "### $file ###\n";
        foreach ($missing as [$key, ]) {
            echo "+$key\n";
        }
        foreach ($obsolete as $key) {
            echo "-$key (obsolete)\n";
        }
        continue;
    }

    if (!$patch) {
        // preview only
        echo "### $file: " . count($missing) . " missing, " . count($obsolete) . " obsolete ###\n";
        foreach ($missing as [$key, $anchor]) {
            $where = $anchor === null ? '(top of file)' : "after '$anchor'";
            echo $enSource[$key] . " # XXX   $where\n";
        }
        foreach ($obsolete as $key) {
            echo "# obsolete in $file (not in en.lang): $key\n";
        }
        continue;
    }

    // --patch: insert missing English strings (marked # XXX) in en.lang order,
    // leaving every existing line untouched. Obsolete keys are reported, not
    // removed (use --remove / --obsolete for those).
    $lines = explode("\n", $src);
    $insertAfter = [];      // endLine => [block, ...]
    $topBlocks = [];
    foreach ($missing as [$key, $anchor]) {
        $block = $enSource[$key] . ' # XXX';
        if ($anchor === null) {
            $topBlocks[] = $block;
        } else {
            $insertAfter[$transKeys[$anchor]][] = $block;
        }
    }
    $firstStart = $transStmts ? $transStmts[0][1] : null;

    $out = [];
    foreach ($lines as $idx => $line) {
        $lineNo = $idx + 1;
        if ($topBlocks && $lineNo === $firstStart) {
            foreach ($topBlocks as $b) {
                $out[] = $b;
            }
            $topBlocks = [];
        }
        $out[] = $line;
        if (isset($insertAfter[$lineNo])) {
            foreach ($insertAfter[$lineNo] as $b) {
                $out[] = $b;
            }
        }
    }
    if ($topBlocks) {   // file had no PALANG statements at all
        foreach ($topBlocks as $b) {
            $out[] = $b;
        }
    }

    file_put_contents($file, implode("\n", $out));
    echo "*** $file: added " . count($missing) . " missing string(s)";
    if ($obsolete) {
        echo ", " . count($obsolete) . " obsolete string(s) left in place (see --remove/--obsolete)";
    }
    echo " ***\n";
    foreach ($obsolete as $key) {
        fwrite(STDERR, "# obsolete in $file (not in en.lang): $key\n");
    }
}
ENDOFPHP
} # end update_string_list()



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


function remove_string() {
	for file in $filelist ; do
		line="$(grep "PALANG\['$remove_string'\]" "$file")" || {
			echo "*** $file does not contain \$PALANG['$remove_string'] ***" >&2
			continue
		}

		# create patch
		echo "
--- $file.old
+++ $file
@@ -1,1 +1,0 @@
-$line
		" > "$file.patch"

		test $patch = 0 && cat $file.patch
		test $patch = 1 && patch $file < $file.patch
	done
} # end remove_string()


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


function obsolete() {
	for file in $filelist ; do
		# do not skip en.lang

		line="$(grep "PALANG\['$text'\]" "$file")" || {
			echo "*** $file does not contain \$PALANG['$text'] ***" >&2
			continue
		}

		newline="$line # obsolete"

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



function comparetext() {
	for file in $filelist ; do
		echo "<?php 
			include('$file');
			if (\$PALANG['$text1'] != \$PALANG['$text2']) {
				echo '$file: ' . \$PALANG['$text1'] . ' -- $text1' . \"\\n\";
				echo '$file: ' . \$PALANG['$text2'] . ' -- $text2' . \"\\n\";
			}
		" | php
	done
}



function cleanup() {
	# check for duplicated strings
	for file in $filelist ; do
		sed -n "/PALANG/ s/[  ]*\$PALANG\['// ; s/'.*//p" $file |sort |uniq -c |grep -v " *1 " >&2 && \
		echo "*** duplicated string in $file, see above for details ***" >&2
	done

	# cleanup tempfiles
	test $nocleanup = 0 && for file in $filelist ; do
		rm -f $file.patch $file.strings $file.diff $file.orig
	done
} # end cleanup()


statistics() {
	(
	cat << 'EOF'
Postfixadmin - translation statistics
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Translating is easy:
- download your language file from git
  https://github.com/postfixadmin/postfixadmin/tree/master/languages
- search for lines with '# XXX' comments and
  - translate the line
  - remove the '# XXX'
  Note: The file is utf-8 encoded.
- post your translation as a pull request
  https://github.com/postfixadmin/postfixadmin/pulls


Number of missing translations:
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

EOF

	grep -c XXX *.lang |sed 's/:/: /'

	cat << 'EOF'


Statistics based on:
EOF

	LANG=C git log -1 --format='Revision: %H%nLast Changed Date: %ci'
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


'"$0"' --remove string [--patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    Remove $PALANG['"'"'string'"'"'] from language files


'"$0"' --addcomment string comment [--patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    Add a comment to $PALANG['"'"'string'"'"']

    Useful if a string needs to be translated again.


'"$0"' --obsolete string [--patch] [--nocleanup] [foo.lang [bar.lang [...] ] ]

    Mark $PALANG['"'"'string'"'"'] as obsolete / no longer used


'"$0"' --comparetext string1 string2 [foo.lang [bar.lang [...] ] ]

    Compare two texts in $PALANG.
    This can be useful to find out if two equel texts in $PALANG are the 
    same in all languages. No output means no difference.


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
nocleanup=0 # don't delete tempfiles
rename=0 # rename a string
remove=0 # remove a string
stats=0  # create translation statistics
addcomment=0 # add translation comment
obsolete=0 # add obsolete note
comparetext=0 # compare two PALANG texts
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
		--comparetext)
			comparetext=1
			shift; text1="$1"
			shift; text2="$1"
			test -z "$text2" && { echo '--comparetext needs two parameters' >&2 ; exit 1; }
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
		--remove)
			remove=1
			shift ; remove_string="$1"
			test -z "$remove_string" && { echo '--remove needs a parameter' >&2 ; exit 1 ; }
			;;
		--addcomment)
			addcomment=1
			shift ; text="$1"
			shift ; comment="$1"
			echo "$text" | grep '^[a-z_-]*\.lang$' && comment='' # error out on *.lang - probably a filename
			echo "$comment" | grep '^[a-z_-]*\.lang$' && comment='' # error out on *.lang - probably a filename
			test -z "$comment" && { echo '--addcomment needs two parameters' >&2 ; exit 1 ; }
			;;
		--obsolete)
			obsolete=1
			shift ; text="$1"
			echo "$text" | grep '^[a-z_-]*\.lang$' && comment='' # error out on *.lang - probably a filename
			test -z "$text" && { echo '--addcomment needs a parameter' >&2 ; exit 1 ; }
			;;
		--forcepatch)
			# kept for backwards compatibility; --patch is now robust
			patch=1
			echo '--forcepatch is deprecated, using --patch instead' >&2
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
test "$remove" = 1 && { remove_string ; cleanup ; exit 0 ; }
test "$obsolete" = 1 && { obsolete ; cleanup ; exit 0 ; }
test "$comparetext" = 1 && { comparetext ; cleanup ; exit 0 ; }

test "$stats" = 1 && { statistics ; exit 0 ; }

update_string_list ; cleanup # default operation
