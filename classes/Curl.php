<?php
/**
 * Curl
 */
namespace Sledgehammer;
/**
 * Curl, an HTTP/FTP response object
 * Simplifies asynchronous requests & paralell downloads.
 * Uses the cURL functions and throws exceptions on errors.
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
 * @property-read mixed $content_type    Content-Type: of the requested document, null: indicates server did not send valid Content-Type: header
 *
 * @property-write Closure $onLoad  Event fires when the request has successfully completed.
 * @property-write Closure $onAbort Event fires when the request has been aborted. For instance, by invoking the abort() method.
 *
 * @package Core
 */
class Curl extends Observable {

	/**
	 * Sane defaults for Curl requests. Only used in the static helper methods like Curl::get(), Curl::post(), etc.
	 *
	 * Only set options you need on EVERY request. A (Charles) proxy for example.
	 *   Curl::$defaults[CURLOPT_PROXY] = "127.0.0.1";
	 *   Curl::$defaults[CURLOPT_PROXYPORT] = 8888;
	 * @var array
	 */
	public static $defaults = array(
		CURLOPT_FAILONERROR => true, // Report an error when the HTTP status >= 400.
		CURLOPT_RETURNTRANSFER => true, // Don't stream the contents to the output buffer.
		// Automaticly disabled in safe mode
		CURLOPT_FOLLOWLOCATION => true, // When allowed follow 301 and 302 redirects.
		CURLOPT_MAXREDIRS => 25, // Prevent infinite redirection loops.
		// Dynamic options
//		CURLOPT_TIMEOUT => ?, // Don't allow the request to take more time the the internal php timeout.
	);

