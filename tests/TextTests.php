<?php
/**
 * TextTests
 *
 */
namespace SledgeHammer;

class TextTests extends TestCase {

	function test_length_and_encoding_detection() {
		$utf8 = $this->getString('UTF-8');
		$latin1 = $this->getString('ISO-8859-15');
		$ascii = 'abc';
		$expectedLength = 13;
		$numberOfBytesInUtf8 = 17;
		$this->assertEqual(strlen($latin1), $expectedLength, 'strlen() returns the number of chars on ISO-8859-15 and other singlebyte encodings');
		$this->assertEqual(strlen($utf8), $numberOfBytesInUtf8, 'strlen() return the number of bytes, NOT the number of chars on UTF-8 and other multibyte encodings');

		$this->assertEqual(text($latin1)->length, $expectedLength, 'Text->length should return the number of characters on a ISO-8859-15 string');
		$this->assertEqual(text($utf8)->length, $expectedLength, 'Text->length should return the number of characters on a UTF-8 string');

		$this->assertEqual(strlen(text($latin1)), $numberOfBytesInUtf8, 'Text converts ISO-8859-15 string to UTF-8 strings');
	}

	function test_toUpper() {
		$italie = html_entity_decode('itali&euml;', ENT_COMPAT, 'UTF-8');
		$uppercaseItalie = html_entity_decode('ITALI&Euml;', ENT_COMPAT, 'UTF-8');
		$this->assertNotEqual(strtoupper($italie), $uppercaseItalie, 'strtoupper() doesn\'t work with UTF-8');

		$text = text($italie);
		$uppercaseText = $text->toUpper();
		$this->assertEqual($text, $italie, 'toUpper doesn\'t modify the text instance');
		$this->assertIsA($uppercaseText, 'SledgeHammer\Text', 'Returns a new Text instance');
		$this->assertEqual($uppercaseText, $uppercaseItalie, 'toUpper convert the characters to uppercase');
		$this->assertEqual($uppercaseText->toLower(), $italie, 'toLower convert the characters back to lowercase');
	}

	function test_ArrayAcces() {
		$utf8 = $this->getString('UTF-8');
		$text = text($utf8);

		$this->assertEqual($utf8[3], 'I', 'php uses index 0 AND 1 for the copyright sign');
		$this->assertEqual($text[2], 'I', 'Text uses index 0 for the copyright sign');
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
		$this->assertEqual(text('bob')->ucfirst(), 'Bob');
		$this->assertEqual(text('STOP')->ucfirst(), 'STOP', 'ucfirst() should do nothing when the first chararakter already is uppercase');
		$this->assertEqual(text('STOP')->capitalize(), 'Stop', 'capitalize() should convert the first charakter to uppercase and the rest to lowercase');
	}

	private function getString($charset) {
		$html = '&copy; Itali&euml; &euro; 10';
		return html_entity_decode($html, ENT_COMPAT, $charset);
	}

}

?>