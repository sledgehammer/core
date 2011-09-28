<?php
/**
 * Verantwoordelijk voor het on-the-fly inladen en declareren van classes en interfaces.
 * Dit verbeterd parsetijd/geheugenverbruik aanzienlijk, alleen de bestanden die je nodig hebt worden ge-include.
 *
 * @package Core
 */
namespace SledgeHammer;
class AutoLoader extends Object {

	/**
	 * @var bool  Bij true zal define() een fout genereren als de class niet bekend is.
	 */
	public $standalone = true;

	/**
	 * @var bool  If a class or interface doesn't exist in a namespace use the class from a higher namespace
	 */
	public $resolveNamespaces = true;
	/**
	 * @var bool  Bij true worden de resultaten (per module) gecached, de cache zal opnieuw opgebouwt worden als er bestanden gewijzigd of toegevoegd zijn.
	 */
	public $enableCache;
	/**
	 * @var string
	 */
	private $path;

	/**
	 * Checks that are enabled when the module contains a classes folder.
	 * The settings can be overridden with by placing an  autoloader.ini in the offending folder.
	 * @var array
	 */
	private $settings = array(
		'matching_filename' => true,
		'mandatory_definition' => true,
		'mandatory_superclass' => true,
		'one_definition_per_file' => true,
		'ignore_folders' => array(),
		'ignore_files' => array(),
		'revalidate_cache_delay' => 10,
		'detect_accidental_output' => true,
	);
	/**
	 * @var array  Array containing the filename per class or interface.
	 */
	private $definitions = array();

	/**
	 *
	 * @param string $path
	 */
	function __construct($path) {
		$this->path = $path;
	}

	function init() {
		if (file_exists($this->path.'AutoLoader.db.php')) {
			include($this->path.'AutoLoader.db.php');
			$this->definitions = $definitions;
			return;
		}
		$modules = Framework::getModules();
		foreach ($modules as $folder => $module) {
			$this->importModule($module);
		}
	}

	/**
	 * include() the file containing the class of interface
	 *
	 * @param string $definition Fully qualified class or interface name
	 */
	function define($definition) {
		if (class_exists($definition, false) || interface_exists($definition, false)) {
			return true;
		}
		$filename = $this->getFilename($definition);
		if ($filename === null) {
			if ($this->resolveNamespaces && $this->resolveNamespace($definition)) {
				return true; // The class/interface is defined by resolving a namespace
			}
			if ($this->standalone) {
				warning('Unknown definition: "'.$definition.'"', array('Available definitions' => implode(array_keys($this->definitions), ', ')));

			}
			return false;
		}
		$success = include_once($filename);
		if ($success !== 1) { // Lukte het includen van het bestand?
			throw new \Exception('Failed to include "'.$filename.'"');
		}
		if (class_exists($definition, false) || interface_exists($definition, false)) {
			return true;
		}
		throw new \Exception('AutoLoader is corrupt, class "'.$definition.'" not found in "'.$filename.'"');
	}

	/**
	 * Get the filename
	 *
	 * @param string $definition Fully qualified class/interface name
	 * @return string|null  Return null if the definion can't be found
	 */
	function getFilename($definition) {
		$filename = @$this->definitions[$definition];
		if ($filename !== null) {
			return $this->fullPath($filename);
		}
		foreach ($this->definitions as $name => $value) {
			if (strcasecmp($name, $definition) == 0) {
				if (error_reporting() == (error_reporting() | E_STRICT)) { // Strict mode?
					notice('Definition "'.$definition.'" not found, using "'.$name.'" fallback');
				}
				return $this->fullPath($value);
			}
		}
	}

	function getDefinitions() {
		return array_keys($this->definitions);
	}


