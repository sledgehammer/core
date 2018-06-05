<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Collection;
use stdClass;

class CollectionTest extends TestCase
{
    public function test_where()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        $vegetables = $fruitsAndVegetables->where(array('type' => 'vegetable'));
        $this->assertSame(count($vegetables), 1, 'Only 1 vegetable in the collection');
        $this->assertSame($vegetables[0]['name'], 'carrot');
        $this->assertSame($vegetables->toArray(), array(
            array(
                'id' => '8',
                'name' => 'carrot',
                'type' => 'vegetable',
            ),
        ));
        $fruits = $fruitsAndVegetables->where(array('type' => 'fruit'));
        $this->assertSame(count($fruits), 3, '3 fruits in the collection');
        $this->assertSame($fruits[0]['name'], 'apple');
        $this->assertSame($fruits->toArray(), array(
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
        $andWhere = $fruitsAndVegetables->where(array('AND', 'type' => 'fruit', 'id <=' => 4));
        $this->assertCount(1, $andWhere);

        $orWhere = $fruitsAndVegetables->where(array('OR', 'type' => 'fruit', 'id' => 8));
        $this->assertCount(4, $orWhere);
    }

    public function test_select()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        // Simple select a single field
        $names = $fruitsAndVegetables->select('name');
        $this->assertSame($names->toArray(), array(
            'apple',
            'pear',
            'banana',
            'carrot',
        ));
        // The second parameter determines the key
        $list = $fruitsAndVegetables->select('name', '[id]');
        $this->assertSame($list->toArray(), array(
            4 => 'apple',
            6 => 'pear',
            7 => 'banana',
            8 => 'carrot',
        ));
        // Select multiple fields and create a new structure
        $struct = $fruitsAndVegetables->where(array('name' => 'banana'))->select(array('name' => '[name]', 'meta[id]' => 'id', 'meta[type]' => 'type'));
        $this->assertSame($struct->toArray(), array(
            array(
                'name' => 'banana',
                'meta' => array(
                    'id' => '7',
                    'type' => 'fruit',
                ),
            ),
        ));
    }

