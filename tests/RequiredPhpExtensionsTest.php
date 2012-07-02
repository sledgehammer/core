<?php
/**
 * Controleer of alle benodige php extenties geinstalleerd zijn
 */
namespace Sledgehammer;
class RequiredPhpExtensionsTest extends TestCase {

	/**
	 * Switch off to scan all files in the PATH
	 * @var bool
	 */
	private $onlyClassesFolder = true;

	/**
	 * Assoc array waarvan de key de functie/class is en de value de extentie.
	 * @var array
	 */
	private $definitionToExtension = array();

	/**
	 * Assoc array met als key de extentie gevult met alle bestanden die deze extentie gebruiken.
	 * @var array
	 */
	private $extensionUsedIn = array();

	/**
	 *
	 * @var array
	 */
	private $missingExtensions = array();

	/**
	 * tests/data/required_php_extentions.db.php inlezen en omzetten naar de $function_to_extention_map
	 */
	function __construct() {
		$functions_per_extention = include(dirname(__FILE__).'/data/required_php_extentions_functions.db.php');
		foreach ($functions_per_extention as $extention => $functions_or_classes) {
			foreach ($functions_or_classes as $function_or_class) {
				if (isset($this->definitionToExtension[strtolower($function_or_class)])) {
					trigger_error('Duplicate entry "'.$function_or_class.'"', E_USER_NOTICE);
				}
				$this->definitionToExtension[strtolower($function_or_class)] = $extention;
			}
		}
	}

	/**
	 * Controleer of de php extenties geinstalleerd zijn
	 */
	function test_missing_extentions() {
		if (!function_exists('token_get_all')) {
			$this->fail('PHP extention "tokenizer" is required for this UnitTest');
			return;
		}
		if ($this->onlyClassesFolder) { // Alleen de classes mappen van de modules inlezen
			$modules = Framework::getModules();
			foreach ($modules as $module) {
				$this->checkFolder($module['path'].'classes/');
			}
		} else { // check all php files within $path
			$this->checkFolder(PATH);
		}
		foreach ($this->missingExtensions as $extension => $definition) {
			$this->fail('Missing php extension "'.$extension.'". Function or class "'.$definition.'" is used in '.quoted_human_implode(' and', $this->extensionUsedIn[$extension]));
		}
		$this->assertTrue(true, 'All required extenstion are installed');
	}

	/**
	 * Scan a folder and subfolders for *.php files.
	 * @param string $folder
	 * @return void
	 */
	private function checkFolder($folder) {
		if (!file_exists($folder)) {
			return;
		}
		$DirectoryIterator = new \DirectoryIterator($folder);
		foreach ($DirectoryIterator as $Entry) {
			if ($Entry->isDot() || $Entry->getFilename() == '.svn') {
				continue;
			}
			if ($Entry->isDir()) {
				$this->checkFolder($Entry->getPathname());
			} elseif (substr($Entry->getFilename(), -4) == '.php') {
				$this->checkFile($Entry->getPathname());
			}
		}
	}

	/**
	 * Analyze the PHP file and check all used classes and functions again the known extension mapping.
	 * @param string $filename
	 */
	private function checkFile($filename) {
		$analyser = new PHPAnalyzer();
		$analyser->open($filename);
		$definitions = array_merge(array_keys($analyser->usedDefinitions), array_keys($analyser->usedFunctions));
		$extentions = array();
		foreach ($definitions as $definition) {
			$key = strtolower($definition);
			if (isset($this->definitionToExtension[$key])) { // function/class belongs to an extension?
				$extentions[$this->definitionToExtension[$key]] = true;
				if (function_exists($key) == false && class_exists($key) == false) {
					$this->missingExtensions[$this->definitionToExtension[$key]] = $key;
				}
			}
		}
		foreach (array_keys($extentions) as $extention) {
			$this->extensionUsedIn[$extention][] = $filename;
		}
	}

}

?>
