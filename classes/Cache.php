<?php
/**
 * Cache
 */
namespace Sledgehammer;
/**
 * A Cache node in the Caching graph.
 * @link https://github.com/sledgehammer/sledgehammer/wiki/Caching
 *
 * API:
 *   return cache('Twitter.feeds', '+15min', function () {
 *     // slow operation
 *     return $outcome;
 *   });
 * or
 *   Cache::rootNode()->Twitter->feeds->value(array('expires' => '15min'), function () {
 *     // slow operation
 *     return $outcome;
 *   });
 *
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
	private $_identifier;

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
	 * File handle for the file-backend.
	 * @var resource|null
	 */
	private $_file;

	/**
	 * Constructor
	 * @param string $identifier
	 * @param string $backend ENUM "apc" or "file"
	 */
	protected function __construct($identifier, $backend) {
		$this->_guid = sha1('Sledgehammer'.__FILE__.$identifier);
		$this->_identifier = $identifier;
		$this->_backend = $backend;
	}

	function __destruct() {
		if ($this->_locked) {
			notice('Cache is locked, releasing: "'.$this->_identifier.'"');
			$this->release(); // Auto-release locks on time-outs
		}
	}

	/**
	 * Return the rootnode for the application.
	 * @return Cache
	 */
	static function rootNode() {
		if (self::$instance !== null) {
			return self::$instance;
		}
		$backend = function_exists('apc_fetch') ? 'apc' : 'file';
		$backend = 'file';
		self::$instance = new Cache('', $backend);
		if ($backend === 'file') {
			mkdirs(TMP_DIR.'Cache');
			if (rand(1, 25) === 1) { // Run gc only once in every X requests.
				self::file_gc();
			}
		}
		return self::$instance;
	}

	/**
	 * Returns the cached value when valid cache entry was found. otherwise retrieves the value via the $closure, stores it in the cache and returns it.
	 *
	 * @param string|int|array $options A string or int is interpreted as a 'expires' option.
	 * array(
	 *   'max_age' => int|string // The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
	 *   'expires' => int|string, // A string is parsed via strtotime(). Examples: '+5min' or '2020-01-01' int's larger than 3600 (1 hour) are interpreted as unix timestamp expire date. And int's smaller or equal to 3600 are interpreted used as ttl.
	 *   'forever' => bool, // Default false (When true no )
	 *   'lock' => (bool) // Default true, Prevents a cache stampede (http://en.wikipedia.org/wiki/Cache_stampede)
	 * )
	 * @param callable $closure  The method to retrieve/calculate the value.
	 * @return mixed
	 */
	function value($options, $closure) {
		// Convert option to an array
		if (is_array($options) === false) {
			$options = array(
				'expires' => $options
			);
		}
		// Merge default options
		$default = array(
			'expires' => false,
			'forever' => false,
			'max_age' => false,
			'lock' => true,
		);
		$options = array_merge($default, $options);
		if (count($options) !== count($default)) {
			notice('Option: '.quoted_human_implode(' and ', array_keys(array_diff($options, $default))).' is invalid');
		}
		if ($options['expires'] === false && $options['forever'] === false && $options['max_age'] === false) {
			throw new InfoException('Invalid options: "expires",  "max_age" or "forever" must be set', $options);
		}
		if ($options['forever']  && $options['expires'] !== false) {
			throw new InfoException('Invalid options: "expires" and "forever" can\'t both be set', $options);
		}
		// Read cache
		if ($options['lock']) {
			$this->lock();
		}
		// Read value from cache
		try {
			$hit = $this->read($value, $options['max_age']);
		} catch (\Exception $e) {
			// Reading cache failed
			if ($options['lock']) {
				$this->release();
			}
			throw $e;
		}
		if ($hit) {
			if ($options['lock']) {
				$this->release();
				return $value;
			}
		}
		// Miss, obtain value.
		try {
			$value = call_user_func($closure);
		} catch(\Exception $e) {
			if ($options['lock']) {
				$this->release();
			}
			throw $e;
		}
		// Store value
		$this->write($value, $options['expires']);
		if ($options['lock']) {
			$this->release();
		}
		return $value;
	}

	/**
	 * Retrieve the value from the cache and set it to the $output value.
	 * Returns true if the cache entry was valid.
	 *
	 * @param array $node Cached data + metadata
	 * @param string|int|null $maxAge (optional) The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
	 * @return bool
	 */
	protected function read(&$output, $maxAge = false) {
		$method = $this->_backend.'_read';
		$success = $this->$method($node);
		if ($success === false) {
			return false;
		}
		if ($maxAge !== false) {
			if (is_string($maxAge)) {
				$maxAge = strtotime($maxAge);
			}
			if ($maxAge <= 3600) { // Is maxAge a ttl?
				$maxAge = time() - $maxAge;
			}
			if ($maxAge > time()) {
				notice('maxAge is '.($maxAge - time()).' seconds in the future', 'Use Cache->clear() to invalidate a cache entry');
				return false;
			}
			if ($node['updated'] < $maxAge) { // Older than the maximum age?
				return false;
			}
		}
		if ($node['expires'] != 0 && $node['expires'] <= time()) { // Is expired?
			return false;
		}
		$output = $node['data'];
		return true; // Cache is valid
	}

	/**
	 * Store the value in the cache.
	 *
	 * @param mixed $value  The value
	 * @param string|int $expires expires  A string is parsed via strtotime(). Examples: '+5min' or '2020-01-01' int's larger than 3600 (1 hour) are interpreted as unix timestamp expire date. And int's smaller or equal to 3600 are interpreted used as ttl.
	 */
	protected function write($value, $expires = false) {
		if ($expires !== false) {
			if (is_string($expires)) {
				$expires = strtotime($expires);
			}
			if ($expires <= 3600) { // Is a ttl?
				$expires += time();
			} elseif ($expires < time()) {
				notice('Writing an expired cache entry', 'Use Cache->clear() to invalidate a cache entry');
			}
		}
		$method = $this->_backend.'_write';
		$this->$method($value, $expires);
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
			$this->_nodes[$property] = new Cache($this->_identifier.'.'.$property, $this->_backend);
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
		if ($expires === false) {
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
		if ($this->_file !== null) {
			throw new \Exception('Cache already has an open filepointer');
		}
		$this->_file = fopen(TMP_DIR.'Cache/'.$this->_guid, 'c+');
		if ($this->_file === false) {
			throw new \Exception('Creating lockfile failed');
		}
		while (true) {
			if (flock($this->_file, LOCK_EX)) {
				break;
			}
			usleep(100);
		}
	}

	private function file_release() {
		if ($this->_file === null) {
			throw new \Exception('Cache doesn\'t have an open filepointer');
		}
		flock($this->_file, LOCK_UN);
		fclose($this->_file);
		$this->_file = null;
	}

	private function file_read(&$output) {
		$expires = stream_get_line($this->_file, 1024, "\n");
		if ($expires == false) { // Entry is empty?
			return false;
		}
		$updated = stream_get_line($this->_file, 1024, "\n");
		$output = array(
			'expires' => intval(substr($expires, 9)), // "Expires: " = 9
			'updated' => intval(substr($updated, 9)), // "Updated: " = 9
			'data' => unserialize(stream_get_contents($this->_file)),
		);
		return true;
	}

	private function file_write($value, $expires = null) {
		if ($this->_file === null) {
			throw new \Exception('Cache doesn\'t have an open filepointer');
		}
		if ($expires === false) {
			$expires = 'Never';
		}
		fseek($this->_file, 0);
		ftruncate($this->_file, 0);
		fwrite($this->_file, 'Expires: '.$expires."\nUpdated: ".time()."\n");
		fwrite($this->_file, serialize($value));
		fflush($this->_file);
	}

	private function file_delete() {
		if ($this->_file) {
			throw new \Exception('Can\'t clear a locked file');
		}
		unlink(TMP_DIR.'Cache/'.$this->_guid);
	}

	/**
	 * Cleanup expired cache entries
	 */
	private static function file_gc() {
		$dir = new \DirectoryIterator(TMP_DIR.'Cache');
		$files = array();
		foreach ($dir as $entry) {
			if ($entry->isFile()) {
				$files[] = $entry->getFilename();
			}
		}
		shuffle($files);
		$files = array_slice($files, 0, ceil(count($files) / 10)); // Take 10% of the files
		$cache = new Cache('GC', 'file');
		foreach ($files as $id) {
			$cache->_guid = $id;
			$cache->_file = fopen(TMP_DIR.'Cache/'.$id, 'r');
			$hit = $cache->read($output);
			fclose($cache->_file);
			$cache->_file = null;
			if ($hit === false) { // Expired?
				$cache->clear();
			}
		}
	}

	function offsetGet($offset) {
		if (empty($this->_nodes['['.$offset.']'])) {
			$this->_nodes['['.$offset.']'] = new Cache($this->_identifier.'['.$offset.']', $this->_backend);
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
		throw new \Exception('Cache is not compatible with serialize');
	}
}

?>
