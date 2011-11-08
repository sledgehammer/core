<?php
/**
 * a PHPTokenizer that helps to identify the class and interface names.
 *
 * Tokens:
 *   T_HTML        Inline html output
 *   T_PHP         PHP-code that issn't a type or namespace definition
 *   T_NAMESPACE   The name of a namespace, A empty string '' indicates a global scope
 *   T_USE         A namespace or a classname including a namespace
 *   T_USE_ALIAS   The alias of for the T_USE namespace
 *   T_INTERFACE   An interface that is defined
 *   T_CLASS       A class that is defined
 *   T_EXTENDS     The parent class/interface
 *   T_IMPLEMENTS  The interface(s) that are implemented
 *   T_FUNCTION    A function/method that is defined
 *   T_TYPE_HINT   The type that hinted in the function or catch block.
 *   T_PARAMETER   A parameter/variable of the defined function
 *   T_PARAMETER_VALUE  The default value of the parameter
 *
 *   T_OBJECT       An class that is used in the code
 *   T_CALL         An global function that is called in the code
 *   T_METHOD_CALL  An method that is called in the code
 *
 * @package Core
 */
namespace SledgeHammer;
class PHPTokenizer extends Object implements \Iterator {

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
	 * @var int
	 */
	private $tokenIndex;
	/**
	 * @var array
	 */
	private $tokens;
	private $lineNumber;
	private $arrayDepth;


	/**
	 *
	 * @param string $contents The contents of a php script/file
	 */
	function __construct($contents) {
		$previousError = error_get_last();
		$this->tokens = token_get_all($contents);
		$error = error_get_last();
		if ($error !== $previousError) {
			notice($error['type'], $error['message']);
		}
	}

	function rewind() {
		$this->tokenIndex = 0;
		$this->state = 'HTML';
		$this->key = -1;
		$this->current = null;
		$this->lineNumber = 1;
		if (count($this->tokens) == 0) {
			$this->validState = 'INVALID';
		} else {
			$this->validState = 'VALID';
			$this->next();
		}
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
		$line =  $this->lineNumber;

		for (; $this->tokenIndex < $count; $this->tokenIndex++) {
			$token = $this->tokens[$this->tokenIndex];
			if ($this->tokenIndex === ($count - 1)) { // laatste token?
				$nextToken = false;
				$this->validState = 'LAST';
			} else {
				$nextToken = $this->tokens[$this->tokenIndex + 1];
			}
			if (is_array($token)) {
				$tokenContents = $token[1];
				$this->lineNumber = $token[2];
			} else {
				$tokenContents = $token;
			}
			$value .= $tokenContents;
			$method = 'parse_'.$this->state;
			if (method_exists($this, $method) == false) {
				$this->failure('Invalid state');
				break;
			}
			$result = $this->$method($token, $nextToken);
			switch ($result['action']) {

				case 'CONTINUE': // $token belongs to the current state
					break;

				case 'CONTINUE_AS':
					$this->state = $result['state'];
					break;

				case 'LAST_TOKEN': // The current $token is the last token of the current state.
					$this->state = $result['state'];
					$this->current = array($result['token'], $value, $line);
					$this->tokenIndex++;
					return;

				case 'SWITCH': // The current token belongs to another state
					if (empty($result['token'])) {
						$this->failure('return-token is required for a SWITCH');
					}
					$this->state = $result['state'];
					if ($value != $tokenContents) {
						$this->current = array($result['token'], substr($value, 0, 0 - strlen($tokenContents)), $line);
						$this->validState = 'VALID';
						return;
					}
					break;

				case 'RESCAN': // The current token belongs to another state
					$this->state = $result['state'];
					if ($value == $tokenContents) {
						$value = '';
					} else {
						$value = substr($value, 0, 0 - strlen($tokenContents));
					}
					$this->tokenIndex--;
					break;

				case 'SINGLE_TOKEN':
					if (isset($result['state'])) { // state is optional
						$this->failure('Can\'t change state for a SINGLE_TOKEN');
					}
					if ($value == $tokenContents) {
						$this->current = array($result['token'], $value, $line);
						$this->tokenIndex++;
						return;
					} else {
						$this->current = array($result['tokenBefore'], substr($value, 0, 0 - strlen($tokenContents)), $line);
						$this->validState = 'VALID';
						return;
					}
					break;

				case 'KEYWORD': // Switch state and skip whitespace
					$this->state = $result['state'];
					$this->expectToken($nextToken, T_WHITESPACE);
					$value .= $nextToken[1];
					if (isset($result['token'])) {
						$this->current = array($result['token'], $value, $line);
						$this->tokenIndex += 2;
						return;
					}
					$this->tokenIndex++;
					break;

				case 'OPERATOR': // Switch state and optionally skip whitespace
					$this->state = $result['state'];
					if ($nextToken[0] == T_WHITESPACE) {
						$value .= $nextToken[1];
						if (isset($result['token'])) {
							$this->current = array($result['token'], $value, $line);
							$this->tokenIndex += 2;
							return;
						}
						$this->tokenIndex++;
					} elseif (isset($result['token'])) {
						$this->current = array($result['token'], $value, $line);
						$this->tokenIndex++;
						return;
					}
					break;

				case 'EMPTY_TOKEN':
					$this->state = $result['state'];
					if ($tokenContents != $value) {
						$this->failure('Not yet supported');
					}
					$this->current = array($result['token'], '', $line);
					return;

				default:
					$this->failure('Unknown action: '.$result['action']);
					break;
			}
		}
		if ($value === '') {
			return;
		}
		switch ($this->state) {

			case 'HTML':
				break;

			case 'PHP':
				$this->current = array('T_PHP', $value, $line);
				break;

			default:
				$this->failure('Unexpected file ending (state: "'.$this->state.'")');
				break;
		}
	}

