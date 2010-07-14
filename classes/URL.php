<?php
/**
 * Diverse statische functies die met een URL te maken hebben
 *
 * @package Core
 */

class URL extends Object{
	
	static $cached_extract_path; // De cache voor de extract_path() functie, Is in principe private, maar kan in unittests overschreven worden om een URL te testen

	/**
	 * Vraag de huidige URI op
	 *
	 * @return string
	 */
	static function uri() {
		if (!isset($_SERVER['REQUEST_URI'])) {
			if (self::$cached_extract_path !== NULL) { // Wordt er een bestand gemocked (via een unittest)
				$url = '/';
				foreach($cached_extract_path['folders'] as $folder) {
					$url .= $folder.'/';
				}
				$url .= self::$cached_extract_path['filename'];
				return $url;
			}
			return '';
		}
		$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
		$server = $_SERVER['SERVER_NAME'];
		$file   = $_SERVER['REQUEST_URI'];

		return $scheme.$server.$file;
	}

	/**
	 * Vraag een specifiek deel van url op of de lijst met onderdelen
	 *
	 * @param NULL|string $part Het deel van de url welke gevraagd wordt, bij NULL krijg je een array met alle gegevens
	 * @param NULL|string $url De url om te parsen, bij NULL wordt de huidige url gebruikt
	 * @return mixed
	 */
	static function info($part = NULL, $url = NULL) {
		$partsTable = array(
			'scheme' => PHP_URL_SCHEME,
			'host' => PHP_URL_HOST,
			'port' => PHP_URL_PORT,
			'user' => PHP_URL_USER,
			'pass' => PHP_URL_PASS,
			'path' => PHP_URL_PATH,
			'query' => PHP_URL_QUERY,
			'fragment' => PHP_URL_FRAGMENT
		);

		if ($url === NULL) {
			$url = URL::uri();
		}
		if ($part === NULL) {
			return parse_url($url);
		}
		if ($part == 'file') {
			return basename($url);
		}
		if (!array_key_exists($part, $partsTable)) {
			notice('Unexpected part: "'.$part.'", expecting: "'.implode('", "', array_keys($partsTable)).'"');
			return false;
		}
		return parse_url($url, $partsTable[$part]);
	}

	/**
	 * Een sub-domein opvragen van een domein
	 * 
	 * @param int $index Bepaald welke subdomein van de subdomeinen er wordt opgevraagd. 0 = eerste subdomein van links, -1 =  eerste subdomein van rechts
	 * @param NULL|string $uri de uri waarvan het subdomein opgevraagd moet worden
	 * @return string
	 */
	static function subdomain($index = -1, $uri = NULL) {
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
		$hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
		$regexDomain = '/[a-z0-9]+([a-z]{2}){0,1}.[a-z]{2,4}$/i';
		if (preg_match($regexDomain, $hostname, $match)) { // Zit er een domeinnaam in de hostname? 
			return $match[0];
		}
		return 'example.com'; 	
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
	 * Bestandnaam en mappen (in array vorm) opvragen uit de url
	 *
	 * @return array
	 */
	static function extract_path() {
		if (self::$cached_extract_path !== NULL) { // Zijn de variabelen al eerder opgevraagd?
			if (!is_array(self::$cached_extract_path['folders']) || !is_string(self::$cached_extract_path['filename'])) {
				throw new Exception('Invalid format for URL::$cached_extract_path; format: array(\'folders\' => (array) $folders, \'filename\' => (string) $filename)');
			}
			return self::$cached_extract_path;
		}
		if (isset($_SERVER['REQUEST_URI']) == false) { // Is geen REQUEST_URI? 
			warning('$_SERVER[REQUEST_URI] not set');
			return false;
		}
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
		// De zojuist opgevraagde waarden cachen. Zodat de voldende aanroepen VirtualFolder->extract_path() versnelt worden.
		self::$cached_extract_path = array(
			'filename' => $filename,
			'folders' => $folders
		);
		return self::$cached_extract_path;
	}
}
?>
