<?php
/**
 * a PHPTokenizer that helps to identify the class and interface names. 
 * 
 * Tokens:
 *   T_HTML
 *   T_PHP          PHP-code that issn't a type or namespace definition. 
 *   T_NAMESPACE    The name of a namespace, A empty string '' indicates a global scope.
 *   T_USE          The name of a namespace or a classname including a namespace
 *   T_USE_ALIAS    The alias of for the T_USE namespace 
 *   T_INTERFACE    The name of the interface
 *   T_CLASS        The name of the class
 *   T_EXTENDS      The name of the parent class/interface
 *   T_IMPLEMENTS   The name of the interface
 * 
 * @package core
 */
namespace SledgeHammer;
class PHPTokenizer extends Object implements \Iterator {
	
	/**
	 * @var array 
	 */
	private $tokens;
	/**
	 * @var int
	 */
	private $tokenIndex;
	/**
	 * @var string 
	 */
	private $state = 'INIT';
	/**
	 * @var int 
	 */
	private $key;
	/**
	 * @var array 
	 */
	private $current;
	/**
	 * @var enum 'VALID|LAST|INVALID' 
	 */
	private $validState = 'INVALID';


	/**
	 *
	 * @param string $contents The contents of a php script/file
	 */
	function __construct($contents) {
		$this->tokens = token_get_all($contents);
	}
	
	function rewind() {
		$this->tokenIndex = 0;
		$this->state = 'HTML';
		$this->key = -1;
		$this->current = null;
		$this->validState = 'VALID';
		$this->next();
	}
	
	function valid() {
		return ($this->validState !== 'INVALID');
	}

	function key() {
		return $this->key;
	}
	
	function current() {
		return $this->current;
		
	}
	
