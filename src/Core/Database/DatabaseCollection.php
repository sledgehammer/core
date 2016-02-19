<?php

namespace Sledgehammer\Core\Database;

use Exception;
use PDO;
use PDOStatement;
use Sledgehammer\Core\Collection;
use Sledgehammer\Core\InfoException;
use Sledgehammer\Core\PropertyPath;
use Traversable;
use const Sledgehammer\COMPARE_OPERATORS;
use function Sledgehammer\array_key_unshift;
use function Sledgehammer\extract_logical_operator;
use function Sledgehammer\notice;

/**
 * DatabaseCollection a Collection interface to a database result.
 * It will lazyily generate & mutate the SQL query based on the filter & sorting operations.
 * Inspired by "Linq to SQL".
 */
class DatabaseCollection extends Collection
{
    /**
     * The SQL object  or string fetches the items in this collection.
     *
     * @var Sql|string
     */
    private $sql;

    /**
     * The database identifier. (default: "default").
     *
     * @var string
     */
    protected $dbLink;

    /**
     * Constructor.
     *
     * @param Sql|string $sql    The SELECT query.
     * @param string     $dbLink The database identifier.
     */
    public function __construct($sql, $dbLink = 'default')
    {
        $this->sql = $sql;
        $this->dbLink = $dbLink;
    }

    /**
     * Clone a database collection.
     */
    public function __clone()
    {
        if ($this->data !== null) {
            parent::__clone();

            return;
        }
        if (is_object($this->sql)) {
            $this->sql = clone $this->sql;
        }
    }

    /**
     * Return a new collection where each element is a subselection of the original element.
     * (Known as "collect" in Ruby or "pluck" in underscore.js).
     *
     * @param string|array      $selector  Path to the variable to select. Examples: "->id", "[message]", "customer.name", array('id' => 'message_id', 'message' => 'message_text')
     * @param string|null|false $selectKey (optional) The path that will be used as key. false: Keep the current key, null:  create linear keys.
     *
     * @return Collection
     */
    public function select($selector, $selectKey = false)
    {
        if ($this->data !== null || is_string($this->sql) || (is_object($selector) && is_callable($selector))) {
            return parent::select($selector, $selectKey);
        }
        if (is_int($selector)) {
            $selector = (string) $selector;
        }
        $selectorPaths = is_string($selector) ? array($selector => $selector) : $selector;
        $hasKeySelector = ($selectKey !== false && $selectKey !== null);
        if ($hasKeySelector) {
            array_key_unshift($selectorPaths, $selectKey, $selectKey);
        }
        if (count($selectorPaths) === 0) { // empty selector?
            return parent::select($selector, $selectKey);
        }
        $isWildcardSelector = ($this->sql->columns === '*' || $this->sql->columns == array('*' => '*'));
        if ($isWildcardSelector === false && (is_string($this->sql->columns) || count($selectorPaths) >= count($this->sql->columns))) {
            // The selector can't be a subsection of current columns.
            return parent::select($selector, $selectKey);
        }

        $columns = [];
        foreach ($selectorPaths as $to => $from) {
            $column = $this->convertPathToColumn($from);
            $alias = $this->convertPathToColumn($to);
            if ($column === false || $alias === false) { // Path can't be mapped to column
                return parent::select($selector, $selectKey);
            }
            if ($isWildcardSelector === false) {
                if (isset($this->sql->columns[$column])) {
                    $column = $this->sql->columns[$column]; // Use the original column/calculation (just change the alias)
                } else {
                    // @todo Use array_search() for support of indexed array of columns.
                    return parent::select($selector, $selectKey);
                }
            }
            if ($hasKeySelector) {
                $alias = $column; // Don't alias in SQL, but (re)use the $selector.
            }
            $columns[$alias] = $column;
        }
        $collection = new self($this->sql->select($columns), $this->dbLink);
        if ($hasKeySelector || is_string($selector)) { // Does the $collection requires additional selecting?
            return $collection->select($selector, $selectKey);
        }

        return $collection;
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
     *
     * @return Collection
     */
    public function where($conditions)
    {
        if ($this->data !== null || is_string($this->sql) || (is_object($conditions) && is_callable($conditions)) || $this->sql->limit !== false || $this->sql->offset != 0) {
            return parent::where($conditions);
        }
        $db = Connection::instance($this->dbLink);
        $sql = $this->sql;
        $logicalOperator = extract_logical_operator($conditions);
        if ($logicalOperator === false) {
            if (count($conditions) > 1) {
                notice('Conditions with multiple conditions require a logical operator.', "Example: array('AND', 'x' => 1, 'y' => 5)");
            }
            $logicalOperator = 'AND';
        } else {
            unset($conditions[0]);
        }
        if ($logicalOperator === 'AND') {
            $method = 'andWhere';
        } elseif ($logicalOperator === 'OR') {
            $method = 'orWhere';
        } else {
            throw new Exception('Unsupported logical operator "'.$logicalOperator.'", expecting "AND" or "OR"');
        }

        // The result are rows(fetch_assoc arrays), all conditions must be columnnames (or invalid)
        foreach ($conditions as $path => $value) {
            if (preg_match('/^(.*) ('.COMPARE_OPERATORS.')$/', $path, $matches)) {
                $column = $this->convertPathToColumn($matches[1]);
                $operator = $matches[2];
            } else {
                $column = $this->convertPathToColumn($path);
                $operator = '==';
            }
            if ($column === false) { // Converting to path failed?
                array_key_unshift($conditions, 0, $logicalOperator);

                return parent::where($conditions);
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
                        warning('Unknown behavior for NULL values with operator "'.$operator.'"');
                        $expectation = $db->quote($expectation);
                        break;
                }
                $sql = $sql->$method($column.' '.$operator.' '.$expectation);
            } else {
                if ($operator === '!=') {
                    $sql = $sql->$method('('.$column.' != '.$db->quote($value, PDO::PARAM_STR).' OR '.$column.' IS NULL)');
                } elseif ($operator === 'IN') {
                    if ((is_array($value) || $value instanceof Traversable) === false) {
                        notice('Operator IN expects an array or Traversable', $value);
                        $value = explode(',', $value);
                    }
                    $quoted = [];
                    foreach ($value as $val) {
                        $quoted[] = $this->quote($db, $column, $val);
                    }
                    $sql = $sql->$method($column.' '.$operator.' ('.implode(', ', $quoted).')');
                } else {
                    if ($operator === '==') {
                        $operator = '=';
                    }
                    $sql = $sql->$method($column.' '.$operator.' '.$this->quote($db, $column, $value));
                }
            }
        }

        return new self($sql, $this->dbLink);
    }

