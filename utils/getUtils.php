<?php

use Sledgehammer\Core\FlushCache;
use Sledgehammer\Core\GenerateStaticAutoLoader;
use Sledgehammer\Devutils\UtilScript;


return array(
    'flush_cache.html' => new FlushCache(),
    'generate_AutoLoader.db.php' => new GenerateStaticAutoLoader(),
    'populate_Docroot.html' => new UtilScript('populate_DocumentRoot.php', 'Generate static public/ folder'),
);
