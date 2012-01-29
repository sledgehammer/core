<?php
/**
 * Renders the data as Json
 * Compatible with MVC's the Document/View inferface.
 *
 * @package Core
 */

namespace SledgeHammer;

class Json extends Object {

	/**
	 * @var mixed $data  UTF-8 encoded data.
	 */
	private $data;

	/**
	 * @param mixed  $data      The data to be sent as json
	 * @param string $charset   The encoding used in $data, use null for autodetection. Assume UTF-8 by default
	 */
	function __construct($data, $charset = 'UTF-8') {
		if (strtoupper($charset) !== 'UTF-8') {
			$this->data = $this->convertToUTF8($data, $charset);
		} else {
			$this->data = $data;
		}
	}

	/**
	 * Change Content-Type to "application/json"
	 */
	function getHeaders() {
		if (count($_FILES) == 0) {
			return array(
				'http' => array(
					'Content-Type' => 'application/json',
				)
			);
		} else {
			// Als er bestanden ge-upload zijn, gaat het *niet* om een XMLHttpRequest, maar waarschijnlijk om een upload naar een hidden iframe via javascript.
			// Een "application/json" header zal dan een ongewenste download veroorzaken.
			// (Of als de JSONView extensie is geinstalleerd, wordt de json versmurft als html)
			return array(
				'http' => array(
					'Content-Type' => 'plain/text',
				)
			);
		}
	}

	/**
	 * Render the $data as json
	 */
	function render() {
		echo self::encode($this->data);
	}

	/**
	 * Render a standalone document
	 *
	 * @return bool
	 */
	function isDocument() {
		return true;
	}

	/**
	 * Returns a string containing the JSON representation of value.
	 *
	 * @param mixed $data
	 * @param array|int $options
	 * @throws \Exception
	 * @return string JSON formatted string
	 */
	static function encode($data, $options = array()) {
		if (is_int($options)) {
			$optionMask = $options;
		} else {
			$optionMask = 0;
			foreach ($options as $option) {
				$optionMask += $option;
			}
		}
		$json = json_encode($data, $optionMask);
		$error = json_last_error();
		if ($error !== JSON_ERROR_NONE) {
			$this->throwError($error);
		}
		return $json;
	}

	/**
	 * Takes a JSON encoded string and converts it into a PHP variable.
	 *
	 * @param string $json JSON formatted string
	 * @param bool $assoc
	 * @param int $depth
	 * @param array|int $options
	 * @throws \Exception
	 * @return mixed data
	 */
	static function decode($json, $assoc = false, $depth = 512, $options = array()) {
		if (is_int($options)) {
			$optionMask = $options;
		} else {
			$optionMask = 0;
			foreach ($options as $option) {
				$optionMask += $option;
			}
		}
		if ($optionMask === 0) {
			$data = json_decode($json, $assoc, $depth);
		} else {
			$data = json_decode($json, $assoc, $depth, $optionMask); //Opstions available since php 5.4.0
		}
		$error = json_last_error();
		if ($error !== JSON_ERROR_NONE) {
			$this->throwError($error);
		}
		return $data;
	}

	/**
	 *
	 * @param mixed       $data     The non UTF-8 encoded data
	 * @param string|null $charset  The from_encoding, Use null for autodetection
	 * @return mixed  UTF8 encoded data
	 */
	private function convertToUTF8($data, $charset) {
		if (is_string($data)) {
			return mb_convert_encoding($data, 'UTF-8', $charset);
		}
		if (is_object($data)) {
			$data = get_object_vars($data);
		}
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = $this->convertToUTF8($value, $charset);
			}
			return $data;
		}
		// Is $data a integer, float, etc
		return $data;
	}

	/**
	 *
	 * @param int $errno
	 * @return type
	 */
	private static function throwError($errno) {
		static $lookup = null;
		if ($lookup === null) {
			$lookup = array();
			$constants = get_defined_constants();
			foreach ($constants as $constant => $constant_value) {
				if (substr($constant, 0, 11) === 'JSON_ERROR_') {
					$lookup[$constant_value] = $constant;
				}
			}
		}
		if (array_key_exists($errno, $lookup)) {
			$message = '['.$lookup[$errno].'] ';
		} else {
			$message = '['.$errno.'] ';
		}
		switch ($errno) {
			case JSON_ERROR_NONE:
				$message .= 'No errors';
				break;
			case JSON_ERROR_DEPTH:
				$message .= 'The maximum stack depth has been exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$message .= 'Invalid or malformed JSON';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$message .= 'Control character error, possibly incorrectly encoded';
				break;
			case JSON_ERROR_SYNTAX:
				$message .= 'Syntax error';
				break;
			case JSON_ERROR_UTF8:
				$message .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
			default: $message = 'Unknown error';
		}
		throw new Exception($message, $errno);
	}

}

?>