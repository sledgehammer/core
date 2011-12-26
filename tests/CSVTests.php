<?php

/**
 * CSVTests
 *
 */
namespace SledgeHammer;
class CSVTests extends TestCase {

	function test_csv() {
		$data = array(array('id' => 1, 'name' => 'John'), array('id' => 2, 'name' => 'Doe'));
		$filename = TMP_DIR.'CSVTests_testfile.csv';
		CSV::write($filename, $data);
		$this->assertEqual(file_get_contents($filename), "id;name\n1;John\n2;Doe\n");
		$csv = new CSV($filename);
		$this->assertEqual(iterator_to_array($csv), $data);

	}
}

?>
