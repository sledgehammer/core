<?php
namespace Sledgehammer;
/**
 * SQLTests
 *
 * @package Core
 */
class SQLTest extends TestCase {

	function test_fluent_api() {
		$sql = select('*')
				->from('customers AS c')
				->innerJoin('orders', 'c.id = customer_id')
				->andWhere('c.id = 1');
		$sql->where[] = 'orders.id = 1';
		$this->assertEquals((string) $sql, 'SELECT * FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');
	}

	function test_property_api() {
		// Creating a structured SQL (Makes adding manipulation the query incode easy)
		$sql = new SQL();
		$sql->columns = array('*');
		$sql->setFrom('customers AS c');
		$sql->setJoin('orders', 'inner join', 'c.id = customer_id');
		$sql->where = array(
			'operator' => 'AND',
			'c.id = 1',
			'orders.id = 1',
		);
		$this->assertEquals((string) $sql, 'SELECT * FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');

		// Creating a from raw strings (makes it easy to generate the query you want)
		$sql = new SQL();
		$sql->columns = '*';
		$sql->tables = 'customers';
		$sql->where = 'id = 1';
		$this->assertEquals((string) $sql, 'SELECT * FROM customers WHERE id = 1');
	}

}

?>
