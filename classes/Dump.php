<?php
/**
 * Verzorgt de implementatie van de dump() functie.
 * Maakt gebruik van var_dump() en voegt er dan syntax highlighting aan toe.
 *
 * (Is compatible met Component interface uit Webcore)
 * @package Core
 */

class Dump extends Object {

	private
		$variable,
		$trace;

	function __construct($variable = NULL) {
		$this->variable = $variable;
		$trace = debug_backtrace();
		$file = $trace[0]['file'];
		$line = $trace[0]['line'];
		$file_short = str_replace(PATH, '', $file);
		$this->trace = 'new Dump(<strong>'.Dump::get_variable_name($file, $line).'</strong>) in <strong>'.$file_short.'</strong> on line <strong>'.$line.'</strong>';
	}

	function render() {
		$this->render_dump($this->variable, $this->trace);
	}

	/**
	 * De een gekleurde var_dump van de variabele weergeven
	 */
	static function render_dump($variable, $trace = false) {
		$dump_style = array(
			'font: 12px Courier, monospace',
			'color: #008000', /* kleur van een operator*/
			'border: 1px dashed #CFCFCF',
			'border-top: 0',
			'margin: 0',
			'margin-bottom: 16px',
			'padding: 8px',
			'line-height: 14px',
			'background-color: #FBFBFB',
			'text-align: left',
			'text-shadow: none',
			);
		$dumptrace_style = array(
			'font: 12px Tahoma, sans-serif',
			'border: 1px dashed #CFCFCF',
			'background-color: #EEEEEE',
			'margin-top: 8px',
			'border-bottom: 0',
			'padding:3px',
			'padding-left: 9px',
			'color: #777777',
			'text-shadow: none',			
		);
		echo '<div style="'.implode(';', $dumptrace_style).'">';
		if ($trace) {
			echo $trace;
		} else {
			Dump::trace();
		}
		echo "</div>\n";
		echo "\n<pre style=\"".implode(';', $dump_style) ."\">\n";
		$old_value = ini_get('html_errors');
		ini_set('html_errors', false); // Hierdoor is Dump compatible met de xdebug module
		ob_start();
		var_dump($variable);
		$data = rtrim(ob_get_clean());
		Dump::render_vardump($data);
		echo "\n</pre>\n";
		ini_set('html_errors', $old_value);
	}

	/**
	 * De vardump van kleuren voorzien.
	 * Retourneert de positie tot waar de gegevens geparsed zijn
	 * @return int
	 */
	private static function render_vardump($gegevens, $spaties = 0) {
		$nixel = '/images/nixel.gif';
		if ($gegevens[0] == '&') {
			$gegevens = substr($gegevens, 1);
			$positie = 1;
			echo '&amp;';
		} else {
			$positie = 0;
		}
		if (substr($gegevens, 0, 4) == 'NULL') {
			echo syntax_highlight('NULL', 'constant');
			return $positie + 4;
		}
		if (substr($gegevens, 0, 11) == '*RECURSION*') {
			echo syntax_highlight('// Deze variabele wordt al weergeven in de dump (Recursief genest)', 'comment');
			return $positie + 11;
		}
		$positie_haak_begin = strpos($gegevens, '(');
		if ($positie_haak_begin === false) {
			error('Onbekend datatype "'.$gegevens.'"');
		}
		$positie_haak_eind = strpos($gegevens, ')', $positie_haak_begin);
		$type = substr($gegevens, 0, $positie_haak_begin);
		$lengte = substr($gegevens, $positie_haak_begin + 1, $positie_haak_eind - $positie_haak_begin - 1);
		$positie += $positie_haak_eind + 1;

		// primitieve typen afhandelen
		switch($type) {

			// boolean (true en false)
			case 'bool':
				echo syntax_highlight($lengte, 'constant');
				return $positie;

			// getallen (int, float)
			case 'int':
			case 'float':
				echo syntax_highlight($lengte, 'number');
				return $positie;

			// tekst (string)
			case 'string':
				$tekst = substr($gegevens, $positie_haak_eind + 3, $lengte);
				echo syntax_highlight($tekst, 'string');
				return $positie + $lengte + 3;

			// Resource (bestand, database)
			case 'resource':
				$resource = 'Resource#'.$lengte;
				$positie = strpos($gegevens, "\n");

				if ($positie === false) {
					$positie = strlen($gegevens);
				} else {
					$gegevens = substr($gegevens, 0, $positie);
				}

				$resource.= preg_replace('/.*\(/', ' (', $gegevens);
				$resource = substr($resource, 0);
				echo syntax_highlight($resource, 'constant');
				return $positie;

			// Een array
			case 'array':
				if ($lengte == 0) {// Een lege array?
					echo syntax_highlight('array()', 'method');
					return $positie + $spaties + 4;
				}
				$gegevens = substr($gegevens, $positie_haak_eind + 4);// ') {\n' eraf halen
				echo syntax_highlight('array(', 'method');
				echo "\n";

				$spaties += 2;
				for($i = 0; $i < $lengte; $i++) {// De elementen
					for ($j = 0; $j < $spaties; $j++) {
						echo " ";
					}

					$gegevens = substr($gegevens, $spaties + 1); // spaties en [ eraf halen
					$positie_blokhaak = strpos($gegevens, ']=>');
					$index = substr($gegevens, 0, $positie_blokhaak);

					if ($index[0] == '"') { // assoc array?
						echo syntax_highlight(substr($index, 1, -1), 'string');
					} else {
						echo syntax_highlight($index, 'number');
					}
					echo ' => ';

					$gegevens = substr($gegevens, $positie_blokhaak + 4 + $spaties);
					$lengte_elemement = Dump::render_vardump($gegevens, $spaties);
					$gegevens = substr($gegevens, $lengte_elemement + 1);
					$positie += $lengte_elemement + strlen($index) + ($spaties * 2) + 6;

					echo ",\n";
				}
				$spaties -= 2;
				$positie += 4 + $spaties;
				for ($j = 0; $j < $spaties; $j++) {
					echo " ";
				}

				echo syntax_highlight(')', 'method');
				return $positie;

			// Een object
			case 'object':
				$gegevens = substr($gegevens, $positie_haak_eind + 1);
				$object = $lengte;
				$positie_haak_begin = strpos($gegevens, '(');
				if ($positie_haak_begin === false) {
					error('Raar object "'.$object.'"');
				}
				$positie_haak_eind = strpos($gegevens, ')', $positie_haak_begin);
				$type = substr($gegevens, 0, $positie_haak_begin);
				$lengte = substr($gegevens, $positie_haak_begin + 1, $positie_haak_eind - $positie_haak_begin - 1);
				$gegevens = substr($gegevens, $positie_haak_eind + 4); // ' {\n' eraf halen
				echo syntax_highlight($object, 'class');
				if ($lengte == 0) { // Geen attributen?
					return $positie + $positie_haak_eind + strpos($gegevens, '}') + 5; // tot '}\n' eraf halen.
				}
				echo syntax_highlight(' {', 'class');
				echo "\n";
				$spaties += 2;
				for($i = 0; $i < $lengte; $i++) { // De attributen
					for ($j = 0; $j < $spaties; $j++) {
						echo " ";
					}
					$gegevens = substr($gegevens, $spaties + 1); // spaties en [ eraf halen
					$positie_blokhaak = strpos($gegevens, ']=>');
					$attribuut = substr($gegevens, 0, $positie_blokhaak);
					self::render_attribute($attribuut);
					echo ' -> ';
					$gegevens = substr($gegevens, $positie_blokhaak + 4 + $spaties);
					$lengte_elemement = Dump::render_vardump($gegevens, $spaties);
					$gegevens = substr($gegevens, $lengte_elemement + 1);
					echo ",\n";
					$positie += $lengte_elemement + strlen($attribuut) + ($spaties * 2) + 6;
				}
				$spaties -= 2;
				$positie += $positie_haak_eind + 5 + $spaties;
				for ($j = 0; $j < $spaties; $j++) {
					echo " ";
				}
				echo syntax_highlight('}', 'class');
				return $positie;
				break;

			default:
				error('Onbekend datatype "'.$type.'"');
		}
	}

