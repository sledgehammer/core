<?php
/**
 * Controleer diverse Sledgehammer vereisten
 */
namespace Sledgehammer;

class HTMLTokenizerTest extends TestCase {

	function test_skipped() {
		$this->markTestSkipped('Not really unittests (No assertions on the output)');
	}

	function dont_test_cdata() {
		$html = <<<EOD
<html>
<body>
<div id="test"><![CDATA[Testing Data here]]></div>
</body>
</html>
<script>
alert(document.getElementById("test").innerHTML );
alert(document.getElementById("test").firstChild.data);
</script>
EOD;
		$tokens = new HTMLTokenizer($html);
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_plainText() {
		$tokenizer = new HTMLTokenizer('Een plain tekst voorbeeld');
		$this->prettyPrint($tokenizer);
		$this->assertNoWarnings($tokenizer);
	}

	function dont_test_link() {
		$tokens = new HTMLTokenizer('<a href="http://www.google.nl">Zoeken</a>');
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_comment() {
		$tokens = new HTMLTokenizer('text<!-- Comment --> <!---->text');
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_before_after() {
		$tokens = new HTMLTokenizer('before<br />middle<a href="test.html" empty="">TEST</a>after');
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_inline_dtd() {
		$tokens = new HTMLTokenizer('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">');
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);

		$html = <<<EOD
<!DOCTYPE NEWSPAPER [

<!ELEMENT NEWSPAPER (ARTICLE+)>
<!ELEMENT ARTICLE (HEADLINE,BYLINE,LEAD,BODY,NOTES)>
<!ELEMENT HEADLINE (#PCDATA)>
<!ELEMENT BYLINE (#PCDATA)>
<!ELEMENT LEAD (#PCDATA)>
<!ELEMENT BODY (#PCDATA)>
<!ELEMENT NOTES (#PCDATA)>

<!ATTLIST ARTICLE AUTHOR CDATA #REQUIRED>
<!ATTLIST ARTICLE EDITOR CDATA #IMPLIED>
<!ATTLIST ARTICLE DATE CDATA #IMPLIED>
<!ATTLIST ARTICLE EDITION CDATA #IMPLIED>

]>tekst
EOD;
		$tokens = new HTMLTokenizer($html);
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_value_delimiters() {
		$html = '<a onclick="alert(\'Hi\')" target = _blank>';
		$tokens = new HTMLTokenizer($html);
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
	}

	function dont_test_evil_html() {
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
		$tokens = new HTMLTokenizer($html);
		$this->prettyPrint($tokens);
		$this->assertNoWarnings($tokens);
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

	private function prettyPrint($tokenizer) {
		$errorColor = 'white:background:red';
		$colors = array(
			'T_TAG' => 'purple',
			'T_CLOSE_TAG' => 'purple',
			'T_OPEN' => 'green',
			'T_CLOSE' => 'green',
			'T_ATTRIBUTE' => 'brown',
			'T_VALUE' => 'darkblue',
			'T_COMMENT' => 'gray',
			'T_DTD_ENTITY' => 'orange',
			'T_DTD_ATTRIBUTES' => 'brown',
			'T_TEXT' => 'black',
			'T_CDATA' => 'black',
			'T_WHITESPACE' => 'red',
			'T_INVALID' => $errorColor,
			'T_LT' => $errorColor,
			'T_GT' => $errorColor,
			'T_PARSER_TAG' => 'Aquamarine',
			'T_DELIMITER' => 'orange',
		);
		echo '<pre style="background:white;overflow:auto;padding:10px;color:red">';
		foreach ($tokenizer as $index => $token) {
			if (is_array($token)) {
				echo '<span title="', $token[0], '" style="color:', $colors[$token[0]], '">', htmlentities($token[1]), '</span>';
			} else {
				echo '<span style="color:green">', htmlentities($token), '</span>';
			}
		}
		echo "</pre>";
	}

	private function assertNoWarnings($tokenizer) {
		foreach ($tokenizer->warnings as $warning) {
			$this->fail($warning);
		}
		$tokenizer->warnings = array();
	}

}

?>