	/**
	 *
	 * @param string $definition Fully qualified class/interface name
	 * @return bool
	 */
	private function resolveNamespace($definition) {
		if (strpos($definition, '\\') === false) {
			return false;
		}
		$namespaces = explode('\\', $definition);
		$class = array_pop($namespaces);
		$targetNamespace =  implode('\\', $namespaces);
		array_pop($namespaces); // een namespace laag hoger
		$extends = implode('\\', $namespaces);
		if ($extends == '') {
			$extends  = $class;
		} else {
			$extends .= '\\'.$class;
		}
		$php = 'namespace '.$targetNamespace." {\n\t";
		if (class_exists($extends, false)) {
			$php .= 'class '.$class;
		} elseif (interface_exists($class, false)) {
			$php .= 'interface '.$class;
		} else {
			return false;
		}
		if (error_reporting() == (error_reporting() | E_STRICT)) { // Strict mode
			notice('Importing "'.$class.'" into namespace "'.$targetNamespace.'"', 'Use "\\'.$extends.'"');
		}
		$php .= ' extends \\'.$extends." {}\n}";
		eval($php);

		return true;
	}


	function importModule($module, $settings = array()) {
		$path = $module['path'];
		if (file_exists($module['path'].'classes')) {
			$path = $path.'classes';
		} else {
			$settings = $this->mergeSettings(array(
				'matching_filename' => false,
				'mandatory_definition' => false,
				'mandatory_superclass' => false,
				'one_definition_per_file' => false,
				'revalidate_cache_delay' => 20,
				'detect_accidental_output' => false,
			), $settings);
		}
		$settings = $this->loadSettings($path, $settings);
		if ($this->enableCache) {
			$folder = basename($module['path']);
			$cacheFile = TMP_DIR.'AutoLoader/'.substr(md5(dirname($module['path'])), 8, 16).'/'.$folder.'.php'; // md5(module_path)/module_naam(bv core).php
			if (!mkdirs(dirname($cacheFile))) {
				$this->enableCache = false;
			} elseif (file_exists($cacheFile)) {
				$mtimeCache = filemtime($cacheFile);
				$revalidateCache = ($mtimeCache < (time() - $settings['revalidate_cache_delay'])); // Is er een delay ingesteld en is deze nog niet verstreken?;
				if ($revalidateCache == false || $mtimeCache > mtime_folders($module['path'])) { // Is het cache bestand niet verouderd?
					// Use the cacheFile
					include($cacheFile);
					$this->definitions += $definitions;
					if ($settings['revalidate_cache_delay'] && $revalidateCache) { // is het cache bestand opnieuw gevalideerd?
						touch($cacheFile); // de mtime van het cache-bestand aanpassen, (voor het bepalen of de delay is vertreken)
					}
					return;
				}
			}
		}
		$this->importFolder($path, $settings);
		if ($this->enableCache) {
			$this->writeCache($cacheFile, $this->relativePath($module['path']));
		}
	}

	function importFolder($path, $settings = array()) {
		$settings = $this->loadSettings($path, $settings);
		try {
			$dir = new \DirectoryIterator($path);
		} catch (\Exception $e) {
			notice($e->getMessage());
			return;
		}
		foreach ($dir as $entry) {
			if ($entry->isDot()) {
				continue;
			}
			if ($entry->isDir()) {
				if (in_array($entry->getPathname(), $settings['ignore_folders'])) {
					continue;
				}
				$this->importFolder($entry->getPathname(), $settings);
			}
			if (file_extension($entry->getFilename()) == 'php') {
				if (in_array($entry->getPathname(), $settings['ignore_files']) == false) {
					$this->importFile($entry->getPathname(), $settings);
				}
			}
		}
	}

