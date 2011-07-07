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
	
	function donttest_tokenizer_merged_output() {
		$files = $this->getDefinitionFiles();
		foreach ($files as $filename) {
			$this->assertEqualTokenizer($filename); 
		}
	}	
	


	
	function test_compilation_errors() {
		restore_error_handler();
		$analyzer = new PHPAnalyzer();

		$filename = $GLOBALS['AutoLoader']->getFilename('TestOfSimpleSeleniumRemoteControl');
		$this->assertEqualTokenizer($filename);
		
		try {
			$tokenizer = new PHPTokenizer(file_get_contents($filename));
			$tokens = iterator_to_array($tokenizer);
			foreach ($tokens as $token) {
				if (strpos($token[1], '{') !== false) {
					if ($token[0] != 'T_OPEN_BRACKET' || $token[1] != '{') {
					//	dump($token);
					}
				}
			}
			dump($tokens);
//			dump($tokenizer);

			dump($analyzer->getInfo('SledgeHammer\CSVIterator'));
			
			$files = $this->getDefinitionFiles();
			foreach ($files as $filename) {
				$analyzer->open($filename);
			}
		
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
			$this->fail($e->getMessage());
		}
//		dump($analyzer->getInfoWithReflection('SledgeHammer\CSVIterator'));


		

//		dump($analyzer->getInfo('SledgeHammer\Component'));
//		dump($analyzer->getInfo('ArrayAccess'));
//		dump($analyzer->getInfo('stdClass'));
//		dump($analyzer->getInfo('ArrayIterator'));

	//	die('OK?');
		
		
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
	
	private function assertEqualTokenizer($filename) {
		$content = file_get_contents($filename); //$GLOBALS['AutoLoader']->getFilename('SledgeHammer\GoogleAnalytics');
		try {
			$tokenIterator = new PHPTokenizer($content);
			$mergedTokens = '';
			$tokens = array();
			foreach ($tokenIterator as $token) {
				$mergedTokens .= $token[1];
				$tokens[] = $token;
			}
			$this->assertEqual($content, $mergedTokens, 'Input should match all tokens combined (file: "'.$filename.'")');
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
			$this->fail($e->getMessage());
		}
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
