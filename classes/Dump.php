<?php
/**
 * Dump
 */
namespace Sledgehammer;
/**
 * Parses a var_dump() and renders a syntax highlighted version of var_export()
 *
 * Usage:
 *   dump($var);
 * or in a VirtualFolder:
 *  return new Dump($var);
 *
 * (Compatible with the View interface from MVC)
 *
 * Disable xdebug's var_dump() for full-length, full-depth dump() output.
 * By adding "xdebug.overload_var_dump = Off" to the php.ini
 *
 * @package Core
 */
class Dump extends Object {

	/**
	 * The variable to display.
	 * @var mixed
	 */
	private $variable;

	/**
	 * The trace from where the `new Dump()` originated.
	 * @var array|null
	 */
	private $trace;

	/**
	 * Detected xdebug 2.2.0 var_dump() output.
	 * @var bool
	 */
	private static $xdebug;

	/**
	 * The position of the cursor while parsing the var_dump() string
	 * @var int
	 */
	private $offset;
	/**
	 *
	 * @var type
	 */
	private $vardump;

	/**
	 * Colors based on Tomorrow Night
	 * @link https://github.com/ChrisKempson/Tomorrow-Theme
	 * @var array
	 */
	private static $colors = array(
		'background' => '#1d1f21',
		'foreground' => '#c5c8c6', // lightgray:  [, {, ( and ,
		'current' => '#282a2e', // darkgray

		'class' => '#f0c674', // Yellow
		'attribute' => '#cc6666', // Red
		'variable' => '#cc6666', // Red: $x, $this
		'method' => '#81a2be', // Blue
		'keyword' => '#b294bb', // Purple
		'resource' => '#b294bb', // Purple
		'string' => '#b5bd68', // Green: "hello"
		'number' => '#de935f', // Orange: 0
		'symbol' => '#de935f', // Orange: true, null
		'comment' => '#969896', // Gray
		'operator' => '#8abeb7', // Agua: +, ->
	);

	/**
	 * Constructor
	 * @param mixed $variable  The variable to display on render()
	 */
	function __construct($variable, $backtrace = null) {
		$this->variable = $variable;
		if ($backtrace === null) {
			$backtrace = debug_backtrace();
		}
		if (isset($backtrace[0]['file']) && basename($backtrace[0]['file']) == 'functions.php' && isset($backtrace[1]['function']) && $backtrace[1]['function'] === 'dump') {
			// call via the global dump() function.
			$this->trace = array(
				'invocation' => 'dump',
				'file' => $backtrace[1]['file'],
				'line' => $backtrace[1]['line'],
			);
		} else {
			// Via constructing this Dump object.
			$this->trace = array(
				'invocation' => 'new '.__CLASS__,
				'file' => $backtrace[0]['file'],
				'line' => $backtrace[0]['line'],
			);
			if (array_value($backtrace[0], 'class') === 'Sledgehammer\DebugR') {
				$this->trace['invocation'] = 'DebugR::dump';
			}
		}
	}

