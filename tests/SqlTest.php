<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Database\Sql;

/**
 * Unittest for the Sql query generator.
 */
class SqlTest extends TestCase
{
    public function test_method_chaining()
    {
        $sql = \Sledgehammer\select('c.id')
                ->from('customers AS c')
                ->innerJoin('orders', 'c.id = customer_id')
                ->andWhere('c.id = 1');
        $sql->where[] = 'orders.id = 1';
        $sql = $sql->column('o.amount');
        $this->assertEquals((string) $sql, 'SELECT c.id, o.amount FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');
    }

    public function test_property_api()
    {
        // Creating a structured SQL (Makes adding manipulation the query incode easy)
        $sql = new Sql();
        $sql->columns = array('*');
        $sql->setFrom('customers AS c');
        $sql->setJoin('orders', 'inner join', 'c.id = customer_id');
        $sql->where = array(
            'AND',
            'c.id = 1',
            'orders.id = 1',
        );
        $this->assertEquals((string) $sql, 'SELECT * FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');

        // Creating a from raw strings (makes it easy to generate the query you want)
        $sql = new Sql();
        $sql->columns = '*';
        $sql->tables = 'customers';
        $sql->where = 'id = 1';
        $this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE id = 1');
    }

    public function test_nested_conditions()
    {
        $sql = \Sledgehammer\select('*')->from('customers')->where(array('OR', 'bonus = 1', array('AND', 'special = 1', 'age < 12')));
        $this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE bonus = 1 OR (special = 1 AND age < 12)');

        $sql = \Sledgehammer\select('*')->from('customers')->orWhere(array('AND', 'bonus = 1', array('AND', 'special = 1', 'age < 12')));
        $this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE bonus = 1 AND special = 1 AND age < 12');

        $sql = \Sledgehammer\select('*')->from('customers')->where(array('bonus = 1'))->orWhere('special = 1')->andWhere('age < 12');
        $this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE (bonus = 1 OR special = 1) AND age < 12');

        $sql = \Sledgehammer\select('*')->from('customers');
        $sql->where = array(
            'AND', // ignored (only 1 condition)
            array(
                'OR',
                array(
                    'OR', 'a = 1', 'b = 2', // a nested OR inside an OR wouldn't be placed inside () because (a || b) || c == a || b || c  
                ),
                'c = 3',
                array(
                    'AND',
                    'd = 4',
                    array(
                        'AND', 'e = 5', 'f = 6', // a nested AND inside an AND wouldn't be placed inside () because  d && (e && f) == d && e && f
                    ),
                    array('OR'), // nodes with only a logical operator are skipped.
                    array('g = 7'), // nodes without logical operator, but containing only 1 condition are treated as a that condition.
                    array('OR', 'h = 8'), // nodes with only 1 condition are treated as a that condition. The swap between AND -> OR doesn't cause ()
                    array('OR', 'i = 9', 'j = 10'),
                ),
            ),
        );
        $this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE a = 1 OR b = 2 OR c = 3 OR (d = 4 AND e = 5 AND f = 6 AND g = 7 AND h = 8 AND (i = 9 OR j = 10))');
    }
}
