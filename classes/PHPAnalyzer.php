<?php
/**
 * PHPAnalyzer
 */
namespace Sledgehammer;
/**
 * Statically Analyzes PHP code and collects data about class and interface usage and deflarations.
 *
 * @package Core
 */
class PHPAnalyzer extends Object {

	/**
	 * Deflared classes.
	 * array(
	 *   $fullclassname => array(
	 *     'namespace' => $namespace,
	 *     'class' => $classname,
	 *     'extends' => $fullsuperclass,
	 *     'implements' => array($interface, ...),
	 *     'methods' => array(
	 *       $methodname => array(
	 *         $parameter => $defaultvalue,
	 *         ...
	 *       ),
	 *       ...
	 *     ),
	 *     'filename' => $fullpath,
	 *   ),
	 *   ...
	 * )
	 * @var array
	 */
	public $classes = array();

	/**
	 * Deflared classes.
	 * array(
	 *   $fullinterfacename => array(
	 *     'namespace' => $namespace,
	 *     'interface' => $interfacename,
	 *     'extends' => array($interface, ...),
	 *     'methods' => array(
	 *       $methodname => array(
	 *         $parameter => $defaultvalue,
	 *         ...
	 *       ),
	 *       ...
	 *     ),
	 *     'filename' => $fullpath,
	 *   ),
	 *   ...
	 * )
	 * @var array
	 */
	public $interfaces = array();

	/**
	 * array(
	 *   $fulldefinitionname => array(
	 *     $fullpath => array($linenr, ...),
	 *     ...
	 *   )
	 * )
	 * @var array
	 */
	public $usedDefinitions = array();

	/**
	 * Function calls
	 * @var array
	 */
	public $usedFunctions = array();

	/**
	 * The AutoLoader used to lookup the corresponding filename for the definitions.
	 * Uses the Framework::$autoLoader by default.
	 * @var AutoLoader
	 */
	private $autoLoader;

