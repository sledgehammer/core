<?php

namespace Sledgehammer\Core;

use Exception;

/**
 * Utility class for generating and manipulating urls.
 */
class Url extends Object
{
    /**
     * The protocol/schema.
     *
     * @var string
     */
    public $scheme;

    /**
     * The hostname/ip.
     *
     * @var string
     */
    public $host;

    /**
     * Portnumber.
     *
     * @var int
     */
    public $port;

    /**
     * The (unescaped) path.
     *
     * @var string
     */
    public $path;

    /**
     * The parameters in the querystring.
     *
     * @var array
     */
    public $query = [];

    /**
     * The #hash.
     *
     * @var string
     */
    public $fragment;

    /**
     * The username.
     *
     * @var string
     */
    public $user;

    /**
     * The password.
     *
     * @var string
     */
    public $pass;

    /**
     * The url of the current page.
     *
     * @var Url
     */
    private static $current;

    /**
     * Constructor.
     *
     * @param string $url De url om te parsen
     */
    public function __construct($url)
    {
        $info = parse_url($url);
        if ($info === false) {
            throw new Exception('Invalid url: "'.$url.'"');
        }
        if (isset($info['query'])) {
            parse_str($info['query'], $info['query']); // Zet de query om naar een array
        }
        if (isset($info['path'])) {
            $info['path'] = rawurldecode($info['path']); // "%20" omzetten naar " " e.d.
        }
        \Sledgehammer\set_object_vars($this, $info);
    }

    /**
     * Generate the url as a string.
     * Allows URL object to be used as php strings.
     *
     * @return string
     */
    public function __toString()
    {
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
            if ($url !== '' && substr($this->path, 0, 1) !== '/') {
                $url .= '/'; // prevent the path being appended to the hostname.
            }
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
     * Get folders in a array (based on the path).
     *
     * @return array
     */
    public function getFolders()
    {
        $parts = explode('/', $this->path);
        array_pop($parts); // remove filename part
        $folders = [];
        foreach ($parts as $folder) {
            if ($folder !== '') { // don't add the root and skip "//"
                $folders[] = $folder;
            }
        }

        return $folders;
    }

    /**
     * Get de filename (or "index.html" if no filename is given.).
     *
     * @return string
     */
    public function getFilename()
    {
        if ($this->path === null || substr($this->path, -1) == '/') { // Gaat het om een map?
            return 'index.html';
        }

        return basename($this->path);
    }

    /**
     * Return new Url with different protocol.
     * 
     * Example:
     *   $url->schema('https']); returns the secure url without modifing the original url.
     * 
     * @param string $protocol
     *
     * @return Url
     */
    public function scheme($protocol)
    {
        $url = clone $this;
        $url->scheme = $protocol;

        return $url;
    }

    /**
     * Return new Url with different hostname.
     *
     * @param string $hostname
     *
     * @return Url
     */
    public function host($hostname)
    {
        $url = clone $this;
        $url->host = $hostname;

        return $url;
    }

    /**
     * Return new Url with different port.
     *
     * @param string $number
     *
     * @return Url
     */
    public function port($number)
    {
        $url = clone $this;
        $url->port = $number;

        return $url;
    }

    /**
     * Return new Url with different path.
     *
     * @param string $path
     *
     * @return Url
     */
    public function path($path)
    {
        $url = clone $this;
        $url->path = $path;

        return $url;
    }

    /**
     * Return new Url with modified paramaters.
     * 
     * @param array $parameters
     * @param bool  $merge      true: Keep existing parameters, false:  overwrite existing query.
     */
    public function query($parameters, $merge = false)
    {
        $url = clone $this;
        if ($merge) {
            foreach ($parameters as $parameter => $value) {
                $url->query[$parameter] = $value;
            }
        } else {
            $url->query = $parameters;
        }

        return $url;
    }

    /**
     * Return new Url with modified parameters.
     * 
     * @param string $parameter
     * @param mixed  $value
     * @param int [$index] 
     *
     * @return Url
     */
    public function parameter($parameter, $value, $index = false)
    {
        $url = clone $this;
        $append = false;
        if ($index === false && preg_match('/^(?<param>[^\[]+)\[(?<index>[^\]]*)\]$/', $parameter, $match)) {
            $parameter = $match['param'];
            $index = $match['index'];
            $append = ($match['index'] === '');
        }
        if ($index === false) {
            $url->query[$parameter] = $value;
        } else {
            if (isset($url->query[$parameter])) {
                if (is_string($url->query[$parameter])) {
                    $url->query[$parameter] = [$url->query[$parameter]];
                }
            } else {
                $url->query[$parameter] = [];
            }
            if ($append) {
                $url->query[$parameter][] = $value;
            } else {
                $url->query[$parameter][$index] = $value;
            }
        }

        return $url;
    }

    /**
     * Return new Url without the given parameter.
     *
     * @param string $parameter
     * @param int [$index]
     * @return Url
     */
    public function removeParameter($parameter, $index = false)
    {
        $url = clone $this;
        if ($index === false && preg_match('/^(?<param>[^\[]+)\[(?<index>[^\]]+)\]$/', $parameter, $match)) {
            $parameter = $match['param'];
            $index = $match['index'];
        }
        if ($index === false) {
            unset($url->query[$parameter]);
        } elseif (isset($url->query[$parameter])) {
            unset($url->query[$parameter][$index]);
        }
        return $url;
    }

    /**
     * Return new Url with different .
     *
     * @param string $value
     *
     * @return Url
     */
    public function fragment($value)
    {
        $url = clone $this;
        $url->fragment = $value;

        return $url;
    }

    /**
     * Gets the current url based on the information in the $_SERVER array.
     *
     * @return Url
     */
    public static function getCurrentURL()
    {
        if (self::$current === null) {
            if (\Sledgehammer\array_value($_SERVER, 'HTTPS') == 'on') {
                $scheme = 'https';
                $port = ($_SERVER['SERVER_PORT'] == '443') ? '' : ':'.$_SERVER['SERVER_PORT'];
            } else {
                $scheme = 'http';
                $port = ($_SERVER['SERVER_PORT'] == '80') ? '' : ':'.$_SERVER['SERVER_PORT'];
            }
            $domain = $_SERVER['SERVER_NAME'];
            if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { // An IP6 address?
                $domain = '['.$domain.']'; // Enclose IP in brackets
            }
            self::$current = new self($scheme.'://'.$domain.$port.$_SERVER['REQUEST_URI']);
        }

        return clone self::$current;
    }

    /**
     * Set the current url (Mock a request).
     *
     * @param string|Url $url
     */
    public static function setCurrentURL($url)
    {
        if (is_string($url)) {
            $url = new self($url);
        }
        self::$current = $url;
    }
}
