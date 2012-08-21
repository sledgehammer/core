<?php
/**
 * Collection
 */
namespace Sledgehammer;
/**
 * Collection: Array on Steriods
 * Provides a filtering, sorting, events and other utility functions for collections.
 *
 * Inspired by LinQ and Underscore.php
 *
 * @package Core
 */
class Collection extends Observable implements \Iterator, \Countable, \ArrayAccess {

	/**
	 * The traversable the Collection class operates on.
	 * @var \Traversable Iterator / array
	 */
	protected $data;

	/**
	 * Allow listening to the events: 'changing' and 'changed'
	 * @var array
	 */
	protected $events = array(
		'changing' => array(),
		'changed' => array(),
	);

	/**
	 * Contructor
	 * @param \Traversable|array $data
	 */
	function __construct($data) {
		$this->data = $data;
	}

	/**
	 * Return a new collection where each element is a subselection of the original element.
	 * (Known as "collect" in Ruby or "pluck" in underscore.js)
	 *
	 * @param string|array|Closure $selector  Path to the variable to select. Examples: "->id", "[message]", "customer.name", array('id' => 'message_id', 'message' => 'message_text')
	 * @param string|null|false $selectKey  (optional) The path that will be used as key. false: Keep the current key, null:  create linear keys.
	 * @return Collection
	 */
	function select($selector, $selectKey = false) {
		if (is_closure($selector)) {
			$closure = $selector;
		} elseif (is_array($selector)) {
			$closure = function ($item) use ($selector) {
				$target = array();
				PropertyPath::map($item, $target, $selector);
				return $target;
			};
		} else {
			$closure = PropertyPath::compile($selector);
		}
		if ($selectKey === null) {
			$index = 0;
		}
		$items = array();
		foreach ($this as $key => $item) {
			if ($selectKey === null) {
				$key = $index;
				$index++;
			} elseif ($selectKey !== false) {
				$key = PropertyPath::get($selectKey, $item);
			}
			$items[$key] = $closure($item, $key);
		}
		return new Collection($items);
	}

