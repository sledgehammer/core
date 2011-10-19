<?php
/**
 * Test Database behaviour
 */
namespace SledgeHammer;

class DatabaseTests extends DatabaseTestCase {

	function donttest_connect() {
		$dbDsn = new Database('mysql:host=localhost', 'root');
		$dbUrl = new Database('mysql://root@localhost');
	}

	function test_notice() {
		$db = $this->getDatabase();
		$this->expectError(true, 'An invalid query should generate an error');
		$result = $db->exec('this is not even a query');
		$this->assertEqual($result, false);
		$this->expectError();
		$result = $db->exec('INSERT INTO ducks (name) VALUES ("0123456789ABCDEF")');
	}

	function test_fetch() {
		$db = $this->getDatabase();
		// Fetch row
		$kwik = $db->fetchRow('SELECT * FROM ducks LIMIT 1');
		$this->assertEqual($kwik, array(
			'id' => '1',
			'name' => 'Kwik',
		));
		$this->assertEqual($db->fetchValue('SELECT name FROM ducks LIMIT 1'), 'Kwik');

		$this->expectError('Resultset has no columns, expecting 1 or more columns');
		$fail = $db->fetchValue('INSERT INTO ducks VALUES (90, "90")');
	}

	/**
	 * @param Database $db
	 * @return Database
	 */
	function fillDatabase($db) {
		$db->reportWarnings = false;
		$x = $db->query('CREATE TABLE ducks (
			id INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			name VARCHAR(10) NOT NULL
		)');
		$db->reportWarnings = true;
		$db->query('INSERT INTO ducks (name) VALUES ("Kwik"), ("Kwak"), ("Kwek")');
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
