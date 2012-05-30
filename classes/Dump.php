<?php
/**
 * Dump
 */
namespace Sledgehammer;
/**
 * Parses a var_dump() and renders a syntax highlighted version of var_export();
 * Usage:
 *   dump($var);
 * or in a VirtualFolder:
 *  return new Dump($var);
 *
 * (Compatible with the View interface from MVC)
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
	private static $xdebug = null;

	/**
	 * Constructor
	 * @param mixed $variable  The variable to display on render()
	 */
	function __construct($variable = null) {
		$this->variable = $variable;
		$trace = debug_backtrace();
		$file = $trace[0]['file'];
		$line = $trace[0]['line'];
		$this->trace = array(
			'invocation' => 'new '.__CLASS__,
			'file' => $file,
			'line' => $line,
		);
	}

	/**
	 * View::render() interface
	 * @return void
	 */
	function render() {
		$this->render_dump($this->variable, $this->trace);
	}

	/**
	 * De een gekleurde var_dump van de variabele weergeven
	 *
	 * @param mixed $variable
	 * @param array|null $trace
	 */
	static function render_dump($variable, $trace = null) {
		self::render_trace($trace);

		$style = array(
			'border: 1px solid #e1e1e8',
			'border-top: 0',
			'margin: 0 5px 18px 5px',
			'padding: 10px 15px 15px 15px',
			'line-height: 14px',
			'background: #fbfbfc',
			'border-radius: 0 0 4px 4px',
			'font: 10px/13px Monaco, monospace',
			'color: teal', /* kleur van een operator */
			'-webkit-font-smoothing: none',
			'font-smoothing: none',
			'overflow-x: auto',
			// reset
			'text-shadow: none',
			'text-align: left',
			'box-shadow: none',
		);

		echo "<pre style=\"".implode(';', $style)."\">\n";
		$old_value = ini_get('html_errors');
		ini_set('html_errors', false); // Forces xdebug < 2.2.0 to use render with the internal var_dump()
		ob_start();
		if (self::$xdebug === null) {
			// Detect xdebug 2.2 output
			var_dump(array('' => null));
			self::$xdebug = (strpos(ob_get_clean(), "'' =>") !== false);
			ob_start();
		}
		var_dump($variable);
		$output = rtrim(ob_get_clean());
		try {
			self::render_vardump($output);
		} catch (\Exception $e) {
			report_exception($e);
		}
		echo "\n</pre>\n";
		ini_set('html_errors', $old_value);
	}

	/**
	 * De vardump van kleuren voorzien.
	 * Retourneert de positie tot waar de gegevens geparsed zijn
	 *
	 * @param string $data the var_dump() output.
	 * @param int $indenting The the number of leading spaces
	 * @return int the number of characters the variable/array/object occupied in the var_dump() output
	 */
	private static function render_vardump($data, $indenting = 0) {
		if ($data[0] == '&') {
			$data = substr($data, 1);
			$pos = 1;
			echo '&amp;';
		} else {
			$pos = 0;
		}
		if (substr($data, 0, 4) == 'NULL') {
			echo syntax_highlight('null', 'constant');
			return $pos + 4;
		}
		if (substr($data, 0, 11) == '*RECURSION*') {
			echo '*RECURSION* ', syntax_highlight('// This variable is already shown', 'comment');
			return $pos + 11;
		}
		$bracketStart = strpos($data, '(');
		if ($bracketStart === false) {
			throw new \Exception('Unknown datatype "'.$data.'"');
		}
		$bracketEnd = strpos($data, ')', $bracketStart);
		$type = substr($data, 0, $bracketStart);
		$length = substr($data, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
		$pos += $bracketEnd + 1;

		if (self::$xdebug && substr($type, 0, 5) === 'class') {
			$type = 'object';
		}

		// primitive types
		switch ($type) {

			// boolean (true en false)
			case 'bool':
				echo syntax_highlight($length, 'constant');
				return $pos;

			// numbers (int, float)
			case 'int':
			case 'float':
				echo syntax_highlight($length, 'number');
				return $pos;

			// text (string)
			case 'string':
				$text = substr($data, $bracketEnd + 3, $length);
				echo syntax_highlight($text, 'string_pre');
				return $pos + $length + 3;

			// Resources (file, gd, curl)
			case 'resource':
				$resource = 'Resource#'.$length;
				$pos = strpos($data, "\n");

				if ($pos === false) {
					$pos = strlen($data);
				} else {
					$data = substr($data, 0, $pos);
				}

				$resource.= preg_replace('/.*\(/', ' (', $data);
				$resource = substr($resource, 0);
				echo syntax_highlight($resource, 'constant');
				return $pos;

			// Arrays
			case 'array':
				if ($length == 0) {// Empty array?
					echo syntax_highlight('array()', 'method');
					return $pos + $indenting + 4;
				}
				$data = substr($data, $bracketEnd + 4); // ') {\n' eraf halen
				echo syntax_highlight('array(', 'method');
				echo "\n";
				if (self::$xdebug && preg_match('/^\s+\.\.\.\n/', $data, $matches)) {// xdebug limit reached?
					echo str_repeat(' ', $indenting + 4), "...\n";
					echo str_repeat(' ', $indenting), syntax_highlight(')', 'method');
					return $pos + $indenting - 2 + strpos($data, '}');
				}
				$indenting += 2;
				for ($i = 0; $i < $length; $i++) {// De elementen
					echo str_repeat(' ', $indenting);
					if (self::$xdebug) {
						$data = substr($data, $indenting); // strip spaces
						$arrowPos = strpos($data, " =>\n");
						$index = substr($data, 0, $arrowPos);
						if ($index[0] === '[') { // numeric index?
							echo syntax_highlight(substr($index, 1, -1), 'number');
						} elseif ($index[0] == "'") {
							// @todo detect " =>\n" in the index
							echo syntax_highlight(substr($index, 1, -1), 'string');
						} else {
							throw new InfoException('Invalid index', array('index' => $index));
						}
						echo ' => ';
						$pos += strlen($index) + 5;
						$data = substr($data, $arrowPos + 4 + $indenting);
					} else {
						$data = substr($data, $indenting + 1); // spaties en [ eraf halen
						$arrowPos = strpos($data, ']=>');
						$index = substr($data, 0, $arrowPos);

						if ($index[0] == '"') { // assoc array?
							echo syntax_highlight(substr($index, 1, -1), 'string');
						} else {
							echo syntax_highlight($index, 'number');
						}
						echo ' => ';
						$data = substr($data, $arrowPos + 4 + $indenting);
						$pos += strlen($index) + 6;
					}
					$elementLength = self::render_vardump($data, $indenting);
					$data = substr($data, $elementLength + 1);
					$pos += $elementLength + ($indenting * 2);
					echo ",\n";
				}
				$indenting -= 2;
				$pos += 4 + $indenting;
				echo str_repeat(' ', $indenting);

				echo syntax_highlight(')', 'method');
				return $pos;

			// Objects
			case 'object':
				if (self::$xdebug) {
					preg_match('/^class (.+)#/', $data, $matches);
					$object = $matches[1];
				} else {
					$data = substr($data, $bracketEnd + 1);
					$object = $length;
					$bracketStart = strpos($data, '(');
					if ($bracketStart === false) {
						throw new \Exception('Unexpected object notation "'.$object.'"');
					}
					$bracketEnd = strpos($data, ')', $bracketStart);
					$type = substr($data, 0, $bracketStart);
					$length = substr($data, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
				}
				$data = substr($data, $bracketEnd + 4); // ' {\n' eraf halen
				echo syntax_highlight($object, 'class');
				if ($length == 0) { // Geen attributen?
					return $pos + $bracketEnd + strpos($data, '}') + 5; // tot '}\n' eraf halen.
				}
				echo syntax_highlight(' {', 'class');
				echo "\n";
				if (self::$xdebug && preg_match('/^\s+\.\.\.\n/', $data, $matches)) { // xdebug limit reached?
					echo str_repeat(' ', $indenting + 4), "...\n";
					echo str_repeat(' ', $indenting), syntax_highlight('}', 'class');
					return $pos + strpos($data, '}') + 2;
				}
				$indenting += 2;
				for ($i = 0; $i < $length; $i++) { // attributes
					echo str_repeat(' ', $indenting);
					if (self::$xdebug) {
						$data = substr($data, $indenting); // strip spaces
						$arrowPos = strpos($data, " =>\n");
						$attribute = substr($data, 0, $arrowPos);
						$data = substr($data, $arrowPos + 4 + $indenting);
						$pos += strlen($attribute) + 4;
					} else {
						$data = substr($data, $indenting + 1); // strip spaces and [
						$arrowPos = strpos($data, ']=>');
						$attribute = substr($data, 0, $arrowPos);
						$data = substr($data, $arrowPos + 4 + $indenting);
						$pos += strlen($attribute) + 6;
					}
					self::render_attribute($attribute);
					echo ' -> ';
					if (self::$xdebug && preg_match('/^\s+\.\.\.\n\n/', $data, $matches)) { // xdebug limit reached?
						echo "\n", str_repeat(' ', $indenting + 4), "...\n\n";
						echo str_repeat(' ', $indenting - 2), syntax_highlight('}', 'class');
						return $pos + strlen($matches[0]);
					}
					$elementLength = self::render_vardump($data, $indenting);
					$data = substr($data, $elementLength + 1);
					echo ",\n";
					$pos += $elementLength + ($indenting * 2);
				}
				$indenting -= 2;
				$pos += $bracketEnd + 5 + $indenting;
				echo str_repeat(' ', $indenting);
				echo syntax_highlight('}', 'class');
				return $pos;
				break;

			default:
				throw new \Exception('Unknown datatype "'.$type.'"');
		}
	}

	/**
	 * Achterhaald het bestand en regelnummer waarvan de dump functie is aangeroepen
	 * @param array $trace
	 */
	private static function render_trace($trace = null) {
		if ($trace === null) {
			$trace = array(
				'invocation' => 'dump'
			);
			$backtrace = debug_backtrace();
			for ($i = count($backtrace) - 1; $i >= 0; $i--) {
				if (isset($backtrace[$i]['function']) && strtolower($backtrace[$i]['function']) == 'dump') {
					if (isset($backtrace[$i]['file'])) {
						// Parameter achterhalen
						$trace['file'] = $backtrace[$i]['file'];
						$trace['line'] = $backtrace[$i]['line'];
						break;
					}
				}
			}
		}
		$style = array(
			'font: 13px/22px \'Helvetica Neue\', Helvetica, sans-serif',
			'border: 1px solid #e1e1e8',
			'border-bottom: 1px solid #ececf0',
			'border-radius: 4px 4px 0 0',
			'background-color: #f7f7f9',
			'margin: 15px 5px 0 5px',
			'padding: 3px',
			'padding-left: 9px',
			'color: #777',
			'text-shadow: 0 1px 0 #fff',
		);
		echo '<div style="'.implode(';', $style).'">';
		echo $trace['invocation'], '(<span style="margin: 0 3px;">';

		$file = file($trace['file']);
		$line = $file[($trace['line'] - 1)];
		$line = preg_replace('/.*dump\(/i', '', $line); // Alles voor de dump aanroep weghalen
		$argument = preg_replace('/\);.*/', '', $line); // Alles na de dump aanroep weghalen
		$argument = trim($argument);
		if (preg_match('/^\$[a-z_]+[a-z_0-9]*$/i', $argument)) { // $var?
			echo syntax_highlight($argument, 'attribute');
		} elseif (preg_match('/^(?P<function>[a-z_]+[a-z_0-9]*)\((?<arguments>[^\)]*)\)$/i', $argument, $matches)) { // function()?
			echo syntax_highlight($matches['function'], 'method'), '<span style="color:#444">(', htmlentities($matches['arguments'], ENT_COMPAT, Framework::$charset), ')</span>';
		} elseif (preg_match('/^(?P<object>\$[a-z_]+[a-z_0-9]*)\-\>(?P<attribute>[a-z_]+[a-z_0-9]*)(?P<element>\[.+\])$/i', $argument, $matches)) { // $object->attribute or $object->attribute[12]?
			echo syntax_highlight($matches['object'], 'class'), syntax_highlight('->', 'operator'), syntax_highlight($matches['attribute'], 'attribute');
			if ($matches['element']) {
				echo syntax_highlight('[', 'operator'), '<span style="color:#333">', substr($matches['element'], 1, -1), '</span>', syntax_highlight(']', 'operator');
			}
		} elseif (preg_match('/^(?P<object>\$[a-z_]+[a-z_0-9]*)\-\>(?P<method>[a-z_]+[a-z_0-9]*)\((?<arguments>[^\)]*)\)$/i', $argument, $matches)) { // $object->method()?
			echo syntax_highlight($matches['object'], 'class'), syntax_highlight('->', 'operator'), syntax_highlight($matches['method'], 'method'), '<span style="color:#444">(', htmlentities($matches['arguments'], ENT_COMPAT, Framework::$charset), ')</span>';
		} else {
			echo '<span style="color:#333">', htmlentities($argument, ENT_COMPAT, Framework::$charset), '</span>';
		}
		echo '</span>)&nbsp; in /', str_replace('\\', '/', str_replace(PATH, '', $trace['file'])), ' on line ', $trace['line'];
		echo "</div>\n";
	}

	/**
	 * Haal de scope uit de attribute string en
	 *
	 * @param string $attribute Bv '"log", '"error_types:private" of '"error_types":"ErrorHandler":private'
	 */
	static private function render_attribute($attribute) {
		if (self::$xdebug) {
			if (preg_match('/(public|protected|private) \$(.+)$/', $attribute, $matches)) {
				echo syntax_highlight($matches[2], 'attribute');
				if ($matches[1] !== 'public') {
					echo '<span style="font-size:9px">:', $matches[1], '</span>';
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
				echo syntax_highlight(substr($attribute, 1, -1), 'attribute');
				break;

			case 2:
				if (substr($parts[1], -1) == '"') { // Sinds php 5.3 is wordt ':protected' en ':private' NA de '"' gezet ipv ervoor
					// php < 5.3
					echo syntax_highlight(substr($parts[0], 1), 'attribute'), '<span style="font-size:9px">:', substr($parts[1], 0, -1), '</span>';
				} else { // php >= 5.3
					echo syntax_highlight(substr($parts[0], 1, -1), 'attribute'), '<span style="font-size:9px">:', $parts[1], '</span>';
				}
				break;

			case 3: // Sinds 5.3 staat bij er naast :private ook van welke class deze private is. bv: "max_string_length_backtrace":"ErrorHandler":private
				echo syntax_highlight(substr($parts[0], 1, -1), 'attribute'), '<span style="font-size:9px" title="'.htmlentities(substr($parts[1], 1, -1)).'">:', $parts[2], '</span>';
				break;

			default:
				throw new InfoException('Unexpected number of parts: '.$partsCount, $parts);
		}
	}

}

?>
