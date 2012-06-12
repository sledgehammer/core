<?php
/**
 * URL
 */
namespace Sledgehammer;
/**
 * Utility class for generating and manipulating urls.
 *
 * @package Core
 */
class URL extends Object {

	/**
	 * The protocol schema.
	 * @var string
	 */
	public $scheme;

	/**
	 * The hostname/ip
	 * @var string
	 */
	public $host;

	/**
	 * Portnumber.
	 * @var int
	 */
	public $port;

	/**
	 * The (unescaped) path.
	 * @var string
	 */
	public $path;

	/**
	 * The parameters in the querystring.
	 * @var array
	 */
	public $query = array();

	/**
	 * The #hash
	 * @var string
	 */
	public $fragment;

	/**
	 * The username.
	 * @var string
	 */
	public $user;

	/**
	 * The password.
	 * @var string
	 */
	public $pass;

	/**
	 * The url of the current page.
	 * @var URL
	 */
	private static $current;

	/**
	 * Constructor
	 * @param null|string $url  De url om te parsen, bij NULL wordt de huidige url gebruikt
	 */
	function __construct($url) {
		$info = parse_url($url);
		if ($info === false) {
			throw new \Exception('Invalid url: "'.$url.'"');
		}
		if (isset($info['query'])) {
			parse_str($info['query'], $info['query']); // Zet de query om naar een array
		}
		if (isset($info['path'])) {
			$info['path'] = rawurldecode($info['path']); // "%20" omzetten naar " " e.d.
		}
		set_object_vars($this, $info);
	}

	/**
	 * Generate the url as a string.
	 * Allows URL object to be used as php strings.
	 *
	 * @return string
	 */
	function __toString() {
		$url = '';
		if ($this->scheme !== null && $this->host !== null) {
			$url .= $this->scheme.'://';
			if (empty($this->user) == false) {
				$url .= rawurlencode($this->user);
				if (empty($this->pass) == false) {
					$url .= ':'.$this->pass;
				}
				$url .= '@';
			}
			$url .= $this->host;
			if ($this->port) {
				$standardPorts = array(
					'http' => 80,
					'https' => 443,
				);
				if ($this->scheme === null || empty($standardPorts[$this->scheme]) || $standardPorts[$this->scheme] != $this->port) { // Is the port non-standard?
					$url .= ':'.$this->port;
				}
			}
		}
		if ($this->path !== null) {
			$url .= str_replace('%2F', '/', rawurlencode($this->path));
		}
		if (is_string($this->query) && trim($this->query) !== '') {
			$url .= '?'.$this->query;
		} elseif (is_array($this->query) && count($this->query) != 0) {
			$url .= '?'.http_build_query($this->query);
		}
		if ($this->fragment !== null) {
			$url .= '#'.$this->fragment;
		}
		return $url;
	}

	/**
	 * Get foldes in a array (based on the path)
	 * @return array
	 */
	function getFolders() {
		$parts = explode('/', $this->path);
		array_pop($parts); // remove filename part
		$folders = array();
		foreach ($parts as $folder) {
			if ($folder !== '') { // dont add the root and skip "//"
				$folders[] = $folder;
			}
		}
		return $folders;
	}

	/**
	 * Get de filename (or "index.html" if no filename is given.)
	 * @return string
	 */
	function getFilename() {
		if ($this->path === null || substr($this->path, -1) == '/') { // Gaat het om een map?
			return 'index.html';
		}
		return basename($this->path);
	}

	/**
	 * Gets the current url based on the information in the $_SERVER array.
	 *
	 * @return URL
	 */
	static function getCurrentURL() {
		if (self::$current === null) {
			if (array_value($_SERVER, 'HTTPS') == 'on') {
				$scheme = 'https';
				$port = ($_SERVER['SERVER_PORT'] == '443') ? '' : ':'.$_SERVER['SERVER_PORT'];
			} else {
				$scheme = 'http';
				$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':'.$_SERVER['SERVER_PORT'];
			}
			self::$current = new URL($scheme.'://'.$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI']);
		}
		return clone self::$current;
	}

	/**
	 * Set the current url (Mock a request)
	 *
	 * @param string|URL $url
	 */
	static function setCurrentURL($url) {
		if (is_string($url)) {
			$url = new URL($url);
		}
		self::$current = $url;
	}

	/**
	 * Multi-functionele functie om parameters op te vragen en toe te voegen
	 *
	 * URL:parameters(); vraagt de huidige parameters op. ($_GET)
	 * URL:parameters("naam['test']=1234"); of URL::parameters(array('naam'=>array('test'=>1234))); voegt deze parameter toe aan de huidige parameter array.
	 * URL:parameter("bla=true", 'x=y'); voegt 2 parameter 'arrays' samen
	 *
	 * @param array $append  De parameter die toegevoegd moet worden
	 * @param mixed $stack   De url of array waarde parameters waaraan toegevoegd moet worden, bij NULL worden de huidige $_GET parameters gebruikt
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
	 * Returns the domain without subdomains.
	 *
	 * @return string
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