	/**
	 *
	 * @param string $filename
	 * @param array $settings
	 * @return array definitions
	 */
	function importFile($filename, $settings = array()) {
		$setttings = $this->mergeSettings($settings);
		$previousError = error_get_last();
		$tokens = token_get_all(file_get_contents($filename));
		$error = error_get_last();
		if ($error !== $previousError) {
			notice($error['message'].' in "'.$filename.'"');
		}
		$definitions = array();
		$namespace = '';
		$state = 'DETECT';
		foreach ($tokens as $token) {
			if ($token[0] == T_WHITESPACE) {
				continue;
			}
			switch ($state) {

				case 'DETECT':
					switch ($token[0]) {

						case T_NAMESPACE:
							$state = 'NAMESPACE';
							$namespace = '';
							break;

						case T_CLASS:
							$state = 'CLASS';
							break;

						case T_INTERFACE:
							$state = 'INTERFACE';
							break;

					}
					break;

				case 'NAMESPACE':
					if (in_array($token[0], array(T_STRING, T_NS_SEPARATOR))) {
						$namespace .= $token[1];
						break;
					}
					if (in_array($token, array(';', '{'))) {
						$state = 'DETECT';
						break;
					}
					$this->unexpectedToken($token);
					$state = 'DETECT';
					break;

				case 'CLASS':
					if ($token[0] == T_STRING) {
						if ($settings['matching_filename'] && substr(basename($filename), 0, -4) != $token[1]) {
							notice('Filename doesn\'t match classname "'.$token[1].'" in "'.$filename.'"', array('settings' => $settings));
					}
						if ($namespace == '') {
							$definition = $token[1];
						} else {
							$definition = $namespace.'\\'.$token[1];
						}
						$definitions[] = $definition;
						break;
					}
					if ($token[0] == T_EXTENDS) {
						$state = 'DETECT';
						break;
					} elseif ($settings['mandatory_superclass'] && !in_array($definition, array('SledgeHammer\Object', 'SledgeHammer\Framework', 'SledgeHammer\ErrorHandler'))) {
						notice('Class: "'.$definition.'" has no superclass, expection "class X extends Y"');
					}
					if ($token =='{' || $token[0] == T_IMPLEMENTS) {
						$state = 'DETECT';
						break;
					}
					$this->unexpectedToken($token);
					$state ='DETECT';
					break;

				case 'INTERFACE':
					if ($token[0] == T_STRING) {
						if ($namespace == '') {
							$definition = $token[1];
						} else {
							$definition = $namespace.'\\'.$token[1];
						}
						$definitions[] = $definition;
						$state = 'DETECT';
						break;
					}
					$this->unexpectedToken($token);
					$state ='DETECT';
					break;

				default:
					throw new \Exception('Unexpected state: "'.$state.'"');
			}
		}
		if ($settings['detect_accidental_output'] && $token[0] == T_INLINE_HTML) {
			notice('Invalid end of file. (html)output detected in "'.basename($filename).'"');
		}
		/* elseif ($token[0] == T_CLOSE_TAG && $token[1] != '?>') {
			notice('Invalid end of file, accidental newline detected in "'.basename($filename).'"'); // newline directly after the close tag doesn't cause problems
		} */
		if (count($definitions) > 1) {
			if ($settings['one_definition_per_file']) {
				notice('Multiple definitions found in '.$filename, $definitions);
			}
		} elseif ($settings['mandatory_definition'] && count($definitions) == 0) {
			notice('No classes or interfaces found in '.$filename);
		}
		$filename = $this->relativePath($filename);
		foreach ($definitions as $definition) {
			if (isset($this->definitions[$definition])) {
				if ($this->definitions[$definition] != $filename) {
					notice('"'.$definition.'" is ambiguous, it\'s found in multiple files: "'.$this->definitions[$definition].'" and "'.$filename.'"', array('settings' => $settings));
				} else {
					//if ($settings['one_definition_per_file']) {
					//$this->parserNotice('"'.$identifier.'" is declared multiple times in: "'.$loaderDefinition['filename'].'"');
					//}
				}
			}
			$this->definitions[$definition] = $filename;
		}
	}

	/**
	 * Veranderdt de scope van alle functies en eigenschappen van de $class van private of protected naar public.
	 * Hierdoor kun je in UnitTests controleren of de inhoud van het objecten correct is.
	 * Deze functie NIET gebruiken in productie-code
	 *
	 * @throws Exception Als de class al gedefineerd is. (De eval() zou anders fatale "Cannot redeclare class" veroorzaken).
	 * @return void
	 */
	function exposePrivates($class) {
		if (class_exists($class, false)) {
			throw new \Exception('Class: "'.$class.'" is already defined');
		}
		$filename = $this->getFilename($class);
		$tokens = token_get_all(file_get_contents($filename));
		$php_code = '';
		if ($tokens[0][0] != T_OPEN_TAG) {
			notice('Unexpected beginning of the file, expecting "<?php"');
			return;
		} else {
			unset($tokens[0]); // Haal de "<?php" er vanaf
		}
		foreach ($tokens as $token) {
			if (is_string($token)) {
				$php_code .= $token;
				continue;
			}
			switch ($token[0]) {

				// Geen commentaar
				case T_DOC_COMMENT:
				case T_COMMENT:
					break;
				// Geen private en protected
				case T_PRIVATE:
				case T_PROTECTED:
					$php_code .= 'public';
					break;

				// Alle andere php_code toevoegen aan de $php_code string
				default:
					$php_code .= $token[1];
					break;
			}
		}
		// De php code uitvoeren en de class (zonder protected en private) declareren
		eval($php_code);
	}

