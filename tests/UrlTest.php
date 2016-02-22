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
        $url = new Url('/filename with spaces.html');
        $this->assertEquals($url->__toString(), '/filename%20with%20spaces.html');

        // decode urlpath
        $url = new Url('/path%20with%20spaces.html');
        $this->assertEquals($url->path, '/path with spaces.html');
        $this->assertEquals($url->__toString(), '/path%20with%20spaces.html');
    }

    public function test_query()
    {
        // querystring notation
        $url = new Url('/');
        $url->query = 'name=value';
        $this->assertEquals($url->__toString(), '/?name=value');

        // query/parameter array notation
        $url = new Url('/');
        $url->query['name'] = 'value';
        $this->assertEquals($url->__toString(), '/?name=value');
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
}