	/**
	 * Renders information about the variable, like var_dump() but with improved syntax and coloring.
	 */
	function render() {
		$old_value = ini_get('html_errors');
		ini_set('html_errors', false); // Forces xdebug < 2.2.0 to use render with the internal var_dump()
		if (self::$xdebug === null) {
			// Detect xdebug 2.2 output
			ob_start();
			var_dump(array('' => null));
			self::$xdebug = (strpos(ob_get_clean(), "'' =>") !== false);
		}
		$this->renderTrace();
		$style = array(
			'margin: 0 5px 18px 5px',
			'padding: 10px 15px 15px 15px',
			'line-height: 14px',
			'background: '.self::$colors['background'],
			'border-radius: 0 0 4px 4px',
			'font: 10px/13px Monaco, monospace',
			'color: '.self::$colors['foreground'],
			'-webkit-font-smoothing: none',
			'font-smoothing: none',
			'overflow-x: auto',
			// reset
			'text-shadow: none',
			'text-align: left',
			'box-shadow: none',
		);
		$id = uniqid('dump');
		echo "<pre id=\"".$id."\" style=\"".implode(';', $style)."\">\n";

		ob_start();
		var_dump($this->variable);
		$this->vardump = rtrim(ob_get_clean());
//		$this->debug($output);
		try {
			$this->offset = 0;
			$this->parseVardump();
		} catch (\Exception $e) { // parsing failed?
			report_exception($e);
			echo $this->vardump; //show original var_dump()
		}
		echo "\n</pre>\n";
		if (defined('Sledgehammer\WEBROOT') || defined('Sledgehammer\WEBPATH')) {
			$webroot = defined('Sledgehammer\WEBPATH') ? WEBPATH : WEBROOT;
			echo "<script type=\"text/javascript\">window.$ || document.write('<script src=\"".$webroot."core/js/jquery.js\"><\/sc' + 'ript>')</script>";
			echo "<script type=\"text/javascript\">\n";
			echo "(function () {\n";
			echo "	var dump = $('#".$id."');\n";
			echo "	$('[data-dump=container]', dump).each(function () {\n";
			echo "		var contents = $(this);\n";
			echo "		var toggle = $(this).prev();\n";
			echo "		toggle.css('cursor', 'pointer');\n";
			echo "		toggle.click(function () {\n";
			echo "			contents.toggle();\n";
			echo "			if (contents.is(':visible')) {\n";
			echo "				contents.prev('[data-dump=placeholder]').remove();\n";
			echo "			} else {\n";
			echo "				var hellip = $('<span data-dump=\"placeholder\" style=\"cursor:pointer;padding:0 3px\">&hellip;</span>');\n";
			echo "				contents.before(hellip);\n";
			echo "				hellip.click(function () { toggle.trigger('click');});\n";
			echo "			}\n";
			echo "		});\n";
			echo "	});\n";
			echo "})();\n";
			echo "</script>";
		}
		ini_set('html_errors', $old_value);
	}

	/**
	 * Parse the output from the vardump and render the html version.
	 *
	 * @param int $indentationLevel  The level of indentation (for arrays & objects)
	 */
	private function parseVardump($indentationLevel = 0) {
		if ($this->vardump[$this->offset] == '&') { // A reference?
			echo '&amp;';
			$this->offset++;
		}
		// NULL
		if ($this->part(4) === 'NULL') {
			$this->renderType('symbol', 'null');
			$this->offset += 4;
			return;
		}
		// Recursive
		if ($this->part(11) === '*RECURSION*') {
			$this->renderType('keyword', '*RECURSION* ');
			$this->renderType('comment', '// This variable is already shown');
			$this->offset += 11;
			return;
		}
		// A type. Example "int(4)"
		$parenthesesOpenPos = $this->position('(');
		if ($parenthesesOpenPos === false) {
			throw new \Exception('Unknown datatype "'.$this->part(25).'"');
		}
		$newlinePos = $this->position("\n");
		if (self::$xdebug && $newlinePos !== false && $newlinePos < $parenthesesOpenPos) { // No "(" on the same line?
			$type = $this->part($newlinePos);
			$length = -1;
			$this->renderType('method', $type);
			echo '(?) ';
			$this->renderType('keyword', '?RECURSION?');
			$this->offset += $newlinePos;
			return;
		} else {
			$type = $this->part($parenthesesOpenPos);
			$this->offset += $parenthesesOpenPos + 1;
			$parenthesesClosePos = $this->position(')');
			$length = $this->part($parenthesesClosePos);
			$this->offset += $parenthesesClosePos + 1;

			if (self::$xdebug && substr($type, 0, 5) === 'class') {
				preg_match('/^class (.+)#/', $type, $matches);
				$class = $matches[1];
				$type = 'object';
				$this->offset += 3; // " {\n"
			}
		}

		switch ($type) {
			// boolean (true en false)
			case 'bool':
				$this->renderType('symbol', $length);
				return;

			// numbers (int, float)
			case 'int':
			case 'float':
			case 'double':
				$this->renderType('number', $length);
				return;

			// text (string)
			case 'string':
				$this->offset += 2; // strip ' "'
				$quote = '&#39;'; // single quote (&apos)
				if (self::$xdebug) {
					$endQuotePos = $this->position("\"\n");
					if ($endQuotePos === false) { // vardump only has 1 line.
						$endQuotePos = strlen($this->vardump) - $this->offset - 1;
					}
					if ($endQuotePos != $length) {
						$quote = '&quot;'; // double quote, because xdebug renders newlines as "\n"
						$length = $endQuotePos;
					}
				}
				echo $quote;
				$this->renderType('string', $this->part($length));
				echo $quote;
				$this->offset += $length + 1; // '"' = 1
				return;

			// Resources (file, gd, curl)
			case 'resource':
				$newlinePos = $this->position("\n"); // @todo check if this is not to greedy
				if ($newlinePos === false) { // A dump(resource)
					$resourceType = substr($this->vardump, $this->offset);
				} else {
					$resourceType = $this->part($newlinePos);
				}
				$this->offset += strlen($resourceType);
				$resourceType = preg_replace('/^.*\(|\)$/', '', $resourceType); // strip " type of (" ")"
				$this->renderType('resource', 'resource '.$length.'('.$resourceType.')');
				return;

			case 'array':
				$this->renderType('method', 'array');
				if (self::$xdebug) {
					if ($this->part(3, $this->position("{\n") + (($indentationLevel + 1) * 2) + 2) === '...') { // Start the next line with "..."
						echo '( &hellip; )';
						$this->offset += $this->position("}") + 1; // "}"
						return;
					}
				}
				if ($length == 0) {
					$this->offset += $this->position("}") + 1; // "}"
					echo '()';
					return;
				}
				echo "(<span data-dump=\"container\">\n";
				if ($length !== -1) {
					$this->offset += $this->position("{\n") + 2;
				}
				$this->parseArrayContents($length, $indentationLevel + 1);
				$this->renderIndent($indentationLevel);
				echo "</span>)";
				$this->assertIndentation($indentationLevel);
				$this->offset += ($indentationLevel * 2) + 1; // "}"
				return;

			case 'object':
				if (self::$xdebug === false) {
					$class = $length;
					$this->offset += $this->position('(') + 1; // strip "#?? ("
					$parenthesesClosePos = $this->position(')');
					$length = $this->part($parenthesesClosePos);
					$this->offset += $parenthesesClosePos + 4; // ") {\n" = 4
				}
				$this->renderType('class', $class);
				if (self::$xdebug) {
					if ($this->part(3, (($indentationLevel + 1) * 2)) === '...') { // Start the next line with "..."
						echo ' { &hellip; }';
						$this->offset += $this->position("}") + 1; // "}"
						return;
					}
				}
				echo " {<span data-dump=\"container\">\n";
				$this->parseObjectContents($length, $indentationLevel + 1);
				$this->renderIndent($indentationLevel);
				echo "</span>}";
				$this->assertIndentation($indentationLevel);
				$this->offset += ($indentationLevel * 2) + 1; // "}"
				break;

			default:
				throw new \Exception('Unknown datatype "'.$type.'"');
		}
	}

