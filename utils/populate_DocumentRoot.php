<?php

/**
 * De echte public/ map (apache's documentroot) vullen met de bestanden uit de diverse public/ mappen.
 */

namespace Sledgehammer\Core;

require_once __DIR__.'/../../core/bootstrap.php';
if ($argc > 1) {
    $targetFolders = array_slice($argv, 1);
} else {
    // Detecteer de publieke folder(s)
    $targetFolders = [];
    $detectFolders = array('www', 'public');
    foreach ($detectFolders as $folder) {
        if (file_exists(\Sledgehammer\PATH.$folder.'/rewrite.php')) {
            $targetFolders[] = $folder;
        }
    }
}
if (count($targetFolders) == 0) {
    echo "  FAILED: No folders detected.\n";
    echo 'Usage: php '.basename(__FILE__)." folder1 [folder2]\n";
    echo "  \n";

    return false;
}
$modules = Framework::getModules();
$folders = [];
foreach ($modules as $folder => $info) {
    $modulePath = $info['path'];
    if (is_dir($modulePath.'public')) {
        if (\Sledgehammer\array_value($info, 'app')) {
            $folders[$modulePath.'public'] = '';
        } else {
            $folders[$modulePath.'public'] = '/'.$folder;
        }
    }
}
foreach ($targetFolders as $targetFolder) {
    $fileCount = 0;
    echo "\nPopulating /".$targetFolder." ...\n";

    foreach ($folders as $folder => $targetSuffix) {
        $targetPath = \Sledgehammer\PATH.$targetFolder.$targetSuffix;
        \Sledgehammer\mkdirs($targetPath);
        $fileCount += copydir($folder, $targetPath, array('.svn'));
    }
    echo '  '.$fileCount." files copied\n";
}
if (isset($modules['minify'])) {
    include $modules['minify']['path'].'utils/minify_DocumentRoot.php';
} else {
    echo "  done.\n";
}

return true;
