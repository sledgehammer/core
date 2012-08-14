<?php
/**
 * Cache
 */
namespace Sledgehammer;
/**
 * A key-value caching interface.
 *
 * if (Cache::hit('key123', $value)) {
 *	return $value;
 * }
 * // slow operation ...
 * return Cache::cached('key123', $result);
 *
 */
class Cache extends Object {

	/**
	 * Check if a key has a cached value, and set the value to the $output argument
	 *
	 * @param string $key
	 * @param mixed $output
	 * @return bool
	 */
	static function hit($key, &$output) {
		$output = apc_fetch($key, $success);
		return $success;
	}

	/**
	 * Store the value in the cache and returns the value.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 */
	static function cached($key, $value, $ttl = null) {
		$success = apc_store($key, $value, $ttl);
		if ($success === false) {
			notice('Failed to store value');
		}
		return $value;
	}

}

?>