	/**
	 * Achterhaald het bestand en regelnummer waarvan de dump functie is aangeroepen 
	 */
	private static function trace() {
		$trace = debug_backtrace();
		for($i = count($trace) - 1; $i >= 0 ; $i--) {
			if (isset($trace[$i]['function']) && strtolower($trace[$i]['function']) == 'dump') {
				if (isset($trace[$i]['file'])) {
					// Parameter achterhalen
					$file = $trace[$i]['file'];
					$line = $trace[$i]['line'];
					$file_short = str_replace(PATH, '', $file);
					echo 'dump(<strong>'.Dump::get_variable_name($file, $line).'</strong>) in <strong>/'.str_replace('\\','/',$file_short).'</strong> on line <strong>'.$trace[$i]['line'].'</strong>.';
					return;
				}
			}
		}
	}

	/**
	 * De naam van de variabele die aan de dump functie of constructor is meegegeven
	 */
	static private function get_variable_name($file, $line) {
		$file = file($file);
		$line = $file[$line -1];
		$line = preg_replace('/.*dump\(/i', '', $line); // Alles voor de dump aanroep weghalen
		$parameter_name = preg_replace('/\);.*/', '', $line); // Alles na de dump aanroep weghalen
		$parameter_name = trim($parameter_name);
		return $parameter_name;
	}

	/**
	 * Haal de scope uit de attribute string en
	 *
	 * @param string $attribute Bv '"log", '"error_types:private" of '"error_types":"ErrorHandler":private'
	 */
	static private function render_attribute($attribute) {
		$parts = explode(':', $attribute);
		$partsCount = count($parts);
		switch ($partsCount) {
			
			case 1: // Is de scope niet opgegeven?
				echo syntax_highlight(substr($attribute, 1, -1), 'attribute');
				break;

			case 2:
				if (substr($parts[1], -1) == '"') { // Sinds php 5.3 is wordt ':protected' en ':private' NA de '"' gezet ipv ervoor
					// php < 5.3
					echo syntax_highlight(substr($parts[0], 1), 'attribute'), '<span style="font-size:10px">:',  substr($parts[1], 0, -1), '</span>';
				} else { // php >= 5.3
					echo syntax_highlight(substr($parts[0], 1, -1), 'attribute'), '<span style="font-size:10px">:',  $parts[1], '</span>';
				}
				break;

			case 3: // Sinds 5.3 staat bij er naast :private ook van welke class deze private is. bv: "max_string_length_backtrace":"ErrorHandler":private
				echo syntax_highlight(substr($parts[0], 1, -1), 'attribute'), '<span style="font-size:10px" title="'.htmlentities(substr($parts[1], 1, -1)).'">:',  $parts[2], '</span>';
				break;

			default:
				notice('Unexpected number of parts: '.$partsCount, $parts);
		}
	}
}
?>
