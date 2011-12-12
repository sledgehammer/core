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
		} catch (\Exception $e) {
			$this->assertEqual($e->getMessage(), 'The array is marked readonly');
		}
		$counter = 0;
		foreach ($wrapped as $key => $value) {
			$counter++;
			if ($counter == 1) {
				$this->assertEqual($key, 'greeting');
				$this->assertEqual($value, 'Hello');
			} elseif ($counter == 2) {
				$this->assertEqual($key, 'subarray');
				$this->assertEqual($value['element'], 'value');
			}
		}
		$this->assertEqual($counter, 2, '$data has 2 elements');
	}

}

?>
