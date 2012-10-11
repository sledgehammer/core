<?php
/**
 * De mappen die niet nodig zijn in productie omgeving verwijderen.
 * Denk aan de "docs" & "tests" mappen van de modules.
 */
namespace Sledgehammer;
echo "Cleaning modules...\n";
require_once(__DIR__.'/../../core/bootstrap.php');

$fileCount = 0;
foreach ($modules as $module) {
	$fileCount += rmdir_recursive($module['path'].'docs/', true);
	$fileCount += rmdir_recursive($module['path'].'tests/', true);
}
$fileCount += rmdir_recursive(PATH.'docs/', true);

echo "  done. ".$fileCount." files removed.\n";

?>
