<?php
/**
 * Verantwoordelijk voor het on-the-fly inladen en declareren van classes en interfaces.
 * Dit verbeterd parsetijd/geheugenverbruik aanzienlijk, alleen de bestanden die je nodig hebt worden ge-include.
 *
 * @package Core
 */
namespace SledgeHammer;
class PHPAnalyzer extends Object {
	
	public $classes = array();
	
	public $interfaces = array();

	/**
	 * Extract class and interface definitions from a file.
	 *
	 * @param string $filename Fullpath to the php-file.
	 */
	function open($filename) {
		$tokens = new PHPTokenizer(file_get_contents($filename));
		unset($source);
		
		$namespace = '';
		$uses = array();
		$definitions = array();
		foreach ($tokens as $token) {
			$type = $token[0];
			if ($type == 'T_PHP' || $type == 'T_HTML') {
				continue;
			}
			$value = $token[1];
			switch ($type) {
				
				case 'T_NAMESPACE':
					$namespace = $value;
					break;
				
				case 'T_USE':
					$pos = strrpos($value, '\\');
					$namespaceAlias = substr($value, $pos + 1);
					$uses[$namespaceAlias] = $value;
					break;
					
				case 'T_USE_AS':
					$uses[$value] = $uses[$namespaceAlias];
					unset($uses[$namespaceAlias]);
					break;
					
				
				case 'T_INTERFACE':
					$definitions[] = array(
						'type' => 'INTERFACE',
						'namespace' => $namespace,
						'interface' => $value,
						'identifier' => $this->prefixNamespace($namespace, $value, $uses),
						'extends' => array()
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
						'implements' => array()
						
					);
					$definition = &$definitions[count($definitions) - 1];
					break;
				
				case 'T_EXTENDS':
					$definition['extends'][] = $this->prefixNamespace($namespace, $value, $uses);
					break;
				
				case 'T_IMPLEMENTS':
					$definition['implements'][] = $this->prefixNamespace($namespace, $value, $uses);
					break;
				
				default:
					notice('Unexpected tokenType: "'.$type.'"');
					break;
			}
		}
		unset($definition);
		// Add definitions to de loader
		foreach ($definitions as $index => $definition) {
			$identifier = $definition['identifier'];
			unset($definition['identifier']);
			$definition['filename'] = $filename;
			/*
			$duplicate = false;
			if (isset($this->classes[$identifier])) {
				$duplicate = $this->classes[$identifier];
			} elseif (isset($this->interfaces[$identifier])) {
				$duplicate = $this->interfaces[$identifier];
			}
			if ($duplicate) {
				$this->parserNotice('"'.$identifier.'" is ambiguous, it\'s found in multiple files: "'.$duplicate['filename'].'" and "'.$definition['filename'].'"');
			}*/
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
	 * Resolve the full classname.
	 * 
	 * @param string $namespace
	 * @param string $identifier  The class or interface name 
	 * @return string 
	 */
	private function prefixNamespace($namespace, $identifier, $uses = array()) {
		$pos = strpos($identifier, '\\');
		if ($pos !== false) {
			if ($pos === 0) {
				return substr($identifier, 1);
			}
			foreach ($uses as $alias => $namespace) {
				$alias .= '\\'; 
				if (substr($identifier, 0, strlen($alias)) === $alias) {
					return $namespace.substr($identifier, strlen($alias) - 1);
				}
			}
			return $identifier;
		}
		if (isset($uses[$identifier])) {
			return $uses[$identifier];
		}
		if ($namespace == '') {
			return $identifier;
		}
		return $namespace.'\\'.$identifier;
	}
/*
	private function unexpectedToken($token, $filename) {
		if (is_string($token)) {
			$error = syntax_highlight($token);
		} else {
			$error = token_name($token[0]).': '.syntax_highlight($token[1]);
		}
		notice('Unexpected token: '.$error.' in "'.$this->relativePath($filename).'"');
	}
 */
}
?>
