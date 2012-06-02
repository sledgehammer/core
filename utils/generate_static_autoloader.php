<?php
/**
 * Rebuild the Library database-file based on the installed modules
 */
namespace Sledgehammer;
$library_db_folder = realpath(dirname(__FILE__).'/../../../');
if ($library_db_folder == '') {
	trigger_error('Invalid directory structure, expection "$folder/modules/core/"', E_USER_ERROR);
}
if (file_put_contents($library_db_folder.'/AutoLoader.db.php', '<?php $classes = array(); $interfaces = array(); ?>') === false) { // Een "leeg" library.db.php bestand wegschrijven, zodat de core/init.php zonder problemen ingeladen kan worden.
	trigger_error('Unable to write to "AutoLoader.db.php"', E_USER_ERROR);
}
require_once(dirname(__FILE__).'/../bootstrap.php');

echo "Resolving required modules...\n";
$modules = Framework::getModules();
$max_length_module = 0;
foreach ($modules as $module) {
	if (strlen($module['name']) > $max_length_module) {
		$max_length_module = strlen($module['name']);
	}
}

echo "Scanning classes and interfaces...\n";
ini_set('memory_limit', '128M'); // Bij grote hoeveelheden classes (1000+) gebruikt php token_get_all() onnodig veel geheugen
$Loader = new AutoLoader(PATH);
$Loader->enableCache = false;
foreach ($modules as $module) {
	$Loader->importModule($module);
}
echo "Writing library...\n";
$Loader->writeCache(PATH.'AutoLoader.db.php');
if (file_exists(PATH.'AutoLoader.db.php')) {
	echo "  done.\n";
} else {
	echo "  failed.\n";
	exit(0);
}
?>
