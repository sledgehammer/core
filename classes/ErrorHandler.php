<?php
/**
 * Verzorgt de afhandeling van php fouten. 
 * Deze kan een uitgebreide foutmeling in html-formaat tonen en/of emailen
 *
 * @package Core
 */
namespace SledgeHammer;
class ErrorHandler {

	public 
		// De verschillende afhandlings opties.
		$log           = true,  // Schrijf de fout ook weg naar het php errorlog (/var/log/httpd/error.log?)
		$cli           = false, // echo de foutmelding zonder extras' met een timestamp, (php cli.php > error.log)
		$html          = false, // echo de foutmelding en extra informatie met html opmaak 
		$email         = false, // Email de foutmelding naar dit emailadres.

		// Limiet aan het aantal email dat de ErrorHandler verstuurd. 
		// Bij waardes zoals false ("", 0, NULL) is er GEEN limiet
		$emails_per_request = false, // Het aantal fouten dat gemaild gedurende 1 php script
		$emails_per_minute = false, // Het aantal fouten dat gemaild mag worden per minuut
		$emails_per_day = false; // Het aantal fouten dat gemaild mag worden per dag

	private
		$error_types = array(
			E_WARNING => 'Warning',
			E_NOTICE => 'Notice',
			E_ERROR => 'Error',
			E_USER_ERROR => 'Error',
			E_USER_WARNING => 'Warning',
			E_USER_NOTICE => 'Notice',
			E_STRICT => 'PHP5_Strict',
			4096 => 'RecoverableError', // E_RECOVERABLE_ERROR constante is pas bekend sinds php 5.2.0
			8192 => 'Deprecated', //  E_DEPRECATED constante is pas bekend sinds php 5.3.0
			16384 => 'Deprecated', // E_USER_DEPRECATED constante is pas bekend sinds php 5.3.0
			'EXCEPTION' => 'Exception',
			// Error levels that can't be caught or triggered directly, but could be retrieved with error_get_last()
			E_COMPILE_WARNING => 'Warning',
			E_COMPILE_ERROR => 'Error',
			E_CORE_WARNING => 'Warning',
			E_CORE_ERROR => 'Error',
			E_PARSE => 'Error',
			
		),
		$max_string_length_backtrace = 51200, // Maximaal 50 KiB per argument in de backtrace weergeven
		$isProcessing = false; // Wordt gebruikt voor het bepalen van fouten tijdens de error reporting

	/**
 	 * Deze ErrorHandler instellen voor het afhandelen van de errormeldingen. 
 	 */
	function init() {
		set_exception_handler(array($this, 'handle_exception'));
		// Vanwege een bug in php5.3.x is de ErrorHandler::trigger_error_callback() vervangen door deze een globale functie 
		// Zie http://bugs.php.net/bug.php?id=50519 voor meer informatie
		if (function_exists('ErrorHandler_trigger_error_callback') == false) {
			// Defineer een globale nieuwe functie
			function ErrorHandler_trigger_error_callback($type, $message, $filename = NULL, $line = NULL, $context = NULL) {
				ErrorHandler::handle($type, $message);
				if ($type == E_USER_ERROR || $type == 4096) { // 4096 == E_RECOVERABLE_ERROR
					exit();
				}	
			}
			function ErrorHandler_shutdown_callback() {
        $error = error_get_last();
        if ($error !== NULL && $error['type'] === E_ERROR) {
					//ErrorHandler::handle($error['type'], $error['message'], $error['file'], $error['line']);
					ErrorHandler::handle($error['type'], $error['message']);
					//ErrorHandler_trigger_error_callback($error['type'], $error['message'], $error['file'], $error['line']);
					
        }
			}
		}
		register_shutdown_function('SledgeHammer\ErrorHandler_shutdown_callback');
		set_error_handler('SledgeHammer\ErrorHandler_trigger_error_callback');
	}

