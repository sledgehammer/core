<?php
/**
 * Database
 */
namespace Sledgehammer;
/**
 * A PDO Database class with additional debugging functions.
 * By default will report clean sql-errors as notices, sets the encoding to UTF8 and sets the default fetch behaviour to FECTH_ASSOC
 *
 * @package Core
 */
class Database extends \PDO {

	/**
	 * Report MySQL warnings.
	 * @var bool
	 */
	public $reportWarnings = true;

	/**
	 * Logs en profiles the executed queries.
	 * @var Logger
	 */
	public $logger;

	/**
	 * Remember the previous insertId when using warnings. (Because "SHOW WARNINGS" query resets the value of lastInsertId() to "0")
	 * @var int
	 */
	private $previousInsertId;

	/**
	 * The \PDO::ATTR_DRIVER_NAME. "mysql" or "sqlite"
	 * @var string
	 */
	private $driver;

	/**
	 * The database instances that are accessible by getDatabase() and the statusbar() functions.
	 * array('link' => Database)
	 * @var array|Database
	 */
	static $instances = array();

	/**
	 * Cached quoted identifiers(column and table names) per database driver.
	 * @var array
	 */
	private static $quotedIdentifiers = array();

	/**
	 * Connect to a database.
	 *
	 * @param string $dsn  The pdo-dsn "mysql:host=localhost" or url: "mysql://root@localhost/my_database?charset=utf-8"
	 * @param string $username
	 * @param string $passwd
	 * @param array $options
	 */
	function __construct($dsn, $username = null, $passwd = null, $options = array()) {
		$start = microtime(true);
		$isUrlStyle = preg_match('/^[a-z]+:\/\//i', $dsn, $match);
		if ($isUrlStyle) { // url syntax?
			$url = new URL($dsn);
			$config = $url->query;
			$config['host'] = $url->host;
			if ($url->port !== null) {
				$config['port'] = $url->port;
			}
			if ($url->path !== null && $url->path != '/') {
				$config['dbname'] = substr($url->path, 1); // strip "/"
			}
			$driver = strtolower($url->scheme);
			$username = $url->user;
			$passwd = $url->pass;
		} else {
			preg_match('/^([a-z]+):/i', $dsn, $match);
			$driver = strtolower($match[1]);
		}

		if ($driver == 'mysql') {
			$this->reportWarnings = true;
			if (isset($config['charset'])) {
				$charset = $config['charset'];
			} elseif (strpos($dsn, ';charset=')) {
				$parts = explode(';', $dsn);
				foreach ($parts as $i => $part) {
					if (strpos($part, 'charset=') === 0) {
						$charset = substr($part, 8);
						unset($parts[$i]);
					}
				}
				$dsn = implode(';', $parts);
			} else {
				// Use Sledgehammer setting (default utf-8)
				switch (strtolower(Framework::$charset)) {

					case 'utf-8':
						$charset = 'UTF8';
						break;

					case 'iso-8859-1':
					case 'iso-8859-15':
						$charset = 'latin1';
						break;

					default:
						$charset = Framework::$charset;
						break;
				}
			}
			if (version_compare(PHP_VERSION, '5.3.6') == -1) {
				if (empty($options[\PDO::MYSQL_ATTR_INIT_COMMAND])) {
					$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES "'.mysql_escape_string($charset).'"';
				}
			} elseif ($isUrlStyle) {
				$config['charset'] = $charset;
			} else {
				$dsn .= ";charset=".$charset;
			}
		} else {
			unset($this->reportWarnings); // Unset the reportWarning property for database drivers that don't support it
		}
		if ($isUrlStyle) {
			$dsn = $driver.':';
			foreach ($config as $name => $value) {
				$dsn .= $name.'='.$value.';';
			}
		}
		// Parse $options
		$loggerOptions = array(
			'identifier' => 'Database['.$dsn.']',
			'limit' => 1000,
			'renderer' => array($this, 'renderLog'),
			'start' => 0,
			'plural' => 'queries',
			'singular' => 'query',
			'columns' => array('SQL', 'Duration'),
		);
		foreach ($options as $property => $value) {
			if (substr($property, 0, 3) === 'log') {
				$loggerOptions[lcfirst(substr($property, 3))]= $value;
				unset($options[$property]);
			} elseif ($property === 'reportWarnings') {
				$this->$property = $value;
			}
		}
		$this->logger = new Logger($loggerOptions);
		if (empty($options[\PDO::ATTR_DEFAULT_FETCH_MODE])) {
			$options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;
		}
		if (empty($options[\PDO::ATTR_STATEMENT_CLASS])) {
			$options[\PDO::ATTR_STATEMENT_CLASS] = array('Sledgehammer\PDOStatement');
		}
		parent::__construct($dsn, $username, $passwd, $options);
		$this->logger->append('Database connection to '.$dsn, array('duration' => (microtime(true) - $start)));
		$this->logger->count--;

		$this->driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);

