<?php
/**
 * Globale functies van de core module
 *
 * @package Core
 */
namespace SledgeHammer;

/**
 * Als het element bestaat wordt de waarde gereturnt, anders wordt niks (null) gereturnd. (Zonder foutmeldingen)
 *
 * i.p.v.
 *   if (isset($_GET['foo']) && $_GET['foo'] == 'bar') {
 * Schrijf je:
 *   if (array_value($_GET, 'foo') == 'bar') {
 *
 * @param array $array
 * @param string $key
 * @return mixed
 */
function array_value($array, $key) {
	if (is_string($key) === false && is_int($key) === false) {
		notice('Unexpected type: "'.gettype($key).'" for paremeter $key, expecting a int or string');
		return;
	}
	if (is_array($array) == false) {
		return;
	}
	if (array_key_exists($key, $array)) {
		return $array[$key];
	}
}
 
/**
 * Test of een array een assoc array is. (Als een key NIET van het type integer zijn)
 *
 * @return bool
 */
function is_assoc($array) {
	foreach ($array as $key => $null) {
		if (!is_int($key)) {
			return true;
		}
	}
	return false;
}

/**
 * Test of een array een indexed array is. (Als de keys liniair zijn opgebouwd)
 *
 * @return bool
 */
function is_indexed($array) {
	if (!is_array($array)) {
		if (!(is_object($array) && in_array('Iterator', class_implements($array)))) {
			notice('Unexpected '.gettype($array).', expecting array (or Iterator)');
			return false;
		}
	}
	$index = 0;
	foreach ($array as $key => $null) {
		if ($key !== $index) {
			return false;
		}
		$index++;
	}
	return true;
}

/**
 * implode, maar dan zodat deze leesbaar is voor mensen. 
 * Bv: echo human_implode(' of ', array('appel', 'peer', 'banaan')); wordt 'appel, peer of banaan' 
 * 
 * @param string $glueLast deze wordt gebruikt tussen de laaste en het eennalaatste element. bv: ' en ' of ' of ' 
 * @param array $array 
 * @param string $glue 
 * @return string
 */
function human_implode($glueLast, $array, $glue = ', ') {
    if (!$array || !count ($array)) { // sanity check
        return '';
    }
    $last = array_pop ($array); // get last element   
    if (!count ($array)) { // if it was the only element - return it
        return $last;
    }
    return implode ($glue, $array).$glueLast.$last;
}

/**
 * Detect mimetype based on file-extention
 * @see core/settings/mime_types.ini for "extention to mimetype" translations

 * @param string $filename
 * @param bool $allow_unknown_types Bij False zal er een foutmelding gegenereerd worden als het bestandstype onbekend is.
 * @param string $default De mimetype die wordt geretourneerd als er geen mimetype bekend is.
 * @return string Content-Type
 */
function mimetype($filename, $allow_unknown_types = false, $default = 'application/octet-stream') {
	$extension = file_extension($filename);
	if ($extension === null) {
		$mimetype = null;
	} else {
		if (empty($GLOBALS['mimetypes.ini'])) {
			$GLOBALS['mimetypes.ini'] = parse_ini_file(dirname(__FILE__).'/settings/mimetypes.ini');
		}
		$mimetype = value($GLOBALS['mimetypes.ini'][strtolower($extension)]);
	}
	if ($mimetype === NULL) {
		if (!$allow_unknown_types) {
			trigger_error('Unknown mime type for :"'.$extension.'", E_USER_WARNING');
		}
		return $default;
	}
	return $mimetype;
}

/**
 * Een element uit een array opvragen. Kan ook een subelement opvragen "element[subelement1][subelement2]"
 * Retourneert true als de waarde is gevonden. [abc] wordt ['abc'], maar [1] wordt geen ['1']
 *
 * @param mixed $value wordt ingesteld
 * @return bool
 */
