<?php
/**
 * Basic HTTP autentication
 * Usage:
 *   $auth =  new HttpAuthentication('my_app');
 *   if ($credentials = $auth->import($error)) {
 *     if (my_check($credentials['username'], $credentials['password'])) {
 *       return true;
 *     }
 *     $auth->reset(); 
 *   }
 *   return false;
 *
 * (Volgt de Import interface uit de forms module)
 * 
 * @todo Uitloggen werkt niet in Google Chrome
 * 
 * @package Core
 */
namespace SledgeHammer;
class HttpAuthentication extends Object {

	private
		$realm;

	function __construct($realm) {
		$this->realm = $realm;
	}

	function initial($value) {
		throw new Exception('Can\'t set initial values');
	}

	/**
	 * Aanmelden volgens het HTTP Protocol
	 *
	 * @return array|false|NULL Retourneerd array met de credentials bij succes, false & NULL bij mislukt, waarbij NULL het aanmelden door de gebruiker is geannuleerd
	 */
	function import(&$errorMessage, $source = NULL) {
		if(headers_sent()) {
			$errorMessage = 'Inloggen is mislukt';
			warning('Unable to login, headers already sent');
			return false;
		}
		$state = $this->getState();
		switch ($state) {

			case 'readCredentials': // Gebruikernaam en wachtwoord opvragen
				if(isset($_SERVER['PHP_AUTH_USER'])) { // Is er een gebruikersnaam bekend?
					// De onderstaande foutmelding wordt ook ingesteld als de gebruikersnaam/wachtwoord wel klopt. Die controle bevind zich buiten deze class.
					$errorMessage = 'De opgegeven gebruikersnaam/wachtwoord combinatie is ongeldig';
					return array('username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']);
				} else { // Geen inloggegevens 
					$this->send_headers();
					$errorMessage = 'Het inloggen is geannuleerd'; // HTTP Authentication is canceled
					return false;
				}
				break;
				
			// De inloggegevens waren incorrect
			case 'ignoreCredentials':
				$errorMessage = 'Het inloggen is geannuleerd'; // HTTP Authentication is canceled				
				$this->send_headers();
				return false;
				
			case 'loggedOut':
				$this->setState('readCredentials');
				$errorMessage = 'Het inloggen is geannuleerd'; // HTTP Authentication is canceled
				$this->send_headers();
				return false;

			default:
				$errorMessage = 'Unexpected login state: "'.$state.'"';
				header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden');
				warning($errorMessage);
				$this->reset();				
				return false;
				
		}
	}

	/**
	 * De inloggegevens resetten, zodat er opnieuw een inlogscherm getoond wordt.
	 * Deze functie moet je aanroepen als de credentials incorrect waren
	 */
	function reset() {		
		header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
		if(isset($_SERVER['PHP_AUTH_USER'])) {
			if ($this->getState() == 'readCredentials') {
				$_SESSION['HttpAuthentication_ignoreCounter'] = 0;
			}
			$this->setState('ignoreCredentials'); // Negeer de credentials de volgende request
			
		}
	}
	
	/**
	 * Gebruik deze functie om de gebruiker uit te loggen.
	 */
	function logout() {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
		$this->setState('loggedOut');// init session
	}

	/**
	 * De http headers sturen om het login scherm te tonen.
	 */
	private function send_headers() {
		header($_SERVER['SERVER_PROTOCOL'].' 401 Unauthorized');
		header('WWW-Authenticate: Basic realm="'.addslashes($this->realm).'"');
		if ($this->getState() == 'ignoreCredentials') {
			$_SESSION['HttpAuthentication_ignoreCounter']++;
			$browser = browser('name');
			if ($browser == 'Microsoft Internet Explorer') {
				if ($_SESSION['HttpAuthentication_ignoreCounter'] > 1) { // Na 2x een WWW-Auth pas weer letten op de credentials 
					$this->setState('readCredentials');
				}  					
			} else { // Firefox zal direct een loginbox tonen.
				$this->setState('readCredentials');
			}		
		} else {
			$this->setState('readCredentials');
		}
	}
	
	/**
	 * De status opvragen
	 * 
	 * @return string Status
	 */
	private function getState() {
		if (!isset($_SESSION)) {
			session_start();
		}
		if (empty($_SESSION['HttpAuthentication_state'])) { // Zijn er nog geen sessie gegevens?
			$_SESSION['HttpAuthentication_state'] = 'readCredentials';
		}
		return $_SESSION['HttpAuthentication_state'];
	}
	
	/**
	 * De status instellen
	 * 
	 * @param string $state
	 * @return void
	 */
	private function setState($state) {
		if (!isset($_SESSION)) {
			session_start();
		}
		$_SESSION['HttpAuthentication_state'] = $state;
	}
}
?>
