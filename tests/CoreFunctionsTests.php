<?php
/**
 * Controleer diverse globale SledgeHammer functies
 */

class CoreFunctionsTests extends UnitTestCase {

	function test_value_function() {
		$bestaat = 'Wel';
		$this->assertEqual(value($bestaat), $bestaat, 'value($var) heeft de waarde van $var terug');
		$this->assertEqual(value($bestaatNiet), null, 'value() op een niet bestaande $var geeft NULL terug');
		// Kon ik dit maar voorkomen....
		$this->assertTrue(array_key_exists('bestaatNiet', get_defined_vars()), 'Na de value() bestaat de var $bestaatNiet en heeft de waarde NULL');
	}
	
}
?>
