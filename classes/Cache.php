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
 *   return $cache->storeUntil('+15min', function () {
 *     // slow operation
 *     return $outcome;
 *   });
 */
class Cache extends Object implements \ArrayAccess {

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

	/**
	 * Connected nodes.
	 * @var array|Cache
	 */
	private $_nodes = array();

	/**
	 * ENUM 'apc' or 'file'
	 * @var string
	 */
	private $_backend;

	/**
	 * Timestamp of when lock took place or false if not locked.
	 * @var false|int
	 */
	private $_locked = false;

	/**
	 * The application root node.
	 * @var Cache
	 */
	private static $instance;

	/**
	 * Property that has a backend specific contents.
	 * @var mixed
	 */
	private $_config;

	/**
	 * Constructor
	 * @param string $identifier
	 * @param string $backend ENUM "apc" or "file"
	 */
	protected function __construct($identifier, $backend) {
		$this->_guid = sha1('Sledgehammer'.__FILE__.$identifier);
		$this->_path = $identifier;
		$this->_backend = $backend;
	}

	function __destruct() {
		if ($this->_locked) {
			notice('Cache is locked, releasing...');
			$this->release(); // Auto-release locks on time-outs
		}
	}

	static function getInstance() {
		if (self::$instance !== null) {
			return self::$instance;
		}
		mkdirs(TMP_DIR.'Cache');
		$backend = function_exists('apc_fetch') ? 'apc' : 'file';
		self::$instance = new Cache('', $backend);
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
	 * Maintains a lock when the cache is invalid, its expected to store a new value which auto-releases the lock.
	 *
	 * @param mixed $output
	 * @param string|int|null $maxAge (optional) The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
	 * @return boolean
	 */
	function hit(&$output, $maxAge = null) {
		$this->lock();
		$method = $this->_backend.'_read';
		$success = $this->$method($node);
		if ($success === false) {
			return false;
		}
		if ($maxAge !== null) {
			if (is_string($maxAge)) {
				$maxAge = strtotime($maxAge);
			}
			if ($maxAge <= 3600) { // Is a ttl?
				$maxAge = time() - $maxAge;
			}
			if ($node['updated'] < $maxAge) { // Cache is older than the maximum age?
				return false;
			}
		}
		if ($node['expires'] != 0 && $node['expires'] <= time()) { // has expired?
			return false;
		}
		// Cache hit!
		$output = $node['data'];
		$this->release();
		return true;
	}

	/**
	 * Store the value in the cache.
	 *
	 * @param string|int $expires expires  A string is parsed via strtotime(). Examples: '+5min' or '2020-01-01' int's larger than 3600 (1 hour) are interpreted as unix timestamp expire date. And int's smaller or equal to 3600 are interpreted used as ttl.
	 * @param callable $closure
	 * @return mixed
	 */
	function storeUntil($expires, $closure) {
		if ($this->_locked === false) {
			throw new \Exception('Cache wasn\'t locked, call hit() or fetch() before storeUntil()');
		}
		if (is_string($expires)) {
			$expires = strtotime($expires);
		}
		if ($expires <= 3600) { // Is a ttl?
			$expires += time();
		} elseif ($expires < time()) {
			notice('Writing an expired cache entry', 'Use Cache->clear() to invalidate a cache entry');
		}
		try {
			$value = call_user_func($closure);
		} catch(\Exception $e) {
			$this->release();
			throw $e;
		}
		$method = $this->_backend.'_write';
		$this->$method($value, $expires);
		$this->release();
		return $value;
	}

	/**
	 * Store in de cache without an expiration date.
	 *
	 * @param callable $closure
	 * @return mixed
	 */
	function storeForever($closure) {
		if ($this->_locked === false) {
			throw new \Exception('Cache wasn\'t locked, call hit() or fetch() before storeForever()');
		}
		try {
			$value = call_user_func($closure);
		} catch(\Exception $e) {
			$this->release();
			throw $e;
		}
		$method = $this->_backend.'_write';
		$this->$method($closure);
		$this->release();
		return $value;
	}

	/**
	 * Clear the cached value.
	 */
	function clear() {
		$method = $this->_backend.'_delete';
		$this->$method();
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
		if (empty($this->_nodes[$property])) {
			$this->_nodes[$property] = new Cache($this->_path.'.'.$property, $this->_backend);
		}
		return $this->_nodes[$property];
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
		while (true) {
			if (apc_add($this->_guid.'.lock', 'LOCKED', $ttl)) {
				break;
			}
			// @todo Implement a timeout / Exception?
			usleep(100);
		}
	}

	/**
	 * Release the lock for this cached entry
	 */
	private function apc_release() {
		if (apc_delete($this->_guid.'.lock') == false) {
			warning('apc_delete() failed, was already released?');
		}
	}

	private function file_lock() {
		if ($this->_config !== null) {
			throw new \Exception('Cache already has an open filepointer');
		}
		$this->_config = fopen(TMP_DIR.'Cache/'.$this->_guid, 'c+');
		if ($this->_config === false) {
			throw new \Exception('Creating lockfile failed');
		}
		while (true) {
			if (flock($this->_config, LOCK_EX)) {
				break;
			}
			usleep(100);
		}
	}

	private function file_release() {
		if ($this->_config === null) {
			throw new \Exception('Cache doesn\'t have an open filepointer');
		}
		flock($this->_config, LOCK_UN);
		fclose($this->_config);
		$this->_config = null;
	}

	private function file_read(&$output) {
		$expires = stream_get_line($this->_config, 1024, "\n");
		if ($expires == false) { // Entry is empty?
			return false;
		}
		$updated = stream_get_line($this->_config, 1024, "\n");
		$output = array(
			'expires' => intval(substr($expires, 9)), // "Expires: " = 9
			'updated' => intval(substr($updated, 9)), // "Updated: " = 9
			'data' => unserialize(stream_get_contents($this->_config)),
		);
		return true;
	}

	private function file_write($value, $expires = null) {
		if ($this->_config === null) {
			throw new \Exception('Cache doesn\'t have an open filepointer');
		}
		if ($expires === null) {
			$expires = 'Never';
		}
		fseek($this->_config, 0);
		ftruncate($this->_config, 0);
		fwrite($this->_config, 'Expires: '.$expires."\nUpdated: ".time()."\n");
		fwrite($this->_config, serialize($value));
		fflush($this->_config);
	}

	private function file_delete() {
		if ($this->_config) {
			throw new \Exception('Can\'t clear a locked file');
		}
		unlink(TMP_DIR.'Cache/'.$this->_guid);
	}

	function offsetGet($offset) {
		if (empty($this->_nodes['['.$offset.']'])) {
			$this->_nodes['['.$offset.']'] = new Cache($this->_path.'['.$offset.']', $this->_backend);
		}
		return $this->_nodes['['.$offset.']'];
	}

	function offsetExists($offset) {
		throw new \Exception('Not implemented');
	}

	function offsetSet($offset, $value) {
		throw new \Exception('Not implemented');
	}

	function offsetUnset($offset) {
		throw new \Exception('Not implemented');
	}

	function __sleep() {
		throw new \Exception('Cache not compatible with serialize');
	}

}

?>
