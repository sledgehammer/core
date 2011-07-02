<?php
/**
 * Controleer de werking van het URL object
 * 
 * @package Core
 */
namespace SledgeHammer;
class URLTests extends \UnitTestCase {

	function test_url_parts() {
		$urlString = 'http://me:mypass@example.com:8080/path/file?name=value#top';
		$url = new URL($urlString);
		$this->assertEqual($url->user, 'me');
		$this->assertEqual($url->pass, 'mypass');
		$this->assertEqual($url->host, 'example.com');
		$this->assertEqual($url->port, 8080);
		$this->assertEqual($url->path, '/path/file');
		$this->assertEqual($url->query, array('name' => 'value'));
		$this->assertEqual($url->fragment, 'top');
		$this->assertEqual($url->__toString(), $urlString, 'Generated string should contain all the parts');
	}
	
	function test_path() {
		// escape (invalid url)
		$url = new URL('/filename with spaces.html');
		$this->assertEqual($url->__toString(), '/filename%20with%20spaces.html');
		
		// decode urlpath
		$url = new URL('/path%20with%20spaces.html');
		$this->assertEqual($url->path, '/path with spaces.html');
		$this->assertEqual($url->__toString(), '/path%20with%20spaces.html');
	}
	function test_query() {
		// querystring notation
		$url = new URL('/');
		$url->query = 'name=value';
		$this->assertEqual($url->__toString(), '/?name=value');
		
		// query/parameter array notation
		$url = new URL('/');
		$url->query['name'] = 'value';
		$this->assertEqual($url->__toString(), '/?name=value');
	}
	
	function test_folders() {
		$url = new URL('http://example.com');
		$this->assertEqual($url->getFolders(), array(), 'The root should have no folders');
		$url->path = '/test.html';
		$this->assertEqual($url->getFolders(), array(), 'a file in the root should have no folders');
		
		$url->path = '/folder1/';
		$this->assertEqual($url->getFolders(), array('folder1'));
		
		$url->path = '/folder1/test.html';
		$this->assertEqual($url->getFolders(), array('folder1'));
		
		$url->path = '/folder1/folder2/';
		$this->assertEqual($url->getFolders(), array('folder1', 'folder2'));
		
		$url->path = '/folder1/folder2/test.html';
		$this->assertEqual($url->getFolders(), array('folder1', 'folder2'));
	}
	
	function test_filename() {
		$url = new URL('http://example.com');
		$this->assertEqual($url->getFilename(), 'index.html');
		$url->path = '/test1.html';
		$this->assertEqual($url->getFilename(), 'test1.html');
		
		$url->path = '/folder1/';
		$this->assertEqual($url->getFilename(), 'index.html');
		
		$url->path = '/folder1/test2.html';
		$this->assertEqual($url->getFilename(), 'test2.html');
	}
}