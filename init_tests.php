<?php
/**
 * Bootstrap the Sledgehammer Framework for PHPUnit runner
 *
 * @package Core
 */
namespace Sledgehammer;
const ENVIRONMENT = 'development';
require(dirname(__FILE__).'/init_framework.php');
// Make all the classes inside the tests folders
$modules = Framework::getModules();
foreach ($modules as $module) {
	if (is_dir($module['path'].'tests')) {
		Framework::$autoLoader->importFolder($module['path'].'tests', array(
			'mandatory_definition' => false,
		));
	}
}
?>
