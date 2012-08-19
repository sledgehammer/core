<?php
/**
 * Observable
 */
namespace Sledgehammer;
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
 * $button->on('click', function ($sender) {
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
	 * @return string identifier
	 */
	function on($event, $callback) {
		if (is_callable($callback) == false) {
			throw new InfoException('Parameter $callback issn\'t callable', $callback);
		}
		if ($this->hasEvent($event) === false) {
			$availableEvents = array_keys($this->events);
			$availableEvents[] = 'change';
			foreach (array_keys(get_public_vars($this)) as $property) {
				$availableEvents[] = 'change:'.$property;
			}
			throw new InfoException('Event: "'.$event.'" not registered', 'Available events: '.quoted_human_implode(', ', array_keys($this->events)));
		}
		$this->events[$event][] = $callback;
		end($this->events[$event]);
		$identifier = key($this->events[$event]);
		if ($event === 'change') {
			$properties = array_merge(array_keys(get_public_vars($this)), array_keys($this->__kvo));
			$self = $this;
			foreach ($properties as $property) {
				$this->on('change:'.$property, function ($sender, $new, $old) use ($self, $property) {
						$self->trigger('change', $sender, $property, $new, $old);
					});
			}
		}
		if (preg_match('/^change:([a-z0-9]+)$/i', $event, $matches)) {
			$property = $matches[1];
			if (array_key_exists($matches[1], $this->__kvo) === false) {
				$this->__kvo[$property] = $this->$property;
				unset($this->$property);
			}
		}
		return $identifier;
	}

	/**
	 * Check if the observable has registered the given event.
	 *
	 * @param string $event
	 * @return bool
	 */
	function hasEvent($event) {
		$found = array_key_exists($event, $this->events);
		if ($found) {
			return true;
		}
		if ($event === 'change') {
			return ((count(get_public_vars($this)) + count($this->__kvo)) !== 0); // A class without public properties doesn't have a change event.
		}
		if (preg_match('/^change:([a-z0-9]+)$/i', $event, $matches)) {
			if (array_key_exists($matches[1], $this->__kvo)) {
				return true;
			}
			$reflection = new \ReflectionObject($this);
			$properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
			foreach ($properties as $property) {
				if ($property->name === $matches[1]) { // a public property exists?
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove a callback from an event
	 *
	 * @param string $event
	 * @param string $identifier
	 */
	function off($event, $identifier) {
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
					$this->on($event, $value);
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