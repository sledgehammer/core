<?php

/**
 * Collection.
 */

namespace Sledgehammer\Core;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Exception;
use Iterator;
use IteratorAggregate;

/**
 * Collection: Array on Steriods
 * Provides a filtering, sorting, events and other utility functions for collections.
 *
 * Inspired by LinQ and Underscore.php
 */
class Collection extends Object implements IteratorAggregate, Countable, ArrayAccess
{
    use Observable;
    /**
     * The traversable the Collection class operates on.
     *
     * @var \Traversable Iterator / array
     */
    protected $data;

    /**
     * Allow listening to the events: 'changing' and 'changed'.
     *
     * @var array
     */
    protected $events = array(
        'adding' => [],
        'added' => [],
        'removing' => [],
        'removed' => [],
    );

    /**
     * Constructor.
     *
     * @param \Traversable|array $data
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Return a new collection where each element is a subselection of the original element.
     * (Known as "collect" in Ruby or "pluck" in underscore.js).
     *
     * @param string|array|Closure $selector  Path to the variable to select. Examples: "->id", "[message]", "customer.name", array('id' => 'message_id', 'message' => 'message_text')
     * @param string|null|false    $selectKey (optional) The path that will be used as key. false: Keep the current key, null:  create linear keys.
     *
     * @return Collection
     */
    public function select($selector, $selectKey = false)
    {
        if (\Sledgehammer\is_closure($selector)) {
            $closure = $selector;
        } elseif (is_array($selector)) {
            $closure = function ($item) use ($selector) {
                $target = [];
                PropertyPath::map($item, $target, $selector);

                return $target;
            };
        } else {
            $closure = PropertyPath::compile($selector);
        }
        if ($selectKey === null) {
            $index = 0;
        }
        $items = [];
        foreach ($this as $key => $item) {
            if ($selectKey === null) {
                $key = $index;
                ++$index;
            } elseif ($selectKey !== false) {
                $key = PropertyPath::get($selectKey, $item);
            }
            $items[$key] = $closure($item, $key);
        }

        return new self($items);
    }

    /**
     * Returns a new collection where the key is based on a property.
     *
     * @param string|null|Closure $selector The path that will be used as key.
     *
     * @return Collection
     */
    public function selectKey($selector)
    {
        if ($selector === null) {
            if (is_array($this->data)) {
                return new self(array_values($this->data));
            }
            $items = [];
            foreach ($this as $key => $item) {
                $items[] = $item;
            }

            return new self($items);
        } elseif (\Sledgehammer\is_closure($selector)) {
            $closure = $selector;
        } else {
            $closure = PropertyPath::compile($selector);
        }
        $items = [];
        foreach ($this as $key => $item) {
            $items[$closure($item, $key)] = $item;
        }

        return new self($items);
    }

    /**
     * Return a new Collection with a subsection of the collection based on the conditions.
     *
     * @examples
     * where('apple') returns the items with the value is "apple" (1D array)
     * where('> 5')   returns the items where the value is greater-than 5
     * where(array('id' => 4))  returns the items where the element or property 'id' is 4
     * where(array('user->id <' => 4))  returns the items where the property 'id' of element/property 'user' is smaller-than 4
     * where(array('id IN' => array(2, 3, 5)))  returns the items where the property 'id' is 2, 3 or 5
     * where(function ($item) { return (strpos($item, 'needle') !== false); })  return the items which contain the text 'needle'
     *
     * @see PropertyPath::get() & \Sledgehammer\compare() for supported paths and operators
     *
     * NOTE: The Collection class uses \Sledgehammer\compare() for matching while the DatabaseCollection uses the sql WHERE part.
     *       this may cause different behavior. For example "ABC" == "abc" might evalute to true in MySQL (depends on the chosen collation)
     *
     * @param mixed $conditions array|Closure|expression
     *
     * @return Collection
     */
    public function where($conditions)
    {
        $filter = $this->buildFilter($conditions);
        $data = [];
        $counter = -1;
        foreach ($this as $key => $item) {
            ++$counter;
            if ($filter($item, $key) === false) {
                continue;
            }
            if ($key != $counter) { // Is this an array?
                $data[$key] = $item;
            } else {
                $data[] = $item;
            }
        }

        return new self($data);
    }

