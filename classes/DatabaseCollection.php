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
		if ($this->iterator !== null || is_string($this->sql)) {
			return parent::where($conditions);
		}
		$db = getDatabase($this->dbLink);
		$sql = $this->sql;
		// The result are rows(fetch_assoc arrays), all conditions must be columnnames (or invalid)
		foreach ($conditions as $column => $value) {
			$sql = $sql->andWhere($db->quoteIdentifier($column).' = '.$db->quote($value));
		}
		return new DatabaseCollection($sql, $this->dbLink);
	}

	function rewind() {
		$this->validateIterator();
		parent::rewind();
	}

	function count() {
		$this->validateIterator();
		return parent::count();
	}

	private function validateIterator() {
		if ($this->iterator == null) {
			$db = getDatabase($this->dbLink);
			$this->iterator = $db->query($this->sql);
		} else {
			// @todo? iterator isDirty check
		}
	}

}

?>