    /**
     * Return a new collection sorted by the given field in ascending order.
     *
     * @param string $selector
     * @param int    $method   The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
     *
     * @return Collection
     */
    public function orderBy($selector, $method = SORT_REGULAR)
    {
        if ($this->data === null && $method == SORT_REGULAR && is_string($selector) && is_string($this->sql) === false && is_array($this->sql->orderBy) && $this->sql->limit === false && $this->sql->offset == 0) {
            $sql = clone $this->sql;
            array_key_unshift($sql->orderBy, $selector, 'ASC');

            return new self($sql, $this->dbLink);
        }

        return parent::orderBy($selector, $method);
    }

    /**
     * Return a new collection sorted by the given field in descending order.
     *
     * @param string $selector
     * @param int    $method   The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
     *
     * @return Collection
     */
    public function orderByDescending($selector, $method = SORT_REGULAR)
    {
        if ($this->data === null && $method == SORT_REGULAR && is_string($selector) && is_string($this->sql) === false && is_array($this->sql->orderBy) && $this->sql->limit === false && $this->sql->offset == 0) {
            $sql = clone $this->sql;
            array_key_unshift($sql->orderBy, $selector, 'DESC');

            return new self($sql, $this->dbLink);
        }

        return parent::orderByDescending($selector, $method);
    }

    /**
     * Return a new Collection without the first x items.
     *
     * @param int $offset
     *
     * @return Collection
     */
    public function skip($offset)
    {
        if ($this->data === null && is_string($this->sql) === false) {
            $sql = clone $this->sql;
            $sql->offset += $offset; // Add to the current offset.
            if ($sql->limit === false) {
                return new self($sql, $this->dbLink);
            } else {
                $sql->limit -= $offset;
                if ($sql->limit <= 0) { // Will all entries be skipped?
                    return new Collection(array()); // return an empty collection
                }

                return new self($sql, $this->dbLink);
            }
        }

        return parent::skip($offset);
    }

