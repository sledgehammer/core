<?php
/**
 * Cache
 */
namespace Sledgehammer;
/**
 * A Cache node in the Caching graph.
 * @link https://github.com/sledgehammer/sledgehammer/wiki/Caching
 *
 * Example:
 *   $cache = cache('Twitter.feeds');
 *   if ($cache->hit($value)) {
 *     return $value;
 *   }
 *   // slow operation
 *   ...
 *   return $cache->storeUntil('+15min', $result);
 */
class Cache extends Object {

	/**
	 * Unique identifier for this cache node.
	 * @var string
	 */
	private $_guid;

	/**
	 * Relative identifier for this application.
	 * @var string
	 */
	private $_path;
	private $_backend;
	private $_locked = false;

	private static $instance;

	/**
	 * Constructor
	 * @param string $identifier
	 * @param string $backend ENUM "apc" or "file"
	 */
	function __construct($identifier, $backend = null) {
		$this->_guid = sha1('Sledgehammer'.__FILE__.$identifier);
		$this->_path = $identifier;
		$this->_backend = $backend;
		if ($backend === null) {
			$this->_backend = function_exists('apc_fetch') ? 'apc' : 'file';
		}
	}

	function __destruct() {
		if ($this->_locked) {
			$this->release(); // Auto-release locks on time-outs
		}
	}

	static function getInstance() {
		if (self::$instance !== null) {
			return self::$instance;
		}
		self::$instance = new Cache('');
		return self::$instance;

	}

	/**
	 * Retrieve a value from the cache.
	 * Returns false if no valid cache entry was found.
	 *
	 * @param string|int|null $maxAge (optional) The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
	 * @return boolean
	 */
	function fetch($maxAge = null) {
		$success = $this->hit($output, $maxAge);
		if ($success) {
			return $output;
		}
		return false;
	}

	/**
	 * Retrieve the value from the cache and set it to the $ouput value.
	 * Returns true if the cache entry was valid.
	 *
	 * @param mixed $output
	 * @param string|int|null $maxAge (optional) The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
	 * @return boolean
	 */
	function hit(&$output, $maxAge = null) {
		$this->lock();
		$method = $this->_backend.'_read';
		if ($this->$method($node)) {
			$output = $node['data'];
			$this->release();
			return true;
		}
		// Maintain lock
		return false;
	}

	/**
	 * Store the value in the cache.
	 *
	 * @param string|int $expires expires  A string is parsed via strtotime(). Examples: '+5min' or '2020-01-01' int's larger than 3600 (1 hour) are interpreted as unix timestamp expire date. And int's smaller or equal to 3600 are interpreted used as ttl.
	 * @param mixed $value
	 * @return mixed
	 */
	function storeUntil($expires, $value) {
		if (is_string($expires)) {
			$expires = strtotime($expires);
		}
		if ($expires <= 3600) { // Is a ttl?
			$expires += time();
		} elseif ($expires < time()) {
			notice('Writing an expired cache entry', 'Use Cache->clear() to invalidate a cache entry');
		}
		$method = $this->_backend.'_write';
		$this->$method($value, $expires);
		$this->release();
		return $value;
	}

	/**
	 * Store in de cache without an expiration date.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	function storeForever($value) {
		$method = $this->_backend.'_write';
		$this->$method($value);
		$this->release();
		return $value;
	}

	/**
	 * Clear the cached value.
	 */
	function clear() {
		$this->lock();
		$method = $this->_backend.'_delete';
		$this->$method($value);
		$this->release();
	}

	/**
	 * Obtain a lock for this cached node
	 */
	private function lock() {
		$method = $this->_backend.'_lock';
		$this->$method();
		$this->_locked = time();
	}
	/**
	 * Release the lock for this cached node
	 * @throws \Exception
	 */
	private function release() {
		if ($this->_locked === false) {
			throw new \Exception('Must call lock() before release()');
		}
		$method = $this->_backend.'_release';
		$this->$method();
		$this->_locked = false;
	}

	/**
	 * Returns a related cache node.
	 *
	 * @param string $property
	 * @return Cache
	 */
	function __get($property) {
		$this->$property = new Cache($this->_path.'.'.$property, $this->_backend);
		return $this->$property;
	}

	function __set($property, $value) {
		if ($value instanceof Cache) {
			$this->$property = $value;
			return;
		}
		parent::__set($property, $value);
	}

	/**
	 * Check if a key has a cached value, and set the value to the $output argument.
	 *
	 * @param mixed $output
	 * @return bool
	 */
	private function apc_read(&$output) {
		$output = apc_fetch($this->_guid, $success);
		return $success;
	}

	/**
	 * Store the value in the cache.
	 *
	 * @param mixed $value
	 * @param int $expires
	 */
	private function apc_write($value, $expires = null) {
		$data = array(
			'data' => $value,
		);
		if ($this->_locked) {
			$data['updated'] = $this->_locked;
		} else {
			$data['updated'] = time();
		}
		if ($expires === null) {
			$ttl = 0; // Forever
			$data['expires'] = 'Never';
		} else {
			$ttl = $expires - $data['updated'];
			$data['expires'] = $expires;
		}
		$success = apc_store($this->_guid, $data, $ttl);
		if ($success === false) {
			warning('Failed to write value to the cache');
		}
	}

	/**
	 * Clear a cached valie
	 */
	private function apc_delete() {
		apc_delete($this->_guid);
	}

	/**
	 * Obtain a lock for this cache entry.
	 */
	private function apc_lock() {
		$ttl = intval(ini_get('max_execution_time'));
		while(true) {
			if (apc_add($this->_guid.'.lock', 'LOCKED', $ttl)) {
				break;
			}
			// @todo Implement a timeout / Exception?
			usleep(100);
		}
		$this->_locked = time();
	}

	/**
	 * Release the lock for this cached entry
	 */
	private function apc_release() {
		if (apc_delete($this->_guid.'.lock') == false) {
			warning('Release failed, was already released?');
		}
	}
}

?>
