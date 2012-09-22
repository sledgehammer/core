<?php
/**
 * DebugR
 *
 * @link http://debugr.net/
 */
namespace Sledgehammer;
/**
 * DebugR
 */
class DebugR extends Object {

	static $increments = array();

	/**
	 * Callback for sending a HTTP header.
	 * @var callable
	 */
	static $headerAdd = 'header';

	/**
	 * Callback for removing a HTTP header.
	 * @var callable
	 */
	static $headerRemove = 'header_remove';

	/**
	 * Send an variable to console.log()
	 * @param mixed $data
	 */
	static function log($variable) {
		return self::send('log', Json::encode($variable));
	}

	/**
	 * Send an message to console.warning()
	 * @param string $message  The warning message
	 */
	static function warning($message) {
		return self::send('warning', $message);
	}

	/**
	 * Send an message to console.error()
	 * @param string $message  The error message
	 */
	static function error($message) {
		return self::send('error', $message);
	}

	/**
	 * Send a dump() to the <body> element.
	 * @param string $data
	 */
	static function dump($variable) {
		$backtrace = debug_backtrace();
		$dump = new Dump($variable, $backtrace);
		ob_start();
		$dump->render();
		return self::send('html', ob_get_clean());
	}

	/**
	 *
	 * @param string $label
	 * @param string $message
	 * @param string $overwrite
	 */
	static function send($label, $message, $overwrite = false) {
		if (self::isEnabled() === false) {
			return;
		}
		if (preg_match('/^(?<label>[a-z0-9\-]+)(?<suffix>\\.[0-9]+)?$/i', $label, $match) == false) {
			notice('Label: "'.$label.'" in invalid', 'A label may contain number, letters and "-"');
			return;
		}
		$number = array_value(self::$increments, $match['label']);
		if (isset($match['suffix'])) { // Has a suffix?
			$labelSuffix = $match[0];
			if ($overwrite === false) {
				notice('Overwrite flag required for label: "'.$label.'"');
				return;
			}
			if ($number <= substr($match['suffix'], 1)) {
				self::$increments[$match['label']] = substr($match['suffix'], 1) + 1;
			}
		} elseif ($overwrite === false) {
			if ($number) {
				$label .= '.'.$number;
			}
			self::$increments[$match['label']] = $number + 1;
		}
		if (headers_sent($file, $line)) {
			if ($file == '' && $line == 0) {
				$location = '';
			} else {
				$location = ', output started in '.$file.' on line '.$line;
			}
			notice('Couldn\'t sent header(s)'.$location);
			return;
		}
		$value = base64_encode($message);
		if (strlen($value) <= (4 * 1024)) { // Under 4KiB?
			call_user_func(self::$headerAdd, 'DebugR-'.$label.': '.$value);
		} else {
			// Send in 4KB chunks.
			call_user_func(self::$headerRemove, 'DebugR-'.$label);
			$chunks = str_split($value, (4 * 1024));
			foreach ($chunks as $index => $chunk) {
				call_user_func(self::$headerAdd, 'DebugR-'.$label.'.chunk'.$index.': '.$chunk);
			}
		}
	}

	/**
	 * Check if DebugR is enabled.
	 * @return bool
	 */
	static function isEnabled() {
		// @todo Authentication
		return isset($_SERVER['HTTP_DEBUGR']);
	}

}

?>
