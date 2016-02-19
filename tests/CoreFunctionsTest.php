<?php

namespace SledgehammerTests\Core;

use function Sledgehammer\array_value;
use function Sledgehammer\compare;
use function Sledgehammer\value;

/**
 * Unittest for the global Sledgehammer functions.
 */
class CoreFunctionsTest extends TestCase
{
    public function test_value_function()
    {
        $bestaat = 'Wel';
        $this->assertEquals(value($bestaat), $bestaat, 'value($var) geeft de waarde van $var terug');
        $this->assertEquals(value($bestaatNiet), null, 'value() op een niet bestaande $var geeft null terug');
        // Kon ik dit maar voorkomen....
        $this->assertTrue(array_key_exists('bestaatNiet', get_defined_vars()), 'Na de value() bestaat de var $bestaatNiet en heeft de waarde null');
    }

    public function test_value_array_function()
    {
        $array = [
            'bestaat' => 'Wel',
        ];
        $array['nested'] = &$array;
        $this->assertEquals('Wel', array_value($array, 'bestaat'), 'array_value($var, "key") geeft de waarde van $var["key"] terug');
        $this->assertEquals(null, array_value($array, 'bestaatNiet'), 'array_value() op een niet bestaande index geeft null terug');
        $this->assertEquals(null, array_value($array, 'bestaat', 'niet'), 'array_value() met een index  geeft null terug');
        $this->assertEquals('Wel', array_value($array, 'nested', 'nested', 'bestaat'), 'array_value($var, "key1", "key1") heeft de waarde van $var["key1"]["key2] terug');
        $this->assertEquals(null, array_value($array, 'nested', 'nested', 'bestaatNiet'));
    }

    public function test_compare()
    {
        $this->assertTrue(compare('asd', '==', 'asd'));
        $this->assertTrue(compare(2, '==', 2));
        $this->assertFalse(compare('asd', '==', 'AsD')); // But MySQL will evalutate this to true, depending on the collation
        $this->assertTrue(compare('1', '==', 1));
        $this->assertTrue(compare(null, '==', null));
        $this->assertTrue(compare(1, '>', null));
        $this->assertTrue(compare(0, '>=', null));
        $this->assertFalse(compare('', '==', 0));
        $this->assertFalse(compare(0, '>', null));
        $this->assertTrue(compare(2, 'IN', array(1, 2, 3)));
        $this->assertFalse(compare(4, 'IN', array(1, 2, 3)));
        $this->assertTrue(compare(4, 'NOT IN', array(1, 2, 3)));
        $this->assertFalse(compare(2, 'NOT IN', array(1, 2, 3)));
        $this->assertTrue(compare(1, '==', true));
        $this->assertTrue(compare('1', '==', true));
        $this->assertTrue(compare(0, '==', false));
        $this->assertTrue(compare('0', '==', false));
        // compare uses the stricter equals() rules.
        $this->assertFalse(compare('true', '==', true), '"true" != true');
        $this->assertFalse(compare('true', '==', false), '"true" != false either');
        $this->assertFalse(compare(2, '==', false));
        $this->assertFalse(compare(2, '==', true));

        $this->assertTrue(compare('car', 'LIKE', 'car'));
        $this->assertTrue(compare('cartoon', 'LIKE', 'ca%'));
        $this->assertFalse(compare('cartoon', 'LIKE', 'ca%pet'));
        $this->assertTrue(compare('car', 'LIKE', 'c_r'));
        $this->assertTrue(compare('cartoon', 'LIKE', 'ca%'));
        $this->assertTrue(compare('\\a%b_c\\', 'LIKE', '\\a\%b\_c\\'), 'escape %, _ with \ ');
        $this->assertTrue(compare('car', 'NOT LIKE', 'bar'));
        $this->assertFalse(compare('car', 'NOT LIKE', 'car'));
    }
}
