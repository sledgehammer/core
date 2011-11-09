<?php
/**
 * Observer, an event model implementation
 *
 * @package Core
 */
namespace SledgeHammer;

abstract class Observer extends Object {

	protected $events = array();

	/**
	 * Trigger the event.
	 *
	 * @param type $event
	 * @param mixed $arg1 
	 * @param mixed $argN
	 */
	function fire($event, $arg1 = null) {
		if (isset($this->events[$event])) {
			$method = 'on'.ucfirst($event);
			$args = func_get_args();
			array_shift($args);
			if (method_exists($this, $method)) {
				call_user_func_array(array($this, $method), $args);
			}
			foreach ($this->events[$event] as $callback) {
				call_user_func_array(array($this, $callback), $args);
			}
		} else {
//			notice('Event: "'.$event.'" not registered');
		}
	}

	function __set($property, $value) {
		if (preg_match('/^on[A-Z]/', $property)) { // An event? like "onClick"
			$event = lcfirst(substr($property, 2));
			if (isset($this->events[$event])) {
				$this->events[$event] = array(); // Reset listeners.
				if ($value !== null) {
					$this->addListener($event, $value);
				}
				return;
			}
		}
		return parent::__set($property, $value);
	}

	/**
	 *
	 * @param string $event
	 * @param Closure|string|array $callback
	 * @return bool
	 */
	function addListener($event, $callback) {
		if (is_callable($callback) == false) {
			warning('Parameter $callback issn\'t callable', $callback);
			return false;
		}
		if (array_key_exists($event, $this->events) === false) {
			warning('Event: "'.$event.'" not registered');
			return false;
		}
		$this->events[$event][] = $callback;
		return true;
	}

}

?>
