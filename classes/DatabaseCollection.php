<?php
/**
 * DatabaseCollection a Collection interface to a database result.
 * Inspired by "Linq to SQL"
 *
 * @package Core
 */
namespace SledgeHammer;

class DatabaseCollection extends Collection {

	/**
	 * @var SQL
	 */
	public $sql;
	protected $dbLink;

	/**
	 * @param SQL|string $sql  The SELECT query
	 * @param string $dbLink  The database identifier
	 */
	function __construct($sql, $dbLink = 'default') {
		$this->sql = $sql;
		$this->dbLink = $dbLink;
	}

	/**
	 * Return a subsection of the collection based on the conditions.
	 *
	 * Convert the $conditions to SQL object when appropriate.
	 *
	 * auto converts
	 *   ['x_id' => null]  to "x_id IS NULL"
	 * 	 ['x_id !=' => null]  to "x_id IS NOT NULL"
	 *  'hits' => 0]  to "hits = '0'"  (Because in mysql '' = 0 evaluates to true, '' = '0' to false)
	 *
	 * @param array $conditions
	 * @return Collection
	 */
	function where($conditions) {
		if ($this->data !== null || is_string($this->sql) || (is_object($conditions) && is_callable($conditions))) {
			return parent::where($conditions);
		}
		$db = getDatabase($this->dbLink);
		$sql = $this->sql;
		// The result are rows(fetch_assoc arrays), all conditions must be columnnames (or invalid)
		foreach ($conditions as $column => $value) {
			if (preg_match('/^(.*) ('.COMPARE_OPERATORS.')$/', $column, $matches)) {
				$column = $matches[1];
				$operator = $matches[2];
			} else {
				$operator = '==';
			}
			if ($value === null) {
				switch ($operator) {
					case '==':
						$operator = 'IS';
						$expectation = 'NULL';
						break;

					case '!=':
						$operator = 'IS NOT ';
						$expectation = 'NULL';
						break;

					case '>':
					case '<':
					case '>=':
					case '<=':
						$expectation = "''";
						break;

					default:
						warning('Unknown behaviour for NULL values with operator "'.$operator.'"');
						$expectation = $db->quote($expectation);
						break;
				}
				$sql = $sql->andWhere($db->quoteIdentifier($column).' '.$operator.' '.$expectation);
			} else {
				if ($operator === '!=') {
					$expression = '('.$db->quoteIdentifier($column).' != '.$db->quote($value, \PDO::PARAM_STR).' OR '.$db->quoteIdentifier($column).' IS NULL)'; // Include
				} else {
					if ($operator === '==') {
						$operator = '=';
					}
					$sql = $sql->andWhere($db->quoteIdentifier($column).' '.$operator.' '.$this->quote($db, $column, $value));
				}
			}
		}
		return new DatabaseCollection($sql, $this->dbLink);
	}

	function rewind() {
		$this->validateIterator();
		parent::rewind();
	}

	function count() {
		$this->dataToArray();
		return count($this->data);
	}

	protected function dataToArray() {
		if (is_array($this->data)) {
			return;
		}
		if ($this->data === null) {
			$db = getDatabase($this->dbLink);
			$this->data = $db->query($this->sql);
		}
		if ($this->data instanceof \PDOStatement) {
			$this->data = $this->data->fetchAll();
			return;
		}
		if ($this->data === false) {
			throw new InfoException('Unable to "fetchAll()", the query failed', (string) $this->sql);
		}
		return parent::dataToArray();
	}

	private function validateIterator() {
		if ($this->data === null) {
			$db = getDatabase($this->dbLink);
			$this->data = $db->query($this->sql);
		} else {
			// @todo? iterator isDirty check
		}
	}

	/**
	 * Don't put quotes around number for columns that are assumend to be integers ('id' or ending in '_id')
	 *
	 * @param Database $db
	 * @param string $column
	 * @param mixed $value
	 */
	private function quote($db, $column, $value) {
		if ((is_int($value) || preg_match('/^[123456789]{1}[0-9]*$/', $value)) && ($column == 'id' || substr($column, -3) == '_id')) {
			return $value;
		}
		return $db->quote($value);
	}

}

?>
