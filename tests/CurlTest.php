<?php

namespace SledgehammerTests\Core;

use Exception;
use Sledgehammer\Core\Curl;

class CurlTest extends TestCase
{
    // @todo Test version >= 7.19.4 (which has CURLOPT_REDIR_PROTOCOLS)

    public function test_single_get()
    {
        $this->assertEmptyPool();
        $response = Curl::get('http://www.bfanger.nl/');
        $this->assertEquals($response->http_code, 200);
        $this->assertEquals($response->effective_url, 'https://bfanger.nl/'); // forwarded to bfanger.nl (without "www.")
    }

    public function test_async()
    {
        $this->assertEmptyPool();
        $response = Curl::get('http://bfanger.nl/');
        $this->assertFalse($response->isComplete());
        for ($i = 0; $i < 50; ++$i) {
            usleep(10000);
            $complete = $response->isComplete();
            if ($complete) {
                break;
            }
        }
        $this->assertTrue($complete);
    }

    public function test_paralell_get()
    {
        $this->assertEmptyPool();
        $response = Curl::get('http://bfanger.nl/');
        $paralell = Curl::get('http://bfanger.nl/');
        $now = microtime(true);
        Curl::synchronize(); // wait for both request to complete
        $elapsed = microtime(true) - $now;
        $this->assertTrue(($response->total_time + $paralell->total_time) > $elapsed, 'Parallel requests are faster');
    }

    public function test_exception_on_error()
    {
        $this->assertEmptyPool();
        $response = Curl::get('noprotocol://bfanger.nl/');
        try {
            $response->getContent();
            $this->fail('Requests to an invalid protocol should throw an exception');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Requests to an invalid protocol should throw an exception');
        }
        try {
            $response->getInfo();
            $this->fail('Retrieving info on a failed tranfer should throw an exception');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Retrieving info on a failed tranfer should throw an exception');
        }
    }

    public function test_events()
    {
        $this->assertEmptyPool();
        $response = Curl::get('http://bfanger.nl/');
        $output = false;
        $response->onLoad = function ($response) use (&$output) {
            $output = $response->http_code;
        };
        $this->assertEquals($output, false);
        $this->assertEquals($response->getInfo(CURLINFO_HTTP_CODE), 200); // calls waitForCompletion which triggers the event
        $this->assertEquals($output, 200);
    }

    public function test_curl_debugging()
    {
        $this->assertEmptyPool();
        $fp = fopen('php://memory', 'w+');
        $options = array(
            CURLOPT_STDERR => $fp,
            CURLOPT_VERBOSE => true,
        );
        $response = Curl::get('http://bfanger.nl/', $options);
        $this->assertEquals($response->http_code, 200);
        rewind($fp);
        $log = stream_get_contents($fp);
        $response->on('closed', function () use ($fp) {
            fclose($fp);
        });
        $this->assertTrue(strstr($log, '< HTTP/1.1 200 OK') !== false, 'Use CURLOPT_VERBOSE should write to the CURLOPT_STDERR');
    }

    public function test_paralell_download()
    {
        $this->assertEmptyPool();
        for ($i = 0; $i < 2; ++$i) {
            Curl::download('http://bfanger.nl/', \Sledgehammer\TMP_DIR.'curltest'.$i.'.downoad', [], true);
        }
        Curl::synchronize();
        $this->assertEmptyPool();
        for ($i = 0; $i < 2; ++$i) {
            unlink(\Sledgehammer\TMP_DIR.'curltest'.$i.'.downoad');
        }
    }

    public function test_put()
    {
        $filename = \Sledgehammer\TMP_DIR.basename(__CLASS__).'.txt';
        file_put_contents($filename, 'Curl TEST');
        $request = Curl::putFile('http://bfanger.nl/', $filename);
        $this->assertEquals(200, $request->http_code);
    }

    private function assertEmptyPool()
    {
        $this->assertFalse(isset(Curl::$requests) && count(Curl::$requests) > 0, 'Global cURL pool should be emtpy');
    }
}
