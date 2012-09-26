<?php
/**
 * CacheTest
 */
namespace Sledgehammer;
/**
 * CacheTest
 */
class CacheTest extends TestCase {

	function test_startup() {
		mkdirs(TMP_DIR.'Cache');
		$cache = Cache::getInstance();
		$this->assertInstanceOf('Sledgehammer\Cache', $cache);
	}

	function test_cache_miss() {
		$this->cache_miss_test('file');
		$this->cache_miss_test('apc');
	}

	function test_cache_hit() {
		$this->cache_hit_test('file');
		$this->cache_hit_test('apc');
	}

	function test_cache_expires() {
		$this->cache_expires_test('file');
		$this->cache_expires_test('apc');
	}

	private function cache_miss_test($type) {
		$cache = new CacheTester(__FILE__.__FUNCTION__, $type);
		$hit = $cache->hit($output);
		$this->assertFalse($hit);
		$this->unlockAndClear($cache);
	}

	private function cache_hit_test($type) {
		$cache = new CacheTester(__FILE__.__FUNCTION__, $type);
		$cache->fetch();
		$cache->storeUntil(1, array($this, 'callback'));
		$hit = $cache->hit($output);
		$this->assertTrue($hit);
		$this->assertEquals('VALUE', $output);
		$cache->clear();
	}

	private function cache_expires_test($type) {
		$cache = new CacheTester(__FILE__.__FUNCTION__, $type);
		$cache->fetch(); // lock
		$cache->storeUntil(1, array($this, 'callback')); // store for 1 sec.
		usleep(100000); // 0.1 sec
		$this->assertEquals('VALUE', $cache->fetch(), 'Should not be expired just yet');
		sleep(2); // Wait 1.5 sec for the cache to expire.
		$hit = $cache->hit($output);
		$this->assertFalse($hit, 'Should be expired');
		$this->unlockAndClear($cache);
	}

	/**
	 * Callback for the "expensive" operation.
	 */
	function callback() {
		return 'VALUE';
	}
	private function unlockAndClear($cache) {
		$cache->storeUntil(0, array($this, 'callback'));
		$cache->clear();
	}

}

?>
