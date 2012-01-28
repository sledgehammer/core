<?php

namespace SledgeHammer;

/**
 * cURL, an HTTP/FTP response object
 * Simplifies asynchronous requests & paralell downloads.
 * Uses the curl functions and throws exceptions on errors.
 *
 * @property string $effective_url  Last effective URL
 * @property string $http_code      Last received HTTP code
 * @property mixed $filetime        Remote time of the retrieved document, if -1 is returned the time of the document is unknown
 * @property mixed $total_time      Total transaction time in seconds for last transfer
 * @property mixed $namelookup_time Time in seconds until name resolving was complete
 * @property mixed $connect_time    Time in seconds it took to establish the connection
 * @property mixed $pretransfer_time    Time in seconds from start until just before file transfer begins
 * @property mixed $starttransfer_time  Time in seconds until the first byte is about to be transferred
 * @property mixed $redirect_time   Time in seconds of all redirection steps before final transaction was started
 * @property mixed $size_upload     Total number of bytes uploaded
 * @property mixed $size_download   Total number of bytes downloaded
 * @property mixed $speed_download  Average download speed
 * @property mixed $speed_upload    Average upload speed
 * @property mixed $header_size     Total size of all headers received
 * @property mixed $header_out      The request string sent. For this to work, add the CURLINFO_HEADER_OUT option to the handle by calling curl_setopt()
 * @property mixed $request_size    Total size of issued requests, currently only for HTTP requests
 * @property mixed $ssl_verifyresult         Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER
 * @property mixed $content_length_download  content-length of download, read from Content-Length: field
 * @property mixed $content_length_upload    Specified size of upload
 * @property mixed $content_type    Content-Type: of the requested document, NULL indicates server did not send valid Content-Type: header
 */
class cURL extends Object {

	/**
	 * @var array All the given CURLOPT_* options
	 */
	private $request = array();

	/**
	 * @var string  RUNNING | COMPLETED | ABORTED | ERROR
	 */
	private $state;

	/**
	 * @var resource cURL handle
	 */
	private $curl;

	/**
	 * @var resource cURL multi handle
	 */
	static private $pool;

	/**
	 * @var int  Number of tranfers in the pool
	 */
	static private $tranferCount = 0;

	/**
	 * Create a new cURL request/response.
	 *
	 * @param array $options  The CURLOPT_* request options
	 * @throws \Exception
	 */
	function __construct($options = array()) {
		$this->state = 'ERROR';
		self::$tranferCount++;
		$this->curl = curl_init();
		$this->send($options);
	}

	function __destruct() {
		if ($this->curl !== null) {
			curl_multi_remove_handle(self::$pool, $this->curl);
			self::$tranferCount--;
			curl_close($this->curl);
			if (self::$tranferCount === 0) {
				curl_multi_close(self::$pool);
				self::$pool = null;
			}
		}
	}

