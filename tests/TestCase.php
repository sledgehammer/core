<?php

namespace SledgehammerTests\Core;

/**
 * A PHPUnit TestCase.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Constructor.
     *
     * @param string $name
     * @param array  $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

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
