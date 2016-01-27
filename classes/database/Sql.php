<?php

/**
 * Sql
 */

namespace Sledgehammer;

/**
 * Query object to mutate and generate (complex) SQL queries.
 * @link http://martinfowler.com/eaaCatalog/queryObject.html
 *
 * Doesn't have a "fluent" interface: Methods won't modify the object (and return $this) but will return a new object.
 *
 *   $sql = select('*')->from('customers'); // uses the select shorthand
 *
 *   $sql2 = $sql->where('id = 1');
 *   echo $sql; // "SELECT * FROM customers"
 *   echo $sql2; // "SELECT * FROM customers WHERE id = 1"
 *
 * More on method chaining: http://stackoverflow.com/questions/1103985/method-chaining-why-is-it-a-good-practice-or-not#7215149
 *
 * @package Core
 */
class Sql extends Object {

    /**
     * Hiermee kun de "SELECT" aanpassen naar een "SELECT SQL_COUNT" e.d.
     * @link http://dev.mysql.com/doc/refman/5.6/en/select.html
     * @var string
     */
    public $select = 'SELECT';

    /**
     * Array or string containing the fields, When the key is a string it will be used as alias "$value AS $key"
     * "*" becomes "*"
     * "id, name" becomes "id, name"
     * array('id', 'name') becomes "id, name"
     * array('id', 'name2' => 'name') becomes "id, name AS name2"
     * @var array|string
     */
    public $columns = [];

    /**
     * array('alias1'=> 'table1', 't2' => array('type' => 'INNER JOIN', 'table' => 'table2 AS t2', 'on' => 't1.id = t2.t1_id'));
     * @var array
     */
    public $tables = [];

    /**
     * Tree-structure with conditions. array('AND', 'col1 = 23', array('OR', 'col2 = 1', 'col2 = 56'))
     * @var string|array
     */
    public $where = '';

    /**
     * Array met group by info, wordt verbonden met ', '
     * @var array|string
     */
    public $groupBy = [];

    /**
     * Similar to $where, but for the HAVING clause.
     * @var string|array
     */
    public $having = '';

    /**
     * index is naam, waarde is DESC of ASC
     * @var array|string
     */
    public $orderBy = [];

    /**
     * Limit the number of results
     * @var false|int
     */
    public $limit = false;

    /**
     * Skip the first x results.
     * @var false|int
     */
    public $offset = false;

    /**
     * Allow the SQL object to be used as string.
     * @return string
     */
    function __toString() {
        try {
            return $this->compose();
        } catch (\Exception $e) {
            // __toString must not throw an exception
            report_exception($e);
            return '';
        }
    }

    /**
     * Adds columns to the existing $this->columns.
     *
     * @param array $columns
     * param string ...
     */
    function addColumns($columns) {
        if (func_num_args() > 1) { // Support for $this->addColumns('col1', 'col2') notation.
            $columns = func_get_args();
        } elseif (is_string($columns)) {
            $columns = array($columns);
        }
        foreach ($columns as $alias => $column) {
            $alias = $this->extractAlias($column, $alias, true); // Zit er een alias in de $column string?
            if ($alias === null) {
                $this->columns[] = $column;
            } else {
                if (isset($sql->columns[$alias])) {
                    notice('Overruling column(alias) "' . $alias . '"');
                }
                $this->columns[$alias] = $column;
            }
        }
    }

    /**
     * Remove a column via its alias.
     *
     * @param string $alias
     */
    function removeColumn($alias) {
        unset($sql->columns[$alias]);
    }

    /**
     * Set the FROM clause to one or more tables.
     *
     * @param string|array $table
     */
    function setFrom($table) {
        if (count($this->tables) != 0) {
            $this->tables = [];
        }
        if (func_num_args() == 1 && is_array($table)) { // Eerste argument is een array?
            $tables = $table;
        } else {
            $tables = func_get_args();
        }
        foreach ($tables as $alias => $table) {
            $alias = $this->extractAlias($table);
            if (isset($this->tables[$alias])) {
                throw new \Exception('Table (or alias) "' . $alias . '" is not unique');
            }
            $this->tables[$alias] = $table;
        }
    }

    /**
     * Set/Append a join to the FROM
     *
     *
     * @param string $table
     * @param string $type 'INNER JOIN', ',', 'LEFT JOIN'
     * @param string $on
     */
    function setJoin($table, $type = ',', $on = null) {
        $type = strtoupper($type);
        $alias = $this->extractAlias($table);
        if (isset($this->tables[$alias])) {
            throw new \Exception('Table (or alias) "' . $alias . '" is not unique');
        }
        $this->tables[$alias] = array(
            'type' => $type,
            'table' => $table,
            'on' => $on
        );
    }

