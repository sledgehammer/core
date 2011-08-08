<?php
/**
 * Stopwatch
 */
namespace SledgeHammer;
class Stopwatch extends Object{
	
	private $start;
	private $lap;
	
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
	 *
	 * @param string $label 
	 * @return string
	 */
	function getElapsedTime() {
		$elapsed = microtime(true) - $this->start;
		return format_parsetime($elapsed).' sec';
	}
	
	/**
	 *
	 * @param string $label 
	 * @return string
	 */
	function getLapTime() {
		$now = microtime(true);
		$elapsed = $now - $this->lap;
		$this->lap  = $now;
		return format_parsetime($elapsed).' sec';
	}
}

?>
