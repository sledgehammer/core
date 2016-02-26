<?php

namespace Sledgehammer\Core;

/**
 * Helper class for manual performance profiling.
 */
class Stopwatch extends Object
{
    /**
     * Timestamp.
     *
     * @var float
     */
    private $start;

    /**
     * Timestamp.
     *
     * @var float
     */
    private $lap;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Reset the counters.
     */
    public function reset()
    {
        $this->start = microtime(true);
        $this->lap = $this->start;
    }

    /**
     * Return the timeinterval since the stopwatch started.
     *
     * @return string
     */
    public function getElapsedTime()
    {
        $elapsed = microtime(true) - $this->start;

        return \Sledgehammer\format_parsetime($elapsed).' sec';
    }

    /**
     * Return the timeinterval since the last getLapTime().
     *
     * @return string
     */
    public function getLapTime()
    {
        $now = microtime(true);
        $elapsed = $now - $this->lap;
        $this->lap = $now;

        return \Sledgehammer\format_parsetime($elapsed).' sec';
    }
}
