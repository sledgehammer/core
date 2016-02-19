<?php

namespace SledgehammerTests\Core\Fixtures;

use Sledgehammer\Core\Cache;
use SledgehammerTests\Core\ObservableTest;

/**
 * TestButton, An class for testing an Observable.
 *
 * @see ObservableTest
 */
class CacheTester extends Cache
{
    public function __construct($identifier, $backend)
    {
        parent::__construct($identifier, $backend);
    }
}
