<?php
/**
 * A PDO Database class with additional debugging functions.
 * By default will report clean sql-errors as notices, sets the encoding to UTF8 and sets the default fetch behaviour to FECTH_ASSOC
 *
 * @package Core
 */
namespace SledgeHammer;

class Database extends \PDO {

	/**
	 * @var bool  Report mysql warnings
	 */
	public $reportWarnings = 'auto';

	/**
	 * @var array  Structure containing all logged executed queries
	 */
	public $log = array();

	/**
	 * @var int  The maximum amount of queries that will be logged in full.
	 */
	public $logLimit = 1000;

	/**
	 * @var bool  Add filename and linenumber traces to the log.
	 */
	public $logBacktrace = false;

	/**
	 * @var float  Total time it took to execute all queries (in seconds);
	 */
	public $executionTime = 0;

	/**
	 * @var int  Number of executed queries.
	 */
	private $queryCount;

	/**
	 * @var int  Only log the first 50KiB of a long query.
	 */
	private $logStatementCharacterLimit = 51200;

	/**
	 *
	 * @param string $dsn  The pdo-dsn "mysql:host=localhost" or url: "mysql://root@localhost/my_database?charset=utf-8"
	 * @param type $username
	 * @param type $passwd
	 * @param type $options
	 */
	function __construct($dsn, $username = null, $passwd = null, $options = array()) {
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
				$config['dbname'] = substr($url->path, 1); // strip "/"
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

					case 'utf-8': $charset = 'UTF8';
						break;
					case 'iso-8859-1': $charset = 'latin1';
						break;
					case 'iso-8859-15': $charset = 'latin1';
						break;
					default: $charset = $GLOBALS['charset'];
						break;
				}
			}
			if (empty($config['charset'])) {
				
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
	function exec($statement) {
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
	function query($statement) {
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
	 * Prepares a statement for execution and returns a PDOStatement object
	 * @link http://php.net/manual/en/pdo.prepare.php
	 *
	 * @param string $statement  The SQL statement to prepare
	 * @param array $driver_options
	 * @return \PDOStatement 
	 */
	function prepare($statement, $driver_options = array()) {
		$start = microtime(true);
		$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('SledgeHammer\PreparedStatement', array($this)));
		$result = parent::prepare($statement, $driver_options);
		$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('PDOStatement')); // Restore default class
		$this->logStatement('[Prepared] '.$statement, (microtime(true) - $start));
		$this->queryCount--;
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->reportWarnings($statement);
		}
		array('PDOStatement');
		return $result;
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
		return ('`'.str_replace('`', '``', $identifier).'`');
	}

	/**
	 * Quotes a string for use in a query.
	 * @link http://php.net/manual/en/pdo.quote.php
	 *
	 * @param string $value  The string to be quoted.
	 * @param int $parameterType [optional] Provides a data type hint for drivers that have alternate quoting styles.
	 * @return string  A quoted string that is safe to pass into an SQL statement.
	 */
	public function quote($value, $parameterType = null) {
		if ($parameterType === null && $value === null) {
			return 'NULL';
		}
		if ($parameterType === \PDO::PARAM_INT && (is_int($value) || preg_match('/^[1-9]{1}[0-9]*$/', $value))) {
			// No quotes around INT values.
			return $value;
		}
		if (is_float($value)) {
			$previousLocale = setlocale(LC_NUMERIC, 'C'); // Force notation with a dot. "0.5"
			$value = (string) $value;
			setlocale(LC_NUMERIC, $previousLocale); // restore locale
		}
		return parent::quote($value, $parameterType);
	}

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
			$id = 'querylog_C'.$this->queryCount.'_M'.strtolower(substr(md5($this->log[0]['sql']), 0, 6)).'_R'.rand(10, 99); // Bereken een uniek ID (Count + Md5 + Rand)
			if ($popup) {
				echo '<a href="#" onclick="document.getElementById(\''.$id.'\').style.display=\'block\';document.body.addEventListener(\'keyup\', function (e) { if(e.which == 27) {document.getElementById(\''.$id.'\').style.display=\'none\';}}, true)">';
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
				echo '<pre id="'.$id.'" class="sledegehammer_querylog" style="display:none;">';
				echo '<a href="javascript:document.getElementById(\''.$id.'\').style.display=\'none\';" title="close" class="sledegehammer_querylog_close" style="float:right;">&#10062;</a>';
			}
			for ($i = 0; $i < $query_log_count; $i++) {
				$log = $this->log[$i];
				echo '<div><span class="sledegehammer_querylog_number">'.$i.'</span> '.$this->highlight($log['sql'], $log['truncated'], $log['time'], $log['backtrace']).'</div>';
			}
			if ($this->queryCount == 0 && ($this->queryCount - $query_log_count) > 0) {
				echo '<br /><b style="color:#ffa500">The other '.($this->number_of_queries - $query_log_count).' queries are suppressed.</b><br /><br />';
			}
			if ($popup) {
				echo '</pre>';
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
		$results = $result->fetchAll();
		$count = count($results);
		if ($count == 1) {
			return $results[0];
		}
		if (count($results) > 1) {
			notice('Unexpected '.count($results).' rows, expecting 1 row');
		} elseif (!$allow_empty_results) {
			notice('No record(s) found');
		}
		return false;
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

	/**
	 * De sql query in het debug_blok overzichtelijk weergeven
	 * De keywords van een sql query dik(<b>) maken en de querytijd een kleur geven (rood voor trage queries, orange voor middelmatige en grijs voor snelle queries)
	 */
	private function highlight($sql, $truncated, $time, $backtrace) {
		$sql = htmlspecialchars($sql, ENT_COMPAT, $GLOBALS['charset']);
		$startKeywords = array('SELECT', 'UPDATE', 'REPLACE INTO', 'INSERT INTO', 'DELETE', 'CREATE TABLE', 'CREATE DATABASE', 'DESCRIBE', 'TRUNCATE TABLE', 'TRUNCATE', 'SHOW', 'SET', 'START TRANSACTION', 'ROLLBACK');
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
		return $sql.' <em class="execution_time" style="color:'.$color.'">('.number_format($time, 3).' sec)</em>'.$backtrace;
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
	 * Add the SQL statement to the query_log
	 * Bij hele groote queries wordt de alleen eerste ($this->remember_queries_max_length) karakters van de query gelogt
	 *
	 * @param string $statement
	 * @param float $executedIn  The time it took to execute the query
	 * @return void
	 */
	function logStatement($statement, $executedIn) {
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