	function next() {
		if ($this->validState ===  'LAST') {
			$this->validState = 'INVALID';
			$this->current = null;
			return;
		}
		$this->key++;
		$count = count($this->tokens);
		$value = '';
		
		for (; $this->tokenIndex < $count; $this->tokenIndex++) {
			$i = $this->tokenIndex;
			$token = $this->tokens[$i];
			if ($i === ($count - 1)) { // laatste token?
				$nextToken = false;
			} else {
				$nextToken = $this->tokens[$i + 1];
			}
			$value .= is_array($token) ? $token[1] : $token;
			switch ($this->state) {
				
				case 'HTML':
					if (in_array($token[0], array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) && $value === $token[1]) { // Dit is geen HTML token
						$this->state = 'PHP';
						continue;
					}
					if ($nextToken === false) {
						$this->current = array('T_HTML', $value);
						$this->validState = 'LAST';

						return; // end of file
					}
					if ($nextToken[0] === T_OPEN_TAG) {
						$this->current = array('T_HTML', $value);
						$this->tokenIndex++;
						return; // token complete
					}
					break;
					
				case 'PHP':
					if ($token[0] == T_CLOSE_TAG) {
						$this->state = 'HTML';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return; // token complete
					}
					if ($token[0] == T_NAMESPACE) {
						$this->state = 'NAMESPACE_START';
					}
					if ($token[0] == T_USE) {
						$this->state = 'USE_START';
					}
					if ($token[0] == T_INTERFACE) {
						$this->state = 'INTERFACE';
					}
					if ($token[0] == T_CLASS) {
						$this->state = 'CLASS';
					}
					if ($token[0] == T_EXTENDS) {
						$this->state = 'EXTENDS_START';
					}
					if ($token[0] == T_IMPLEMENTS) {
						$this->state = 'IMPLEMENTS_START';
					}
					break;
					
				case 'NAMESPACE_START':
					if ($nextToken[0] == '{') { // Global namespace?
						$this->state = 'NAMESPACE';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					if ($nextToken[0] == T_STRING) {
						$this->state = 'NAMESPACE';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectToken($token, T_WHITESPACE);
					break;
					
				case 'NAMESPACE':
					if ($value === '{') {
						// Global namespace
						$this->state = 'PHP';
						$this->current = array('T_NAMESPACE', ''); // empty NAMESPACE token, om aan te geven dat de global namespace geactiveerd wordt.
						return;
					}
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
						$this->state = 'PHP';
						$this->current = array('T_NAMESPACE', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
					break;
					
				case 'USE_START':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'USE';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
					break;
					
				case 'USE':
					if ($nextToken == ';' || $nextToken[0] == T_WHITESPACE) {
						if ($nextToken != ';') {
							$this->state = 'USE_AS';
						} else {
							$this->state = 'PHP';
						}
						$this->current = array('T_USE', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
					break;
					
				case 'USE_AS':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'USE_ALIAS';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectTokens($token, array(T_WHITESPACE, T_AS));
					break;
					
				case 'USE_ALIAS':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
						$this->state = 'PHP';
						$this->current = array('T_USE_AS', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
					break;
					
				case 'INTERFACE':
					if ($nextToken[0] == T_STRING) {
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					if ($token[0] == T_STRING) {
						$this->state = 'PHP';
						$this->current = array('T_INTERFACE', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectToken($token, T_STRING);
					break;
					
				case 'CLASS':
					if ($nextToken[0] == T_STRING) {
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					if ($token[0] == T_STRING) {
						$this->state = 'PHP';
						$this->current = array('T_CLASS', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectToken($token, T_STRING);
					break;
				
				case 'EXTENDS_START':
					//$this->dump($token);
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'EXTENDS';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectToken($token, T_WHITESPACE);
					break;
					
				case 'EXTENDS':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
						$this->state = 'EXTENDS_SEPARATOR';
						$this->current = array('T_EXTENDS', $value);
						$this->tokenIndex++;
						return;
					}
					break;
					
				case 'EXTENDS_SEPARATOR':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'EXTENDS';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					if ($token == '{') {
						$this->state = 'PHP';
						break;
					}
					if ($token[0] == T_IMPLEMENTS) {
						$this->state = "IMPLEMENTS_START";
						continue;
					}
					$this->expectTokens($token, array(T_WHITESPACE, ','));
					break;
					
				case 'IMPLEMENTS_START':				
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'IMPLEMENTS';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					$this->expectToken($token, T_WHITESPACE);
					break;
				
				case 'IMPLEMENTS':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
						$this->state = 'IMPLEMENTS_SEPARATOR';
						$this->current = array('T_IMPLEMENTS', $value);
						$this->tokenIndex++;
						return;
					}
					break;
				
				case 'IMPLEMENTS_SEPARATOR':
					if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
						$this->state = 'IMPLEMENTS';
						$this->current = array('T_PHP', $value);
						$this->tokenIndex++;
						return;
					}
					if ($token == '{') {
						$this->state = 'PHP';
						break;
					}
					$this->expectTokens($token, array(T_WHITESPACE, ','));
					break;
					
				default:
					$this->failure('Invalid state');
					break;
			}
		}
		$this->validState = 'LAST';
		if ($value === '') {
			return;
		}
		switch ($this->state) {
				
			case 'PHP':
				$this->current = array('T_PHP', $value);
				break;
			
			default:
				$this->failure('Unexpected file ending (state: "'.$this->state.'")');
				break;
		}
	}
	
	private function dump($token) {
		if (is_array($token)) {
			$token[0] = token_name($token[0]);
		}
		dump($token);
	}
	
	private function failure($message) {
		$suffix = '';
		for ($i = $this->tokenIndex; $i >= 0; $i--) {
			if (is_array($this->tokens[$i])) {
				$suffix .= ' (state "'.$this->state.'" parsing line '.$this->tokens[$i][2].')';
				break;
			}
		}
		throw new \Exception($message.$suffix);
	}
	
	private function expectToken($token, $expectedToken) {
		if ($this->isEqual($token, $expectedToken) == false) {
			$this->failure('Unexpected token: "'.$this->tokenName($token).'", expecting "'.$this->tokenName($expectedToken).'"');
		}
	}
	
	/**
	 * Check if the $token is one of the expected tokens
	 */
	private function expectTokens($token, $expectedTokens) {
		foreach ($expectedTokens as $expectedToken) {
			if ($this->isEqual($token, $expectedToken)) {
				return;
			}
		}
		$names = array();
		foreach ($expectedTokens as $expectedToken) {
			$names[] = $this->tokenName($expectedToken);
		}
		$this->failure('Unexpected token: "'.$this->tokenName($token).'", expecting "'.human_implode('" or "', $names, '", "').'"');
	}
	
	private function tokenName($token) {
		if (is_array($token)) {
			return token_name($token[0]);
		}
		if (is_int($token)) {
			return token_name($token);
		}
		return $token;
	}

	/**
	 *
	 * @param string|array $token
	 * @param string|int $expectedToken 
	 */
	private function isEqual($token, $expectedToken) {
		if (is_int($expectedToken)) {
			return ($token[0] === $expectedToken );
		}
		if (is_string($expectedToken)) {
			return ($token === $expectedToken );
		}
		throw new \Exception('Invalid value for parameter: $expectedToken');
	}
}
?>
