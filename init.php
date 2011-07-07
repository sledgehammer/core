<?php
/**
 * SledgeHammer Core initialiseren
 *
 * @package Core
 */
namespace SledgeHammer;
if (!defined('SledgeHammer\MICROTIME_START')) {
	define('SledgeHammer\MICROTIME_START', microtime(true));
}
define('SledgeHammer\PATH', dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR); // Het pad instellen. Dit is de map waar de sledgehammer map in staat.
define('SledgeHammer\E_MAX', (E_ALL | E_STRICT)); /// Echt alle errors afvangen, inclusief de PHP5 STRICT hints
error_reporting(E_MAX); // Foutniveau activeren
if (ini_get('date.timezone') == '') { // Is er geen tijdzone ingesteld?
	date_default_timezone_set('Europe/Amsterdam'); // Voorkom foutmeldingen door de tijdzone in te stellen
}
$corePath = dirname(__FILE__).'/';
require_once($corePath.'functions.php'); 
require_once($corePath.'classes/Object.php'); // De generieke superclass
require_once($corePath.'classes/Framework.php'); // Helper class voor modules e.d. 
require($corePath.'classes/ErrorHandler.php');
require($corePath.'classes/AutoLoader.php');

$GLOBALS['charset'] = 'UTF-8';
// ErrorHandeler instellen (standaard configuratie: geeft geen output, maar logt deze naar de error_log())
$GLOBALS['ErrorHandler'] = new ErrorHandler;
$GLOBALS['ErrorHandler']->init();

$GLOBALS['AutoLoader'] = new AutoLoader(PATH); // De AutoLoader aanmaken. (maar om te functioneren moet de $AutoLoader->init() nog aangeroepen worden)

spl_autoload_register(array($GLOBALS['AutoLoader'], 'define'));
?>
