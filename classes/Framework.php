<?php
/**
 * Framework
 */
namespace Sledgehammer;
/**
 * Container voor Sledgehammer Framework functions
 * - Module detection and initialisation
 * - Language & locale initialisation
 *
 * @package Core
 */
class Framework {

	/**
	 * Register UTF-8 as default charset.
	 * @var string
	 */
	static $charset = 'UTF-8';

	/**
	 * The AutoLoader instance.
	 * @var AutoLoader
	 */
	static $autoLoader;

	/**
	 * The ErrorHandler instance.
	 * @var ErrorHandler
	 */
	static $errorHandler;

	/**
	 * List required sledgehammer modules and sort on depedency.
	 * (The module without depedency comes first.)
	 *
	 * @param string $modulesPath
	 * @return array
	 */
	static function getModules($modulesPath = null) {
		if ($modulesPath === null) {
			$modulesPath = MODULES_DIR;
			$applicationPath = APPLICATION_DIR;
		} else {
			$applicationPath = dirname($modulesPath).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR;
		}
		static $cache = array();
		if (isset($cache[$modulesPath])) {
			return $cache[$modulesPath];
		}
		$required_modules = array();
		$module_info = array(
			'application' => array(
				'name' => 'Application',
				'path' => $applicationPath,
				'required_modules' => self::detectModules($modulesPath),
				'optional_modules' => array(),
				'application' => true
			)
		);
		// Fetch all required_modules
		self::appendModules($modulesPath, $required_modules, $module_info, 'application', 'detectModules()');
		if (!file_exists($applicationPath)) {
			unset($required_modules[array_search('application', $required_modules)]); // De application is zelf niet required
		}
		// Sort by dependancy
		$sorted_modules = array();
		$cyclic_dependency_check = count($required_modules) + 1;
		while (count($required_modules) > 0) { // Loop until all required_modules are sorted
			if ($cyclic_dependency_check == count($required_modules)) { // No modules are sorted in the previous while loop?
				throw new \Exception('Cyclic depedency detected for modules "'.implode('" and "', $required_modules).'"');
			}
			$cyclic_dependency_check = count($required_modules);
			foreach ($required_modules as $index => $module) {
				$all_dependancy_are_met = true;
				foreach ($module_info[$module]['required_modules'] as $required_module) {
					if (!in_array($required_module, $sorted_modules)) { // is the required depencancy not met
						$all_dependancy_are_met = false;
						break;
					}
				}
				if ($all_dependancy_are_met) {
					$sorted_modules[] = $module;
					unset($required_modules[$index]);
				}
			}
		}
		// Merge the sorted modules with the module_info array
		$modules = array();
		foreach ($sorted_modules as $module) {
			$modules[$module] = $module_info[$module];
		}
		$cache[$modulesPath] = $modules;
		return $modules;
	}

	/**
	 * Initialiseerd een module.
	 * laad de $module/functions.php en $module/init.php in
	 *
	 * @param string $path Absolute path van een module
	 */
	static function initModule($path) {
		if (in_array(substr($path, -1), array('\\', '/')) == false) { // Is er geen trailing "/" opgegeven in het path?
			$path .= DIRECTORY_SEPARATOR; // De trailing slash toevoegen
		}
		if (!is_dir($path)) {
			notice('Module path: "'.$path.'" not found');
		}
		if (file_exists($path.'functions.php')) {
			include_once($path.'functions.php');
		}
		if (file_exists($path.'init.php')) {
			include($path.'init.php');
		}
	}

