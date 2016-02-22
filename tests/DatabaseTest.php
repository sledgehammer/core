<?php

namespace SledgehammerTests\Core;

use PDO;
use Sledgehammer\Core\Database\Connection;
use Sledgehammer\Core\Database\Statement;

/**
 * Test Database behavior.
 */
class DatabaseTest extends DatabaseTestCase
{
    public function __construct()
    {
        parent::__construct();
//		parent::__construct('mysql');
//		parent::__construct('sqlite');
    }

    public function test_connect()
    {
        $dbDsn = new Connection('mysql:host=localhost', 'root', 'root');
        $dbUrl = new Connection('mysql://root:root@localhost');
        $this->assertTrue(true, 'No exceptions were thrown');
    }

    /**
     * An invalid query should generate an error.
     */
    public function test_invalid_query()
    {
        $this->setExpectedException('PHPUnit_Framework_Error_Notice');
        $db = Connection::instance($this->dbLink);
        $result = $db->exec('this is not even a query');
    }

    public function test_notice_on_truncated_data()
    {
        $db = Connection::instance($this->dbLink);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $this->markTestSkipped('Only available in MySQL');
        }
        $this->setExpectedException('PHPUnit_Framework_Error_Notice');
        $db->exec('INSERT INTO ducks (name) VALUES ("0123456789ABCDEF")');
    }

    public function test_fetch()
    {
        $db = Connection::instance($this->dbLink);
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

    public function test_count()
    {
        $db = Connection::instance($this->dbLink);
        $result = $db->query('SELECT * FROM ducks');
        $this->assertInstanceOf(Statement::class, $result);
        $this->assertEquals(count($result), 3); //, 'count() should return the number of rows found');
    }

    /**
     * @param Database $db
     *
     * @return Database
     */
    public function fillDatabase($db)
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
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
        $query->execute(array('Kwik'));
        $query->execute(array('Kwek'));
        $query->execute(array('Kwak'));
    }

    public function getTests()
    {
        if (\Sledgehammer\ENVIRONMENT != 'development') {
            $this->fail('Skipping DatabaseTestCases tests in "'.\Sledgehammer\ENVIRONMENT.'"');

            return [];
        }

        return parent::getTests();
    }
}
