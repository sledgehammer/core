<?php
/**
 * Functies die ook buiten de SledgeHammer namespace direct benaderd kunnen worden.
 */

/**
 * Een zeer krachtige debug functie die de inhoud van een variabele (met htmlopmaak) toont
 */
function dump($variable, $export = false) {
	if (!class_exists('SledgeHammer\Dump', false)) {
		include(dirname(__FILE__).'/classes/Dump.php');
	}
	if ($export) {
		ob_start();
		SledgeHammer\Dump::render_dump($variable);
		return ob_get_clean();
	} else {
		SledgeHammer\Dump::render_dump($variable);
	}
}

/**
 * Een crusiale fout. (script kan niet meer functioneren)
 */
function error($message, $information = NULL) {
	SledgeHammer\ErrorHandler::handle(E_USER_ERROR, $message, $information, true);
	exit(1); // Het script direct stoppen.
}

/**
 * Een fout. (Het script kan beperkt zijn taak volbrengen)
 */
function warning($message, $information = NULL) {
	SledgeHammer\ErrorHandler::handle(E_USER_WARNING, $message, $information, true);
}
/**
 * Een foutje. (Het script werkt, maar een iets niet helemaal lekker.)
 */
function notice($message, $information = NULL) {
	SledgeHammer\ErrorHandler::handle(E_USER_NOTICE, $message, $information, true);
}

/**
 * Een verouderde functionaleit.
 */
function deprecated($message = 'Deze functionaliteit zal in de toekomst niet meer ondersteund worden.', $information = NULL) {
	if (defined('E_USER_DEPRECATED')) {
		SledgeHammer\ErrorHandler::handle(E_USER_DEPRECATED, $message, $information, true); // Kan pas sinds php 5.3.0
	} else {
		notice('Deprecated: '.$message, $information);
	}
}

/**
 * Als de variable bestaat wordt de waarde gereturnt, anders wordt niks (null) gereturnd. (Zonder foutmeldingen)
 * Let op! Heeft als side-effect dat de variable wordt ingesteld op null. array_value() heeft hier geen last van, maar is alleen geschikt voor arrays. 
 *
 * i.p.v.
 *   if (isset($_GET['foo']) && $_GET['foo'] == 'bar') {
 * Schrijf je:
 *   if (value($_GET['foo']) == 'bar') {
 * 
 * @return mixed
 */
function value(&$variable) {
	if (isset($variable)) {
		return $variable;
	}
}

?>
