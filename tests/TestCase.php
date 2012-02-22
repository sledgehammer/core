<?php
namespace SledgeHammer;
/**
 * A PHPUnit TestCase
 *
 * @package Core
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase {

	function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	protected function assertEqual($expected, $actual, $message = '') {
		deprecated('PHPUnit uses assertEquals() instead of assertEqual()');
		return parent::assertEquals($expected, $actual, $message);
	}

	protected function assertNotEqual($expected, $actual, $message = '') {
		deprecated('PHPUnit uses assertNotEquals() instead of assertNotEqual()');
		return parent::assertNotEquals($expected, $actual, $message);
	}
	protected function assertIsA($expected, $actual, $message = '') {
		deprecated('PHPUnit doesn\'t have a assertIsA() function');
		return parent::	assertTrue(get_class($expected) == get_class($actual), $message);
	}


	protected function pass($message = '') {
		deprecated('PHPUnit doesn\'t have a pass() function');
		$this->assertTrue(true, $message);
	}

	protected function expectError($message = '') {
		deprecated('PHPUnit doesn\'t have a expectError() function');
	}

}

?>