	/**
	 * Functie die wordt aangeroepen door de functies notice() / warning() / error() en de error handler functie
	 *
	 * @param $check_for_alternate_error_handler Controleert of de error door een andere error_handler moet worden afgehandeld (Bv: SimpleTestErrorHandler) 
	 */
	static function handle($type, $message, $information = NULL, $check_for_alternate_error_handler = false) {
		if (error_reporting() != (error_reporting() | $type)) { // Dit type fout niet afhandelen?
			return;
		}
		if ($check_for_alternate_error_handler) {
			$callback = set_error_handler('SledgeHammer\ErrorHandler_trigger_error_callback');
			if ($callback !== 'SledgeHammer\ErrorHandler_trigger_error_callback') {
				if ($callback === NULL) {
					restore_error_handler();
				} else {
					set_error_handler($callback);
				}
				$conversion_table = array(E_ERROR => E_USER_ERROR, E_WARNING => E_USER_WARNING, E_NOTICE => E_USER_NOTICE);
				if (array_key_exists($type, $conversion_table)) {
					$type = $conversion_table[$type]; // Voorkom "Invalid error type specified" bij trigger_error
				}
				trigger_error($message, $type); // De fout doorgeven aan de andere error handler
				return;
			}
		}
		if (get_class(@ $GLOBALS['ErrorHandler']) == 'SledgeHammer\ErrorHandler') {
			$GLOBALS['ErrorHandler']->process($type, $message, $information);
			return;
		} else { 
			if ($type == E_STRICT) {
				$type = E_USER_NOTICE;
			}
			echo '<span style="color:red">The ErrorHandler is not configured.</span><br />'."\n";
			$message .= ' in <b>'.self::file().'</b> on line <b>'.self::line().'</b><br />'; // Het bericht uitbreiden met bestand en regelnummer informatie.
		}
		trigger_error($message, $type); // De fout doorgeven aan een andere error handler
	}

	/**
	 * De error van html-opmaak voorzien
	 *
	 * @param int|Exception $type 
	 * @param string $message
	 * @param mixed $information
	 */
	function render($type, $message = NULL, $information = NULL) {
		$style = array(
			'margin-top: 8px',
			'margin-bottom: 16px',
			'padding: 6px',
			'background-color: #ffffe1',
			'color: #000000;',
			'font-family: Tahoma, sans-serif',
			'font-size: 12px',
			'line-height: 14px',
			'text-align: left',
			'text-shadow: none',
			'overflow-x: auto',
		);
		if (!$this->email) {
			$style[] = 'border: 1px dashed #cfcfcf';
		}
		if ($type instanceof \Exception) {
			$message = $type->getMessage();
			$type = 'EXCEPTION';
		}
		if (strtolower($this->error_types[$type]) == 'notice') {
			$message_color  = '#0000cc';
		} else {
			$message_color  = '#cc0000';
		}
		echo '<div style="'.implode(';', $style).'"><img style="margin-right: 8px;margin-bottom: 4px" src="http://bfanger.nl/core/ErrorHandler/'.strtolower($this->error_types[$type]).'.gif" alt="" align="left" /><span style="color:'.$message_color.'">'."\n";
		if (is_array($message)) {
			$message = 'Array';
		}
		if ($information === NULL && strpos($message, 'Missing argument ') === 0) {
			$information = $this->search_function($message); // informatie tonen over welke parameters er verwacht worden.
		}
		$message_plain = $message;
		if (strpos($message, '<span style="color:') === false) { // Alleen html van de syntax_hightlight functie toestaan, alle overige htmlkarakers escapen
			$message_plain = htmlspecialchars($message_plain); // Vertaal alle html karakters
		}
		echo '<b>'.$this->error_types[$type].':</b> '.$message_plain.'</span><br clear="all" />'."\n".'';
		if ($information !== NULL && !empty($information)) {
			echo "<b>Extra information</b><br />\n<span style='color:#007700'>";
			if (is_array($information)) {
				$this->export_array($information);
			} elseif (is_object($information)) {
				echo syntax_highlight($information).":<br />\n";
				$this->export_array($information);
			} else {
				echo $information."<br />\n";
			}
			echo '</span>';
		}
		if ($this->email) { // email specifieke informatie blokken genereren?
			$this->client_info();
			$this->server_info();
		}
		$this->backtrace();
		switch ($type) {

			case E_WARNING:
			case E_USER_ERROR:
			case E_USER_WARNING:
				if (!empty($GLOBALS['Databases'])) {
					echo '<b>Databases</b><br />';
					$popup = $this->email ? false : true;
					foreach ($GLOBALS['Databases'] as $link => $Database) {
						if (is_object($Database) && method_exists($Database, 'debug')) {
							echo $link.': ';
							$Database->debug($popup);
							echo "<br />\n";
						}
					}
				}
				break;
		}
		echo '</div>';
	}

