# Translating PostfixAdmin

PostfixAdmin is translated into multiple languages. The translation files can be found in `languages/*.lang` with one
file per language.

`languages/en.lang` is the canonical (English) source.

Each file is a PHP file (so MUST start with `<?php`, containing a list of :

```PHP
$PALANG['key'] = 'text';
```

statements. They are UTF-8 encoded (htmlentities may also be used).

## Translating

To contribute or update a translation:

- Edit the file for your language, e.g. languages/de.lang.
- Untranslated strings are marked with a trailing `# XXX` comment - they still
  contain the English text. For each such line:
    - translate the text, then
    - remove the "# XXX" marker.
    - Note: If you're running Postfixadmin locally, refreshing the relevant page should show changes straight away (there is
      no caching/compilation required). If you refresh a web browser using that translation, you should see your changes
      straight away
- Generate a patch file with your changes and attach it to a new issue at : https://github.com/postfixadmin/postfixadmin, or (ideally) open a new pull request.

## Adding or changing strings (for developers)

Only edit languages/en.lang when you add a new `$PALANG[...]` string or change an
existing one. Then propagate it to every other language file:

```shell
$ cd languages
$ ./language-update.sh --patch
```

This copies the new (or changed) keys into each language file, marked with a
trailing "# XXX" comment so translators can find the strings that still need
translating. Commit the updated languages/*.lang files together with your
en.lang change.

Please do not hand-translate strings in a feature pull request - just leave the
English "# XXX" placeholders and let the translation teams handle them.

Run `./language-update.sh --help` for its other modes (listing missing keys,
renaming or removing a key across all language files, etc.).
