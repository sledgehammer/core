<?php
/**
 * Een (MySQL improved) database connectie met extra debug informatie
 *
 * @package Core
 */
namespace SledgeHammer;
class MySQLiDatabase extends \MySQLi {

	public
		$connected = false,
		$report_warnings = true,
		$throw_exception_on_error = false,
		$query_log = array(),
		$remember_queries = 1000, // Het maximaal aantal queries dat gelogd worden en getoond wordt bij $this->debug()
		$remember_backtrace = false, // [bool] Het php-bestand en regelnummer waar de query werd aangeroepen onthouden
		$execution_time = 0;

	private
		$number_of_queries = 0, // Aantal queries dat is uitgevoerd.
		$remember_queries_max_length = 51200, // Maximaal een 50KiB van een query onhouden in de log
		$tableInfoCache; // De cache voor tableInfo()

	/**
	 * Verbinding maken met de database server
	 *
	 * @return bool
	 */
	function connect($host = null, $user = null, $password = NULL, $database = NULL, $port = NULL, $socket = NULL) {
		$this->tableInfoCache = array();
		$start_time = microtime(true);
		$this->connected = false;
		$success = parent::connect($host, $user, $password, $database, $port, $socket); 
		$execution_time = (microtime(true) - $start_time);
		if ($this->remember_queries) {
			$sql = 'CONNECT(\''.$host.'\');';
			if ($database !== NULL) {
				$sql .= ' SELECT_DB(\''.$database.'\');';
			}
			$this->query_log[] = array('sql' => $sql, 'time' => $execution_time, 'truncated' => 0, 'backtrace' => $this->backtrace());
		}
		$this->execution_time += $execution_time;
		if ($success === false) { // Geeft i.p.v "NULL of false" een "true of false"
			return false;
		} else {
			$this->set_charset('utf8');
			if ($this->report_warnings) {
				$this->query('SET sql_warnings = ON');
			}
			$this->connected = true;
			return true;
		}
	}

	/**
	 * De verbinding met de database server verbreken
	 */
	function close() {
		$this->connected = false;
		return parent::close();
	}

	/**
	 * Een SQL query uitvoeren met foutdetectie en logging.
	 * foreach (Database->query($sql) as $key => $value) {}
	 *
	 * @param string $sql De SQL query
	 * @param NULL|string $key beinvloed het foreach gedrag. NULL: regelnummer
	 * @param NULL|string $value beinvloed het foreach gedrag. NULL: de gegevens in een assoc array
	 * @return SQLResultIterator|bool
	 */
	function query($sql, $key = NULL, $value = NULL, $result_mode = MYSQLI_STORE_RESULT) {
		$start_time = microtime(true);
		$Result = parent::query($sql, $result_mode);
		$execution_time = microtime(true) - $start_time;

		if ($this->remember_queries) {
			$this->remember_query($sql, $execution_time);
		}
		$this->execution_time += $execution_time;
		$this->number_of_queries++;

		if ($this->connected && $this->report_warnings && $this->warning_count != 0) { // MySQL warnings tonen?
			$this->report_warnings();
		}
		if (is_object($Result)) { // Gaat het om een mysqli_result object
			return new MySQLiResultIterator($Result, $key, $value);
		} elseif ($Result) { // Is de Result true (INSERT, UPDATE of SET statements)
			return $Result;
		} else { // Fout in de query
			if ($this->connected) {
				$error = 'MySQL error['.$this->errno.'] '.$this->error;
				if ($this->throw_exception_on_error) {
					throw new \Exception($error);
				} else {
					$this->notice($error, $sql);
				}
			} elseif ($this->throw_exception_on_error) {
				throw new \Exception('Not connected');
			}
			return false;
		}
	}

	/**
	 * Meerdere sql queries uitvoeren (met foutdetectie en logging van queries)
	 *
	 * @param string $sql De sql queries
	 * @return bool
	 */
	function multi_query($sql) {
		$start_time = microtime(true);
		$success = parent::multi_query($sql);
		$execution_time = microtime(true) - $start_time;
		if ($this->report_warnings && $this->warning_count != 0) { // MySQL warnings tonen?
			$this->report_warnings();
		}
		if (!$this->remember_queries) {
			$this->remember_query($sql, $execution_time);
		}
		$this->execution_time += $execution_time;
		$this->number_of_queries++;  // Bug: Er zijn waarschijnlijk meer dan 1 query uitgevoerd, maar hoeveel is onbekend.
		if ($success) {
			return true;
		} else {
			$error_message = 'MySQL error['.$this->errno.'] '.$this->error;
			$this->notice($error_message);
			if ($this->throw_exception_on_error) {
				throw new \Exception($error_message);
			}
			return false;
		}
	}