		if (isset($this->reportWarnings) && $this->reportWarnings === true) {
			parent::exec('SET sql_warnings = ON');
		}
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
		$this->logger->append((string) $statement, array('duration' => (microtime(true) - $start)));
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->checkWarnings($statement);
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
		$statement = (string) $statement;
		$result = parent::query($statement);
		$this->logger->append($statement, array('duration' => (microtime(true) - $start)));
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->checkWarnings($statement);
		}
		return $result;
	}

	/**
	 * Prepares a statement for execution and returns a PDOStatement object
	 * @link http://php.net/manual/en/pdo.prepare.php
	 *
	 * @param string $statement  The SQL statement to prepare
	 * @param array $driver_options
	 * @return PreparedStatement
	 */
	function prepare($statement, $driver_options = array()) {
		$start = microtime(true);
		$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('Sledgehammer\PreparedStatement', array($this)));
		$result = parent::prepare($statement, $driver_options);
		$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, array('Sledgehammer\PDOStatement')); // Restore default class
		$this->logger->totalDuration += (microtime(true) - $start);
		if ($result === false) {
			$this->reportError($statement);
		} else {
			$this->checkWarnings($statement);
		}
		return $result;
	}

	/**
	 * Puts backticks '`' around a column- table or databasename.
	 * Only adds quotes around columnname if needed.
	 * (Prevents SQL injection)
	 *
	 * @param string $identifier  A column, table or database-name
	 * @return string
	 */
	function quoteIdentifier($identifier) {
		if (isset(self::$quotedIdentifiers[$this->driver][$identifier])) {
			return self::$quotedIdentifiers[$this->driver][$identifier];
		}
		$addQuotes = false;
		if (preg_match('/^[^0-9][0-9a-z_]+$/i', $identifier) == false) { // Does the column contain a strange character?
			$addQuotes = true;
		} else {
			// generic keywords (included in both sqlite and mysql)
			$keywords = array('ADD', 'ALL', 'ALTER', 'ANALYZE', 'AND', 'AS', 'ASC', 'BEFORE', 'BETWEEN', 'BY', 'CASCADE', 'CASE', 'CHECK', 'COLLATE', 'COLUMN', 'CONSTRAINT', 'CREATE', 'CROSS', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'DATABASE', 'DEFAULT', 'DELETE', 'DESC', 'DISTINCT', 'DROP', 'EACH', 'ELSE', 'EXISTS', 'EXPLAIN', 'FOR', 'FOREIGN', 'FROM', 'GROUP', 'HAVING', 'IF', 'IGNORE', 'IN', 'INDEX', 'INNER', 'INSERT', 'INTO', 'IS', 'JOIN', 'KEY', 'LEFT', 'LIKE', 'LIMIT', 'MATCH', 'NATURAL', 'NOT', 'NULL', 'ON', 'OR', 'ORDER', 'OUTER', 'PRIMARY', 'REFERENCES', 'REGEXP', 'RELEASE', 'RENAME', 'REPLACE', 'RESTRICT', 'RIGHT', 'SELECT', 'SET', 'TABLE', 'THEN', 'TO', 'TRIGGER', 'UNION', 'UNIQUE', 'UPDATE', 'USING', 'VALUES', 'WHEN', 'WHERE');
			if ($this->driver === 'mysql') {
				$keywords = array_merge($keywords, array('ACCESSIBLE', 'ASENSITIVE', 'BIGINT', 'BINARY', 'BLOB', 'BOTH', 'CALL', 'CHANGE', 'CHAR', 'CHARACTER', 'CONDITION', 'CONTINUE', 'CONVERT', 'CURRENT_USER', 'CURSOR', 'DATABASES', 'DAY_HOUR', 'DAY_MICROSECOND', 'DAY_MINUTE', 'DAY_SECOND', 'DEC', 'DECIMAL', 'DECLARE', 'DELAYED', 'DESCRIBE', 'DETERMINISTIC', 'DISTINCTROW', 'DIV', 'DOUBLE', 'DUAL', 'ELSEIF', 'ENCLOSED', 'ESCAPED', 'EXIT', 'FALSE', 'FETCH', 'FLOAT', 'FLOAT4', 'FLOAT8', 'FORCE', 'FULLTEXT', 'GENERAL', 'GRANT', 'HIGH_PRIORITY', 'HOUR_MICROSECOND', 'HOUR_MINUTE', 'HOUR_SECOND', 'IGNORE_SERVER_IDS', 'INFILE', 'INOUT', 'INSENSITIVE', 'INT', 'INT1', 'INT2', 'INT3', 'INT4', 'INT8', 'INTEGER', 'INTERVAL', 'ITERATE', 'KEYS', 'KILL', 'LEADING', 'LEAVE', 'LINEAR', 'LINES', 'LOAD', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCK', 'LONG', 'LONGBLOB', 'LONGTEXT', 'LOOP', 'LOW_PRIORITY', 'MASTER_HEARTBEAT_PERIOD', 'MASTER_SSL_VERIFY_SERVER_CERT', 'MAXVALUE', 'MAXVALUE', 'MEDIUMBLOB', 'MEDIUMINT', 'MEDIUMTEXT', 'MIDDLEINT', 'MINUTE_MICROSECOND', 'MINUTE_SECOND', 'MOD', 'MODIFIES', 'NO_WRITE_TO_BINLOG', 'NUMERIC', 'OPTIMIZE', 'OPTION', 'OPTIONALLY', 'OUT', 'OUTFILE', 'PRECISION', 'PROCEDURE', 'PURGE', 'RANGE', 'READ', 'READS', 'READ_WRITE', 'REAL', 'REPEAT', 'REQUIRE', 'RESIGNAL', 'RESIGNAL', 'RETURN', 'REVOKE', 'RLIKE', 'SCHEMA', 'SCHEMAS', 'SECOND_MICROSECOND', 'SENSITIVE', 'SEPARATOR', 'SHOW', 'SIGNAL', 'SIGNAL', 'SLOW', 'SMALLINT', 'SPATIAL', 'SPECIFIC', 'SQL', 'SQLEXCEPTION', 'SQLSTATE', 'SQLWARNING', 'SQL_BIG_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_SMALL_RESULT', 'SSL', 'STARTING', 'STRAIGHT_JOIN', 'TERMINATED', 'TINYBLOB', 'TINYINT', 'TINYTEXT', 'TRAILING', 'TRUE', 'UNDO', 'UNLOCK', 'UNSIGNED', 'USAGE', 'USE', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'VARBINARY', 'VARCHAR', 'VARCHARACTER', 'VARYING', 'WHILE', 'WITH', 'WRITE', 'XOR', 'YEAR_MONTH', 'ZEROFILL'));
			} elseif ($this->driver === 'sqlite') {
				$keywords = array_merge($keywords, array('ABORT', 'ACTION', 'AFTER', 'ATTACH', 'AUTOINCREMENT', 'BEGIN', 'CAST', 'COMMIT', 'CONFLICT', 'DEFERRABLE', 'DEFERRED', 'DETACH', 'END', 'ESCAPE', 'EXCEPT', 'EXCLUSIVE', 'FAIL', 'FULL', 'GLOB', 'IMMEDIATE', 'INDEXED', 'INITIALLY', 'INSTEAD', 'INTERSECT', 'ISNULL', 'NO', 'NOTNULL', 'OF', 'OFFSET', 'PLAN', 'PRAGMA', 'QUERY', 'RAISE', 'REINDEX', 'ROLLBACK', 'ROW', 'SAVEPOINT', 'TEMP', 'TEMPORARY', 'TRANSACTION', 'VACUUM', 'VIEW', 'VIRTUAL'));
			}
			if (in_array(strtoupper($identifier), $keywords)) {
				$addQuotes = true;
			}
		}
		if ($addQuotes) {
			$identifier = '`'.str_replace('`', '``', $identifier).'`';
		}
		self::$quotedIdentifiers[$this->driver][$identifier] = $identifier;
		return $identifier;
	}

	/**
	 * Quotes a string for use in a query.
	 * @link http://php.net/manual/en/pdo.quote.php
	 *
	 * @param string $value  The string to be quoted.
	 * @param int $parameterType [optional] Provides a data type hint for drivers that have alternate quoting styles.
	 * @return string  A quoted string that is safe to pass into an SQL statement.
	 */
	function quote($value, $parameterType = null) {
		if ($parameterType === null && $value === null) {
			return 'NULL';
		}
		// No quotes around INT values.
		if (is_int($value) && ($parameterType === null || $parameterType === \PDO::PARAM_INT)) {
			return $value;
		}
		if ($parameterType === \PDO::PARAM_INT && preg_match('/^[1-9]{1}[0-9]*$/', $value)) {
			return $value;
		}
		// Force notation with a dot. "0.5"
		if (is_float($value)) {
			$previousLocale = setlocale(LC_NUMERIC, 'C');
			$value = (string) $value;
			setlocale(LC_NUMERIC, $previousLocale); // restore locale
		}
		return parent::quote($value, $parameterType);
	}

	/**
	 * Generate warnings for accessing non-existing properties.
	 *
	 * @param string $property
	 */
	function __get($property) {
		warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.');
	}

	/**
	 * Generate warnings for setting non-existing properties.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __set($property, $value) {
		warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.');
		$this->$property = $value;
	}

	/**
	 * Debug informatie tonen
	 *
	 * @param bool $popup true: Add javascript to open the querylog in a popup.
	 * @param string|null $connection  Name of the connection a.k.a. $dblink.
	 * @return void
	 */
	function debug($popup = true, $connection = null) {
		$query_log_count = count($this->log); // Het aantal onthouden queries.
		if ($query_log_count > 0) {
			$id = 'querylog_C'.$this->queryCount.'_M'.strtolower(substr(md5($this->log[0]['sql']), 0, 6)).'_R'.rand(10, 99); // Bereken een uniek ID (Count + Md5 + Rand)
			if ($popup) {
				echo '<a href="#" onclick="document.getElementById(\''.$id.'\').style.display=\'block\';document.body.addEventListener(\'keyup\', function (e) { if(e.which == 27) {document.getElementById(\''.$id.'\').style.display=\'none\';}}, true); document.getElementById(\''.$id.'\').focus(); return false">';
			}
		}
		if ($connection !== null) {
			echo ' <b>', $connection, '</b>&nbsp;connection ';
		}
		echo '<b>', $this->queryCount, '</b>&nbsp;';
		echo ($this->queryCount === 1) ? 'query' : 'queries';
		if ($this->queryCount != 0) {
			echo '&nbsp;in&nbsp;<b>'.number_format($this->executionTime, 3, ',', '.').'</b>&nbsp;sec';
		}

		if ($popup && $query_log_count > 0) {
			echo '</a>';
		}
		if (!$popup) {
			echo '<br />';
		}
		if ($query_log_count != 0) { // zijn er queries onthouden?
			if ($popup) {
				echo '<div id="'.$id.'" class="sledegehammer_querylog" tabindex="-1" style="display:none;">';
				echo '<a href="javascript:document.getElementById(\''.$id.'\').style.display=\'none\';" title="close" class="sledegehammer_querylog_close" style="float:right;">&times;</a>';
			}
			for ($i = 0; $i < $query_log_count; $i++) {
				$log = $this->log[$i];
				echo '<div class="sledegehammer_querylog_sql"><span class="sledegehammer_querylog_number">'.$i.'</span> '.$this->highlight($log['sql'], $log['truncated'], $log['time'], $log['backtrace']).'</div>';
			}
			if ($this->queryCount == 0 && ($this->queryCount - $query_log_count) > 0) {
				echo '<br /><b style="color:#ffa500">The other '.($this->number_of_queries - $query_log_count).' queries are suppressed.</b><br /><br />';
			}
			if ($popup) {
				echo '</div>';
			}
		}
	}

	/**
	 * Execute the query and return the resultset as array
	 *
	 * @param string $statement  The SQL statement
	 * @return array|false
	 */
	function fetchAll($statement) {
		$result = $this->query($statement);
		if ($result instanceof \PDOStatement) {
			return $result->fetchAll();
		}
		return $result;
	}

	/**
	 * Fetch a single row
	 *
	 * @param string $statement  The SQL query
	 * @param bool $allow_empty_results  true: Suppress the notice when no record is found.
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
			notice('Row not found');
		}
		return false;
	}

	/**
	 * Fetch a single value
	 * The resultset may contain only 1 record with only 1 column.
	 *
	 *
	 * @param string $sql  The SQL query
	 * @param bool $allow_empty_results  true: Suppress the notice when no record is found.
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
	 * Returns the ID of the last inserted row or sequence value.
	 * @link http://php.net/manual/en/pdo.lastinsertid.php
	 *
	 * @param string $name [optional]
	 * @return string
	 */
	function lastInsertId($name = null) {
		if ($name === null && isset($this->reportWarnings) && $this->reportWarnings === true) {
			return $this->previousInsertId;
		}
		return parent::lastInsertId($name);
	}

	/**
	 * De sql query in het debug_blok overzichtelijk weergeven
	 * De keywords van een sql query dik(<b>) maken en de querytijd een kleur geven (rood voor trage queries, orange voor middelmatige en grijs voor snelle queries)
	 *
	 * @param string $sql
	 * @param int $truncated Number of bytes that where truncated
	 * @param float $duration
	 * @param array $backtrace
	 * @return string
	 */
	function renderLog($entry, $meta) {
		$sql = htmlspecialchars($entry, ENT_COMPAT, 'ISO-8859-15');
		static $regex = null;
		if ($regex === null) {
			$startKeywords = array('SELECT', 'UPDATE', 'ANALYSE', 'ALTER TABLE', 'REPLACE INTO', 'INSERT INTO', 'DELETE', 'CREATE TABLE', 'CREATE DATABASE', 'DESCRIBE', 'TRUNCATE TABLE', 'TRUNCATE', 'SHOW', 'SET', 'START TRANSACTION', 'ROLLBACK');
			$inlineKeywords = array('AND', 'AS', 'ASC', 'BETWEEN', 'BY', 'COLLATE', 'COLUMN', 'CURRENT_DATE', 'DESC', 'DISTINCT', 'FROM', 'GROUP', 'HAVING', 'IF', 'IN', 'INNER', 'IS', 'JOIN', 'KEY', 'LEFT', 'LIKE', 'LIMIT', 'OFFSET', 'NOT', 'NULL', 'ON', 'OR', 'ORDER', 'OUTER', 'RIGHT', 'SELECT', 'SET', 'TO', 'UNION', 'VALUES', 'WHERE');
			$regex = '/^'.implode('\b|^', $startKeywords).'\b|\b'.implode('\b|\b', $inlineKeywords).'\b/';
		}
		$sql = preg_replace($regex, '<span class="sql-keyword">\\0</span>', $sql);
		$sql = preg_replace('/`[^`]+`/', '<span class="sql-identifier">\\0</span>', $sql);
		if (isset($meta['truncated'])) {
			$kib = round($meta['truncated'] / 1024);
			$sql .= '<span class="logentry-alert">...'.$kib.' KiB truncated</span>';
		}
		echo '<td class="logentry-code">', $sql, '</td>';
		$duration = $meta['duration'];
		if ($duration > 0.2) {
			$color = 'logentry-alert';
		} elseif ($duration > 0.05) {
			$color = 'logentry-warning';
		} else {
			$color = 'logentry-debug';
		}
		echo '<td class="logentry-number ', $color, '"><b>', format_parsetime($duration), '</b>&nbsp;sec</td>';
	}

	/**
	 * Report SQL error
	 * A cleaner error than PDO::ERRMODE_WARNING generates would generate.
	 *
	 * @param string $statement
	 */
	function reportError($statement) {
		$error = $this->errorInfo();
		$this->previousInsertId = '0';
		if ($this->getAttribute(\PDO::ATTR_ERRMODE) == \PDO::ERRMODE_SILENT) { // The error issn't already reported?
			$info = array();
			if ($statement instanceof SQL) {
				$info['SQL'] = (string) $statement;
			}
			notice('SQL error ['.$error[1].'] '.$error[2], $info);
		}
	}

	/**
	 * Report MySQL warnings and notes if any.
	 *
	 * @param SQL|string $statement
	 */
	function checkWarnings($statement) {
		if (isset($this->reportWarnings) && $this->reportWarnings === true) {
			$info = array();
			if ($statement instanceof SQL) {
				$info['SQL'] = (string) $statement;
			}
			$start = microtime(true);
			$this->previousInsertId = parent::lastInsertId();
			$warnings = parent::query('SHOW WARNINGS');
			if ($warnings === false) {
				$this->reportError('SHOW WARNINGS');
				return;
			}
			$this->logger->totalDuration += (microtime(true) - $start);
			if ($warnings->rowCount()) {
				foreach ($warnings->fetchAll(\PDO::FETCH_ASSOC) as $warning) {
					notice('SQL '.strtolower($warning['Level']).' ['.$warning['Code'].'] '.$warning['Message'], $info);
				}
				// @todo Clear warnings
				// PDO/MySQL doesn't clear the warnings before CREATE/DROP DATABASE queries.
			}
		}
	}

}

?>
