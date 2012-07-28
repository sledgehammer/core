<?php
/**
 * Rebuild the Library database-file based on the installed modules
 */
namespace Sledgehammer;
$projectFolder = realpath(dirname(__FILE__).'/../../../');
if ($projectFolder == '') {
	trigger_error('Invalid directory structure, expection "$folder/sledgehammer/core/"', E_USER_ERROR);
}
require_once(dirname(__FILE__).'/../bootstrap.php');

echo "Resolving required modules...\n";
$modules = Framework::getModules();
$maxLength = 0;
foreach ($modules as $module) {
	if (strlen($module['name']) > $maxLength) {
		$maxLength = strlen($module['name']);
	}
}

echo "Scanning classes and interfaces...\n";
ini_set('memory_limit', '128M'); // Bij grote hoeveelheden classes (1000+) gebruikt php token_get_all() onnodig veel geheugen
$loader = new AutoLoader(PATH);
$loader->enableCache = false;
foreach ($modules as $module) {
	$path = $module['path'];
	if (file_exists($module['path'].'classes')) { // A sledgehammer folder layout?
		$path = $path.'classes'; // Only import the classes folder
		$settings = array(); // Use the strict default settings
	} else {
		// Disable validations
		$settings = array(
			'matching_filename' => false,
			'mandatory_definition' => false,
			'mandatory_superclass' => false,
			'one_definition_per_file' => false,
			'revalidate_cache_delay' => 20,
			'detect_accidental_output' => false,
		);
	}
	$loader->importFolder($path, $settings);
}
echo "Writing database...\n";
$loader->saveDatabase(PATH.'AutoLoader.db.php');
if (file_exists(PATH.'AutoLoader.db.php')) {
	echo "  done.\n";
} else {
	echo "  failed.\n";
	exit(0);
}
?>