	/**
	 * select_db(), maar dan met foutdetectie en logging
	 */
	function select_db($database) {
		$this->tableInfoCache = array();
		$start_time = microtime(true);
		$success = parent::select_db($database);
		$execution_time = microtime(true) - $start_time;
		if ($this->remember_queries) {
			$this->remember_query('SELECT_DB(\''.$database.'\')', $execution_time);
		}
		if (!$success) {
			$error_message = 'MySQL error['.$this->errno.'] '.$this->error;
			notice($error_message);
			if ($this->throw_exception_on_error) {
				throw new \Exception($error_message);
			}
		}
		return $success;
	}

	/**
	 * Voert een real_escape_string() uit en zet er quotes om de waarde (als dat nodig is).
	 * In tegenstelling tot de PDO variant zal null een '"NULL"' teruggeven, i.p.v. '""'
	 *
	 * @link php.net/manual/en/pdo.quote.php
	 */
	function quote($value) {
		switch (gettype($value)) {
			case "NULL":
				return 'NULL';

			case 'integer':
				return $value;

			case 'double': // float
				return $this->real_escape_string($value);

			default:
				return '"'.$this->real_escape_string($value).'"';
		}
	}

	/**
	 * Zet backticks ` om de kolomnaam, als dat nodig is
	 * 
	 * @param sting $identifier  Een kolom, tabel of databasenaam
	 * @return string
	 */
	function quoteIdentifier($identifier) {
		if (preg_match('/^[0-9a-z_]+$/i', $identifier)) { // Zit er geen vreemde karakter in de $identifier
			return $identifier;
		}
		return ('`'.str_replace('`', '``', $identifier) . '`');
	}
	
	/**
	 * Escapes special characters in a string for use in a SQL statement, taking into account the current charset of the connection
	 */
	function real_escape_string($value) {
		if (is_float($value)) {
			$previousLocale = setlocale(LC_NUMERIC, 'C'); // Forceer de puntnotatie
			$value = (string) $value;
			setlocale(LC_NUMERIC, $previousLocale); // getalnotatie herstellen
		}
		return parent::real_escape_string($value);
	}

	/**
	 * Debug informatie tonen
	 *
	 * @param bool $popup Bij true zullen de queries pas getoond worden na het klikken een icoon, Bij false worden de queries direct getoond.
	 * @return void
	 */
	function debug($popup = true) {
		$query_log_count = count($this->query_log); // Het aantal onthouden queries.
		if ($query_log_count > 0) {
			$id = 'dbDebug_C'.$this->number_of_queries.'_M'.strtolower(substr(md5($this->query_log[0]['sql']), 0, 6)).'_R'.rand(10,99); // Bereken een uniek ID (Count + Md5 + Rand)
			if ($popup) {
				echo '<a href="#" onclick="document.getElementById(\''.$id.'\').style.display=\'block\';">';
			}
		}
		echo '<b>';
		if ($this->number_of_queries == 0 && $this->remember_queries && !$this->connected) {
			echo 'Not&nbsp;connected</b>';
			if ($query_log_count == 0) {
				return;
			}
		} else {
			echo $this->number_of_queries.'</b>&nbsp;';
		 	if ($this->number_of_queries == 1) {
				echo 'query';
			} else {
				echo 'queries';
			}
		}
		if ($popup && $query_log_count > 0) {
			echo '</a>';
		}
		echo '&nbsp;in&nbsp;<b>'.number_format($this->execution_time, 3, ',', '.').'</b>sec';
		if (!$popup) {
			echo '<br />';
		} 
		if ($query_log_count != 0) { // zijn er queries onthouden?
			if ($popup) {
				echo '<div id="'.$id.'" class="dbdebug" style="display:none;text-align:left;">';
				echo '<img src="http://bfanger.nl/core/images/debug_close.gif" alt="<close>" onclick="document.getElementById(\''.$id.'\').style.display=\'none\';" />';
			}
			for($i = 0; $i < $query_log_count; $i++) {
				$log = $this->query_log[$i];
				echo $i.': '.$this->highlight($log['sql'], $log['truncated'], $log['time'], $log['backtrace']).'<br />';
			}
			if ($this->remember_queries == 0 && ($this->number_of_queries - $query_log_count) > 0) {
				echo '<br /><b style="color:#ffa500">The other '.($this->number_of_queries - $query_log_count).' queries are suppressed.</b><br /><br />';
			}
			if ($popup) {
				echo  '</div>';
			}
		}
	}
	