	/**
	 * Extract class and interface definitions from a file.
	 *
	 * @param string $filename Fullpath to the php-file.
	 */
	function open($filename) {
		$tokens = new PHPTokenizer(file_get_contents($filename));

		$namespace = '';
		$uses = array();
		$definitions = array();
		$definition = array(
			'level' => -1
		);
		$globalFunctions = array();
		$functions = &$globalFunctions;
		$level = 0;
		$parameterLevel = -1; // skip parameters in anonymous functions
		foreach ($tokens as $token) {
			$type = $token[0];
			$value = $token[1];
			if ($value == '' && $type != 'T_NAMESPACE') {
				notice('Empty token', $token);
			}
			if ($type == 'T_PHP' || $type == 'T_HTML') {
				continue;
			}
			switch ($type) {

				case 'T_NAMESPACE':
					$namespace = $value;
					break;

				case 'T_USE':
					$pos = strrpos($value, '\\');
					$namespaceAlias = substr($value, $pos + 1);
					$uses[$namespaceAlias] = $value;
					break;

				case 'T_USE_ALIAS':
					$uses[$value] = $uses[$namespaceAlias];
					unset($uses[$namespaceAlias]);
					break;


				case 'T_INTERFACE':
					$definitions[] = array(
						'type' => 'INTERFACE',
						'namespace' => $namespace,
						'interface' => $value,
						'identifier' => $this->prefixNamespace($namespace, $value, $uses),
						'extends' => array(),
						'methods' => array(),
						'level' => $level
					);
					$definition = &$definitions[count($definitions) - 1];
					break;

				case 'T_CLASS':
					$definitions[] = array(
						'type' => 'CLASS',
						'namespace' => $namespace,
						'class' => $value,
						'identifier' => $this->prefixNamespace($namespace, $value, $uses),
						'extends' => array(),
						'implements' => array(),
						'methods' => array(),
						'level' => $level
					);
					$definition = &$definitions[count($definitions) - 1];
					break;

				case 'T_EXTENDS':
					$extends = $this->prefixNamespace($namespace, $value, $uses);
					$definition['extends'][] = $extends;
					$this->addUsedIn($extends, $filename, $token[2]);
					break;

				case 'T_IMPLEMENTS':
					$interface = $this->prefixNamespace($namespace, $value, $uses);
					$definition['implements'][] = $interface;
					$this->addUsedIn($interface, $filename, $token[2]);
					break;

				case 'T_FUNCTION':
					$function = $value;
					$parameter = null;
					if ($level == ($definition['level'] + 1)) {
						$definition['methods'][$function] = array();
						$functions = &$definition['methods'];
					} else {
						$functions = &$globalFunctions;
					}
					$parameterLevel = $level;
					break;

				case 'T_TYPE_HINT':
					if (strtolower($value) !== 'array') {
						$this->addUsedIn($this->prefixNamespace($namespace, $value, $uses), $filename, $token[2]);
					}
					break;

				case 'T_PARAMETER':
					if ($parameterLevel != $level) { // Doesn't this parameter belong to the function?
						break; // Propably a catch () parameter
					}
					$parameter = substr($value, 1); // strip '$'
					$functions[$function][$parameter] = null;
					break;

				case 'T_PARAMETER_VALUE':
					$functions[$function][$parameter] = $value;
					$parameter = null;
					break;

				case 'T_OPEN_BRACKET':
					$level++;
					break;

				case 'T_CLOSE_BRACKET':
					$level--;
					break;

				case 'T_CALL':
					$this->addCalledIn($value, $filename, $token[2]);
					break;

				case 'T_METHOD_CALL':
					break;

				case 'T_OBJECT':
					$this->addUsedIn($this->prefixNamespace($namespace, $value, $uses), $filename, $token[2]);
					break;

				default:
					notice('Unexpected tokenType: "'.$type.'"');
					break;
			}
		}
		if ($level != 0) {
			notice('Level: '.$level.' Number of "{" doesn\'t match the number of "}"');
		}
		unset($definition);
		// Add definitions to de loader
		foreach ($definitions as $index => $definition) {
			$identifier = $definition['identifier'];
			unset($definition['identifier'], $definition['level']);
			$definition['filename'] = $filename;
//			$duplicate = false;
//			if (isset($this->classes[$identifier])) {
//				$duplicate = $this->classes[$identifier];
//			} elseif (isset($this->interfaces[$identifier])) {
//				$duplicate = $this->interfaces[$identifier];
//			}
//			if ($duplicate) {
//				$this->parserNotice('"'.$identifier.'" is ambiguous, it\'s found in multiple files: "'.$duplicate['filename'].'" and "'.$definition['filename'].'"');
//			}
			switch ($definition['type']) {

				case 'CLASS':
					unset($definition['type']);
					if (count($definition['extends']) > 1) {
						notice('Class: "'.$definition['class'].'" Multiple inheritance is not allowed for classes');
						$definition['extends'] = $definition['extends'][0];
					} elseif (count($definition['extends']) == 1) {
						$definition['extends'] = $definition['extends'][0];
					} else {
						unset($definition['extends']);
					}
					if (count($definition['implements']) == 0) {
						unset($definition['implements']);
					}
					$this->classes[$identifier] = $definition;
					break;

				case 'INTERFACE':
					unset($definition['type']);
					$this->interfaces[$identifier] = $definition;
					break;

				default:
					throw new \Exception('Unsupported type: "'.$definition['type'].'"');
			}
		}
	}

	/**
	 * Extract definition information.
	 *
	 * @param string $definition
	 * @return array
	 */
	function getInfo($definition) {
		// Check analyzed definitions
		if (isset($this->classes[$definition])) {
			return $this->classes[$definition];
		}
		if (isset($this->interfaces[$definition])) {
			return $this->interfaces[$definition];
		}
		$filename = $this->getAutoLoader()->getFilename($definition);
		if ($filename !== null) {
			$this->open($filename);
		} elseif (class_exists($definition, false) || interface_exists($definition, false)) {
			$this->getInfoWithReflection($definition);
		}
		if (isset($this->classes[$definition])) {
			return $this->classes[$definition];
		}
		if (isset($this->interfaces[$definition])) {
			return $this->interfaces[$definition];
		}
		throw new \Exception('Definition "'.$definition.'" is not found');
	}

