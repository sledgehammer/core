<?php
/**
 * Readonly
 *
 */
namespace SledgeHammer;

class Readonly extends Wrapper {

	protected function in($value, $element, $context) {
		throw new \Exception('The '.gettype($this->_data).' is marked readonly');
	}

}

?>
