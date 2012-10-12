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
	 * Define constants
	 */
	static function defineConstants() {
		if (defined('Sledgehammer\ENVIRONMENT') === false) {
			if (defined('ENVIRONMENT') === false) {
				/**
				 * The configured environment. Uses $_SERVER['APPLICATION_ENV'] or uses 'production' as fallback.
				 */
				define('Sledgehammer\ENVIRONMENT', isset($_SERVER['APPLICATION_ENV']) ? $_SERVER['APPLICATION_ENV'] : 'production');
			} else {
				define('Sledgehammer\ENVIRONMENT', ENVIRONMENT);
			}
		}

		/**
		 * Directory of the installed Sledgehammer modules. Usually the "sledgehammer/" folder
		 */
		define('Sledgehammer\MODULES_DIR', dirname(CORE_DIR).DIRECTORY_SEPARATOR);
		/**
		 * Directory of the project.
		 */
		define('Sledgehammer\PATH', dirname(dirname(MODULES_DIR)).DIRECTORY_SEPARATOR);
		if (!defined('Sledgehammer\APP_DIR')) {
			/**
			 * Directory for the app.
			 */
			define('Sledgehammer\APP_DIR', PATH.'app'.DIRECTORY_SEPARATOR);
		}

		if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
			/**
			 * Errorlevel for all errors messages.
			 */
			define('Sledgehammer\E_MAX', E_ALL);
		} else {
			// Declare constans missing in PHP 5.3
			define('Sledgehammer\SORT_NATURAL', 6); // "natural order" sorting method for Collection->orderBy()
			define('Sledgehammer\E_MAX', (E_ALL | E_STRICT)); // E_MAX an error_reporing level that includes all message types (E_ALL doesn't include E_STRICT)
			define('T_TRAIT', -1); // Used in the AutoLoader
		}

		/**
		 * Case insensitive "natural order" sorting method for Collection->orderBy()
		 * Uses natcasesort()
		*/
		define('Sledgehammer\SORT_NATURAL_CI', -2);

		// Detect & create a writable tmp folder
		if (defined('Sledgehammer\TMP_DIR') === false) {
			$tmpDir = PATH.'tmp'.DIRECTORY_SEPARATOR;
			if (is_dir($tmpDir) && is_writable($tmpDir)) { // A writable local tmp folder exist?
				if (function_exists('posix_getpwuid')) {
					$tmpDir .= array_value(posix_getpwuid(posix_geteuid()), 'name').'/';
				}
			} else {
				$tmpDir = '/tmp/sledgehammer-'.md5(PATH);
				if (function_exists('posix_getpwuid')) {
					$tmpDir .= '-'.array_value(posix_getpwuid(posix_geteuid()), 'name').'/';
				}
			}
			/**
			 * Directory for temporary files.
			 */
			define('Sledgehammer\TMP_DIR', $tmpDir);
		}
		if (!defined('DEBUG_VAR') && !defined('Sledgehammer\DEBUG_VAR')) {
			/**
			 * Configure the "?debug=1" to use another $_GET variable.
			 */
			define('Sledgehammer\DEBUG_VAR', 'debug'); // Use de default DEBUG_VAR "debug"
		}
	}

	static function configureErrorhandler() {
		error_reporting(E_MAX); // Activate the maximum error_level
		
		self::$errorHandler = new ErrorHandler;
		self::$errorHandler->init();

		if (ENVIRONMENT === 'development' || ENVIRONMENT === 'phpunit') {
			ini_set('display_errors', true);
			self::$errorHandler->html = true;
			self::$errorHandler->debugR = true;
			self::$errorHandler->emails_per_request = 10;
		} else {
			ini_set('display_errors', false);
			self::$errorHandler->emails_per_request = 2;
			self::$errorHandler->emails_per_minute = 6;
			self::$errorHandler->emails_per_day = 100;
			$_email = isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : false;
			if (preg_match('/^.+@.+\..+$/', $_email) && !in_array($_email, array('you@example.com'))) { // Is het geen emailadres, of het standaard apache emailadres?
				self::$errorHandler->email = $_email;
			} elseif ($_email != '') { // Is het email niet leeg of false?
				error_log('Invalid $_SERVER["SERVER_ADMIN"]: "'.$_email.'", expecting an valid emailaddress');
			}
		}

		if (DEBUG_VAR != false) { // Is the DEBUG_VAR enabled?
			$overrideDebugOutput = null;
			if (isset($_GET[DEBUG_VAR])) { // Is the DEBUG_VAR present in the $_GET parameters?
				$overrideDebugOutput = $_GET[DEBUG_VAR];
				switch ($overrideDebugOutput) {

					case 'cookie':
						setcookie(DEBUG_VAR, true);
						break;

					case 'nocookie':
						setcookie(DEBUG_VAR, false, 0);
						break;
				}
			} elseif (isset($_COOKIE[DEBUG_VAR])) { // Is the DEBUG_VAR present in the $_COOKIE?
				$overrideDebugOutput = $_COOKIE[DEBUG_VAR];
			}
			if ($overrideDebugOutput !== null) {
				ini_set('display_errors', (bool) $overrideDebugOutput);
				self::$errorHandler->html = (bool) $overrideDebugOutput;
			}
		}
	}

	static function configureAutoloader() {
		// Dectect modules
		$modules = self::getModules();

		// Register the AutoLoader
		self::$autoLoader = new AutoLoader(PATH);
		spl_autoload_register(array(self::$autoLoader, 'define'));

		// Initialize the AutoLoader
		if (file_exists(PATH.'AutoLoader.db.php')) {
			self::$autoLoader->loadDatabase(PATH.'AutoLoader.db.php');
		} else {
			// Import definitions inside the modules.
			foreach ($modules as $module) {
				$path = $module['path'];
				if (file_exists($module['path'].'classes')) { // A sledgehammer folder layout?
					$path = $path.'classes'; // Only import the classes folder
					$settings = array(); // Use the strict default settings
				} else {
					// Disable validations
					$settings = array(
						'matching_filename' => false,
						'mandatory_definition' => false,
						'mandatory_superclass' => false,
						'one_definition_per_file' => false,
						'revalidate_cache_delay' => 20,
						'detect_accidental_output' => false,
					);
				}
				self::$autoLoader->importFolder($path, $settings);
			}
		}

		/*  Disable AutoLoader for other vendor packages (Probable no longer needed with composer)
		if (file_exists(PATH.'vendor/')) { // Does the app have vendor packages?
			extend_include_path(PATH.'vendor/');
			// Add classes to the AutoLoader
			self::$autoLoader->importFolder(PATH.'vendor/', array(
				'matching_filename' => false,
				'mandatory_definition' => false,
				'mandatory_superclass' => false,
				'one_definition_per_file' => false,
				'revalidate_cache_delay' => 30,
				'detect_accidental_output' => false,
				'ignore_folders' => array('data'),
				'cache_level' => 3,
			));
			if (file_exists(PATH.'vendor/pear/php/')) { // Add pear classes the include path.
				extend_include_path(PATH.'vendor/pear/php/');
			}
		}
		*/
	}

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
			$appPath = APP_DIR;
		} else {
			$appPath = dirname($modulesPath).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR;
		}
		static $cache = array();
		if (isset($cache[$modulesPath])) {
			return $cache[$modulesPath];
		}
		$required_modules = array();
		$module_info = array(
			'app' => array(
				'name' => 'app',
				'path' => $appPath,
				'required_modules' => self::detectModules($modulesPath),
				'optional_modules' => array(),
				'app' => true
			)
		);
		// Fetch all required_modules
		self::appendModules($modulesPath, $required_modules, $module_info, 'app', 'detectModules()');
		if (!file_exists($appPath)) {
			unset($required_modules[array_search('app', $required_modules)]); // De app is zelf niet required
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
			if ($module === 'app') {
				throw new \Exception('Info for the app "module" must be configured');
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
