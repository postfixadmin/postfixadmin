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


$test2 = new DovecotCrypt('test2');
$test2->crypt('CRAM-MD5');
echo "CRAM_MD5:\n\n";
echo "Crypted: ".$test2->get()."\n";
if ($test2->verify('CRAM-MD5', $test2->get())) {
    echo "Varified: true\n";
} else {
    echo "Varified: false\n";
}
echo "\n";
