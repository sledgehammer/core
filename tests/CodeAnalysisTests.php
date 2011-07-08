<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */
namespace SledgeHammer;
class CodeAnalysisTests extends \UnitTestCase {

	function test_definitions() {
		$loader = new AutoLoader(PATH);
		$loader->enableCache = false;
		$modules = Framework::getModules();
		foreach ($modules as $module) {
			$loader->importModule($module);
		}
		
		if (file_exists(PATH.'AutoLoader.db.php')) {
			$php_code = file_get_contents(PATH.'AutoLoader.db.php');
			$php_code = substr($php_code, 5, -3); // <?php eraf halen
			$this->assertNull(eval($php_code), 'AutoLoader.db.php zou geen php fouten mogen bevatten');
			$this->assertTrue(isset($definitions), '$definitions zou gedefineerd moeten zijn');

			// @todo: Controleren of de inhoud van de autoloader.db.php niet verouderd is.
		}
	}
	
	/**
	 * Crawl the codebase and validate if all used classes are available
	 */
	function test_spider_codebase() {
		$analyzer = new PHPAnalyzer();
		$modules = Framework::getModules();
		foreach ($modules as $module) {
			if (file_exists($module['path'].'init.php')) {
				$analyzer->open($module['path'].'init.php');
			}
			if (file_exists($module['path'].'functions.php')) {
				$analyzer->open($module['path'].'functions.php');
			}
		}
		$analyzer->open(PATH.'public/rewrite.php');
		$definitionCount = 0;
		$passes = array();
		$failed = false;
		while ($definitionCount != count($analyzer->usedDefinitions)) {
			$newDefinitions = array_slice($analyzer->usedDefinitions, $definitionCount);
			$definitionCount = count($analyzer->usedDefinitions);
			foreach (array_keys($newDefinitions) as $definition) {
				if ($this->tryGetInfo($analyzer, $definition) == false) {
					$failed = true;
				}
			}
			$passes[] = count($newDefinitions);
		}
		if ($failed == false) {
			$this->pass('The '.count($analyzer->usedDefinitions).' detected definitions are found');
		}
//		dump($passes);
	}

	/**
	 * Analize all known classes and validate if all classes are available
	 */
	function test_entire_codebase() {
		$analyzer = new PHPAnalyzer();
		$files = $this->getDefinitionFiles();
		foreach ($files as $filename) {
			$analyzer->open($filename);
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
		// Check all used definitions
		$failed = false;
		foreach (array_keys($analyzer->usedDefinitions) as $definition) {
			if ($this->tryGetInfo($analyzer, $definition) == false) {
				$failed = true;
			}
		}
		if ($failed == false) {
			$this->pass('All '.count($analyzer->usedDefinitions).' definitions are found');
		}
	}
	
	/**
	 *
	 * @param PHPAnalyzer $analyzer
	 * @param string $definition 
	 * @return bool  Success
	 */
	private function tryGetInfo(PHPAnalyzer $analyzer, $definition) {
		try {
			$analyzer->getInfo($definition);
		}  catch (\Exception $e) {
			$suffix = ' (used in';
			foreach ($analyzer->usedDefinitions[$definition] as $filename => $lines) {
				$suffix .= ' "'.$filename.'" on line';
				if (count($lines) == 1) {
					$suffix .= ' '.$lines[0];
				} else {
					$suffix .= 's '.human_implode(' and ', $lines);
				}
			}
			$suffix .= ')';
			$this->fail($e->getMessage().$suffix);
			return false;
		}
		return true;
	}
	
	private function getDefinitionFiles() {
		$definitions = $GLOBALS['AutoLoader']->getDefinitions();
		$files = array();
		foreach ($definitions as $definition) {
			$files[] = $GLOBALS['AutoLoader']->getFilename($definition);
		}
		return array_unique($files);
	}
}
?>
