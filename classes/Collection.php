<?php
/**
 * Collection: Array on Steriods
 * Provides a filtering and other utility funtions for collections.
 *
 * @package Core
 */
namespace SledgeHammer;
use \ArrayIterator;

const SORT_NATURAL = -1;

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
		$isClosure = (is_object($selector) && is_callable($selector));
		foreach ($this as $key => $item) {
			if ($keyPath !== null) {
				$key = PropertyPath::get($item, $keyPath);
			}
			if (is_string($selector)) {
				$items[$key] = PropertyPath::get($item, $selector);
			} elseif ($isClosure) {
				$items[$key] = $selector($item, $key);
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
	 * Return a new Collection with a subsection of the collection based on the condition criteria
	 *
	 * @param array $conditions
	 * @return Collection
	 */
	function where($conditions) {
		$isClosure = (is_object($conditions) && is_callable($conditions));
		$data = array();
		$counter = -1;
		foreach ($this as $key => $item) {
			$counter++;
			if ($isClosure) {
				if ($conditions($item, $key) == false) {
					continue;
				}
			} else {
				foreach ($conditions as $path => $expectation) {
					$actual = PropertyPath::get($item, $path);
					if (equals($actual, $expectation) == false) {
						continue 2; // Skip this entry
					}
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
	 * Return a new collection sorted by the given field in ascending order.
	 *
	 * @param string $path
	 * @param int $flag  The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
	 * @return Collection
	 */
	function orderBy($path, $method = SORT_REGULAR) {
		$sortOrder = array();
		$items = array();
		$indexed = true;
		$counter = 0;
		// Collect values
		foreach ($this as $key => $item) {
			$items[$key] = $item;
			$sortOrder[$key] = PropertyPath::get($item, $path);
			if ($key !== $counter) {
				$indexed = false;
			}
			$counter++;
		}
		// Sort the values
		if ($method == SORT_NATURAL) {
			natsort($sortOrder);
		} else {
			asort($sortOrder, $method);
		}
		//
		$sorted = array();
		foreach (array_keys($sortOrder) as $key) {
			if ($indexed) {
				$sorted[] = $items[$key];
			} else { // Keep keys intact
				$sorted[$key] = $items[$key];
			}
		}
		return new Collection($sorted);
	}

	/**
	 * Return a new collection sorted by the given field in descending order.
	 *
	 * @param string $path
	 * @param int $flag  The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
	 * @return Collection
	 */
	function orderByDescending($path, $method = SORT_REGULAR) {
		return $this->orderBy($path, $method)->reverse();
	}

	/**
	 * Return a new collection in the reverse order
	 * @return Collection
	 */
	function reverse() {
		$order = array();
		$indexed = true;
		$counter = 0;
		// Collect values
		foreach ($this as $key => $item) {
			$items[$key] = $item;
			$order[] = $key;
			if ($key !== $counter) {
				$indexed = false;
			}
			$counter++;
		}
		rsort($order);
		$reversed = array();
		foreach ($order as $key) {
			if ($indexed) {
				$reversed[] = $items[$key];
			} else { // Keep keys intact
				$reversed[$key] = $items[$key];
			}
		}
		return new Collection($reversed);
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