function extract_element($array, $identifier, &$value) {
	if (isset($array[$identifier])) { // Bestaat het element 'gewoon' in de array?
		$value = $array[$identifier];
		return true;
	}
	$bracket_position = strpos($identifier, '[');
	if ($bracket_position === false) { // Gaat het NIET om een array?
		return false;
	} elseif (strpos($identifier, '[]')) {
		notice('Het identifier bevat een ongeldige combinatie van blokhaken: []', $identifier);
		return false;
	}
	preg_match_all("/\\[[^[]*\\]/", $identifier, $keys); // Deze reguliere exp. splits alle van alle subelementen af in een array. element[subelement1][subelement2] wordt array("[subelement1]", "[subelement2]")
	$identifier = substr($identifier, 0, $bracket_position);
	$php_variabele = "\$array[\"".addslashes($identifier)."\"]";
	foreach($keys[0] as $key) {
		$php_code = 'if (gettype(@'.$php_variabele.") == 'array') {\n\treturn false;\n}\nreturn true;";
		if (eval($php_code)) {
			return false;
		}
		if (preg_match('/[a-zA-Z]+/', $key)) {
			$key = "[\"".addslashes(substr($key, 1, -1))."\"]";
		}
		$php_variabele .= $key;
	}
	$php_code = 'if (isset('.$php_variabele."))\n{\n\t\$value = ".$php_variabele.";\n\t\$return = true;\n}\nelse\n{\n\t\$return = false;\n}";
	eval($php_code);
	return $return;
}


/**
 * Zodra er een unserialize() wordt aangeroepen op een string wat een object moet worden en de class in nog niet gedefineerd ( zoals bij eend session_start() ) wordt deze functie aanroepen.
 */
function unserialize_callback($class) {
	$GLOBALS['AutoLoader']->declare_class($class);
}

/**
 * Controleerd of 2 variabelen gelijk of bijna gelijk aan elkaar zijn.
 * equals((float) 1.000, (int) 1) == true
 * equals("1.1", 1.1) == true
 *
 * @return bool
 */
function equals($var1, $var2) {
	if ($var1 === $var2) { // Zijn de variabelen van het zelfde type en hebben ze dezelde waarde?
		return true; // Dan zijn ze gelijk aan elkaar.
	}
	if (is_numeric($var1) && is_numeric($var2) && $var1 == $var2) { // Zijn het allebij getallen en hebben ze dezelde waarde?
		return true;
	}
	return false;
}

/**
 * Vergelijk de eigenschappen van 2 objecten met de equals functie.
 * 
 * @param Object $object1
 * @param Object $object2
 * @param array $properties De eigenschappen die vergeleken moeten worden
 * @return bool True als de eigenschappen gelijk zijn aan elkaar
 */
function equal_properties($object1, $object2, $properties) {
	foreach ($properties as $property) {
		if (equals($object1->$property, $object2->$property) == false) { // Zijn de eigenschappen verschillend?
			return false;
		}		
	}
	return true; // Er zijn geen verschillen gevonden
}
 
/**
 * Werkt als get_object_vars() maar i.p.v. de waardes op te vragen worden deze ingesteld
 *
 * @param Object $Object Het (doel) object waar de eigenschappen worden aangepast 
 * @param array $values Een assoc array met als key de eigenschap. bv: array('id' => 1)
 * @param bool $check_for_propery Bij false zal de functie alle array-elementen proberen in het object te zetten, Bij true zullen alleen bestaande elementen ingesteld worden
 * @return void
 */
function set_object_vars($Object, $values, $check_for_property = false) {
	if ($check_for_property) {
		foreach ($values as $property => $value) {
		 if (property_exists($Object, $property)) {
				$Object->$property = $value;
		 }
		}
	}	else {
		foreach ($values as $property => $value) {
			$Object->$property = $value;
		}
	}
}

/**
 * Werkt net als get_class_methods, maar de array bevat alleen de publieke functies
 *
 * @param string|object De naam van de class of het object zelf.
 * @return array
 */
function get_public_methods($class) {
	return get_class_methods($class);
}
/**
 * Een redirect naar een andere pagina.
 * Werkt indien mogelijk via de HTTP header en anders via Javascript of een META refresh tag.
 *
 * @param string $url  De URL van de 
 * @param bool $permanent  Bij true wordt ook de "301 Moved Permanently" header verstuurd
 * @return exit()
 */
