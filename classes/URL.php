<?php
/**
 * Description of URL
 *
 * @author bob
 */
class URL extends Object {
	
	public
		$scheme,
		$host,
		$port,
		$path,
		$query;
	
	public
		$user,
		$password,
		$fragment;
	
	public
		$folders,
		$filename;

	/**
	 * @param NULL|string $url De url om te parsen, bij NULL wordt de huidige url gebruikt
	 */
	function __construct($url) {
		unset($this->folders, $this->filename);
		$info = parse_url($url);
		if ($info === false) {
			throw new Exception('Invalid url parameter: "'.$url.'"');
		}
		set_object_vars($this, $info);
	}
	
	function __toString() {
		$url = '';
		if ($this->scheme !== null && $this->host !== null) {
			$url .= $this->scheme.'://'.$this->host;
			if ($this->port) {
				$url .= ':'.$this->port;
			}
		}
		$url .= $this->path;
		if ($this->query !== null) {
			$url .= '?'.$this->query;

		}
		return $url;
	}
	function __get($property) {
		$path = rawurldecode($this->path); // "%20" omzetten naar " " e.d.
				
		switch ($property) {
			
			case 'filename':
				if (substr($path, -1) == '/') { // Gaat het om een map?
					return 'index.html';
				}
				return basename($path);

			case 'folders':
				if($path == '/') {
					return array();
				}
				if (substr($path, -1) == '/') { // Gaat het om een map?
					return explode('/', substr($path, 1, -1));
				}
				// Het gaat om een bestand (in een map)
				return explode('/', substr(dirname($path), 1)); 
				
			default:
				return parent::__get($property);
		}
	}
	
	/**
	 * Gets the current url
	 * @return URL
	 */
	static function getCurrentURL() {
		$url = 'http';
		if (array_value($_SERVER, 'HTTPS') == 'on') {
			$url .= 's';
		}
		$url .= '://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		return new URL($url);
	}

	/**
	 * Vraag een specifiek deel van url op of de lijst met onderdelen
	 *
	 * @param NULL|string $part Het deel van de url welke gevraagd wordt, bij NULL krijg je een array met alle gegevens
	 * @param NULL|string $url De url om te parsen, bij NULL wordt de huidige url gebruikt
	 * @return mixed
	 */
	static function info($part, $url = null) {
		deprecated('Use the OOP "new URL()" syntax');
		if ($part === null) {
			error('No longer supported');
		}
		$url = new URL($url);
		return $url->$part;
	}
	
	static function uri() {
		deprecated('Use the OOP "new URL()" syntax');
		$url = new URL();
		return $url->__toString();
	}
	
	static function extract_path() {
		deprecated('Use the OOP "new URL()" syntax');
		$url = new URL();
		$folders = explode('/', $url->path);
		
		// Default waarden instellen.
		$filename = 'index.html';
		$folders = array();
		$path = URL::info('path', $_SERVER['REQUEST_URI']);
		if ($path === false) { // Kon de url niet geparsed worden? 
			return false;
		}
		$path = rawurldecode($path); // "%20" omzetten naar " " e.d.
		if($path != '/') {
			if (substr($path, -1) == '/') { // Gaat het om een map?
				$folders = explode('/', substr($path, 1, -1)); 
			} else { // Het gaat om een bestand (in een map)
				$folders = explode('/', substr($path, 1)); 
				$filename =  array_pop($folders);
			}
		}
		return array(
			'filename' => $filename,
			'folders' => $folders
		);
	}
	
		/**
	 * Multi-functionele functie om parameters op te vragen en toe te voegen
	 *
	 * URL:parameters(); vraagt de huidige parameters op. ($_GET)
	 * URL:parameters("naam['test']=1234"); of URL::parameters(array('naam'=>array('test'=>1234))); voegt deze parameter toe aan de huidige parameter array.
	 * URL:parameter("bla=true", 'x=y'); voegt 2 parameter 'arrays' samen
	 *
	 * @param array $append De parameter die toegevoegd moet worden
	 * @param mixed $stack: De url of array waarde parameters waaraan toegevoegd moet worden, bij NULL worden de huidige $_GET parameters gebruikt
	 * @return array
	 */
	static function parameters($append = array(), $stack = NULL) {
		deprecated('Maar nog geen alternatief beschikbaar');
		if ($stack === NULL) { // Huidige parameters opvragen
			$stack = $_GET;
		} elseif (is_string($stack)) { // Is er geen array, maar een query string meegegeven?
			parse_str($stack, $stack);
		}
		if (is_string($append)) {
			parse_str($append, $append);
		}
		return array_merge(array_diff_key($stack, $append), $append); // De array kan gebruikt worden in een http_build_query()
	}
	
		/**
	 * Een sub-domein opvragen van een domein
	 * 
	 * @param int $index Bepaald welke subdomein van de subdomeinen er wordt opgevraagd. 0 = eerste subdomein van links, -1 =  eerste subdomein van rechts
	 * @param NULL|string $uri de uri waarvan het subdomein opgevraagd moet worden
	 * @return string
	 */
	static function subdomain($index = -1, $uri = NULL) {
		deprecated('Maar nog geen alternatief beschikbaar');

		if ($uri === NULL) {
			$uri = URL::info('host');
		}
		$parts = explode('.', $uri);
		$count = count($parts);
		if ($index < 0) { // is $index negatief?
			$index = $count - 2 + $index; // van links naar rechts
		} elseif ($index + 2 >= $count) { // is $index groter dan aantal subdomeinen? 
			return '';
		}
		$subdomain = @$parts[$index];
		return ($subdomain === NULL) ? '' : $subdomain;
	}
	
	/**
	 * 
	 */
	static function domain() {
		deprecated('Maar nog geen alternatief beschikbaar');

		$hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
		$regexDomain = '/[a-z0-9]+([a-z]{2}){0,1}.[a-z]{2,4}$/i';
		if (preg_match($regexDomain, $hostname, $match)) { // Zit er een domeinnaam in de hostname? 
			return $match[0];
		}
		return 'example.com'; 	
	}

}

?>