	/**
	 * Haalt een enkele rij op uit de database
	 * 
	 * @param string $sql De SQL query
	 * @param bool $allow_empty_results Bij true word er geen foutmelding gegenereerd als er geen rij wordt gevonden
	 * @return array|false
	 */
	function fetch_row($sql, $allow_empty_results = false) {
		$Result = $this->query($sql);
		if (!$Result) {
			return false; // Foutieve query
		} elseif ($Result === true) { // UPDATE, INSERT query
			warning('Unexpected SQL resultset, make sure a SELECT statement is issued');
			return false;
		}
		if ($Result->count() > 1) {
			$this->notice('Unexpected '.$Result->count().' rows, expecting 1 row', $sql, array('Tip' => 'Use "LIMIT 1"'));
			return false;
		}
		$row = $Result->Result->fetch_assoc();
		if ($row === NULL) {
			if (!$allow_empty_results) {
				$this->notice('No record(s) found', $sql);
			}
			return false;
		}
		return $row;
	}
	
	/**
	 * Haal een elke waarde op uit de database
	 * 
	 * @param string $sql De SQL query
	 * @param bool $allow_empty_results Bij true word er geen foutmelding gegenereerd als er geen rij wordt gevonden
	 * @return string|NULL|false
	 */
	function fetch_value($sql, $allow_empty_results = false) {
		$row = $this->fetch_row($sql, $allow_empty_results);
		if (!$row) {
			return false;
		}
		if (count($row) > 1) {
			notice('Unexpected number of columns('.count($row).'), expecting 1 column');
			return false;
		}
		return current($row);
	}

	/**
	 * Een MySQL dump bestand importeren
	 *
	 * @param string $filepath De bestandsnaam inclisief path van het sql bestand
	 * @param string $error_message return de foutmelding
	 * @param bool $strip_php_comments Bij true worden de regels met "/*" beginnen en eindingen met  "* /" genegeerd
	 * @param false|callback $progress_callback Deze callback word na elke voltooide query aangeroepen met het regelnummer als parameter.
	 * @return bool
	 */
	function import($filepath, &$error_message, $strip_php_comments = false, $progress_callback = false) {
		if ($progress_callback && !is_callable($progress_callback)) {
			notice('Invalid $progress_callback', $progress_callback);
			$progress_callback = false;
		}
		$fp = fopen($filepath, 'r');
		$filepath = (strpos($filepath, PATH) === 0) ? substr($filepath, strlen(PATH)) : $filepath; // Het PATH van de $filepath afhalen zodat eventuele fouten een kort path tonen
		if (!$fp) { // Kan het bestand niet geopend worden?
			$error_message = 'File "'.$filepath.'" not found';
			return false;
		}
		$queries = array();
		$query = '';
		$concaternated_query = '';
		$explode_exceptions = array('&euro', '&amp', '&eacute', '&eacute'); // Het einde van een query wordt verkeerd ge-explode bij deze waardes
		$line_nr = 0;
		while (($line = fgets($fp)) !== false) {
			$line_nr++;
			$line = utf8_encode(rtrim($line));
			if ($line == '' || preg_match('/^[ ]*--/', $line)) { // Geen lege of MySQL-commentaar regel?
				continue;
			}
			if ($strip_php_comments && substr($line, 0, 2) == '/*' && substr(rtrim($line), -2) == '*/') { // Geen /* */ commentaar?
				continue;
			}
			$pieces = explode(';', $line);
			if (count($pieces) == 1) {
				$concaternated_query .= $line."\n";
				continue;
			}
			foreach ($pieces as $index => $piece) {
				$concaternated_query .= $piece;
				// De query controleren of deze foutief is afgekapt 
				foreach ($explode_exceptions as $tag) {
					if (substr($concaternated_query, 1 - strlen($tag)) == $tag) {
						$concaternated_query .= $piece.';';
						continue 2; // volgende piece
					}
				}
				if ($concaternated_query != '') {
					if (!$this->query($concaternated_query)) {
						$error_message = 'Invalid SQL statement in "'.$filepath.'" on line '.$line_nr;
						fclose($fp);
						return false;
					}
					if ($progress_callback) {
						call_user_func($progress_callback, $line_nr);
					}
					$concaternated_query = '';
				}
			}
		}
		fclose($fp);
		$concaternated_query = trim($concaternated_query);
		if ($concaternated_query != '') {
			if (!$this->query($concaternated_query)) {
				$error_message = 'Invalid SQL statement in "'.$filepath.'" on line '.$line_nr;
				return false;
			}
		}
		return true;
	}
	
	function tableInfo($table) {
		if (isset($this->tableInfoCache[$table])) { // Staan deze gegevens in de (php_memory)cache?
			return $this->tableInfoCache[$table];
		}
		$info = array(
			'primary_keys' => array(),
			//'table' => $table,
			'columns' => array(),
			'defaults' => array()
		);
		$result = $this->query('DESCRIBE '.$table, 'Field');
		foreach ($result as $column => $row) {
			if ($row['Key'] == 'PRI') {
				$info['primary_keys'][] = $column;
			}
			$info['columns'][] = $column;
			if ($this->server_version < 50100 && $row['Default'] === '') { // Vanaf MySQL 5.1 is de Default waarde NULL ipv "" als er geen default is opgegeven
				$info['defaults'][$column] = NULL; // Corrigeer de defaultwaarde "" naar NULL
			} else {
				$info['defaults'][$column] = $row['Default'];
			} 
		}
		$this->tableInfoCache[$table] = $info;
		return $info;
	}
	
