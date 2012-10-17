<?php
/**
 * CsvTest
 */
namespace Sledgehammer;
/**
 * @package Core
 */
class CsvTest extends TestCase {

	function test_csv() {
		$data = array(array('id' => '1', 'name' => 'John'), array('id' => '2', 'name' => 'Doe'));
		$filename = TMP_DIR.'CsvTests_testfile.csv';
		Csv::write($filename, $data);
		$this->assertEquals(file_get_contents($filename), "id;name\n1;John\n2;Doe\n");
		$csv = new Csv($filename);
		$this->assertEquals(iterator_to_array($csv), $data);
		unlink($filename);
	}

}

?>
