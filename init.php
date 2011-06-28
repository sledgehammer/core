<?php
/**
 * Het SledgeHammer Core initialiseren
 *
 * @package Core
 */
namespace SledgeHammer;
if (!defined('MICROTIME_START')) {
	define('MICROTIME_START', microtime(true));
}
define('PATH', dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR); // Het pad instellen. Dit is de map waar de modules in staan.
define('E_MAX', (E_ALL | E_STRICT)); /// Echt alle errors afvangen, inclusief de PHP5 STRICT hints
error_reporting(E_MAX); // Foutniveau activeren
if (ini_get('date.timezone') == '') { // Is er geen tijdzone ingesteld?
	date_default_timezone_set('Europe/Amsterdam'); // Voorkom foutmeldingen door de tijdzone in te stellen
}
$corePath = dirname(__FILE__).'/';
require_once($corePath.'functions.php'); // De functies voor in de SledgeHammer namespace
require_once($corePath.'global_functions.php'); // De functies voor in de global namespace
require_once($corePath.'classes/Object.php'); // De generieke superclass
require_once($corePath.'classes/Framework.php'); // Helper class voor modules e.d. 
require($corePath.'classes/ErrorHandler.php');
require($corePath.'classes/AutoLoader.php');

$GLOBALS['charset'] = 'UTF-8';
// ErrorHandeler instellen (standaard configuratie is geen output, maar alleen error_log())
$GLOBALS['ErrorHandler'] = new ErrorHandler;
$GLOBALS['ErrorHandler']->init();

$GLOBALS['AutoLoader'] = new AutoLoader(PATH); // De AutoLoader aanmaken. (maar om te functioneren moet de $AutoLoader->init() nog aangeroepen worden)

ini_set('unserialize_callback_func', 'unserialize_callback');
spl_autoload_register(array($GLOBALS['AutoLoader'], 'declareClass'));
?>