	/**
	 * Parse the contents of an array.
	 *
	 * @param int $length  The number of items in this array.
	 * @param int $indentationLevel  The number of spaces the elements are indented.
	 */
	private function parseArrayContents($length, $indentationLevel) {
		$indent = $indentationLevel * 2; // var_dump uses 2 spaces per indent.
		for ($i = 0; $i < $length; $i++) {
			if (self::$xdebug && $this->vardump[$this->offset] === "\n" && $this->part(18, ($indentationLevel * 2) + 1) === '(more elements)...') {
				echo "\n";
				$this->renderIndent($indentationLevel);
				echo "&hellip; ";
				$this->renderType('comment', "// more elements\n");
				$this->offset += ($indentationLevel * 2) + 20;
				return;
			}
			$this->assertIndentation($indentationLevel);
			$this->offset += $indent + 1; // strip spaces and "[" or "'"
			// extract key
			if (self::$xdebug) {
				$numericKey = ($this->vardump[$this->offset - 1] === "["); // is "'" for strings
			} else {
				$numericKey = $this->vardump[$this->offset] !== '"';
			}
			$arrowPos = $this->position("=>\n");
			if (self::$xdebug) {
				$key = $this->part($arrowPos - 2); // strip "] " or "' "
			} else {
				if ($numericKey) {
					$key = $this->part($arrowPos - 1); // strip "]"
				} else {
					$key = $this->part($arrowPos - 3, 1); // strip '"' + '"]'
				}
			}
			$this->offset += $arrowPos + 3 + $indent; // "=>\n" = 3
			// render key
			$this->renderIndent($indentationLevel);
			if ($numericKey) {
				$this->renderType('number', $key);
			} else {
				echo '&#39;';
				$this->renderType('string', $key);
				echo '&#39;';
			}
			$this->renderType('operator', ' => ');
			if (self::$xdebug && $this->part(3, $indent + 2) === '...') {
				echo "&hellip;\n";
				$this->offset += $indent + 7; // "  ...\n\n" = 7
				continue;
			}
			// Value
			$this->parseVardump($indentationLevel);
			$this->offset += 1; // "\n"
			echo ",\n";
		}
	}

