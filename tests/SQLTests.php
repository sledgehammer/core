<?php
/**
 * SQLTests
 *
 * @package Core
 */
namespace SledgeHammer;

class SQLTests extends \SledgeHammer\TestCase {

	function test_fluent_api() {
		$sql = select('*')
		    ->from('customers AS c')
			->innerJoin('orders', 'c.id = customer_id')
			->andWhere('c.id = 1');
		$sql->where[] = 'orders.id = 1';
		$this->assertEqual((string)$sql, 'SELECT * FROM customers AS c INNER JOIN orders ON (c.id = customer_id) WHERE c.id = 1 AND orders.id = 1');
	}
}

?>