function redirect($url, $permanently = false) {
	if (headers_sent()) {
		echo '<script type="text/javascript">window.location="'.addslashes($url).'";</script>'; // Probeer een javascript redirect
		echo '<noscript><meta http-equiv="refresh" content="'.addslashes('0; url='.$url).'"></noscript>'; // Probeer meta-refresh (kan in Internet Explorer uitgezet worden >< )
		echo 'U werd doorverwezen naar een nieuwe pagina. Dit lukte niet automatisch. Gebruik deze <a href="'.$url.'">link</a> om verder te gaan.';
	} else {
		if ($permanently) {
			header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
		} else {
			header($_SERVER['SERVER_PROTOCOL'].' 302 Found');
		}
		header('Location: '.$url);
	}
	exit();
}

/**
 * Een bestand downloaden(naar het geheugen) en opslaan.
 * retourneert de groote van het bestand (in karakters)
 *
 * @return int|false
 */
function wget($url, $filename) {
	$file_contents = file_get_contents($url);
	if ($file_contents) {
		if (file_put_contents($filename, $file_contents)) {
			return strlen($file_contents);
		}
	}
	return false;
}

/**
 * Het path creeren
 * Zal alle map-namen die in het path genoemd worden proberen te maken.
 * Zal geen foutmelding geven als het path al bestaat.
 * 
 * @param string $path De map die gemaakt moet worden
 * @return bool
 */
function mkdirs($path) {
	if (is_dir($path)) {
		return true;
	} 
	$parent = dirname($path); 
	if ($parent == $path) { // Is er geen niveau hoger?
		warning('Unable to create path "'.$path.'"'); // Ongeldig $path bv NULL of ""
	} elseif (mkdirs($parent)) { // Maak (waneer nodig) de boverliggende deze map aan.
		return mkdir($path); //  Maakt de map aan.
	}
	return false;
}

/**
 * De map, inclusief alle bestanden en submappen in het $path verwijderen.
 *
 * @throws Exception on failure
 * @return int Het aantal verwijderde bestanden
 */
function rmdir_recursive($path, $allowFailures = false) {
	$counter = 0;
	$dir = new \DirectoryIterator($path);
	foreach ($dir as $entry) {
		if ($entry->isDot()) {
			continue;
		}
		if ($entry->isDir()) { // is het een map?
			$counter += rmdir_recursive($entry->getPathname().'/', $allowFailures);
			continue;
		}
		if (unlink($entry->getPathname()) == false && $allowFailures == false) {
			throw new Exception('Failed to delete "'.$entry->getPathname().'"');
		}
		$counter++;
	}
	if (rmdir($path) == false && $allowFailures == false) {
		throw new Exception('Failed to delete directory "'.$path.'"');
	}
	return $counter;
}

/**
 * Delete the contents of the folder, but not the folder itself.
 *
 * @throws Exception on failure
 * @return int Het aantal verwijderde bestanden
 */
function rmdir_contents($path, $allowFailures = false) {
	$counter = 0;
	$dir = new \DirectoryIterator($path);
	foreach ($dir as $entry) {
		if ($entry->isDot()) {
			continue;
		}
		if ($entry->isDir()) { // is het een map?
			$counter += rmdir_recursive($entry->getPathname(), $allowFailures);
		} else {
			if (unlink($entry->getPathname()) == false && $allowFailures == false) {
				throw new Exception('Failed to delete "'.$entry->getPathname().'"');
			}
			$counter++;
		}
	}
	return $counter;
}


/**
 * Een bestand verwijderen, met extra controle dat het bestand zich in de $basepath map bevind. 
 * 
 * @throws Exception  
 * @param $filepath
 * @param $basepath
 * @return void
 */
