<?php
    // $Id: parse_error_test.php,v 1.2 2006/11/20 23:44:37 lastcraft Exp $
    
    require_once('../unit_tester.php');
    require_once('../reporter.php');

    $test = &new TestSuite('This should fail');
    $test->addTestFile('test_with_parse_error.php');
    $test->run(new HtmlReporter());
?>