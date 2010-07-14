<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */

class CoreRequirementsTest extends UnitTestCase {

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

	/**
	 * Controleer de Library
	 */
	function test_library_extract_definitions() {
		$Library = new Library(PATH);
		$Library->enable_cache = false;
		$modules = SledgeHammer::getModules();
		$Library->extract_definitions_from_modules($modules);
		$this->assertTrue($Library->validate(), '$Library->validate() should return true');
	}

	/**
	 * Controleer de Library database (library.db.php)
	 *
	 * @todo: Controleren of de inhoud van de library.db.php niet verouderd is. 
	 */
	function test_library_db() {
		if (file_exists(PATH.'library.db.php')) {
			$php_code = file_get_contents(PATH.'library.db.php');
			$php_code = substr($php_code, 5, -3); // <?php eraf halen
			$this->assertNull(eval($php_code), 'library.db.php zou geen php fouten mogen bevatten');
			$this->assertTrue(isset($classes), '$classes zou gedefineerd moeten zijn');
			$this->assertTrue(isset($interfaces), '$interfaces zou gedefineerd moeten zijn');
		}
	}

	function test_environment() {
		// unittest wordt vanuit de php cli uitgevoerd. $_SERVER bevat dus geen SERVER_ADMIN.
		// $this->assertPattern("/^([a-z0-9._-](\+[a-z0-9])*)+@[a-z0-9.-]+\.[a-z]{2,6}$/i", $_SERVER['SERVER_ADMIN'], '$_SERVER[SERVER_ADMIN] zou een emailadres moeten zijn');
		//$allowedEnvironments = array('development', 'staging', 'production');
		//$this->assertTrue(in_array(ENVIRONMENT, $allowedEnvironments), 'ENVIRONMENT moet een van de volgende waarden zijn: "'.human_implode('" of "', $allowedEnvironments, '", "').'"');
	}

	function test_module_ini() {
		$modules = SledgeHammer::getModules();
		foreach ($modules as $index => $module) {
			if ($index == 'application') {
				continue;
			}
			$module_ini = parse_ini_file($module['path'].'module.ini', true);
			$this->assertTrue(isset($module_ini['owner']), 'Module: "'.$module['name'].'" zou een "owner" moeten hebben');
			if ($this->assertTrue(isset($module_ini['owner_email']), 'Module: "'.$module['name'].'" zou een "owner_email" moeten hebben')) {
				// @todo: Email notatie controleren
			}
			//if ($this->assertTrue(isset($module_ini['version']), 'Module: "'.$module['name'].'" zou een "version" moeten hebben')) {
				// @todo: Versie speficatie controleren
			//}
		}
	}

	function test_constants_ini() {
		$modules = SledgeHammer::getModules();
		foreach ($modules as $name => $module) {
			if (file_exists($module['path'].'constants.ini') || file_exists($module['path'].'settings/constants.ini')) {
				$this->fail('constants.ini gevonden in "'.$name.'". Deze worden niet meer ondersteund');
			}
		}
	}
}
?>
