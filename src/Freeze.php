<?php

namespace Sledgehammer\Core;

/**
 * A wrapper that prevents changes to an object or array.
 */
class Freeze extends Wrapper
{
    /**
     * Don't allow setting any data in a read-only object/array.
     *
     * @param mixed  $value
     * @param string $element
     * @param string $context
     *
     * @throws \Exception
     */
    protected function in($value, $element, $context)
    {
        throw new \Exception('The ' . gettype($this->_data) . ' is marked readonly');
    }

    /**
     * Don't allow calling "any" method in a read-only object/array.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @throws \Exception
     */
    public function __call($method, $arguments)
    {
        throw new \Exception('The ' . gettype($this->_data) . ' is marked readonly');
    }
}