	/**
	 * De fout afhandelen volgens de afhandel opties (email, log, html). 
	 *
	 * @param int $type E_USER_NOTICE, E_USER_ERROR, enz
	 * @param string $message De foutmelding
	 * @param mixed $information Extra informatie voor de fout. bv array('Tip' => 'Controleer ...')
	 */
	private function process($type, $message, $information = NULL) {
		if ($this->isProcessing) {
			echo '<span style="color:red">ErrorHandler failure';
			if ($this->html && is_string($message)) { // show error?
				echo ' ', htmlentities($message);
			}
			echo ' </span>';
			error_log('ErrorHandler failure: '.$message);
			return;
		}
		$this->isProcessing = true;
		if ($this->log || $this->cli) {
			$error_message = $this->error_types[$type].': '.$message;
			if ($file = self::file()) {
				$error_message .= ' in '.$file.' on line '.self::line();
			}
			if ($this->log) {
				error_log($error_message);
			}
			if ($this->cli) {
				echo '['.date('Y-m-d H:i:s').'] '.$error_message."\n";
			}
		}
		// Limiet contoleren
		if ($this->email) { // Een foutrapport via email versturen?
			$limit = $this->import_email_limit();
			if ($limit < 1) { // Is de limiet bereikt?
				$this->email = false; // Niet emailen
			}
		}
		if (!$this->email && !$this->html) { // Geen email en geen html uitvoer
			$this->isProcessing = false;
			return; // Stoppen met het bouwen van een foutrapport
		}
		if ($this->email) { // De limiet is niet niet bereikt.
			ob_start(); // bouw de html van de foutmelding eerst op in een buffer.
		}
		self::render($type, $message, $information);
		if ($this->email) { // email versturen?
			// Headers voor de email met HTML indeling
			$headers = "MIME-Version: 1.0\n";
			$headers .= "Content-type: text/html; charset=iso-8859-1\n";
			$headers .= 'From: '.$this->from_email()."\n";

			if (function_exists('mail') && !mail($this->email, $this->error_types[$type].': '.$message, '<html><body style="background-color: #ffffe1">'.ob_get_contents()."</body></html>\n", $headers)) {
				error_log('Het foutrapport kon niet per mail verstuurd worden.');
			}
			if ($this->html) {
				ob_end_flush(); // buffer weergeven
			} else {
				ob_end_clean(); // buffer niet weergeven
			}
		} else {
			flush();
		}
		$this->isProcessing = false;
	}

	/**
	 * Bestand waar de fout in optrad
	 */
	static function file() {
		$location = debug_backtrace();
		$filename = false;
		for($i = 0; $i < count($location); $i++) {
			if (isset($location[$i]['file']) && $location[$i]['file'] !== __FILE__) {
				if ($location[$i]['function'] == 'handle') {
					continue;
				}
				$filename = $location[$i]['file'];
				break;
			}
		}
		$filename = (strpos($filename, PATH) === 0) ? substr($filename, strlen(PATH)) : $filename;
		return $filename;
	}

	/**
	 * Regel waar de fout in optrad
	 */
	static function line() {
		$location = debug_backtrace();
		for ($i = 0; $i < count($location); $i++) {
			if (isset($location[$i]['file']) && $location[$i]['file'] !== __FILE__) {
				if ($location[$i]['function'] == 'handle') {
					continue;
				}
				return $location[$i]['line'];
			}
		}
	}

	/**
	 * De bestanden, regelnummers, functie en objectnamen waar de fout optrad weergeven.
	 */
	private function backtrace() {
		echo '<b>Backtrace</b><div>'."\n";
		$backtrace = debug_backtrace();

		// Pass 1: 
		//   Backtrace binnen de errorhandler niet tonen
		//   Bij een Exception de $Exception->getTrace() tonen.
		//   De plaats van de errror extra benadrukken (door een extra witregel)
		while ($call = next($backtrace)) {
			if (@$call['class'] != __CLASS__) {
				if ($call['function'] == 'SledgeHammer\ErrorHandler_trigger_error_callback') { // Is de fout getriggerd door php
					if (isset($call['file'])) { // Zit de fout niet in een functie? 
						$this->backtrace_highlight($call, true); // Dan is de fout afkomsting van deze $call, maar verberg de ErrorHandler_trigger_error_callback parameters
						next($backtrace);
						break;
					} else { // De fout komt uit een functie (bijvoorbeeld een Permission denied uit een fopen()) 
						$call = next($backtrace); // Sla deze $call over deze bevat alleen de ErrorHandler_trigger_error_callback() aanroep
						$this->backtrace_highlight($call);
						next($backtrace);
						break;
					}
				} elseif ($call['function'] == 'ErrorHandler_shutdown_callback') {
					$error = error_get_last();
					$this->backtrace_highlight($error);
					next($backtrace);
					break;
				}
				$this->backtrace_highlight($call);
				next($backtrace);
				break;
			} elseif ($call['function'] == 'handle_exception' || ($call['function'] == 'render' && $call['args'][0] instanceof \Exception)) { // Gaat het om een Exception
				$Exception =  $call['args'][0];
				$call['file'] = $Exception->getFile();
				$call['line'] = $Exception->getLine();
				$this->backtrace_highlight($call, true);
				$backtrace = $Exception->getTrace();
				break;
			}
		}
		echo '<br />';
		// Pass 2: Alle elementen weergeven uit de $backtrace die nog niet behandeld zijn 
		while ($call = current($backtrace)) {
			$this->backtrace_highlight($call);
			next($backtrace);
		}	
		echo '</div>';
	}

