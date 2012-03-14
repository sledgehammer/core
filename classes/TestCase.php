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

	function __call($method, $arguments) {
		throw new \BadMethodCallException('Method: PHPUnit_Framework_TestCase->'.$method.'() doesn\'t exist');
	}

}

?>