#!/bin/ksh
set -eu

# PostfixAdmin install script (Korn shell version).
# 1. Ensures 'composer' is available, downloading composer.phar if necessary.
# 2. Runs 'php composer install' to install required runtime libraries.
# 3. Creates 'templates_c' directory if missing and sets permissive mode.

PATH=/bin:/usr/bin:/usr/local/bin
export PATH

COMPOSER_URL="https://getcomposer.org/download/latest-stable/composer.phar"

# Check for PHP
if ! command -v php >/dev/null 2>&1; then
  print -u2 "I require php but it's not installed. Aborting."
  exit 1
fi

# Change to script directory
cd "$(dirname "$0")"

print " * Checking for composer.phar"
if ! command -v composer >/dev/null 2>&1; then
  # Composer not in PATH: look for local composer.phar
  if [[ ! -f composer.phar ]]; then
    print " * 'composer' not found in PATH, downloading from $COMPOSER_URL"
    if [[ -x /usr/bin/wget ]]; then
      wget -q -O composer.phar "$COMPOSER_URL"
    elif [[ -x /usr/bin/curl ]]; then
      curl -sSL -o composer.phar "$COMPOSER_URL"
    else
      print -u2 " ** Could not find wget or curl; please download $COMPOSER_URL to this directory."
      exit 1
    fi
  fi

  COMPOSER="$(pwd)/composer.phar"
  if [[ ! -f "$COMPOSER" ]]; then
    print -u2 "Failed to download composer; please fetch $COMPOSER_URL manually."
    exit 1
  fi
else
  COMPOSER="$(command -v composer)"
fi

print " * Using composer ($COMPOSER)"
print " * Installing libraries (php \"$COMPOSER\" install --no-dev --optimize-autoloader)"
php "$COMPOSER" install --no-dev --optimize-autoloader

# Create templates_c if missing
if [[ ! -d templates_c ]]; then
  mkdir -p templates_c
  chmod 777 templates_c
  print
  print " Warning:"
  print "   templates_c directory didn't exist, now created."
  print
  print "   You should change ownership and tighten permissions:"
  print "     chown www:www templates_c && chmod 750 templates_c"
  print
fi

print
print "Please continue configuration/setup in your web browser."
print "See also: https://github.com/postfixadmin/postfixadmin/blob/master/INSTALL.TXT#L58"
