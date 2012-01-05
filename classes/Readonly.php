<?php
/**
 * Readonly, a wrapper that prevents changes to an object or array.
 *
 * @package Core
 */
namespace SledgeHammer;

class Readonly extends Wrapper {

	protected function in($value, $element, $context) {
		throw new \Exception('The '.gettype($this->_data).' is marked readonly');
	}

	public function __call($method, $arguments) {
		throw new \Exception('The '.gettype($this->_data).' is marked readonly');
	}

}

?>
