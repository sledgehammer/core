<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Collection;
use stdClass;

class CollectionTest extends TestCase
{
    public function test_where()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        $vegetables = $fruitsAndVegetables->where(['type' => 'vegetable']);
        $this->assertSame(count($vegetables), 1, 'Only 1 vegetable in the collection');
        $this->assertSame($vegetables[0]['name'], 'carrot');
        $this->assertSame($vegetables->toArray(), [
            [
                'id' => '8',
                'name' => 'carrot',
                'type' => 'vegetable',
            ],
        ]);
        $fruits = $fruitsAndVegetables->where(['type' => 'fruit']);
        $this->assertSame(count($fruits), 3, '3 fruits in the collection');
        $this->assertSame($fruits[0]['name'], 'apple');
        $this->assertSame($fruits->toArray(), [
            [
                'id' => '4',
                'name' => 'apple',
                'type' => 'fruit',
            ],
            [
                'id' => '6',
                'name' => 'pear',
                'type' => 'fruit',
            ],
            [
                'id' => '7',
                'name' => 'banana',
                'type' => 'fruit',
            ],
        ]);
        $andWhere = $fruitsAndVegetables->where(['AND', 'type' => 'fruit', 'id <=' => 4]);
        $this->assertCount(1, $andWhere);

        $orWhere = $fruitsAndVegetables->where(['OR', 'type' => 'fruit', 'id' => 8]);
        $this->assertCount(4, $orWhere);
    }

    public function test_select()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        // Simple select a single field
        $names = $fruitsAndVegetables->select('name');
        $this->assertSame($names->toArray(), [
            'apple',
            'pear',
            'banana',
            'carrot',
        ]);
        // The second parameter determines the key
        $list = $fruitsAndVegetables->select('name', '[id]');
        $this->assertSame($list->toArray(), [
            4 => 'apple',
            6 => 'pear',
            7 => 'banana',
            8 => 'carrot',
        ]);
        // Select multiple fields and create a new structure
        $struct = $fruitsAndVegetables->where(['name' => 'banana'])->select(['name' => '[name]', 'meta[id]' => 'id', 'meta[type]' => 'type']);
        $this->assertSame($struct->toArray(), [
            [
                'name' => 'banana',
                'meta' => [
                    'id' => '7',
                    'type' => 'fruit',
                ],
            ],
        ]);
    }

    public function test_orderBy()
    {
        $fruitsAndVegetables = $this->getFruitsAndVegetables();
        // indexed
        $abc = $fruitsAndVegetables->orderBy('name')->select('name');
        $this->assertSame($abc->toArray(), [
            'apple',
            'banana',
            'carrot',
            'pear',
        ]);
        $zyx = $fruitsAndVegetables->orderByDescending('name')->select('name');
        $this->assertSame($zyx->toArray(), [
            'pear',
            'carrot',
            'banana',
            'apple',
        ]);
    }

    public function test_selectKey()
    {
        $xyz = new Collection(['x' => 10, 'y' => 20, 'z' => 30]);
        $this->assertSame([10, 20, 30], $xyz->selectKey(null)->toArray(), 'null should return an index array.');
        $this->assertSame([10 => 10, 20 => 20, 30 => 30], $xyz->selectKey('.')->toArray(), 'Using a path as key.');
        $closure = function ($item, $key) {
            return $key.$item;
        };
        $this->assertSame(['x10' => 10, 'y20' => 20, 'z30' => 30], $xyz->selectKey($closure)->toArray(), 'Using a closure as key.');
    }

    public function test_where_operators()
    {
        $numbers = new Collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $this->assertCount(1, $numbers->where('2'));
        $this->assertCount(2, $numbers->where('<= 2'));
        $this->assertCount(5, $numbers->where('> 5'));
        $vehicles = new Collection([
            ['name' => 'car', 'wheels' => 4],
            ['name' => 'trike', 'wheels' => 3],
            ['name' => 'bike', 'wheels' => 2],
        ]);
        $this->assertCount(1, $vehicles->where(['wheels >' => 3]));
        $this->assertCount(2, $vehicles->where(['name LIKE' => '%ke']));
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
        $collection = new Collection([10, 20, ['id' => 30], $object1, $object2]);
        $this->assertSame(1, $collection->indexOf(20));
        $this->assertSame(2, $collection->indexOf(['id?' => 30]));
        $this->assertSame(4, $collection->indexOf($object2));
    }

    public function test_remove()
    {
        $object = new stdClass();
        $collection = new Collection([10, ['id' => 20], $object]);
        $collection->remove($object);
        $this->assertSame([10, ['id' => 20]], $collection->toArray());
        $collection->remove(function ($item) {
            return $item == 10;
        });
        $this->assertSame([['id' => 20]], $collection->toArray());
        //		$this->assertSame(2, $collection->indexOf(array('id?' => 30)));
    }

    public function test_remove_odd_in_foreach()
    {
        $collection = new Collection([1, 2, 3, 4]);
        $i = 0;
        foreach ($collection as $entry) {
            if ($i % 2 === 1) {
                $collection->remove($entry); // remove odd rows inside a foreach
            }
            ++$i;
        }
        $this->assertCount(2, $collection, 'Should be halved');
        $this->assertSame([1, 3], $collection->toArray());
    }

    public function test_remove_even_in_foreach()
    {
        $collection = new Collection([1, 2, 3, 4]);
        $i = 0;
        foreach ($collection as $entry) {
            if ($i % 2 === 0) {
                $collection->remove($entry); // remove even rows inside a foreach
            }
            ++$i;
        }
        $this->assertCount(2, $collection, 'Should be halved');
        $this->assertSame([2, 4], $collection->toArray());
    }

    public function test_map()
    {
        $collection = new Collection([1, 2, 3, 5]);
        $mapped = $collection->map(function ($v) {
            return $v * 2;
        });
        $this->assertSame([2, 4, 6, 10], $mapped->toArray(), 'All values should be doubled');
        $this->assertSame([1, 2, 3, 5], $collection->toArray(), 'Original array should remain intact');
    }

    public function test_reduce()
    {
        $collection = new Collection([1, 2, 3, 4]);
        $result = $collection->reduce(function ($result, $v) {
            return $result + $v;
        });
        $this->assertSame(10, $result, '1 + 2 + 3 + 4 = 10');
        $this->assertSame([1, 2, 3, 4], $collection->toArray(), 'Original array should remain intact');
    }

    /**
     * A collection containing fruit entries and a vegetable entry.
     *
     * @return Collection
     */
    private function getFruitsAndVegetables()
    {
        return new Collection([
            ['id' => '4', 'name' => 'apple', 'type' => 'fruit'],
            ['id' => '6', 'name' => 'pear', 'type' => 'fruit'],
            ['id' => '7', 'name' => 'banana', 'type' => 'fruit'],
            ['id' => '8', 'name' => 'carrot', 'type' => 'vegetable'],
        ]);
    }
}
