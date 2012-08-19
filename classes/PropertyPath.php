<?php
/**
 * PropertyPath
 */
namespace Sledgehammer;
/**
 * An helper class that resolves properties inside arrays and objects based on a path.
 * Inspired by XPath
 * But implemented similar to "Property Path Syntax" in Silverlight
 * @link http://msdn.microsoft.com/en-us/library/cc645024(v=VS.95).aspx
 *
 * @todo? Wildcards for getting and setting a range of properties ''
 * @todo? exists($path) method
 *
 * Format:
 *   'abc' maps to element $data['abc'] or property $data->abc.
 *   '->abc' maps to property $data->abc
 *   '[abc]' maps to element  $data['abc']
 *   'abc.efg' maps to  $data['abc']['efg], $data['abc']->efg, $data->abc['efg] or $data->abc->efg
 *   '->abc->efg' maps to property $data->abc->efg
 *   '->abc[efg]' maps to property $data->abc[efg]
 *
 *  Specials operators:
 *   '[*]' match all elements. Example: 'devices[*].id' returns an array with just the device ids.
 *   '.' just returns the $data.
 *
 * Use "\" to escape characters. Example "[complex\[key\]]" look up $data["complex[key]"]
 *
 * Add "?" after an identifier to allow missing properties/elements. Example: "[abc?]" or "abc?" checks if the abc property/element exists. (and returns null if they don't)
 *
 * @package Core
 */
class PropertyPath extends Object {

	const TYPE_ANY = 'ANY'; // object-property or array-element
	const TYPE_PROPERTY = 'PROPERTY';
	const TYPE_ELEMENT = 'ELEMENT';
	const TYPE_METHOD = 'METHOD';
	const TYPE_OPTIONAL = 'ANY?';
	const TYPE_OPTIONAL_PROPERTY = 'PROPERTY?';
	const TYPE_OPTIONAL_ELEMENT = 'ELEMENT?';
	const TYPE_SUBPATH = 'SUBPATH';
	const TYPE_SELF = 'SELF';

	// Tokens
	const T_STRING = 'T_STRING';
	const T_DOT = 'T_DOT';
	const T_ARROW = 'T_ARROW';
	const T_OPTIONAL = 'T_OPTIONAL';
	const T_BRACKET_OPEN = 'T_BRACKET_OPEN';
	const T_BRACKET_CLOSE = 'T_BRACKET_CLOSE';
	const T_PARENTHESES = 'T_PARENTHESES';
	const T_ALL_ELEMENTS = 'T_ALL_ELEMENTS';

