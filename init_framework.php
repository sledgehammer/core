<?php
/**
 * Het Framework initialiseren.
 * Initialiseerd de database(s) en alle sledgehammer-modules (constants, functions en init)
 */
namespace SledgeHammer;
if (!defined('SledgeHammer\INITIALIZED')) {
	define('SledgeHammer\INITIALIZED', true);

	if (!defined('ENVIRONMENT') && !defined('SledgeHammer\ENVIRONMENT')) {
		define('SledgeHammer\ENVIRONMENT', isset($_SERVER['APPLICATION_ENV']) ? $_SERVER['APPLICATION_ENV'] : 'production');
	}
	if (ENVIRONMENT === 'development') {
		ini_set('display_errors', true);
	} else {
		ini_set('display_errors', false);
	}
	require_once(dirname(__FILE__).'/init.php'); // Core module laden
	if (ENVIRONMENT === 'development') {
		Framework::$errorHandler->html = true;
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

	if (!defined('DEBUG_VAR') && !defined('SledgeHammer\DEBUG_VAR')) {
		define('SledgeHammer\DEBUG_VAR', 'debug'); // Use de default DEBUG_VAR "debug"
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
	Framework::$autoLoader->init(); // De AutoLoader initialiseren

	if (file_exists(PATH.'pear/classes')) { // Does the application have PEAR packages installed?
		extend_include_path(PATH.'pear/classes');
		// Add classes to the AutoLoader
		Framework::$autoLoader->importFolder($pearIncludePath, array(
			'matching_filename' => false,
			'mandatory_definition' => false,
			'mandatory_superclass' => false,
			'one_definition_per_file' => false,
			'revalidate_cache_delay' => 30,
			'detect_accidental_output' => false,
			'ignore_folders' => array('data'),
			'cache_level' => 2,
		));
	}

	// Per module de constants.ini, functions.php en init.php inladen en defineren
	$modules = Framework::getModules();
	unset($modules['core']);
	foreach($modules as $module) {
		Framework::initModule($module['path']);
	}
	unset($_email, $modules, $module, $overrideDebugOutput);

	define('SledgeHammer\MICROTIME_INIT', microtime(true));
}
?>
