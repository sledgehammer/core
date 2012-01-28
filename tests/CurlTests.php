<?php
/**
 * CollectionTests
 *
 */
namespace SledgeHammer;

class CurlTests extends TestCase {

	function test_single_get() {
		$response = cURL::get('http://www.bfanger.nl/');
		$this->assertEqual($response->http_code, 200);
		$this->assertEqual($response->effective_url, 'http://bfanger.nl/'); // forwarded to bfanger.nl (without "www.")
	}

	function test_async() {
		$now = microtime(true);
		$response = cURL::get('http://bfanger.nl/');
		$this->assertFalse($response->isComplete());
		for ($i = 0; $i < 10; $i++) {
			sleep(1);
			$complete = $response->isComplete();
			if ($complete) {
				break;
			}
		}
		$this->assertTrue($complete);
	}

	function test_paralell_get() {
		$response = cURL::get('http://bfanger.nl/');
		$paralell = cURL::get('http://bfanger.nl/');
		$now = microtime(true);
		$this->assertEqual($response->http_code, 200);
		$this->assertEqual($paralell->http_code, 200);
		$elapsed = microtime(true) - $now;
		$this->assertTrue(($response->total_time + $paralell->total_time) > $elapsed, 'Parallel downloads are faster');
	}


}

?>
