<?php
/**
 * CurlTests
 *
 */

namespace Sledgehammer;

class CurlTest extends TestCase {

	function test_single_get() {
		$this->assertEmptyPool();
		$response = cURL::get('http://www.bfanger.nl/');
		$this->assertEquals($response->http_code, 200);
		$this->assertEquals($response->effective_url, 'http://bfanger.nl/'); // forwarded to bfanger.nl (without "www.")
	}

	function test_async() {
		$this->assertEmptyPool();
		$response = cURL::get('http://bfanger.nl/');
		$this->assertFalse($response->isComplete());
		for ($i = 0; $i < 50; $i++) {
			usleep(10000);
			$complete = $response->isComplete();
			if ($complete) {
				break;
			}
		}
		$this->assertTrue($complete);
	}

	function test_paralell_get() {
		$this->assertEmptyPool();
		$response = cURL::get('http://bfanger.nl/');
		$paralell = cURL::get('http://bfanger.nl/');
		$now = microtime(true);
		cUrl::synchronize(); // wait for both request to complete
		$elapsed = microtime(true) - $now;
		$this->assertTrue(($response->total_time + $paralell->total_time) > $elapsed, 'Parallel requests are faster');
	}

	function test_exception_on_error() {
		$this->assertEmptyPool();
		$response = cURL::get('noprotocol://bfanger.nl/');
		try {
			$response->getContent();
			$this->fail('Requests to an invalid protocol should throw an exception');
		} catch (\Exception $e) {
			$this->assertTrue(true, 'Requests to an invalid protocol should throw an exception');
		}
		try {
			$response->getInfo();
			$this->fail('Retrieving info on a failed tranfer should throw an exception');
		} catch (\Exception $e) {
			$this->assertTrue(true, 'Retrieving info on a failed tranfer should throw an exception');
		}
	}

	function test_events() {
		$this->assertEmptyPool();
		$response = cURL::get('http://bfanger.nl/');
		$output = false;
		$response->onLoad = function ($response) use (&$output) {
					$output = $response->http_code;
				};
		$this->assertEquals($output, false);
		$this->assertEquals($response->getInfo(CURLINFO_HTTP_CODE), 200); // calls waitForCompletion which triggers the event
		$this->assertEquals($output, 200);
	}

	function test_curl_debugging() {
		$this->assertEmptyPool();
		$fp = fopen('php://memory', 'w+');
		$options = array(
			CURLOPT_STDERR => $fp,
			CURLOPT_VERBOSE => true,
		);
		$response = cURL::get('http://bfanger.nl/', $options);
		$this->assertEquals($response->http_code, 200);
		rewind($fp);
		$log = stream_get_contents($fp);
		fclose($fp);
		$this->assertTrue(strstr($log, '< HTTP/1.1 200 OK') !== false, 'Use CURLOPT_VERBOSE should write to the CURLOPT_STDERR');
	}

	function test_paralell_download() {
		$this->assertEmptyPool();
		for ($i = 0; $i < 2; $i++) {
			cURL::download('http://bfanger.nl/', TMP_DIR.'curltest'.$i.'.downoad', array(), true);
		}
		cURL::synchronize();
		$this->assertEmptyPool();
		for ($i = 0; $i < 2; $i++) {
			unlink(TMP_DIR.'curltest'.$i.'.downoad');
		}
	}

	private function assertEmptyPool() {
		$this->assertFalse(isset(cURL::$requests) && count(cURL::$requests) > 0, 'Global cURL pool should be emtpy');
	}

}

?>
