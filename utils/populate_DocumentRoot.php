<?php
/**
 * De echte public/ map (apache's documentroot) vullen met de bestanden uit de diverse public/ mappen
 */
namespace Sledgehammer;

require_once(dirname(__FILE__).'/../../core/bootstrap.php');
if ($argc > 1) {
	$targetFolders = array_slice($argv, 1);
} else {
	// Detecteer de publieke folder(s)
	$targetFolders = array();
	$detectFolders = array('www', 'public');
	foreach ($detectFolders as $folder) {
		if (file_exists(PATH.$folder.'/rewrite.php')) {
			$targetFolders[] = $folder;
		}
	}
}
if (count($targetFolders) == 0) {
	echo "  FAILED: No folders detected.\n";
	echo "Usage: php ".basename(__FILE__)." folder1 [folder2]\n";
	echo "  \n";
	return false;
}
$modules = Framework::getModules();
$folders = array();
foreach ($modules as $folder => $info) {
	$modulePath = $info['path'];
	if (is_dir($modulePath.'public')) {
		if (array_value($info, 'application')) {
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
		$targetPath = PATH.$targetFolder.$targetSuffix;
		mkdirs($targetPath);
       	$fileCount += copydir($folder, $targetPath, array('.svn'));

	}
	echo '  '.$fileCount." files copied\n";
}
if (isset($modules['minify'])) {
	include($modules['minify']['path'].'utils/minify_DocumentRoot.php');
} else {
	echo "  done.\n";
}
return true;
?>