	/**
	 * Shortcut for  tableInfo()s ['primary_keys']
	 * @return string|array 
	 */
	function getPrimaryKeys($table) {
		if (isset($this->tableInfoCache[$table])) { // Staan deze gegevens in de (php_memory)cache?
			return $this->tableInfoCache[$table]['primary_keys'];
		}
		$info = $this->tableInfo($table);
		return $info['primary_keys'];
	}

	/**
	 * De sql query in het debug_blok overzichtelijk weergeven
	 * De keywords van een sql query dik(<b>) maken en de querytijd een kleur geven (rood voor trage queries, orange voor middelmatige en grijs voor snelle queries)
	 */
	private function highlight($sql, $truncated, $time, $backtrace) {
		$sql = htmlspecialchars($sql);
		$startKeywords = array('SELECT', 'UPDATE', 'REPLACE INTO', 'INSERT INTO', 'DELETE', 'CREATE TABLE', 'CREATE DATABASE', 'DESCRIBE', 'TRUNCATE TABLE', 'TRUNCATE', 'SHOW TABLES', 'START TRANSACTION', 'ROLLBACK');
		$inlineKeywords = array('SELECT', 'VALUES', 'SET', 'FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'ASC', 'DESC', 'LIMIT', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'AS', 'ON');
		$sql = preg_replace('/^'.implode('\b|^', $startKeywords).'\b|\b'.implode('\b|\b', $inlineKeywords).'\b/', '<b>\\0</b>', $sql);
		$sql = str_replace("\n", '<br />', $sql);
		if ($time > 0.1) {
			$color = '#FF0000';
		} elseif ($time > 0.01) {
			$color = '#FFA500';
		} else {
			$color = '#999999';
		}
		if ($truncated) {
			$kib = round($truncated / 1024);
			$sql .= '<b style="color:#ffa500">...'.$kib.' KiB truncated</b>';
		}
		if ($backtrace) {
			$backtrace = ' in <b style="color:black">'.$backtrace['file'].'</b> on line <b style="color:black">'.$backtrace['line'].'</b>';
		} elseif ($backtrace === false) {
			$backtrace = ' backtrace failed';
		}
		return $sql.' <em style="color:'.$color.'">('.number_format($time, 3).' sec)</em>'.$backtrace;
	}

	/**
	 * De MySQL waarschuwingen opvragen en naar de ErrorHandler sturen
	 */
	private function report_warnings() {
		// $this->get_warnings(); // Undocumented feature
		$Warnings = $this->query('SHOW WARNINGS');
		foreach ($Warnings as $warning) {
			notice('MySQL warning['.$warning['Code'].'] '.$warning['Message']);
		}
	}

	/**
	 * De SQL statement onthouden in de query_log
	 * Bij hele groote queries wordt de alleen eerste ($this->remember_queries_max_length) karakters van de query gelogt
	 *
	 * @return void
	 */
	private function remember_query($sql, $execution_time) {
		$sql_length = strlen($sql);
		$truncated = 0;
		if ($sql_length > $this->remember_queries_max_length) {
			$sql = substr($sql, 0, $this->remember_queries_max_length);
			$truncated = ($sql_length - $this->remember_queries_max_length);
		}
		$this->query_log[] = array('sql' => $sql, 'time' => $execution_time, 'truncated' => $truncated, 'backtrace' => $this->backtrace());
		$this->remember_queries--;
	}

	/**
	 * Opzoeken vanuit welk bestand de query werdt aangeroepen
	 *
	 * @return NULL|false|array
	 */
	private function backtrace() {
		if (!$this->remember_backtrace) {
			return NULL;
		}
		foreach (debug_backtrace() as $trace) {
			if ($trace['file'] != __FILE__) {
				break;
			} 
		}
		if (isset($trace['file']) && isset($trace['line'])) {
			return array(
				'file' => str_replace(PATH, '', $trace['file']),
				'line' => $trace['line']
			);
		}
		return false;
	}
	
	/**
	 * 
	 * @param $message
	 * @param string|SQL $sql
	 * @return void
	 */
	private function notice($message, $sql, $info = array()) {
		if (gettype($sql) == 'object' && method_exists($sql, '__toString')) {
			$info['SQL'] = $sql->__toString();
		}
		notice($message, $info);
	}
}
?>
