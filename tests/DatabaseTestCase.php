<?php
/**
 * DatabaseTestCase
 */
namespace Sledgehammer;
/**
 * Add database specific assertions to the TestCase class
 * @package Core
 */
abstract class DatabaseTestCase extends TestCase {

	/**
	 * Disable the rebuilding of the database per test_*() method.
	 * @var bool
	 */
	protected $skipRebuildDatabase = false;

	/**
	 * Name of the database connection
	 * @var string
	 */
	protected $dbLink = '__NOT_CONNECTED__';

	/**
	 * Als $debug op "true" staat worden er na een FAIL extra informatie gedumpt.
	 * @var bool
	 */
	protected $debug = true;

	/**
	 * Name of the database "CREATE DATABASE $dbName"
	 * @var bool
	 */
	private $dbName;

	/**
	 * Number of queries at the start of the test_*()
	 * @var int
	 */
	private $queryCount;

	/**
	 * Constructor
	 * @param string $pdoDriver Choose between a sqlite of mysql database.
	 */
	function __construct($pdoDriver = 'sqlite') {
		parent::__construct();
		// Voorkom dat de default connectie gebruikt wordt.
		if (isset(Database::$instances['default'])) {
			Database::$instances['default_backup'] = Database::$instances['default'];
			Database::$instances['default'] = 'INVALID';
		}
		if (ENVIRONMENT !== 'phpunit') {
			return;
		}

		if ($this->dbLink == '__NOT_CONNECTED__') {
			$parts = explode('\\', get_class($this));
			$class = preg_replace('/Tests$/', '', array_pop($parts)); // Classname without namespace and "Tests" suffix
			$this->dbName = 'unittest_'.preg_replace('/[^0-9a-z_]*/i', '', $class); // Genereer databasenaam
			$this->dbLink = $this->dbName;

			switch ($pdoDriver) {

				case 'mysql':
					$this->dbLink .= '_'.$_SERVER['HTTP_HOST'];
					;
					$db = new Database('mysql://root@localhost', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
					$db->reportWarnings = false;
					$db->query('DROP DATABASE IF EXISTS '.$this->dbName);
					$db->query('CREATE DATABASE '.$this->dbName);
					$db->query('USE '.$this->dbName);
					break;

				case 'sqlite':
					$db = new Database('sqlite::memory:', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
					break;
				default:
					throw new \Exception('Unsupported pdoDriver');
			}
			Database::$instances[$this->dbLink] = $db;
			if ($this->skipRebuildDatabase) {
				$this->fillDatabase($db);
				if ($pdoDriver === 'mysql') {
					$db->reportWarnings = true;
				}
			}
		}
	}

	/**
	 * Only run database tests in development.
	 * (CREATE/DROP DATABASE rights on a production server is a bad idea)
	 *
	 * @return array
	 */
	function getTests() {
		if (ENVIRONMENT !== 'phpunit') {
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
		if (isset(Database::$instances['default_backup'])) {
			Database::$instances['default'] = Database::$instances['default_backup'];
			unset(Database::$instances['default_backup']);
		}
		// DROP test database
		$db = $this->getDatabase();
		if ($this->dbName && $db->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
			$db->query('DROP DATABASE '.$this->dbName);
		}
		$this->assertTrue(true, 'cleaned up');
	}

	/**
	 * Shoud be used to fill the testdatabase with content (CREATEs and INSERTs)
	 * @param Database $database
	 */
	abstract function fillDatabase($database);

	/**
	 * Get the test database instance.
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
		$entries = array_slice($db->logger->entries, $this->queryCount); // Haal de queries uit de query_log die sinds de setUp() van deze test_*() zijn uitgevoert
		$queries = array();
		foreach ($entries as $row) {
			$queries[] = (string) $row[0];
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
		$entry = $db->logger->entries[count($db->logger->entries) - 1][0];
		if ($sql == $entry) {
			if ($message === NULL) {
				$message = 'SQL ['.$sql.'] is executed';
			}
			$this->assertTrue(true, $message);
			return true;
		} else {
			if ($message === NULL) {
				$message = 'Unexpected SQL ['.$entry.'], expecting ['.$sql.']';
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
		$count = count($db->logger->entries) - $this->queryCount;
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
		$this->assertEquals($expected_contents, $table_contents, $message);
	}

	/**
	 * Setup the database environment.
	 */
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
					unset(Database::$instances[$this->dbLink]);
					$newDb = new Database('sqlite::memory:', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
					foreach ($db as $property => $value) {
						$newDb->$property = $value;
					}
					$db = $newDb;
					Database::$instances[$this->dbLink] = $newDb;
					break;
			}
			$this->fillDatabase($db);
		}
		$this->queryCount = count($db->logger->entries);
	}

}

?>
