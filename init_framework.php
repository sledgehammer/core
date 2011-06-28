<?php
/**
 * Initialiseer de database(s) en alle sledgehammer-modules (constants, functions en init)
 */
namespace SledgeHammer;
if (!defined('SLEDGEHAMMER_FRAMEWORK')) {
	define('SLEDGEHAMMER_FRAMEWORK', true);

	if (!defined('ENVIRONMENT')) {
		define('ENVIRONMENT', isset($_SERVER['APPLICATION_ENV']) ? $_SERVER['APPLICATION_ENV'] : 'production');
	}
	$configFile = (ENVIRONMENT == 'development') ? 'development' : 'defaults'; 
	$config = parse_ini_file(dirname(__FILE__).'/settings/sledgehammer_'.$configFile.'.ini'); 
	ini_set('display_errors', (bool) $config['display_errors']);
	require_once(dirname(__FILE__).'/init.php'); // Core module laden
	if (ENVIRONMENT != 'development') {
		$email = isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : false;
		if (preg_match('/^.+@.+\..+$/', $email) && !in_array($email, array('you@example.com'))) { // Is het geen emailadres, of het standaard apache emailadres?
			$GLOBALS['ErrorHandler']->email = $email;
		} elseif ($email != '') { // Is het email niet leeg of false?
			error_log('Invalid $_SERVER["SERVER_ADMIN"]: "'.$email.'", expecting an valid emailaddress');
		}
	}
	$GLOBALS['ErrorHandler']->html = (bool) $config['error_handler_html'];
	$GLOBALS['ErrorHandler']->cli = (bool) $config['error_handler_cli'];
	$GLOBALS['ErrorHandler']->log = (bool) $config['error_handler_log'];
	$GLOBALS['ErrorHandler']->emails_per_request = $config['error_handler_emails_per_request'];
	$GLOBALS['ErrorHandler']->emails_per_minute = $config['error_handler_emails_per_minute'];
	$GLOBALS['ErrorHandler']->emails_per_day = $config['error_handler_emails_per_day'];


	if (!isset($debug_override_variable)) {
		$debug_override_variable = 'debug'; // Gebruik de debug variabele
	}
	if (empty($debug_override_variable) == false) { // Is de override variable ingesteld? (not false)
		$showDebugInfo = NULL;
		if (isset($_GET[$debug_override_variable])) { // Is er een debug instelling bekend?
			$showDebugInfo = $_GET[$debug_override_variable];
			switch ($showDebugInfo) {

				case 'cookie':
					setcookie($debug_override_variable, true);
					break;

				case 'nocookie':
					setcookie($debug_override_variable, false, 0);
					break;
			}
		} elseif (isset($_COOKIE[$debug_override_variable])) {
			$showDebugInfo = $_COOKIE[$debug_override_variable];
		}
		if ($showDebugInfo !== NULL) { // Is de override variabele niet opgegegeven?
			// Debug instellingen overschrijven
			ini_set('display_errors', (bool) $showDebugInfo);
			$GLOBALS['ErrorHandler']->html = (bool) $showDebugInfo;
		}
	}
	$GLOBALS['AutoLoader']->init(); // De AutoLoader initialiseren

	// Database connecties maken
	$GLOBALS['database_failure'] = false;
	if (file_exists(PATH.'application/settings/database.ini')) {
		$success = SledgeHammer::initDatabases();
		$GLOBALS['database_failure'] = !$success;
	}
	// Per module de constants.ini, functions.php en init.php inladen en defineren
	$modules = Framework::getModules();
	unset($modules['core']);
	foreach($modules as $module) {
		Framework::initModule($module['path']);
	}
	unset($configFile, $config, $email, $success, $modules, $module, $showDebugInfo); 

	if ($debug_override_variable == 'debug') {
		unset($debug_override_variable);
	}
	define('MICROTIME_INIT', microtime(true));
}
?>
