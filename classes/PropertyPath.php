<?php
/**
 * PropertyPath
 * @package Core
 */
namespace Sledgehammer;
/**
 * PropertyPath, a helper class that resolves properties inside arrays and objects based on a path.
 * Inspired by XPath
 * But implemented similar to "Property Path Syntax" in Silverlight
 * @link http://msdn.microsoft.com/en-us/library/cc645024(v=VS.95).aspx
 *
 * @todo Escaping '[12\[34]' for key '12[34'
 * @todo? Wildcards for getting and setting a range of properties ''
 * @todo? $path Validation properties
 *
 * Format:
 *   'abc'  maps to element $data['abc'] or property $data->abc.
 *   '->abc' maps to property $data->abc
 *   '[abc] maps to element  $data['abc']
 *   'abc.efg'  maps to  $data['abc']['efg], $data['abc']->efg, $data->abc['efg] or $data->abc->efg
 *   '->abc->efg' maps to property $data->abc->efg
 *   '->abc[efg]' maps to property $data->abc[efg]
 */
class PropertyPath extends Object {

	const TYPE_PROPERTY = 'PROPERTY';
	const TYPE_ELEMENT = 'ELEMENT';
	const TYPE_ANY = 'ANY'; // object-property or array-element
	const TYPE_METHOD = 'METHOD';
	const CHAIN = 'CHAIN';

