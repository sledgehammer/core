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
	 * Return a subsection of the collection based on the condition criteria
	 * (Refines the SQL object when appropriate)
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
			if (preg_match('/^(.*) (<|>|<=|>=|!=|==)$/', $column, $matches)) {
				$sql = $sql->andWhere($db->quoteIdentifier($matches[1]).' '.$matches[2].' '.$db->quote($value));
			} else {
				if ($value === null) {
					$sql = $sql->andWhere($db->quoteIdentifier($column).' IS NULL');
				} else {
					$sql = $sql->andWhere($db->quoteIdentifier($column).' = '.$db->quote($value));
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
			throw new \InfoException('Unable to "fetchAll()", the query failed', (string) $this->sql);
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

}

?>
