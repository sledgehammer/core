<?php
/**
 * Wrap the object/array into readonly container

 * @package Filter
 */
namespace SledgeHammer;
abstract class Wrapper extends Object implements \ArrayAccess, \Iterator {

	protected $_data;

	/**
	 * @var bool  Wrap elements that are object or arrays
	 */
	private $_recursive = true;

	/**
	 * @param object/iterator/array $data
	 * @param array $option
	 */
	function __construct($data, $options = array()) {
		$this->_data = $data;
		foreach ($options as $name => $value) {
			$property = '_'.$name;
			if (property_exists($this, $property)) {
				$this->$property = $value;
			} else {
				notice('Invalid option: "'.$name.'"');
			}
		}
	}

	/**
	 * Process the value that is going out (is being retrieved from the wrapped object)
	 *
	 * @param type $value
	 * @param type $element
	 * @param type $context
	 */
	protected function out($value, $element, $context) {
		if ($this->_recursive && (is_array($value) || is_object($value))) {
			// Return a wrapper with the same configuration as
			$clone = clone $this;
			$clone->_data = $value;
			return $clone;
		}
		return $value;
	}

	/**
	 * Process the value before it's going in (is being set into the wrapped object)
	 *
	 * @param type $value
	 * @param type $element
	 * @param type $context
	 */
	protected function in($value, $element, $context) {
		return $value;
	}

	/**
	 * Magic getter en setter functies zodat je dat de eigenschappen zowel als object->eigenschap kunt opvragen
	 */

	function __get($property) {
		return $this->out($this->_data->$property, $property, 'object');
	}

	function __set($property, $value) {
		$value = $this->in($value, $property, 'object');
		$this->_data->$property = $value;
	}

	/**
	 * ArrayAccess wrapper functies die er voor zorgen dat de eigenschappen ook als array['element'] kunt opgevragen
	 */

	function offsetGet($key) {
		if (is_array($this->_data)) {
			return $this->out($this->_data[$key], $key, 'array');
		}
	}
	function offsetSet($key, $value) {
		if (is_array($this->_data)) {
			$value = $this->in($value, $key, 'array');
			$this->_data[$key] = $value;
		}
	}
	function offsetUnset($property) {
		throw new \Exception('Not (yet) supported');
	}
	function offsetExists($property) {
		throw new \Exception('Not (yet) supported');
	}

	/**
	 * Iterator functions waardoor je de elementen via een iterator_to_array() kunt omzetten naar een array.
	 * Hierdoor kun je via convert_iterators_to_arrays()
	 */
	function rewind() {
		if ($this->_data instanceof \Iterator) {
			return $this->_data->rewind();
		}
	}

	function current() {
		if ($this->_data instanceof \Iterator) {
			return $this->out($this->_data->current(), 'current', 'iterator');
		}
	}

	function next() {
		if ($this->_data instanceof \Iterator) {
			$this->_data->next();
		}
	}

	function key() {
		if ($this->_data instanceof \Iterator) {
			return $this->out($this->_data->next(), 'key', 'iterator');
		}
	}

	function valid() {
		if ($this->_data instanceof \Iterator) {
			return $this->_data->valid();
		}
		return false;
	}
}
?>
