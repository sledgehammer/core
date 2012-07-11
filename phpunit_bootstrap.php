<?php
/**
 * Bootstrap the Sledgehammer Framework for PHPUnit runner
 *
 * @package Core
 */
namespace Sledgehammer;
const ENVIRONMENT = 'phpunit';
require(dirname(__FILE__).'/bootstrap.php');
// Make all the classes inside the tests folders available to the AutoLoader
$modules = Framework::getModules();
foreach ($modules as $module) {
	if (is_dir($module['path'].'tests')) {
		Framework::$autoLoader->importFolder($module['path'].'tests', array(
			'mandatory_definition' => false,
		));
	}
}
// Disable error & exception handlers
for ($i = 1; $i < 5; $i++) {
	restore_error_handler();
	restore_exception_handler();
}
?>