function safe_unlink($filepath, $basepath, $recursive = false) {
	if (in_array(substr($basepath, -1), array('/', '\\'))) { // Heeft de $basepath een trailing slash?
		$basepath = substr($basepath, 0, -1);
	}
	if (strlen($basepath) < 4) { // Minimal "/tmp"
		throw new Exception('$basepath "'.$basepath.'" is too short');
	}
	// 	Controleer of het path niet buiten de basepath ligt.
	$realpath = realpath(dirname($filepath));		
	if ($realpath == false) {
		throw new Exception('Invalid folder: "'.dirname($filepath).'"'); // Kon het path niet omvormen naar een bestaande map.
	}
	$filepath = $realpath.'/'.basename($filepath);  // Nette $filepath
	if (substr($filepath, 0, strlen($basepath)) != $basepath) { // Hack poging?
		throw new Exception('Ongeldige bestandsnaam "'.$filepath.'"');
	}
	if (!file_exists($filepath)) {
		throw new Exception('File "'.$filepath.'" not found');
	}
	if ($recursive ) {
		rmdir_recursive($filepath);
		return;
	}
	if (unlink($filepath) == false) {
		throw new Exception('Failed to delete "'.$filepath.'"');
	}
}

/**
 * De nieuwste/hoogste mtime opvragen dat zich in het $path bevind.
 * Controleert de timestamps van alle 
 * 
 * @param string $path 
 * @return int
 */
function mtime_folders($path, $exclude = array()) {
	$max_ts = filemtime($path); // Vraag de mtime op van de map
	if ($max_ts === false) { // Bestaat het $path niet?
		return false;
	}
	// Controleer of een van de bestanden of submappen een nieuwere mtime heeft.
	$dir = new \DirectoryIterator($path);
	foreach ($dir as $entry) {
		if ($entry->isDot() || in_array($entry->getFilename(), $exclude)) {
			continue;
		}
		if ($entry->isDir()) {
			$ts = mtime_folders($entry->getPathname());
		} else {
			$ts = filemtime($entry->getPathname());
		}
		if ($ts > $max_ts) { // Heeft de submap een nieuwere timestamp?
			$max_ts = $ts;
		}
	}
	return $max_ts;
}

/**
 * Een map inclusief bestanden en submappen copieren (Als de doelmap al bestaat, worden deze overschreven/aangevuld)
 * De doelmap wordt NIET eerst verwijderd
 *
 * @param string $source  De bronmap
 * @param string $target  De doelmap
 * @param array $exclude  Een array met bestand en/of mapnamen die niet gekopieerd zullen.
 */
function copydir($source, $dest, $exclude = array()) {
	if (!is_dir($dest) && !mkdir($dest)) {
		return false;
	}
	$file_count = 0;
	$DirectoryIterator = new DirectoryIterator($source);
	foreach ($DirectoryIterator as $Entry) {
		if ($Entry->isDot() || in_array($Entry->getFilename(), $exclude)) {
			continue;
		} elseif ($Entry->isFile()) {
			if (copy($Entry->getPathname(), $dest.'/'.$Entry->getFilename())) {
				$file_count++;
			} else {
				break;
			}
		} elseif ($Entry->isDir()) {
			$file_count += copydir($Entry->getPathname(), $dest.'/'.$Entry->getFilename(), $exclude);
		} else {
			notice('Unsupported filetype');
		}
	}
	return $file_count;
}

/**
 * Een variabele aan de hand van zijn datatype een kleur geven en omzetten naar html-formaat.
 * Wordt o.a. gebruikt door Dump & ErrorHandler 
 * De een kleuren worden bepaald in /css/datatypes.css uit webcore
 *
 * @return string
 */
