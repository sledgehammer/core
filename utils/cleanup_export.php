<?php

/**
 * De mappen die niet nodig zijn in productie omgeving verwijderen.
 * Denk aan de "docs" & "tests" mappen van de modules.
 */

namespace Sledgehammer\Core;

echo "Cleaning modules...\n";
require_once __DIR__.'/../../core/bootstrap.php';

$fileCount = 0;
foreach ($modules as $module) {
    $fileCount += \Sledgehammer\rmdir_recursive($module['path'].'docs/', true);
    $fileCount += \Sledgehammer\rmdir_recursive($module['path'].'tests/', true);
}
$fileCount += \Sledgehammer\rmdir_recursive(\Sledgehammer\PATH.'docs/', true);

echo '  done. '.$fileCount." files removed.\n";
