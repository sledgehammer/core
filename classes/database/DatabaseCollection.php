<?php
namespace SledgeHammer;
/**
 * DatabaseCollection a Collection interface to a database result.
 * Inspired by "Linq to SQL"
 *
 * @package Core
 */
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

	function __clone() {
		if ($this->data !== null) {
			parent::__clone();
			return;
		}
		if (is_object($this->sql)) {
			$this->sql = clone $this->sql;
		}
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

	function orderBy($path, $method = SORT_REGULAR) {
		if ($this->data === null && $method == SORT_REGULAR && is_string($this->sql) === false && is_array($this->sql->order_by) && $this->sql->limit === false && $this->sql->offset == 0) {
			$sql = clone $this->sql;
			array_key_unshift($sql->order_by, $path, 'ASC');
			return new DatabaseCollection($sql, $this->dbLink);
		}
		return parent::orderBy($path, $method);
	}

	function orderByDescending($path, $method = SORT_REGULAR) {
		if ($this->data === null && $method == SORT_REGULAR && is_string($this->sql) === false && is_array($this->sql->order_by) && $this->sql->limit === false && $this->sql->offset == 0) {
			$sql = clone $this->sql;
			array_key_unshift($sql->order_by, $path, 'DESC');
			return new DatabaseCollection($sql, $this->dbLink);
		}
		return parent::orderBy($path, $method);
	}

	function skip($offset) {
		if ($this->data === null && is_string($this->sql) === false) {
			$sql = clone $this->sql;
			$sql->offset += $offset; // Add to the current offset.
			if ($sql->limit === false) {
				return new DatabaseCollection($sql, $this->dbLink);
			} else {
				$sql->limit -= $offset;
				if ($sql->limit <= 0) { // Will all entries be skipped?
					return new Collection(array()); // return an empty collection
				}
				return new DatabaseCollection($sql, $this->dbLink);
			}
		}
		return parent::skip($offset);
	}

	function take($length) {
		if ($this->data === null && is_string($this->sql) === false) {
			if ($this->sql->limit === false || $this->sql->limit >= $length) {
				return new DatabaseCollection($this->sql->limit($length), $this->dbLink);
			}
		}
		return parent::take($length);
	}

	/**
	 *
	 * @param string|SQL $sql
	 */
	function setQuery($sql) {
		$this->sql = $sql;
		$this->data = null;
	}

	function rewind() {
		$this->validateIterator();
		parent::rewind();
	}

	function count() {
		if ($this->data === null && $this->sql instanceof SQL) {
			$sql = $this->sql->select('COUNT(*)');
			return intval(getDatabase($this->dbLink)->fetchValue($sql));
		}
		$this->dataToArray();
		return count($this->data);
	}

	protected function dataToArray() {
		if (is_array($this->data)) {
			return;
		}
		if ($this->data === null) {
			$db = getDatabase($this->dbLink);
			$this->data = $db->fetchAll($this->sql);
			return;
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