function syntax_highlight($variabele, $datatype = NULL) {
	if ($datatype === NULL) {
		$datatype = gettype($variabele);
	}
	switch($datatype) {

		case 'string':
			$variabele = '&#39;'.str_replace("\n", '<br />', str_replace(' ', '&nbsp;', htmlspecialchars($variabele))).'&#39;';
			break;
		case 'string_pre': // voor strings binnen een <pre> zoals bij de dump()
			$datatype = 'string';
			$variabele = '&#39;'.htmlspecialchars($variabele).'&#39;';
			break;
		case 'integer':
		case 'double':
			$datatype = 'number';
			break;

		case 'boolean':
			$datatype = 'constant';
			$variabele = $variabele ? 'true' : 'false';
			break;

		case 'NULL':
			$datatype = 'constant';
			$variabele = 'NULL';
			break;

		case 'array':
			$datatype = 'method';
			$variabele = 'array('.count($variabele).')';
			break;

		case 'object':
			$datatype = 'class';
			$variabele = get_class($variabele);
			break;

		case 'resource':
			$datatype = 'constant';
			break;

		// al geconverteerde datatypes
		case 'constant':
		case 'operator':
		case 'number':
		case 'comment':
		case 'class':
		case 'attribute':
		case 'method':
			break;

		default:
			notice('Datatype: "'.$datatype.'" is unknown');
			break;
	}
	if (!isset($GLOBALS['highlight_colors'])) {
		include(dirname(__FILE__).'/settings/highlight_colors.php');
	}	
	return '<span style="color:'.$GLOBALS['highlight_colors'][$datatype].'">'.$variabele.'</span>';
}

/**
 * Het database object van een link opvragen.
 * @param string $link De naam van de link die opgevraagd moet worden
 * @todo betere locatie vinden dan de functions.php
 * @return MySQLiDatabase
 */
function getDatabase($link = 'default') {
	if (isset($GLOBALS['Databases'][$link])) {
		if (is_string($GLOBALS['Databases'][$link])) { // Is dit een verwijzing naar een andere database?
			return getDatabase($GLOBALS['Databases'][$link]);
		}
		return $GLOBALS['Databases'][$link];
	}
	throw new Exception('Database connection: $GLOBALS[\'Databases\'][\''.$link.'\'] is not configured');
	return false;
}

/**
 * Zet een float om naar een leesbare (parse)tijdnotatie
 */
function format_parsetime($seconds, $precision = 3) {
	if ($seconds < 60) { // Duurde het genereren korter dan 1 minuut?
		return number_format($seconds, $precision);
	}  else {
		$minutes = floor($seconds / 60);
		$miliseconds = fmod($seconds, 1);
		$seconds = str_pad($seconds % 60, 2, '0', STR_PAD_LEFT);
		return $minutes.':'.$seconds.substr(number_format($miliseconds, $precision), 1);
	}
}

/**
 * Toont een reeks debug en profiling gegevens zoals parsetijd en geheugenverbruik.
 */
function statusbar() {
	if (defined('MICROTIME_START')) {
		$now = microtime(true);
		echo '<span id="statusbar_parsetime">Parsetime:&nbsp;<b>'.format_parsetime($now - MICROTIME_START) .'</b>sec. ';
		if (defined('MICROTIME_INIT')) {
			echo '<span id="statusbar_parsetimes">(Init:&nbsp;<b>'.format_parsetime(MICROTIME_INIT - MICROTIME_START) .'</b>sec.';
			if (defined('MICROTIME_EXECUTE')) {
				echo ' Execute:&nbsp;<b>'.format_parsetime(MICROTIME_EXECUTE - MICROTIME_INIT) .'</b>sec. ';
				echo 'Render:&nbsp;<b>'.format_parsetime($now - MICROTIME_EXECUTE) .'</b>sec.';
			}
			echo ')</span> ';
		}
		echo '</span>'."\n";
	}
	if (function_exists('memory_get_usage')) { // Geheugenverbruik in MiB tonen
		echo 'Memory:&nbsp;<b>'.number_format(memory_get_usage() / 1048576, 2).'</b>';
		if (function_exists('memory_get_peak_usage')) {
			echo '/<b>'.number_format(memory_get_peak_usage() / 1048576, 2).'</b>';
		}
		echo 'MiB.'."\n";
	}
	if (isset($_ENV['HOSTNAME'])) {
		echo 'Host:&nbsp;<b>'.str_replace('.uniserver.nl', '', $_ENV['HOSTNAME']).'</b> ';
	}
	if (isset($GLOBALS['Databases'])) {
		echo 'Databases: ';
		foreach ($GLOBALS['Databases'] as $link => $Database) {
			if (is_object($Database)) {
				echo '['.$link.'&nbsp;';
				$Database->debug();
				echo '] ';
			}
		}
	}
}