	/**
	 * Parse the contents of an object.
	 *
	 * @param int $length  The number of attribute in this object.
	 * @param int $indentationLevel  The number of spaces the elements are indented.
	 */
	private function parseObjectContents($length, $indentationLevel) {
		$indent = $indentationLevel * 2; // var_dump uses 2 spaces per indent.
		for ($i = 0; $i < $length; $i++) {
			$this->assertIndentation($indentationLevel);
			$this->offset += $indent; // strip spaces
			// Extract attribute
			$arrowPos = $this->position("=>\n");
			if (self::$xdebug) {
				$attribute = $this->part($arrowPos - 1);
			} else {
				$attribute = $this->part($arrowPos - 2, 1); // Strip "[" + "]"
			}
			// Render attribute
			$this->renderIndent($indentationLevel);
			$this->parseAttribute($attribute);
			$this->offset += $arrowPos + 3 + $indent; // "=>\n" = 3

			$this->renderType('operator', ' -> ');

			// Value
			if (self::$xdebug && $this->part(3, $indent + 2) == '...') {
				echo "\n";
				$this->renderIndent($indentationLevel);
				echo "&hellip;\n\n";
				$this->offset += ($indentationLevel * 2) + 7; // "\n  ...\n" = 7
				return;
			}
			$this->parseVardump($indentationLevel);
			$this->offset += 1; // "\n"
			echo ",\n";
		}
	}

	/**
	 * Check if the leading spaces in current line matches the indent level.
	 *
	 * @param int $level
	 */
	private function assertIndentation($level) {
		if (preg_match('/^[\s]+/', $this->part(($level * 2) + 1), $match)) {
			if (strlen($match[0]) == ($level * 2)) { // correct length?
				return; // valid
			}
		}
		if ($level == 0) {
			return;
		}
		throw new InfoException('Invalid indentation at offset '.$this->offset.', Expecting "'.str_repeat(' ', $level * 2).'", got "'.$this->part(($level * 2) + 1).'"', array('Near' => $this->part(100)));
	}

	/**
	 * Resolves the variablename and renders the "dump($x) in filename.php on line X" line.
	 */
	private function renderTrace() {
		$style = array(
			'font: 13px/22px \'Helvetica Neue\', Helvetica, sans-serif',
			'border-radius: 4px 4px 0 0',
			'background-color: '.self::$colors['current'],
			'margin: 15px 5px 0 5px',
			'padding: 3px 15px',
			'color: '.self::$colors['foreground'],
			// reset styling
			'text-shadow: none',
		);
		echo "<div style=\"".implode(';', $style)."\">";
		if (self::$xdebug) {
			echo '<span style="float:right;color:'.self::$colors['comment'].'" title="Add \'xdebug.overload_var_dump = Off\' to php.ini for complete output.">Limited by xdebug</span>';
		}
		$this->renderType('comment', $this->trace['invocation']);
		echo '(<span style="margin: 0 3px;">';

		if (substr($this->trace['file'], -14) !== ' eval()\'d code') {
			$file = file($this->trace['file']);
			$line = $file[($this->trace['line'] - 1)];
			$line = preg_replace('/.*dump\(/i', '', $line); // Alles voor de dump aanroep weghalen
			$argument = preg_replace('/\);.*/', '', $line); // Alles na de dump aanroep weghalen
			$argument = trim($argument);
			if (preg_match('/^\$[a-z_]+[a-z_0-9]*$/i', $argument)) { // $var?
				$this->renderType('variable', $argument);
			} elseif (preg_match('/^(?P<function>[a-z_]+[a-z_0-9]*)\((?<arguments>[^\)]*)\)$/i', $argument, $matches)) { // function()?
				$this->renderType('method', $matches['function']);
				echo '(', $this->escape($matches['arguments']), ')';
			} elseif (preg_match('/^(?P<object>\$[a-z_]{1}[a-z_0-9]*)\-\>(?P<attribute>[a-z_]{1}[a-z_0-9]*)(?P<element>\[.+\]){0,1}$/i', $argument, $matches)) { // $object->attribute or $object->attribute[12]?
				$this->renderType('variable', $matches['object']);
				$this->renderType('operator', '->');
				$this->renderType('attribute', $matches['attribute']);
				if (isset($matches['element'])) {
					echo '['.$this->escape(substr($matches['element'], 1, -1)).']';
				}
			} elseif (preg_match('/^(?P<object>\$[a-z_]+[a-z_0-9]*)\-\>(?P<method>[a-z_]+[a-z_0-9]*)\((?<arguments>[^\)]*)\)$/i', $argument, $matches)) { // $object->method()?
				$this->renderType('variable', $matches['object']);
				$this->renderType('operator', '->');
				$this->renderType('method', $matches['method']);
				echo '(', $this->escape($matches['arguments']), ')';
			} else {
				echo $this->escape($argument);
			}
		}
		echo '</span>)&nbsp;';
		$this->renderType('comment', ' in ');
		echo '/', str_replace('\\', '/', str_replace(PATH, '', $this->trace['file']));
		$this->renderType('comment', ' on line ');
		echo $this->trace['line'];
		echo "</div>\n";
	}

