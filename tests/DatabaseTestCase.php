<?php

namespace SledgehammerTests\Core;

use Exception;
use PDO;
use Sledgehammer\Core\Database\Connection;

/**
 * Add database specific assertions to the TestCase class.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * Disable the rebuilding of the database per test_*() method.
     *
     * @var bool
     */
    protected $skipRebuildDatabase = false;

    /**
     * Name of the database connection.
     *
     * @var string
     */
    protected $dbLink = '__NOT_CONNECTED__';

    /**
     * Als $debug op "true" staat worden er na een FAIL extra informatie gedumpt.
     *
     * @var bool
     */
    protected $debug = true;

    /**
     * Name of the database "CREATE DATABASE $dbName".
     *
     * @var bool
     */
    private $dbName;

    /**
     * Number of queries at the start of the test_*().
     *
     * @var int
     */
    private $queryCount;

    /**
     * Constructor.
     *
     * @param string $pdoDriver Choose between a sqlite of mysql database.
     */
    public function __construct($pdoDriver = 'sqlite')
    {
        parent::__construct();
        // Voorkom dat de default connectie gebruikt wordt.
        if (isset(Connection::$instances['default'])) {
            Connection::$instances['_default_backup'] = Connection::$instances['default'];
            Connection::$instances['default'] = 'INVALID';
        }

        // if (\Sledgehammer\ENVIRONMENT !== 'phpunit') {
        //     return;
        // }

        if ($this->dbLink == '__NOT_CONNECTED__') {
            $parts = explode('\\', get_class($this));
            $class = preg_replace('/Tests$/', '', array_pop($parts)); // Classname without namespace and "Tests" suffix
            $this->dbName = 'unittest_'.preg_replace('/[^0-9a-z_]*/i', '', $class); // Genereer databasenaam
            $this->dbLink = $this->dbName;

            switch ($pdoDriver) {
                case 'mysql':
                    $this->dbLink .= '_'.$_SERVER['HTTP_HOST'];
                    $db = new Connection('mysql://root:root@localhost', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
                    $db->reportWarnings = false;
                    $db->query('DROP DATABASE IF EXISTS '.$this->dbName);
                    $db->query('CREATE DATABASE '.$this->dbName);
                    $db->query('USE '.$this->dbName);
                    break;

                case 'sqlite':
                    $db = new Connection('sqlite::memory:', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
                    break;
                default:
                    throw new Exception('Unsupported pdoDriver');
            }
            Connection::$instances[$this->dbLink] = $db;
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
     * (CREATE/DROP DATABASE rights on a production server is a bad idea).
     *
     * @return array
     */
    public function getTests()
    {
        if (\Sledgehammer\ENVIRONMENT !== 'phpunit') {
            $this->fail('Skipping DatabaseTestCases tests in "'.\Sledgehammer\ENVIRONMENT.'"');

            return [];
        }

        return parent::getTests();
    }

    /**
     * The last test in the TestCase.
     */
    public function test_cleanup()
    {
        // DROP test database
        $db = Connection::instance($this->dbLink);
        if ($this->dbName && $db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $db->query('DROP DATABASE '.$this->dbName);
        }
        // Restore default database connection backup
        if (isset(Connection::$instances['default']) && Connection::$instances['default'] === 'INVALID') {
            Connection::$instances['default'] = Connection::$instances['_default_backup'];
            unset(Connection::$instances['_default_backup']);
        }
        $this->assertTrue(true, 'cleaned up');
    }

    /**
     * Shoud be used to fill the testdatabase with content (CREATEs and INSERTs).
     *
     * @param Database $database
     */
    abstract public function fillDatabase($database);

    /**
     * Controleer of de $sql query is uitgevoerd sinds de start van de test_*().
     *
     * @param string      $sql
     * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
     *
     * @return bool
     */
    public function assertQuery($sql, $message = null)
    {
        if ($message === null) {
            $message = 'SQL ['.$sql.'] should be executed';
        }
        $db = Connection::instance($this->dbLink);
        $entries = array_slice($db->logger->entries, $this->queryCount); // Haal de queries uit de query_log die sinds de setUp() van deze test_*() zijn uitgevoert
        $queries = [];
        foreach ($entries as $row) {
            $queries[] = (string) $row[0];
        }
        foreach ($queries as $query) {
            if ($sql == $query) {
                $this->assertTrue(true, $message);

                return true;
            }
        }
        if ($this->debug) {
            \Sledgehammer\dump($queries);
        }
        $this->fail($message);
    }

    /**
     * Controleert of de $sql query gelijk is aan de laatst uitgevoerde query.
     *
     * @param string      $sql
     * @param null|string $message Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
     *
     * @return bool
     */
    public function assertLastQuery($sql, $message = null)
    {
        $db = Connection::instance($this->dbLink);
        $entry = $db->logger->entries[count($db->logger->entries) - 1][0];
        if ($sql == $entry) {
            if ($message === null) {
                $message = 'SQL ['.$sql.'] is executed';
            }
            $this->assertTrue(true, $message);

            return true;
        } else {
            if ($message === null) {
                $message = 'Unexpected SQL ['.$entry.'], expecting ['.$sql.']';
            }
            $this->fail($message);

            return false;
        }
    }

    /**
     * Het aantal queries controleren.
     *
     * @param int         $expectedCount Het aantal queries dat tot nu toe is uitgevoerd. (Deze wordt voor elke test_*() gereset)
     * @param null|string $message       Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
     */
    public function assertQueryCount($expectedCount, $message = null)
    {
        $db = Connection::instance($this->dbLink);
        $count = count($db->logger->entries) - $this->queryCount;
        if ($message === null) {
            $message = 'Number of queries ('.$count.') should match '.$expectedCount;
        }
        $this->assertSame($count, $expectedCount, $message);
    }

    /**
     * Controleert de inhoud van de tabel met de inhoud van de meegegeven array.
     *
     * @param string      $table
     * @param array       $expected_contents De verwachte inhoud van de tabel
     * @param null|string $message           Het bericht dat op de testpagina getoond wordt (met een PASS of FAIL ervoor)
     *
     * @return bool
     */
    public function assertTableContents($table, $expected_contents, $message = null)
    {
        $db = Connection::instance($this->dbLink);
        $table_contents = iterator_to_array($db->query('SELECT * FROM '.$table));
        if ($message === null) {
            $message = 'Table "'.$table.' should match contents. %s';
        }
        $this->assertSame($expected_contents, $table_contents, $message);
    }

    /**
     * Setup the database environment.
     */
    public function setUp(): void
    {
        $db = Connection::instance($this->dbLink);
        //dump(iterator_to_array($db->query('SHOW DATABASES', null, 'Database')));
        if ($this->skipRebuildDatabase == false && $this->dbName) {
            switch ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'mysql':
                    $reportWarnings = $db->reportWarnings;
                    $db->reportWarnings = false;
                    $db->query('DROP DATABASE '.$this->dbName);
                    $db->query('CREATE DATABASE '.$this->dbName);
                    $db->query('USE '.$this->dbName);
                    $db->reportWarnings = $reportWarnings;
                    break;

                case 'sqlite';
                    Connection::$instances[$this->dbLink] = 'CLEAR';
                    $newDb = new Connection('sqlite::memory:', null, null, array('logIdentifier' => substr($this->dbLink, 9)));
                    foreach ($db as $property => $value) {
                        $newDb->$property = $value;
                    }
                    $db = $newDb;
                    Connection::$instances[$this->dbLink] = $newDb;
                    break;
            }
            $this->fillDatabase($db);
        }
        $this->queryCount = count($db->logger->entries);
    }
}