    /**
     * Returns the first item that matches the conditions.
     *
     * @param mixed $conditions array|Closure|expression  See Collection::where() for condition options
     * @param bool  $allowNone  When no match is found, return null instead of throwing an Exception.
     *
     * @return mixed
     */
    public function find($conditions, $allowNone = false)
    {
        $filter = $this->buildFilter($conditions);
        foreach ($this as $key => $item) {
            if ($filter($item, $key) !== false) {
                return $item;
            }
        }
        if ($allowNone) {
            return;
        }
        throw new Exception('No item found that matches the conditions');
    }

    /**
     * Remove one or more items from the this collection.
     *
     * @param mixed $conditions array|Closure|expression  See Collection::where() for condition options
     *
     * @return bool
     */
    public function remove($conditions, $allowNone = false)
    {
        $this->dataToArray();
        $filter = $this->buildFilter($conditions);
        $removeKeys = [];
        $isIndexed = true;
        $index = 0;
        $previousKey = false;
        foreach ($this as $key => $item) {
            if ($index !== $key) {
                $isIndexed = false;
            }
            ++$index;
            if ($filter($item, $key) !== false) {
                $removeKeys[] = $key;
            }
            $previousKey = $key;
        }
        foreach ($removeKeys as $key) {
            $this->offsetUnset($key);
        }
        if (count($removeKeys) === 0) {
            if ($allowNone) {
                return false;
            }
            throw new Exception('Unable to remove the entry, No item found that matches the conditions');
        }
        if ($isIndexed) {
            $this->data = array_values($this->data);
        }

        return true;
    }

    /**
     * Returns the the key of first item that matches the conditions.
     *
     * Returns null when nothing matched the conditions.
     *
     * @param mixed $conditions array|Closure|expression  See Collection::where() for condition options
     *
     * @return mixed|null
     */
    public function indexOf($conditions)
    {
        $this->dataToArray(); // Array-access is expected, convert data to array.
        $filter = $this->buildFilter($conditions);
        foreach ($this as $key => $item) {
            if ($filter($item, $key) !== false) {
                return $key;
            }
        }

        return;
    }

