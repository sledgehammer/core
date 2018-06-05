<?php

namespace SledgehammerTests\Core;

use Exception;
use Sledgehammer\Core\Curl;
use Sledgehammer\Core\Environment;

class CurlTest extends TestCase
{
    // @todo Test version >= 7.19.4 (which has CURLOPT_REDIR_PROTOCOLS)
    protected function setUp()
    {
        if (getenv('CI') && \version_compare(phpversion(), '7.2', '>=')) {
            $this->markTestSkipped('Prevent https errors on travis');
        }
    }
    public function test_single_get()
    {
        if (getenv('CI')) {
            $this->markTestSkipped('Prevent https errors on travis');
        }
        $this->assertEmptyPool();
        $response = Curl::get('https://www.bfanger.nl/');
        $this->assertSame($response->http_code, 200);
        $this->assertSame($response->effective_url, 'https://bfanger.nl/'); // forwarded to https and without "www."
    }

    public function test_async()
    {
        $this->assertEmptyPool();
        $response = Curl::get('http://jsonplaceholder.typicode.com/posts');
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
        $response = Curl::get('http://jsonplaceholder.typicode.com/posts/1');
        $paralell = Curl::get('http://jsonplaceholder.typicode.com/posts/2');
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
        $response = Curl::get('http://jsonplaceholder.typicode.com/users/1');
        $output = false;
        $response->onLoad = function ($response) use (&$output) {
            $output = $response->http_code;
        };
        $this->assertSame($output, false);
        $this->assertSame($response->getInfo(CURLINFO_HTTP_CODE), 200); // calls waitForCompletion which triggers the event
        $this->assertSame($output, 200);
    }

    public function test_curl_debugging()
    {
        $this->assertEmptyPool();
        $fp = fopen('php://memory', 'w+');
        $options = array(
            CURLOPT_STDERR => $fp,
            CURLOPT_VERBOSE => true,
        );
        $response = Curl::get('http://jsonplaceholder.typicode.com/users/1', $options);
        $this->assertSame($response->http_code, 200);
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
            Curl::download('http://jsonplaceholder.typicode.com/users/1', Environment::tmpdir().'curltest'.$i.'.downoad', [], true);
        }
        Curl::synchronize();
        $this->assertEmptyPool();
        for ($i = 0; $i < 2; ++$i) {
            unlink(Environment::tmpdir().'curltest'.$i.'.downoad');
        }
    }

    public function test_put()
    {
        $filename = Environment::tmpdir().basename(__CLASS__).'.txt';
        file_put_contents($filename, 'Curl TEST');
        $request = Curl::putFile('http://date.jsontest.com/?service=ip', $filename, [CURLOPT_FAILONERROR => false]);
        $this->assertSame(405, $request->http_code);
    }

    private function assertEmptyPool()
    {
        $this->assertFalse(isset(Curl::$requests) && count(Curl::$requests) > 0, 'Global cURL pool should be emtpy');
    }
}