	private function backtrace_highlight($call, $location_only = false) {
		if (!$location_only) {
			if (isset($call['object'])) {
				echo syntax_highlight(get_class($call['object']), 'class');
				echo $call['type'];
			} elseif (isset($call['class'])) {
				echo syntax_highlight($call['class'], 'class');
				echo $call['type'];
			}
			if (isset($call['function'])) {
				echo syntax_highlight($call['function'], 'method');
				$errorHandlerInvocations = array('trigger_error_callback', 'trigger_error', 'warning', 'error', 'notice', 'deprecated');
				$databaseClasses = array('Database', 'mysqli', 'MySQLiDatabase', 'SledgeHammer\MySQLiDatabase'); // prevent showing passwords in the backtrace.
				$databaseFunctions = array('mysql_connect', 'mysql_pconnect', 'mysqli_connect', 'mysqli_pconnect');
				if (in_array($call['function'], array_merge($errorHandlerInvocations, $databaseFunctions)) || ($call['function'] == 'connect' && in_array(@$call['class'], $databaseClasses)) || (in_array($call['function'], array('call_user_func', 'call_user_func_array')) && in_array($call['args'][0], $errorHandlerInvocations)))  {
					echo '(...)';
				} else {
					echo '(';
					if (isset($call['args'])) {
						$args = array();
						foreach ($call['args'] as $arg) {
							if (is_string($arg) && strlen($arg) > $this->max_string_length_backtrace) {
								$kib = round((strlen($arg) - $this->max_string_length_backtrace) / 1024);
								$arg = substr($arg, 0, $this->max_string_length_backtrace);
								$args[] = syntax_highlight($arg).'<span style="color:red;">...'.$kib.'&nbsp;KiB&nbsp;truncated</span>';
							} else {
								$args[] = syntax_highlight($arg);
							}
						}
						echo implode(', ', $args);
					}
					echo ')';
				}
			}
		}
		if (!empty($call['file'])) {
			if (strpos($call['file'], PATH) === 0) {
				$call['file'] = substr($call['file'], strlen(PATH));
			}
			echo ' in&nbsp;<b>'.str_replace('\\', '/', $call['file']).'</b>'; // De bestandnaam opvragen en filteren.
		}
		if (isset($call['line'])) {
			echo ' on&nbsp;line&nbsp;<b>'.$call['line'].'</b>';
		}
		echo '<br />'."\n";
	}

	/**
	 * Een functie die de juiste functie syntax laat zien. ipv de standaard 'argument x is missing' melding
	 */
	private function search_function($message) {
		preg_match('/[0-9]+/', $message, $argument);
		$argument = $argument[0] - 1;
		$location = debug_backtrace();
		for ($i = 0; $i < count($location); $i++) {
			if ($location[$i]['file'] != __FILE__) {
				$file = file($location[$i]['file']);
				$function = $file[$location[$i]['line'] - 1];
				break;
			}
		}
		$function = preg_replace("/^[ \t]*(public |private |protected )function |\n/", '', $function);
		return array('Function' => $function);
	}

