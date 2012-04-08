<?php
/**
 * Initialize the SledgeHammer Core module.
 *
 * @package Core
 */
namespace SledgeHammer;
if (!defined('SledgeHammer\MICROTIME_START')) {
	define('SledgeHammer\MICROTIME_START', microtime(true));
}
define('SledgeHammer\MODULES_DIR', dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR); // Configure the constante for the modules directory. Usually the "sledgehammer/" folder.
define('SledgeHammer\PATH', dirname(MODULES_DIR).DIRECTORY_SEPARATOR); // Configure the constant for the project directory.
if (!defined('SledgeHammer\APPLICATION_DIR')) {
	define('SledgeHammer\APPLICATION_DIR', PATH.'application'.DIRECTORY_SEPARATOR);
}
define('SledgeHammer\E_MAX', (E_ALL | E_STRICT)); // E_MAX an error_reporing level that includes all message types (E_ALL doesn't include E_STRICT)
error_reporting(E_MAX); // Activate the maximum error_level
if (ini_get('date.timezone') == '' && DIRECTORY_SEPARATOR === '/') { // No timezone configured in php.ini?
	error_log('"date.timezone" is not defined in your php.ini');
	date_default_timezone_set(trim(`date +%Z`)); // Use the system's timezone
}
define('SledgeHammer\CORE_DIR', dirname(__FILE__).'/');
require_once(CORE_DIR.'functions.php');
require_once(CORE_DIR.'classes/Object.php'); // The generic superclass
require_once(CORE_DIR.'classes/Framework.php'); // Helper class for extracting and loading SledgeHammer modules
require(CORE_DIR.'classes/ErrorHandler.php');
require(CORE_DIR.'classes/AutoLoader.php');

if (function_exists('mb_internal_encoding')) {
	mb_internal_encoding(Framework::$charset);
}

// Detect a writable tmp folder
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

// Register the ErrorHandler & AutoLoader (But leave the the configuration &initialisation to init_framework.php)
Framework::$errorHandler = new ErrorHandler;
Framework::$errorHandler->init();

Framework::$autoLoader = new AutoLoader(PATH);
spl_autoload_register(array(Framework::$autoLoader, 'define'));

?>
