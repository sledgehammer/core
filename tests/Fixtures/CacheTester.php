<?php

namespace SledgehammerTests\Core\Fixtures;

use Sledgehammer\Core\Cache;

/**
 * TestButton, An class for testing an Observable.
 *
 */
class CacheTester extends Cache
{
    public function __construct($identifier, $backend)
    {
        parent::__construct($identifier, $backend);
    }
}
