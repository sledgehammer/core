<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Text;

class TextTest extends TestCase
{
    public function test_length_and_encoding_detection()
    {
        $utf8 = $this->getString('UTF-8');
        $latin1 = $this->getString('ISO-8859-15');
        $ascii = 'abc';
        $expectedLength = 13;
        $numberOfBytesInUtf8 = 17;
        $detectOrder = array('ASCII', 'UTF-8', 'ISO-8859-15');
        $this->assertEquals(strlen($latin1), $expectedLength, 'strlen() returns the number of chars on ISO-8859-15 and other singlebyte encodings');
        $this->assertEquals(strlen($utf8), $numberOfBytesInUtf8, 'strlen() return the number of bytes, NOT the number of chars on UTF-8 and other multibyte encodings');

        $this->assertEquals(\Sledgehammer\text($latin1, $detectOrder)->length, $expectedLength, 'Text->length should return the number of characters on a ISO-8859-15 string');
        $this->assertEquals(\Sledgehammer\text($utf8, $detectOrder)->length, $expectedLength, 'Text->length should return the number of characters on a UTF-8 string');

        $this->assertEquals(strlen(\Sledgehammer\text($latin1, $detectOrder)), $numberOfBytesInUtf8, 'Text converts ISO-8859-15 string to UTF-8 strings');
    }

    public function test_toUpper()
    {
        $italie = html_entity_decode('itali&euml;', ENT_COMPAT, 'UTF-8');
        $uppercaseItalie = html_entity_decode('ITALI&Euml;', ENT_COMPAT, 'UTF-8');
        $this->assertNotEquals(strtoupper($italie), $uppercaseItalie, 'strtoupper() doesn\'t work with UTF-8');

        $text = \Sledgehammer\text($italie);
        $uppercaseText = $text->toUpper();
        $this->assertEquals((string) $text, $italie, 'toUpper doesn\'t modify the text instance');
        $this->assertInstanceOf(Text::class, $uppercaseText, 'Returns a new Text instance');
        $this->assertEquals($uppercaseText, $uppercaseItalie, 'toUpper convert the characters to uppercase');
        $this->assertEquals($uppercaseText->toLower(), $italie, 'toLower convert the characters back to lowercase');
    }

    public function test_ArrayAcces()
    {
        $utf8 = $this->getString('UTF-8');
        $text = \Sledgehammer\text($utf8);

        $this->assertEquals($utf8[3], 'I', 'php uses index 0 AND 1 for the copyright sign');
        $this->assertEquals($text[2], 'I', 'Text uses index 0 for the copyright sign');
    }

    public function test_endsWith()
    {
        $this->assertTrue(\Sledgehammer\text('1234')->endsWith('34'));
        $this->assertFalse(\Sledgehammer\text('1234')->endsWith('12'));
    }

    public function test_startsWith()
    {
        $this->assertTrue(\Sledgehammer\text('1234')->startsWith('12'));
        $this->assertFalse(\Sledgehammer\text('1234')->startsWith('34'));
    }

    public function test_ucfirst_and_capitalize()
    {
        $this->assertEquals(\Sledgehammer\text('bob')->ucfirst(), 'Bob');
        $this->assertEquals(\Sledgehammer\text('STOP')->ucfirst(), 'STOP', 'ucfirst() should do nothing when the first chararakter already is uppercase');
        $this->assertEquals(\Sledgehammer\text('STOP')->capitalize(), 'Stop', 'capitalize() should convert the first charakter to uppercase and the rest to lowercase');
    }

    private function getString($charset)
    {
        $html = '&copy; Itali&euml; &euro; 10';

        return html_entity_decode($html, ENT_COMPAT, $charset);
    }
}
