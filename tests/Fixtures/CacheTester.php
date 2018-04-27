<?php

namespace SledgehammerTests\Core\Fixtures;

use Sledgehammer\Core\Cache;

class CacheTester extends Cache
{
    public function __construct($identifier, $backend)
    {
        parent::__construct($identifier, $backend);
    }
}
