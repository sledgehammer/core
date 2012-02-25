<?php
namespace SledgeHammer;
/**
 * Bootstrap the SledgeHammer Framework for PHPUnit
 *
 * @package Core
 */
const ENVIRONMENT = 'development';
require(dirname(__FILE__).'/init_framework.php');
// Make all the classes inside the tests folders
$modules = Framework::getModules();
foreach ($modules as $module) {
	if (is_dir($module['path'].'tests')) {
		$GLOBALS['AutoLoader']->importFolder($module['path'].'tests', array(
			'mandatory_definition' => false,
		));
	}
}
?>
