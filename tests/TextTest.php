<?php
/**
 * TextTests
 *
 */
namespace Sledgehammer;

class TextTest extends TestCase {

	function test_length_and_encoding_detection() {
		$utf8 = $this->getString('UTF-8');
		$latin1 = $this->getString('ISO-8859-15');
		$ascii = 'abc';
		$expectedLength = 13;
		$numberOfBytesInUtf8 = 17;
		$detectOrder = array('ASCII', 'UTF-8', 'ISO-8859-15');
		$this->assertEquals(strlen($latin1), $expectedLength, 'strlen() returns the number of chars on ISO-8859-15 and other singlebyte encodings');
		$this->assertEquals(strlen($utf8), $numberOfBytesInUtf8, 'strlen() return the number of bytes, NOT the number of chars on UTF-8 and other multibyte encodings');

		$this->assertEquals(text($latin1, $detectOrder)->length, $expectedLength, 'Text->length should return the number of characters on a ISO-8859-15 string');
		$this->assertEquals(text($utf8, $detectOrder)->length, $expectedLength, 'Text->length should return the number of characters on a UTF-8 string');

		$this->assertEquals(strlen(text($latin1, $detectOrder)), $numberOfBytesInUtf8, 'Text converts ISO-8859-15 string to UTF-8 strings');
	}

	function test_toUpper() {
		$italie = html_entity_decode('itali&euml;', ENT_COMPAT, 'UTF-8');
		$uppercaseItalie = html_entity_decode('ITALI&Euml;', ENT_COMPAT, 'UTF-8');
		$this->assertNotEquals(strtoupper($italie), $uppercaseItalie, 'strtoupper() doesn\'t work with UTF-8');

		$text = text($italie);
		$uppercaseText = $text->toUpper();
		$this->assertEquals((string)$text, $italie, 'toUpper doesn\'t modify the text instance');
		$this->assertInstanceOf('Sledgehammer\Text', $uppercaseText, 'Returns a new Text instance');
		$this->assertEquals($uppercaseText, $uppercaseItalie, 'toUpper convert the characters to uppercase');
		$this->assertEquals($uppercaseText->toLower(), $italie, 'toLower convert the characters back to lowercase');
	}

	function test_ArrayAcces() {
		$utf8 = $this->getString('UTF-8');
		$text = text($utf8);

		$this->assertEquals($utf8[3], 'I', 'php uses index 0 AND 1 for the copyright sign');
		$this->assertEquals($text[2], 'I', 'Text uses index 0 for the copyright sign');
	}

	function test_endsWith() {
		$this->assertTrue(text('1234')->endsWith('34'));
		$this->assertFalse(text('1234')->endsWith('12'));
	}

	function test_startsWith() {
		$this->assertTrue(text('1234')->startsWith('12'));
		$this->assertFalse(text('1234')->startsWith('34'));
	}

	function test_ucfirst_and_capitalize() {
		$this->assertEquals(text('bob')->ucfirst(), 'Bob');
		$this->assertEquals(text('STOP')->ucfirst(), 'STOP', 'ucfirst() should do nothing when the first chararakter already is uppercase');
		$this->assertEquals(text('STOP')->capitalize(), 'Stop', 'capitalize() should convert the first charakter to uppercase and the rest to lowercase');
	}

	private function getString($charset) {
		$html = '&copy; Itali&euml; &euro; 10';
		return html_entity_decode($html, ENT_COMPAT, $charset);
	}

}

?>