    /**
     * Return the highest value.
     *
     * @param string|Closure $selector Path to the variable to select. Examples: "->id", "[message]", "customer.name"
     *
     * @return mixed
     */
    public function max($selector = '.')
    {
        if (\Sledgehammer\is_closure($selector)) {
            $closure = $selector;
        } else {
            $closure = PropertyPath::compile($selector);
        }
        $max = null;
        $first = true;
        foreach ($this as $item) {
            $value = $closure($item);
            if ($first) {
                $first = false;
                $max = $value;
            } elseif ($value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    /**
     * Return the lowest value.
     *
     * @param string|Closure $selector Path to the variable to select. Examples: "->id", "[message]", "customer.name"
     *
     * @return mixed
     */
    public function min($selector = '.')
    {
        if (\Sledgehammer\is_closure($selector)) {
            $closure = $selector;
        } else {
            $closure = PropertyPath::compile($selector);
        }
        $min = null;
        $first = true;
        foreach ($this as $item) {
            $value = $closure($item);
            if ($first) {
                $first = false;
                $min = $value;
            } elseif ($value < $min) {
                $min = $value;
            }
        }

        return $min;
    }

    /**
     * Build a closure which validates an item with the gives $conditions.
     *
     * @param mixed $conditions array|Closure|expression  See Collection::where() for condition options
     *
     * @return callable
     */
    protected function buildFilter($conditions)
    {
        if (\Sledgehammer\is_closure($conditions)) {
            return $conditions;
        }
        if (is_array($conditions)) {
            // Create filter that checks all conditions
            $logicalOperator = \Sledgehammer\extract_logical_operator($conditions);
            if ($logicalOperator === false) {
                if (count($conditions) > 1) {
                    \Sledgehammer\notice('Conditions with multiple conditions require a logical operator.', "Example: array('AND', 'x' => 1, 'y' => 5)");
                }
                $logicalOperator = 'AND';
            } else {
                unset($conditions[0]);
            }
            $operators = [];
            foreach ($conditions as $path => $expectation) {
                if (preg_match('/^(.*) ('.\Sledgehammer\COMPARE_OPERATORS.')$/', $path, $matches)) {
                    unset($conditions[$path]);
                    $conditions[$matches[1]] = $expectation;
                    $operators[$matches[1]] = $matches[2];
                } else {
                    $operators[$path] = false;
                }
            }
            // @todo Build an optimized closure for when a single conditions is given.
            if ($logicalOperator === 'AND') {
                return function ($item) use ($conditions, $operators) {
                    foreach ($conditions as $path => $expectation) {
                        $actual = PropertyPath::get($path, $item);
                        $operator = $operators[$path];
                        if ($operator) {
                            if (\Sledgehammer\compare($actual, $operator, $expectation) === false) {
                                return false;
                            }
                        } elseif (\Sledgehammer\equals($actual, $expectation) === false) {
                            return false;
                        }
                    }

                    return true; // All conditions are met.
                };
            } elseif ($logicalOperator === 'OR') {
                return function ($item) use ($conditions, $operators) {
                    foreach ($conditions as $path => $expectation) {
                        $actual = PropertyPath::get($path, $item);
                        $operator = $operators[$path];
                        if ($operator) {
                            if (\Sledgehammer\compare($actual, $operator, $expectation) !== false) {
                                return true;
                            }
                        } elseif (\Sledgehammer\equals($actual, $expectation) !== false) {
                            return true;
                        }
                    }

                    return false; // None of conditions are met.
                };
            } else {
                throw new Exception('Unsupported logical operator "'.$logicalOperator.'", expecting "AND" or "OR"');
            }
        }
        //'<= 5' or '10'
        // Compare the item directly with value given as $condition.
        if (is_string($conditions) && preg_match('/^('.\Sledgehammer\COMPARE_OPERATORS.') (.*)$/', $conditions, $matches)) {
            $operator = $matches[1];
            $expectation = $matches[2];
        } else {
            $expectation = $conditions;
            $operator = '==';
        }

        return function ($value) use ($expectation, $operator) {
            return \Sledgehammer\compare($value, $operator, $expectation);
        };
    }

    /**
     * Return a new collection sorted by the given field in ascending order.
     *
     * @param string|Closure $selector
     * @param int            $method   The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
     *
     * @return Collection
     */
    public function orderBy($selector, $method = SORT_REGULAR)
    {
        $sortOrder = [];
        $items = [];
        $indexed = true;
        $counter = 0;
        if (\Sledgehammer\is_closure($selector)) {
            $closure = $selector;
        } else {
            $closure = PropertyPath::compile($selector);
        }
        // Collect values
        foreach ($this as $key => $item) {
            $items[$key] = $item;
            $sortOrder[$key] = $closure($item, $key);
            if ($key !== $counter) {
                $indexed = false;
            }
            ++$counter;
        }
        // Sort the values
        if ($method === SORT_NATURAL) {
            natsort($sortOrder);
        } elseif ($method === \Sledgehammer\SORT_NATURAL_CI) {
            natcasesort($sortOrder);
        } else {
            asort($sortOrder, $method);
        }
        $sorted = [];
        foreach (array_keys($sortOrder) as $key) {
            if ($indexed) {
                $sorted[] = $items[$key];
            } else { // Keep keys intact
                $sorted[$key] = $items[$key];
            }
        }

        return new self($sorted);
    }

    /**
     * Return a new collection sorted by the given field in descending order.
     *
     * @param string|Closure $selector
     * @param int            $method   The sorting method, options are: SORT_REGULAR, SORT_NUMERIC, SORT_STRING or SORT_NATURAL
     *
     * @return Collection
     */
    public function orderByDescending($selector, $method = SORT_REGULAR)
    {
        return $this->orderBy($selector, $method)->reverse();
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
        $this->dataToArray();

        return new self(array_slice($this->data, $offset));
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
        $this->dataToArray();

        return new self(array_slice($this->data, 0, $limit));
    }

    /**
     * Return a new collection in the reverse order.
     *
     * @return Collection
     */
    public function reverse()
    {
        $this->dataToArray();
        $preserveKeys = (\Sledgehammer\is_indexed($this->data) === false);

        return new self(array_reverse($this->data, $preserveKeys));
    }

    /**
     * Creates a new collection with all values converted by the callback.
     *
     * @link http://php.net/manual/function.array-map.php
     *
     * @param Closure$callback The callback is called per item and the return value is stored in the new collection.
     *
     * @return Collection
     */
    public function map($callback)
    {
        return new self(array_map($callback, $this->toArray()));
    }

    /**
     * Iteratively reduce the collection to a single value using a callback function.
     *
     * @link http://php.net/manual/function.array-reduce.php
     *
     * @param Closure $callback The callback is called per item and the return value is given as result to the next callback.
     * @param mixed   $initial  The inital value of the result.
     *
     * @return mixed
     */
    public function reduce($callback, $initial = null)
    {
        return array_reduce($this->toArray(), $callback, $initial);
    }

    /**
     * Return the collection as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return iterator_to_array($this);
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
        if (is_array($this->data)) {
            return count($this->data);
        }
        if ($this->data instanceof Countable) {
            return $this->data->count();
        }
        $this->dataToArray();

        return count($this->data);
    }

    /**
     * Inspect the internal query.
     *
     * @return mixed
     */
    public function getQuery()
    {
        throw new Exception('The getQuery() method is not supported by '.get_class($this));
    }

    /**
     * Change the internal query.
     *
     * @param mixed $query
     */
    public function setQuery($query)
    {
        throw new Exception('The setQuery() method is not supported by '.get_class($this));
    }

    /**
     * Clone a collection.
     */
    public function __clone()
    {
        $this->dataToArray();
    }

    /**
     * Whether a offset exists.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param int|string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->dataToArray();

        return array_key_exists($offset, $this->data);
    }

    /**
     * Offset to retrieve.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param int|string $offset
     *
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        $this->dataToArray();

        return $this->data[$offset];
    }

    /**
     * Offset to set.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param int|string $offset
     * @param mixed      $value
     */
    public function offsetSet($offset, $value)
    {
        $this->dataToArray();
        if ($offset === null) {
            $this->trigger('adding', $value, null, $this);
            $this->data[] = $value;
            $this->trigger('added', $value, null, $this);
        } else {
            $replacing = false;
            if (array_key_exists($offset, $this->data)) {
                $old = $this->data[$offset];
                if ($old === $value) { // No change?
                    return;
                }
                $this->trigger('removing', $old, $offset, $this);
                $replacing = true;
            }
            $this->trigger('adding', $value, $offset, $this);
            $this->data[$offset] = $value;
            if ($replacing) {
                $this->trigger('removed', $old, $offset, $this);
            }
            $this->trigger('added', $value, $offset, $this);
        }
    }

    /**
     * Offset to unset.
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param int string $offset
     */
    public function offsetUnset($offset)
    {
        $this->dataToArray();
        if (array_key_exists($offset, $this->data)) {
            $old = $this->data[$offset];
            $this->trigger('removing', $old, $offset, $this);
            unset($this->data[$offset]);
            $this->trigger('removed', $old, $offset, $this);
        } else {
            unset($this->data[$offset]);
        }
    }

    /**
     * Convert $this->data to an array.
     */
    protected function dataToArray()
    {
        if (is_array($this->data)) {
            return;
        }
        if ($this->data instanceof Iterator) {
            $this->data = iterator_to_array($this->data);

            return;
        }
        if ($this->data instanceof \Traversable) {
            $items = [];
            foreach ($this->data as $key => $item) {
                $items[$key] = $item;
            }
            $this->data = $items;

            return;
        }
        $type = gettype($this->data);
        $typeOrClass = ($type === 'object') ? get_class($this->data) : $type;
        throw new Exception(''.$typeOrClass.' is not an Traversable');
    }

    /**
     * @return Iterator
     */
    public function getIterator()
    {
        if ($this->data instanceof IteratorAggregate) {
            return $this->data->getIterator();
        }
        $this->dataToArray();
        if (is_array($this->data) === false) {
            throw new Exception('Failed to convert data to an array');
        }

        return new ArrayIterator($this->data);
    }
}