    public function test_orderBy()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        // indexed
        $abc = $fruitsAndVegetables->orderBy('name')->select('name');
        $this->assertSame($abc->toArray(), array(
            'apple',
            'banana',
            'carrot',
            'pear',
        ));
        $zyx = $fruitsAndVegetables->orderByDescending('name')->select('name');
        $this->assertSame($zyx->toArray(), array(
            'pear',
            'carrot',
            'banana',
            'apple',
        ));
    }

    public function test_selectKey()
    {
        $xyz = new Collection(array('x' => 10, 'y' => 20, 'z' => 30));
        $this->assertSame(array(10, 20, 30), $xyz->selectKey(null)->toArray(), 'null should return an index array.');
        $this->assertSame(array(10 => 10, 20 => 20, 30 => 30), $xyz->selectKey('.')->toArray(), 'Using a path as key.');
        $closure = function ($item, $key) {
            return $key.$item;
        };
        $this->assertSame(array('x10' => 10, 'y20' => 20, 'z30' => 30), $xyz->selectKey($closure)->toArray(), 'Using a closure as key.');
    }

    public function test_where_operators()
    {
        $numbers = new Collection(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10));
        $this->assertCount(1, $numbers->where('2'));
        $this->assertCount(2, $numbers->where('<= 2'));
        $this->assertCount(5, $numbers->where('> 5'));
        $vehicles = new Collection(array(
            array('name' => 'car', 'wheels' => 4),
            array('name' => 'trike', 'wheels' => 3),
            array('name' => 'bike', 'wheels' => 2),
        ));
        $this->assertCount(1, $vehicles->where(array('wheels >' => 3)));
        $this->assertCount(2, $vehicles->where(array('name LIKE' => '%ke')));
    }

    public function test_events()
    {
        $removedCount = 0;
        $collection = $this->getFruitsAndVegetables();
        $collection->on('removed', function ($item, $index) use (&$removedCount) {
            ++$removedCount;
        });

        unset($collection[3]);
        $this->assertSame(1, $removedCount, 'Removing an item should trigger a "removed" event');
        unset($collection[3]);
        $this->assertSame(1, $removedCount, 'An unset that wasn\'t set should NOT trigger a "removed" event');

        $addedCount = 0;
        $collection->on('added', function ($item, $index) use (&$addedCount) {
            ++$addedCount;
        });
        $collection['Hi'] = 'New value';
        $this->assertSame(1, $addedCount, 'Adding an item should trigger a "added" event');
        $collection['Hi'] = 'Another value';
        $this->assertSame(2, $removedCount, 'Replacing an item should trigger a "removed" event');
        $this->assertSame(2, $addedCount, 'Replacing an item should trigger a "added" event');
    }

    public function test_indexof()
    {
        $object1 = new stdClass();
        $object2 = new stdClass();
        $collection = new Collection(array(10, 20, array('id' => 30), $object1, $object2));
        $this->assertSame(1, $collection->indexOf(20));
        $this->assertSame(2, $collection->indexOf(array('id?' => 30)));
        $this->assertSame(4, $collection->indexOf($object2));
    }

    public function test_remove()
    {
        $object = new stdClass();
        $collection = new Collection(array(10, array('id' => 20), $object));
        $collection->remove($object);
        $this->assertSame(array(10, array('id' => 20)), $collection->toArray());
        $collection->remove(function ($item) {
            return $item == 10;
        });
        $this->assertSame(array(array('id' => 20)), $collection->toArray());
//		$this->assertSame(2, $collection->indexOf(array('id?' => 30)));
    }

    public function test_remove_odd_in_foreach()
    {
        $collection = new Collection(array(1, 2, 3, 4));
        $i = 0;
        foreach ($collection as $entry) {
            if ($i % 2 === 1) {
                $collection->remove($entry); // remove odd rows inside a foreach
            }
            ++$i;
        }
        $this->assertCount(2, $collection, 'Should be halved');
        $this->assertSame(array(1, 3), $collection->toArray());
    }

    public function test_remove_even_in_foreach()
    {
        $collection = new Collection(array(1, 2, 3, 4));
        $i = 0;
        foreach ($collection as $entry) {
            if ($i % 2 === 0) {
                $collection->remove($entry); // remove even rows inside a foreach
            }
            ++$i;
        }
        $this->assertCount(2, $collection, 'Should be halved');
        $this->assertSame(array(2, 4), $collection->toArray());
    }

    public function test_map()
    {
        $collection = new Collection(array(1, 2, 3, 5));
        $mapped = $collection->map(function ($v) {
            return $v * 2;
        });
        $this->assertSame(array(2, 4, 6, 10), $mapped->toArray(), 'All values should be doubled');
        $this->assertSame(array(1, 2, 3, 5), $collection->toArray(), 'Original array should remain intact');
    }

    public function test_reduce()
    {
        $collection = new Collection(array(1, 2, 3, 4));
        $result = $collection->reduce(function ($result, $v) {
            return $result + $v;
        });
        $this->assertSame(10, $result, '1 + 2 + 3 + 4 = 10');
        $this->assertSame(array(1, 2, 3, 4), $collection->toArray(), 'Original array should remain intact');
    }

    /**
     * A collection containing fruit entries and a vegetable entry.
     *
     * @return Collection
     */
    private function getFruitsAndVegetables()
    {
        return new Collection(array(
            array('id' => '4', 'name' => 'apple', 'type' => 'fruit'),
            array('id' => '6', 'name' => 'pear', 'type' => 'fruit'),
            array('id' => '7', 'name' => 'banana', 'type' => 'fruit'),
            array('id' => '8', 'name' => 'carrot', 'type' => 'vegetable'),
        ));
    }
}
