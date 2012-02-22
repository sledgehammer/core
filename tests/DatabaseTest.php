<?php
/**
 * Test Database behaviour
 *
 * @package Core
 */
namespace SledgeHammer;

class DatabaseTest extends DatabaseTestCase {

	function __construct() {
		parent::__construct();
//		parent::__construct('mysql');
//		parent::__construct('sqlite');
	}

	function test_connect() {
		$dbDsn = new Database('mysql:host=localhost', 'root');
		$dbUrl = new Database('mysql://root@localhost');
	}

	function test_notice() {
		$db = $this->getDatabase();
		$this->expectError(true, 'An invalid query should generate an error');
		$result = $db->exec('this is not even a query');
		$this->assertEqual($result, false);
		if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql') {
			// MySQL reports truncated warnings
			$this->expectError();
			$result = $db->exec('INSERT INTO ducks (name) VALUES ("0123456789ABCDEF")');
		}
	}

	function test_fetch() {
		$db = $this->getDatabase();
		$this->assertEqual($db->fetchAll('SELECT * FROM ducks'), array(
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
		$this->assertEqual($kwik, array(
			'id' => '1',
			'name' => 'Kwik',
		));
		// Fetch value
		$this->assertEqual($db->fetchValue('SELECT name FROM ducks LIMIT 1'), 'Kwik');

		$this->expectError('Resultset has no columns, expecting 1 or more columns');
		$fail = $db->fetchValue('INSERT INTO ducks VALUES (90, "90")');
	}

	function test_count() {
		$db = $this->getDatabase();
		$result = $db->query('SELECT * FROM ducks');
		$this->assertIsA($result, 'SledgeHammer\PDOStatement');
		$this->assertEqual(count($result), 3); //, 'count() should return the number of rows found');
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