	/**
	 * Returns a new collection where the key is based on a property
	 *
	 * @param string|null|Closure $selector  The path that will be used as key.
	 * @return Collection
	 */
	function selectKey($selector) {
		if ($selector === null) {
			if (is_array($this->data)) {
				return new Collection(array_values($this->data));
			}
			$items = array();
			foreach ($this as $key => $item) {
				$items[] = $item;
			}
			return new Collection($items);
		} elseif (is_closure($selector)) {
			$closure = $selector;
		} else {
			$closure = PropertyPath::compile($selector);
		}
		$items = array();
		foreach ($this as $key => $item) {
			$items[$closure($item, $key)] = $item;
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
	 * NOTE: The Collection class uses Sledgehammer\compare() for matching while the DatabaseCollection uses the sql WHERE part.
	 *       this may cause different behaviour. For example "ABC" == "abc" might evalute to in MySQL (depends on the chosen collation)
	 *
	 * @param mixed $conditions array|Closure|expression
	 * @return Collection
	 */
	function where($conditions) {
		$isClosure = is_closure($conditions);
		if ($isClosure === false) {
			if (is_array($conditions)) {
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
			} else { //'<= 5' or '10'
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
					$actual = PropertyPath::get($path, $item);
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
			if ($key != $counter) { // Is this an array?
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
	 * @param string|Closure $selector
	 * @param int $method  The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
	 * @return Collection
	 */
	function orderBy($selector, $method = SORT_REGULAR) {
		$sortOrder = array();
		$items = array();
		$indexed = true;
		$counter = 0;
		if (is_closure($selector)) {
			$closure = $selector;
		} else {
			$closure = PropertyPath::compile($selector);
		}
		// Collect values
		foreach ($this as $key => $item) {
			$items[$key] = $item;
			$sortOrder[$key] = $closure($item, $key);
			if ($key !== $counter) {
				$indexed = false;
			}
			$counter++;
		}
		// Sort the values
		if ($method === SORT_NATURAL) {
			natsort($sortOrder);
		} elseif ($method === SORT_NATURAL_CI) {
			natcasesort($sortOrder);
		} else {
			asort($sortOrder, $method);
		}
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
	 * @param string|Closure $selector
	 * @param int $method  The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
	 * @return Collection
	 */
	function orderByDescending($selector, $method = SORT_REGULAR) {
		return $this->orderBy($selector, $method)->reverse();
	}

	/**
	 * Return a new Collection without the first x items.
	 *
	 * @param int $offset
	 * @return Collection
	 */
	function skip($offset) {
		$this->dataToArray();
		return new Collection(array_slice($this->data, $offset));
	}

	/**
	 * Return a new Collection with only the first x items.
	 *
	 * @param int $limit
	 * @return Collection
	 */
	function take($limit) {
		$this->dataToArray();
		return new Collection(array_slice($this->data, 0, $limit));
	}

	/**
	 * Return a new collection in the reverse order.
	 *
	 * @return Collection
	 */
	function reverse() {
		$this->dataToArray();
		$preserveKeys = (is_indexed($this->data) === false);
		return new Collection(array_reverse($this->data, $preserveKeys));
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
	 * Returns the number of elements in the collection.
	 * @link http://php.net/manual/en/class.countable.php
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

	/**
	 * Inspect the internal query.
	 *
	 * @return mixed
	 */
	function getQuery() {
		throw new \Exception('The getQuery() method is not supported by '.get_class($this));
	}

	/**
	 * Change the internal query.
	 *
	 * @param mixed $query
	 */
	function setQuery($query) {
		throw new \Exception('The setQuery() method is not supported by '.get_class($this));
	}

	/**
	 * Clone a collection.
	 */
	function __clone() {
		$this->dataToArray();
	}

	/**
	 * Return the current element
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed
	 */
	function current() {
		if (is_array($this->data)) {
			return current($this->data);
		}
		return $this->data->current();
	}

	/**
	 * Return the current key/index.
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return int|string
	 */
	function key() {
		if (is_array($this->data)) {
			return key($this->data);
		}
		return $this->data->key();
	}

	/**
	 * Move forward to next element.
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void
	 */
	function next() {
		if (is_array($this->data)) {
			return next($this->data);
		}
		return $this->data->next();
	}

	/**
	 * Rewind the Iterator to the first element.
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void
	 */
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
		$typeOrClass = ($type === 'object') ? get_class($this->data) : $type;
		throw new \Exception(''.$typeOrClass.' is not an Traversable');
	}

	/**
	 * Checks if current position is valid.
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return bool
	 */
	function valid() {
		if (is_array($this->data)) {
			return (key($this->data) !== null);
		}
		return $this->data->valid();
	}

	/**
	 * Whether a offset exists.
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param int|string $offset
	 * @return bool
	 */
	function offsetExists($offset) {
		$this->dataToArray();
		return array_key_exists($offset, $this->data);
	}

	/**
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param int|string $offset
	 * @return mixed
	 */
	function offsetGet($offset) {
		$this->dataToArray();
		return $this->data[$offset];
	}

	/**
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param int|string $offset
	 * @param mixed $value
	 * @return void
	 */
	function offsetSet($offset, $value) {
		$this->dataToArray();
		$this->trigger('changing', $this);
		if ($offset === null) {
			$this->data[] = $value;
		} else {
			$this->data[$offset] = $value;
		}
		$this->trigger('changed', $this);
	}

	/**
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param int string $offset
	 * @return void
	 */
	function offsetUnset($offset) {
		$this->dataToArray();
		$this->trigger('changing', $this);
		unset($this->data[$offset]);
		$this->trigger('changed', $this);
	}

	/**
	 * Convert $this->data to an array.
	 *
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
