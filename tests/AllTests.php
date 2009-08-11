<?php
require_once 'PHPUnit/Framework.php';
 
require_once 'ExtensionTest.php';
require_once 'PHPTest.php';
 
class AllTests
{
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Project');
 
        $suite->addTestSuite('ExtensionTest');
        $suite->addTestSuite('PHPTest');
 
        return $suite;
    }
}

