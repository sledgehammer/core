<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Url;

class UrlTest extends TestCase
{
    public function test_url_parts()
    {
        $urlString = 'http://me:mypass@example.com:8080/path/file?name=value#top';
        $url = new Url($urlString);
        $this->assertEquals($url->user, 'me');
        $this->assertEquals($url->pass, 'mypass');
        $this->assertEquals($url->host, 'example.com');
        $this->assertEquals($url->port, 8080);
        $this->assertEquals($url->path, '/path/file');
        $this->assertEquals($url->query, array('name' => 'value'));
        $this->assertEquals($url->fragment, 'top');
        $this->assertEquals($url->__toString(), $urlString, 'Generated string should contain all the parts');
    }

    public function test_path()
    {
        // escape (invalid url)
        $url1 = new Url('/filename with spaces.html');
        $this->assertEquals($url1->__toString(), '/filename%20with%20spaces.html');

        // decode urlpath
        $url2 = new Url('/path%20with%20spaces.html');
        $this->assertEquals($url2->path, '/path with spaces.html');
        $this->assertEquals($url2->__toString(), '/path%20with%20spaces.html');
    }

    public function test_query()
    {
        // querystring notation
        $url1 = new Url('/');
        $url1->query = 'name=value';
        $this->assertEquals($url1->__toString(), '/?name=value');

        // query/parameter array notation
        $url2 = new Url('/');
        $url2->query['name'] = 'value';
        $this->assertEquals($url2->__toString(), '/?name=value');
    }

    public function test_folders()
    {
        $url = new Url('http://example.com');
        $this->assertEquals($url->getFolders(), [], 'The root should have no folders');
        $url->path = '/test.html';
        $this->assertEquals($url->getFolders(), [], 'a file in the root should have no folders');

        $url->path = '/folder1/';
        $this->assertEquals($url->getFolders(), array('folder1'));

        $url->path = '/folder1/test.html';
        $this->assertEquals($url->getFolders(), array('folder1'));

        $url->path = '/folder1/folder2/';
        $this->assertEquals($url->getFolders(), array('folder1', 'folder2'));

        $url->path = '/folder1/folder2/test.html';
        $this->assertEquals($url->getFolders(), array('folder1', 'folder2'));
    }

    public function test_filename()
    {
        $url = new Url('http://example.com');
        $this->assertEquals($url->getFilename(), 'index.html');
        $url->path = '/test1.html';
        $this->assertEquals($url->getFilename(), 'test1.html');

        $url->path = '/folder1/';
        $this->assertEquals($url->getFilename(), 'index.html');

        $url->path = '/folder1/test2.html';
        $this->assertEquals($url->getFilename(), 'test2.html');
    }
    
    public function testImmutableMethods() {
        $url = new Url('http://example.com/about.htm');
        
        $this->assertEquals('https://example.com/about.htm', (string) $url->scheme('https'));
        $this->assertEquals('http://example.nl/about.htm', (string) $url->host('example.nl'));
        $this->assertEquals('http://example.com:8080/about.htm', (string) $url->port(8080));
        $this->assertEquals('http://example.com/disclaimer.htm', (string) $url->path('disclaimer.htm'));
        $this->assertEquals('http://example.com/about.htm?param1=one', (string) $url->query(['param1' => 'one']));
        $this->assertEquals('http://example.com/about.htm?param2=two', (string) $url->parameter('param2', 'two'));
        $this->assertEquals('http://example.com/about.htm#author', (string) $url->fragment('author'));
        
        $this->assertEquals('http://example.com/about.htm', $url, 'Original url is unchanged');
    }
    
    public function testParameter()
    {
        $emptyUrl = new Url('');
        $this->assertEquals('?test=value', (string) $emptyUrl->parameter('test', 'value'));
        $this->assertEquals('?test%5B0%5D=value', (string) $emptyUrl->parameter('test[]', 'value'));
        $this->assertEquals('?test%5B99%5D=value', (string) $emptyUrl->parameter('test[99]', 'value'));
        
        $urlWithParam = new Url('?param=1');
        $this->assertEquals('?param%5B0%5D=1&param%5B1%5D=value', (string) $urlWithParam->parameter('param[]', 'value'));

        $urlWithParams = new Url('?param[0]=123&param[4]=456');
        $this->assertEquals('?param%5B0%5D=123&param%5B4%5D=456&param%5B5%5D=value', (string) $urlWithParams->parameter('param[]', 'value'));
        $this->assertEquals('?param%5B0%5D=123&param%5B4%5D=value', (string) $urlWithParams->parameter('param[4]', 'value'));
        $this->assertEquals('?param%5B0%5D=123&param%5B4%5D=value', (string) $urlWithParams->parameter('param', 'value', 4));
        
        $this->assertEquals('?param%5B0%5D=123', (string) $urlWithParams->removeParameter('param[4]'));
        $this->assertEquals('?param%5B0%5D=123', (string) $urlWithParams->removeParameter('param', 4));
        
    }
}
