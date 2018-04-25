<?php

namespace Sledgehammer\Core\Debug;

use DirectoryIterator;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sledgehammer\Core\InfoException;
use Sledgehammer\Core\Base;
use Sledgehammer\Core\Singleton;

/**
 * Load class and interface definitions on demand.
 * Improves performance (parsetime & memory usage), only classes that are used are loaded.
 *
 * Validates definiton files according to $this->settings.
 * Detects and corrects namespace issues.
 */
class Autoloader extends Base
{
    use Singleton;

    /**
     * If a class or interface doesn't exist in a namespace use the class from a higher namespace.
     *
     * @var bool
     */
    public $resolveNamespaces = true;

    /**
     * Bij true worden de resultaten (per module) gecached, de cache zal opnieuw opgebouwt worden als er bestanden gewijzigd of toegevoegd zijn.
     *
     * @var bool
     */
    public $enableCache = false;

    /**
     * The project basepath.
     *
     * @var string
     */
    private $path;

    /**
     * Checks that are enabled when the module contains a classes folder.
     * The settings can be overridden with by placing an  autoloader.ini in the offending folder.
     *
     * @var array
     */
    private $defaultSettings = array(
        'matching_filename' => false, // The classname should match the filename.
        'mandatory_definition' => false, // A php-file should declare a class or interface
        'mandatory_superclass' => false, // A class should extend another class (preferably \Sledgehammer\Object as base)
        'one_definition_per_file' => false, // A php-file should only contain one class or inferface definition.
        'ignore_folders' => ['.git'], // Exclude these folders (relative from autoloader.ini) otherwise use absolute paths
        'ignore_files' => [], // Exclude these files (relative from autoloader.ini) otherwise use absolute paths
        'revalidate_cache_delay' => 10, // Check/detect changes every x seconds.
        'detect_accidental_output' => true, // Check if the php-file contains html parts (which would send the http headers)
        'cache_level' => 1, // Number of (sub)folders to create caches for
        'filesize_limit' => 524288, // Skip files larger than 512KiB (to prevent out of memory issues)
        'notice_ambiguous' => true, // Show a notice if a definition is ambiguous.
);

    /**
     * Array containing the filename per class or interface.
     *
     * @var array
     */
    private $definitions = [];

    /**
     * Array containing skipped ambiguous definitions.
     *
     * @var array
     */
    private $ambiguous = [];

    /**
     * Constructor.
     *
     * @param string $path Project path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Load definitions from a static file or detect definition for all modules.
     *
     * @param string $filename          Location of the database file.
     * @param bool   $merge             Merge the definitions with the existing definitions. (false: overwrite all definitions)
     * @param int    $expectedScanCount The expected scanCount in the database.
     *
     * @return bool
     */
    public function loadDatabase($filename, $merge = false, $expectedScanCount = false)
    {
        $scanCount = '__NONE__';
        $definitions = '__NONE__';
        $ambiguous = '__NONE__';
        include $filename;
        if ($definitions === '__NONE__') {
            \Sledgehammer\warning('Invalid database file: "'.$filename.'"', '$definitions was not defined');

            return false;
        }
        if ($ambiguous === '__NONE__') {
            \Sledgehammer\warning('Invalid database file: "'.$filename.'"', '$ambiguous was not defined');

            return false;
        }
        if ($expectedScanCount) {
            if ($scanCount === '__NONE__') {
                \Sledgehammer\warning('Invalid database file: "'.$filename.'"', '$scanCount was not defined');

                return false;
            }
            if ($scanCount !== $expectedScanCount && $scanCount !== false) { // Number of files didn't match.
                return false; // file deletion detected
            }
        }

        if ($merge) {
            $this->definitions += $definitions;
            foreach ($ambiguous as $definition => $files) {
                if (isset($this->ambiguous[$definition])) {
                    $this->ambiguous[$definition] = array_merge($this->ambiguous[$definition], $files);
                } else {
                    $this->ambiguous[$definition] = $files;
                }
            }
        } else {
            $this->definitions = $definitions;
            $this->ambiguous = $ambiguous;
        }
        foreach ($this->ambiguous as $definition => $files) {
            if (isset($this->definitions[$definition])) { // Ignore ambiguos definitions
                $this->ambiguous[$definition][] = $this->definitions[$definition];
                unset($this->definitions[$definition]);
            } elseif (count($files) === 1) { // Only 1 ambigous, the other implementation was removed?
                $this->definitions[$definition] = array_pop($files);
                unset($this->ambiguous[$definition]);
            }
        }

        return true;
    }

