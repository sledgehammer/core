<?php
/**
 * cURL, an OOP wrapper for curl functions.
 *
 * Setting options:
 *   Classic: $cURL->setOption(CURLOPT_URL, "http://example.com");
 *   Propery: $cURL->url = "http://example.com";
 *
 * Reading output:
 *   Classic: $cURL->getInfo(CURLINFO_HTTP_CODE);
 *   Property: $cURL->http_code;
 *
 * @property string $url
 */

namespace SledgeHammer;

class cURL extends Object {

	/**
	 * @var resource $curl
	 */
	private $curl;
	private $options = array();

	function __construct($url = null, $options = array()) {
		$this->curl = curl_init();
		if ($url !== null) {
			$options[CURLOPT_URL] = (string)$url;
		}
		$this->setOptions($options);
	}

	function __destruct() {
		if ($this->curl !== null) {
			curl_close($this->curl);
		}
	}

	function __set($property, $value) {
		$const = 'CURLOPT_'.strtoupper($property);
		if (defined($const)) {
			$option = eval('return '.$const.';');
			$this->setOption($option, $value);
			return;
		}
		parent::__set($property, $value);
	}

	function __get($property) {
		$const = 'CURLINFO_'.strtoupper($property);
		if (defined($const)) {
			$option = eval('return '.$const.';');
			return $this->getInfo($option);
		}
		return parent::__get($propery);
	}

	function setOption($option, $value) {
		if (is_string($option)) {
			$const = 'CURLOPT_'.strtoupper($option);
			$option = eval('return '.$const.';');
			if ($option === null) {
				throw new \Exception('Option lookup failed');
			}
		}
		if (curl_setopt($this->curl, $option, $value) === false) {
			throw new \Exception('Setting option:'. self::optionName($option).' failed');
		}
		if (ENVIRONMENT === 'development') {
			$option = self::optionName($option);
		}
		$this->options[$option] = $value;
	}

	function setOptions($options) {
		dump($options);
		foreach ($options as $option => $value) {
			$this->setOption($option, $value);
		}
	}

	function getInfo($option = CURLINFO_EFFECTIVE_URL) {
		return curl_getinfo($this->curl, $option);
	}

	static function get($url, $options = array()) {
		$defaults = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
		);
		$request = new cURL($url, $options + $defaults);
		return $request->execute();
	}

	static function post($url, $params = array(), $options = array()) {
		$defaults = array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $params,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
		);
		$request = new cURL($url, $options + $defaults);
		return $request->execute();
	}

	function execute() {
		$success = curl_exec($this->curl);
		if ($success === false) {
			$error = curl_errno($this->curl);
			if ($error !== 0) {
				throw new InfoException('[cURL error '.$error.'] '.curl_error($this->curl), $this->options);
			}
		}
		return $success;
	}

	static function optionName($number) {
		static $lookup = null;
		if ($lookup === null) {
			$lookup = array();
			$constants = get_defined_constants();
			foreach ($constants as $constant => $constant_value) {
				if (substr($constant, 0, 8) === 'CURLOPT_') {
					$lookup[$constant_value] = $constant;
				}
			}
		}
		if (array_key_exists($number, $lookup)) {
			return $lookup[$number];
		}
		return $number;
	}

}

?>