	/**
	 * Preform an asynchonous GET request
	 *
	 * @param string $url
	 * @param array $options Additional CURLOPT_* options
	 * @return \SledgeHammer\cURL  cURL response
	 */
	static function get($url, $options = array()) {
		$options[CURLOPT_URL] = $url;
		$defaults = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_FOLLOWLOCATION => true,
		);
		return new cURL($options + $defaults);
	}

	/**
	 * Preform an asynchonous POST request
	 *
	 * @param string $url
	 * @param array $options Additional CURLOPT_* options
	 * @return \SledgeHammer\cURL  cURL response
	 */
	static function post($url, $params = array(), $options = array()) {
		$options[CURLOPT_URL] = $url;
		$defaults = array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $params,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
		);
		$request = new cURL($options + $defaults);
		return $request;
	}

	/**
	 * Get information about the response.
	 *
	 * @param int $option (optional)CURLINFO_* value (Returns an array with all info if no $option is given)
	 * @return mixed
	 */
	function getInfo($option = null) {
		$this->waitForCompletion();
		if ($option === null) {
			return curl_getinfo($this->curl);
		}
		return curl_getinfo($this->curl, $option);
	}

	/**
	 * The response body.
	 * (If the CURLOPT_RETURNTRANSFER option is set)
	 *
	 * @return string
	 */
	function getContent() {
		$this->waitForCompletion();
		return curl_multi_getcontent($this->curl);
	}

	/**
	 * Access CURLINFO_* info as properties.
	 *
	 * @param string $property
	 * @return mixed
	 */
	function __get($property) {
		$const = 'CURLINFO_'.strtoupper($property);
		if (defined($const)) {
			$option = eval('return '.$const.';');
			$this->waitForCompletion();
			return curl_getinfo($this->curl, $option);
		}
		return parent::__get($property);
	}

	/**
	 * Returns the response body
	 * @return string
	 */
	function __toString() {
		try {
			return $this->getContent();
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
			return '';
		}
	}

	/**
	 * Check if this request is complete
	 *
	 * @return boolean
	 * @throws InfoException
	 */
	function isComplete(&$activeTransferCount = null) {
		if ($this->state !== 'RUNNING') {
			return true;
		}
		// Add messages from the curl_multi handle to the $messages array.
		$error = CURLM_CALL_MULTI_PERFORM;
		while ($error === CURLM_CALL_MULTI_PERFORM) {
			$error = curl_multi_exec(self::$pool, $activeTransferCount);
		}
		if ($error !== CURLM_OK) {
			throw new \Exception('Failed to execute cURL multi handle (error: '.self::multiErrorName($error).')');
		}
		static $messages = array();
		$queued = 0;
		do {
			$info = curl_multi_info_read(self::$pool, $queued);
			if ($info !== false) {
				$messages[] = $info;
			}
		} while ($queued > 0);
		// Scan the (global) $messages for $this->curl handle
		foreach ($messages as $index => $message) {
			if ($message['handle'] === $this->curl) {
				unset($messages[$index]); // cleanup the (global) $messages array
				$this->state = 'COMPLETE';
				$error = $message['result'];
				if ($error !== CURLE_OK) {
					$this->state = 'ERROR';

					throw new InfoException('['.self::errorName($error).'] '.curl_error($this->curl), $this->request);
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Reuse the curl handle/connection to (re)send an request
	 * Use the options to override settings from the previous request.
	 *
	 * @param array Additional/override  CURLOPT_* settings.
	 */
	function resend($options = array()) {
//		$this->waitForCompletion();
		if ($this->state !== 'ABORTED') {
			$this->abort();
		}
		$this->send($options);
	}

	/**
	 * Abort the current request/download.
	 *
	 * @throws \Exception
	 */
	function abort() {
		$error = curl_multi_remove_handle(self::$pool, $this->curl);
		if ($error !== CURLM_OK) {
			throw new \Exception('Failed to remove cURL handle (error: '.self::multiErrorName($error).')');
		}
		$this->state = 'ABORTED';
	}

	/**
	 * Send a request to the
	 * 1. Set the CURLOPT_* options
	 * 2. Add the request to the pool
	 * 3. Starts the request.
	 *
	 * @param array $options
	 * @throws \Exception
	 */
	private function send($options) {
		// Setting options
		foreach ($options as $option => $value) {
			if (curl_setopt($this->curl, $option, $value) === false) {
				throw new \Exception('Setting option:'.self::optionName($option).' failed');
			}
			if (ENVIRONMENT === 'development') {
				$option = self::optionName($option);
			}
			$this->request[$option] = $value;
		}

		// multi curl init
		if (self::$pool === null) {
			self::$pool = curl_multi_init();
			if (self::$pool === false) {
				throw new \Exception('Failed to create cURL multi handle');
				;
			}
		}
		// Add request
		$error = curl_multi_add_handle(self::$pool, $this->curl);
		if ($error !== CURLM_OK) {
			throw new \Exception('['.self::multiErrorName($error).'] Failed to add cURL handle');
		}
		// Start request
		$error = CURLM_CALL_MULTI_PERFORM;
		while ($error === CURLM_CALL_MULTI_PERFORM) {
			$error = curl_multi_exec(self::$pool, $active);
		}
		if ($error !== CURLM_OK) {
			throw new \Exception('['.self::multiErrorName($error).'] Failed to execute cURL multi handle');
		}
		$this->state = 'RUNNING';
	}

	/**
	 * Wait for the request to complete
	 *
	 * @throws \Exception
	 */
	private function waitForCompletion() {
		if ($this->isComplete()) {
			return;
		}
		$active = 0;
		do {
			// Wait for (incomming) data
			if (curl_multi_select(self::$pool) === -1) {
				throw new \Exception('Failed to detect changes in the cURL multi handle');
			}
			if ($this->isComplete($active)) {
				return;
			}
		} while ($active > 0);
	}

	/**
	 * Convert a CURLOPT_* integer to the constantname.
	 *
	 * @param int $number
	 * @return string
	 */
	private static function optionName($number) {
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

	/**
	 * Convert a CURLE_* integer to the constantname.
	 *
	 * @param int $number
	 * @return string
	 */
	private static function errorName($number) {
		static $lookup = null;
		if ($lookup === null) {
			$lookup = array();
			$constants = get_defined_constants();
			foreach ($constants as $constant => $constant_value) {
				if (substr($constant, 0, 6) === 'CURLE_') {
					$lookup[$constant_value] = $constant;
				}
			}
		}
		if (array_key_exists($number, $lookup)) {
			return $lookup[$number];
		}
		return $number;
	}

	/**
	 * Convert a CURLM_* integer to the constantname.
	 *
	 * @param int $number
	 * @return string
	 */
	private static function multiErrorName($number) {
		static $lookup = null;
		if ($lookup === null) {
			$lookup = array();
			$constants = get_defined_constants();
			foreach ($constants as $constant => $constant_value) {
				if (substr($constant, 0, 6) === 'CURLM_') {
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
