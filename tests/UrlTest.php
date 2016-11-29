<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Url;

class UrlTest extends TestCase
{
    public function test_url_parts()
    {
        $urlString = 'http://me:mypass@example.com:8080/path/file?name=value#top';
        $url = new Url($urlString);
        $this->assertSame($url->user, 'me');
        $this->assertSame($url->pass, 'mypass');
        $this->assertSame($url->host, 'example.com');
        $this->assertSame($url->port, 8080);
        $this->assertSame($url->path, '/path/file');
        $this->assertSame($url->query, array('name' => 'value'));
        $this->assertSame($url->fragment, 'top');
        $this->assertSame($url->__toString(), $urlString, 'Generated string should contain all the parts');
    }

    public function test_path()
    {
        // escape (invalid url)
        $url1 = new Url('/filename with spaces.html');
        $this->assertSame($url1->__toString(), '/filename%20with%20spaces.html');

        // decode urlpath
        $url2 = new Url('/path%20with%20spaces.html');
        $this->assertSame($url2->path, '/path with spaces.html');
        $this->assertSame($url2->__toString(), '/path%20with%20spaces.html');
    }

    public function test_query()
    {
        // querystring notation
        $url1 = new Url('/');
        $url1->query = 'name=value';
        $this->assertSame($url1->__toString(), '/?name=value');

        // query/parameter array notation
        $url2 = new Url('/');
        $url2->query['name'] = 'value';
        $this->assertSame($url2->__toString(), '/?name=value');
    }

    public function test_folders()
    {
        $url = new Url('http://example.com');
        $this->assertSame($url->getFolders(), [], 'The root should have no folders');
        $url->path = '/test.html';
        $this->assertSame($url->getFolders(), [], 'a file in the root should have no folders');

        $url->path = '/folder1/';
        $this->assertSame($url->getFolders(), array('folder1'));

        $url->path = '/folder1/test.html';
        $this->assertSame($url->getFolders(), array('folder1'));

        $url->path = '/folder1/folder2/';
        $this->assertSame($url->getFolders(), array('folder1', 'folder2'));

        $url->path = '/folder1/folder2/test.html';
        $this->assertSame($url->getFolders(), array('folder1', 'folder2'));
    }

    public function test_filename()
    {
        $url = new Url('http://example.com');
        $this->assertSame($url->getFilename(), 'index.html');
        $url->path = '/test1.html';
        $this->assertSame($url->getFilename(), 'test1.html');

        $url->path = '/folder1/';
        $this->assertSame($url->getFilename(), 'index.html');

        $url->path = '/folder1/test2.html';
        $this->assertSame($url->getFilename(), 'test2.html');
    }
    
    public function testImmutableMethods() {
        $url = new Url('http://example.com/about.htm');
        
        $this->assertSame('https://example.com/about.htm', (string) $url->scheme('https'));
        $this->assertSame('http://example.nl/about.htm', (string) $url->host('example.nl'));
        $this->assertSame('http://example.com:8080/about.htm', (string) $url->port(8080));
        $this->assertSame('http://example.com/disclaimer.htm', (string) $url->path('disclaimer.htm'));
        $this->assertSame('http://example.com/about.htm?param1=one', (string) $url->query(['param1' => 'one']));
        $this->assertSame('http://example.com/about.htm?param2=two', (string) $url->parameter('param2', 'two'));
        $this->assertSame('http://example.com/about.htm#author', (string) $url->fragment('author'));
        
        $this->assertEquals('http://example.com/about.htm', $url, 'Original url is unchanged');
    }
    
    public function testParameter()
    {
        $emptyUrl = new Url('');
        $this->assertSame('?test=value', (string) $emptyUrl->parameter('test', 'value'));
        $this->assertSame('?test%5B0%5D=value', (string) $emptyUrl->parameter('test[]', 'value'));
        $this->assertSame('?test%5B99%5D=value', (string) $emptyUrl->parameter('test[99]', 'value'));
        
        $urlWithParam = new Url('?param=1');
        $this->assertSame('?param%5B0%5D=1&param%5B1%5D=value', (string) $urlWithParam->parameter('param[]', 'value'));

        $urlWithParams = new Url('?param[0]=123&param[4]=456');
        $this->assertSame('?param%5B0%5D=123&param%5B4%5D=456&param%5B5%5D=value', (string) $urlWithParams->parameter('param[]', 'value'));
        $this->assertSame('?param%5B0%5D=123&param%5B4%5D=value', (string) $urlWithParams->parameter('param[4]', 'value'));
        $this->assertSame('?param%5B0%5D=123&param%5B4%5D=value', (string) $urlWithParams->parameter('param', 'value', 4));
        
        $this->assertSame('?param%5B0%5D=123', (string) $urlWithParams->removeParameter('param[4]'));
        $this->assertSame('?param%5B0%5D=123', (string) $urlWithParams->removeParameter('param', 4));
        
    }
}
