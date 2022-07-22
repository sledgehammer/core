<?php

/**
 * Statement.
 */

namespace Sledgehammer\Core\Database;

use PDOStatement;
use Countable;

/**
 * PDOStatement override
 * Adds Countable to the Database results.
 */
class Statement extends PDOStatement implements Countable
{
    /**
     * Return the number of rows in the result
     * (Slow on SQlite databases).
     */
    public function count(): int
    {
        $count = $this->rowCount();
        if ($count !== 0) {
            return $count; // Return the rowCount (num_rows in MySQL)
        }
        // SQLite returns 0 (no affected rows)
        return count($this->fetchAll());
    }
}
