<?php
/**
 * Een PDO Database connectie met extra debug informatie.
 * Adds error messages or exceptions and changes default fetch behaviour to FECTH_ASSOC
 *
 * @package Core
 */
namespace SledgeHammer;
class Database extends \PDO {

	public
		$reportWarnings = 'auto', // (bool) Report mysql warnings
		$log = array(), // Structure containing all logged executed queries
		$logLimit = 1000, // Het maximaal aantal queries dat gelogd worden en getoond wordt bij $this->debug()
		$logBacktrace = false, // [bool] Het php-bestand en regelnummer waar de query werd aangeroepen onthouden
		$executionTime = 0;

	private
		$queryCount, // Number of executed queries.
		$logStatementCharacterLimit = 51200; // Maximaal een 50KiB van een query onhouden in de log

	/**
	 *
	 * @param string $dsn  The pdo-dsn "mysql:host=localhost" or url: "mysql://root@localhost/my_database?charset=utf-8"
	 * @param type $username
	 * @param type $passwd
	 * @param type $options
	 */
	public function __construct($dsn, $username = null, $passwd = null, $options = array()) {
		$start = microtime(true);
		$isUrlStyle = preg_match('/^[a-z]+:\/\//i', $dsn, $match);
		if ($isUrlStyle) { // url syntax?
			$url = new URL($dsn);
			$logMessage = 'CONNECT(\''.$url->host.'\');';
			$config = $url->query;
			$config['host'] = $url->host;
			if ($url->port !== null) {
				$config['port'] = $url->port;
			}
			if ($url->path !== null && $url->path != '/') {
				$config['dbname'] = substr($url->path,  1); // strip "/"
				$logMessage .= ' SELECT_DB(\''.$config['dbname'].'\');';
			}
			$driver = strtolower($url->scheme);
			$username = $url->user;
			$passwd = $url->pass;
		} else {
			preg_match('/^([a-z]+):/i', $dsn, $match);
			$driver = strtolower($match[1]);
			$logMessage = 'CONNECT(\''.$dsn.'\');';
		}

		if ($driver == 'mysql') {
			$this->reportWarnings = true;
			if (isset($config['charset'])) {
				$charset = $config['charset'];
			} elseif (strpos($dsn, ';charset=')) {
				notice('TODO: Extract $charset from dsn'); // @todo implement
			} else {
				// Use SledgeHammer setting (default utf-8)
				switch (strtolower($GLOBALS['charset'])) {

					case 'utf-8': $charset = 'UTF8'; break;
					case 'iso-8859-1': $charset = 'latin1'; break;
					case 'iso-8859-15': $charset = 'latin1'; break;
					default: $charset = $GLOBALS['charset']; break;
				}
			}
			if (empty ($config['charset'])) {
			}
			if (version_compare(PHP_VERSION, '5.3.6') == -1) {
				$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES "'.mysql_escape_string($charset).'"';
			} elseif ($isUrlStyle) {
				$config['charset'] = $charset;
			} else {
				$dsn .= ";charset=".$charset;
			}
		} else {
			$this->reportWarnings = false;
		}
		if ($isUrlStyle) {
			$dsn = $driver.':';
			foreach ($config as $name => $value) {
				$dsn .= $name.'='.$value.';';
			}
		}
		//	@todo Add support for config options like: 'report_warnings', 'throw_exception_on_error', 'remember_queries', 'remember_backtrace'

		parent::__construct($dsn, $username, $passwd, $options);
		$this->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC); // Default to FETCH_ASSOC mode
		if ($this->reportWarnings) {
			parent::exec('SET sql_warnings = ON');
		}
		$this->logStatement($logMessage, (microtime(true) - $start));
		$this->queryCount = 0;
	}

	/**
	 * Execute an SQL statement and return the number of affected rows
	 * @link http://php.net/manual/en/pdo.exec.php
	 *
	 * @param string $statement  The SQL statement to prepare and execute.
	 * @return int|bool
	 */
	public function exec($statement) {
		$start = microtime(true);
		$result = parent::exec($statement);
		$this->logStatement($statement, (microtime(true) - $start));
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->reportWarnings($statement);
		}
		return $result;
	}

	/**
	 * Executes an SQL statement, returning a result set as a PDOStatement object
	 * @link http://php.net/manual/en/pdo.query.php
	 *
	 * @param string $statement  The SQL statement to prepare and execute.
	 * @return PDOStatement
	 */
	public function query($statement) {
		$start = microtime(true);
		$result = parent::query($statement);
		$this->logStatement($statement, (microtime(true) - $start));
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->reportWarnings($statement);
		}
		return $result;
	}

	/**
	 * Meerdere sql queries uitvoeren (met foutdetectie en logging van queries)
	 *
	 * @param string $sql De sql queries
	 * @return bool
	 * /
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
	}*/

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
	 * Quotes a string for use in a query.
	 * @link http://php.net/manual/en/pdo.quote.php
	 *
	 * @param string $string  The string to be quoted.
	 * @param int $parameter_type [optional] Provides a data type hint for drivers that have alternate quoting styles.
	 * @return string  A quoted string that is safe to pass into an SQL statement.
	 */
	public function quote($string, $parameterType = null) {
		if ($parameterType === null) {
			if ($string === null) {
				return 'NULL';
			}
			if (is_int($string) || preg_match('/^[123456789]{1}[0-9]*$/', $string)) { // A number?
				return $string;
			}
		}

		return parent::quote($string, $parameterType);
	}
	/**
	 * Escapes special characters in a string for use in a SQL statement, taking into account the current charset of the connection
	 * /
	function real_escape_string($value) {
		if (is_float($value)) {
			$previousLocale = setlocale(LC_NUMERIC, 'C'); // Forceer de puntnotatie
			$value = (string) $value;
			setlocale(LC_NUMERIC, $previousLocale); // getalnotatie herstellen
		}
		return parent::real_escape_string($value);
	}
	 */

	function __get($property) {
		warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.');
	}
	function __set($property, $value) {
		warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.');
		$this->$property = $value;
	}
	/**
	 * Debug informatie tonen
	 *
	 * @param bool $popup Bij true zullen de queries pas getoond worden na het klikken een icoon, Bij false worden de queries direct getoond.
	 * @return void
	 */
	function debug($popup = true) {
		$query_log_count = count($this->log); // Het aantal onthouden queries.
		if ($query_log_count > 0) {
			$id = 'dbDebug_C'.$this->queryCount.'_M'.strtolower(substr(md5($this->log[0]['sql']), 0, 6)).'_R'.rand(10,99); // Bereken een uniek ID (Count + Md5 + Rand)
			if ($popup) {
				echo '<a href="#" onclick="document.getElementById(\''.$id.'\').style.display=\'block\';">';
			}
		}
		echo '<b>', $this->queryCount, '</b>&nbsp;queries';
		if ($popup && $query_log_count > 0) {
			echo '</a>';
		}
		if ($this->queryCount != 0) {
			echo '&nbsp;in&nbsp;<b>'.number_format($this->executionTime, 3, ',', '.').'</b>sec';
		}
		if (!$popup) {
			echo '<br />';
		}
		if ($query_log_count != 0) { // zijn er queries onthouden?
			if ($popup) {
				echo '<div id="'.$id.'" class="dbdebug" style="display:none;text-align:left;">';
				echo '<img src="http://bfanger.nl/core/images/debug_close.gif" alt="<close>" onclick="document.getElementById(\''.$id.'\').style.display=\'none\';" />';
			}
			for($i = 0; $i < $query_log_count; $i++) {
				$log = $this->log[$i];
				echo $i.': '.$this->highlight($log['sql'], $log['truncated'], $log['time'], $log['backtrace']).'<br />';
			}
			if ($this->queryCount == 0 && ($this->queryCount - $query_log_count) > 0) {
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
	 * @param string $statement De SQL query
	 * @param bool $allow_empty_results Bij true word er geen foutmelding gegenereerd als er geen rij wordt gevonden
	 * @return array|false
	 */
	function fetchRow($statement, $allow_empty_results = false) {
		$result = $this->query($statement);
		if ($result == false) {  // Foutieve query
			return false;
		} elseif ($result->columnCount() == 0) { // UPDATE, INSERT query
			warning('Resultset has no columns, expecting 1 or more columns');
			return false;
		}
		if ($result->rowCount() > 1) {
			notice('Unexpected '.$result->rowCount().' rows, expecting 1 row');
			return false;
		}
		$row = $result->fetch();
		if ($row === false) {
			if (!$allow_empty_results) {
				notice('No record(s) found');
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
	function fetchValue($sql, $allow_empty_results = false) {
		$row = $this->fetchRow($sql, $allow_empty_results);
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
			if ($strip_php_comments && substr($line, 0, 2) == '/*' && substr(rtrim($line), -2) == '*'.'/') { // Geen /* * / commentaar?
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
	/*

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
	 * /
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
		$sql = htmlspecialchars($sql, ENT_COMPAT, $GLOBALS['charset']);
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
	 * Report SQL error
	 * A cleaner error than PDO::ERRMODE_WARNING generates would generate.
	 *
	 * @param string $statement
	 */
	private function reportError($statement) {
		$error = $this->errorInfo();
		if ($this->getAttribute(\PDO::ATTR_ERRMODE) == \PDO::ERRMODE_SILENT) { // The error issn't already reported?
			$info = array();
			if ($statement instanceof SQL) {
				$info['SQL'] = (string) $statement;
			}
			notice('SQL error ['.$error[1].'] '.$error[2], $info);
		}
	}
	/**
	 * Report MySQL warnings and notes if any
	 *
	 * @param string $result
	 */
	private function reportWarnings($statement) {
		if ($this->reportWarnings) {
			$info = array();
			if ($statement instanceof SQL) {
				$info['SQL'] = (string) $statement;
			}
			$start = microtime(true);
			$warnings = parent::query('SHOW WARNINGS');
			$this->executionTime += (microtime(true) - $start);
			if ($warnings->rowCount()) {
				foreach ($warnings->fetchAll(\PDO::FETCH_ASSOC) as $warning) {
					notice('SQL '.strtolower($warning['Level']).' ['.$warning['Code'].'] '.$warning['Message'], $info);
				}
				// @todo Clear warnings
				// PDO/MySQL doesn't clear the warnings before CREATE/DROP DATABASE queries.
			}
		}
	}

	/**
	 * De SQL statement onthouden in de query_log
	 * Bij hele groote queries wordt de alleen eerste ($this->remember_queries_max_length) karakters van de query gelogt
	 *
	 * @param string $statement
	 * @param float $executedIn  The time it took to execute the query
	 * @return void
	 */
	private function logStatement($statement, $executedIn) {
		$this->queryCount++;
		$this->executionTime += $executedIn;
		if ($this->logLimit == 0) {
			return;
		}
		$sql = $statement;
		$sql_length = strlen($sql);
		$truncated = 0;
		if ($sql_length > $this->logStatementCharacterLimit) {
			$sql = substr($sql, 0, $this->logStatementCharacterLimit);
			$truncated = ($sql_length - $this->logStatementCharacterLimit);
		}
		$this->log[] = array('sql' => $sql, 'time' => $executedIn, 'truncated' => $truncated, 'backtrace' => $this->backtrace());
		$this->logLimit--;
	}

	/**
	 * Opzoeken vanuit welk bestand de query werdt aangeroepen
	 *
	 * @return NULL|false|array
	 */
	private function backtrace() {
		if ($this->logBacktrace == false) {
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
}
?>
