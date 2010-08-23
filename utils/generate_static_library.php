<?php
/**
 * Rebuild the Library database-file based on the installed modules
 */
$library_db_folder = realpath(dirname(__FILE__).'/../../../');
if ($library_db_folder == '') {
	trigger_error('Invalid directory structure, expection "$folder/modules/core/"', E_USER_ERROR);
}
if (file_put_contents($library_db_folder.'/autoloader.db.php', '<?php $classes = array(); $interfaces = array(); ?>') === false) { // Een "leeg" library.db.php bestand wegschrijven, zodat de core/init.php zonder problemen ingeladen kan worden.
	trigger_error('Unable to write to "autoloader.db.php"', E_USER_ERROR);
}
require_once(dirname(__FILE__).'/../init.php');
//$ErrorHandler->cli = true; // Forceer foutmeldingen

echo "Resolving required modules...\n";
$modules = SledgeHammer::getModules();
$max_length_module = 0;
foreach ($modules as $module) {
	if (strlen($module['name']) > $max_length_module) {	
		$max_length_module = strlen($module['name']);
	}
}

echo "Scanning classes and interfaces...\n";
ini_set('memory_limit', '32M'); // Bij grote hoeveelheden classes (1000+) gebruikt php token_get_all() onnodig veel geheugen
$Loader = new AutoLoader(PATH);
$Loader->enableCache = false;
$summary = $Loader->inspectModules($modules);
foreach ($summary as $module => $summary) {
	echo '  '.str_pad($module, $max_length_module). ' : '.$summary['classes'] .' classes';
	if ($summary['interfaces']) {
		echo ' and '.$summary['interfaces'].' interfaces';
	}
	echo "\n";
}
echo "Validating library...\n";
$Loader->validate();
echo "Writing library...\n";
if ($Loader->saveDatabase()) {
	echo "  done.\n";
} else {
	echo "  failed.\n";
	exit(0);
}
?>
