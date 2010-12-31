<?php

require_once('crypt.php');
require_once('dovecot_crypt.php');





$test = new DovecotCrypt('test');
$test->crypt('CRYPT');
echo "CRYPT:\n\n";
echo "Crypted: ".$test->get()."\n";
if ($test->verify('CRYPT', $test->get())) {
  echo "Varified: true\n";
} else {
  echo "Varified: false\n";
}
echo "\n";
?>