    /**
     * include() the file containing the class of interface.
     *
     * @param string $definition Fully qualified class or interface name
     */
    public function define($definition)
    {
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
            if ($this->isLast()) {
                if (isset($this->ambiguous[$definition])) {
                    \Sledgehammer\warning('Ambiguous definition: "'.$definition.'"', array('Multiple implentations' => $this->ambiguous[$definition]));
                } else {
                    \Sledgehammer\warning('Unknown definition: "'.$definition.'"', array('Available definitions' => implode(array_keys($this->definitions), ', ')));
                }
            }

            return false;
        }
        $success = include_once $filename;
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
            throw new Exception('Failed to include "'.$filename.'"');
        }
        throw new Exception('AutoLoader is corrupt, class "'.$definition.'" not found in "'.$filename.'"');
    }

    /**
     * Get the filename.
     *
     * @param string $definition Fully qualified class/interface name
     *
     * @return string|null Return null if the definion can't be found
     */
    public function getFilename($definition)
    {
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
                    \Sledgehammer\notice('Definition "'.$definition.'" not found, using "'.$name.'" fallback');
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
    public function getDefinitions()
    {
        return array_keys($this->definitions);
    }

    /**
     * Import a class into the required namespace.
     *
     * @param string $definition Fully qualified class/interface name
     *
     * @return bool
     */
    private function resolveNamespace($definition)
    {
        if (strpos($definition, '\\') === false) { // Definition in the global namespace?
            if ($this->isLast() === false) {
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
            if (count($namespaces) === 1 && $namespaces[0] == 'Sledgehammer') { // Pre 2016 notation?
                $extends = 'Sledgehammer\\Core\\'.$class;
            } else {
                // Try stripping 1 namespace (todo multiple)
                array_pop($namespaces); // een namespace laag hoger
                $extends = implode('\\', $namespaces);
                if ($extends == '') {
                    $extends = $class;
                } else {
                    $extends .= '\\'.$class;
                }
            }
            if (isset($this->definitions[$extends])) {
                $this->define($extends);
            }
        }
        $php = 'namespace '.$targetNamespace." {\n\t";
        if (class_exists($extends, false)) {
            $php .= 'class '.$class;
            $reflection = new ReflectionClass($extends);
            if (count($reflection->getMethods(ReflectionMethod::IS_ABSTRACT)) !== 0) {
                \Sledgehammer\notice('Cant\' import "'.$class.'" into namespace "'.$targetNamespace.'" ("'.$extends.'" contains abstract methods)');

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
            \Sledgehammer\warning('Definition "'.$class.'" not found, importing "'.$definition.'" into the the global namespace', 'Change the classname or add "namespace '.implode('\\', $namespaces).';" or "use \\'.$definition.';" to the beginning of the php file"');
        } elseif (error_reporting() == (error_reporting() | E_STRICT)) { // Strict mode
            \Sledgehammer\notice('Importing "'.$extends.'" into namespace "'.$targetNamespace.'"', 'use '.$extends.';');
        }
        $php .= ' extends \\'.$extends." {}\n}";
        eval($php);

        return true;
    }

    /**
     * Is this the last autoload function?
     *
     * @return bool
     */
    private function isLast()
    {
        $loaders = spl_autoload_functions();
        $last = $loaders[count($loaders) - 1];

        return is_array($last) && $last[0] === $this;
    }

    /**
     * Import definitions inside a module.
     * Uses strict validation rules when the module contains a classes folder.
     *
     * @param array $module
     */
    public function importModule($module)
    {
        \Sledgehammer\deprecated('importModule is deprecated in favor of importFolder()');
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
     * @param array  $settings
     */
    public function importFolder($path, $settings = [])
    {
        $composer = false;
        if (file_exists($path.'/composer.json')) {
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
            $composer = json_decode(file_get_contents($path.'composer.json'), true);
        }
        if ($composer && isset($composer['sledgehammer'])) {
            $settings += [
                'matching_filename' => true,
                'mandatory_definition' => true, // A php-file should declare a class or interface (unless the filename is lowercase)
                'mandatory_superclass' => true, // A class should extend another class (preferably \Sledgehammer\Object as base)
                'one_definition_per_file' => true, // A php-file should only contain one class or inferface definition.
                'detect_accidental_output' => true, // Check if the php-file contains html parts (which would send the http headers)
                'notice_ambiguous' => true, // Show a notice if a definition is ambiguous.
            ];
        }
        $settings = $this->loadSettings($path, $settings);
        if ($composer) {
            $locations = [];
            $trace = [];
            $preventDefault = true;
            if (isset($composer['autoload']['classmap'])) {
                foreach ($composer['autoload']['classmap'] as $entry) {
                    $paths = is_array($entry) ? $entry : array($entry);
                    foreach ($paths as $entryPath) {
                        if (empty($entryPath) || $entry === '.') {
                            \Sledgehammer\notice('Empty autoload.classmap in "composer.json" isn\'t supported');
                            $preventDefault = false;
                            break;
                        } elseif (is_dir($path.$entryPath)) {
                            $locations[] = $entryPath;
                            $trace[] = 'classmap';
                        }
                    }
                }
            }
            $namespaces = [];
            if (isset($composer['autoload']['psr-0'])) {
                $namespaces += $composer['autoload']['psr-0'];
            }
            if (isset($composer['autoload']['psr-4'])) {
                $namespaces += $composer['autoload']['psr-4'];
            }
            if (isset($composer['autoload-dev']['psr-0'])) {
                $namespaces += $composer['autoload-dev']['psr-0'];
            }
            if (isset($composer['autoload-dev']['psr-4'])) {
                $namespaces += $composer['autoload-dev']['psr-4'];
            }
            foreach ($namespaces as $namespace => $entry) {
                $paths = is_array($entry) ? $entry : array($entry);
                foreach ($paths as $entryPath) {
                    if (in_array($entryPath, array('', '/', '.'))) {
                        $preventDefault = false;
                        break 2;
                    }
                    $locations[] = $entryPath;
                    $trace[] = 'psr-0 ('.$namespace.')';
                }
            }
            if ($preventDefault) {
                foreach ($locations as $i => $entry) {
                    if (is_dir($path.$entry)) {
                        if (in_array($entry, $settings['ignore_folders'])) {
                            continue;
                        }
                        $this->importFolder($path.$entry, $settings);
                    } elseif (is_file($path.$entry)) {
                        if (in_array($entry, $settings['ignore_files'])) {
                            continue;
                        }
                        $this->importFile($path.$entry, $settings);
                    } else {
                        // Allow invalid composer.json configurations, not Sledgehammers problem.
                        // \Sledgehammer\notice('Invalid "composer.json" entry: '.$trace[$i].': "'.$entry.'"', 'file or directory: "'.$path.$entry.'" not found');
                    }
                }

                return;
            }
        }
        $useCache = ($this->enableCache && $settings['cache_level'] > 0);
        if ($useCache) {
            --$settings['cache_level'];
            $scanCount = false;
            $folder = basename($path);
            if ($folder == 'classes') {
                $folder = basename(dirname($path));
            }
            $cacheFile = \Sledgehammer\TMP_DIR.'AutoLoader/'.$folder.'_'.md5($path).'.php';
            if (!\Sledgehammer\mkdirs(dirname($cacheFile))) {
                $this->enableCache = false;
                $useCache = false;
            } elseif (file_exists($cacheFile)) {
                $mtimeCache = filemtime($cacheFile);
                $revalidateCache = ($mtimeCache < (time() - $settings['revalidate_cache_delay'])); // Is er een delay ingesteld en is deze nog niet verstreken?;
                $mtimeFolder = ($revalidateCache ? \Sledgehammer\mtime_folders($path, array('php'), $scanCount) : 0);
                if ($mtimeFolder !== false && $mtimeCache > $mtimeFolder) { // Is het cache bestand niet verouderd?
                    if ($this->loadDatabase($cacheFile, true, $scanCount)) {
                        if ($settings['revalidate_cache_delay'] && $revalidateCache) { // is het cache bestand opnieuw gevalideerd?
                            touch($cacheFile); // de mtime van het cache-bestand aanpassen, (voor het bepalen of de delay is vertreken)
                        }

                        return;
                    }
                }
            }
        }
        // Import files & subfolders
        try {
            $dir = new DirectoryIterator($path);
        } catch (Exception $e) {
            \Sledgehammer\notice($e->getMessage());

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
            if (\Sledgehammer\file_extension($entry->getFilename()) == 'php') {
                if (in_array($entry->getPathname(), $settings['ignore_files']) == false) {
                    $this->importFile($entry->getPathname(), $settings);
                }
            }
        }
        if ($useCache) {
            $this->saveDatabase($cacheFile, $this->relativePath($path), $scanCount);
        }
    }

    /**
     * Import the definition in a file.
     *
     * @param string $filename
     * @param array  $settings
     *
     * @return array definitions
     */
    public function importFile($filename, $settings = [])
    {
        $setttings = $this->mergeSettings($settings);
        $previousError = error_get_last();
        if (filesize($filename) > $settings['filesize_limit']) {
            $this->hint('File '.$filename.' too big, skipping...', array(
                'allowed size' => $settings['filesize_limit'],
                'actual size' => filesize($filename),
            ));
            return;
        }
        $tokens = token_get_all(file_get_contents($filename));
        $error = error_get_last();
        if ($error !== $previousError) {
            \Sledgehammer\notice($error['message'].' in "'.$filename.'"');
        }
        $definitions = [];
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

                        case T_DOUBLE_COLON:
                            $state = 'SKIP_ONE';
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
                            \Sledgehammer\notice('Filename doesn\'t match classname "'.$token[1].'" in "'.$filename.'"', array('settings' => $settings));
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
                    } elseif ($settings['mandatory_superclass'] && !in_array($definition, array('Sledgehammer\Core\Base'))) {
                        \Sledgehammer\notice('Class: "'.$definition.'" has no superclass, expection "class X extends Y"');
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
                            \Sledgehammer\notice('Filename doesn\'t match interface-name "'.$token[1].'" in "'.$filename.'"', array('settings' => $settings));
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

                case 'SKIP_ONE':
                    $state = 'DETECT';
                    break;

                default:
                    throw new Exception('Unexpected state: "'.$state.'"');
            }
        }
        if ($settings['detect_accidental_output'] && $token[0] == T_INLINE_HTML) {
            \Sledgehammer\notice('Invalid end of file. (html)output detected in "'.basename($filename).'"');
        }
        /* elseif ($token[0] == T_CLOSE_TAG && $token[1] != '?>') {
          \Sledgehammer\notice('Invalid end of file, accidental newline detected in "'.basename($filename).'"'); // newline directly after the close tag doesn't cause problems
          } */
        if (count($definitions) > 1) {
            if ($settings['one_definition_per_file']) {
                \Sledgehammer\notice('Multiple definitions found in '.$filename, $definitions);
            }
        } elseif ($settings['mandatory_definition'] && count($definitions) === 0 && basename($filename) !== strtolower(basename($filename))) {
            \Sledgehammer\notice('No classes or interfaces found in '.$filename);
        }
        $filename = $this->relativePath($filename);
        foreach ($definitions as $definition) {
            if (isset($this->definitions[$definition]) && $this->definitions[$definition] != $filename) {
                if (empty($this->ambiguous[$definition])) {
                    $this->ambiguous[$definition] = array($this->definitions[$definition]);
                }
                unset($this->definitions[$definition]); // Ignore both definitions to prevent autoloading the wrong one.
            }
            if (isset($this->ambiguous[$definition])) {
                $this->ambiguous[$definition][] = $filename;
                if ($settings['notice_ambiguous']) {
                    \Sledgehammer\notice('"'.$definition.'" is ambiguous, it\'s found in multiple files: '.\Sledgehammer\quoted_human_implode(' and ', $this->ambiguous[$definition]), array('settings' => $settings));
                }
            } else {
                $this->definitions[$definition] = $filename;
            }
        }
    }

    /**
     * Convert the scope of all properties and methods to public.
     * Allows you to inspect the private parts of an object from unittests.
     * Don't use exposePrivates in production code.
     *
     *  @throws Exceptions when the (target)definition is already defined. (Prevent the fatal error: "Cannot redeclare class/interface")
     *
     * @param string $definition       Name of the definition with private properties en methods.
     * @param string $targetDefinition (optional) Specify an alternative classname for the exposed code.
     */
    public function exposePrivates($definition, $targetDefinition = null)
    {
        if ($targetDefinition === null) {
            $targetDefinition = $definition;
        } elseif (dirname(str_replace('\\', '/', $definition)) !== dirname(str_replace('\\', '/', $targetDefinition))) {
            throw new Exception('Target: "'.$targetDefinition.'" does\'t match the "'.$definition.'" namespace');
        }
        if (class_exists($targetDefinition, false)) {
            throw new Exception('Class: "'.$targetDefinition.'" is already defined');
        }
        if (interface_exists($targetDefinition, false)) {
            throw new Exception('Interace: "'.$targetDefinition.'" is already defined');
        }
        $filename = $this->getFilename($definition);
        if ($filename === null) {
            throw new InfoException('Unknown definition: "'.$definition.'"', array('Available definitions' => implode(array_keys($this->definitions), ', ')));
        }
        $tokens = token_get_all(file_get_contents($filename));
        $code = '';
        $tokenCount = count($tokens);
        for ($i = 0; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            if (is_string($token)) {
                $code .= $token;
                continue;
            }
            if ($i === 0) {
                if ($token[0] !== T_OPEN_TAG) {
                    throw new Exception('Unexpected beginning of the file, expecting "<?php"');
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
                    $code .= basename(str_replace('\\', '/', $targetDefinition)); // target definition (without namespace)
                    $i += 2;
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
     * @param string      $filename   The location of the database file.
     * @param null|string $pathFilter Only save definition in this path. null: saves all imported definitions.
     * @param int         $scanCount  The number of files scanned (for delete detection)
     */
    public function saveDatabase($filename, $pathFilter = null, $scanCount = false)
    {
        if ($pathFilter === null) {
            $definitions = $this->definitions;
            $ambiguous = $this->ambiguous;
        } else {
            $definitions = [];
            $ambiguous = [];
            $length = strlen($pathFilter);
            foreach ($this->definitions as $definition => $file) {
                if (substr($file, 0, $length) == $pathFilter) {
                    $definitions[$definition] = $file;
                }
            }
            foreach ($this->ambiguous as $definition => $files) {
                foreach ($files as $file) {
                    if (substr($file, 0, $length) == $pathFilter) {
                        if (empty($ambiguous[$definition])) {
                            $ambiguous[$definition] = [];
                        }
                        $ambiguous[$definition][] = $file;
                    }
                }
            }
        }
        ksort($definitions);
        ksort($ambiguous);
        $php = "<?php\n/**\n * AutoLoader database\n */\n";
        $php .= '$scanCount = '.($scanCount === false ? 'false' : intval($scanCount)).";\n";
        $php .= "\$definitions = array(\n";
        foreach ($definitions as $definition => $file) {
            $php .= "\t'".addslashes($definition)."' => '".addslashes($file)."',\n";
        }
        $php .= ");\n";
        $php .= "\$ambiguous = array(\n";
        foreach ($ambiguous as $definition => $files) {
            $php .= "\t'".addslashes($definition)."' => array(";
            foreach ($files as $file) {
                $php .= "'".addslashes($file)."',";
            }
            $php .= "),\n";
        }
        $php .= ");\n?>";

        file_put_contents($filename, $php);
    }

    /**
     * Merge settings.
     *
     * @param array $settings
     * @param array $overrides
     *
     * @return array
     */
    private function mergeSettings($settings, $overrides = [])
    {
        $availableSettings = array_keys($this->defaultSettings);
        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $this->defaultSettings)) {
                $settings[$key] = $value;
            } else {
                \Sledgehammer\notice('Invalid setting: "'.$key.'" = '.\Sledgehammer\syntax_highlight($value), array('Available settings' => $availableSettings));
            }
        }
        if (array_keys($settings) != $availableSettings) {
            $missing = array_diff_key($this->defaultSettings, $settings);
            foreach ($missing as $key => $value) {
                $settings[$key] = $value; // Use global setting
            }
            if (count($settings) !== count($this->defaultSettings)) { // Contains invalid settings?
                $invalid = array_diff_key($settings, $this->defaultSettings);
                \Sledgehammer\notice('Invalid setting: "'.key($invalid).'" = '.\Sledgehammer\syntax_highlight(current($invalid)), array('Available settings' => $availableSettings));
            }
        }

        return $settings;
    }

    /**
     * Load settings from a ini file which overrides settings for that folder & subfolders.
     *
     * @param string $path
     * @param array  $settings
     *
     * @return array
     */
    private function loadSettings($path, $settings = [])
    {
        if (substr($path, -1) == '/') {
            $path = substr($path, 0, -1);
        }
        $overrides = [];
        if (file_exists($path.'/autoloader.ini')) {
            $overrides = parse_ini_file($path.'/autoloader.ini', true);
            if (isset($overrides['ignore_folders'])) {
                $folders = [];
                foreach (explode(',', $overrides['ignore_folders']) as $folder) {
                    if (substr($folder, -1) == '/') {
                        $folder = substr($folder, 0, -1);
                    }
                    $folders[] = $path.'/'.$folder;
                }
                $overrides['ignore_folders'] = $folders;
            }
            if (isset($overrides['ignore_files'])) {
                $files = [];
                foreach (explode(',', $overrides['ignore_files']) as $filename) {
                    $files[] = $path.'/'.$filename;
                }
                $overrides['ignore_files'] = $files;
            }
        }

        return $this->mergeSettings($settings, $overrides);
    }

    /**
     * Report the offending token.
     *
     * @param string|array $token
     */
    private function unexpectedToken($token)
    {
        if (is_string($token)) {
            \Sledgehammer\notice('Unexpected token: '.\Sledgehammer\syntax_highlight($token));
        } else {
            \Sledgehammer\notice('Unexpected token: '.token_name($token[0]).': '.\Sledgehammer\syntax_highlight($token[1]).' on line '.$token[2]);
        }
    }

    /**
     * Maakt van een absoluut path een relatief path (waar mogelijk).
     *
     * @param $filename Absoluut path
     */
    private function relativePath($filename)
    {
        if (strpos($filename, $this->path) === 0) {
            $filename = substr($filename, strlen($this->path));
        }

        return $filename;
    }

    /**
     * Geeft aan een absoluut path terug voor $filename.
     *
     * @param string $filename relatief of absoluut path van het bestand
     */
    private function fullPath($filename)
    {
        if (DIRECTORY_SEPARATOR == '/') {
            // Gaat het om een unix bestandsysteem
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

    public static function lazyRegister($definition)
    {
        $backtrace = debug_backtrace();
        if (isset($backtrace[2]['function']) && $backtrace[2]['function'] === 'class_exists') {
            // Don't activate the Autoloader for "class_exists()"
            return;
        }
        if (\Sledgehammer\ENVIRONMENT === 'development') {
            self::hint('Activating the Sledgehammer Autoloader, Composer failed to load "'.$definition.'"');
        }
        $autoloader = self::instance();
    }

    public static function defaultInstance()
    {
        // Register the AutoLoader
        $autoloader = new self(\Sledgehammer\PATH);

        // Initialize the AutoLoader
        if (file_exists(\Sledgehammer\PATH.'AutoLoader.db.php')) {
            $autoloader->loadDatabase(\Sledgehammer\PATH.'AutoLoader.db.php');
        } else {
            if (file_exists(\Sledgehammer\PATH.'/composer.json')) {
                $autoloader->importFolder(\Sledgehammer\PATH);
            }
            // Add Autoloader support for the other vendor packages.
            // Fixes cASe issues, repair namespaces, etc.
            if (file_exists(\Sledgehammer\VENDOR_DIR)) { // Does the app have vendor packages?
                $autoloader->importFolder(\Sledgehammer\VENDOR_DIR, [
                    'matching_filename' => false,
                    'mandatory_definition' => false,
                    'mandatory_superclass' => false,
                    'one_definition_per_file' => false,
                    'revalidate_cache_delay' => 30,
                    'detect_accidental_output' => false,
                    'ignore_folders' => ['.git'],
                    'cache_level' => 3,
                    'notice_ambiguous' => false,
                ]);
            }
        }
        spl_autoload_register(array($autoloader, 'define'));
        spl_autoload_unregister('Sledgehammer\Core\Debug\AutoLoader::lazyRegister');

        return $autoloader;
    }

    /**
     * Report the notice but prevent the (Laravel) error_handler to throw an exception.
     */
    private static function hint($message, $information = null) {
        $handler = set_error_handler('error_log');
        restore_error_handler();
        if ($handler !== null) {
            if (is_array($handler) && is_object($handler[0]) && $handler[0] instanceof \Illuminate\Foundation\Bootstrap\HandleExceptions) {
                \Illuminate\Support\Facades\Log::notice($message);
            } else {
                \Sledgehammer\notice($message, $information);
            }
        }
    }
}
