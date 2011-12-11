<?php
/**
 * WrapperTests
 *
 */
namespace SledgeHammer;

class WrapperTests extends TestCase {

	function test_readonly_array() {
		$data = array(
			'greeting' => 'Hello',
			'subarray' => array('element' => 'value'),
		);
		$wrapped = new Readonly($data);
		$this->assertEqual($wrapped['greeting'], 'Hello');
		$this->assertIsA($wrapped['subarray'], 'SledgeHammer\Readonly');

		try {
			$wrapped['greeting'] = 'new value';
			$this->fail('Readonly should not allow a new value');
		}  catch (\Exception $e) {
			$this->assertEqual($e->getMessage(), 'The array is marked readonly');
		}

	}
}

?>
