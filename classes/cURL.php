<?php
/**
 * cUrl
 */
namespace Sledgehammer;
/**
 * cURL, an HTTP/FTP response object
 * Simplifies asynchronous requests & paralell downloads.
 * Uses the curl functions and throws exceptions on errors.
 *
 * @property-read string $effective_url  Last effective URL
 * @property-read string $http_code      Last received HTTP code
 * @property-read mixed $filetime        Remote time of the retrieved document, if -1 is returned the time of the document is unknown
 * @property-read mixed $total_time      Total transaction time in seconds for last transfer
 * @property-read mixed $namelookup_time Time in seconds until name resolving was complete
 * @property-read mixed $connect_time    Time in seconds it took to establish the connection
 * @property-read mixed $pretransfer_time    Time in seconds from start until just before file transfer begins
 * @property-read mixed $starttransfer_time  Time in seconds until the first byte is about to be transferred
 * @property-read mixed $redirect_time   Time in seconds of all redirection steps before final transaction was started
 * @property-read mixed $size_upload     Total number of bytes uploaded
 * @property-read mixed $size_download   Total number of bytes downloaded
 * @property-read mixed $speed_download  Average download speed
 * @property-read mixed $speed_upload    Average upload speed
 * @property-read mixed $header_size     Total size of all headers received
 * @property-read mixed $header_out      The request string sent. For this to work, add the CURLINFO_HEADER_OUT option to the handle by calling curl_setopt()
 * @property-read mixed $request_size    Total size of issued requests, currently only for HTTP requests
 * @property-read mixed $ssl_verifyresult         Result of SSL certification verification requested by setting CURLOPT_SSL_VERIFYPEER
 * @property-read mixed $content_length_download  content-length of download, read from Content-Length: field
 * @property-read mixed $content_length_upload    Specified size of upload
 * @property-read mixed $content_type    Content-Type: of the requested document, NULL indicates server did not send valid Content-Type: header
 *
 * @property-write Closure $onLoad  Event fires when the request has successfully completed.
 * @property-write Closure $onAbort Event fires when the request has been aborted. For instance, by invoking the abort() method.
 *
 * @package Core
 */
class cURL extends Observable {

	/**
	 * Allow listening to the events: 'load' and 'abort'
	 * @var array
	 */
	protected $events = array(
		'load' => array(), // Fires when the request has completed
		'abort' => array(), // Fires when the request was aborted
		'closed' => array(), // Fires when the curl handle is closed
	);

	/**
	 * All the given CURLOPT_* options
	 * @var array
	 */
	private $options = array();

	/**
	 * RUNNING | COMPLETED | ABORTED | ERROR
	 * @var string
	 */
	private $state;

	/**
	 * cURL handle
	 * @var resource
	 */
	private $handle;

	/**
	 * Global queue of active cURL requests
	 * @var array|cURL
	 */
	static $requests = array();

	/**
	 * cURL multi handle
	 * @var resource
	 */
	static private $pool;

	/**
	 * Keep the connections open, speeds up requests to the same server.
	 * @var bool
	 */
	static $keepalive = true;

	/**
	 * Number of tranfers in the pool
	 * @var int
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

	/**
	 * Complete the transfer and remove the curl handle from the pool.
	 *
	 * @return void
	 */
	function __destruct() {
		if (is_resource($this->handle)) {
			if ($this->state === 'RUNNING') {
				$this->waitForCompletion(); // Complete the request/upload
			}
			if ($this->state !== 'ABORTED') {
				$this->stop(); // Remove the cURL handle from the pool
			}
			curl_close($this->handle);
			$this->trigger('closed', $this);
		}
	}

	/**
	 * Preform an asynchonous GET request
	 *
	 * @param string $url
	 * @param array $options Additional CURLOPT_* options
	 * @param Closure|callback $callback  The callback that will e triggered on the load event.
	 * @return \Sledgehammer\cURL  cURL response
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
			$response->on('load', $callback);
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
	 * @return \Sledgehammer\cURL  cURL response
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
			$response->on('load', $callback);
		}
		return $response;
	}

	/**
	 * Download a file to the filesystem.
	 *
	 * @param string $url
	 * @param string $filename
	 * @param array $options
	 * @param bool $async
	 * @throws \Exception
	 * @return cURL
	 */
	static function download($url, $filename, $options = array(), $async = false) {
		$fp = fopen($filename, 'w');
		if ($fp === false) {
			throw new \Exception('Unable to write to "'.$filename.'"');
		}
		$defaults = array(
			CURLOPT_URL => $url,
			CURLOPT_FILE => $fp,
			CURLOPT_FAILONERROR => true,
		);
		$response = new cURL($options + $defaults);
		$response->on('closed', function () use ($fp) {
				fclose($fp);
			});
		if ($async == false) {
			$response->waitForCompletion();
		}
		return $response;
	}

