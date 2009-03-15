<?php
// $Id: unit_tests.php,v 1.51 2007/01/16 23:35:22 lastcraft Exp $
if (! defined('TEST')) {
    define('TEST', __FILE__);
}
require_once(dirname(__FILE__) . '/test_groups.php');
require_once(dirname(__FILE__) . '/../reporter.php');

if (TEST == __FILE__) {
    $test = &new UnitTests();
    if (SimpleReporter::inCli()) {
        $result = $test->run(new SelectiveReporter(new TextReporter(), @$argv[1], @$argv[2]));
        return ($result ? 0 : 1);
    }
    $test->run(new SelectiveReporter(new HtmlReporter(), @$_GET['c'], @$_GET['t']));
}
?>