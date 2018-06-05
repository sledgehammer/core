<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Csv;
use Sledgehammer\Core\Environment;

class CsvTest extends TestCase
{
    public function test_read()
    {
        // Read a file (where the last line is not an EOL)
        $csv = new Csv(__DIR__.'/data/noeol.csv');
        $this->assertSame(array(
            array('department' => 'it', 'name' => 'Govert'),
            array('department' => 'health', 'name' => 'G. Verschuur'),
                ), iterator_to_array($csv));
    }

    public function test_skip_empty_lines()
    {
        $csv = new Csv(__DIR__.'/data/empty_lines.csv');
        $this->assertSame(array(
            array('id' => '1', 'name' => 'Donald Duck'),
            array('id' => '2', 'name' => 'Goofy'),
            array('id' => '3', 'name' => 'Kwik'),
            array('id' => '4', 'name' => 'Kwek'),
            array('id' => '5', 'name' => 'Darkwing Duck'),
                ), iterator_to_array($csv));
    }

    public function test_write()
    {
        $data = array(array('id' => '1', 'name' => 'John'), array('id' => '2', 'name' => 'Doe'));
        $filename = Environment::tmpdir().'CsvTests_testfile.csv';
        Csv::write($filename, $data);
        $this->assertSame(file_get_contents($filename), "id;name\n1;John\n2;Doe\n");
        $csv = new Csv($filename);
        $this->assertSame(iterator_to_array($csv), $data);
        unlink($filename);
    }
}
