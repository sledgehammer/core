<?php

use Sledgehammer\Core\Util\FlushCache;
use Sledgehammer\Core\Util\GenerateStaticAutoLoader;


return array(
    'flush_cache.html' => new FlushCache(),
    'generate_AutoLoader.db.php' => new GenerateStaticAutoLoader(),
);