/**
 * Zet alle iterators binnen $data om naar arrays.
 * Doet zijn best om $source niet te veranderen, maar is afhankelijk van __clone
 * (private en protected eigenschappen worden niet omgezet)
 * 
 * @param mixed $data
 * @return mixed
 */
function iterators_to_arrays($data) {
	if (!is_object($data) && !is_array($data)) { // Is het een primitief type?  
		return $data; // niks om om te zetten.
	}
	if (is_object($data)) {
		if ($data instanceof Iterator) { // Is dit een iterator?
			$array = iterator_to_array($data); // Iterator omzetten naar een array.
			return iterators_to_arrays($array); /// Alle elementen (mogelijk) omzetten 
		} else {
			$object = clone $data;
			foreach ($data as $property => $value) {
				$object->$property = iterators_to_arrays($value);
			}
			return $object;
		}
	}
	// dan is het een array.
	$array = array();
	foreach ($data as $key => $value) {
		$array[$key] = iterators_to_arrays($value); // Alle elementen (mogelijk) omzetten 		
	}
	return $array;
} 

/**
 * Geeft de keys van een iterator in een array. Net als array_key(), maar dan voor iterators.
 *
 * @see array_keys()
 * @param Iterator $iterator
 * @return array * 
 */
function iterator_keys($iterator, $search_value = null, $strict = false) {
	if (is_array($iterator)) {
		return array_keys($iterator);
	}
	if (func_num_args() > 1) {
		error('$search_value not yet implemented');
	}
	if (!is_object($iterator) || !($iterator instanceof Iterator)) {
		warning('The first argument should be an Iterator object');
	}
	$keys = array();
	for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
		$keys[] = $iterator->key();
	}
	return $keys;
}
/**
 * Hiermee kun je de timeout van het script verlengen.
 * Bij $relative op true zal de timeout aanvuld worden zodat het script nog minimaal n seconden mag draaien.
 * (De reeds ingestelde timeout zal nooit ingekort worden)
 * Bij $relative op false (absolute) zullen de $seconden bij de huidige timeout worden opgeteld.
 *
 * @param $fromNow 
 * @return void
 */
function extend_time_limit($seconds, $relative = false) {
	$currentLimit = ini_get('max_execution_time');
	if ($currentLimit == 0) {
		return;
	}
	if ($relative) {
		$elapsed = ceil(microtime(true) - START);
		if (($elapsed + $seconds) > $currentLimit) { // Is de berekende timeout groter dan de huidige?
			set_time_limit($elapsed + $seconds);
		}
	} else {
		set_time_limit($currentLimit + $seconds);
	}
}

/**
 * Deze functie geeft de extentie van de bestandnaam terug.
 *
 * Voorbeelden:
 *   $filename          $extension  $file
 *   ".htaccess"         null       ".htaccess"
 *   "index.html"       "html"      "index"
 *   "game.PART001.rar" "rar"       "game.PART001"
 *
 * @param $filename De bestandsaam
 * @param $filename_without_extention  Deze reference word ingesteld met de bestandsnaam, maar dan zonder extensie 
 * @return string De extensie
 */
function file_extension($filename, &$filename_without_extention = null) {
	if (preg_match('/^([.]*.+)\.([^.]+)$/', $filename, $parts)) {
		$filename_without_extention = $parts[1];
		return $parts[2];
	}
	$filename_without_extention = $filename;
	return null; // Deze $filename heeft geen extensie
}

/**
 * Retreive browser and OS info
 *
 * @param NULL|string $part Het deel van de info welke gevraagd wordt("name", "version" of "os"), bij NULL krijg je een array met alle gegevens
 * @return string|array array (
 *   'name'=> $browser,
 *   'version'=> $version,
 *   'os'=> $os,
 * );
 */
