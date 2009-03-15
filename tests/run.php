<?php
/**
 * Responsible for test suite...
 * @package tests
 */
require_once(dirname(__FILE__) . '/common.php');

require_once('simpletest/reporter.php');
require_once('simpletest/unit_tester.php');

$test = new GroupTest('Postfixadmin XMLRPC Unit Tests');

$test->addTestFile('./RemoteVacationTest.php');

exit($test->run(new TextReporter()) ? 0 : 1);