	/**
	 * Gegevens over de omgeving van de client
	 */
	private function client_info() {
		$browser = browser();
		echo "<div>\n";
		echo "<b>Client information</b><br />\n";
		if (isset($_SERVER['REQUEST_URI'])) {
			$href = (value($_SERVER['HTTPS']) == 'on') ? 'https' : 'http';
			$href .= '://'.$_SERVER['SERVER_NAME'];
			if ($_SERVER['SERVER_PORT'] != 80) {
				$href .= ':'.$_SERVER['SERVER_PORT'];
			}
			$href .= $_SERVER['REQUEST_URI'];
			echo '<b>URI:</b> <a href="'.$href.'">'.$_SERVER['REQUEST_URI']."</a><br />\n";
		}
		if (isset($_SERVER['HTTP_REFERER'])) {
			echo '<b>Referer:</b> '.$_SERVER['HTTP_REFERER']."<br />\n";
		}
		if (isset($_SERVER['REMOTE_ADDR'])) {
			echo '<b>IP:</b> '.$_SERVER['REMOTE_ADDR']."<br />\n";
		}
		$browser = browser();
		echo '<b>Browser:</b> '.$browser['name'].' '.$browser['version'].' for '.$browser['os'].' - <em>'.syntax_highlight(@$_SERVER['HTTP_USER_AGENT'])."</em><br />\n";
		echo '<b>Cookie:</b> '.syntax_highlight(@count($_COOKIE) != 0)."<br />\n";
		echo '</div>';
	}

	/**
	 * Gegevens over de server
	 */
	private function server_info() {
			echo "<div>\n";
			echo "<b>Server information</b><br />\n";
			echo '<b>Hostname:</b> '.php_uname('n')."<br />\n";
			echo '<b>Environment:</b> '.ENVIRONMENT."<br />\n";
			if (isset($_SERVER['SERVER_SOFTWARE'])) {
				echo '<b>Software:</b> '.$_SERVER['SERVER_SOFTWARE']."<br />\n";
			}
			echo '</div>';

	}
	/**
	 * Een array printen met syntax highlighting.
	 */
	private function export_array($array) {
		foreach ($array as $key => $value) {
			if (is_array($value) && count($value) != 0) {
				echo '<b>'.$key.':</b><br />'."\n";
				if (is_indexed($value)) {
					foreach($value as $value2) {
						echo '&nbsp;&nbsp;'.syntax_highlight($value2)."<br />\n";
					}
				} else {
					foreach ($value as $key2 => $value2) {
						echo '&nbsp;&nbsp;<em>'.$key2.':</em> '.syntax_highlight($value2)."<br />\n";
					}
				}
			} else {
				echo '<b>'.$key.':</b> '.syntax_highlight($value).'<br />'."\n";
			}
		}
	}

	/**
	 * Opvragen hoeveel emails er nog verstuurd mogen worden.
	 *
	 * @return false|int Retourneert the limiet
	 */
	private function import_email_limit() {
		// Waardes uit de configuratie corrigeren
		$this->emails_per_day = $this->cast_to_limit($this->emails_per_day);
		$this->emails_per_minute = $this->cast_to_limit($this->emails_per_minute);
		$this->emails_per_request = $this->cast_to_limit($this->emails_per_request);
		//
		if ($this->emails_per_request !== 'NO_LIMIT') {
			$limit = $this->emails_per_request;
			$this->emails_per_request--;
			if ($limit < 1) {
				$this->emails_per_request = 0;
				return 0;
			}
		}
		$filename = PATH.'tmp/error_handler_email_limit.txt';
		if (!@file_exists($filename)) {
			error_log('File "'.$filename.'" doesn\'t exist (yet)');
			// nieuwe voorraad.
			$day_limit = $this->emails_per_day;
			$minute_limit = $this->emails_per_minute;
		} else { // het bestand bestaat.
			// Bestand inlezen.
			$fp = @fopen($filename, 'r');
			if ($fp === false) {
				error_log('Reading file "'.$filename.'" failed');
				return false; // Kon het bestand niet openen.
			}
			$datum = @rtrim(fgets($fp)); // datum inlezen.
			$day_limit = $this->cast_to_limit(@rtrim(fgets($fp)), $this->emails_per_day);
			$tijdstip = @rtrim(fgets($fp)); // minuut inlezen
			$minute_limit = $this->cast_to_limit(@rtrim(fgets($fp)), $this->emails_per_minute);
			@fclose($fp);

			if ($datum != @date('j-n')) { // is het een andere datum?
				$day_limit = $this->emails_per_day; // nieuwe dag voorraad.
				$minute_limit = $this->emails_per_minute;
				error_log('Resetting errorlimit');
			} elseif ($tijdstip != @date('H:i')) { // Is het een andere minuut
					$minute_limit = $this->emails_per_minute;// nieuwe minuut voorraad.
			}
		}
		$limit = 0; // Standaard instellen dat de limiet is bereikt.
		if ($day_limit === 'NO_LIMIT' && $minute_limit === 'NO_LIMIT') { // Is er helemaal geen limiet?
			$limit = 999;
		} elseif ($day_limit === 'NO_LIMIT') { // Er is geen limiet per dag?
			$limit = $minute_limit; // Gebruik de limit van de minuut
			$minute_limit--; // voorraad verminderen
		} elseif ($minute_limit === 'NO_LIMIT') { // Geen limit per minuut?
			$limit = $day_limit; // Gebruik de limit van de minuut
			$day_limit--; // voorraad verminderen
		} else { // Er is zowel een limiet per dag als per minuut
			$limit = ($day_limit < $minute_limit) ? $day_limit : $minute_limit; // limit wordt de laagste waarde
			// Voorraad verminderen
			$day_limit--;
			$minute_limit--;
		}
		// Is de limiet van 0 naar -1 veranderd, zet deze weer op 0
		$day_limit = $this->cast_to_limit($day_limit);
		$minute_limit = $this->cast_to_limit($minute_limit);
		
		// Wijzigingen opslaan
		$fp = @fopen($filename, 'w');
		if ($fp !== false) {
			$write = @fwrite($fp, @date('j-n')."\n".$day_limit."\n".@date('H:i')."\n".$minute_limit);
			if ($write == false) {
				error_log('Writing to file "'.$filename.'"  failed');
				return false; // Kon niet schrijven.
			}
			@fclose($fp);
		} else {
			error_log('Unable to open file "'.$filename.'" in write mode');
			return false; // Kon het bestand niet openen om te schrijven
		}
		return $limit;
	}

