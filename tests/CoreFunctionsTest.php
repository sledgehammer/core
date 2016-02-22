<?php

namespace SledgehammerTests\Core;

/**
 * Unittest for the global Sledgehammer functions.
 */
class CoreFunctionsTest extends TestCase
{
    public function test_value_function()
    {
        $bestaat = 'Wel';
        $this->assertEquals(\Sledgehammer\value($bestaat), $bestaat, 'value($var) geeft de waarde van $var terug');
        $this->assertEquals(\Sledgehammer\value($bestaatNiet), null, 'value() op een niet bestaande $var geeft null terug');
        // Kon ik dit maar voorkomen....
        $this->assertTrue(array_key_exists('bestaatNiet', get_defined_vars()), 'Na de value() bestaat de var $bestaatNiet en heeft de waarde null');
    }

    public function test_value_array_function()
    {
        $array = [
            'bestaat' => 'Wel',
        ];
        $array['nested'] = &$array;
        $this->assertEquals('Wel', \Sledgehammer\array_value($array, 'bestaat'), '\Sledgehammer\array_value($var, "key") geeft de waarde van $var["key"] terug');
        $this->assertEquals(null, \Sledgehammer\array_value($array, 'bestaatNiet'), '\Sledgehammer\array_value() op een niet bestaande index geeft null terug');
        $this->assertEquals(null, \Sledgehammer\array_value($array, 'bestaat', 'niet'), '\Sledgehammer\array_value() met een index  geeft null terug');
        $this->assertEquals('Wel', \Sledgehammer\array_value($array, 'nested', 'nested', 'bestaat'), '\Sledgehammer\array_value($var, "key1", "key1") heeft de waarde van $var["key1"]["key2] terug');
        $this->assertEquals(null, \Sledgehammer\array_value($array, 'nested', 'nested', 'bestaatNiet'));
    }

    public function test_compare()
    {
        $this->assertTrue(\Sledgehammer\compare('asd', '==', 'asd'));
        $this->assertTrue(\Sledgehammer\compare(2, '==', 2));
        $this->assertFalse(\Sledgehammer\compare('asd', '==', 'AsD')); // But MySQL will evalutate this to true, depending on the collation
        $this->assertTrue(\Sledgehammer\compare('1', '==', 1));
        $this->assertTrue(\Sledgehammer\compare(null, '==', null));
        $this->assertTrue(\Sledgehammer\compare(1, '>', null));
        $this->assertTrue(\Sledgehammer\compare(0, '>=', null));
        $this->assertFalse(\Sledgehammer\compare('', '==', 0));
        $this->assertFalse(\Sledgehammer\compare(0, '>', null));
        $this->assertTrue(\Sledgehammer\compare(2, 'IN', array(1, 2, 3)));
        $this->assertFalse(\Sledgehammer\compare(4, 'IN', array(1, 2, 3)));
        $this->assertTrue(\Sledgehammer\compare(4, 'NOT IN', array(1, 2, 3)));
        $this->assertFalse(\Sledgehammer\compare(2, 'NOT IN', array(1, 2, 3)));
        $this->assertTrue(\Sledgehammer\compare(1, '==', true));
        $this->assertTrue(\Sledgehammer\compare('1', '==', true));
        $this->assertTrue(\Sledgehammer\compare(0, '==', false));
        $this->assertTrue(\Sledgehammer\compare('0', '==', false));
        // compare uses the stricter equals() rules.
        $this->assertFalse(\Sledgehammer\compare('true', '==', true), '"true" != true');
        $this->assertFalse(\Sledgehammer\compare('true', '==', false), '"true" != false either');
        $this->assertFalse(\Sledgehammer\compare(2, '==', false));
        $this->assertFalse(\Sledgehammer\compare(2, '==', true));

        $this->assertTrue(\Sledgehammer\compare('car', 'LIKE', 'car'));
        $this->assertTrue(\Sledgehammer\compare('cartoon', 'LIKE', 'ca%'));
        $this->assertFalse(\Sledgehammer\compare('cartoon', 'LIKE', 'ca%pet'));
        $this->assertTrue(\Sledgehammer\compare('car', 'LIKE', 'c_r'));
        $this->assertTrue(\Sledgehammer\compare('cartoon', 'LIKE', 'ca%'));
        $this->assertTrue(\Sledgehammer\compare('\\a%b_c\\', 'LIKE', '\\a\%b\_c\\'), 'escape %, _ with \ ');
        $this->assertTrue(\Sledgehammer\compare('car', 'NOT LIKE', 'bar'));
        $this->assertFalse(\Sledgehammer\compare('car', 'NOT LIKE', 'car'));
    }
}
