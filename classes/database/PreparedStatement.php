<?php
/**
 * PreparedStatement
 */
namespace Sledgehammer;
/**
 * A PDOStatment subclass that logs prepared statements.
 * Logs the execution time and executed query to the connected Database.
 * @package Core
 */
class PreparedStatement extends PDOStatement {

	/**
	 * Direct link to the Database object that created the prepared statement.
	 * @var Database
	 */
	private $database;

	/**
	 * The bound parameters via bindParam/bindValue
	 * @var array
	 */
	private $params = array();

	/**
	 * Constructor (called from within PDO->prepare() via \PDO::ATTR_STATEMENT_CLASS)
	 * @link http://www.php.net/manual/pdo.constants.php
	 *
	 * @param Database $database
	 */
	private function __construct($database) {
		$this->database = $database;
	}

	/**
	 * Executes a prepared statement.
	 * @link http://php.net/manual/en/pdostatement.execute.php
	 *
	 * @param array $input_parameters (optional)
	 * @return bool
	 */
	function execute($input_parameters = array()) {
		$start = microtime(true);
		if (func_num_args() === 0) {
			$result = parent::execute();
		} else {
			$result = parent::execute($input_parameters);
		}
		$statement = (string) $this->queryString;
		$executedIn = (microtime(true) - $start);
		if ($this->database->logger->limit === -1 || $this->database->logger->count < $this->database->logger->limit) { // Only interpolate the query if it's going to be logged.
			$params = (count($input_parameters) === 0) ? $this->params : $input_parameters;
			$statement = $this->interpolate($statement, $params);
		}
		$this->database->logger->append($statement, array('duration' => $executedIn));
		if ($result === false) {
			$this->database->reportError($statement);
		} else {
			$this->database->checkWarnings($statement);
		}
		return $result;
	}

	/**
	 * Binds a parameter to the specified variable name.
	 * @link http://php.net/manual/en/pdostatement.bindparam.php
	 *
	 * @param mixed $parameter
	 * @param mixed $variable
	 * @param int $data_type
	 * @param int $length
	 * @param mixed $driver_options
	 * @return bool
	 */
	function bindParam($parameter, &$variable, $data_type = null, $length = null, $driver_options = null) {
		$this->params[$parameter] = &$variable;
		return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}

	/**
	 * Bind a column to a PHP variable
	 * @link http://php.net/manual/en/pdostatement.bindcolumn.php
	 *
	 * @param mixed $parameter
	 * @param mixed $value
	 * @param mixed $data_type
	 * @return bool
	 */
	function bindValue($parameter, $value, $data_type = null) {
		$this->params[$parameter] = $value;
		return parent::bindValue($parameter, $value, $data_type);
	}

	/**
	 * Replaces any parameter placeholders in a query with the value of that
	 * parameter. Useful for debugging. Assumes anonymous parameters from
	 * $params are are in the same order as specified in $query
	 *
	 * @param string $query The sql query with parameter placeholders
	 * @param array $params The array of substitution parameters
	 * @return string The interpolated query
	 */
	private function interpolate($query, $params) {
		$keys = array();

		// build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.preg_quote($key).'/';
			} else {
				$keys[] = '/[?]/';
			}
			$params[$key] = $this->database->quote($value, \PDO::PARAM_STR);
		}
		return preg_replace($keys, $params, $query, 1);
	}

}

?>
