<?php
/**
 * Object
 */
namespace Sledgehammer;
/**
 * A generic php superclass.
 *
 * Improved error reporting:
 *   When accessing non-existing properties show a list show a list of available properties.
 *   When calling a non-existing method show a list show a list of available methods.
 *
 * Changes compared to PHP's stdClass behaviour:
 *   Generates a warning when setting a non-yet-existing property (instead of silently adding the property)
 *   Throws an Exception when calling a non-existing method (instead of a fatal error)
 *   Generates a notice when the object is used as a string (instead of throwing an exception)
 *
 * @package Core
 */
abstract class Object {

	/**
	 * Report that $property doesn't exist.
	 *
	 * @param string $property
	 * @return void
	 */
	function __get($property) {
		$rObject = new \ReflectionObject($this);
		$values = get_object_vars($this);
		$properties = array();
		foreach ($rObject->getProperties() as $rProperty) {
			if ($rProperty->isPublic()) {
				if (array_key_exists($rProperty->name, $values) === false) {
					continue; // skip properties that are unset()
				}
				$scope = 'public';
			} elseif ($rProperty->isProtected()) {
				$scope = 'protected';
			} elseif ($rProperty->isPrivate()) {
				$scope = 'private';
			}
			$properties[$scope][] = $rProperty->name;
		}
		$propertiesText = '';
		$glue = '<br />&nbsp;&nbsp;$';
		foreach ($properties as $scope => $props) {
			$propertiesText .= '<b>'.$scope.' properties</b>'.$glue.implode($glue, $props).'<br /><br />';
		}
		warning('Property: "'.$property.'" doesn\'t exist in a "'.get_class($this).'" object.', $propertiesText);
	}

	/**
	 * Report that $property doesn't exist and set the property to the given $value.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	function __set($property, $value) {
		Object::__get($property); // Report error
		$this->$property = $value; // Add the property to the object. (PHP's default behaviour)
	}

	/**
	 * Report that the $method doesn't exist.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return void
	 */
	function __call($method, $arguments) {
		$rObject = new \ReflectionObject($this);
		$methods = array();
		foreach ($rObject->getMethods() as $rMethod) {
			if (in_array($rMethod->name, array('__get', '__set', '__call', '__toString'))) {
				continue;
			}
			if ($rMethod->isPublic()) {
				$scope = 'public';
			} elseif ($rMethod->isProtected()) {
				$scope = 'protected';
			} elseif ($rMethod->isPrivate()) {
				$scope = 'private';
			}
			$parameters = array();
			foreach ($rMethod->getParameters() as $rParam) {
				$param = '$'.$rParam->name;
				if ($rParam->isDefaultValueAvailable()) {
					$param .= ' = '.syntax_highlight($rParam->getDefaultValue());
				}
				$parameters[] = $param;
			}
			$methods[$scope][] = syntax_highlight($rMethod->name, 'method').'('.implode(', ', $parameters).')';
		}
		$methodsText = '';
		$glue = '<br />&nbsp;&nbsp;';
		foreach ($methods as $scope => $mds) {
			$methodsText .= '<b>'.$scope.' methods</b>'.$glue.implode($glue, $mds).'<br /><br />';
		}
		throw new InfoException('Method: "'.$method.'" doesn\'t exist in a "'.get_class($this).'" object.', $methodsText);
	}

	/**
	 * The object is used as an string
	 *
	 * @return string
	 */
	function __toString() {
		notice('Object: "'.get_class($this).'" is used as string');
		return "Object(".get_class($this).")";
	}

}

?>