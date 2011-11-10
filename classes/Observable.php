<?php
/**
 * Observable, an Event/Listener
 *
 * Example subclass:
 * class Button extends Observable {
 *
 *   protected $events = array(
 *     'click' => array()
 *   );
 *   // optional method that gets executed when the 'click' event is fired.
 *	 protected function onClick($sender) {}
 * }
 *
 * $button = new Button();
 * $button->addListener(function ($sender) {
 *   echo 'clicked';
 * });
 *
 * // Or property style event-binding: (Only 1 listeners per event)
 *
 * $button->onClick = function () {
 *   echo 'clicked';
 * };
 *
 * // reset all listeners.
 * $this->onClick = null;
 *
 *
 *
 * @package Core
 */
namespace SledgeHammer;

abstract class Observable extends Object {

	/**
	 * @var array  The events/listeners. array($event1 => array($listener1, ...), ...)
	 */
	protected $events = array();

	/**
	 * Trigger the event.
	 *
	 * @param string $event
	 * @param stdClass $sender
	 * @param mixed $args (optional)
	 */
	function fire($event, $sender, $args = null) {
		if (isset($this->events[$event])) {
			$method = 'on'.ucfirst($event);
			$arguments = func_get_args();
			array_shift($arguments);
			if (method_exists($this, $method)) {
				call_user_func_array(array($this, $method), $arguments);
			}
			foreach ($this->events[$event] as $callback) {
				call_user_func_array($callback, $arguments);
			}
		} else {
			notice('Event: "'.$event.'" not registered', 'Available events: '.quoted_human_implode(', ', array_keys($this->events)));
		}
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
		if ($this->hasEvent($event) === false) {
			warning('Event: "'.$event.'" not registered', 'Available events: '.quoted_human_implode(', ', array_keys($this->events)));
			return false;
		}
		$this->events[$event][] = $callback;
		return true;
	}

	function hasEvent($event) {
		return array_key_exists($event, $this->events);
	}

	/**
	 *
	 * @param type $property
	 * @param type $value
	 * @return type
	 */
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

}

?>
