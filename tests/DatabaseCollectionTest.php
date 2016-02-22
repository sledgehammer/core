<?php

namespace SledgehammerTests\Core;

use PDO;
use Sledgehammer\Core\Database\Connection;
use Sledgehammer\Core\Database\DatabaseCollection;
use function Sledgehammer\select;

class DatabaseCollectionTest extends DatabaseTestCase
{
    protected $skipRebuildDatabase = true;

    /**
     * Fixture.
     *
     * @var array
     */
    private $fruitsAndVegetables = array(
        array('id' => '4', 'name' => 'apple', 'type' => 'fruit'),
        array('id' => '6', 'name' => 'pear', 'type' => 'fruit'),
        array('id' => '7', 'name' => 'banana', 'type' => 'fruit'),
        array('id' => '8', 'name' => 'carrot', 'type' => 'vegetable'),
    );

    public function fillDatabase($db)
    {
        $db->query('CREATE TABLE fruits (
			id INTEGER PRIMARY KEY,
			name TEXT,
			type TEXT)');

        foreach ($this->fruitsAndVegetables as $fruit) {
            $db->query('INSERT INTO fruits VALUES ('.$fruit['id'].', '.$db->quote($fruit['name']).', '.$db->quote($fruit['type']).')');
        }
    }

    public function test_where()
    {
        $fruits = $this->getDatabaseCollection();
        $this->assertEquals($fruits->toArray(), $this->fruitsAndVegetables); // The contents of the database collections should identical to array based collection
        $this->assertEquals((string) $fruits->getQuery(), 'SELECT * FROM fruits');
        $this->assertQueryCount(1);
        $this->assertLastQuery('SELECT * FROM fruits');
        $this->assertCount(4, $fruits);
        $this->assertQueryCount(1, 'Counting after the inital query is done in php');
        $apple = $fruits->where(array('name' => 'apple'));
        $this->assertEquals($apple->count(), 1);
        $this->assertQueryCount(1, 'Filtering after the initial query is done in php (in the Collection class)');

        $pear = $this->getDatabaseCollection()->where(array('name' => 'pear'));
        $this->assertEquals($pear->count(), 1);
        $this->assertQueryCount(2);
        $this->assertLastQuery("SELECT COUNT(*) FROM fruits WHERE name = 'pear'");
        $this->assertEquals((string) $pear->getQuery(), "SELECT * FROM fruits WHERE name = 'pear'");

        $lowIds = $this->getDatabaseCollection()->where(array('id <=' => 6));
        $this->assertQueryCount(2);
        $this->assertCount(2, $lowIds);
        $this->assertEquals('SELECT * FROM fruits WHERE id <= 6', (string) $lowIds->getQuery());

        $appleIsNotAVegatable = $this->getDatabaseCollection()->where(array('AND', 'name' => 'apple', 'type' => 'vegetable'));
        $this->assertCount(0, $appleIsNotAVegatable->toArray());
        $this->assertLastQuery("SELECT * FROM fruits WHERE name = 'apple' AND type = 'vegetable'");

        $appleOrVegatables = $this->getDatabaseCollection()->where(array('OR', 'name' => 'apple', 'type' => 'vegetable'));
        $this->assertCount(2, $appleOrVegatables->toArray());
        $this->assertLastQuery("SELECT * FROM fruits WHERE name = 'apple' OR type = 'vegetable'");

        $startingWithA = $this->getDatabaseCollection()->where(array('name LIKE' => 'a%'));
        $this->assertCount(1, $startingWithA->toArray());
        $this->assertLastQuery("SELECT * FROM fruits WHERE name LIKE 'a%'");
    }

    public function test_select()
    {
        $fruits = $this->getDatabaseCollection();
        $onlyName = $fruits->take(1)->select('name')->toArray();
        $this->assertEquals(array('apple'), $onlyName);
        $this->assertLastQuery('SELECT name FROM fruits LIMIT 1');
        $this->assertQueryCount(1);

        $nameWithKey = $fruits->take(1)->select('name', 'id')->toArray();
        $this->assertEquals(array(4 => 'apple'), $nameWithKey);
        $this->assertLastQuery('SELECT id, name FROM fruits LIMIT 1');
        $this->assertQueryCount(2);

        $firstPartial = $fruits->take(1)->select(array('name', 'type'))->toArray();
        $this->assertEquals(array(array('apple', 'fruit')), $firstPartial);
        $this->assertLastQuery('SELECT name AS `0`, type AS `1` FROM fruits LIMIT 1');
        $this->assertQueryCount(3);

        $firstPartialWithKey = $fruits->take(1)->select(array('name', 'type'), 'id')->toArray();
        $this->assertEquals(array(4 => array('apple', 'fruit')), $firstPartialWithKey);
        $this->assertLastQuery('SELECT id, name, type FROM fruits LIMIT 1');
        $this->assertQueryCount(4);

        $firstPartialWithKey2 = $fruits->take(1)->select(array('name', 'type'), 'name')->toArray();
        $this->assertEquals(array('apple' => array('apple', 'fruit')), $firstPartialWithKey2);
        $this->assertLastQuery('SELECT name, type FROM fruits LIMIT 1');
        $this->assertQueryCount(5);

        $onlyNameLazy = $fruits->take(1)->select(array('name', 'type'))->select(0)->toArray();
        $this->assertEquals(array('apple'), $onlyNameLazy);
        $this->assertLastQuery('SELECT name AS `0` FROM fruits LIMIT 1');
        $this->assertQueryCount(6, 'select() doesn\'t excute the generated query directly and can be reduced futher');
    }

    public function test_count()
    {
        $collection = $this->getDatabaseCollection();
        $this->assertEquals(4, count($collection));
        $this->assertLastQuery('SELECT COUNT(*) FROM fruits');
        $this->assertEquals(4, count($collection->toArray()));
        $this->assertLastQuery('SELECT * FROM fruits');
    }

    public function test_escaped_where()
    {
        $collection = $this->getDatabaseCollection();
        $emptyCollection = $collection->where(array('name' => "'"));
        $this->assertEquals(count($emptyCollection->toArray()), 0);
        if (Connection::instance($this->dbLink)->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->assertLastQuery("SELECT * FROM fruits WHERE name = ''''");
        } else {
            $this->assertLastQuery("SELECT * FROM fruits WHERE name = '\''");
        }
        $this->assertEquals((string) $collection->getQuery(), 'SELECT * FROM fruits', 'Collection->where() does not	modify the orginal collection');
    }

    public function test_unescaped_where()
    {
        $collection = $this->getDatabaseCollection();
        $collection->setQuery($collection->getQuery()->andWhere("name LIKE 'B%'")); // Direct modification of the $collection
        $this->assertEquals(count($collection->toArray()), 1);
        $this->assertLastQuery("SELECT * FROM fruits WHERE name LIKE 'B%'");
    }

    public function test_min()
    {
        $collection = $this->getDatabaseCollection();
        $this->assertEquals(4, $collection->min('id'));
        $this->assertLastQuery('SELECT MIN(id) FROM fruits');
    }

    public function test_max()
    {
        $collection = $this->getDatabaseCollection();
        $this->assertEquals(7, $collection->where(array('type' => 'fruit'))->max('id'));
        $this->assertLastQuery("SELECT MAX(id) FROM fruits WHERE type = 'fruit'");
    }

    /**
     * A collection containing fruit entries and a vegetable entry.
     *
     * @return DatabaseCollection
     */
    private function getDatabaseCollection()
    {
        return new DatabaseCollection(select('*')->from('fruits'), $this->dbLink);
    }
}