    /**
     * Returns a new SQL with $sql->columns replaced by the given $columns
     *
     * @param array|string $columns ('AS value' => 'column name')
     * param string ...
     * @return Sql
     */
    function select($columns) {
        $sql = clone $this;
        $sql->columns = [];
        if (is_array($columns)) {
            $sql->addColumns($columns);
        } else {
            $sql->addColumns(func_get_args()); // Add ALL parameters as columns.
        }
        return $sql;
    }

    /**
     * Returns a new SQL with the given $column added to the $sql->columns
     *
     * @param string $column
     * @param string $alias
     * @return Sql
     */
    function column($column, $alias = null) {
        if ($alias === null) {
            $columns = array($column);
        } else {
            $columns = array($alias => $column);
        }
        return $this->columns($columns);
    }

    /**
     * Returns a new SQL with the given $columns added to the $sql->columns.
     * To override current columns use $sql->select()
     *
     * @param array|string $columns
     * param string ...
     * @return Sql
     */
    function columns($columns) {
        $sql = clone $this;
        if (func_num_args() > 1) {
            $columns = func_get_args();
        }
        $sql->addColumns($columns);
        return $sql;
    }

    /**
     * Single table: from('table') or from('table AS t')
     * Multiple tables: from('table1', 'table2') of from(array('table1', 'table2'))
     *   from('table1 AS t1', 'table2 t2') of from(array('t1' => 'table1', 'table2 AS t2'))
     *
     * @param string|array $table
     * @return Sql
     */
    function from($table) {
        $sql = clone $this;
        $sql->setFrom($table);
        return $sql;
    }

    /**
     * Returns a new SQL with the given join appended.
     *
     * @param string $table
     * @param string $on
     * @return Sql
     */
    function innerJoin($table, $on) {
        return $this->join('INNER JOIN', $table, $on);
    }

    /**
     * Returns a new SQL with the given join appended.
     *
     * @param string $table
     * @param string $on
     * @return Sql
     */
    function leftJoin($table, $on) {
        return $this->join('LEFT JOIN', $table, $on);
    }

    /**
     * Returns a new SQL with the given join appended.
     *
     * @param string $table
     * @param string $on
     * @return Sql
     */
    function rightJoin($table, $on) {
        return $this->join('RIGHT JOIN', $table, $on);
    }

    /**
     * Returns a new SQL with the $sql->where set to the given $where.
     *
     * @param string|array $where
     * @return Sql
     */
    function where($where) {
        $sql = clone $this;
        if ($sql->where !== '') {
            notice('Overruling where');
        }
        $sql->where = $where;
        return $sql;
    }

    /**
     * Returns a new SQL with the where modified to include the "AND" restriction.
     *
     * @param mixed $restriction
     * @return Sql
     */
    function andWhere($restriction) {
        $sql = clone $this;
        if ($sql->where === '') {
            $sql->where = array('AND', $restriction);
        } elseif (extract_logical_operator($sql->where) === 'AND') {
            $sql->where[] = $restriction;
        } else {
            $sql->where = array('AND', $sql->where, $restriction);
        }
        return $sql;
    }

    /**
     * Returns a new SQL with the where modified to include the "OR" restriction.
     *
     * @param mixed $restriction
     * @return Sql
     */
    function orWhere($restriction) {
        $sql = clone $this;
        if ($sql->where === '') {
            $sql->where = array('OR', $restriction);
        } elseif (extract_logical_operator($sql->where) === 'OR') {
            $sql->where[] = $restriction;
        } else {
            $sql->where = array('OR', $sql->where, $restriction);
        }
        return $sql;
    }

    /**
     * Returns a new SQL with $this->groupBy set to the given $columns.
     *
     * @param string|array $columns
     * @return Sql
     */
    function groupBy($columns) {
        $sql = clone $this;
        $sql->groupBy = $columns;
        return $sql;
    }

    /**
     * Returns a new SQL with $this->orderBy set to the given $column => $direction
     *
     * @param string $column
     * @param string $direction "ASC" or "DESC"
     * @return Sql
     */
    function orderBy($column, $direction = 'ASC') {
        $sql = clone $this;
        $sql->orderBy = array(
            $column => $direction
        );
        return $sql;
    }

    // @todo implement thenBy($column, $direction)

    /**
     * Return a new SQL with $this->limit set to the given $limit.
     *
     * @param int $limit The maximum number of records
     * @return Sql
     */
    function limit($limit) {
        $sql = clone $this;
        $sql->limit = $limit;
        return $sql;
    }

    /**
     * Return a new SQL with $this->offset set to the given $limit.
     *
     * @param int $offset Skip x records.
     * @return Sql
     */
    function offset($offset) {
        $sql = clone $this;
        $sql->offset = $offset;
        return $sql;
    }

