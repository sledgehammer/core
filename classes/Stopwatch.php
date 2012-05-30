<?php
/**
 * Stopwatch
 */
namespace Sledgehammer;
/**
 * Helper class for manual performance profiling.
 *
 * @package Core
 */
class Stopwatch extends Object {

	/**
	 * Timestamp
	 * @var float
	 */
	private $start;
	/**
	 * Timestamp
	 * @var float
	 */
	private $lap;

	/**
	 * Constructor
	 */
	function __construct() {
		$this->reset();
	}

	/**
	 * Reset the counters
	 */
	function reset() {
		$this->start = microtime(true);
		$this->lap = $this->start;
	}

	/**
	 * Return the timeinterval since the stopwatch started.
	 *
	 * @return string
	 */
	function getElapsedTime() {
		$elapsed = microtime(true) - $this->start;
		return format_parsetime($elapsed).' sec';
	}

	/**
	 * Return the timeinterval since the last getLapTime().
	 *
	 * @return string
	 */
	function getLapTime() {
		$now = microtime(true);
		$elapsed = $now - $this->lap;
		$this->lap = $now;
		return format_parsetime($elapsed).' sec';
	}

}

?>
