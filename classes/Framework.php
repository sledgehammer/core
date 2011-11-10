<?php
/**
 * Container voor SledgeHammer Framework functions
 * - Module detection and initialisation
 * - Database initialisation
 *
 * @todo Beter locatie vinden voor de database functies
 * @package Core
 */
namespace SledgeHammer;

class Framework {

	private static $cachedRequiredModules = array();

	/**
	 * List required sledgehammer modules and sort on depedency.
	 * (The module without depedency comes first.)
	 */
	static function getModules($modulesPath = NULL) {
		if ($modulesPath === NULL) {
			$modulesPath = self::getPath();
		}
		$applicationPath = dirname($modulesPath).DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR;
		if (isset(self::$cachedRequiredModules[$modulesPath])) {
			return self::$cachedRequiredModules[$modulesPath];
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
		self::$cachedRequiredModules[$modulesPath] = $modules;
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
	 * @param string $modulePath  De map waar de "modules" in staan
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
				} elseif (file_exists($module_path.'module.ini') == false) {
					notice('Missing module.ini for module: "'.$module.'" required by "'.$required_by.'"');
					$module_info[$module] = array('name' => $module);
				} elseif (!($module_info[$module] = parse_ini_file($module_path.'module.ini'))) {
					throw new \Exception('module.ini from mModule: "'.$module.'" is corrupt');
				}
				$module_info[$module]['path'] = $module_path;
			}
			if (isset($module_info[$module]['required_modules'])) {
				$module_info[$module]['required_modules'] = explode(',', $module_info[$module]['required_modules']);
				foreach ($module_info[$module]['required_modules'] as $index => $required_module) {
					$module_info[$module]['required_modules'][$index] = trim($required_module);
				}
			} else {
				$module_info[$module]['required_modules'] = ($module == 'core') ? array() : array('core');
			}
			if (isset($module_info[$module]['optional_modules'])) {
				$module_info[$module]['optional_modules'] = explode(',', $module_info[$module]['optional_modules']);
				foreach ($module_info[$module]['optional_modules'] as $index => $optional_module) {
					$module_info[$module]['optional_modules'][$index] = trim($optional_module);
				}
			} else {
				$module_info[$module]['optional_modules'] = array();
			}
		}
		foreach ($module_info[$module]['required_modules'] as $required_dependancy) {
			self::appendModules($modulesPath, $required_modules, $module_info, $required_dependancy, $module);
		}
		foreach ($module_info[$module]['optional_modules'] as $recommended_dependancy) {
			if (file_exists($modulesPath.$recommended_dependancy.'/module.ini')) {
				self::appendModules($modulesPath, $required_modules, $module_info, $recommended_dependancy, $module);
			}
		}
	}

	/**
	 * Vanuit een database.ini bestand (o.b.v de $environment) database connecties maken
	 *
	 * @param string $environment  De environment waar de connecties van gemaakt worden.
	 * @param string $filename  De locatie van het ini bestand met database instellingen.
	 * @return bool Alle connecties zijn succesvol gemaakt
	 */
	static function initDatabases($environment = null, $filename = null) {
		$databases = self::extractDatabaseSettings($environment, $filename);
		$success = true;
		$links = array();
		foreach ($databases as $label => $dsn) {
			if (isset($databases[$dsn])) {
				$GLOBALS['Databases'][$label] = $dsn; // symlink
			} else {
				try {
					$GLOBALS['Databases'][$label] = new Database($dsn);
				} catch (\Exception $e) {
					$GLOBALS['Databases'][$label] = '__NOT_CONNECTED__';
					warning('Failed to connect to database['.$label.']: ', $e->getMessage());
				}
			}
		}
		if (count($GLOBALS['Databases']) == 1 && key($GLOBALS['Databases']) != 'deault') {
			$GLOBALS['Databases']['default'] = key($GLOBALS['Databases']); // symlink "default" to the only database connection.
		}
		return $success;
	}

	/**
	 * De settings van de databases van 1 environment uit een ini file halen.
	 *
	 * @param string $environment  De environment waar de connecties van gemaakt worden.
	 * @param string $filename  De locatie van het ini bestand met database instellingen.
	 * @return array
	 */
	private static function extractDatabaseSettings($environment = null, $filename = null) {
		if ($environment === null) {
			$environment = ENVIRONMENT;
		}
		if ($filename === null) {
			$filename = APPLICATION_DIR.'/settings/database.ini';
		}
		$settings = parse_ini_file($filename, true);
		if (!$settings) { // Staat er een fout in het ini bestand, of is deze leeg?
			notice('File: "'.$filename.'" is invalid');
			return false;
		}
		$databases = array();
		$allowedEnvironments = array('development', 'staging', 'production');
		foreach ($settings as $group => $links) {
			if (!in_array($group, $allowedEnvironments)) {
				notice('Invalid environment: ['.$group.'] in '.$filename, array('Allowed environments' => $allowedEnvironments));
			}
		}
		if (isset($settings[$environment])) {
			return $settings[$environment];
		}
		notice('No databases configured for "'.$environment.'" environment');
		return false;
	}

	/**
	 * Detecteer alle modules in de modules map
	 * 
	 * @param string $modulePath
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

	/**
	 * Het pad waar de sledgehammer modules in staan.
	 *
	 * @return string
	 */
	private static function getPath() {
		return dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR;
	}

}

?>
