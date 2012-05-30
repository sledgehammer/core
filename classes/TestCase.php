<?php
/**
 * TestCase
 */
namespace Sledgehammer;
/**
 * A PHPUnit TestCase.
 *
 * @package Core
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * Constructor
	 *
	 * @param string $name
	 * @param array $data
	 * @param string $dataName
	 */
	function __construct($name = NULL, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);
	}

	/**
	 * Trigger an exception instead of a fatal error when using a invalid assert method.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @throws \BadMethodCallException
	 */
	function __call($method, $arguments) {
		throw new \BadMethodCallException('Method: PHPUnit_Framework_TestCase->'.$method.'() doesn\'t exist');
	}

}

?>