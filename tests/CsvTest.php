<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Csv;

class CsvTest extends TestCase
{
    public function test_read()
    {
        // Read a file (where the last line is not an EOL)
        $csv = new Csv(__DIR__.'/data/noeol.csv');
        $this->assertSame([
            ['department' => 'it', 'name' => 'Govert'],
            ['department' => 'health', 'name' => 'G. Verschuur'],
                ], iterator_to_array($csv));
    }

    public function test_skip_empty_lines()
    {
        $csv = new Csv(__DIR__.'/data/empty_lines.csv');
        $this->assertSame([
            ['id' => '1', 'name' => 'Donald Duck'],
            ['id' => '2', 'name' => 'Goofy'],
            ['id' => '3', 'name' => 'Kwik'],
            ['id' => '4', 'name' => 'Kwek'],
            ['id' => '5', 'name' => 'Darkwing Duck'],
                ], iterator_to_array($csv));
    }

    public function test_write()
    {
        $data = [['id' => '1', 'name' => 'John'], ['id' => '2', 'name' => 'Doe']];
        $filename = \Sledgehammer\TMP_DIR.'CsvTests_testfile.csv';
        Csv::write($filename, $data);
        $this->assertSame(file_get_contents($filename), "id;name\n1;John\n2;Doe\n");
        $csv = new Csv($filename);
        $this->assertSame(iterator_to_array($csv), $data);
        unlink($filename);
    }
}
