<?php
/**
 * Controleer of alle benodige php extenties geinstalleerd zijn
 */

class RequiredPhpExtentions extends UnitTestCase {

	private 
		$classes_folders_only = true, // 
		$functions_per_extention,
		$function_to_extention_map = array(), // assoc array waarvan de key de functie/class is en de value de extentie. 
		$extention_to_files_map = array(); // assoc array met als key de extentie gevult met alle bestanden die deze extentie gebruiken.

	/**
	 * tests/data/required_php_extentions.db.php inlezen en omzetten naar de $function_to_extention_map
	 */
	function __construct() {
		$this->functions_per_extention = include(dirname(__FILE__).'/data/required_php_extentions_functions.db.php');
		foreach ($this->functions_per_extention as $extention => $functions_or_classes) {
			foreach ($functions_or_classes as $function_or_class) {
				if (isset($this->function_to_extention_map[strtolower($function_or_class)])) {
					trigger_error('Duplicate entry "'.$function_or_class.'"', E_USER_NOTICE);
				}
				$this->function_to_extention_map[strtolower($function_or_class)] = $extention;
			}
		}
	}

	/**
	 * Controleer of de php extenties geinstalleerd zijn
	 */
	function test_extentions() {
		if (!function_exists('token_get_all')) {
			$this->fail('PHP extention "tokenizer" is required for this UnitTest');
			return;
		}
		if ($this->classes_folders_only) {
			// Alleen de classes mappen van de modules inlezen
			$modules = SledgeHammer::getModules();
			foreach ($modules as $module) {
				$this->append_required_extentions($module['path'].'classes/');
			}
		} else { // check all php files within $path
			$this->append_required_extentions($path);
		}

		$extention_whitelist = include(dirname(__FILE__).'/data/required_php_extentions_whitelist.db.php');
		$extentions = array_keys($this->extention_to_files_map);
		foreach ($extentions as $extention) {
			$functions_or_classes = $this->functions_per_extention[$extention];
			$not_fully_installed = false;
			foreach ($functions_or_classes as $function_or_class) {
				if (! (function_exists($function_or_class) || class_exists($function_or_class, false))) {
					$this->fail('Class or function "'.$function_or_class.'" is not defined');
					$not_fully_installed = true;
				}
			}
			// Geef altijd een foutmelding als de extentie ontbreekt, maar geef alleen een PASS bij de modules die niet ge-whitelist worden.
			if ($not_fully_installed) {
				$required_by = count($this->extention_to_files_map[$extention]).' files, file[0]="';
				$required_by .= $this->extention_to_files_map[$extention][0].'"';
				$this->fail('PHP extention "'.$extention.'" should be installed (required by '.$required_by.')');
			} elseif (!in_array($extention, $extention_whitelist)) {
				$this->pass('PHP extention "'.$extention.'" is installed');
			}
		}
		//echo '<pre>';
		//print_r($this->extention_to_files_map);
	}

	function append_required_extentions($folder) {
		if (!file_exists($folder)) {
			return;
		}
		$DirectoryIterator = new DirectoryIterator($folder);
		foreach ($DirectoryIterator as $Entry) {
			if ($Entry->isDot() || $Entry->getFilename() == '.svn') {
				continue;
			}
			if ($Entry->isDir()) {
				$this->append_required_extentions($Entry->getPathname());
			} elseif (substr($Entry->getFilename(), -4) == '.php') {
				$this->append_required_extentions_for($Entry->getPathname());
			}
		}
	}

	private function append_required_extentions_for($file) {
		$info = $this->parse_file($file);
		$functions_or_classes = array_merge($info['classes'], $info['functions']);
		$extentions = array();
		foreach($functions_or_classes as $function_or_class) {
			$key = strtolower($function_or_class);
			if (isset($this->function_to_extention_map[$key])) {
				$extentions[$this->function_to_extention_map[$key]] = true;
			}
		}
		foreach (array_keys($extentions) as $extention) {
			$this->extention_to_files_map[$extention][] = $file;
		}
	}

	/**
	 * Parse het php-bestand met de tokenizer en geef de gebruikte functies en classes terug.
	 * @param string $file Locatie van het te parsen bestand.
	 */
	private function parse_file($file) {
		$classes = array();
		$functions = array();
		$tokens = token_get_all(file_get_contents($file));
		$html = true; // 
		$possible_function = false;
		$possible_class = false;
		foreach ($tokens as $token) {
			if (!is_array($token)) {
				if ($token == '(' && $possible_function) { // Is de $possible_function is ook echt een functie?
					$functions[$possible_function] = true;
				}
				continue;
			} elseif ($token[0] == T_WHITESPACE) {
				continue;
			}
			// negeer html blokken.
			if ($html) {
				if ($token[0] == T_OPEN_TAG) {
					$html = false;
				}
				$previous_token = $token;
				continue;
			}
			switch($token[0]) {

				case T_CLOSE_TAG:
					$html = true;
					break;

				case T_STRING:
					switch ($previous_token[0]) {

						case T_EXTENDS:
						case T_NEW:
							$classes[$token[1]] = true;
							break;

						case T_DOUBLE_COLON: // Een statische functie aanroep
							if ($possible_class[1] != 'parent' && $possible_class[1] != 'self') { // Wordt er een externe class gebruikt?
								$classes[$possible_class[1]] = true;
								// $possible_function = $possible_class[1].'::'.$token[1];
							}
							break;

						case T_OBJECT_OPERATOR: // functie aanroep naar object
						case T_FUNCTION: // definitie van een functie
							$possible_function = false;
							break;

						default:
							$possible_function = $token[1];
							break;
					}
					break;

				default:
					$possible_function = false;
			}
			$possible_class = $previous_token;
			$previous_token = $token;
		}
		return array(
			'classes' => array_keys($classes),
			'functions' => array_keys($functions)
		);
	}

	private function echo_token($token) {
		echo token_name($token[0]).': '.htmlentities($token[1])."\n";
	}
}
?>
