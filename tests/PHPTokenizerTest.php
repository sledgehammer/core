<?php
/**
 * PHPTokenizerTests
 *
 */
namespace SledgeHammer;

class PHPTokenizerTest extends TestCase {

	function donttest_tokenizer() {
		$filename = $GLOBALS['AutoLoader']->getFilename('SledgeHammer\FFVideo');
		$this->assertEqualTokenizer($filename);

		try {
			$tokenizer = new PHPTokenizer(file_get_contents($filename));
			$tokens = iterator_to_array($tokenizer);
			foreach ($tokens as $token) {
				if (strpos($token[1], '{') !== false) {
					if ($token[0] != 'T_OPEN_BRACKET' || $token[1] != '{') {
						dump($token);
					}
				}
			}
			dump($tokens);
//			dump($tokenizer);
//			dump($analyzer->getInfo('SledgeHammer\CSVIterator'));
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
		}
	}

	function donttest_tokenizer_merged_output() {
		$files = $this->getDefinitionFiles();
		foreach ($files as $filename) {
			$this->assertEqualTokenizer($filename);
		}
	}

	private function assertEqualTokenizer($filename) {
		$content = file_get_contents($filename); //$GLOBALS['AutoLoader']->getFilename('SledgeHammer\GoogleAnalytics');
		try {
			$tokenIterator = new PHPTokenizer($content);
			$mergedTokens = '';
			$tokens = array();
			foreach ($tokenIterator as $token) {
				$mergedTokens .= $token[1];
				$tokens[] = $token;
			}
			$this->assertEquals($content, $mergedTokens, 'Input should match all tokens combined (file: "'.$filename.'")');
		} catch (\Exception $e) {
			ErrorHandler::handle_exception($e);
			$this->fail($e->getMessage());
		}
	}

	private function getDefinitionFiles() {
		$definitions = $GLOBALS['AutoLoader']->getDefinitions();
		$files = array();
		foreach ($definitions as $definition) {
			$files[] = $GLOBALS['AutoLoader']->getFilename($definition);
		}
		return array_unique($files);
	}

}

?>
