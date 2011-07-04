<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */
namespace SledgeHammer;
class AutoLoaderTests extends \UnitTestCase {

	function test_definitions() {
		$loader = new AutoLoader(PATH);
		$loader->enableCache = false;
		$modules = Framework::getModules();
		foreach ($modules as $module) {
			$loader->importModule($module);
		}
		
		if (file_exists(PATH.'AutoLoader.db.php')) {
			$php_code = file_get_contents(PATH.'autoloader.db.php');
			$php_code = substr($php_code, 5, -3); // <?php eraf halen
			$this->assertNull(eval($php_code), 'autoloader.db.php zou geen php fouten mogen bevatten');
			$this->assertTrue(isset($classes), '$classes zou gedefineerd moeten zijn');
			$this->assertTrue(isset($interfaces), '$interfaces zou gedefineerd moeten zijn');

			// @todo: Controleren of de inhoud van de autoloader.db.php niet verouderd is.
		}
	}
	
	function test_availability() {
		$loader = new AutoLoader(PATH);
		$loader->init();
		$definitions = $loader->getDefinitions();
		$analyzer = new PHPAnalyzer();
		//set_error_handler('SledgeHammer\ErrorHandler_trigger_error_callback');

		foreach ($definitions as $definition) {
			$filename = $loader->getFilename($definition);
			$analyzer->open(PATH.$filename);
		}
		
		foreach ($analyzer->classes as $class => $info) {
			// check parent class
			if (isset($info['extends'])) {
				if (!isset($analyzer->classes[$info['extends']]) && !class_exists($info['extends'], false)) {
					notice('Parent class "'.$info['extends'].'" not found for class "'.$class.'" in '.$info['filename']);
				}
			}
			
			if (isset($info['implements'])) {
				foreach ($info['implements'] as $interface) {
					if (!isset($analyzer->interfaces[$interface]) && !interface_exists($interface, false)) {
						notice('Interface "'.$interface.'" not found for class "'.$class.'" in '.$info['filename']);
					}
				}
			}
		}
		foreach ($analyzer->interfaces as $interface => $info) {
			// check parent interface(s)
			if (isset($info['extends'])) {
				foreach ($info['extends'] as $parentInterface) {
					if (!isset($analyzer->interfaces[$parentInterface]) && !interface_exists($parentInterface, false)) {
						notice('Parent interface "'.$parentInterface.'" not found for interface "'.$interface.'" in '.$info['filename']);
					}
				}
			}
		}
	}
}
?>
