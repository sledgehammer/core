<?php
/**
 * Controleer diverse SledgeHammer vereisten
 */
namespace SledgeHammer;
class CodeAnalysisTest extends TestCase {

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

	function donttest_single_file() {
		$phpAnalyzer = new PHPAnalyzer();
		$info = $phpAnalyzer->getInfo('CssMin');
		dump($info);
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
		if (file_exists(PATH.'public/rewrite.php')) {
			$analyzer->open(PATH.'public/rewrite.php');
		}
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
			$this->assertTrue(true, 'The '.count($analyzer->usedDefinitions).' detected definitions are found');
		}
//		dump($passes);
	}

	/**
	 * Analize all known classes and validate if all classes are available
	 */
	function test_known_classes() {
		$definitions = Framework::$autoLoader->getDefinitions();
		$files = array();
		foreach ($definitions as $definition) {
			$files[] = Framework::$autoLoader->getFilename($definition);
		}
		$files = array_unique($files);

		$analyzer = new PHPAnalyzer();
		foreach ($files as $filename) {
			try {
				$analyzer->open($filename);
			}  catch (\Exception $e) {
//				ErrorHandler::handle_exception($e);
				$this->fail($e->getMessage());
			}
		}
		/* checking parents wordt ook gedaan door de used check
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
		}*/
		// Check all used definitions
		$failed = false;
		foreach (array_keys($analyzer->usedDefinitions) as $definition) {
			if ($this->tryGetInfo($analyzer, $definition) == false) {
				$failed = true;
			}
		}
		if ($failed == false) {
			$this->assertTrue(true, 'All '.count($analyzer->usedDefinitions).' definitions are found');
		}
	}

	function donttest_entire_codebase() {
		$loader = new AutoLoader(PATH);
		$loader->importFolder(PATH, array(
			'matching_filename' => false,
			'mandatory_definition' => false,
			'mandatory_superclass' => false,
			'one_definition_per_file' => false,
			'detect_accidental_output' => false,
		)); // Import all
		//
		$analyzer = new PHPAnalyzer();
		$this->analyzeDirectory($analyzer, PATH);
		// Check all used definitions
		$failed = false;
		foreach (array_keys($analyzer->usedDefinitions) as $definition) {
			if ($this->tryGetInfo($analyzer, $definition) == false) {
				$failed = true;
			}
		}
		if ($failed == false) {
			$this->assertTrue(true, 'All '.count($analyzer->usedDefinitions).' definitions are found');
		}
	}

	private function analyzeDirectory($analyzer, $path) {
		$dir = new \DirectoryIterator($path);
		foreach ($dir as $entry) {
			if ($entry->isDot()) {
				continue;
			}
			if ($entry->isDir()) {
				$this->analyzeDirectory($analyzer, $entry->getPathname());
				continue;
			}
			$ext = file_extension($entry->getFilename());
			if (in_array($ext, array('php'))) {
				try {
					$analyzer->open($entry->getPathname());
				}  catch (\Exception $e) {
//					ErrorHandler::handle_exception($e);
					$this->fail($e->getMessage());
				}
			}
		}
	}

	/**
	 * @param PHPAnalyzer $analyzer
	 * @param string $definition
	 * @return bool  Success
	 */
	private function tryGetInfo(PHPAnalyzer $analyzer, $definition) {
		if (in_array($definition,array('AutoCompleteTestRepository', 'PHPUnit_Framework_TestCase'))) {
			return true;
		}
		try {
			$analyzer->getInfo($definition);
			return true;
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
	}
}
?>
