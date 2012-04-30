<?php
/**
 * Verzorgt de afhandeling van php fouten.
 * Deze kan een uitgebreide foutmeling in html-formaat tonen en/of emailen
 *
 * @package Core
 */
namespace SledgeHammer;
class ErrorHandler {

	/**
	 * @var Schrijf de fout ook weg naar het php errorlog (/var/log/httpd/error.log?)
	 */
	public $log = true;

	/**
	 * @var echo de foutmelding zonder extras' met een timestamp, (php cli.php > error.log)
	 */
	public $cli = false;

	/**
	 * @var echo de foutmelding en extra informatie met html opmaak.
	 */
	public $html = false;

	/**
	 * @var string Email de foutmelding naar dit emailadres.
	 */
	public $email = false;
	// Limiet aan het aantal email dat de ErrorHandler verstuurd.
	// Bij waardes zoals false ("", 0, NULL) is er GEEN limiet
	/**
	 * @var Het aantal fouten dat gemaild gedurende 1 php script.
	 */
	public $emails_per_request = 'NO_LIMIT';

	/**
	 * @var Het aantal fouten dat gemaild mag worden per minuut.
	 */
	public $emails_per_minute = 'NO_LIMIT';

	/**
	 * @var Het aantal fouten dat gemaild mag worden per dag.
	 */
	public $emails_per_day = 'NO_LIMIT';

	/**
	 * @var int  Limit the amount of errors with a full backtrace. Setting this value too high may cause browser crashes. (The limit is measured per error-type)
	 */
	public $detail_limit = 50;
	private $limits = array();

	/**
	 * Some error levels can't be caught or triggered directly, but could be retrieved with error_get_last()
	 * @var error-type to title/color/icon mapping.
	 */
	private $error_types = array(
		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Error',
		E_CORE_WARNING => 'Warning',
		E_COMPILE_ERROR => 'Error',
		E_COMPILE_WARNING => 'Warning',
		E_USER_ERROR => 'Error',
		E_USER_WARNING => 'Warning',
		E_USER_NOTICE => 'Notice',
		E_STRICT => 'PHP5_Strict',
		E_RECOVERABLE_ERROR => 'RecoverableError', // E_RECOVERABLE_ERROR (4096) available since php 5.2.0
		E_DEPRECATED => 'Deprecated', // E_DEPRECATED (8192) available since php 5.3.0
		E_USER_DEPRECATED => 'Deprecated', // E_USER_DEPRECATED (16384) available since php 5.3.0
		'EXCEPTION' => 'Exception',
	);

	/**
	 * @var Maximaal 50 KiB per argument in de backtrace weergeven.
	 */
	private $max_string_length_backtrace = 51200;

