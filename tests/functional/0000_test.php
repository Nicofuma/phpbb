<?php
/**
 * Created by PhpStorm.
 * User: Tristan
 * Date: 03/05/14
 * Time: 02:54
 */ 
phpinfo();

class test extends PHPUnit_Framework_TestCase
{
	protected $test_case_helpers;

	public function __construct($name = NULL, array $data = array(), $dataName = '')
{
	echo(PHPUnit_Runner_Version::getVersionString());
	echo' - ' . PHP_VERSION;
}
}
