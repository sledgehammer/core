<?php
/**
 * PHPTokenizerTest
 */
namespace Sledgehammer;
/**
 *
 */
class PHPTokenizerTest extends TestCase {

	function test_skipped() {
		$this->markTestSkipped('Not really unittests (No assertions on the output)');
	}

	function donttest_tokenizer() {
		$filename = Framework::$autoLoader->getFilename('App');
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
			ob_flush();
		} catch (\Exception $e) {
			report_exception($e);
			ob_flush();
		}
	}

	function donttest_tokenizer_merged_output() {
		$files = $this->getDefinitionFiles();
		foreach ($files as $filename) {
			$this->assertEqualTokenizer($filename);
		}
	}

	private function assertEqualTokenizer($filename) {
		$content = file_get_contents($filename); //Framework::$autoLoader->getFilename('Sledgehammer\GoogleAnalytics');
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
			report_exception($e);
			ob_flush();
			$this->fail($e->getMessage());
		}
	}

	private function getDefinitionFiles() {
		$definitions = Framework::$autoLoader->getDefinitions();
		$files = array();
		foreach ($definitions as $definition) {
			$files[] = Framework::$autoLoader->getFilename($definition);
		}
		return array_unique($files);
	}

}

?>
