<?php
/**
 * Bootstrap the Sledgehammer Framework.
 *
 * @package Core
 */
namespace Sledgehammer;
if (!defined('Sledgehammer\CORE_DIR')) {

	// Define constants
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
	if (ENVIRONMENT === 'development' || ENVIRONMENT === 'phpunit') {
		ini_set('display_errors', true);
	} else {
		ini_set('display_errors', false);
	}

	if (!defined('Sledgehammer\STARTED')) {
		/**
		 * Timestamp (in microseconds) of the when the script started.
		 */
		define('Sledgehammer\STARTED', microtime(true));
	}
	/**
	 * Directory of the Sledgehammer Core.
	 */
	define('Sledgehammer\CORE_DIR', dirname(__FILE__).'/');
	/**
	 * Directory of the installed Sledgehammer modules. Usually the "sledgehammer/" folder
	 */
	define('Sledgehammer\MODULES_DIR', dirname(CORE_DIR).DIRECTORY_SEPARATOR);
	/**
	 * Directory of the project.
	 */
	define('Sledgehammer\PATH', dirname(MODULES_DIR).DIRECTORY_SEPARATOR);
	if (!defined('Sledgehammer\APPLICATION_DIR')) {
		/**
		 * Directory of the application specific files.
		 */
		define('Sledgehammer\APPLICATION_DIR', PATH.'application'.DIRECTORY_SEPARATOR);
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
	error_reporting(E_MAX); // Activate the maximum error_level

	/**
	 * Case insensitive "natural order" sorting method for Collection->orderBy()
	 * Uses natcasesort()
	*/
	define('Sledgehammer\SORT_NATURAL_CI', -2);

	// Include functions
	require_once(CORE_DIR.'functions.php');

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
		unset($tmpDir);
	}
	mkdirs(TMP_DIR);

	// Include classes
	require_once(CORE_DIR.'classes/Object.php'); // The generic superclass
	require_once(CORE_DIR.'classes/Framework.php'); // Helper class for extracting and loading Sledgehammer modules
	require_once(CORE_DIR.'classes/InfoException.php');
	require_once(CORE_DIR.'classes/ErrorHandler.php');
	require_once(CORE_DIR.'classes/AutoLoader.php');

	if (function_exists('mb_internal_encoding')) {
		mb_internal_encoding(Framework::$charset);
	}

	// Register the ErrorHandler & AutoLoader (But leave the the configuration & initialisation to bootstrap.php)
	Framework::$errorHandler = new ErrorHandler;
	Framework::$errorHandler->init();

	Framework::$autoLoader = new AutoLoader(PATH);
	spl_autoload_register(array(Framework::$autoLoader, 'define'));

	if (ENVIRONMENT === 'development' || ENVIRONMENT === 'phpunit') {
		Framework::$errorHandler->html = true;
		Framework::$errorHandler->debugR = true;
		Framework::$errorHandler->emails_per_request = 10;
	} else {
		Framework::$errorHandler->emails_per_request = 2;
		Framework::$errorHandler->emails_per_minute = 6;
		Framework::$errorHandler->emails_per_day = 100;
		$_email = isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : false;
		if (preg_match('/^.+@.+\..+$/', $_email) && !in_array($_email, array('you@example.com'))) { // Is het geen emailadres, of het standaard apache emailadres?
			Framework::$errorHandler->email = $_email;
		} elseif ($_email != '') { // Is het email niet leeg of false?
			error_log('Invalid $_SERVER["SERVER_ADMIN"]: "'.$_email.'", expecting an valid emailaddress');
		}
	}

	if (!defined('DEBUG_VAR') && !defined('Sledgehammer\DEBUG_VAR')) {
		/**
		 * Configure the "?debug=1" to use another $_GET variable.
		 */
		define('Sledgehammer\DEBUG_VAR', 'debug'); // Use de default DEBUG_VAR "debug"
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
			Framework::$errorHandler->html = (bool) $overrideDebugOutput;
		}
	}
	// Dectect modules
	$modules = Framework::getModules();

	// Initialize the AutoLoader
	if (file_exists(PATH.'AutoLoader.db.php')) {
		Framework::$autoLoader->loadDatabase(PATH.'AutoLoader.db.php');
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
			Framework::$autoLoader->importFolder($path, $settings);
		}
	}

	if (file_exists(PATH.'vendor/')) { // Does the application have vendor packages?
		extend_include_path(PATH.'vendor/');
		// Add classes to the AutoLoader
		Framework::$autoLoader->importFolder(PATH.'vendor/', array(
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

	// Set DebugR statusbar header.
	if (headers_sent() === false && DebugR::isEnabled()) {
		DebugR::send('sledgehammer-statusbar', 'no statusbar data.', true);
	}

	// Initialize modules
	unset($modules['core']);
	foreach($modules as $module) {
		Framework::initModule($module['path']);
	}
	unset($_email, $modules, $module, $overrideDebugOutput);

	/**
	 * Timestamp (in microseconds) of the when the Sledgehammer Framework was initialized.
	 */
	define('Sledgehammer\INITIALIZED', microtime(true));
}
?>
