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
	private $options = array();

	/**
	 * @var string
	 */
	private $state;

	/**
	 * @var resource The curl request
	 */
	private $curl;

	/**
	 * @var resource cURL multi handle
	 */
	static private $pool;

	/**
	 * @var int
	 */
	static private $tranferCount = 0;

	/**
	 *
	 * @param array $options  The CURLOPT_* request options
	 * @throws \Exception
	 */
	function __construct($options = array()) {
		$this->state = 'ERROR';
		self::$tranferCount++;
		$this->curl = curl_init();

		// Setting options
		foreach ($options as $option => $value) {
			if (curl_setopt($this->curl, $option, $value) === false) {
				throw new \Exception('Setting option:'.self::optionName($option).' failed');
			}
			if (ENVIRONMENT === 'development') {
				$option = self::optionName($option);
			}
			$this->options[$option] = $value;
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
		if ($error !== 0) {
			throw new \Exception('Failed to add cURL handle (error: '.$error.')');
		}
		// Start request
		$state = CURLM_CALL_MULTI_PERFORM;
		while ($state === CURLM_CALL_MULTI_PERFORM) {
			$state = curl_multi_exec(self::$pool, $active);
		}
		if ($state !== CURLM_OK) {
			throw new \Exception('Failed to execute cURL multi handle (error: '.$state.')');
		}
		$this->state = 'RUNNING';
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
	 * @return \SledgeHammer\cURL
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
	 * Preform an asynchonous GET request
	 *
	 * @param string $url
	 * @param array $options Additional CURLOPT_* options
	 * @return \SledgeHammer\cURL
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
		$state = CURLM_CALL_MULTI_PERFORM;
		while ($state === CURLM_CALL_MULTI_PERFORM) {
			$state = curl_multi_exec(self::$pool, $activeTransferCount);
		}
		if ($state !== CURLM_OK) {
			throw new \Exception('Failed to execute cURL multi handle (error: '.$state.')');
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
				$error = curl_errno($this->curl);
				if ($error !== CURLE_OK) {
					$this->state = 'ERROR';
					throw new InfoException('[cURL error '.$error.'] '.curl_error($this->curl), $this->options);
				}
				return true;
			}
		}
		return false;
	}

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
	 * Get information about the response.
	 *
	 * @param int $option (optional)CURLINFO_* value
	 * @return mixed  Array with all info if no $option is given
	 */
	function getInfo($option = null) {
		$this->waitForCompletion();
		if ($option === null) {
			return curl_getinfo($this->curl);
		}
		return curl_getinfo($this->curl, $option);
	}

	/**
	 * The response.
	 * (If the CURLOPT_RETURNTRANSFER option is set)
	 *
	 * @return string
	 */
	function getContent() {
		$this->waitForCompletion();
		return curl_multi_getcontent($this->curl);
	}

	function __toString() {
		try {
			$this->waitForCompletion();
			return curl_multi_getcontent($this->curl);
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
			return '';
		}
	}

	/**
	 * Convert a CURLOPT_* integer to the constantname.
	 *
	 * @param int $number
	 * @return string
	 */
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

}

?>