	/**
	 * @var Wordt gebruikt voor het bepalen van fouten tijdens de error reporting
	 */
	private $isProcessing = false;

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
					exit(1);
				}
			}

			function ErrorHandler_shutdown_callback() {
				$error = error_get_last();
				if ($error !== NULL && $error['type'] === E_ERROR) {
					ErrorHandler::handle($error['type'], $error['message']);
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
			restore_error_handler();
			if ($callback !== 'SledgeHammer\ErrorHandler_trigger_error_callback') {
				$conversion_table = array(E_ERROR => E_USER_ERROR, E_WARNING => E_USER_WARNING, E_NOTICE => E_USER_NOTICE);
				if (array_key_exists($type, $conversion_table)) {
					$type = $conversion_table[$type]; // Voorkom "Invalid error type specified" bij trigger_error
				}
				trigger_error($message, $type); // De fout doorgeven aan de andere error handler
				return;
			}
		}
		if (get_class(@ Framework::$errorHandler) == 'SledgeHammer\ErrorHandler') {
			Framework::$errorHandler->process($type, $message, $information);
			return;
		} else {
			if ($type == E_STRICT) {
				$type = E_USER_NOTICE;
			}
			echo '<span style="color:red">The ErrorHandler is not configured.</span><br />'."\n";
			$location = self::location();
			if ($location) {
				$message .= ' in <b>'.$location['file'].'</b> on line <b>'.$location['line'].'</b><br />';
			}
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
			'padding: 13px 15px 15px 15px',
			'background-color: #fcf8e3',
			'color: #333',
			'font: 12px/1.25 \'Helvetica Neue\', Helvetica, sans-serif',
			// reset styling
			'text-shadow: none',
			'text-align: left',
			'overflow-x: auto',
			'white-space: normal',
		);

		if (!$this->email) {
			$style[] = 'border: 1px solid #eeb; border-radius: 4px; margin: 15px 5px 18px 5px';
		}
		switch ($type) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$offset = ' -52px';
				$label_color = '#3a77cd'; // blue
				$message_color = '#3a77cd';
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$offset = ' -26px';
				$label_color = '#f89406'; // orange
				$message_color = '#d46d11';
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$offset = ' -78px';
				$label_color = '#999'; // gray
				$message_color = '#333';
				break;
			case E_STRICT:
				$offset = ' -104px';
				$label_color = '#777bb4'; // purple
				$message_color = '#50537f';
				break;
			default:
				$offset = '';
				$label_color = '#c94a48'; // red
				$message_color = '#c00';
		}
		// Determine if a full report should be rendered
		$showDetails = true;
		if ($this->detail_limit !== 'NO_LIMIT') {
			if (isset($this->limits['backtrace'][$type]) === false) {
				$this->limits['backtrace'][$type] = $this->detail_limit;
			}
			if ($this->limits['backtrace'][$type] === 0) {
				$showDetails = false;
			} else {
				$this->limits['backtrace'][$type]--;
			}
		}
		echo "<!-- \"'> -->\n"; // break out of the tag/attribute
		echo '<div style="', implode(';', $style), '">';
		if ($showDetails) {
			echo '<span style="display:inline-block; width: 26px; height: 26px; vertical-align: middle; margin: 0 6px 2px 0; background: url(\'http://bfanger.nl/core/ErrorHandler.png\')'.$offset.'"></span>';
		}
		echo '<span style="font-size:13px; text-shadow: 0 1px 0 #fff;color:', $message_color, "\">";
		if (is_array($message)) {
			$message = 'Array';
		}
		$backtrace = debug_backtrace(); // @todo Implement a more effient way to detect exceptions
		$exception = false;
		if (isset($backtrace[3]['function']) && $backtrace[3]['function'] == 'handle_exception' && $backtrace[3]['args'][0] instanceof \Exception) {
			$exception = $backtrace[3]['args'][0];
			$message = $exception->getMessage();
			if ($exception instanceof InfoException) {
				$information = $exception->getInformation();
			}
		}
		if ($information === NULL && strpos($message, 'Missing argument ') === 0) {
			$information = $this->search_function($message); // informatie tonen over welke parameters er verwacht worden.
		}
		$message_plain = $message;
		if (strpos($message, '<span style="color:') === false) { // Alleen html van de syntax_hightlight functie toestaan, alle overige htmlkarakers escapen
			$message_plain = htmlspecialchars($message_plain); // Vertaal alle html karakters
		}
		if ($showDetails) {
			$label_style = '';
		} else {
			$label_style = ' style="'.implode(';', array(
						'padding: 1px 3px 2px',
						'font-size: 10px',
						'line-height: 15px',
						'text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25)',
						'color: #fff',
						'vertical-align: middle',
						'white-space: nowrap',
						'background-color: '.$label_color,
						'border-radius: 3px',
					)).'"';
		}
		echo "<b".$label_style.">";
		if ($exception) {
			if ($type == E_USER_ERROR) {
				echo 'Uncaught ';
			}
			echo get_class($exception);
		} else {
			echo $this->error_types[$type];
		}
		echo "</b>&nbsp;\n\n\t", $message_plain, "\n\n</span>";

		if ($showDetails || $this->email) {
			echo '<hr style="height: 1px; background: #eeb; border: 0;margin: 6px -2px 12px -2px;" />';
			if ($information !== NULL && !empty($information)) {
				echo "<b>Extra information</b><br />\n<span style='color:#007700'>";
				if (is_array($information)) {
					$this->export_array($information);
				} elseif (is_object($information)) {
					echo syntax_highlight($information), ":<br />\n";
					$this->export_array($information);
				} else {
					echo $information, "<br />\n";
				}
				echo '</span>';
			}
			if ($this->email) { // email specifieke informatie blokken genereren?
				$this->client_info();
				$this->server_info();
			}
			$this->backtrace($backtrace);
			switch ($type) {

				case E_WARNING:
				case E_USER_ERROR:
				case E_USER_WARNING:
					if (class_exists('SledgeHammer\Database', false) && !empty(Database::$instances)) {
						echo '<b>Databases</b><br />';
						$popup = $this->email ? false : true;
						foreach (Database::$instances as $link => $Database) {
							if (is_object($Database) && method_exists($Database, 'debug')) {
								echo $link, ': ';
								$Database->debug($popup);
								echo "<br />\n";
							}
						}
					}
					break;
			}
		} else {
			$location = $this->location();
			if ($location !== false) {
				echo '&nbsp;&nbsp; in <b>', $location['file'], '</b> on line <b>', $location['line'], '</b>';
			}
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
			$location = self::location();
			if ($location) {
				$error_message .= ' in '.$location['file'].' on line '.$location['line'];
			}
			if ($this->log) {
				error_log($error_message);
			}
			if ($this->cli) {
				echo '[', date('Y-m-d H:i:s'), '] ', $error_message, "\n";
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

			if (function_exists('mail') && !mail($this->email, $this->error_types[$type].': '.$message, '<html><body style="background-color: #fcf8e3">'.ob_get_contents()."</body></html>\n", $headers)) {
				error_log('Het foutrapport kon niet per mail verstuurd worden.');
			}
			if ($this->html) {
				ob_end_flush(); // buffer weergeven
			} else {
				ob_end_clean(); // buffer niet weergeven
			}
		} elseif (ob_get_level() === 0) {
			flush();
		}
		$this->isProcessing = false;
	}

	/**
	 * Returns the filename and linenumber where the error was triggered.
	 *
	 * @return array|false  array('file' => $filename, 'line' => $linenumber)
	 */
	static function location() {
		$backtrace = debug_backtrace();
		foreach ($backtrace as $call) {
			;
			if (isset($call['file']) && $call['file'] !== __FILE__ && $call['function'] !== 'handle') {
				return array(
					'file' => ((strpos($call['file'], PATH) === 0) ? substr($call['file'], strlen(PATH)) : $call['file']),
					'line' => $call['line']
				);
			}
		}
		return false;
	}

	/**
	 * De bestanden, regelnummers, functie en objectnamen waar de fout optrad weergeven.
	 */
	private function backtrace($backtrace) {
		echo "<b>Backtrace</b><div>\n";
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
				} elseif ($call['function'] == 'SledgeHammer\ErrorHandler_shutdown_callback') {
					$error = error_get_last();
					$this->backtrace_highlight($error);
					next($backtrace);
					break;
				}
				$this->backtrace_highlight($call);
				next($backtrace);
				break;
			} elseif ($call['function'] == 'handle_exception' || ($call['function'] == 'render' && $call['args'][0] instanceof \Exception)) { // Gaat het om een Exception
				$Exception = $call['args'][0];
				$thrown = array(
					'file' => $Exception->getFile(),
					'line' => $Exception->getLine()
				);
				echo 'Exception thrown ';
				$this->backtrace_highlight($thrown, true);
				$uncaught = next($backtrace);
				if ($uncaught !== false) { // Not an uncaught exception?
					echo '<span style="color:gray">&nbsp;&nbsp;Caught ';
					$this->backtrace_highlight($call, true);
					echo '</span>';
				}
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
				echo syntax_highlight($call['object'], null, 512);
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
				if (in_array($call['function'], array_merge($errorHandlerInvocations, $databaseFunctions)) || ($call['function'] == 'connect' && in_array(@$call['class'], $databaseClasses)) || (in_array($call['function'], array('call_user_func', 'call_user_func_array')) && in_array($call['args'][0], $errorHandlerInvocations))) {
					echo '(...)';
				} else {
					echo '(';
					if (isset($call['args'])) {
						$first = true;
						foreach ($call['args'] as $arg) {
							if ($first) {
								$first = false;
							} else {
								echo ', ';
							}
							if (is_string($arg) && strlen($arg) > $this->max_string_length_backtrace) {
								$kib = round((strlen($arg) - $this->max_string_length_backtrace) / 1024);
								$arg = substr($arg, 0, $this->max_string_length_backtrace);
								echo syntax_highlight($arg), '<span style="color:red;">&hellip;', $kib, '&nbsp;KiB&nbsp;truncated</span>';
							} else {
								echo syntax_highlight($arg, null, 1024);
							}
						}
					}
					echo ')';
				}
			}
		}
		if (!empty($call['file'])) {
			if (strpos($call['file'], PATH) === 0) {
				echo ' in&nbsp;<b title="', htmlentities($call['file']), '">', substr($call['file'], strlen(PATH)), '</b>'; // De bestandnaam opvragen en filteren.
			} else {
				echo ' in&nbsp;<b>', $call['file'], '</b>'; // De bestandnaam opvragen en filteren.
			}
		}
		if (isset($call['line'])) {
			echo ' on&nbsp;line&nbsp;<b>', $call['line'], '</b>';
		}
		echo "<br />\n";
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
		echo '<b>Hostname:</b> ', php_uname('n'), "<br />\n";
		echo '<b>Environment:</b> ', ENVIRONMENT, "<br />\n";
		if (isset($_SERVER['SERVER_SOFTWARE'])) {
			echo '<b>Software:</b> ', $_SERVER['SERVER_SOFTWARE'], "<br />\n";
		}
		echo '</div>';
	}

	/**
	 * Een array printen met syntax highlighting.
	 */
	private function export_array($array) {
		foreach ($array as $key => $value) {
			if (is_array($value) && count($value) != 0) {
				echo '<b>'.$key.':</b> array('."<br />\n";
				if (is_indexed($value)) {
					foreach ($value as $value2) {
						echo '&nbsp;&nbsp;', syntax_highlight($value2), "<br />\n";
					}
				} else {
					foreach ($value as $key2 => $value2) {
						echo '&nbsp;&nbsp;'.syntax_highlight($key2).' => ', syntax_highlight($value2, null, 2048), "<br />\n";
					}
				}
				echo ")<br />\n";
				;
			} else {
				echo '<b>'.$key.':</b> ', syntax_highlight($value, null, 2048), "<br />\n";
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
		$filename = TMP_DIR.'error_handler_email_limit.txt';
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
				$minute_limit = $this->emails_per_minute; // nieuwe minuut voorraad.
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
			if (count(debug_backtrace()) == 1) { // An uncaught exception? via the set_exception_handler()
				self::handle(E_USER_ERROR, 'Uncaught '.get_class($exception).': '.$exception->getMessage());
			} else {
				self::handle(E_USER_WARNING, get_class($exception).': '.$exception->getMessage());
			}
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
