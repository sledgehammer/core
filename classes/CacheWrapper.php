<?php
/**
 * CacheWrapper
 */
namespace Sledgehammer;
/**
 * Wrap an object inside a CacheWrapper to cache all method calls and properties.
 */
class CacheWrapper extends Object implements \ArrayAccess, \Iterator {

	private $object;
	private $keyPrefix;
	private $ttl;
	private $iterator;

	/**
	 * Constructor
	 * @param string $key  Unique key for the cache
	 * @param object $object
	 * @param int ttl  Time-to-Live in seconds
	 */
	function __construct($key, $object, $ttl = 0) {
		$this->keyPrefix = $key;
		$this->object = $object;
		$this->ttl = $ttl;
	}

	function __get($property) {
		$key = $this->keyPrefix.'->'.$property;
		if (Cache::hit($key, $value)) {
			return $value;
		}
		$value = $this->object->$property;
		if (is_object($value)) {
			$value = new CacheWrapper($key, $value, $this->ttl);
		}
		return Cache::cached($key, $value, $this->ttl);
	}

	function __call($method, $arguments) {
		$key = $this->keyPrefix.'->'.$method.'(';
		foreach ($arguments as $i => $argument) {
			if ($i !== 0) {
				$key .= ', ';
			}
			if (is_array($argument) || is_object($argument)) {
				$key .= serialize($argument);
			} elseif (is_string($argument)) {
				$key .= "'".$argument."'";
			} else {
				$key .= $argument;
			}
		}
		$key .= ')';
		if (Cache::hit($key, $value)) {
			return $value;
		}
		$value = call_user_func_array(array($this->object, $method), $arguments);
		if (is_object($value)) {
			$value = new CacheWrapper($key, $value, $this->ttl);
		}
		return Cache::cached($key, $value, $this->ttl);
	}

	public function offsetExists($offset) {
		return $this->__call('offsetExists', array($offset));
	}

	public function offsetGet($offset) {
		return $this->__call('offsetGet', array($offset));
	}

	public function offsetSet($offset, $value) {
		throw new \Exception('Not supported for a Cached object');
//		return $this->__call('offsetSet', array($offset, $value));

	}

	public function offsetUnset($offset) {
		throw new \Exception('Not supported for a Cached object');
		return $this->__call('offsetUnset', array($offset));

	}

	public function current() {
		return $this->cachedIterator()->current();
	}

	public function key() {
		return $this->cachedIterator()->key();
	}

	public function next() {
		return $this->cachedIterator()->next();
	}

	public function rewind() {
		return $this->cachedIterator()->rewind();
	}

	public function valid() {
		return $this->cachedIterator()->valid();
	}

	/**
	 * @return \Iterator
	 */
	private function cachedIterator() {
		if ($this->iterator !== null) {
			return $this->iterator;
		}
		if (Cache::hit($this->keyPrefix.'#Iterator', $array) === false) {
			$array = Cache::cached($this->keyPrefix.'#Iterator', iterator_to_array($this->object), $this->ttl);
		}
		$this->iterator = new \ArrayIterator($array);
		return $this->iterator;
	}

}

?>
