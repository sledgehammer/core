<?php
/**
 * A SimpleTest TestCase, for DevUtils
 *
 * @package Core
 */
namespace SledgeHammer;

abstract class TestCase extends \UnitTestCase {

	function assertEqual($expected, $actual, $message = '') {
		deprecated('PHPUnit uses assertEquals() instead of assertEqual()');
		if ($message === '') {
			return parent::assertEqual($expected, $actual);
		}
		return parent::assertEqual($expected, $actual, $message);
	}

	protected function assertEquals($expected, $actual, $message = '') {
		if ($message === '') {
			return parent::assertEqual($expected, $actual);
		}
		return parent::assertEqual($expected, $actual, $message);
	}
}

?>
