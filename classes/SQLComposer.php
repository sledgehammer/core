<?php
/**
 * Een object waar een complexe SQL query mee kunt samenstellen
 * @see http://dev.mysql.com/doc/refman/5.1/en/select.html
 *
 * @package Core
 */
class SQLComposer extends Object {

	public
		$select = 'SELECT', // Hiermee kun de "SELECT" aanpassen naar een "SELECT SQL_COUNT" e.d. 
		$fields = array(), // array met kolommen, als de key een string is wordt deze gebruikt als naam "$key AS $value"
		$where = array('operator' => 'AND'), // array met voorwaarden, wordt verbonden via operator (recursief)
		$group_by = array(), // array met group by info, wordt verbonden met ', '
		$having = array('operator' => 'AND'), // zie where
		$order_by = array(), // index is naam, waarde is DESC of ASC
		$limit = false; // [false|int|array] array('offset'=> 0, 'count'=> 10)

	private
		$tables = array(), // array met tabellen en joins (key is de label "tabel AS x" of het in het ON() gedeelte van een join)
		$tables_in_use = array(); // interne array om de join op te bouwen.

	/**
	 * De SQL string samenstellen.
	 *
	 * @return string
	 */
	function compose() {
		$fields = false;
		foreach($this->fields as $as => $field) { // kolommen opbouwen
			if (is_string($as)) {
				$field .= ' AS '.$as;
			}
			$fields[] = $field;
		}
		if (!$fields) {
			warning('1 or more fields are required');
			return false;
		}
		if (!count($this->tables)) {
			warning('1 or more tables are required');
			return false;
		}

		$sql = $this->select."\n ".implode(",\n ", $fields);

		$sql .= " \nFROM\n ".$this->export_tables($this->tables);

		if (count($this->where) > 1) {
			$where_statement = $this->export_restrictions($this->where);
			if ($where_statement != ''){
				$sql .= " \nWHERE\n ".$where_statement;
			}
		}

		if (!is_array($this->group_by)) {
			$sql .= " \nGROUP BY \n".$this->group_by;
		} elseif (count($this->group_by) > 0) {
			$sql .= " \nGROUP BY \n".implode(', ', $this->group_by);
		}

		if (count($this->having) > 1) {
			$sql .= " \nHAVING\n ".$this->export_sql_restrictions($this->having);
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
						warning('Unknown order-type: "'.$order.'"');
				}
			}
			$sql .= " \nORDER BY\n ".implode(', ', $order_by);
		}
		if ($this->limit) {
			if (is_array($this->limit)) {
				$sql .= " \nLIMIT\n ".$this->limit['offset'].', '.$this->limit['count'];
			} else {
				$sql .= " \nLIMIT\n ".$this->limit;
			}
		}
		
		return $sql;
	}

	/**
	 * Een array met kolommen toevoegen
	 *
	 * @param array $fields ('AS value' => 'column name')
	 * @return void
	 */
	function append_fields($fields) {
		foreach($fields as $as => $column) {
			if (is_string($as)) {
				$this->fields[$as] = $column; // De kolom instellen/overschrijven
			} else {
				$sleutel = array_search($column, $this->fields); // zoek op of het om een primitieve tabel gaat (join via ', ')
				if (!is_int($sleutel)) { // bestaat nog niet?
					$this->fields[] = $column;
				}
			}
		}
	}

	/**
	 * Een tabel toevoegen
	 *
	 * @param string $table table name
	 * @param mixed $name string with AS-name or false if not set
	 * @return void
	 */
	function append_table($table, $name = false) { 
		if ($name === false) {
			if (!in_array($table, $this->tables_in_use)) {
				$this->tables[] = $table;
				$this->tables_in_use[] = $table;
			}
		} elseif (!in_array($name, $this->tables_in_use)) {
			$this->tables[$name] = $table;
			$this->tables_in_use[] = $name;
		}
	}

	/**
	 * Een join toevoegen
	 * @param array $tabellen array(tablename => table) jointype specificeren via 'tabel': ('join' => 'INNER | OUTER | LEFT JOIN')
	 * @param string $on ON-clause voor join
	 * @return void
	 */ 
	function append_join($tabellen, $on) {
		if (isset($this->tables[$on])) { // Bestaat deze join al?
			return; // geen actie ondernemen
		}
		$this->tables[$on] = $tabellen;
		foreach($tabellen as $tabelnaam => $tabel) {
			if ($tabelnaam == 'join') {
				continue;
			} elseif (is_string($tabelnaam)) {
				$sleutel = $tabelnaam;
			} else {
				$sleutel = $tabel;
			}
			if (!in_array($sleutel, $this->tables_in_use)) { // Wordt deze tabel nog niet gebruikt?
				$this->tables_in_use[] = $sleutel;
			} else {
				$sleutel = array_search($tabel, $this->tables); // zoek op of het om een primitieve tabel gaat (join via ', ')
				if ($sleutel !== false) { // gevonden
					unset($this->tables[$sleutel]); // Deze tabel is niet meer nodig.
				}
			}
		}
	}

	/**
	 * De tabellen samenstellen (inclusief JOINs)
	 *
	 * @return string
	 */
	private function export_tables($tabellen) {
		$this->tables_in_use = array();
		$joins_zonder_on = true;
		foreach($tabellen as $tabel) {
			if (is_array($tabel)) { // is er een JOIN tussen de tabellen?
				$joins_zonder_on = false;
				break;
			}
		}
		if ($joins_zonder_on) { // Een join van tabellen met tabel 1, tabel 1, ... tabel n
			$sql_array = array();
			foreach($tabellen as $as => $tabel) {
				$sql_array[] = $this->export_table($tabel, $as);
			}
			return implode(', ', $sql_array);
		} else {
			$sql = '';
			foreach($tabellen as $on => $tabel) {
				$tabel = $this->export_table($tabel, $on);
				if ($sql != '' && substr($tabel, 0, 1) != ' ') {
					$sql .= ', '.$tabel;
				} else {
					$sql .= $tabel;
				}
			}
			return $sql;
		}
	}

	/**
	 * Helper functie van export_tables()
	 *
	 * @return string
	 */
	private function export_table($tabellen, $on) {
		if (is_string($tabellen)) { // is dit een tabelnaam
			$sql = $tabellen;
			if (is_string($on)) {
				$sql .= ' AS '.$on;
			}
			if (in_array($sql, $this->tables_in_use)) {
				return false;
			}
			$this->tables_in_use[] = $sql;
			return $sql;
		}
		if (isset($tabellen['join'])) {
			switch($tabellen['join']) {
				case 'INNER JOIN':
				case 'LEFT JOIN':
					$verbinding = $tabellen['join'];
					break;
				default:
					warning('Unexpected join-type: "'.$tabellen['join'].'"');
			}
			unset($tabellen['join']);
		} else {
			warning('"join" is niet ingesteld', 'Denk aan: INNER JOIN, LEFT JOIN, etc');
			return false;
		}
		$sql_array = array();
		foreach($tabellen as $as => $tabel) {
			$sql_array[] = $this->export_table($tabel, $as);
		}
		if (count($sql_array) != 2) {
			warning('De '.$verbinding.' heeft een ongeldige hoeveelheid tabellen', $tabellen);
			return false;
		}
		if ($sql_array[1] === false) {
			// De tabel aan de rechterzijde van de join staat reeds in de join
			$sql_array[1] = $tabellen[1];
		}
		$sql = implode(' '.$verbinding.' ', $sql_array);
		$sql .= ' ON ('.$on.')';
		return $sql;
	}

	// De WHERE en HAVING samenstellen
	private function export_restrictions($restrictions, $haakjes = false) { // [string]
		$prefix = '';
		if (isset($restrictions['operator'])) {
			switch($restrictions['operator']) {
				case 'AND':
				case 'OR':
					$operator = ' '.$restrictions['operator'].' ';
					break;
				case 'IN':
				case 'NOT IN':
					if (empty($restrictions['column'])) {
						warning('operator "'.$restrictions['operator'].'" requires a "column"', 'array("operator" => "'.$restrictions['operator'].'", "column" => [column name], 1, 2, n)');
						return;
					}
					$prefix = $restrictions['column'].' '.$restrictions['operator'].' ';
					unset($restrictions['column']);
					$operator = ', ';
					$haakjes = true;
					break;
				default:
					warning('Onbekende operator: "'.$restrictions['operator'].'"');
			}
			unset($restrictions['operator']); // De operator uit de array halen
		} else {
			warning('where[] statements require an "operator"', 'array("operator" => "AND", "x = 1", "y = 2")');
			return;
		}
		$sql_statements = array();
		foreach($restrictions as $sql) {
			if (is_array($sql)) { // Is het geen sql maar nog een 'restriction'?
				$haakjes_voor_node = ($sql['operator'] !== $operator); // Als de subnode dezelde verbinding heeft, dan geen haakjes
				$sql = $this->export_restrictions($sql, $haakjes_voor_node); // recursief
			}
			if ($sql != '') { // lege sql statements negeren.
				$sql_statements[] = $sql; // stukje sql aan de statement toevoegen
			}
		}
		if (count($sql_statements) == 0) { // Het was een restriction met alleen een operator "array('operator'=>'AND')", maar geen eisen.
			return '';
		}
		$sql = implode($operator, $sql_statements); // De sql statements met elkaar verbinden met de operator.
		if ($haakjes) { // moeten er haakjes omheen?
			$sql = '('.$sql.')';
		}
		return $prefix.$sql;
	}
}
?>
