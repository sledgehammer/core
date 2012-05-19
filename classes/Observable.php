<?php
namespace SledgeHammer;
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
 * 	 protected function onClick($sender) {}
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
 * @package Core
 */
abstract class Observable extends Object {

	/**
	 * Storage array for the properties with KVO (Key Value Observer) listeners
	 * @var array
	 */
	private $__kvo = array();

	/**
	 * The events/listeners. array($event1 => array($listener1, ...), ...)
	 * @abstract @var array
	 */
	protected $events = array();

	/**
	 * Trigger an event.
	 *
	 * @param string $event
	 * @param stdClass $sender
	 * @param mixed $args (optional)
	 */
	function trigger($event, $sender, $args = null) {
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
	 * Add a callback for an event
	 *
	 * @param string $event
	 * @param Closure|string|array $callback
	 * @param string $identifier  The
	 * @return bool
	 */
	function addListener($event, $callback, $identifier = null) {
		if (is_callable($callback) == false) {
			warning('Parameter $callback issn\'t callable', $callback);
			return false;
		}
		if ($this->hasEvent($event) === false) {
			warning('Event: "'.$event.'" not registered', 'Available events: '.quoted_human_implode(', ', array_keys($this->events)));
			return false;
		}
		if ($identifier === null) {
			$this->events[$event][] = $callback;
		} elseif (isset($this->events[$event][$identifier])) {
			warning('Identifier: "'.$identifier.'" in already in use for event: "'.$event.'"');
			return false;
		} else {
			$this->events[$event][$identifier] = $callback;
		}
		if ($event === 'change') {
			$properties = array_merge(array_keys(get_public_vars($this)), array_keys($this->__kvo));
			$self = $this;
			foreach ($properties as $property) {
				$this->addListener('change:'.$property, function ($sender, $new, $old) use ($self, $property) {
					$self->trigger('change', $sender, $property, $new, $old);
				});
			}
		}
		return true;
	}

	/**
	 * Check if the observable has registered the given event.
	 *
	 * @param string $event
	 * @return bool
	 */
	function hasEvent($event) {
		$found = array_key_exists($event, $this->events);
		if ($found === false) {
			if ($event === 'change') {
				return ((count(get_public_vars($this)) + count($this->__kvo)) !== 0); // A class without public properties doenst have a change event.
			}
			if (preg_match('/^change:([a-z0-9]+)$/i', $event, $matches)) {
				$property = $matches[1];
				if (property_exists($this, $property)) {
					$found = true;
					$this->__kvo[$property] = $this->$property;
					unset($this->$property);
				}
			}
		}
		return $found;
	}

	/**
	 * Remove a callback from an event
	 *
	 * @param string $event
	 * @param string $identifier
	 */
	function removeListener($event, $identifier) {
		if ($this->hasEvent($event) === false) {
			warning('Event: "'.$event.'" not registered', 'Available events: '.quoted_human_implode(', ', array_keys($this->events)));
			return false;
		}
		if (empty($this->events[$event][$identifier])) {
			warning('Identifier: "'.$identifier.'" not found in listeners for event: "'.$event.'"', 'Available identifiers: '.quoted_human_implode(', ', array_keys($this->events[$event])));
			return false;
		}
		unset($this->events[$event][$identifier]);
		return true;
	}

	/**
	 * Getting a non-existing or KVO property
	 *
	 * @param string $property
	 * @return mixed
	 */
	function __get($property) {
		if (array_key_exists($property, $this->__kvo)) {
			return $this->__kvo[$property];
		}
		return parent::__get($property);
	}

	/**
	 * Setting a non-existing or KVO property
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __set($property, $value) {
		if (preg_match('/^on[A-Z]/', $property)) { // An event? like "onClick"
			$event = lcfirst(substr($property, 2));
			if ($this->hasEvent($event)) {
				$this->events[$event] = array(); // Reset listeners.
				if ($value !== null) {
					$this->addListener($event, $value);
				}
				return;
			}
		}
		if (array_key_exists($property, $this->__kvo)) { // A property with a change listener?
			$this->__kvo[$property];
			$this->trigger('change:'.$property, $this, $value, $this->__kvo[$property]);
			$this->__kvo[$property] = $value;
			return;
		}
		parent::__set($property, $value);
	}

}

?>