	/**
	 * Allow listening to the events: 'load' and 'abort'
	 * @var array
	 */
	protected $events = array(
		'load' => array(), // Fires when the request has completed
		'abort' => array(), // Fires when the request was aborted
		'closed' => array(), // Fires when the curl handle is closed,
		'error' => array(), // Fires when the curl handle encounters an error CURLOPT_FAILONERROR.
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
	 * @var Curl[]
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
	 * The maximum number of concurrent downloads.
	 * @var int
	 */
	static $maxConcurrent = 64;

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
		/**
		 * Handle a curl_error by throwing the message as an Exception
		 *
		 * @param string $message
		 * @throws InfoException
		 */
		$onError = function ($message, $options) {
			throw new InfoException($message, $options);
		};
		$this->on('error', $onError);
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
	 * @param Closure|callback $callback  The callback for the load event.
	 * @return \Sledgehammer\Curl  Response
	 */
	static function get($url, $options = array(), $callback = null) {
		$options[CURLOPT_URL] = $url;
		$defaults = self::defaults();
		$response = new Curl($options + $defaults);
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
	 * @param Closure|callback $callback  The callback for the load event.
	 * @return \Sledgehammer\Curl  Response
	 */
	static function post($url, $data = array(), $options = array(), $callback = null) {
		$defaults = self::defaults(array(
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
		));
		$response = new Curl($options + $defaults);
		if ($callback !== null) {
			$response->on('load', $callback);
		}
		return $response;
	}

	/**
	 * Preform an asynchonous PUT request
	 *
	 * @param string $url
	 * @param string|array $data
	 * @param array $options Additional CURLOPT_* options
	 * @param Closure|callback $callback  The callback for the load event.
	 * @return \Sledgehammer\Curl  Response
	 */
	static function put($url, $data = '', $options = array(), $callback = null) {
		$defaults = self::defaults(array(
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_POSTFIELDS => $data,
		));
		$response = new Curl($options + $defaults);
		if ($callback !== null) {
			$response->on('load', $callback);
		}
		return $response;
	}

	/**
	 * Preform an asynchonous PUT request.
	 *
	 * Override the CURLOPT_INFILESIZE when dealing with large files.
	 * filesize() has issues in PHP 32bit with files bigger than 2GiB.
	 *
	 * @param string $url
	 * @param string $filename
	 * @param array $options  Additional CURLOPT_* options
	 * @param callable $callback  The callback for the load event.
	 * @return Curl
	 */
	static function putFile($url, $filename, $options = array(), $callback = null) {
		$fp = fopen($filename, 'r');
		$defaults = self::defaults(array(
			CURLOPT_URL => $url,
			CURLOPT_PUT => true,
			CURLOPT_INFILE => $fp,
			CURLOPT_INFILESIZE => filesize($filename)
		));
		$response = new Curl($options + $defaults);
		if ($callback !== null) {
			$response->on('load', $callback);
		}
		$response->on('load', function () use ($fp) {
			fclose($fp);
		});
		return $response;
	}

	/**
	 * Preform an asynchonous DELETE request
	 *
	 * @param string $url
	 * @param array|string $data
	 * @param array $options Additional CURLOPT_* options
	 * @param Closure|callback $callback  The callback for the load event.
	 * @return \Sledgehammer\Curl  Response
	 */
	static function delete($url, $options = array(), $callback = null) {
		$options[CURLOPT_URL] = $url;
		$defaults = self::defaults(array(
			CURLOPT_CUSTOMREQUEST => 'DELETE',
		));
		$response = new Curl($options + $defaults);
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
	 * @return Curl
	 */
	static function download($url, $filename, $options = array(), $async = false) {
		$fp = fopen($filename, 'w');
		if ($fp === false) {
			throw new \Exception('Unable to write to "'.$filename.'"');
		}
		flock($fp, LOCK_EX);
		$defaults = self::defaults(array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_FILE => $fp,
		));
		$response = new Curl($options + $defaults);
		$response->on('load', function () use ($fp) {
			fflush($fp);
			flock($fp, LOCK_UN);
		});
		$response->on('closed', function () use ($fp) {
			fclose($fp);
		});
		// Override default error handle
		$response->onError = function ($message, $options) use ($filename, $fp) {
			flock($fp, LOCK_UN);
			fclose($fp);
			unlink($filename);
			throw new InfoException($message, $options);
		};
		if ($async == false) {
			$response->waitForCompletion();
		}
		return $response;
	}

	/**
	 * Wait for all requests to complete.
	 *
	 * @throws \Exception
	 */
	static function synchronize() {
		self::throttle(0);
		if (self::$keepalive === false && self::$tranferCount === 0) {
			curl_multi_close(self::$pool);
			self::$pool = null;
		}
	}

	/**
	 * Limit the number of active transfers.
	 *
	 * @param int $max  The allowed number of active connections.
	 * @throws \Exception
	 */
	private static function throttle($max) {
		if (self::$pool === null) {
			return;
		}
		$max = intval($max);
		if ($max < 0) {
			notice('Invalid throttle value: '.$max);
			$max = 0;
		}
		if (count(self::$requests) <= $max) {
			return;
		}
		do {
			// Wait for (incomming) data
			if (curl_multi_select(self::$pool, 0.2) === -1) {
				usleep(100000); // wait 0.1 second
			}
			$activeTransfers = 0;
			foreach (Curl::$requests as $curl) {
				if ($curl->isComplete() == false) {
					$activeTransfers++;
				}
			}
		} while ($activeTransfers > $max);
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
	 * Get all response headers.
	 * Requires the CURLOPT_HEADER option.
	 *
	 * @return array
	 * @throws InfoException
	 */
	function getHeaders() {
		if (empty($this->options['CURLOPT_HEADER'])) {
			throw new InfoException('Required option CURLOPT_HEADER was not true or 1', $this->options);
		}
		$lines = explode("\n", trim(substr($this->getContent(), 0, $this->header_size)));
		$headers = array();
		$last = count($lines) - 1;
		for ($i = 0; $i <= $last; $i++) {
			$line = $lines[$i];
			$dividerPos = strpos($line, ':');
			if ($dividerPos !== false) {
				$headers[substr($line, 0, $dividerPos)] = trim(substr($line, $dividerPos + 1));
			}
		}
		return $headers;
	}

	/**
	 * Get the value of a specific response header.
	 * Requires the CURLOPT_HEADER option.
	 *
	 * @param string $header Name of the header (case-insensitive)
	 * @return string|null
	 */
	function getHeader($header) {
		$headers = array_change_key_case($this->getHeaders());
		return array_value($headers, strtolower($header));
	}

	/**
	 * Return the response body.
	 *
	 * @return string
	 */
	function getBody() {
		if (empty($this->options['CURLOPT_HEADER'])) {
			return $this->getContent();
		}
		return substr($this->getContent(), $this->header_size);
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
		$properties = reflect_properties($this);
		$properties['public'] = array_merge($properties['public'], curl_getinfo($this->handle));
		warning('Property "'.$property.'" doesn\'t exist in a '.get_class($this).' object', build_properties_hint($properties));
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
			throw new \Exception('['.self::multiErrorName($error).'] Failed to execute cURL multi handle');
		}
		$queued = 0;
		do {
			$message = curl_multi_info_read(self::$pool, $queued);
			if ($message !== false) {
				// Scan the (global) curl pool for curl handle specified in the handle
				foreach (Curl::$requests as $index => $curl) {
					if ($curl->handle === $message['handle']) {
						unset(Curl::$requests[$index]); // Cleanup global curl pool
						$curl->state = 'COMPLETED';
						$error = $message['result'];
						$tranferCount = self::$tranferCount;
						if ($error !== CURLE_OK) {
							$curl->state = 'ERROR';
							$curl->trigger('error', '['.self::errorName($error).'] '.curl_error($curl->handle), $curl->options, $curl);
						} else {
							$curl->trigger('load', $curl);
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
		$index = array_search($this, Curl::$requests, true);
		if ($index !== false) {
			unset(Curl::$requests[$index]);
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
		Curl::$requests[] = $this; // Watch changes
		// Setting options
		foreach ($options as $option => $value) {
			if (curl_setopt($this->handle, $option, $value) === false) {
				throw new \Exception('Setting option:'.self::optionName($option).' failed');
			}
			$option = self::optionName($option);
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
					Curl::$keepalive = false; // Close the multi handle when all requests are completed.
					Curl::synchronize();
				});
			}
		}
		// Wait until a new tranfer can be added.
		self::throttle(self::$maxConcurrent - 1);

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
				usleep(100000); // wait 0.1 second
			}
		}
	}

	/**
	 * Merge options with the default options.
	 * Used in the static get, post, put, delete and download methods.
	 *
	 * @param array $options Additional defaults
	 * @return array
	 */
	private static function defaults($options = array()) {
		$defaults = self::$defaults;
		if (ini_get('max_execution_time')) {
			$defaults[CURLOPT_TIMEOUT] = floor(ini_get('max_execution_time') - (microtime(true) - STARTED)) - 1; // Prevent a fatal PHP timeout (allow ~1 sec for exception handling)
		}
		if (ini_get('safe_mode') || ini_get('open_basedir')) { // Is CURLOPT_FOLLOWLOCATION not allowed. Although this issn't a
			unset($defaults[CURLOPT_FOLLOWLOCATION]);
			unset($defaults[CURLOPT_MAXREDIRS]);
		}
		return $options + $defaults;
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
