<?php
/**
 * SqlTests
 */
namespace Sledgehammer;
/**
 * Unittest for the Sql query generator.
 *
 * @package Core
 */
class SqlTest extends TestCase {

	function test_method_chaining() {
		$sql = select('c.id')
			->from('customers AS c')
			->innerJoin('orders', 'c.id = customer_id')
			->andWhere('c.id = 1');
		$sql->where[] = 'orders.id = 1';
		$sql = $sql->column('o.amount');
		$this->assertEquals((string) $sql, 'SELECT c.id, o.amount FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');
	}

	function test_property_api() {
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

	function test_nested_conditions() {
		$sql = select('*')->from('customers')->where(array('OR', 'bonus = 1', array('AND', 'special = 1', 'age < 12')));
		$this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE bonus = 1 OR (special = 1 AND age < 12)');

		$sql = select('*')->from('customers')->orWhere(array('AND', 'bonus = 1', array('AND', 'special = 1', 'age < 12')));
		$this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE bonus = 1 AND special = 1 AND age < 12');

		$sql = select('*')->from('customers')->where(array('bonus = 1'))->orWhere('special = 1')->andWhere('age < 12');
		$this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE (bonus = 1 OR special = 1) AND age < 12');
	}

}

?>
