<?php
/**
 * CollectionTests
 *
 */
namespace SledgeHammer;

class CollectionTest extends TestCase {

	function test_where() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		$vegetables = $fruitsAndVegetables->where(array('type' => 'vegetable'));
		$this->assertEquals(count($vegetables), 1, 'Only 1 vegetable in the collection');
		$this->assertEquals($vegetables[0]['name'], 'carrot');
		$this->assertEquals($vegetables->toArray(), array(
			array(
				'id' => '8',
				'name' => 'carrot',
				'type' => 'vegetable',
			)
		));
		$fruits = $fruitsAndVegetables->where(array('type' => 'fruit'));
		$this->assertEquals(count($fruits), 3, '3 fruits in the collection');
		$this->assertEquals($fruits[0]['name'], 'apple');
		$this->assertEquals($fruits->toArray(), array(
			array(
				'id' => '4',
				'name' => 'apple',
				'type' => 'fruit',
			),
			array(
				'id' => '6',
				'name' => 'pear',
				'type' => 'fruit',
			),
			array(
				'id' => '7',
				'name' => 'banana',
				'type' => 'fruit',
			),
		));
	}

	function test_select() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		// Simple select a single field
		$names = $fruitsAndVegetables->select('name');
		$this->assertEquals($names->toArray(), array(
			'apple',
			'pear',
			'banana',
			'carrot',
		));
		// The second parameter determines the key
		$list = $fruitsAndVegetables->select('name', '[id]');
		$this->assertEquals($list->toArray(), array(
			4 => 'apple',
			6 => 'pear',
			7 => 'banana',
			8 => 'carrot',
		));
		// Select multiple fields and create a new structure
		$struct = $fruitsAndVegetables->where(array('name' => 'banana'))->select(array('name' => '[name]', 'meta[id]' => 'id', 'meta[type]' => 'type'));
		$this->assertEquals($struct->toArray(), array(
			array(
				'name' => 'banana',
				'meta' => array(
					'id' => '7',
					'type' => 'fruit',
				),
			)
		));
	}

	function test_sorting() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		// indexed
		$abc = $fruitsAndVegetables->orderBy('name')->select('name');
		$this->assertEquals($abc->toArray(), array(
			'apple',
			'banana',
			'carrot',
			'pear',
		));
		$zxy = $fruitsAndVegetables->orderByDescending('name')->select('name');
		$this->assertEquals($zxy->toArray(), array(
			'pear',
			'carrot',
			'banana',
			'apple',
		));
	}

	function test_where_operators() {
		$numbers = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));
		$this->assertEquals(count($numbers->where('2')), 1);
		$this->assertEquals(count($numbers->where('<= 2')), 2);
		$this->assertEquals(count($numbers->where('> 5')), 5);
		$vehicels = new Collection(array(
			array('name' => 'car', 'wheels' => 4),
			array('name' => 'trike', 'wheels' => 3),
			array('name' => 'bike', 'wheels' => 2),
		));
		$this->assertEquals(count($vehicels->where(array('wheels >' => 3))), 1);
//		dump($vehicels->where(array('wheels >' => 3))->select('name')->offsetGet(0));
	}

	function test_compare() {
		$this->assertTrue(compare('asd', '==', 'asd'));
		$this->assertTrue(compare(2, '==', 2));
		$this->assertFalse(compare('asd', '==', 'AsD')); // But MySQL will evalutate this to true, depending on the collation
		$this->assertTrue(compare('1', '==', 1));
		$this->assertTrue(compare(null, '==', null));
		$this->assertTrue(compare(1, '>', null));
		$this->assertTrue(compare(0, '>=', null));
		$this->assertFalse(compare('', '==', 0));
		$this->assertFalse(compare(0, '>', null));
	}
	function test_database_where() {
		$fruits = $this->getDatabaseCollection();
		$this->assertEquals($fruits->toArray(), $this->getFruitsAndVegetables()->toArray()); // The contents of the database collections should identical to array based collection
		$this->assertEquals((string) $fruits->sql, 'SELECT * FROM fruits');
		$apple = $fruits->where(array('name' => 'apple'));
		$this->assertEquals($apple->count(), 1);
		$this->assertFalse(property_exists($apple, 'sql'), 'Filtering after the initial query is done in php (in the Collection class)');
				restore_error_handler();

		$apple = $this->getDatabaseCollection()->where(array('name' => 'apple'));
		$this->assertEquals($apple->count(), 1);
		$this->assertEquals((string) $apple->sql, "SELECT * FROM fruits WHERE name = 'apple'");

		$lowIds = $this->getDatabaseCollection()->where(array('id <=' => 6));
		$this->assertEquals($lowIds->count(), 2);
		$this->assertEquals((string) $lowIds->sql, "SELECT * FROM fruits WHERE id <= 6");
	}


	/**
	 * A collection containing fruit entries and a vegetable entry
	 * @return Collection
	 */
	private function getFruitsAndVegetables() {
		return new Collection(array(
				array('id' => '4', 'name' => 'apple', 'type' => 'fruit'),
				array('id' => '6', 'name' => 'pear', 'type' => 'fruit'),
				array('id' => '7', 'name' => 'banana', 'type' => 'fruit'),
				array('id' => '8', 'name' => 'carrot', 'type' => 'vegetable'),
			));
	}

	private function getDatabaseCollection() {
		if (empty($GLOBALS['SledgeHammer']['Databases'][__CLASS__])) {
			$db = new Database('sqlite::memory:');
			$db->query('CREATE TABLE fruits (
				id INTEGER PRIMARY KEY,
				name TEXT,
				type TEXT)');
			$GLOBALS['SledgeHammer']['Databases'][__CLASS__] = $db;
			$fruits = $this->getFruitsAndVegetables();
			foreach ($fruits as $fruit) {
				$db->query('INSERT INTO fruits VALUES ('.$fruit['id'].', '.$db->quote($fruit['name']).', '.$db->quote($fruit['type']).')');
			}
		}
		return new DatabaseCollection(select('*')->from('fruits'), __CLASS__);
	}

}

?>