	/**
	 * Zet een variable om naar een limiet (0 of groter of  "NO_LIMIT")
	 * En corrigeert de waarde als een van de waardes NO_LIMIT 
	 *
	 * @param mixed $limit wordt omgezet naar een int of "NO_LIMIT"
	 * @param int|string $configured_limit ingestelde limiet
	 */
	private function cast_to_limit($limit, $configured_limit = NULL) {
		$limit = ($limit === 'NO_LIMIT') ? 'NO_LIMIT' : (int) $limit;
		if ($configured_limit !== NULL) {
			if ($limit === 'NO_LIMIT' && $configured_limit !== 'NO_LIMIT') { // Als er in het error_handler_email_limit.txt "NO_LIMIT" staat, maar in de config 100, return dan 100 
				return $configured_limit;
			}
			if ($configured_limit === 'NO_LIMIT') { // Als er NO_LIMIT in de config staat return dan NO_LIMIT ongeacht waarde uit error_handler_email_limit.txt
				return 'NO_LIMIT';
			}
			if ($limit > $configured_limit) { // Als er in het error_handler_email_limit een groter getal staan dan in de config?
				return $configured_limit; // return de config
			}
		}
		// Rond negative getallen af naar 0 
		if (is_int($limit) && $limit < 0) {	
			return 0;
		}
		return $limit;
	}

	/**
	 * Callback voor exceptions die buiten een try/catch block ge-throw-t worden. 
	 *
	 * @param Exception $exception
	 */
	static function handle_exception($exception) {
		if ($exception instanceof \Exception) {
			self::handle(E_USER_ERROR, 'Uncaught exception: '.$exception->getMessage());
		} else {
			self::handle(E_USER_ERROR, 'Parameter $exception must be an Exception, instead of a '.gettype($exception));
		}
	}

	/**
	 * Vraag het from gedeelte van de te versturen email op.
	 *
	 * return string Bv '"ErrorHandler (www.example.com)" <errorhandler@example.com>'
	 */
	private function from_email() {
		$hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
		$regexDomain = '/[a-z0-9-]+(.[a-z]{2}){0,1}\.[a-z]{2,4}$/i';
		if (preg_match($regexDomain, $hostname, $match)) { // Zit er een domeinnaam in de hostname? 
			$domain = $match[0];
		} elseif (preg_match($regexDomain, $this->email, $match)) { // de hostname was een ip, localhost, ofzo. Pak dan de domeinnaam van het "To:" adres 
			$domain = $match[0];
		} else { // Er zit zelfs geen domeinnaam in het email-adres?
			$domain = 'localhost';
		}
		return '"ErrorHandler ('.$hostname.')" <errorhandler@'.$domain.'>';
	}
}
?>
