<?php
/**
 * Een object waar een complexe SQL query mee kunt samenstellen & bewerken.
 * @see http://dev.mysql.com/doc/refman/5.1/en/select.html
 *
 * @package Core
 */
class SQL extends Object {

	private
		$select = 'SELECT', // Hiermee kun de "SELECT" aanpassen naar een "SELECT SQL_COUNT" e.d. 
		$columns = array(), // array met kolommen, als de key een string is wordt deze gebruikt als naam "$key AS $value"
		$tables = array(); // array(array('type' => 'INNER JOIN', 'table' => 'table2 AS t2', 'on' => 't1.id = t2.t1_id'));
	public
		$where = ''; // string|array met voorwaarden, wordt verbonden via operator (recursief)
	private
		$group_by = array(), // array met group by info, wordt verbonden met ', '
		$having = '', // zie where
		$order_by = array(); // index is naam, waarde is DESC of ASC
	public
		$limit = false, // false|int
		$offset = false; // false|int

	function __toString() {
		try {
			return $this->compose(); 
		} catch (Exception $e) {
			// __toString must not throw an exception
			ErrorHandler::handle_exception($e);
			return '';
		}
	}

	
	/**
	 * Een array met kolommen toevoegen
	 *
	 * @param array|string $columns ('AS value' => 'column name')
	 * @return SQL  for fluent interface 
	 */
	function select($columns) {
		if (count($this->columns) != 0) {
			notice('Overruling columns');
			$this->columns = array();
		}
		if (is_array($columns)) {
			$this->addColumns($columns);
		} else {
			$this->addColumn($columns);
		}
		return $this;
	}

	function addColumn($column, $alias = null) {
		if ($alias === null) {
			$columns = array($column);
		} else {
			$columns = array($alias => $column);
		}
		return $this->addColumns($columns);
	}

	function addColumns($columns) {
		foreach ($columns as $alias => $column) {
			$alias = $this->extractAlias($column, $alias, true); // Zit er een alias in de $column string?
			if ($alias === null) {
				$this->columns[] = $column;
			} else {
				if (isset($this->columns[$alias])) {
					notice('Overruling column(alias) "'.$alias.'"');
				}
				$this->columns[$alias] = $column;
			}
		}
		return $this;
	}

	function removeColumn($alias) {
		unset($this->columns[$alias]);
		return $this;
	}

	/**
	 * Single table: from('table') or from('table AS t')
	 * Multiple tables: from('table1', 'table2') of from(array('table1', 'table2'))
	 *   from('table1 AS t1', 'table2 t2') of from(array('t1' => 'table1', 'table2 AS t2'))
	 *
	 * @return SQL  for fluent interface 
	 */
	function from($table) {
		if (count($this->tables) != 0) {
			notice('Overruling from');
			$this->tables = array();
		}
		if (func_num_args() == 1 && is_array($table)) { // Eerste argument is een array?
			$tables = $table; 
		} else {
			$tables = func_get_args();
		}
		foreach ($tables as $alias => $table) {
			$alias = $this->extractAlias($table);
			if (isset($this->tables[$alias])) {
				throw new Exception('Table (or alias) "'.$alias.'" is not unique');
			}
			$this->tables[$alias] = $table;
		}
		return $this;
	}

	function innerJoin($table, $on) {
		return $this->join('INNER JOIN', $table, $on);
	}

	function leftJoin($table, $on) {
		return $this->join('LEFT JOIN', $table, $on);
	}

	function rightJoin($table, $on) {
		return $this->join('RIGHT JOIN', $table, $on);
	}

	function outerJoin($table, $on) {
		return $this->join('OUTER JOIN', $table, $on);
	}

	function where($where) {
		if ($this->where !== '') {
			notice('Overruling where');
		}
		$this->where = $where;
		return $this;
	}

	function andWhere($restriction) {
		if (is_array($this->where) && $this->where['operator'] == 'AND') {
			$this->where[] = $restriction;
		} else {
			$this->where = array(
				$this->where,
				'operator' => 'AND',
				$restriction
			);
		}
		return $this;
	}

	function orWhere($restriction) {
		if (is_array($this->where) && $this->where['operator'] == 'OR') {
			$this->where[] = $restriction;
		} else {
			$this->where = array(
				$this->where,
				'operator' => 'OR',
				$restriction
			);
		}
		return $this;
	}

	function groupBy($column) {
		$this->group_by[] = $column;
		return $this;
	}

	function orderBy($column, $direction = 'ASC') {
		$this->order_by = array(
				$column => $direction
				);
		return $this;
	}

