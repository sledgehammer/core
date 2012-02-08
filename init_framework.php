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
		$GLOBALS['ErrorHandler']->html = true;
		$GLOBALS['ErrorHandler']->emails_per_request = 10;
	} else {
		$GLOBALS['ErrorHandler']->emails_per_request = 2;
		$GLOBALS['ErrorHandler']->emails_per_minute = 6;
		$GLOBALS['ErrorHandler']->emails_per_day = 100;
		$email = isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : false;
		if (preg_match('/^.+@.+\..+$/', $email) && !in_array($email, array('you@example.com'))) { // Is het geen emailadres, of het standaard apache emailadres?
			$GLOBALS['ErrorHandler']->email = $email;
		} elseif ($email != '') { // Is het email niet leeg of false?
			error_log('Invalid $_SERVER["SERVER_ADMIN"]: "'.$email.'", expecting an valid emailaddress');
		}
	}

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

	// Per module de constants.ini, functions.php en init.php inladen en defineren
	$modules = Framework::getModules();
	unset($modules['core']);
	foreach($modules as $module) {
		Framework::initModule($module['path']);
	}
	unset($email, $success, $modules, $module, $showDebugInfo);

	if ($debug_override_variable == 'debug') {
		unset($debug_override_variable);
	}
	define('SledgeHammer\MICROTIME_INIT', microtime(true));
}
?>
