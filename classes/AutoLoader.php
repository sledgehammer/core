<?php
/**
 * AutoLoader
 */
namespace Sledgehammer;
/**
 * Load class and interface definitions on demand.
 * Improves performance (parsetime & memory usage), only classes that are used are loaded.
 *
 * Validates definiton files according to $this->settings.
 * Detects and corrects namespace issues.
 *
 * @package Core
 */
class AutoLoader extends Object {

	/**
	 * Bij true zal define() een fout genereren als de class niet bekend is.
	 * @var bool
	 */
	public $standalone = true;

	/**
	 * If a class or interface doesn't exist in a namespace use the class from a higher namespace
	 * @var bool
	 */
	public $resolveNamespaces = true;

	/**
	 * Bij true worden de resultaten (per module) gecached, de cache zal opnieuw opgebouwt worden als er bestanden gewijzigd of toegevoegd zijn.
	 * @var bool
	 */
	public $enableCache = true;

	/**
	 * The project basepath
	 * @var string
	 */
	private $path;

	/**
	 * Checks that are enabled when the module contains a classes folder.
	 * The settings can be overridden with by placing an  autoloader.ini in the offending folder.
	 * @var array
	 */
	private $defaultSettings = array(
		'matching_filename' => true, // The classname should match the filename.
		'mandatory_definition' => true, // A php-file should declare a class or interface
		'mandatory_superclass' => true, // A class should extend another class (preferably \Sledgehammer\Object as base)
		'one_definition_per_file' => true, // A php-file should only contain one class or inferface definition.
		'ignore_folders' => array(), // Exclude these folders (relative from autoloader.ini) otherwise use absolute paths
		'ignore_files' => array(), // Exclude these files (relative from autoloader.ini) otherwise use absolute paths
		'revalidate_cache_delay' => 10, // Check/detect changes every x seconds.
		'detect_accidental_output' => true, // Check if the php-file contains html parts (which would send the http headers)
		'cache_level' => 1, // Number of (sub)folders to create caches for
	);

	/**
	 * Array containing the filename per class or interface.
	 * @var array
	 */
	private $definitions = array();

	/**
	 * Constructor
	 * @param string $path Project path
	 */
	function __construct($path) {
		$this->path = $path;
	}

