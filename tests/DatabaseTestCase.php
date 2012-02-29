<?php
/**
 * Breidt de UnitTestCase class uit met assert functies voor het controleren van queries en tabellen.
 */
namespace SledgeHammer;

abstract class DatabaseTestCase extends TestCase {

	protected
		$skipRebuildDatabase = false,
		$dbLink = '__NOT_CONNECTED__',
		$debug = true; // Als $debug op "true" staat worden er na een FAIL extra informatie gedumpt.
	private
		$dbName,
		$queryCount;

	function __construct($pdoDriver = 'sqlite') {
		parent::__construct();
		// Voorkom dat de default connectie gebruikt wordt.
		if (isset($GLOBALS['SledgeHammer']['Databases']['default'])) {
			$GLOBALS['SledgeHammer']['Databases']['default_backup'] = $GLOBALS['SledgeHammer']['Databases']['default'];
			$GLOBALS['SledgeHammer']['Databases']['default'] = 'INVALID';
		}
		if (ENVIRONMENT != 'development') {
			return;
		}

		if ($this->dbLink == '__NOT_CONNECTED__') {
			$parts = explode('\\', get_class($this));
			$class = preg_replace('/Tests$/', '', array_pop($parts)); // Classname without namespace and "Tests" suffix
			$this->dbName = 'TestDB_'.preg_replace('/[^0-9a-z_]*/i', '', $class); // Genereer databasenaam
			$this->dbLink = $this->dbName;

			switch ($pdoDriver) {

				case 'mysql':
					$this->dbLink .= '_'.$_SERVER['HTTP_HOST'];
					;
					$db = new Database('mysql://root@localhost');
					$db->reportWarnings = false;
					$db->query('DROP DATABASE IF EXISTS '.$this->dbName);
					$db->query('CREATE DATABASE '.$this->dbName);
					$db->query('USE '.$this->dbName);
					break;

				case 'sqlite':
					$db = new Database('sqlite::memory:');
					break;
				default:
					throw new \Exception('Unsupported pdoDriver');
			}
			$GLOBALS['SledgeHammer']['Databases'][$this->dbLink] = $db;
			if ($this->skipRebuildDatabase) {
				$this->fillDatabase($db);
				if ($pdoDriver === 'mysql') {
					$db->reportWarnings = true;
				}
			}
		}
	}

	function getTests() {
		if (ENVIRONMENT != 'development') {
			$this->fail('Skipping DatabaseTestCases tests in "'.ENVIRONMENT.'"');
			return array();
		}
		return parent::getTests();
	}

	/**
	 * The last test in the TestCase
	 */
	function test_cleanup() {
		// Restore default database connection
		if (isset($GLOBALS['SledgeHammer']['Databases']['default_backup'])) {
			$GLOBALS['SledgeHammer']['Databases']['default'] = $GLOBALS['Databases']['default_backup'];
			unset($GLOBALS['SledgeHammer']['Databases']['default_backup']);
		}
		// DROP test database
		$db = $this->getDatabase();
		if ($this->dbName && $db->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
			$db->query('DROP DATABASE '.$this->dbName);
		}
	}

	/**
	 * Shoud be used to fill the testdatabase with content (CREATEs and INSERTs)
	 */
	abstract function fillDatabase($database);

	/**
	 * @return Database
	 */
	function getDatabase() {
		return getDatabase($this->dbLink);
	}

	/**
	 * Controleer of de $sql query is uitgevoerd sinds de start van de test_*()
	 *
	 * @param string $sql
	 * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
	 * @return bool
	 */
	function assertQuery($sql, $message = NULL) {
		if ($message === NULL) {
			$message = 'SQL ['.$sql.'] should be executed';
		}
		$db = $this->getDatabase();
		$query_log = array_slice($db->log, $this->queryCount); // Haal de queries uit de query_log die sinds de setUp() van deze test_*() zijn uitgevoert
		$queries = array();
		foreach ($query_log as $row) {
			$queries[] = (string) $row['sql'];
		}
		foreach ($queries as $query) {
			if ($sql == $query) {
				$this->assertTrue(true, $message);
				return true;
			}
		}
		$this->fail($message);
		if ($this->debug) {
			dump($queries);
		}
		return false;
	}

	/**
	 * Controleert of de $sql query gelijk is aan de laatst uitgevoerde query
	 *
	 * @param string $sql
	 * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
	 * @return bool
	 */
	function assertLastQuery($sql, $message = NULL) {
		$db = $this->getDatabase();
		$query = $db->log[count($db->log) - 1];
		if ($sql == $query['sql']) {
			if ($message === NULL) {
				$message = 'SQL ['.$sql.'] is executed';
			}
			$this->assertTrue(true, $message);
			return true;
		} else {
			if ($message === NULL) {
				$message = 'Unexpected SQL ['.$query['sql'].'], expecting ['.$sql.']';
			}
			$this->fail($message);
			return false;
		}
	}

	/**
	 * Het aantal queries controleren.
	 *
	 * @param int $expectedCount  Het aantal queries dat tot nu toe is uitgevoerd. (Deze wordt voor elke test_*() gereset)
	 * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
	 */
	function assertQueryCount($expectedCount, $message = null) {
		$db = $this->getDatabase();
		$count = count($db->log) - $this->queryCount;
		if ($message === null) {
			$message = 'Number of queries ('.$count.') should match '.$expectedCount;
		}
		$this->assertEquals($count, $expectedCount, $message);
	}

	/**
	 * Controleert de inhoud van de tabel met de inhoud van de meegegeven array
	 *
	 * @param string $table
	 * @param array $expected_contents De verwachte inhoud van de tabel
	 * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
	 * @return bool
	 */
	function assertTableContents($table, $expected_contents, $message = NULL) {
		$db = $this->getDatabase();
		$table_contents = iterator_to_array($db->query('SELECT * FROM '.$table));
		if ($message === NULL) {
			$message = 'Table "'.$table.' should match contents. %s';
		}
		if ($this->assertEquals($expected_contents, $table_contents, $message)) {
			return true;
		}
		if ($this->debug) {
			dump($expected_contents);
			dump($table_contents);
		}
		return false;
	}

	function setUp() {
		$db = $this->getDatabase();
		//dump(iterator_to_array($db->query('SHOW DATABASES', null, 'Database')));
		if ($this->skipRebuildDatabase == false && $this->dbName) {
			switch ($db->getAttribute(\PDO::ATTR_DRIVER_NAME)) {

				case 'mysql':
					$reportWarnings = $db->reportWarnings;
					$db->reportWarnings = false;
					$db->query('DROP DATABASE '.$this->dbName);
					$db->query('CREATE DATABASE '.$this->dbName);
					$db->query('USE '.$this->dbName);
					$db->reportWarnings = $reportWarnings;
					break;

				case 'sqlite';
					unset($GLOBALS['SledgeHammer']['Databases'][$this->dbLink]);
					$newDb = new Database('sqlite::memory:');
					foreach ($db as $property => $value) {
						$newDb->$property = $value;
					}
					$db = $newDb;
					$GLOBALS['SledgeHammer']['Databases'][$this->dbLink] = $newDb;
					break;
			}
			$this->fillDatabase($db);
		}
		$this->queryCount = count($db->log);
	}

}

?>
