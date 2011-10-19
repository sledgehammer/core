<?php
/**
 * Breidt de UnitTestCase class uit met assert functies voor het controleren van queries en tabellen.
 */
namespace SledgeHammer;
abstract class DatabaseTestCase extends \UnitTestCase {

	protected
		$skipRebuildDatabase = false,
		$dbLink = '__NOT_CONNECTED__',
		$debug = true; // Als $debug op "true" staat worden er na een FAIL extra informatie gedumpt.

	private 
		$dbName,
		$queryCount;

	function __construct() {
		parent::__construct();
		// Voorkom dat de default connectie gebruikt wordt.
		if (isset($GLOBALS['Databases']['default'])) {
			$GLOBALS['Databases']['default_backup'] = $GLOBALS['Databases']['default'];
			unset($GLOBALS['Databases']['default']);
		}
		if(ENVIRONMENT != 'development') {
			return;
		}
			
		if ($this->dbLink == '__NOT_CONNECTED__') {
			$db = new Database('mysql://root@localhost');
			$host = php_uname('n');
			$suffix = preg_replace('/[^0-9a-z]*/i', '', '_'.$_SERVER['HTTP_HOST']);
			$this->dbName = 'UnitTestDB_'.$suffix; // Genereer databasenaam
			$this->dbLink = $this->dbName;
			$db->reportWarnings = false;
			$db->query('DROP DATABASE IF EXISTS '.$this->dbName);
			$db->query('CREATE DATABASE '.$this->dbName);
			$db->query('USE '.$this->dbName);
			$GLOBALS['Databases'][$this->dbName] = $db;
			if ($this->skipRebuildDatabase) {
				$this->fillDatabase($db);
				$db->reportWarnings = true;

			}
		}
	}
	
	function getTests() {
		if(ENVIRONMENT != 'development') {
			$this->fail('Skipping DatabaseTestCases tests in "'.ENVIRONMENT.'"');
			return array();
		}
		return parent::getTests();
	}

	/**
	 * Laatste test in de UnitTest
	 */
	function test_debug() {
		if (isset($GLOBALS['Databases']['default_backup'])) {
			$GLOBALS['Databases']['default'] = $GLOBALS['Databases']['default_backup'];
			unset($GLOBALS['Databases']['default_backup']);
		}
		$db = $this->getDatabase();
		if ($this->debug) {
			$db->debug();
			echo '<br />';
		}
		if ($this->dbName) {
			$db->query('DROP DATABASE '.$this->dbName);
		}
	}

	/**
	 * 
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
		$query_log = array_slice($db->query_log, $this->queryCount); // Haal de queries uit de query_log die sinds de setUp() van deze test_*() zijn uitgevoert
		$queries = array();
		foreach ($query_log as $row) {
			$queries[] = (string) $row['sql'];
		}
		foreach ($queries as $query) {
			if ($sql == $query) {
				$this->pass($message);
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
		$query =  $db->query_log[count($db->query_log) - 1];
		if ($sql == $query['sql']) {
			if ($message === NULL) {
				$message = 'SQL ['.$sql.'] is executed';
			}
			$this->pass($message);
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
		$count = count($db->query_log) - $this->queryCount;
		if ($message === null) {
			$message = 'Number of queries ('.$count.') should match '.$expectedCount;
		}
		$this->assertEqual($count, $expectedCount, $message);
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
		if ($this->assertEqual($expected_contents, $table_contents, $message)) {
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
			$db->query('DROP DATABASE '.$this->dbName);
			$db->query('CREATE DATABASE '.$this->dbName);
			$db->query('USE '.$this->dbName);
			$this->fillDatabase($db);
		}
		$this->queryCount = count($db->log);
	}
}
?>