	/**
	 * Load definitions from a static file or detect definition for all modules.
	 *
	 * @param string $filename Location of the database file.
	 * @param bool $merge  Merge the definitions with the existing definitions. (false: overwrite all definitions)
	 * @return void
	 */
	function loadDatabase($filename, $merge = false) {
		$definitions = '__NONE__';
		include($filename);
		if ($definitions === '__NONE__') {
			warning('Invalid database file: "'.$filename.'"', 'No $definitions where found');
			return;
		}
		if ($merge) {
			$this->definitions += $definitions;
		} else {
			$this->definitions = $definitions;
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
		if ($filename === null) { // Class not found?
			$backtrace = debug_backtrace();
			if (isset($backtrace[2]['function']) && $backtrace[2]['function'] === 'class_exists') {
				// Don't report warnings or resolveNamespace for "class_exists()"
				return false;
			}
			if ($this->resolveNamespaces && $this->resolveNamespace($definition)) {
				return true; // The class/interface is defined by resolving a namespace
			}
			if ($this->standalone) {
				warning('Unknown definition: "'.$definition.'"', array('Available definitions' => implode(array_keys($this->definitions), ', ')));
			}
			return false;
		}
		$success = include_once($filename);
		if (class_exists($definition, false) || interface_exists($definition, false) || (version_compare(PHP_VERSION, '5.4.0') >= 0 && trait_exists($definition, false))) {
			return true;
		}
		if ($success === true) { // file might already included.
			// Detect class_exists() autoloader loop.
			$backtrace = debug_backtrace();
			if (isset($backtrace[2]['function']) && $backtrace[2]['function'] === 'class_exists' && realpath($backtrace[2]['file']) == realpath($filename)) {
				// class definition is inside a if (class_exists($clasname, true)) statement;
				return false;
			}
		}
		if ($success !== 1) {
			throw new \Exception('Failed to include "'.$filename.'"');
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
		if (substr($definition, 0, 1) === '\\') {
			$definition = substr($definition, 1);
		}
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

	/**
	 * Returns all definitions the AutoLoaders has detected.
	 *
	 * @return array
	 */
	function getDefinitions() {
		return array_keys($this->definitions);
	}

	/**
	 * Import a class into the required namespace.
	 *
	 * @param string $definition Fully qualified class/interface name
	 * @return bool
	 */
	private function resolveNamespace($definition) {
		if (strpos($definition, '\\') === false) { // Definition in the global namespace?
			if ($this->standalone == false) {
				return false; // Allow the other autoloaders to define the definition.
			}
			$extends = false;
			$class = $definition;
			foreach (array_keys($this->definitions) as $definition) {
				$pos = strrpos($definition, '\\');
				if ($pos !== false && substr($definition, $pos + 1) === $class) {
					$extends = $definition;
					$targetNamespace = '';
					$this->define($definition);
					break;
				}
			}
			if ($extends === false) { // No matching classname found?
				return false;
			}
		} else {
			$namespaces = explode('\\', $definition);
			$class = array_pop($namespaces);
			$targetNamespace = implode('\\', $namespaces);
			array_pop($namespaces); // een namespace laag hoger
			$extends = implode('\\', $namespaces);
			if ($extends == '') {
				$extends = $class;
			} else {
				$extends .= '\\'.$class;
			}
			if (isset($this->definitions[$extends])) {
				$this->define($extends);
			}
		}
		$php = 'namespace '.$targetNamespace." {\n\t";
		if (class_exists($extends, false)) {
			$php .= 'class '.$class;
			$reflection = new \ReflectionClass($extends);
			if (count($reflection->getMethods(\ReflectionMethod::IS_ABSTRACT)) !== 0) {
				notice('Cant\' import "'.$class.'" into namespace "'.$targetNamespace.'" ("'.$extends.'" contains abstract methods)');
				return false;
			}
		} elseif (interface_exists($class, false)) {
			$php .= 'interface '.$class;
		} else {
			return false;
		}
		if ($targetNamespace === '') {
			$namespaces = explode('\\', $definition);
			array_pop($namespaces);
			warning('Definition "'.$class.'" not found, importing "'.$definition.'" into the the global namespace', 'Change the classname or add "namespace '.implode('\\', $namespaces).';" or "use \\'.$definition.';" to the beginning of the php file"');
		} elseif (error_reporting() == (error_reporting() | E_STRICT)) { // Strict mode
			notice('Importing "'.$class.'" into namespace "'.$targetNamespace.'"', 'Use "\\'.$extends.'"');
		}
		$php .= ' extends \\'.$extends." {}\n}";
		eval($php);

		return true;
	}

	/**
	 * Import definitions inside a module.
	 * Uses strict validation rules when the module contains a classes folder.
	 *
	 * @param array $module
	 * @return void
	 */
	function importModule($module) {
		deprecated('importModule is deprecated in favor of importFolder()');
		$path = $module['path'];
		if (file_exists($module['path'].'classes')) {
			$path = $path.'classes';
			$settings = $this->settings; // Strict settings
		} else {
			// Disable validations
			$settings = array(
				'matching_filename' => false,
				'mandatory_definition' => false,
				'mandatory_superclass' => false,
				'one_definition_per_file' => false,
				'revalidate_cache_delay' => 20,
				'detect_accidental_output' => false,
			);
		}
		$this->importFolder($path, $settings);
	}

	/**
	 * Import definitions inside a folder.
	 * Checks "autoloader.ini" for additional settings.
	 *
	 * @param string $path
	 * @param array $settings
	 * @return void
	 */
	function importFolder($path, $settings = array()) {
		$settings = $this->loadSettings($path, $settings);
		$useCache = ($this->enableCache && $settings['cache_level'] > 0);
		if ($useCache) {
			$settings['cache_level']--;
			$folder = basename($path);
			if ($folder == 'classes') {
				$folder = basename(dirname($path));
			}
			$cacheFile = TMP_DIR.'AutoLoader/'.$folder.'_'.md5($path).'.php';
			if (!mkdirs(dirname($cacheFile))) {
				$this->enableCache = false;
				$useCache = false;
			} elseif (file_exists($cacheFile)) {
				$mtimeCache = filemtime($cacheFile);
				$revalidateCache = ($mtimeCache < (time() - $settings['revalidate_cache_delay'])); // Is er een delay ingesteld en is deze nog niet verstreken?;
				$mtimeFolder = ($revalidateCache ? mtime_folders($path) : 0);
				if ($mtimeFolder !== false && $mtimeCache > $mtimeFolder) { // Is het cache bestand niet verouderd?
					$this->loadDatabase($cacheFile, true);
					if ($settings['revalidate_cache_delay'] && $revalidateCache) { // is het cache bestand opnieuw gevalideerd?
						touch($cacheFile); // de mtime van het cache-bestand aanpassen, (voor het bepalen of de delay is vertreken)
					}
					return;
				}
			}
		}
		// Import files & subfolders
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
		if ($useCache) {
			$this->saveDatabase($cacheFile, $this->relativePath($path));
		}
	}

	/**
	 * Import the definition in a file
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

						case T_TRAIT:
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
					} elseif ($settings['mandatory_superclass'] && !in_array($definition, array('Sledgehammer\Object', 'Sledgehammer\Framework', 'Sledgehammer\ErrorHandler'))) {
						notice('Class: "'.$definition.'" has no superclass, expection "class X extends Y"');
					}
					if ($token == '{' || $token[0] == T_IMPLEMENTS) {
						$state = 'DETECT';
						break;
					}
					$this->unexpectedToken($token);
					$state = 'DETECT';
					break;

				case 'INTERFACE':
					if ($token[0] == T_STRING) {
						if ($settings['matching_filename'] && substr(basename($filename), 0, -4) != $token[1]) {
							notice('Filename doesn\'t match interface-name "'.$token[1].'" in "'.$filename.'"', array('settings' => $settings));
						}
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
					$state = 'DETECT';
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
	 * Convert the scope of all properties and methods to public.
	 * Allows you to inspect the private parts of an object from unittests.
	 * Don't use exposePrivates in production code.
	 *
	 *  @throws Exceptions when the (target)definition is already defined. (Prevent the fatal error: "Cannot redeclare class/interface")
	 *
	 * @param string $definition Name of the definition with private properties en methods.
	 * @param string $targetDefinition (optional) Specify an alternative classname for the exposed code.
	 * @return void
	 */
	function exposePrivates($definition, $targetDefinition = null) {
		if ($targetDefinition === null) {
			$targetDefinition = $definition;
		} elseif (dirname(str_replace ('\\', '/', $definition)) !== dirname(str_replace ('\\', '/', $targetDefinition))) {
			throw new \Exception('Target: "'.$targetDefinition.'" does\'t match the "'.$definition.'" namespace');
		}
		if (class_exists($targetDefinition, false)) {
			throw new \Exception('Class: "'.$targetDefinition.'" is already defined');
		}
		if (interface_exists($targetDefinition, false)) {
			throw new \Exception('Interace: "'.$targetDefinition.'" is already defined');
		}
		$filename = $this->getFilename($definition);
		if ($filename === null) {
			throw new InfoException('Unknown definition: "'.$definition.'"', array('Available definitions' => implode(array_keys($this->definitions), ', ')));
		}
		$tokens = token_get_all(file_get_contents($filename));
		$code = '';
		$tokenCount = count($tokens);
		for ($i = 0; $i < $tokenCount; $i++) {
			$token = $tokens[$i];
			if (is_string($token)) {
				$code .= $token;
				continue;
			}
			if ($i === 0) {
				if ($token[0] !== T_OPEN_TAG) {
					throw new \Exception('Unexpected beginning of the file, expecting "<?php"');
				}
				continue; // don't add <?php to the $code.
			}

			switch ($token[0]) {

				// Geen private en protected
				case T_PRIVATE:
				case T_PROTECTED:
					$code .= 'public';
					break;

				case T_CLASS:
				case T_INTERFACE:
					$code .= $token[1]; // 'class' or 'interface'
					$code .= $tokens[$i + 1][1]; // whitespace
					$code .= basename(str_replace ('\\', '/', $targetDefinition)); // target definition (without namespace)
					$i+= 2;
					break;

				// Alle andere php_code toevoegen aan de $php_code string
				default:
					$code .= $token[1];
					break;
			}
		}
		// De php code uitvoeren en de class (zonder protected en private) declareren
		eval($code);
	}

	/**
	 * Save all imported definitions to database file.
	 *
	 * @param string $filename  The location of the database file.
	 * @param null|string $pathFilter  Only save definition in this path. null: saves all imported definitions.
	 * @return void
	 */
	function saveDatabase($filename, $pathFilter = null) {
		$definitions = array();
		if ($pathFilter === null) {
			$definitions = $this->definitions;
		} else {
			$length = strlen($pathFilter);
			foreach ($this->definitions as $definition => $file) {
				if (substr($file, 0, $length) == $pathFilter) {
					$definitions[$definition] = $file;
				}
			}
		}
		ksort($definitions);
		$php = "<?php\n/**\n * AutoLoader database\n */\n\$definitions = array(\n";
		foreach ($definitions as $definition => $file) {
			$php .= "\t'".addslashes($definition)."' => '".addslashes($file)."',\n";
		}
		$php .= ");\n?>";
		file_put_contents($filename, $php);
	}

	/**
	 * Merge settings
	 *
	 * @param array $settings
	 * @param array $overrides
	 * @return array
	 */
	private function mergeSettings($settings, $overrides = array()) {
		$availableSettings = array_keys($this->defaultSettings);
		foreach ($overrides as $key => $value) {
			if (array_key_exists($key, $this->defaultSettings)) {
				$settings[$key] = $value;
			} else {
				notice('Invalid setting: "'.$key.'" = '.syntax_highlight($value), array('Available settings' => $availableSettings));
			}
		}
		if (array_keys($settings) != $availableSettings) {
			$missing = array_diff_key($this->defaultSettings, $settings);
			foreach ($missing as $key => $value) {
				$settings[$key] = $value; // Use global setting
			}
			if (count($settings) !== count($this->defaultSettings)) { // Contains invalid settings?
				$invalid = array_diff_key($settings, $this->defaultSettings);
				notice('Invalid setting: "'.key($invalid).'" = '.syntax_highlight(current($invalid)), array('Available settings' => $availableSettings));
			}
		}
		return $settings;
	}

	/**
	 * Load settings from a ini file which overrides settings for that folder & subfolders.
	 *
	 * @param string $path
	 * @param array $settings
	 * @return array
	 */
	private function loadSettings($path, $settings = array()) {
		if (substr($path, -1) == '/') {
			$path = substr($path, 0, -1);
		}
		$overrides = array();
		if (file_exists($path.'/autoloader.ini')) {
			$overrides = parse_ini_file($path.'/autoloader.ini', true);
			if (isset($overrides['ignore_folders'])) {
				$folders = array();
				foreach (explode(',', $overrides['ignore_folders']) as $folder) {
					if (substr($folder, -1) == '/') {
						$folder = substr($folder, 0, -1);
					}
					$folders[] = $path.'/'.$folder;
				}
				$overrides['ignore_folders'] = $folders;
			}
			if (isset($overrides['ignore_files'])) {

				$files = array();
				foreach (explode(',', $overrides['ignore_files']) as $filename) {
					$files[] = $path.'/'.$filename;
				}
				$overrides['ignore_files'] = $files;
			}
		}
		return $this->mergeSettings($settings, $overrides);
	}

	/**
	 * Report the offending token
	 *
	 * @param string|array $token
	 */
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