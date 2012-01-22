<?php
/**
 * cURL
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
		$this->execute();
	}

	function post($params = array()) {

	}

	private function execute() {
		$success = curl_exec($this->curl);
		if ($success === false) {
			$error = curl_errno($this->curl);
			if ($error !== 0) {
				notice('[cURL error '.$error.'] '.curl_errno($this->curl));
			}
		}
	}

}

?>