	/**
	 * Return a substring of the given length of the vardump.
	 *
	 * @param int $length Length of the substring
	 * @param int $offset  Relative offset
	 * @return string
	 */
	private function part($length, $offset = 0) {
		return substr($this->vardump, $this->offset + $offset, $length);
	}

	/**
	 * Returns the relative position of the searchString inside the vardump.
	 * @param string $searchString  The
	 * @param int $offset Relative offset
	 * @return int|false
	 */
	private function position($searchString, $offset = 0) {
		$pos = strpos($this->vardump, $searchString, $this->offset + $offset);
		if ($pos === false) {
			return false;
		}
		return $pos - $this->offset;
	}

	/**
	 * A mini dump for debugging this Dump parser.
	 *
	 * @param mixed $variable
	 */
	private function debug($variable) {
		echo '<b>[DBG]</b> ';
		var_export($variable);
		echo' <b>[/DBG]</b>';
	}

	/**
	 * Parse an attribute name from an object.
	 *
	 * @param string $attribute Examples: '"log"', '"error_types:private"' or '"error_types":"ErrorHandler":private'
	 */
	private function parseAttribute($attribute) {
		if (self::$xdebug) {
			if (preg_match('/(public|protected|private) \$(.+)$/', $attribute, $matches)) {
				$this->renderType('attribute', $matches[2]);
				if ($matches[1] !== 'public') {
					echo '<span style="font-size:9px">:';
					$this->renderType('comment', $matches[1]);
					echo '</span>';
				}
				return;
			} else {
				throw new InfoException('Invalid attribute', array('attribute' => $attribute));
			}
		}
		$parts = explode(':', $attribute);
		$partsCount = count($parts);
		switch ($partsCount) {

			case 1: // Is de scope niet opgegeven?
				$this->renderType('attribute', substr($attribute, 1, -1));
				break;

			case 2:
				$this->renderType('attribute', substr($parts[0], 1, -1));
				echo '<span style="font-size:9px">:';
				$this->renderType('comment', $parts[1]);
				echo '</span>';
				break;

			case 3: // Sinds 5.3 staat bij er naast :private ook van welke class deze private is. bv: "max_string_length_backtrace":"ErrorHandler":private
				$this->renderType('attribute', substr($parts[0], 1, -1));
				echo '<span style="font-size:9px" title="'.$this->escape(substr($parts[1], 1, -1)).'">:';
				$this->renderType('comment', $parts[2]);
				echo '</span>';
				break;

			default:
				throw new InfoException('Unexpected number of parts: '.$partsCount, $parts);
		}
	}

	/**
	 * Render indent (spaces)
	 * @param int $level
	 */
	private function renderIndent($level) {
		echo str_repeat(' ', $level * 2);
	}

	/**
	 * Render the content in the color of the type.
	 *
	 * @param string $type ENUM: 'class', 'attribute', 'variable', 'method', 'keyword', 'resource', 'string', 'number', 'symbol', 'comment' or 'operator'
	 * @param string $content
	 */
	private function renderType($type, $content) {
		echo '<span style="color: ', self::$colors[$type], '">', htmlspecialchars($content, ENT_NOQUOTES, 'ISO-8859-1'), '</span>';
	}

	/**
	 * Escape html output.
	 *
	 * @param string $text
	 * @return string
	 */
	private function escape($text) {
		return htmlspecialchars($text, ENT_NOQUOTES, 'ISO-8859-1');
	}

}

?>