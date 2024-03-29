<?php

namespace Sledgehammer\Core;

/**
 * Wrap the object/array into container object
 * Allow filters and accesscontrol to any object or array.
 *
 * @see \Sledgehammer\Readonly for an example implementation.
 */
abstract class Wrapper extends Base implements \ArrayAccess, \Iterator
{
    /**
     * The wrapped object or array.
     *
     * @var object|array
     */
    protected $_data;

    /**
     * Wrap elements that are object or arrays.
     *
     * @var bool
     */
    private $_recursive = true;

    /**
     * Constructor.
     *
     * @param object/iterator/array $data
     * @param array                 $options
     */
    public function __construct($data, $options = [])
    {
        $this->_data = $data;
        foreach ($options as $name => $value) {
            $property = '_' . $name;
            if (property_exists($this, $property)) {
                $this->$property = $value;
            } else {
                \Sledgehammer\notice('Invalid option: "' . $name . '"');
            }
        }
    }

    /**
     * Process the value that is going out (is being retrieved from the wrapped object).
     *
     * @param mixed            $value
     * @param string           $element
     * @param "object"|"array" $context
     */
    protected function out($value, $element, $context)
    {
        if ($this->_recursive && (is_array($value) || is_object($value))) {
            // Return a wrapper with the same configuration as
            $clone = clone $this;
            $clone->_data = $value;

            return $clone;
        }

        return $value;
    }

    /**
     * Process the value before it's going in (is being set into the wrapped object).
     *
     * @param mixed            $value
     * @param string           $element
     * @param "object"|"array" $context
     */
    protected function in($value, $element, $context)
    {
        return $value;
    }

    /**
     * Magic getter en setter functies zodat je dat de eigenschappen zowel als object->eigenschap kunt opvragen.
     */

    /**
     * Retrieve a property.
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get($property)
    {
        return $this->out($this->_data->$property, $property, 'object');
    }

    /**
     * Set a property.
     *
     * @param string $property
     * @param mixed  $value
     */
    public function __set($property, $value)
    {
        $value = $this->in($value, $property, 'object');
        $this->_data->$property = $value;
    }

    /**
     * Call a method.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $filtered = [];
        foreach ($arguments as $key => $value) {
            $filtered[$key] = $this->in($value, $method . '[' . $key . ']', 'parameter');
        }

        return $this->out(call_user_func_array(array($this->_data, $method), $filtered), $method, 'method', $filtered);
    }

    /**
     * ArrayAccess: Retrieve an element.
     *
     * @param string $key
     */
    public function offsetGet($key): mixed
    {
        if (is_array($this->_data)) {
            return $this->out($this->_data[$key], $key, 'array');
        } elseif ($this->_data instanceof \ArrayAccess) {
            return $this->out($this->_data[$key], $key, 'array');
        } else {
            throw new \Exception('Cannot use object of type ' . get_class($this->_data) . ' as array');
        }
    }

    /**
     * ArrayAccess: Set an element.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function offsetSet($key, $value): void
    {
        if (is_array($this->_data)) {
            $value = $this->in($value, $key, 'array');
            $this->_data[$key] = $value;
        } elseif ($this->_data instanceof \ArrayAccess) {
            $value = $this->in($value, $key, 'array');
            $this->_data[$key] = $value;
        } else {
            throw new \Exception('Cannot use object of type ' . get_class($this->_data) . ' as array');
        }
    }

    /**
     * ArrayAccess: Remove an element.
     *
     * @param string $index
     */
    public function offsetUnset($index): void
    {
        throw new \Exception('Not (yet) supported');
    }

    /**
     * ArrayAccess: Check if an index exists.
     *
     * @param string $index
     */
    public function offsetExists($index): bool
    {
        throw new \Exception('Not (yet) supported');
    }

    /**
     * Iterator: Rewind the iterator to the first element.
     */
    public function rewind(): void
    {
        if ($this->_data instanceof \Iterator) {
            $this->_data->rewind();
            return;
        } elseif (is_array($this->_data)) {
            reset($this->_data);
        }
    }

    /**
     * Iterator: Retrieve the current element.
     */
    public function current(): mixed
    {
        if ($this->_data instanceof \Iterator) {
            return $this->out($this->_data->current(), 'current', 'iterator');
        } elseif (is_array($this->_data)) {
            return $this->out(current($this->_data), 'current', 'iterator');
        }
    }

    /**
     * Iterator: Move the next element.
     */
    public function next(): void
    {
        if ($this->_data instanceof \Iterator) {
            $this->_data->next();
        } elseif (is_array($this->_data)) {
            next($this->_data);
        }
    }

    /**
     * Iterator: Retrieve the current key.
     */
    public function key(): string|int
    {
        if ($this->_data instanceof \Iterator) {
            return $this->out($this->_data->key(), 'key', 'iterator');
        } elseif (is_array($this->_data)) {
            return $this->out(key($this->_data), 'key', 'iterator');
        }
    }

    /**
     * Iterator: Returns false when the end of the iterator is reached.
     */
    public function valid(): bool
    {
        if ($this->_data instanceof \Iterator) {
            return $this->_data->valid();
        } elseif (is_array($this->_data)) {
            return key($this->_data) !== null;
        }

        return false;
    }
}