	/**
	 * Retreiving module info and find all dependend (required) modules
	 *
	 * @param string $modulesPath  De map waar de "modules" in staan
	 * @param array $required_modules  Dit is een array met reeds toegevoegde modules. Zodat er elke modules maximaal 1x wordt ingeladen.
	 * @param array $module_info  In dit array worden gegevens uit de ini bestanden gezet, zodat deze maar 1x ingeladen worden.
	 * @param string $module  Dit is de naam van de module die toegevoegd zal worden aan de $required_modules (inclusief modules waar deze van afhandelijk is)
	 * @param string $required_by  De {$module} is een afhankelijkheid van de {$required_by} module.
	 */
	private static function appendModules($modulesPath, &$required_modules, &$module_info, $module, $required_by) {
		if (in_array($module, $required_modules)) { // Is this module already included
			return;
		}
		$required_modules[] = $module;
		if (!isset($module_info[$module])) {
			if ($module === 'application') {
				throw new \Exception('Info for the application "module" must be configured');
			} else {
				$module_path = $modulesPath.$module.DIRECTORY_SEPARATOR;
				if (file_exists($module_path) == false) {
					warning('Module: "'.$module.'" is missing, but is required by "'.$required_by.'"');
				} elseif (file_exists($module_path.'composer.json') == false) {
					notice('Missing "composer.json" for module: "'.$module.'"', 'Module is required by "'.$required_by.'"');
					$module_info[$module] = array('name' => $module);
				} else {
					$module_info[$module] = json_decode(file_get_contents($module_path.'composer.json'), true);
					if ($module_info[$module] === null) {
						$constants = get_defined_constants();
						$jsonError = json_last_error();
						foreach ($constants as $constant => $value) {
							if ($value === $jsonError && substr($constant, 0, 10) === 'JSON_ERROR') {
								$jsonError = $constant;
								break;
							}
						}
						warning('"'.$module.'/composer.json" is corrupted', $jsonError);
						$module_info[$module] = array('name' => $module);
					}
				}
				$module_info[$module]['path'] = $module_path;
			}
			$module_info[$module]['required_modules'] = array();
			if ($module !== 'core') {
				if (empty($module_info[$module]['require'])) {
					warning('No "require" found in "'.$module.'" composer.json','Add `"require": { "sledgehammer/core": "*" }` to the composer.json');
				} else {
					$module_info[$module]['required_modules'] = array();
					foreach ($module_info[$module]['require'] as $required_module => $version) {
						if (dirname($required_module) == 'sledgehammer') {
							$module_info[$module]['required_modules'][] = basename($required_module);
						}
						// @todo Handle unofficial sledgehammer modules (detect composer.json?)
					}
				}
			}
			$module_info[$module]['optional_modules'] = array();
			if (isset($module_info[$module]['suggest'])) {
				foreach (array_keys($module_info[$module]['suggest']) as $optional_module) {
					if (dirname($required_module) == 'sledgehammer') {
						$module_info[$module]['optional_modules'][] = basename($optional_module);
					}
				}
				// @todo Handle unofficial sledgehammer modules (detect composer.json?)
			}
		}
		foreach ($module_info[$module]['required_modules'] as $required_dependancy) {
			self::appendModules($modulesPath, $required_modules, $module_info, $required_dependancy, $module);
		}
		foreach ($module_info[$module]['optional_modules'] as $recommended_dependancy) {
			if (file_exists($modulesPath.$recommended_dependancy.'/composer.json')) {
				self::appendModules($modulesPath, $required_modules, $module_info, $recommended_dependancy, $module);
			}
		}
	}

	/**
	 * Stel de Locale in zodat getallen en datums op de juiste manier worden weergegeven
	 *
	 * @param null|string $language engelse benaming van de taal die moet worden ingesteld.
	 * @return void
	 */
	static function initLanguage($language) {
		switch ($language) {

			case 'dutch':
				$locales = array('nl_NL.utf8', 'nl_NL.UTF-8', 'dutch');
				break;

			default:
				warning('Invalid language: "'.$language.'"');
				return;
		}
		if (!setlocale(LC_ALL, $locales)) {
			exec('locale -a', $available_locales);
			notice('Setting locale to "'.implode('", "', $locales).'" has failed', 'Available locales: "'.implode('", "', $available_locales).'"');
		} elseif (setlocale(LC_ALL, 0) == 'C') {
			notice('setlocale() failed. (Cygwin issue)');
		}
	}

	/**
	 * De sessie starten, biedt de mogenlijkheid voor sessies in de database
	 */
	static function initSession() { // [void]
		if (headers_sent($file, $line)) {
			notice('Session could not be started. Output started in '.$file.' on line '.$line);
		} else {
			if (isset($_SESSION)) { // Is de sessie al gestart?
				return;
			}
			// Voorkom PHPSESSID in de broncode bij zoekmachines
			if (empty($_SERVER['HTTP_USER_AGENT'])) { // Is de browser meegegeven?
				// Er is geen user_agent/browser opgegeven, waarschijnlijk dan geen browser
				ini_set('url_rewriter.tags', ''); // Geen PHPSESSID in de html code stoppen
			}
			session_start();
		}
	}

	/**
	 * Detecteer alle modules in de modules map.
	 *
	 * @param string $modulesPath
	 * @return array
	 */
	private static function detectModules($modulesPath) {
		$modules = array();
		$Directory = new \DirectoryIterator($modulesPath);
		foreach ($Directory as $entry) {
			if ($entry->isDir() && substr($entry->getFilename(), 0, 1) != '.') { // Is het een niet verborgen map
				$modules[] = $entry->getFilename();
			}
		}
		return $modules;
	}

}

?>