    /**
     * Return a new Collection with only the first x items.
     *
     * @param int $limit
     *
     * @return Collection
     */
    public function take($limit)
    {
        if ($this->data === null && is_string($this->sql) === false) {
            if ($this->sql->limit === false || $this->sql->limit >= $limit) {
                return new self($this->sql->limit($limit), $this->dbLink);
            }
        }

        return parent::take($limit);
    }

    /**
     * @inherit
     */
    public function min($selector = '.')
    {
        if (is_string($selector) && $selector !== '.' && $this->data === null && is_string($this->sql) === false) {
            if ($this->sql->limit === false && $this->sql->offset === false && is_array($this->sql->groupBy) && count($this->sql->groupBy) === 0) {
                $db = Connection::instance($this->dbLink);
                $sql = $this->sql->select('MIN('.$db->quoteIdentifier($selector).')');

                return $db->fetchValue($sql);
            }
        }

        return parent::min($selector);
    }

    /**
     * @inherit
     */
    public function max($selector = '.')
    {
        if (is_string($selector) && $selector !== '.' && $this->data === null && is_string($this->sql) === false) {
            if ($this->sql->limit === false && $this->sql->offset === false && is_array($this->sql->groupBy) && count($this->sql->groupBy) === 0) {
                $db = Connection::instance($this->dbLink);
                $sql = $this->sql->select('MAX('.$db->quoteIdentifier($selector).')');

                return $db->fetchValue($sql);
            }
        }

        return parent::max($selector);
    }

    /**
     * Inspect the SQL query.
     *
     * @return string|Sql
     */
    public function getQuery()
    {
        if (is_object($this->sql)) {
            return clone $this->sql;
        } else {
            return $this->sql;
        }
    }

    /**
     * Override the SQL query.
     *
     * @param string|Sql $sql
     */
    public function setQuery($sql)
    {
        $this->sql = $sql;
        $this->data = null;
    }

    /**
     * Returns the number of elements in the collection.
     *
     * @link http://php.net/manual/en/class.countable.php
     *
     * @return int
     */
    public function count()
    {
        if ($this->data === null && $this->sql instanceof Sql && is_array($this->sql->groupBy) && count($this->sql->groupBy) === 0) {
            $sql = $this->sql->select('COUNT(*)')->limit(false)->offset(false);
            $count = intval(Connection::instance($this->dbLink)->fetchValue($sql));
            if ($this->sql->offset) {
                $count -= $this->sql->offset;
                if ($count < 0) {
                    return 0;
                }
            }
            if ($this->sql->limit !== false && $count > $this->sql->limit) {
                $count = $this->sql->limit;
            }

            return $count;
        }
        $this->dataToArray();

        return count($this->data);
    }

    /**
     * Converts $this->data into an array.
     */
    protected function dataToArray()
    {
        if (is_array($this->data)) {
            return;
        }
        if ($this->data === null) {
            $db = Connection::instance($this->dbLink);
            $this->data = $db->fetchAll($this->sql);

            return;
        }
        if ($this->data instanceof PDOStatement) {
            $this->data = $this->data->fetchAll();

            return;
        }
        if ($this->data === false) {
            throw new InfoException('Unable to "fetchAll()", the query failed', (string) $this->sql);
        }

        return parent::dataToArray();
    }

    /**
     * Don't put quotes around number for columns that are assumend to be integers ('id' or ending in '_id').
     *
     * @param Database $db
     * @param string   $column
     * @param mixed    $value
     */
    private function quote($db, $column, $value)
    {
        if (($column == 'id' || substr($column, -3) == '_id') && (is_int($value) || preg_match('/^[123456789]{1}[0-9]*$/', $value))) {
            return $value;
        }

        return $db->quote($value);
    }

    /**
     * Convert a path to a (escaped) columnname.
     *
     * @param string $path
     */
    private function convertPathToColumn($path)
    {
        $compiled = PropertyPath::parse($path);
        if (count($compiled) > 1) {
            return false;
        }
        if (in_array($compiled[0][0], array(PropertyPath::TYPE_ANY, PropertyPath::TYPE_ELEMENT))) {
            $db = Connection::instance($this->dbLink);

            return $db->quoteIdentifier($compiled[0][1]);
        }

        return false;
    }
}