    /**
     * Build the SQL string.
     *
     * @return string
     */
    private function compose() {
        if (count($this->columns) == 0) {
            throw new \Exception('1 or more columns are required, Call ' . get_class($this) . '->select($columns)');
        }
        if (count($this->tables) == 0) {
            throw new \Exception('1 or tables are required, Call ' . get_class($this) . '->from($table)');
        }
        $sql = $this->select . " ";
        if (is_string($this->columns)) {
            $sql .= $this->columns;
        } else {
            $columns = [];
            foreach ($this->columns as $alias => $column) { // kolommen opbouwen
                if ($alias != $column) {
                    $column .= ' AS ' . $alias;
                }
                $columns[] = $column;
            }
            $sql .= implode(', ', $columns);
        }

        $sql .= ' FROM ';
        if (is_string($this->tables)) {
            $sql .= $this->tables;
        } else {
            $first = true;
            foreach ($this->tables as $table) {
                if (is_string($table)) { // Is er een table (geen join)
                    $join = array('table' => $table);
                } else {
                    $join = $table;
                }
                if (isset($join['type'])) {
                    $sql .= " " . $join['type'] . ' '; // INNER JOIN etc
                } elseif (!$first) {
                    $sql .= ', ';
                }
                $sql .= $join['table'];
                if (isset($join['on'])) {
                    $sql .= ' ON (' . $join['on'] . ')';
                }
                $first = false;
            }
        }
        $where = $this->composeRestrictions($this->where);
        if ($where != '') {
            $sql .= ' WHERE ' . $where;
        }
        if (!is_array($this->groupBy)) {
            $sql .= ' GROUP BY ' . $this->groupBy;
        } elseif (count($this->groupBy) > 0) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        $having = $this->composeRestrictions($this->having);
        if ($having != '') {
            $sql .= ' HAVING ' . $having;
        }
        if (count($this->orderBy) > 0) {
            $order_by = [];
            foreach ($this->orderBy as $column => $order) {
                switch ($order) {

                    case 'NULL':
                        $order_by[] = 'NULL';
                        break;

                    case 'ASC':
                    case 'DESC':
                        $order_by[] = $column . ' ' . $order;
                        break;

                    default:
                        throw new \Exception('Unknown order-type: "' . $order . '"');
                }
            }
            $sql .= ' ORDER BY ' . implode(', ', $order_by);
        }
        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        return $sql;
    }

    /**
     * De inhoud van de WHERE or HAVING samenstellen
     *
     * @param array $restrictions
     * @param bool $addBraces
     * @return string
     */
    private function composeRestrictions($restrictions, $addBraces = false) { // [string]
        if (is_string($restrictions)) { // Is the restrictionsTree already parsed?
            if ($addBraces) {
                return '(' . $restrictions . ')';
            }
            return $restrictions;
        }
        $logicalOperator = extract_logical_operator($restrictions);
        if ($logicalOperator === false) {
            if (count($restrictions) !== 1) {
                throw new InfoException('where[] statements require an logical operator, Example: array("AND", "x = 1", "y = 2")', $restrictions);
            }
        } else {
            unset($restrictions[0]); // Remove the operator from the array.
        }
        if (count($restrictions) === 1) {
            reset($restrictions);
            return $this->composeRestrictions(current($restrictions));
        }
        switch ($logicalOperator) {

            case 'AND':
            case 'OR':
                break;

            default:
                throw new \Exception('Unknown logical operator: "' . $logicalOperator . '"');
        }

        $expressions = [];
        foreach ($restrictions as $restriction) {
            if (is_array($restriction)) { // Is het geen sql maar nog een 'restriction'?
                $operatorSwitch = (extract_logical_operator($restriction) !== $logicalOperator); // If the subnode has the same logical operator, don't add braces. "x = 1 AND (y = 5 AND z = 8)" has same meaning as "x = 1 AND y = 5 AND z = 8"
                $restriction = $this->composeRestrictions($restriction, $operatorSwitch);
            }
            if ($restriction != '') { // ignore empty statements.
                $expressions[] = $restriction;
            }
        }
        if (count($expressions) == 0) { // No statements (a restriction with just the operator "array(AND')" but no restrictions).
            return '';
        }
        $sql = implode(' ' . $logicalOperator . ' ', $expressions); // Join the sql statements with the logical operator.
        if ($addBraces) { // moeten er haakjes omheen?
            return '(' . $sql . ')';
        }
        return $sql;
    }

    /**
     * Returns a new SQL with the join added to the sql.
     *
     * @param string $type "INNER JOIN", "LEFT JOIN", or "RIGHT JOIN"
     * @param string $table Name of the table.
     * @param string $on The ON conditions.
     * @return \Sledgehammer\Sql
     */
    private function join($type, $table, $on) {
        $sql = clone $this;
        $sql->setJoin($table, $type, $on);
        return $sql;
    }

    /**
     * De alias van een kolom of tabel uitzoeken.
     * Geeft anders de gehele string terug.
     *
     * @param string $string
     * @param string $alias
     * @param bool $stripAlias
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
