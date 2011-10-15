<?php
/**
 * Collection: Array on Steriods
 * Provides a filtering and other utility funtions for collections.
 *
 * @package Core
 */
namespace SledgeHammer;
use \ArrayIterator;

class Collection extends Object implements \Iterator, \Countable, \ArrayAccess {

	/**
	 * @var Iterator
	 */
	protected $iterator;

	/**
	 * @param \Iterator|array $iterator
	 */
	function __construct($iterator) {
		if (is_array($iterator)) {
			$this->iterator = new ArrayIterator($iterator);
		} else {
			$this->iterator = $iterator;
		}
	}

	/**
	 * Return a new collection where each element is a subselection of the original element.
	 *
	 * @param string|array $query  Path to the variable to select. Examples: "->id", "[message]", "customer.name", array('id' => 'message_id', 'message' => 'message_text')
	 * @param string|null $keyPath  (optional) The path that will be used as key.
	 * @return Collection
	 */
	function select($selector, $keyPath = null) {
		$items = array();
		foreach ($this as $key => $item) {
			if ($keyPath !== null) {
				$key = PropertyPath::get($item, $keyPath);
			}
			if (is_string($selector)) {
				$items[$key] = PropertyPath::get($item, $selector);
			} else {
				$items[$key] = array();
				foreach ($selector as $fieldPath => $valuePath) {
					$value = PropertyPath::get($item, $valuePath);
					PropertyPath::set($items[$key], $fieldPath, $value);
				}
			}
		}
		return new Collection($items);
	}

	/**
	 * Return a subsection of the collection based on the condition criteria
	 *
	 * @param array $conditions
	 * @return Collection
	 */
	function where($conditions) {
		$data = array();
		$counter = -1;
		foreach ($this as $key => $item) {
			$counter++;
			foreach ($conditions as $path => $expectation) {
				$actual = PropertyPath::get($item, $path);
				if (equals($actual, $expectation) == false) {
					continue 2; // Skip this entry
				}
			}
			if ($key != $counter) { // Is this an array
				$data[$key] = $item;
			} else {
				$data[] = $item;
			}
		}
		return new Collection($data);
	}

	/**
	 * Return the collection as an array
	 *
	 * @return array
	 */
	function toArray() {
		return iterator_to_array($this);
	}

	/**
	 * Return the number of elements in the collection.
	 * count($collection) 
	 * 
	 * @return int
	 */
	function count() {
		return count($this->iterator);
	}

	function __clone() {
		$this->iterator = new ArrayIterator(iterator_to_array($this->iterator));
//		$this->iterator = clone $this->iterator; // doesn't clone the data (in case of the ArrayIterator)
	}
	function __toString() {
		return json_encode($this->toArray());
	}

	// Iterator functions

	function current() {
		return $this->iterator->current();
	}

	function key() {
		return $this->iterator->key();
	}

	function next() {
		return $this->iterator->next();
	}

	function rewind() {
		if ($this->iterator instanceof \Iterator) {
			return $this->iterator->rewind();
		}
		$type = gettype($this->iterator);
		$type = ($type == 'object') ? get_class($this->iterator) : $type;
		throw new \Exception(''.$type.' is not an Iterator');
	}

	function valid() {
		return $this->iterator->valid();
	}

	// ArrayAccess functions

	function offsetExists($offset) {
		if (($this->iterator instanceof ArrayIterator) == false) {
			$this->iterator = new ArrayIterator(iterator_to_array($this));
		}
		return $this->iterator->offsetExists($offset);
	}

	function offsetGet($offset) {
		if (($this->iterator instanceof ArrayIterator) == false) {
			$this->iterator = new ArrayIterator(iterator_to_array($this));
		}
		return $this->iterator->offsetGet($offset);
	}

	function offsetSet($offset, $value) {
		if (($this->iterator instanceof ArrayIterator) == false) {
			$this->iterator = new ArrayIterator(iterator_to_array($this));
		}
		return $this->iterator->offsetSet($offset, $value);
	}

	function offsetUnset($offset) {
		if (($this->iterator instanceof ArrayIterator) == false) {
			$this->iterator = new ArrayIterator(iterator_to_array($this));
		}
		return $this->iterator->offsetUnset($offset);
	}

}

?>
