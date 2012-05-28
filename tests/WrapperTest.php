<?php
/**
 * WrapperTests
 *
 */
namespace Sledgehammer;

class WrapperTest extends TestCase {

	function test_readonly_array() {
		$data = array(
			'greeting' => 'Hello',
			'subarray' => array('element' => 'value'),
		);
		$wrapped = new Readonly($data);
		$this->assertEquals($wrapped['greeting'], 'Hello');
		$this->assertInstanceOf('Sledgehammer\Readonly', $wrapped['subarray']);

		try {
			$wrapped['greeting'] = 'new value';
			$this->fail('Readonly should not allow a new value');
		} catch (\Exception $e) {
			$this->assertEquals($e->getMessage(), 'The array is marked readonly');
		}
		$counter = 0;
		foreach ($wrapped as $key => $value) {
			$counter++;
			if ($counter == 1) {
				$this->assertEquals($key, 'greeting');
				$this->assertEquals($value, 'Hello');
			} elseif ($counter == 2) {
				$this->assertEquals($key, 'subarray');
				$this->assertEquals($value['element'], 'value');
			}
		}
		$this->assertEquals($counter, 2, '$data has 2 elements');
	}

}

?>
