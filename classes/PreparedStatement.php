<?php
/**
 * A PDOStatment subclass that logs prepared statements.
 * Logs the execution time and executed query to the connected Database.
 *
 * @package Core
 */
namespace SledgeHammer;

class PreparedStatement extends \PDOStatement {

	/**
	 * @var Database
	 */
	private $database;
	private $params = array();

	private function __construct($database) {
		$this->database = $database;
	}

	function execute($input_parameters = array()) {
		$start = microtime(true);
		$result = parent::execute($input_parameters);
		$query = $this->queryString;
		$executedIn = (microtime(true) - $start);
		if ($this->database->logLimit != 0) { // Only interpolate the query if it's going to be logged.
			$params = (count($input_parameters) === 0) ? $this->params : $input_parameters;
			$query = $this->interpolate($query, $params);
		}
		$this->database->logStatement($query, $executedIn);
		return $result;
	}

	function bindParam($parameter, &$variable, $data_type = null, $length = null, $driver_options = null) {
		$this->params[$parameter] = &$variable;
		return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}

	public function bindValue($parameter, $value, $data_type = null) {
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

		# build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
			} else {
				$keys[] = '/[?]/';
			}
			$params[$key] = $this->database->quote($value, \PDO::PARAM_STR);
		}
		return preg_replace($keys, $params, $query, 1, $count);
	}

}

?>
