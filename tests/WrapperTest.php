<?php

namespace SledgehammerTests\Core;

use Exception;
use Sledgehammer\Core\Freeze;

class WrapperTest extends TestCase
{
    public function test_readonly_array()
    {
        $data = array(
            'greeting' => 'Hello',
            'subarray' => array('element' => 'value'),
        );
        $wrapped = new Freeze($data);
        $this->assertSame($wrapped['greeting'], 'Hello');
        $this->assertInstanceOf(Freeze::class, $wrapped['subarray']);

        try {
            $wrapped['greeting'] = 'new value';
            $this->fail('Freeze should not allow a new value');
        } catch (Exception $e) {
            $this->assertSame($e->getMessage(), 'The array is marked readonly');
        }
        $counter = 0;
        foreach ($wrapped as $key => $value) {
            ++$counter;
            if ($counter == 1) {
                $this->assertSame($key, 'greeting');
                $this->assertSame($value, 'Hello');
            } elseif ($counter == 2) {
                $this->assertSame($key, 'subarray');
                $this->assertSame($value['element'], 'value');
            }
        }
        $this->assertSame($counter, 2, '$data has 2 elements');
    }
}
