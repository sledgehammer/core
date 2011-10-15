<?php
/**
 * CollectionTests
 *
 */
namespace SledgeHammer;

class CollectionTests extends \UnitTestCase {

	function test_where() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		$vegetables = $fruitsAndVegetables->where(array('type' => 'vegetable'));
		$this->assertEqual(count($vegetables), 1, 'Only 1 vegetable in the collection');
		$this->assertEqual($vegetables[0]['name'], 'carrot');
		$this->assertEqual($vegetables->toArray(), array(
			array(
				'id' => 8,
				'name' => 'carrot',
				'type' => 'vegetable',
			)
		));
		$fruits = $fruitsAndVegetables->where(array('type' => 'fruit'));
		$this->assertEqual(count($fruits), 3, '3 fruits in the collection');
		$this->assertEqual($fruits[0]['name'], 'apple');
		$this->assertEqual($fruits->toArray(), array(
			array(
				'id' => 4,
				'name' => 'apple',
				'type' => 'fruit',
			),
			array(
				'id' => 6,
				'name' => 'pear',
				'type' => 'fruit',
			),
			array(
				'id' => 7,
				'name' => 'banana',
				'type' => 'fruit',
			),
		));
	}

	function test_select() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		// Simple select a single field
		$names = $fruitsAndVegetables->select('name');
		$this->assertEqual($names->toArray(), array(
			'apple',
			'pear',
			'banana',
			'carrot',
		));
		// The second parameter determines the key
		$list = $fruitsAndVegetables->select('name', '[id]');
		$this->assertEqual($list->toArray(), array(
			4 => 'apple',
			6 => 'pear',
			7 => 'banana',
			8 => 'carrot',
		));
		// Select multiple fields and create a new structure
		$struct = $fruitsAndVegetables->where(array('name' => 'banana'))->select(array('name' => '[name]', 'meta[id]' => 'id', 'meta[type]' => 'type'));
		$this->assertEqual($struct->toArray(), array(
			array(
				'name' => 'banana',
				'meta' => array(
					'id' => 7,
					'type' => 'fruit',
				),
			)
		));
	}

	function test_sorting() {
		$fruitsAndVegetables = $this->getFruitsAndVegetables();
		// indexed
		$abc = $fruitsAndVegetables->orderBy('name')->select('name');
		$this->assertEqual($abc->toArray(), array(
			'apple',
			'banana',
			'carrot',
			'pear',
		));
		$zxy = $fruitsAndVegetables->orderByDescending('name')->select('name');
		$this->assertEqual($zxy->toArray(), array(
			'pear',
			'carrot',
			'banana',
			'apple',
		));
	}

	/**
	 * A collection containing fruit entries and a vegetable entry
	 * @return Collection
	 */
	private function getFruitsAndVegetables() {
		return new Collection(array(
				array('id' => 4, 'name' => 'apple', 'type' => 'fruit'),
				array('id' => 6, 'name' => 'pear', 'type' => 'fruit'),
				array('id' => 7, 'name' => 'banana', 'type' => 'fruit'),
				array('id' => 8, 'name' => 'carrot', 'type' => 'vegetable'),
			));
	}

}

?>
