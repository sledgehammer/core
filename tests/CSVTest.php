<?php
/**
 * CSVTests
 *
 */
namespace Sledgehammer;

class CSVTest extends TestCase {

	function test_csv() {
		$data = array(array('id' => '1', 'name' => 'John'), array('id' => '2', 'name' => 'Doe'));
		$filename = TMP_DIR.'CSVTests_testfile.csv';
		CSV::write($filename, $data);
		$this->assertEquals(file_get_contents($filename), "id;name\n1;John\n2;Doe\n");
		$csv = new CSV($filename);
		$this->assertEquals(iterator_to_array($csv), $data);
		unlink($filename);
	}

}

?>