	private function parse_HTML($token, $nextToken) {
		if ($nextToken === false) {
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_HTML',
				'state' => 'EOF'
			);
		}
		if (in_array($nextToken[0], array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO))) {
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_HTML',
				'state' => 'PHP',
			);
		}
		if (in_array($token[0], array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO))) { // Dit is geen HTML token
			return array(
				'action' =>'SWITCH',
				'token' => 'T_HTML',
				'state' => 'PHP',
			);
		}
		return array(
			'action' => 'CONTINUE'
		);
	}

	private function parse_PHP($token, $nextToken) {
		if ($token[0] == T_CLOSE_TAG) { // end of php section?
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_PHP',
				'state' => 'HTML',
			);
		}
		if ($token == '{') {
			return array(
				'action' => 'SINGLE_TOKEN',
				'token' => 'T_OPEN_BRACKET',
				'tokenBefore' => 'T_PHP',
			);
		}
		if ($token == '}') {
			return array(
				'action' => 'SINGLE_TOKEN',
				'token' => 'T_CLOSE_BRACKET',
				'tokenBefore' => 'T_PHP',
			);
		}
//		if ($nextToken === false) { // end of file?
//			return array('LAST TOKEN?');
//		}

		switch ($token[0]) {
			case T_NAMESPACE:  return array('action'=> 'OPERATOR', 'token' => 'T_PHP', 'state' => 'NAMESPACE');
			case T_USE:        return array('action'=> 'KEYWORD', 'token' => 'T_PHP', 'state' => 'USE');
			case T_INTERFACE:  return array('action'=> 'KEYWORD', 'token' => 'T_PHP', 'state' => 'INTERFACE');
			case T_CLASS:      return array('action'=> 'KEYWORD', 'token' => 'T_PHP', 'state' => 'CLASS');
			case T_EXTENDS:    return array('action'=> 'KEYWORD', 'token' => 'T_PHP', 'state' => 'EXTENDS');
			case T_IMPLEMENTS: return array('action'=> 'KEYWORD', 'token' => 'T_PHP', 'state' => 'IMPLEMENTS');
			case T_FUNCTION:   return array('action'=> 'CONTINUE_AS', 'state' => 'FUNCTION');
			case T_NEW:        return array('action'=> 'KEYWORD', 'state' => 'TYPE');
			case T_INSTANCEOF: return array('action'=> 'KEYWORD', 'state' => 'TYPE');
			case T_CATCH:      return array('action' => 'CONTINUE_AS', 'state' => 'PARAMETERS');
			case T_CURLY_OPEN: return array('action' => 'CONTINUE_AS', 'state' => 'COMPLEX_VARIABLE');
			case T_DOLLAR_OPEN_CURLY_BRACES: return array('action' => 'CONTINUE_AS', 'state' => 'COMPLEX_VARIABLE');
			case T_STRING:
				if ($nextToken == '(') {
					$previousToken = $this->tokens[$this->tokenIndex - 1];
					$type = ($previousToken[0] == T_OBJECT_OPERATOR) ? 'T_METHOD_CALL': 'T_CALL';
					return array('action' => 'SINGLE_TOKEN', 'token' => $type, 'tokenBefore' => 'T_PHP');
				}
				break;
		}
		return array('action' => 'CONTINUE');
	}

	private function parse_NAMESPACE($token, $nextToken) {
		if ($token[0] == '{') { // Global namespace?
			return array('action' => 'EMPTY_TOKEN', 'token' => 'T_NAMESPACE', 'state' => 'PHP');
		}
		$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_NAMESPACE',
				'state' => 'PHP',
			);
		}
	}

	private function parse_USE($token, $nextToken) {
		if ($nextToken == ';') {
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_USE',
				'state' => 'PHP',
			);
		}
		if ($nextToken[0] == T_WHITESPACE) {
			return array(
				'action' => 'LAST_TOKEN',
				'token' => 'T_USE',
				'state' => 'USE_AS',
			);
		}
		if ($token == '(') { // ( followed by a T_VARIABLE? This is a closure use, not a namespace use
			return array('action' => 'CONTINUE_AS', 'state' => 'PHP');
		}
		$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
		return array('action' => 'CONTINUE');
	}
	private function parse_USE_AS($token, $nextToken) {
		if (in_array($token, array(';', '{'))) {
			return array('action' => 'CONTINUE_AS', 'state' => 'PHP');
		}
		if ($token[0] == T_AS) {
			return array('action' => 'KEYWORD', 'state' => 'USE_ALIAS', 'token' => 'T_PHP');

		}
		$this->expectToken($token, T_WHITESPACE);
		return array('action' => 'CONTINUE');
	}

	private function parse_USE_ALIAS($token, $nextToken) {
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
			return array('action' => 'LAST_TOKEN', 'token' => 'T_USE_ALIAS', 'state' => 'PHP');
		}
		return array('action' => 'CONTINUE');
	}

	private function parse_CLASS($token, $nextToken) {
		$this->expectToken($token, T_STRING);
		return array(
			'action' => 'LAST_TOKEN',
			'token'=>'T_CLASS',
			'state' => 'PHP'
		);
	}
	private function parse_INTERFACE($token, $nextToken) {
		$this->expectToken($token, T_STRING);
		return array(
			'action' => 'LAST_TOKEN',
			'token'=>'T_INTERFACE',
			'state' => 'PHP'
		);
	}
	private function parse_EXTENDS($token, $nextToken) {
		$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
			return array(
				'action' => 'LAST_TOKEN',
				'token'=>'T_EXTENDS',
				'state' => 'PHP'
			);
		}
		return array('action' => 'CONTINUE');
	}
	private function parse_IMPLEMENTS($token, $nextToken) {
		$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR));
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
			return array(
				'action' => 'LAST_TOKEN',
				'token'=>'T_IMPLEMENTS',
				'state' => 'IMPLEMENTS_MORE'
			);
		}
		return array('action' => 'CONTINUE');
	}
	private function parse_IMPLEMENTS_MORE($token, $nextToken) {
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR))) {
			return array(
				'action' => 'LAST_TOKEN',
				'token'=>'T_PHP',
				'state' => 'IMPLEMENTS'
			);
		}
		if ($token == '{') {
			return array(
				'action' => 'RESCAN',
				'state' => 'PHP',
			);
		}
		$this->expectTokens($token, array(T_WHITESPACE, ','));
		return array('action' => 'CONTINUE');

	}

	private function parse_FUNCTION($token, $nextToken) {
		if ($token[0] == T_STRING) {
			return array('action' => 'SINGLE_TOKEN', 'token'=> 'T_FUNCTION', 'tokenBefore' => 'T_PHP');
		}
		if ($token == '(') {
			return array('action' => 'RESCAN', 'state' => 'PARAMETERS');
		}
		$this->expectTokens($token, array(T_WHITESPACE, '&'));
		return array('action' => 'CONTINUE');
	}

	private function parse_PARAMETERS($token, $nextToken) {
		if ($token == ')') {
			return array('action' => 'CONTINUE_AS', 'state' => 'PHP');
		}
		if ($token == '=') { // A default value?
			return array('action' => 'OPERATOR', 'state' => 'PARAMETER_VALUE', 'token' => 'T_PHP');
		}
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR, T_ARRAY))) { // Type hint?
			return array('action' => 'LAST_TOKEN', 'token' => 'T_PHP', 'state' => 'PARAMETER_TYPE_HINT');
		}
		if ($token[0] == T_VARIABLE) {
			return array('action' => 'SINGLE_TOKEN', 'token' => 'T_PARAMETER', 'tokenBefore' => 'T_PHP');
		}
		$this->expectTokens($token, array(T_WHITESPACE, ',', '(', '&'));
		return array('action' => 'CONTINUE');
	}

	private function parse_PARAMETER_TYPE_HINT($token, $nextToken) {
		if ($nextToken[0] == T_WHITESPACE) {
			return array('action' => 'LAST_TOKEN', 'token' => 'T_TYPE_HINT', 'state' => 'PARAMETERS');
		}
		$this->expectTokens($token, array(T_STRING, T_NS_SEPARATOR, T_ARRAY));
		return array('action' => 'CONTINUE');
	}

	private function parse_PARAMETER_VALUE($token, $nextToken) {
		$valueTokens = array(T_STRING, T_LNUMBER, T_CONSTANT_ENCAPSED_STRING);
		if (in_array($token[0], $valueTokens)) {
			return array('action' => 'LAST_TOKEN', 'token'=>'T_PARAMETER_VALUE', 'state' => 'PARAMETERS');
		}
		if ($token[0] == T_ARRAY) { // default parameter is a array literal?
			$this->arrayDepth = 0;
			return array('action' => 'RESCAN', 'state' => 'PARAMETER_ARRAY_VALUE');
		}
		if ($token == '-') {
			return array('action' => 'CONTINUE');
		}
		$this->dump($token);
		$this->failure('Unknown default value');
	}

	private function parse_PARAMETER_ARRAY_VALUE($token, $nextToken) {
		if ($token == '(') {
			$this->arrayDepth++;
		} elseif ($token == ')') {
			$this->arrayDepth--;
			if ($this->arrayDepth == 0) { // end of array literal?
				return array('action' => 'LAST_TOKEN', 'token'=>'T_PARAMETER_VALUE', 'state' => 'PARAMETERS');
			}
		}
		if ($this->arrayDepth == 0) {
			$this->expectTokens($token, array(T_ARRAY, T_WHITESPACE, '('));
		}
		return array('action' => 'CONTINUE');
	}


	// new X or instanceof Y
	private function parse_TYPE($token, $nextToken) {
		if (in_array($token[0], array(T_STRING, T_NS_SEPARATOR))) {
			return array('action' => 'SWITCH', 'state' => 'OBJECT', 'token' => 'T_PHP');
		}
		$this->expectToken($token, T_VARIABLE);
		return array('action' => 'CONTINUE_AS', 'state' => 'PHP');
	}

	/**
	 * Parse the classname and return to PHP state
	 */
	private function parse_OBJECT($token, $nextToken) {
		if (in_array($nextToken[0], array(T_STRING, T_NS_SEPARATOR)) == false) {
			return array('action' => 'LAST_TOKEN', 'token' => 'T_OBJECT', 'state' => 'PHP');
		}
		return array('action' => 'CONTINUE');
	}

	private function parse_COMPLEX_VARIABLE($token, $nextToken) {
		if ($token == '}') { // end of complex var
			return array('action' => 'CONTINUE_AS', 'state' => 'PHP');
		}
		if ($token == '$') { // A variable inside a variable?
			// echo "123 {${$varname}[$index]} 456";
			return array('action' => 'CONTINUE_AS', 'state' => 'INNER_COMPLEX_VARIABLE');
		}
		$this->expectTokens($token, array(T_STRING_VARNAME, T_VARIABLE, T_OBJECT_OPERATOR, T_STRING, '[', T_CONSTANT_ENCAPSED_STRING, ']'));
		return array('action' => 'CONTINUE');
	}

	private function parse_INNER_COMPLEX_VARIABLE($token, $nextToken) {
		if ($token == '}') { // end of the inner complex var
			return array('action' => 'CONTINUE_AS', 'state' => 'COMPLEX_VARIABLE');
		}
		$this->expectTokens($token, array('{', T_VARIABLE));
		return array('action' => 'CONTINUE');
	}

	/**
	 * translates the int to the token_name (371 => T_WHITESPACE)
	 * @param token $token
	 */
	private function dump($token) {
		if (is_array($token)) {
			$token[0] = token_name($token[0]);
		}
		dump($token);
	}

	private function failure($message) {
		throw new \Exception($message.' (state "'.$this->state.'" parsing line '.$this->lineNumber.')');
	}

	private function expectToken($token, $expectedToken) {
		if ($this->isEqual($token, $expectedToken) == false) {
			$this->failure('Unexpected token: '.$this->tokenName($token).', expecting "'.$this->tokenName($expectedToken).'"');
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
		$this->failure('Unexpected token: '.$this->tokenName($token).', expecting "'.human_implode('" or "', $names, '", "').'"');
	}

	private function tokenName($token) {
		if (is_array($token)) {
			return token_name($token[0]).' "'.$token[1].'"';
		}
		if (is_int($token)) {
			return token_name($token);
		}
		return '"'.$token.'"';
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