	/**
	 * De SQL string samenstellen.
	 *
	 * @return string
	 */
	private function compose() {
		if (count($this->columns) == 0) {
			throw new Exception('1 or more columns are required, Call '.get_class($this).'->select($columns)');
		}
		if (count($this->tables) == 0) {
			throw new Exception('1 or tables are required, Call '.get_class($this).'->from($table)');
		}
		$sql = $this->select." ";
		$columns = array();
		foreach($this->columns as $alias => $column) { // kolommen opbouwen
			if ($alias != $column) {
				$column .= ' AS '.$alias;
			}
			$columns[] = $column;
		}
		$sql .= implode(', ', $columns);

		$sql .= ' FROM ';
		$first = true;
		foreach($this->tables as $table) {
			if (is_string($table)) { // Is er een table (geen join)
				$join = array('table' => $table);
			} else {
				$join = $table;
			}
			if (isset($join['type'])) {
				$sql .= " ".$join['type'].' '; // INNER JOIN etc
			} elseif (!$first) {
					$sql .= ', ';
			}
			$sql .= $join['table'];
			if (isset($join['on'])) {
				$sql .= ' ON ('.$join['on'].')';
			}
			$first = false;
		}
		$where = $this->composeRestrictions($this->where);
		if ($where != '') {
			$sql .= ' WHERE '.$where;
		}
		if (!is_array($this->group_by)) {
			$sql .= ' GROUP BY '.$this->group_by;
		} elseif (count($this->group_by) > 0) {
			$sql .= ' GROUP BY '.implode(', ', $this->group_by);
		}

		$having = $this->composeRestrictions($this->having);
		if ($having != '') {
			$sql .= ' HAVING '.$having;
		}

		if (count($this->order_by) > 0) {
			$order_by = array();
			foreach($this->order_by as $column => $order) {
				switch($order) {

					case 'NULL':
						$order_by[] = 'NULL';
						break;

					case 'ASC':
					case 'DESC':
						$order_by[] = $column.' '.$order;
						break;

					default:
						throw new Exception('Unknown order-type: "'.$order.'"');
				}
			}
			$sql .= ' ORDER BY '.implode(', ', $order_by);
		}
		if ($this->limit) {
			$sql .= ' LIMIT '.$this->limit;
			if ($this->offset) {
				$sql .= ' OFFSET '.$this->offset;
			}
		}
		return $sql;
	}

	/**
	 * De inhoud van de WHERE or HAVING samenstellen
	 *
	 * @param array $restrictions
	 * @param bool $haakjes
	 * @return string
	 */
	private function composeRestrictions($restrictions, $haakjes = false) { // [string]
		if (is_string($restrictions)) { // Is de restrictionsTree al geparst?
			if ($haakjes) { // moeten er haakjes omheen?
				$restrictions = '('.$restrictions.')';
			}
			return $restrictions;
		}
		$prefix = '';
		if (empty($restrictions['operator'])) {
			throw new Exception('where[] statements require an operator, Exaple: array("operator" => "AND", "x = 1", "y = 2")');
		}
		$operator = strtoupper($restrictions['operator']);
		switch($operator) {

			case 'AND':
			case 'OR':
			case 'XOR':
				$glue = ' '.$operator.' ';
				break;

			case 'IN':
			case 'NOT IN':
				if (empty($restrictions['column'])) {
					warning('operator "'.$restrictions['operator'].'" requires a "column"', 'array("operator" => "'.$restrictions['operator'].'", "column" => [column name], 1, 2, n)');
					return;
				}
				$prefix = $restrictions['column'].' '.$operator.' ';
				unset($restrictions['column']);
				$glue = ', ';
				$haakjes = true;
				break;

			default:
				throw new Exception('Onbekende operator: "'.$operator.'"');
		}
		unset($restrictions['operator']); // De operator uit de array halen
		$sql_statements = array();
		foreach($restrictions as $sql) {
			if (is_array($sql)) { // Is het geen sql maar nog een 'restriction'?
				$haakjes_voor_node = ($sql['operator'] !== $operator); // Als de subnode dezelde verbinding heeft, dan geen haakjes. "x = 1 AND (y = 5 AND z = 8)" == "x = 1 AND y = 5 AND z = 8"
				$sql = $this->composeRestrictions($sql, $haakjes_voor_node); // recursief
			}
			if ($sql != '') { // lege sql statements negeren.
				$sql_statements[] = $sql; // stukje sql aan de statement toevoegen
			}
		}
		if (count($sql_statements) == 0) { // Het was een restriction met alleen een operator "array('operator'=>'AND')", maar geen eisen.
			return '';
		}
		$sql = implode($glue, $sql_statements); // De sql statements met elkaar verbinden met de operator.
		if ($haakjes) { // moeten er haakjes omheen?
			$sql = '('.$sql.')';
		}
		return $prefix.$sql;
	}

	/**
	 * Helper voor de diverse *Join functies
	 * @return $this  For fluent interface
	 */
	private function join($type, $table, $on) {
		$alias = $this->extractAlias($table);
		if (isset($this->tables[$alias])) {
			throw new Exception('Table (or alias) "'.$alias.'" is not unique');
		}
		$this->tables[$alias] = array(
			'type' => $type,
			'table' => $table,
			'on' => $on
		);
		return $this;
	}

	/**
	 * De alias van een kolom of tabel uitzoeken. 
	 * Geeft anders de gehele string terug.
	 *
	 * @return string
	 */
	private function extractAlias(&$string, $alias = null, $stripAlias = false) {
		if (is_string($alias)) { // Is er een alias opgegeven?
			return $alias;
		}
		$string = trim($string);
		if (preg_match('/(.*)\sAS\s([\S]+)$/i', $string, $match)) { // column AS alias
			if ($stripAlias) {
				$string = $match[1];
			}
			return $match[2];
		}
		if (preg_match('/(.*)\s([\S]+)$/i', $string, $match)) { // column alias 
			if ($stripAlias) {
				$string = $match[1];
			}
			return $match[2];
		}
		return $string;
	}
}
?>
