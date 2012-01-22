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

	function __construct($url = null) {
		$this->curl = curl_init();
		if ($url !== null) {
			$this->setOption(CURLOPT_URL, $url);
		}
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
		if (curl_setopt($this->curl, $option, $value) === false) {
			throw new Exception('Setting option'.$option.' failed');
		}
		if (ENVIRONMENT === 'development') {
			$option = self::optionName($option);
		}
		$this->options[$option] = $value;
	}

	function getInfo($option = CURLINFO_EFFECTIVE_URL) {
		return curl_getinfo($this->curl, $option);
	}

	/**
	 *
	 * @param type $params
	 */
	function get($params = array()) {
		$this->setOption(CURLOPT_RETURNTRANSFER, true);
		$response = $this->execute();
		dump($this);
		return $response;
	}

	function post($params = array()) {

	}

	function execute() {
		$success = curl_exec($this->curl);
		if ($success === false) {
			$error = curl_errno($this->curl);
			if ($error !== 0) {
				notice('[cURL error '.$error.'] '.curl_errno($this->curl));
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
					$lookup[$constant_value] = strtolower(substr($constant, 8));
				}
			}
		}
		return $lookup[$number];
	}

}

?>
