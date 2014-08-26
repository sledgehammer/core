<?php
/**
 * HtmlTokenizerTest
 */

namespace Sledgehammer;

/**
 * @package Core
 */
class HtmlTokenizerTest extends TestCase {

    function test_plainText() {
        $tokenizer = new HtmlTokenizer('Een plain tekst voorbeeld');
        $this->assertEquals([['T_TEXT', 'Een plain tekst voorbeeld', 0]], iterator_to_array($tokenizer));
        $this->assertNoWarnings($tokenizer);
    }

    function test_link() {
        $tokenizer = new HtmlTokenizer('<a href="http://www.google.nl/">Zoeken</a>');
        $expectedTokens = [
            ['T_OPEN', '<', 0],
            ['T_TAG', 'a', 1],
            ['T_WHITESPACE', ' ', 2],
            ['T_ATTRIBUTE', 'href', 3],
            ['T_ASSIGNMENT', '=', 7],
            ['T_DELIMITER', '"', 8],
            ['T_VALUE', 'http://www.google.nl/', 9],
            ['T_DELIMITER', '"', 30],
            ['T_CLOSE', '>', 31],
            ['T_TEXT', 'Zoeken', 32],
            ['T_OPEN', '</', 38],
            ['T_CLOSE_TAG', 'a', 40],
            ['T_CLOSE', '>', 41]
        ];
        $this->assertEquals($expectedTokens, iterator_to_array($tokenizer));
        $this->assertNoWarnings($tokenizer);
    }
    
    function test_whitespace() {
        $tokenizer = new HtmlTokenizer("\t <img\t \tsrc = 'image\n.png'\n\n/>  ");
        $expectedTokens = [
            ['T_TEXT', "\t ", 0],
            ['T_OPEN', '<', 2],
            ['T_TAG', 'img', 3],
            ['T_WHITESPACE', "\t \t", 6],
            ['T_ATTRIBUTE', 'src', 9],
            ['T_WHITESPACE', ' ', 12],
            ['T_ASSIGNMENT', '=', 13],
            ['T_WHITESPACE', ' ', 14],
            ['T_DELIMITER', "'", 15],
            ['T_VALUE', "image\n.png", 16],
            ['T_DELIMITER', "'", 26],
            ['T_WHITESPACE', "\n\n", 27],
            ['T_CLOSE', '/>', 29],
            ['T_TEXT', '  ', 31],
        ];
        $this->assertEquals($expectedTokens, iterator_to_array($tokenizer));
        $this->assertNoWarnings($tokenizer);
    }
    
    function test_weird_attributes() {
        $tokenizer = new HtmlTokenizer('<i === a/b <a href="#"></a> />');
        $expectedTokens = [
            ['T_OPEN', '<', 0],
            ['T_TAG', 'i', 1],
            ['T_WHITESPACE', ' ', 2],
            ['T_ATTRIBUTE', '=', 3], // '=' can be an attribute
            ['T_ASSIGNMENT', '=', 4],
            ['T_VALUE', '=', 5], // and '=' can be a value
            ['T_WHITESPACE', ' ', 6],
            ['T_ATTRIBUTE', 'a', 7],
            ['T_INVALID', '/', 8], // is swallowed by the parser
            ['T_ATTRIBUTE', 'b', 9],
            ['T_WHITESPACE', ' ', 10],
            ['T_ATTRIBUTE', '<a', 11], // <a is parsed as an attribute
            ['T_WHITESPACE', ' ', 13],
            ['T_ATTRIBUTE', 'href', 14],
            ['T_ASSIGNMENT', '=', 18],
            ['T_DELIMITER', '"', 19],
            ['T_VALUE', '#', 20],
            ['T_DELIMITER', '"', 21],
            ['T_CLOSE', '>', 22],
            ['T_OPEN', '</', 23],
            ['T_CLOSE_TAG', 'a', 25], // ignored unless an <a> is opende elsewhere
            ['T_CLOSE', '>', 26],
            ['T_TEXT', ' />', 27],
        ];
        $this->assertEquals($expectedTokens, iterator_to_array($tokenizer));
        $this->assertEquals([
            'Invalid character: "=" in attribute "=" on position 3 parsing TAG_BODY',
            'Unexpected "/" inside a tag on position 8 parsing TAG_BODY',
            'Invalid character: "<" in attribute "<a" on position 11 parsing TAG_BODY',
        ], $tokenizer->warnings);
    }

    // Not really unittests (No assertions on the output)
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
        $tokens = new HtmlTokenizer($html);
        $this->prettyPrint($tokens);
        $this->assertNoWarnings($tokens);
    }

    function dont_test_comment() {
        $tokens = new HtmlTokenizer('text<!-- Comment --> <!---->text');
        $this->prettyPrint($tokens);
        $this->assertNoWarnings($tokens);
    }

    function dont_test_before_after() {
        $tokens = new HtmlTokenizer('before<br />middle<a href="test.html" empty="">TEST</a>after');
        $this->prettyPrint($tokens);
        $this->assertNoWarnings($tokens);
    }

    function dont_test_inline_dtd() {
        $tokens = new HtmlTokenizer('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
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
        $tokens = new HtmlTokenizer($html);
        $this->prettyPrint($tokens);
        $this->assertNoWarnings($tokens);
    }

    function dont_test_value_delimiters() {
        $html = '<a onclick="alert(\'Hi\')" target = _blank>';
        $tokens = new HtmlTokenizer($html);
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
        $tokens = new HtmlTokenizer($html);
        $this->prettyPrint($tokens);
        $this->assertNoWarnings($tokens);
    }

    function dont_test_unterminated_stuff() {
        $cacheFile = PATH . 'tmp/www.w3.org_index.html';
        if (file_exists($cacheFile)) {
            $html = file_get_contents($cacheFile);
        } else {
            $html = file_get_contents('http://www.w3.org');
            file_put_contents($cacheFile, $html);
        }
//dump(strlen($html))
//$html = substr($html, 0, 25450);
        $tokenizer = new HtmlTokenizer($html);
        $this->prettyPrint($tokenizer);
        $this->assertNoWarnings($tokenizer);

//$tokens = iterator_to_[$tokenizer);
        /*

          $start = microtime(true);
          for($i = strlen($html); $i > 2; $i--) {
          $tokenizer = new HTMLTokenizer($html);
          $tokens = iterator_to_[$tokenizer);
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
        $colors = [
            'T_TAG' => 'purple',
            'T_CLOSE_TAG' => 'purple',
            'T_OPEN' => 'green',
            'T_CLOSE' => 'green',
            'T_ATTRIBUTE' => 'brown',
            'T_ASSIGNMENT' => 'green',
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
        ];
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
        $tokenizer->warnings = [];
    }

}