	/**
	 *
	 * @param string $cacheFile
	 * @param null|string $definitionPath
	 */
	function writeCache($cacheFile, $definitionPath = null) {
		$definitions = array();
		if ($definitionPath === null) {
			$definitions = $this->definitions;
		} else {
			$length = strlen($definitionPath);
			foreach ($this->definitions as $definition => $filename) {
				if (substr($filename, 0, $length) == $definitionPath) {
					$definitions[$definition] = $filename;
				}
			}
		}
		ksort($definitions);
		$php = "<?php\n/**\n * Generated AutoLoader Cache\n */\n\$definitions = array(\n";
		foreach ($definitions as $definition => $filename) {
			$php .= "\t'".  addslashes($definition)."' => '".  addslashes($filename)."',\n";
		}
		$php .= ");\n?>";
		file_put_contents($cacheFile, $php);
	}

	private function mergeSettings($settings, $overrides = array()) {
		foreach ($overrides as $key => $value) {
			if (array_key_exists($key, $this->settings) == false) {
				notice('Invalid setting: "'.$key.'" = '.syntax_highlight($value), array('Available settings' => array_keys($this->settings)));
			}
			$settings[$key] = $value;
		}
		if (count($settings) !== count($this->settings)) {
			// Use global settings values
			foreach ($this->settings as $key => $value) {
				if (array_key_exists($key, $settings) == false) {
					$settings[$key] = $value;
				}
			}
		}
		return $settings;
	}

	private function loadSettings($path, $settings = array()) {
		if (substr($path, -1) == '/') {
			$path = substr($path, 0, -1);
		}
		$overrides = array();
		if (file_exists($path.'/autoloader.ini')) {
			$overrides = parse_ini_file($path.'/autoloader.ini', true);
			if (isset($overrides['ignore_folders'])) {
				$folders = array();
				foreach(explode(',', $overrides['ignore_folders']) as $folder) {
					if (substr($folder, -1) == '/') {
						$folder = substr($folder, 0, -1);
					}
					$folders[] = $path.'/'.$folder;
				}
				$overrides['ignore_folders'] = $folders;
			}
			if (isset($overrides['ignore_files'])) {

				$files = array();
				foreach(explode(',', $overrides['ignore_files']) as $filename) {
					$files[] = $path.'/'.$filename;
				}
				$overrides['ignore_files'] = $files;
			}
		}
		return $this->mergeSettings($settings, $overrides);
	}

	private function unexpectedToken($token) {
		if (is_string($token)) {
			notice('Unexpected token: '.syntax_highlight($token));
		} else {
			notice('Unexpected token: '.token_name($token[0]).': '.syntax_highlight($token[1]).' on line '.$token[2]);
		}
	}

	/**
	 * Maakt van een absoluut path een relatief path (waar mogelijk)
	 *
	 * @param $filename Absoluut path
	 */
	private function relativePath($filename) {
		if (strpos($filename, $this->path) === 0) {
			$filename = substr($filename, strlen($this->path));
		}
		return $filename;
	}

	/**
	 * Geeft aan een absoluut path terug voor $filename
	 *
	 * @param string $filename relatief of absoluut path van het bestand
	 */
	private function fullPath($filename) {
		if (DIRECTORY_SEPARATOR == '/') {// Gaat het om een unix bestandsysteem
			// Bij een unix variant begint een absoluut path met  '/'
			if (substr($filename, 0, 1) == '/') {
				return $filename; // Absoluut path
			}
		} elseif (preg_match('/^[a-z]{1}:/i', $filename)) { //  Een windows absoluut path begint met driveletter. bv 'C:\'
			return $filename; // Absoluut path
		}
		// Anders was het een relatief path
		return $this->path.$filename;
	}
}
?>