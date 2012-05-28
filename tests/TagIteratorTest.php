<?php
/**
 * Controleer diverse Sledgehammer vereisten
 */
namespace Sledgehammer;

class TagIteratorTest extends TestCase {

	function setUp() {
		ini_set('display_errors', true);
		//restore_error_handler();
	}

	function test_cdata() {
		$this->compare('<div id="test"><![CDATA[<ignore me="ok">]]></div>', array(
			0 => array(
				0 => '<div',
				1 => array(
					'id' => 'test',
				),
				2 => '>',
				'html' => '<div id="test">',
			),
			1 => '<![CDATA[<ignore me="ok">]]>',
			2 => array(
				0 => '</div',
				1 => array(),
				2 => '>',
				'html' => '</div>',
			),
		));
	}

	function test_plainText() {
		$html = 'Een plain tekst voorbeeld';
		$this->compare($html, array($html));
	}

	function test_link() {
		$this->compare('<a href="http://www.google.nl">Zoeken</a>', array(
			0 => array(
				0 => '<a',
				1 => array(
					'href' => 'http://www.google.nl',
				),
				2 => '>',
				'html' => '<a href="http://www.google.nl">',
			),
			1 => 'Zoeken',
			2 => array(
				0 => '</a',
				1 => array(),
				2 => '>',
				'html' => '</a>',
			),
		));
	}

	function test_before_after() {
		$this->compare('before<br />middle<a href="test.html">TEST</a>after', array(
			0 => 'before',
			1 => array(
				0 => '<br',
				1 => array(),
				2 => '/>',
				'html' => '<br />',
			),
			2 => 'middle',
			3 => array(
				0 => '<a',
				1 => array(
					'href' => 'test.html',
				),
				2 => '>',
				'html' => '<a href="test.html">',
			),
			4 => 'TEST',
			5 => array(
				0 => '</a',
				1 => array(),
				2 => '>',
				'html' => '</a>',
			),
			6 => 'after',
		));
	}

	function test_inline_dtd() {
		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$this->compare($html, array(
			array(
				0 => '<!DOCTYPE',
				1 => array(
					0 => ' html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"',
				),
				2 => '>',
				'html' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
			)
		));
//		$html = <<<END
//<!DOCTYPE NEWSPAPER [
//
//<!ELEMENT NEWSPAPER (ARTICLE+)>
//<!ELEMENT ARTICLE (HEADLINE,BYLINE,LEAD,BODY,NOTES)>
//<!ELEMENT HEADLINE (#PCDATA)>
//<!ELEMENT BYLINE (#PCDATA)>
//<!ELEMENT LEAD (#PCDATA)>
//<!ELEMENT BODY (#PCDATA)>
//<!ELEMENT NOTES (#PCDATA)>
//
//<!ATTLIST ARTICLE AUTHOR CDATA #REQUIRED>
//<!ATTLIST ARTICLE EDITOR CDATA #IMPLIED>
//<!ATTLIST ARTICLE DATE CDATA #IMPLIED>
//<!ATTLIST ARTICLE EDITION CDATA #IMPLIED>
//
//]>tekst
//END;
//		$this->compare($html, array(
//			0 => $html
//		));
	}

	function test_evil_html() {
		$html = <<<EOD
			<div href="test" height=1 12=45 bob test=12 /watte=x ditte = 12>This is <> linked</a> this is not.
			<?php this is een <subtag>hidden<tag> ?>
			<a href=123 x = y=2 oops">href<br / ></a>
			<123> tag
			<a href=123>href<br>test</a param='"<x>'>
			<!-- Dit is commentaar -->
			<evil tag='<">"' / test=hihi>
			<!--Ditis<ook->commentaar-->
			dit niet<! dit is volgens firefox commentaar<weird> -->hmm
			<a href="#">link</ a> ook link
EOD;
		$this->compare($html, '__SKIP_OUTPUT_CHECK__');
	}

	function dont_test_unterminated_stuff() {
		$cacheFile = PATH.'tmp/www.w3.org_index.html';
		if (file_exists($cacheFile)) {
			$html = file_get_contents($cacheFile);
		} else {
			$html = file_get_contents('http://www.w3.org');
			file_put_contents($cacheFile, $html);
		}
		//dump(strlen($html))
		//$html = substr($html, 0, 25450);
		$tokenizer = new HTMLTokenizer($html);
		$this->prettyPrint($tokenizer);
		$this->assertNoWarnings($tokenizer);

		//$tokens = iterator_to_array($tokenizer);
		/*

		  $start = microtime(true);
		  for($i = strlen($html); $i > 2; $i--) {
		  $tokenizer = new HTMLTokenizer($html);
		  $tokens = iterator_to_array($tokenizer);
		  //$this->dumpTokens($tokenizer);
		  $html = substr($html, 0, $i);
		  if ($i % 25 == 0) {
		  dump($i); flush();
		  }
		  }
		  dump(microtime(true) - $start);
		 */
	}

	private function assertNoWarnings($tokenizer) {
		$noWarnings = true;
		foreach ($tokenizer->warnings as $warning) {
			$this->fail($warning);
			$noWarnings = false;
		}
		$tokenizer->warnings = array();
		//$this->assertTrue($noWarnings, 'The tokenizer should not generate warnings');
	}

	/**
	 *
	 * @param $html
	 * @param array $expectedOutput
	 * @return void
	 */
	private function compare($html, $expectedOutput) {
		$tags = new TagIterator($html);
		$output = iterator_to_array($tags);
		$reconstructedHtml = '';
		foreach ($output as $token) {
			$reconstructedHtml .= (is_array($token) ? $token['html'] : $token);
		}
		$this->assertEquals($html, $reconstructedHtml, 'reconstructed HTML should match the original HTML');
		if ($expectedOutput != '__SKIP_OUTPUT_CHECK__') {
			$this->assertEquals($expectedOutput, $output);
		}
		//$this->assertWarnings($tags, $warnings);
	}

}

?>