	/**
	 *
	 * @param array|object $data
	 * @param string $path
	 * @return mixed
	 */
	static function get($data, $path) {
		$parts = self::compile($path);
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

				default:
					throw new \Exception('Unsupported type: '.$part[0]);
			}
		}
		return $data;
	}

	/**
	 *
	 * @param array|object $data
	 * @param string $path
	 * @return mixed
	 */
	static function &getReference(&$data, $path) {
		$parts = self::compile($path);
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
	 *
	 * @param array|object $data
	 * @param string $path
	 * @param mixed $value
	 */
	static function set(&$data, $path, $value) {
		$parts = self::compile($path);
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
				if (is_object($data)) {
					$data->{$last[1]} = $value;
				} else {
					$data[$last[1]] = $value;
				}
				break;

			case self::TYPE_ELEMENT:
				$data[$last[1]] = $value;
				break;

			case self::TYPE_PROPERTY:
				$data->{$last[1]} = $value;
				break;

			default:
				throw new \Exception('Unsupported type: '.$part[0]);
		}
	}

	/**
	 * Compile the path
	 *
	 * @param string $path
	 * @return array
	 */
	static function compile($path) {
		// Check if the path is cached
		static $cache = array();
		if (isset($cache[$path])) {
			return $cache[$path];
		}
		$parts = self::compilePath($path, self::TYPE_ANY);
		// Validate parts
		foreach ($parts as $part) {
			if ($part[0] == self::TYPE_PROPERTY && preg_match('/^[a-z_]{1}[a-z_0-9]*$/i', $part[1]) != 1) {
				notice('Invalid property identifier "'.$part[1].'" in path "'.$path.'"');
			}
			if ($part[0] == self::TYPE_METHOD && preg_match('/^[a-z_]{1}[a-z_0-9]*$/i', $part[1]) != 1) {
				notice('Invalid property identifier "'.$part[1].'" in path "'.$path.'"');
			}
		}
		$cache[$path] = $parts;
		return $parts;
	}

	/**
	 * Build a path string from a (mutated) compiled path.
	 *
	 * @param array $compiledPath
	 * @return string path
	 */
	static function assemble($compiledPath) {
		$path = '';
		// @todo Excape values
		$tokens = array_values($compiledPath); // Force to first $index to 0
		foreach ($tokens as $index => $token) {
			switch ($token[0]) {
				case self::TYPE_ANY:
					if ($index != 0) {
						$path .= '.';
					}
					$path .= $token[1];
					break;

				case self::TYPE_PROPERTY:
					$path .= '->'.$token[1];
					break;

				case self::TYPE_ELEMENT:
					$path .= '['.$token[1].']';
					break;

				default:
					warning('Unsupported token', $token);
			}
		}
		return $path;
	}

	/**
	 *
	 * @param string $path
	 * @param TYPE $type start type
	 * @return array
	 */
	private static function compilePath($path, $type) {
		$path = (string) $path;
		if ($path === '') {
			notice('Path is empty');
			return array();
		}
		$tokens = array();
		$arrowPos = self::arrowPosition($path);
		$bracketPos = self::openBracketPosition($path);
		$dotPos = self::dotPosition($path);
		$parenthesesPos = self::parenthesesPosition($path);
		if ($type === self::CHAIN) {
			if ($dotPos === 0) {
				return self::compilePath(substr($path, 1), self::TYPE_ANY);
			}
			if ($arrowPos !== 0 && $bracketPos !== 0) {
				notice('Invalid chain, expecting a ".", "->" or "[" before "'.$path.'"');
			}
			$type = self::TYPE_ANY;
		}
		if ($arrowPos === false && $bracketPos === false && $dotPos === false && $parenthesesPos === false) {
			$tokens[] = array($type, $path);
			return $tokens;
		}
		if ($arrowPos !== false && ($bracketPos === false || $arrowPos < $bracketPos) && ($dotPos === false || $arrowPos < $dotPos) && ($parenthesesPos === false || $arrowPos < $parenthesesPos)) {
			// PROPERTY(OBJECT)
			if ($arrowPos !== 0) {
				$tokens[] = array($type, substr($path, 0, $arrowPos));
			} elseif ($type !== self::TYPE_ANY) {
				notice('Invalid "->" in in the chain', array('path' => $path));
			}
//			if ($bracketPos === false) {
//				$secondArrowPos = self::arrowPosition($path, $arrowPos + 2);
//				// if secondArrow is 0 notice
//				if ($secondArrowPos === false) {
//					$tokens[] = array(self::TYPE_PROPERTY, substr($path, $arrowPos + 2));
//					return $tokens;
//				}
//			}
//
			return array_merge($tokens, self::compilePath(substr($path, $arrowPos + 2), self::TYPE_PROPERTY));
		}
		if ($bracketPos !== false && ($dotPos === false || $bracketPos < $dotPos) && ($parenthesesPos === false || $bracketPos < $parenthesesPos)) {
			// ELEMENT(ARRAY)
			if ($bracketPos !== 0) {
				$tokens[] = array($type, substr($path, 0, $bracketPos));
			}
			$closeBracketPos = self::closeBracketPosition($path, $bracketPos + 1);
			if ($closeBracketPos === false) {
				notice('Unmatched brackets, missing a "]" in path: "'.$path.'"');
				return array(array(self::TYPE_ANY, $path)); // return the entire path as identifier
			}
			$tokens[] = array(self::TYPE_ELEMENT, substr($path, $bracketPos + 1, $closeBracketPos - $bracketPos - 1));
			if ($closeBracketPos + 1 == strlen($path)) { // laatste element?
				return $tokens;
			}
			return array_merge($tokens, self::compilePath(substr($path, $closeBracketPos + 1), self::CHAIN));
		}
		if ($parenthesesPos !== false && ($dotPos === false || $parenthesesPos < $dotPos)) {
			if ($parenthesesPos === 0) {
				notice('no methodname given');
				return array(array(self::TYPE_ANY, $path)); // return the entire path as identifier
			}
			if ($path[$parenthesesPos + 1] != ')') {
				notice('Parameter not (yet) supported');
				return array(array(self::TYPE_ANY, $path)); // return the entire path as identifier
			}
			$tokens[] = array(self::TYPE_METHOD, substr($path, 0, $parenthesesPos));
			if ($parenthesesPos + 2 == strlen($path)) { // Laatste token?
				return $tokens;
			}
			return array_merge($tokens, self::compilePath(substr($path, $parenthesesPos + 2), self::CHAIN));
		}
		// ANY (ARRAY or OBJECT)
		if ($dotPos !== 0) {
			$tokens[] = array($type, substr($path, 0, $dotPos));
		} else {
			notice('Invalid start: "." for path: "'.$path.'"', 'Use "." for chaining, not at the beginning of a path');
		}
		return array_merge($tokens, self::compilePath(substr($path, $dotPos), self::CHAIN));
	}

	// @todo check escaped positions
	private static function dotPosition($path, $offset = null) {
		return strpos($path, '.', $offset);
	}

	private static function arrowPosition($path, $offset = null) {
		return strpos($path, '->', $offset);
	}

	private static function openBracketPosition($path, $offset = null) {
		return strpos($path, '[', $offset);
	}

	private static function closeBracketPosition($path, $offset = null) {
		return strpos($path, ']', $offset);
	}

	/**
	 * Postion of the next "("
	 */
	private static function parenthesesPosition($path, $offset = null) {
		return strpos($path, '(', $offset);
	}

}

?>
