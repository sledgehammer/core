<?php

namespace SledgehammerTests\Core;

/**
 * A PHPUnit TestCase.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Trigger an exception instead of a fatal error when using a invalid assert method.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $arguments)
    {
        throw new \BadMethodCallException('Method: PHPUnit_Framework_TestCase->'.$method.'() doesn\'t exist');
    }
}
