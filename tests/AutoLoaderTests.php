<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */

class AutoLoaderTests extends UnitTestCase {

	function test_definitions() {
		$loader = new AutoLoader(PATH);
		$loader->enableCache = false;
		$modules = SledgeHammer::getModules();
		$loader->inspectModules($modules);
		$this->assertTrue($loader->validate(), 'AutoLoader->validate() should return true');
	}

	/**
	 * Controleer de Library database (autoloader.db.php)
	 */
	function test_library_db() {
		if (file_exists(PATH.'autoloader.db.php')) {
			$php_code = file_get_contents(PATH.'autoloader.db.php');
			$php_code = substr($php_code, 5, -3); // <?php eraf halen
			$this->assertNull(eval($php_code), 'autoloader.db.php zou geen php fouten mogen bevatten');
			$this->assertTrue(isset($classes), '$classes zou gedefineerd moeten zijn');
			$this->assertTrue(isset($interfaces), '$interfaces zou gedefineerd moeten zijn');

			// @todo: Controleren of de inhoud van de autoloader.db.php niet verouderd is.
		}
	}
}
?>
