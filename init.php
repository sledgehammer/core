<?php
/**
 * Het SledgeHammer Core initialiseren
 *
 * @package Core
 */
if (!defined('MICROTIME_START')) {
	define('MICROTIME_START', microtime(true));
}
define('PATH', dirname(dirname(dirname(__FILE__))).'/'); // Het pad instellen. Dit is de map waar de modules in staan.
if (!defined('TMP_DIR')) {
	define('TMP_DIR', 'tmp/');
}
define('E_MAX', (E_ALL | E_STRICT)); /// Echt alle errors afvangen, inclusief de PHP5 STRICT hints
error_reporting(E_MAX); // Foutniveau activeren
if (ini_get('date.timezone') == '') { // Is er geen tijdzone ingesteld?
	date_default_timezone_set('Europe/Amsterdam'); // Voorkom foutmeldingen door de tijdzone in te stellen
}
$corePath = dirname(__FILE__).'/';
require_once($corePath.'functions.php'); // De globale functies
require_once($corePath.'classes/Object.php'); // Basis object
require_once($corePath.'classes/SledgeHammer.php'); // Helper class voor modules e.d. 
require($corePath.'classes/ErrorHandler.php'); // Foutafhandeling
require($corePath.'classes/Library.php'); // Library object

// ErrorHandeler instellen (standaard configuratie is geen output, maar alleen error_log())
$GLOBALS['ErrorHandler'] = new ErrorHandler;
$GLOBALS['ErrorHandler']->init();

$GLOBALS['Library'] = new Library(PATH); // De Library aanmaken. (maar om te functioneren moet de $Library->init() nog aangeroepen worden) 

ini_set('unserialize_callback_func', 'unserialize_callback');
spl_autoload_register(array($GLOBALS['Library'], 'declare_class'));
?>
