<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */
namespace SledgeHammer;
class SledgeHammerEnvironmentChecks extends \UnitTestCase {

	/**
	 * Controleer of de php.ini instellingen goed staan
	 */
	function test_php_ini() {
		$this->assertFalse(ini_get('session.auto_start'), 'php.ini[session.auto_start] moet uit staan');
		$this->assertFalse(ini_get('register_globals'), 'php.ini[register_globals] moet uit staan');
		$this->assertFalse(ini_get('magic_quotes_gpc'), 'php.ini[magic_quotes_gpc] moet uit staan');
	}

	/**
	 * Controleer de PHP versie
	 */
	function test_php_version() {
		$this->assertTrue(version_compare(PHP_VERSION, '5.2.6', '>='), 'PHP should be version 5.2.6 or higher'); 
	}

	/**
	 * Controleer de tmp map
	 */
	function test_tmp_folder() {
		$this->assertTrue(is_writable(PATH.'tmp/'), 'De tmp map zou beschrijfbaar moeten zijn');
	}

	function test_environment() {
		$allowedEnvironments = array('development', 'staging', 'production');
		$this->assertTrue(in_array(ENVIRONMENT, $allowedEnvironments), 'ENVIRONMENT moet een van de volgende waarden zijn: "'.human_implode('" of "', $allowedEnvironments, '", "').'"');
	}

	function test_module_ini() {
		$modules = Framework::getModules();
		foreach ($modules as $index => $module) {
			if ($index == 'application') {
				continue;
			}
			$module_ini = parse_ini_file($module['path'].'module.ini', true);
			$this->assertTrue(isset($module_ini['name']), 'A module.ini should contain a "name" value');
			//$this->assertTrue(isset($module_ini['owner']), 'Module: "'.$module['name'].'" zou een "owner" moeten hebben');
			//if ($this->assertTrue(isset($module_ini['owner_email']), 'Module: "'.$module['name'].'" zou een "owner_email" moeten hebben')) {
				// @todo: Email notatie controleren
			//}
			$this->assertTrue(isset($module_ini['version']), 'Module: "'.$module['name'].'" zou een "version" moeten hebben');
		}
	}

	function test_deprecated_constants_ini() {
		$modules = Framework::getModules();
		foreach ($modules as $name => $module) {
			if (file_exists($module['path'].'constants.ini') || file_exists($module['path'].'settings/constants.ini')) {
				$this->fail('constants.ini gevonden in "'.$name.'". Deze worden niet meer ondersteund');
			}
		}
	}
}
?>