	/**
	 * Retrieve $path from $data.
	 *
	 * @param string $path
	 * @param array|object $data
	 * @return mixed
	 */
	static function get($path, $data) {
		if (is_string($path) === false) {
			deprecated('The $path & $data parameters were swapped');
			$tmp = $data;
			$data = $path;
			$path = $tmp;
		}
		$parts = self::parse($path);
		foreach ($parts as $part) {
			switch ($part[0]) {

				case self::TYPE_ANY:
					if (is_object($data)) {
						$data = $data->{$part[1]};
					} elseif (is_array($data)) {
						$data = $data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object or array');
						return;
					}
					break;

				case self::TYPE_ELEMENT:
					if (is_array($data) || (is_object($data) && $data instanceof \ArrayAccess)) {
						$data = $data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an array');
						return;
					}
					break;

				case self::TYPE_PROPERTY:
					if (is_object($data)) {
						$data = $data->{$part[1]};
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				case self::TYPE_METHOD:
					if (is_object($data)) {
						$data = $data->{$part[1]}();
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				case self::TYPE_OPTIONAL:
					if (is_object($data)) {
						if (isset($data->{$part[1]})) {
							$data = $data->{$part[1]};
						} else {
							return null;
						}
					} elseif (is_array($data)) {
						if (array_key_exists($part[1], $data)) {
							$data = $data[$part[1]];
						} else {
							return null;
						}
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object or array');
						return;
					}
					break;

				case self::TYPE_OPTIONAL_ELEMENT:
					if (is_array($data) || (is_object($data) && ($data instanceof \ArrayAccess || $data instanceof \SimpleXMLElement))) {
						if (isset($data[$part[1]])) {
							$data = $data[$part[1]];
						} else {
							return null;
						}
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an array');
						return;
					}
					break;

				case self::TYPE_OPTIONAL_PROPERTY:
					if (is_object($data)) {
						if (isset($data->{$part[1]})) {
							$data = $data->{$part[1]};
						} else {
							return null;
						}
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				case self::TYPE_SUBPATH:
					if (is_object($data) || is_array($data)) {
						$items = array();
						foreach ($data as $key => $item) {
							$items[$key] = self::get($part[1], $item);
						}
						return $items;
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object or array');
						return;
					}
					break;

				case self::TYPE_SELF:
					return $data;

				default:
					throw new \Exception('Unsupported type: '.$part[0]);
			}
		}
		return $data;
	}

	/**
	 * Retrieve a reference to a value.
	 *
	 * @param string $path
	 * @param array|object $data
	 * @return mixed
	 */
	static function &getReference($path, &$data) {
		if (is_string($path) === false) {
			warning('The $path & $data parameters were swapped');
		}
		$parts = self::parse($path);
		foreach ($parts as $part) {
			switch ($part[0]) {

				case self::TYPE_ANY:
					if (is_object($data)) {
						$data = &$data->{$part[1]};
					} elseif (is_array($data)) {
						$data = &$data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object or array');
						return;
					}
					break;

				case self::TYPE_ELEMENT:
					if (is_array($data)) {
						$data = &$data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an array');
						return;
					}
					break;

				case self::TYPE_PROPERTY:
					if (is_object($data)) {
						$data = &$data->{$part[1]};
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				case self::TYPE_METHOD:
					if (is_object($data)) {
						$data = &$data->{$part[1]}();
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				default:
					throw new \Exception('Unsupported type: '.$part[0]);
			}
		}
		return $data;
	}

	/**
	 * Set $path to $value in $data.
	 *
	 * @param string $path
	 * @param mixed $value
	 * @param array|object $data
	 */
	static function set($path, $value, &$data) {
		if (is_string($path) === false && is_string($data)) {
			warning('The $path, $value & $data parameters were swapped');
		}
		$parts = self::parse($path);
		$last = array_pop($parts);
		foreach ($parts as $part) {
			switch ($part[0]) {

				case self::TYPE_ANY:
					if (is_object($data)) {
						$data = &$data->{$part[1]};
					} elseif (is_array($data)) {
						$data = &$data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object or array');
						return;
					}
					break;

				case self::TYPE_ELEMENT:
					if (is_array($data)) {
						$data = &$data[$part[1]];
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an array');
						return;
					}
					break;

				case self::TYPE_PROPERTY:
					if (is_object($data)) {
						$data = &$data->{$part[1]};
					} else {
						notice('Unexpected type: '.gettype($data).', expecting an object');
						return;
					}
					break;

				default:
					throw new \Exception('Unsupported type: '.$part[0]);
			}
		}

		switch ($last[0]) {

			case self::TYPE_ANY:
			case self::TYPE_OPTIONAL:
				if (is_object($data)) {
					$data->{$last[1]} = $value;
				} else {
					$data[$last[1]] = $value;
				}
				break;

			case self::TYPE_ELEMENT:
			case self::TYPE_OPTIONAL_ELEMENT:
				$data[$last[1]] = $value;
				break;

			case self::TYPE_PROPERTY:
			case self::TYPE_OPTIONAL_PROPERTY:
				$data->{$last[1]} = $value;
				break;

			default:
				throw new \Exception('Unsupported type: '.$last[0]);
		}
	}

	/**
	 * Copy values from the $source to the $target using the paths in the $mapping array.
	 * Keys in the $mapping array are used as **targetpath**
	 * This might be counterintuitive, but allows 1 path in the $source to be mapped to several paths in the $target
	 *
	 * Tip: Use array_flip($mapping) for a reverse mapping.
	 *
	 * @param array|stdClass $source
	 * @param array|stdClass $target
	 * @param array $mapping array(
	 *   pathInTarget => pathInSource,
	 *   to => from
	 * )
	 */
	static function map($source, &$target, $mapping) {
		foreach ($mapping as $to => $from) {
			$value = PropertyPath::get($from, $source);
			PropertyPath::set($to, $value, $target);
		}
	}

	/**
	 * Escapes  an identifier for use in a path.
	 * Escapes all path-modifiers "[", ".", "->", "()", etc.
	 * "a[b]c" => "a\[b\]c"
	 *
	 * @param type $identifier
	 * @return string
	 */
	static function escape($identifier) {
		$escaped = str_replace('\\', '\\\\', $identifier); // escape the escape-character.
		return strtr($escaped, array(
				'.' => '\.',
				'[' => '\[',
				']' => '\[',
				'->' => '\->',
				'()' => '\()',
			));
	}

	/**
	 * Build a path string from a (mutated) parsed path.
	 *
	 * @param array $parsedPath  Output from PropertyPath::parse($path)
	 * @return string path
	 */
	static function assemble($parsedPath) {
		$path = '';
		// @todo Excape values
		$tokens = array_values($parsedPath); // Force to first $index to 0
		foreach ($tokens as $index => $token) {
			$value = self::escape($token[1]);
			switch ($token[0]) {
				case self::TYPE_ANY:
					if ($index != 0) {
						$path .= '.';
					}
					$path .= $value;
					break;

				case self::TYPE_PROPERTY:
					$path .= '->'.$value;
					break;

				case self::TYPE_ELEMENT:
					$path .= '['.$value.']';
					break;

				case self::TYPE_METHOD:
					$path .= $value.'()';
					break;

				case self::TYPE_OPTIONAL:
					if ($index != 0) {
						$path .= '.';
					}
					$path .= $value.'?';
					break;

				case self::TYPE_OPTIONAL_PROPERTY:
					$path .= '->'.$value.'?';
					break;

				case self::TYPE_OPTIONAL_ELEMENT:
					$path .= '['.$value.'?]';
					break;

				default:
					warning('Unsupported token', $token);
			}
		}
		return $path;
	}

	/**
	 * Compile a path into a closure function.
	 * The generated function expected 1 parameter (The $data for the property get)
	 *
	 * @param string $path
	 * return Closure
	 */
	static function compile($path) {
		static $cache = array();
		if (isset($cache[$path])) {
			return $cache[$path];
		}
		$cache[$path] = function ($data) use ($path) {
			return PropertyPath::get($path, $data);
		};
		return $cache[$path];
	}
	/**
	 * Converts the tokens into a parsed path.
	 *
	 * @param string $tokens
	 * @return array
	 */
	static function parse($path) {
		// Check if the path is cached
		static $cache = array();
		if (isset($cache[$path])) {
			return $cache[$path];
		}
		$tokens = self::tokenize($path);
		if (count($tokens) === 0) {
			notice('Path is empty');
			return false;
		}
		$compiled = array();
		$length = count($tokens);
		$first = true;
		for ($i = 0; $i < $length; $i++) {
			$token = $tokens[$i];
			if (($i + 1) === $length) {
				$nextToken = array('T_END', '');
			} else {
				$nextToken = $tokens[$i + 1];
			}
			switch ($token[0]) {

				// TYPE_ANY
				case self::T_STRING;
					if ($first === false) { // Invalid chain? "[el]any" instead of "[el].any"
						notice('Invalid chain, expecting a ".", "->" or "[" before "'.$token[1].'"');
						return false;
					}

					if ($nextToken[0] === self::T_OPTIONAL) {
						$compiled[] = array(self::TYPE_OPTIONAL, $token[1]);
						$i++;
					} else {
						$compiled[] = array(
							self::TYPE_ANY,
							$token[1],
						);
					}
					break;

				// Chained T_ANY
				case self::T_DOT:
					if ($first) {
						if ($nextToken[0] !== 'T_END') {
							notice('Invalid "." in the path', 'Use "." for chaining, not at the beginning of a path');
							return false;
						}
						$compiled[] = array(self::TYPE_SELF, $token[1]);
						break;
					}
					if ($nextToken[0] !== self::T_STRING) {
						notice('Invalid "'.$token[1].'" in path, expecting an identifier after "."');
						return false;
					}
					if (($i + 2) !== $length && $tokens[$i + 2][0] === self::T_OPTIONAL) {
						$compiled[] = array(self::TYPE_OPTIONAL, $nextToken[1]);
						$i += 2;
					} else {
						$compiled[] = array(self::TYPE_ANY, $nextToken[1]);
						$i++;
					}
					break;

				// TYPE_PROPERTY
				case self::T_ARROW:
					if ($nextToken[0] !== self::T_STRING) {
						notice('Invalid "'.$token[1].'" in path, expecting an identifier after an "->"');
						return false;
					}
					if (($i + 2) !== $length && $tokens[$i + 2][0] === self::T_OPTIONAL) {
						$compiled[] = array(self::TYPE_OPTIONAL_PROPERTY, $nextToken[1]);
						$i += 2;
					} else {
						if (preg_match('/^[a-z_]{1}[a-z_0-9]*$/i', $nextToken[1]) != 1) {
							notice('Invalid property identifier "'.$nextToken[1].'" in path "'.$path.'"');
						}
						$compiled[] = array(self::TYPE_PROPERTY, $nextToken[1]);
						$i++;
					}
					break;

				// TYPE_ELEMENT
				case self::T_BRACKET_OPEN:
					if ($nextToken[0] !== self::T_STRING) {
						notice('Unexpected token "'.$token[0].'" in path, expecting T_STRING after ".["', $token);
						return false;
					}
					if (($i + 2) === $length) {
						notice('Unmatched brackets, missing a "]" in path after "'.$nextToken[1].'"');
						return false;
					}
					if ($tokens[$i + 2][0] === self::T_OPTIONAL) {
						if (($i + 2) === $length || $tokens[$i + 3][0] !== self::T_BRACKET_CLOSE) {
							notice('Unmatched brackets, missing a "]" in path after "'.$nextToken[1].'?"');
							return false;
						}
						$compiled[] = array(self::TYPE_OPTIONAL_ELEMENT, $nextToken[1]);
						$i += 3;
					} else {
						if ($tokens[$i + 2][0] !== self::T_BRACKET_CLOSE) {
							notice('Unmatched brackets, missing a "]" in path after "'.$nextToken[1].'"');
							return false;
						}
						$compiled[] = array(self::TYPE_ELEMENT, $nextToken[1]);
						$i += 2;
					}
					break;

				case self::T_ALL_ELEMENTS: // [*]
					if ($nextToken[0] === self::T_STRING) {
						notice('Invalid chain, expecting a ".", "->" or "[" before "'.$nextToken[1].'"');
						return false;
					}
					$offset = $i + 1;
					if ($nextToken[0] === self::T_DOT) {
						$offset++; // skip the dot to prevent "Invalid beginning error"
					}
					// Merge remaining tokens as subpath
					$subpath = '';
					$tokens = array_slice($tokens, $offset);
					foreach ($tokens as $token) {
						$subpath .= $token[1];
					}
					$compiled[] = array(self::TYPE_SUBPATH, $subpath);
					return $compiled;

				default:
					notice('Unexpected token: "'.$token[0].'"');
					return false;
			}
			$first = false;
		}
		$cache[$path] = $compiled;
		return $compiled;
	}

	/**
	 * Convert the path into tokens for the parser.
	 *
	 * @param string $path
	 */
	private static function tokenize($path) {
		$path = (string) $path;
		$tokens = array();
		$length = strlen($path);
		$buffer = '';
		for ($i = 0; $i < $length; $i++) {
			$char = $path[$i];
			switch ($char) {

				case '\\':
					if (($i + 1) === $length) { // is '\' the last character?
						$buffer .= $char;
						break;
					}
					if (in_array($path[$i + 1], array('.', '-', '[', ']', '?', '(', '\\', '*'))) {
						$buffer .= $path[$i + 1];
						$i++;
					}
					break;

				case '.':
					$tokens[] = array(self::T_STRING, $buffer);
					$tokens[] = array(self::T_DOT, '.');
					$buffer = '';
					break;

				case '[':
					if (($i + 1) === $length) { // is '[' the last character?
						$buffer .= $char;
						break;
					}
					$tokens[] = array(self::T_STRING, $buffer);
					$buffer = '';
					if (($i + 2) < $length && $path[$i + 1].$path[$i + 2] == '*]') { // [*] ?
						$tokens[] = array(self::T_ALL_ELEMENTS, '[*]');
						$i += 2;
					} else {
						$tokens[] = array(self::T_BRACKET_OPEN, '[');
					}
					break;

				case ']':
					$tokens[] = array(self::T_STRING, $buffer);
					$tokens[] = array(self::T_BRACKET_CLOSE, ']');
					$buffer = '';
					break;

				case '?':
					$tokens[] = array(self::T_STRING, $buffer);
					$tokens[] = array(self::T_OPTIONAL, '?');
					$buffer = '';
					break;

				case '-': // ->
					if (($i + 1) === $length) { // is '-' the last character?
						$buffer .= $char;
						break;
					}
					if ($path[$i + 1] !== '>') {
						$buffer .= $char;
					} else {
						// Arrow "->" detected
						$tokens[] = array(self::T_STRING, $buffer);
						$tokens[] = array(self::T_ARROW, '->');
						$buffer = '';
						$i++;
					}
					break;

				case '(': // ->
					if (($i + 1) === $length) { // is '(' the last character?
						$buffer .= $char;
						break;
					}
					if ($path[$i + 1] !== ')') {
						$buffer .= $char;
					} else {
						// parentheses "()" detected.
						$tokens[] = array(self::T_STRING, $buffer);
						$tokens[] = array(self::T_PARENTHESES, '()');
						$buffer = '';
						$i++;
					}
					break;

				default:
					$buffer .= $char;
					break;
			}
		}
		$tokens[] = array(self::T_STRING, $buffer);
		foreach ($tokens as $key => $token) {
			if ($token[1] === '') {
				unset($tokens[$key]);
			}
		}
		return array_values($tokens);
	}

}

?>