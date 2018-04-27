<?php

namespace Sledgehammer\Core;

use ArrayAccess;
use ArrayIterator;
use Exception;
use Iterator;

/**
 * Wrap an object inside a CacheWrapper to cache all method calls and properties.
 */
class CacheWrapper extends Base implements ArrayAccess, Iterator
{
    /**
     * The wrapped object.
     *
     * @var object
     */
    private $object;

    /**
     * The cache node.
     *
     * @var Cache
     */
    private $cachePath;
    private $expires;
    private $iterator;

    /**
     * Constructor.
     *
     * @param object $object
     * @param string $cachePath Unique path for the cache
     * @param int ttl  Time-to-Live in seconds
     */
    public function __construct($object, $cachePath, $expires)
    {
        $this->object = $object;
        $this->cachePath = $cachePath;
        $this->expires = $expires;
    }

    public function __get($property)
    {
        $path = $this->cachePath.'->'.$property;
        $object = $this->object;
        $value = \Sledgehammer\cache($path, $this->expires, function () use ($object, $property) {
            return $object->$property;
        });
        if (is_object($value)) {
            $value = new self($value, $path, $this->expires);
        }

        return $value;
    }

    public function __call($method, $arguments)
    {
        $key = $method.'(';
        foreach ($arguments as $i => $argument) {
            if ($i !== 0) {
                $key .= ', ';
            }
            if (is_array($argument) || is_object($argument)) {
                $key .= serialize($argument);
            } elseif (is_string($argument)) {
                $key .= "'".$argument."'";
            } else {
                $key .= $argument;
            }
        }
        $key .= ')';
        $path = $this->cachePath.'['.PropertyPath::escape($key).']';
        $object = $this->object;
        $value = \Sledgehammer\cache($path, $this->expires, function () use ($object, $method, $arguments) {
            return call_user_func_array([$object, $method], $arguments);
        });
        if (is_object($value)) {
            $value = new self($value, $path, $this->expires);
        }

        return $value;
    }

    public function offsetExists($offset)
    {
        return $this->__call('offsetExists', [$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->__call('offsetGet', [$offset]);
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception('Not supported for a Cached object');
    }

    public function offsetUnset($offset)
    {
        throw new Exception('Not supported for a Cached object');
    }

    public function current()
    {
        return $this->cachedIterator()->current();
    }

    public function key()
    {
        return $this->cachedIterator()->key();
    }

    public function next()
    {
        return $this->cachedIterator()->next();
    }

    public function rewind()
    {
        return $this->cachedIterator()->rewind();
    }

    public function valid()
    {
        return $this->cachedIterator()->valid();
    }

    /**
     * @return Iterator
     */
    private function cachedIterator()
    {
        if ($this->iterator !== null) {
            return $this->iterator;
        }
        $object = $this->object;
        $value = \Sledgehammer\cache($this->cachePath.':Iterator', $this->expires, function () use ($object) {
            return iterator_to_array($object);
        });
        $this->iterator = new ArrayIterator($array);

        return $this->iterator;
    }
}
