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
	 * @var \Traversable
	 */
	protected $data;

	/**
	 * @param \Traversable|array $data
	 */
	function __construct($data) {
		$this->data = $data;
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
	 * Return a new Collection with a subsection of the collection based on the conditions.
	 *
	 * @example
	 * where('apple') returns the items with the value is "apple" (1D array)
	 * where('> 5')   returns the items where the value is greater-than 5
	 * where(array('id' => 4))  returns the items where the element or property 'id' is 4
	 * where(array('user->id <' => 4))  returns the items where the property 'id' of element/property 'user' is smaller-than 4
	 * where(function ($item) { return (strpos($item, 'needle') !== false); })  return the items which contain the text 'needle'
	 *
	 * @see PropertyPath::get() & compare() for supported paths and operators
	 *
	 * NOTE: The Collection class uses SledgeHammer\compare() for matching while the DatabaseCollection uses the sql WHERE part.
	 *       this may cause different behaviour. For example "ABC" == "abc" might evalute to in MySQL (depends on the chosen collation)
	 *
	 * @param mixed $conditions array|Closure|expression
	 * @return Collection
	 */
	function where($conditions) {
		if (is_object($conditions) && is_callable($conditions)) {
			$isClosure = true;
		} elseif (is_array($conditions) == false) { // Example: '<= 5' or '10'
			// Compare the items directly (1D)
			if (preg_match('/^('.COMPARE_OPERATORS.') (.*)$/', $conditions, $matches)) {
				$operator = $matches[1];
				$expectation = $matches[2];
			} else {
				$expectation = $conditions;
				$operator = '==';
			}
			$conditions = function ($value) use ($expectation, $operator) {
				return compare($value, $operator, $expectation);
			};
			$isClosure = true;
		} else {
			$isClosure = false;
			$operators = array();
			foreach ($conditions as $path => $expectation) {
				if (preg_match('/^(.*) ('.COMPARE_OPERATORS.')$/', $path, $matches)) {
					unset($conditions[$path]);
					$conditions[$matches[1]] = $expectation;
					$operators[$matches[1]] = $matches[2];
				} else {
					$operators[$path] = false;
				}
			}
		}

		$data = array();
		$counter = -1;
		foreach ($this as $key => $item) {
			$counter++;
			if ($isClosure) {
				if ($conditions($item, $key) === false) {
					continue;
				}
			} else {
				foreach ($conditions as $path => $expectation) {
					$actual = PropertyPath::get($item, $path);
					$operator = $operators[$path];
					if ($operator) {
						if (compare($actual, $operator, $expectation) === false) {
							continue 2; // Skip this entry
						}
					} elseif (equals($actual, $expectation) === false) {
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

	function skip($offset) {
		$this->dataToArray();
		return new Collection(array_slice($this->data, $offset));
	}

	function take($lenght) {
		$this->dataToArray();
		return new Collection(array_slice($this->data, 0, $lenght));
	}

	function limit($offset, $length) {
		$this->dataToArray();
		return new Collection(array_slice($this->data, $offset, $lenght));
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
		if (is_array($this->data)) {
			return count($this->data);
		}
		if ($this->data instanceof \Countable) {
			return $this->data->count();
		}
		$this->dataToArray();
		return count($this->data);
	}

	function __clone() {
		if (is_array($this->data) == false) {
			$this->data = new ArrayIterator(iterator_to_array($this->data));
//		$this->data = clone $this->data; // doesn't clone the data (in case of the ArrayIterator)
		}
	}

	// Iterator functions

	function current() {
		if (is_array($this->data)) {
			return current($this->data);
		}
		return $this->data->current();
	}

	function key() {
		if (is_array($this->data)) {
			return key($this->data);
		}
		return $this->data->key();
	}

	function next() {
		if (is_array($this->data)) {
			return next($this->data);
		}
		return $this->data->next();
	}

	function rewind() {
		if (is_array($this->data)) {
			reset($this->data);
			return;
		}
		if ($this->data instanceof \Iterator) {
			return $this->data->rewind();
		}
		if ($this->data instanceof \Traversable) {
			$this->dataToArray();
			return;
		}
		$type = gettype($this->data);
		$type = ($type == 'object') ? get_class($this->data) : $type;
		throw new \Exception(''.$type.' is not an Traversable');
	}

	function valid() {
		if (is_array($this->data)) {
			return (key($this->data) !== null);
		}
		return $this->data->valid();
	}

	// ArrayAccess functions

	function offsetExists($offset) {
		$this->dataToArray();
		return array_key_exists($offset, $this->data);
	}

	function offsetGet($offset) {
		$this->dataToArray();
		return $this->data[$offset];
	}

	function offsetSet($offset, $value) {
		$this->dataToArray();
		return $this->data[$offset] = $value;
	}

	function offsetUnset($offset) {
		$this->dataToArray();
		unset($this->data[$offset]);
	}

	/**
	 * Convert this->data to an array
	 * @return void
	 */
	protected function dataToArray() {
		if (is_array($this->data)) {
			return;
		}
		if ($this->data instanceof \Iterator) {
			$this->data = iterator_to_array($this->data);
			return;
		}
		if ($this->data instanceof \Traversable) {
			$items = array();
			foreach ($this->data as $key => $item) {
				$items[$key] = $item;
			}
			$this->data = $items;
		}
	}

}

?>