	/**
	 * Use PHP's Reflection classes to extract definition information.
	 *
	 * @param string $definition Class or Interace name
	 * @return array
	 */
	function getInfoWithReflection($definition) {
		if (class_exists($definition) == false && interface_exists($definition) == false) {
			//throw new \Exception('Definition "'.$definition.'" is unknown');
		}
		$reflectionClass = new \ReflectionClass($definition);
		$info = array(
			'namespace' => $reflectionClass->getNamespaceName()
		);
		$class = $reflectionClass->name;
		if ($reflectionClass->isInterface()) {
			$info['interface'] = $class;
			$info['extends'] = $reflectionClass->getInterfaceNames();
		} else {
			$info['class'] = $class;
			$info['implements'] = $reflectionClass->getInterfaceNames();
			$info['extends'] = $reflectionClass->getParentClass();
			if ($info['extends'] == false || $info['extends']->name == 'stdClass') {
				unset($info['extends']);
			} else {
				$info['extends'] = $info['extends']->name;
			}
		}
		$info['methods'] = array();
		foreach ($reflectionClass->getMethods() as $reflectionMethod) {
			if ($reflectionMethod->class != $class) {
				continue; // De methoden uit de parentclass negeren
			}
			$method = $reflectionMethod->name;
			$info['methods'][$method] = array();
			foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
				$parameter = $reflectionParameter->name;
				$info['methods'][$method][$parameter] = null;
				if ($reflectionParameter->isDefaultValueAvailable()) {
					$value = $reflectionParameter->getDefaultValue();
					$info['methods'][$method][$parameter] = $value;
				}
			}
		}
		if ($reflectionClass->isInterface()) {
			$this->interfaces[$definition] = $info;
		} else {
			$this->classes[$definition] = $info;
		}
		return $info;
	}

	/**
	 * Use the given $autoloader for resolving filenames.
	 *
	 * @param AutoLoader $autoLoader
	 */
	function setAutoLoader(AutoLoader $autoLoader) {
		$this->autoLoader = $autoLoader;
	}

	/**
	 * Get the configured autoloader instance.
	 *
	 * @return AutoLoader
	 */
	private function getAutoLoader() {
		if ($this->autoLoader === null) {
			return Framework::$autoLoader;
		}
		return $this->autoLoader;
	}

	/**
	 * Resolve the full classname.
	 *
	 * @param string $namespace  Active namespace
	 * @param string $identifier  The class or interface name
	 * @param array $uses  Active USE statements.
	 * @return string
	 */
	private function prefixNamespace($namespace, $identifier, $uses = array()) {
		$pos = strpos($identifier, '\\');
		if ($pos !== false) {
			if ($pos === 0) {
				// Fully qualified name (\Foo\Bar)
				return substr($identifier, 1);
			}
			// Qualified name (Foo\Bar)
			foreach ($uses as $alias => $namespace) {
				$alias .= '\\';
				if (substr($identifier, 0, strlen($alias)) === $alias) {
					return $namespace.substr($identifier, strlen($alias) - 1);
				}
			}
		} else {
			// Unqualified name (Foo)
			if (isset($uses[$identifier])) { // Is an alias?
				return $uses[$identifier];
			}
		}
		if ($namespace == '') {
			return $identifier;
		}
		return $namespace.'\\'.$identifier;
	}

	/**
	 * Register that a definition is used in $file on $line.
	 *
	 * @param string $definition  The class/interface that is used
	 * @param string $filename  The filename it is use in
	 * @param int $line  The line number it is used on
	 */
	private function addUsedIn($definition, $filename, $line) {
		@$this->usedDefinitions[$definition][$filename][] = $line;
	}

	/**
	 * Register that a function is called in $file on $line.
	 *
	 * @param string $function  The class/interface that is used
	 * @param string $filename  The filename it is use in
	 * @param int $line  The line number it is used on
	 */
	private function addCalledIn($function, $filename, $line) {
		@$this->usedFunctions[$function][$filename][] = $line;
	}
}

?>
