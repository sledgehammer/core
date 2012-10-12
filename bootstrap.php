<?php
/**
 * Bootstrap the Sledgehammer Framework.
 *
 * @package Core
 */
namespace Sledgehammer;
if (!defined('Sledgehammer\CORE_DIR')) {

	if (!defined('Sledgehammer\STARTED')) {
		/**
		 * Timestamp (in microseconds) for when the script started.
		 */
		define('Sledgehammer\STARTED', microtime(true));
	}

	/**
	 * Directory of the Sledgehammer Core.
	 */
	define('Sledgehammer\CORE_DIR', __DIR__.'/');

	require_once(CORE_DIR.'functions.php'); // Global and namespaced functions
	require_once(CORE_DIR.'classes/Object.php'); // The generic superclass
	require_once(CORE_DIR.'classes/Framework.php'); // Helper class for extracting and loading Sledgehammer modules
	require_once(CORE_DIR.'classes/InfoException.php');
	require_once(CORE_DIR.'classes/ErrorHandler.php');
	require_once(CORE_DIR.'classes/AutoLoader.php');

	Framework::defineConstants();
	
	if (function_exists('mb_internal_encoding')) {
		mb_internal_encoding(Framework::$charset);
	}

	// Configure and enable the ErrorHandler	
	Framework::configureErrorhandler();

	// Configure and enable the AutoLoader	
	Framework::configureAutoloader();

	// Set DebugR statusbar header.
	if (headers_sent() === false && DebugR::isEnabled()) {
		DebugR::send('sledgehammer-statusbar', 'no statusbar data.', true);
	}

	// Initialize modules
	$modules = Framework::getModules();
	unset($modules['core']);
	foreach($modules as $module) {
		Framework::initModule($module['path']);
	}
	unset($modules, $module);

	/**
	 * Timestamp (in microseconds) for when the Sledgehammer Framework was initialized.
	 */
	define('Sledgehammer\INITIALIZED', microtime(true));
}
?>