	/**
	 * Wait for all requests to complete
	 *
	 * @throws \Exception
	 */
	static function synchronize() {
		if (self::$pool === null) {
			return 0;
		}
		do {
			// Wait for (incomming) data
			if (curl_multi_select(self::$pool, 0.2) === -1) {
				throw new \Exception('Failed to detect changes in the cURL multi handle');
			}
			$wait = false;
			foreach (cURL::$requests as $curl) {
				if ($curl->isComplete() == false) {
					$wait = true;
				}
			}
		} while ($wait);
		if (self::$keepalive === false && self::$tranferCount === 0) {
			curl_multi_close(self::$pool);
			self::$pool = null;
		}
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
			throw new InfoException('No information available for a '.$this->state.' request', $this->options);
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
			throw new InfoException('No content available for a '.$this->state.' request', $this->options);
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
	 *
	 * @return string
	 */
	function __toString() {
		try {
			return $this->getContent();
		} catch (\Exception $e) {
			report_exception($e);
			return '';
		}
	}

	/**
	 * Check if this request is complete
	 *
	 * @throws \Exception
	 * @return boolean
	 */
	function isComplete() {
		if ($this->state !== 'RUNNING') {
			return true;
		}
		// Add messages from the curl_multi handle to the $messages array.
		do {
			$error = curl_multi_exec(self::$pool, $active);
		} while ($error === CURLM_CALL_MULTI_PERFORM);
		if ($error !== CURLM_OK) {
			throw new \Exception('['.self::multiErrorName($error).'Failed to execute cURL multi handle');
		}
		$queued = 0;
		do {
			$message = curl_multi_info_read(self::$pool, $queued);
			if ($message !== false) {
				// Scan the (global) curl pool for curl handle specified in the handle
				foreach (cURL::$requests as $index => $curl) {
					if ($curl->handle === $message['handle']) {
						unset(cURL::$requests[$index]); // Cleanup global curl pool
						$curl->state = 'COMPLETED';
						$error = $message['result'];
						if ($error !== CURLE_OK) {
							$curl->state = 'ERROR';
							throw new InfoException('['.self::errorName($error).'] '.curl_error($curl->handle), $curl->options);
						}
						$tranferCount = self::$tranferCount;
						$curl->trigger('load', $curl);
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
		$index = array_search($this, cURL::$requests, true);
		if ($index !== false) {
			unset(cURL::$requests[$index]);
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
	 * Start the request
	 * 1. Set the CURLOPT_* options
	 * 2. Add the request to the pool
	 * 3. Starts the request.
	 *
	 * @param array $options CURLOPT_* options
	 * @throws \Exception
	 */
	private function start($options) {
		$this->state = 'ERROR';
		cURL::$requests[] = $this; // Watch changes
		// Setting options
		foreach ($options as $option => $value) {
			if (curl_setopt($this->handle, $option, $value) === false) {
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
			}
			if (self::$keepalive) {
				register_shutdown_function(function () {
					cURL::$keepalive = false; // Close the multi handle when all requests are completed.
					cURL::synchronize();
				});
			}
		}
		// Add request
		$error = curl_multi_add_handle(self::$pool, $this->handle);
		while ($error === CURLM_CALL_MULTI_PERFORM) {
			$error = curl_multi_exec(self::$pool, $active);
		}
		if ($error !== CURLM_OK) {
			throw new \Exception('['.self::multiErrorName($error).'] Failed to add cURL handle');
		}
		self::$tranferCount++;
		// Start request
		do {
			$error = curl_multi_exec(self::$pool, $active);
		} while ($error === CURLM_CALL_MULTI_PERFORM);
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
			if (self::$tranferCount === 0 && self::$keepalive === false) {
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
		while ($this->isComplete() === false) {
			// Wait for (incomming) data
			if (curl_multi_select(self::$pool, 0.1) === -1) {
				throw new \Exception('Failed to detect changes in the cURL multi handle');
			}
		}
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