function browser($part = NULL) {
	// browser
	$version = '';
	if (!isset($_SERVER['HTTP_USER_AGENT'])) {
		$browser = 'php-'.php_sapi_name();
	} elseif (preg_match('/MSIE ([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Microsoft Internet Explorer';
		$version = $match[1];
	} elseif (preg_match('/Opera\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Opera';
		$version = $match[1];
	} elseif (preg_match('/Safari\/([0-9]{3}.[0-9]{1})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Safari';
		$version = $match[1];
		if (preg_match('/Version\/([0-9.]+)/', $_SERVER['HTTP_USER_AGENT'], $match)) { // Is er naast het revisienummer ook een versie nummer?
			$version = $match[1]; // Gebruik het versie nummer
		}
	} elseif (preg_match('/Camino\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Camino';
		$version = $match[1];
	} elseif (preg_match('/Firefox\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Mozilla Firefox';
		$version = $match[1];
	} elseif (preg_match('/Mozilla\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
		$browser = 'Mozilla';
		$version = $match[1];
	} else {
		$browser = $_SERVER['HTTP_USER_AGENT'];
	}
	// Operating system
	if (!isset($_SERVER['HTTP_USER_AGENT'])) {
		$os = PHP_OS;
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Win')) {
		$os = 'Windows';
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone OS')) {
		$os = 'iPhone OS';
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'OS X')) {
		$os = 'Apple OS X';
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Mac')) {
		$os = 'Macintosh';
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Linux')) {
		$os = 'Linux';
	} elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Unix')) {
		$os = 'Unix';
	} else {
		$os = 'Overig';
	}
	$info = array(
		'name'=> $browser,
		'version'=> $version,
		'os'=> $os,
	);
	if ($part === NULL) { // Return all info?
		return $info;
	}
	if (isset($info[$part])) {
		return $info[$part];
	}
	notice('Unexpected part: "'.$part.'", expecting: "'.implode('", "', array_keys($info)).'"');
}

/**
 * Vraagt het IP adres van de client op.
 *
 * Deze methode biedt MINDER beveiling dan de $_SERVER['REMOTE_ADDR'] en is makkelijk te spoofen,
 * Maar in een (reverse) proxy of load-balanced situatie geeft de $_SERVER['REMOTE_ADDR'] het de IP van de load-balancer.
 * En ben je dus afhankelijk van de HTTP_ vars.
 *
 * @return IP
 */
function getClientIp() {
	$fields = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
	$ips = array();
    foreach ($fields as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ips[] = trim($ip);
            }
        }
    }
	if (function_exists('filter_var') == false) { // IP validatie kan pas vanaf php 5.2
		return $ips[0]; // Gebruik dan de eerste.
	}
	$flags = FILTER_FLAG_NO_RES_RANGE; // Geen 127.0.0.1 of 169.254.x.x IPs
	if (ENVIRONMENT != 'development') {
		$flags = $flags | FILTER_FLAG_NO_PRIV_RANGE; // 192.168.x.x niet toestaan. (Tenzij in development modes)
	}
	foreach ($ips as $ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
			notice('Invalid IP: "'.$ip.'"');
		} else {
			return $ip;
		}
	}
	warning('No valid client IP detected', array('IPs' => $ips));
	return $ips[0]; // Gebruik dan de eerste.
}

/**
 * De HTTP headers versturen.
 *
 * @param array $headers
 * @return void
 */
function send_headers($headers) {
	if (count($headers) == 0) {
		return;
	}
	if (headers_sent($file, $line)) {
		if ($file == '' && $line == 0) {
			$location = '';
		} else {
			$location = ', output started in '.$file.' on line '.$line;
		}
		if (class_exists('ErrorHandler', false)) {
				notice('Couldn\'t sent header(s)'.$location,  array('headers' => $headers));
		} else {
			trigger_error('Couldn\'t sent header(s) "'.human_implode(' and ', $headers,'", "').'"'.$location,  E_USER_NOTICE);
		}
	} else {
		$notices = array();
		foreach($headers as $header => $value) {
			if ($header == 'Status') { // and != fastcgi?
				header($_SERVER['SERVER_PROTOCOL'].' '.$value);
			} elseif (is_numeric($header)) {
				$notices[] = 'Invalid HTTP header: "'.$header.': '.$value.'"';
			} else {
				header($header.': '.$value);
			}
		}
		foreach ($notices as $notice) {
			notice($notice, 'Use $headers format: array("Content-Type" => "text/css")');
		}
	}
}

/**
 * Sends the contents of the file including appropriate headers.
 * Zal na het succesvol versturen van het bestand het script stoppen "exit()" 
 * 
 * @param string $filename Filename including path.
 * @throws Exception on Failure
 * @return void
 */
function render_file($filename) {
	$last_modified = filemtime($filename);
	if ($last_modified === false) {
		throw new Exception('Modify date unknown');
	}
	if (array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
		$if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
		if ($if_modified_since >= $last_modified) { // Is the Cached version the most recent?
			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
			exit();
		}
	}
	$headers = array();
	/*
	$resume_support = false; // @todo Bestanden in tmp/ geen resume_support geven.  
	if ($resume_support) {
		$headers[] = 'Accept-Ranges: bytes';
	}*/
	if (is_dir($filename)) {
		throw new Exception('Unable to render_file(). "'.$filename.'" is a folder');
	}
	$headers['Content-Type'] = mimetype($filename);
	$headers['Last-Modified'] = gmdate('r', $last_modified);
	$filesize = filesize($filename);
	if ($filesize === false) {
		throw new Exception('Filesize unknown');
	}
	if (empty($_SERVER['HTTP_RANGE'])) {
		$headers['Content-Length'] = $filesize; // @todo Detectie inbouwen voor bestanden groter dan 2GiB, deze geven fouten.
		send_headers($headers);
		// Output buffers uitschakelen, anders zal readfile heb bestand in het geheugen inladen. (en mogelijk de memory_limit overschrijden) 
		while (ob_get_level() > 0) {
			ob_end_flush();
		}
		$success = readfile($filename); // Het gehele bestand sturen
		if ($success) {
			exit();
		}
		throw new Exception('readfile() failed');
	} else {
		 // Het gehele bestand sturen (resume support is untested)
		$success = readfile($filename);
		if ($success) {
			exit();
		}
		throw new Exception('readfile() failed');
		// #########################################
		// Onderstaande CODE wordt nooit uitgevoerd. 
		
		// Een gedeelte van het bestand sturen

		//check if http_range is sent by browser (or download manager)
		list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		$range = '';
		if ($size_unit == 'bytes') {
			//multiple ranges could be specified at the same time, but for simplicity only serve the first range
			//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
			list($range, $extra_ranges) = explode(',', $range_orig, 2);
		}
		// Figure out download piece from range (if set)
		list($seek_start, $seek_end) = explode('-', $range, 2);
	}

	// set start and end based on range (if set), else set defaults
	// also check for invalid ranges.
	$seek_end = (empty($seek_end)) ? ($filesize - 1) : min(abs(intval($seek_end)),($filesize - 1));
	$seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);
	
	// Only send partial content header if downloading a piece of the file (IE workaround)
	if ($seek_start > 0 || $seek_end < ($filesize - 1)) {
		header('HTTP/1.1 206 Partial Content');
	}
	$headers[] = 'Content-Range: bytes '.$seek_start.'-'.$seek_end.'/'.$filesize;
	$headers[] = 'Content-Length: '.($seek_end - $seek_start + 1);

	$fp = fopen($filename, 'rb');
	if (!$fp) {
		return false;
	}
	fseek($fp, $seek_start); // seek to start of missing part
	set_time_limit(0);
	send_headers($headers);
	while(!feof($fp)) {
		print(fread($fp, 1024*16)); // Verstuur in blokken van 16KiB
		flush();
	}
	return fclose($fp);
}

/**
 * Mappen en bestandsnaam in de url corrigeren
 * Geen " " maar "%20" enz
 */
function urlencode_path($path) {
	$escaped_path = rawurlencode($path);
	return str_replace('%2F', '/', $escaped_path); // De "/" weer terugzetten
}

/**
 * Genereer aan de hand van de $identifier een (meestal) uniek id
 *
 * @param string $identifier
 * @return int
 */
function sem_key($identifier) {
	$md5 = md5($identifier);
	$key = 0;
	for ($i = 0; $i < 32; $i++) {
		$key += ord($md5{$i}) * $i;
	}
	return $key;
}
?>
