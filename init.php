<?php
/**
 * Initialize SledgeHammer Core.
 *
 * @package Core
 */
namespace SledgeHammer;

// Define constants
if (!defined('SledgeHammer\MICROTIME_START')) {
	define('SledgeHammer\MICROTIME_START', microtime(true));
}
define('SledgeHammer\CORE_DIR', dirname(__FILE__).'/');
define('SledgeHammer\MODULES_DIR', dirname(CORE_DIR).DIRECTORY_SEPARATOR); // Configure the constante for the modules directory. Usually the "sledgehammer/" folder.
define('SledgeHammer\PATH', dirname(MODULES_DIR).DIRECTORY_SEPARATOR); // Configure the constant for the project directory.
if (!defined('SledgeHammer\APPLICATION_DIR')) {
	define('SledgeHammer\APPLICATION_DIR', PATH.'application'.DIRECTORY_SEPARATOR);
}
define('SledgeHammer\E_MAX', (E_ALL | E_STRICT)); // E_MAX an error_reporing level that includes all message types (E_ALL doesn't include E_STRICT)
error_reporting(E_MAX); // Activate the maximum error_level
if (defined('SORT_NATURAL') === false) {
	define('SORT_NATURAL', -1); // for Collection->orderBy()
}
define('SORT_NATURAL_CI', -2); // Case insensitive nartural sort for Collection->orderBy()

// Include functions
require_once(CORE_DIR.'functions.php');

// Detect & create a writable tmp folder
if (defined('SledgeHammer\TMP_DIR') === false) {
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
	define('SledgeHammer\TMP_DIR', $tmpDir);
	unset($tmpDir);
}
mkdirs(TMP_DIR);

// Include classes
require_once(CORE_DIR.'classes/Object.php'); // The generic superclass
require_once(CORE_DIR.'classes/Framework.php'); // Helper class for extracting and loading SledgeHammer modules
require(CORE_DIR.'classes/ErrorHandler.php');
require(CORE_DIR.'classes/AutoLoader.php');

if (function_exists('mb_internal_encoding')) {
	mb_internal_encoding(Framework::$charset);
}

// Register the ErrorHandler & AutoLoader (But leave the the configuration & initialisation to init_framework.php)
Framework::$errorHandler = new ErrorHandler;
Framework::$errorHandler->init();

Framework::$autoLoader = new AutoLoader(PATH);
spl_autoload_register(array(Framework::$autoLoader, 'define'));

?>
