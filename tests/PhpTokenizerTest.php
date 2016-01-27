<?php

/**
 * PhpTokenizerTest
 */

namespace Sledgehammer;

/**
 * @package Core
 */
class PhpTokenizerTest extends TestCase {

    function test_skipped() {
        $this->markTestSkipped('Not really unittests (No assertions on the output)');
    }

    function donttest_tokenizer() {
        $filename = Framework::$autoloader->getFilename('App');
        $this->assertEqualTokenizer($filename);

        try {
            $tokenizer = new PhpTokenizer(file_get_contents($filename));
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
            $tokenIterator = new PhpTokenizer($content);
            $mergedTokens = '';
            $tokens = [];
            foreach ($tokenIterator as $token) {
                $mergedTokens .= $token[1];
                $tokens[] = $token;
            }
            $this->assertEquals($content, $mergedTokens, 'Input should match all tokens combined (file: "' . $filename . '")');
        } catch (\Exception $e) {
            report_exception($e);
            ob_flush();
            $this->fail($e->getMessage());
        }
    }

    private function getDefinitionFiles() {
        $definitions = Framework::$autoloader->getDefinitions();
        $files = [];
        foreach ($definitions as $definition) {
            $files[] = Framework::$autoloader->getFilename($definition);
        }
        return array_unique($files);
    }

}

?>
