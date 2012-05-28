<?php
namespace Sledgehammer;
/**
 * Controleer diverse globale Sledgehammer functies
 */
class CoreFunctionsTest extends TestCase {

	function test_value_function() {
		$bestaat = 'Wel';
		$this->assertEquals(value($bestaat), $bestaat, 'value($var) heeft de waarde van $var terug');
		$this->assertEquals(value($bestaatNiet), null, 'value() op een niet bestaande $var geeft NULL terug');
		// Kon ik dit maar voorkomen....
		$this->assertTrue(array_key_exists('bestaatNiet', get_defined_vars()), 'Na de value() bestaat de var $bestaatNiet en heeft de waarde NULL');
	}

}

?>
