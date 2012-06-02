<?php
/**
 * De mappen die niet nodig zijn in productie omgeving verwijderen.
 * Denk aan de "docs" & "tests" mappen van de modules.
 */
namespace Sledgehammer;
echo "Cleaning modules...\n";
require_once(dirname(__FILE__).'/../../core/bootstrap.php');
$modules = Framework::getModules();
foreach ($modules as $module) {
	if (file_exists($module['path'].'.svn')) {
		echo "  FAILED: working copy detected.\nRun: svn export ".escapeshellarg(PATH)." ".escapeshellarg(dirname(PATH).'/release/'),"\n\n";
		return false;
	}
}

function clean_module_rmdir($path) {
	if (file_exists($path)) {
		$count = rmdirs($path);
		rmdir($path); // De map zelf ook verwijderen.
		return $count;

	}
	return 0;
}
$fileCount = 0;
foreach ($modules as $module) {
	$fileCount += clean_module_rmdir($module['path'].'docs/');
	$fileCount += clean_module_rmdir($module['path'].'tests/');
	//	$fileCount += clean_module_rmdir($module['path'].'utils/'); // Is dit slim?
}
clean_module_rmdir(PATH.'docs/');
clean_module_rmdir(PATH.'db/');

echo "  done. ".$fileCount." files removed.\n";

?>
