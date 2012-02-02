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
 *
 * @property Closure $onLoad  Event fires when the request has successfully completed.
 * @property Closure $onAbort Event fires when the request has been aborted. For instance, by invoking the abort() method.
 */
class cURL extends Observable {

	protected $events = array(
		'load' => array(),
		'abort' => array(),
	);
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
	private $handle;

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
		$this->handle = curl_init();
		if ($this->handle === false) {
			throw new \Exception('Failed to create cURL handle');
		}
		$this->start($options);
	}

	function __destruct() {
		if (is_resource($this->handle)) {
			if ($this->state === 'RUNNING') {
				$this->waitForCompletion(); // Complete the request/upload
			}
			if ($this->state !== 'ABORTED') {
				$this->stop(); // Remove the cURL handle from the pool
			}
			curl_close($this->handle);
		}
	}

	/**
	 * Preform an asynchonous GET request
	 *
	 * @param string $url
	 * @param array $options Additional CURLOPT_* options
	 * @param Closure|callback $callback  The callback that will e triggered on the load event.
	 * @return \SledgeHammer\cURL  cURL response
	 */
	static function get($url, $options = array(), $callback = null) {
		$options[CURLOPT_URL] = $url;
		$defaults = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
			CURLOPT_FOLLOWLOCATION => true,
		);
		$response = new cURL($options + $defaults);
		if ($callback !== null) {
			$response->addListener('load', $callback);
		}
		return $response;
	}

	/**
	 * Preform an asynchonous POST request
	 *
	 * @param string $url
	 * @param array|string $data
	 * @param array $options Additional CURLOPT_* options
	 * @param Closure|callback $callback  The callback that will e triggered on the load event.
	 * @return \SledgeHammer\cURL  cURL response
	 */
	static function post($url, $data = array(), $options = array(), $callback = null) {
		$options[CURLOPT_URL] = $url;
		$defaults = array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FAILONERROR => true,
		);
		$response = new cURL($options + $defaults);
		if ($callback !== null) {
			$response->addListener('load', $callback);
		}
		return $response;
	}

	/**
	 * Wait for all requests to complete
	 *
	 * @throws \Exception
	 * @return int  Maximum number of simultaneous requests.
	 */
	static function synchronize() {
		if (self::$pool === null) {
			return 0;
		}
		$max = 0;
		do {
			// Wait for (incomming) data
			if (curl_multi_select(self::$pool) === -1) {
				throw new \Exception('Failed to detect changes in the cURL multi handle');
			}
			$active = 0;
			foreach ($GLOBALS['SledgeHammer']['cURL'] as $curl) {
				$curl->isComplete($active);
				if ($max < $active) {
					$max = $active;
				}
				break;
			}
		} while ($active > 0);
		return $max;
	}

	/**
	 * Get information about the response.
	 *
	 * @param int $option (optional)CURLINFO_* value (Returns an array with all info if no $option is given)
	 * @return mixed
	 */
	function getInfo($option = null) {
		$this->waitForCompletion();
		if ($this->state !== 'COMPLETED') {
			throw new InfoException('No information available for a '.$this->state.' request', $this->request);
		}
		if ($option === null) {
			return curl_getinfo($this->handle);
		}
		return curl_getinfo($this->handle, $option);
	}

	/**
	 * The response body.
	 * (If the CURLOPT_RETURNTRANSFER option is set)
	 *
	 * @return string
	 */
	function getContent() {
		$this->waitForCompletion();
		if ($this->state !== 'COMPLETED') {
			throw new InfoException('No content available for a '.$this->state.' request', $this->request);
		}
		return curl_multi_getcontent($this->handle);
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
			return curl_getinfo($this->handle, $option);
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
	 * @param int $activeTransferCount
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
			throw new \Exception('['.self::multiErrorName($error).'Failed to execute cURL multi handle');
		}
		$queued = 0;
		do {
			$message = curl_multi_info_read(self::$pool, $queued);
			if ($message !== false) {
				// Scan the (global) curl pool for curl handle specified in the handle
				foreach ($GLOBALS['SledgeHammer']['cURL'] as $index => $curl) {
					if ($curl->handle === $message['handle']) {
						unset($GLOBALS['SledgeHammer']['cURL'][$index]); // Cleanup global curl pool
						$curl->state = 'COMPLETED';
						$error = $message['result'];
						if ($error !== CURLE_OK) {
							$curl->state = 'ERROR';
							throw new InfoException('['.self::errorName($error).'] '.curl_error($curl->handle), $curl->request);
						}
						$tranferCount = self::$tranferCount;
						$curl->trigger('load', $curl);
						if ($activeTransferCount === 0 && self::$tranferCount > $tranferCount) { // New transfers where added?
							$activeTransferCount = (self::$tranferCount - $tranferCount);
						}
						if ($curl === $this) {
							return true;
						}
						break;
					}
				}
			}
		} while ($queued > 0);
		return false;
	}

	/**
	 * Reuse the curl handle/connection to (re)send an request
	 * Use the options to override settings from the previous request.
	 *
	 * @param array Additional/override  CURLOPT_* settings.
	 */
	function resend($options = array()) {
		if ($this->state !== 'ABORTED') {
			$this->abort();
		}
		$index = array_search($this, $GLOBALS['SledgeHammer']['cURL'], true);
		if ($index !== false) {
			unset($GLOBALS['SledgeHammer']['cURL'][$index]);
		}
		$this->start($options);
	}

	/**
	 * Abort the current request/download.
	 *
	 * @throws \Exception
	 */
	function abort() {
		$this->isComplete(); // Check if the transfer has completed successfully (and trigger events)
		$previous_state = $this->state;
		$this->stop();
		if ($previous_state === 'RUNNING') {
			$this->trigger('abort', $this);
		}
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
	private function start($options) {
		$this->state = 'ERROR';
		$GLOBALS['SledgeHammer']['cURL'][] = $this; // Watch changes
		// Setting options
		foreach ($options as $option => $value) {
			if (curl_setopt($this->handle, $option, $value) === false) {
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
			}
		}
		// Add request
		$error = curl_multi_add_handle(self::$pool, $this->handle);
		if ($error !== CURLM_OK) {
			throw new \Exception('['.self::multiErrorName($error).'] Failed to add cURL handle');
		}
		self::$tranferCount++;
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
	 * Remove the request from the pool
	 *
	 * @throws \Exception
	 */
	private function stop() {
		if ($this->state !== 'ABORTED') {
			$error = curl_multi_remove_handle(self::$pool, $this->handle);
			self::$tranferCount--;
			if ($error !== CURLM_OK) {
				throw new \Exception('['.self::multiErrorName($error).'] Failed to remove cURL handle');
			}
			if (self::$tranferCount === 0) {
				curl_multi_close(self::$pool);
				self::$pool = null;
			}
		}
		$this->state = 'ABORTED';
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
