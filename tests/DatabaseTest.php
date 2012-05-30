<?php
/**
 * Test Database behaviour
 *
 * @package Core
 */
namespace Sledgehammer;

class DatabaseTest extends DatabaseTestCase {

	function __construct() {
		parent::__construct();
//		parent::__construct('mysql');
//		parent::__construct('sqlite');
	}

	function test_connect() {
		$dbDsn = new Database('mysql:host=localhost', 'root', 'root');
		$dbUrl = new Database('mysql://root:root@localhost');
		$this->assertTrue(true, 'No exceptions were thrown');
	}

	/**
	 * An invalid query should generate an error
	 */
	function test_invalid_query() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice');
		$db = $this->getDatabase();
		$result = $db->exec('this is not even a query');
	}

	function test_notice_on_truncated_data() {
		$db = $this->getDatabase();
		if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
			$this->markTestSkipped('Only available in MySQL');
		}
		$this->setExpectedException('PHPUnit_Framework_Error_Notice');
		$db->exec('INSERT INTO ducks (name) VALUES ("0123456789ABCDEF")');
	}

	function test_fetch() {
		$db = $this->getDatabase();
		$this->assertEquals($db->fetchAll('SELECT * FROM ducks'), array(
			array(
				'id' => '1',
				'name' => 'Kwik',
			),
			array(
				'id' => '2',
				'name' => 'Kwek',
			),
			array(
				'id' => '3',
				'name' => 'Kwak',
			),
		));
		// Fetch row
		$kwik = $db->fetchRow('SELECT * FROM ducks LIMIT 1');
		$this->assertEquals($kwik, array(
			'id' => '1',
			'name' => 'Kwik',
		));
		// Fetch value
		$this->assertEquals($db->fetchValue('SELECT name FROM ducks LIMIT 1'), 'Kwik');

		$this->setExpectedException('PHPUnit_Framework_Error_Warning', 'Resultset has no columns, expecting 1 or more columns');
		$db->fetchValue('INSERT INTO ducks VALUES (90, "90")');
	}

	function test_count() {
		$db = $this->getDatabase();
		$result = $db->query('SELECT * FROM ducks');
		$this->assertInstanceOf('Sledgehammer\PDOStatement', $result);
		$this->assertEquals(count($result), 3); //, 'count() should return the number of rows found');
	}

	/**
	 * @param Database $db
	 * @return Database
	 */
	function fillDatabase($db) {
		if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
			$db->reportWarnings = false;
			$db->query('CREATE TABLE ducks (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				name VARCHAR(10) NOT NULL
			)');
			$db->reportWarnings = true;
		} else {
			$db->query('CREATE TABLE ducks (
				id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
				name TEXT(10) NOT NULL
			)');
		}
		$query = $db->prepare('INSERT INTO ducks (name) VALUES (?)');
		$query->execute(array("Kwik"));
		$query->execute(array("Kwek"));
		$query->execute(array("Kwak"));
	}

	function getTests() {
		if (ENVIRONMENT != 'development') {
			$this->fail('Skipping DatabaseTestCases tests in "'.ENVIRONMENT.'"');
			return array();
		}
		return parent::getTests();
	}

}

?>
