<?php
/**
 * De algemene superclasse van sledgehammer objecten
 *
 * Verzorgt verbeterde foutafhandeling van objecten zoals:
 *   Bij het opvragen van een eigenschap dit niet bestaat krijg je een lijst met eigenschappen die wel bestaan, dit geld ook voor de methodes.
 *   Een foutmelding zodra je eigenschap wilt aanpassen die niet in het object zit (is namelijk toegestaan in php)
 *   
 * @package Core
 */
abstract class Object {

	/**
	 * Er wordt een eigenschap opgevraagd die niet bestaat
	 *
	 * @param string $property naam van de eigenschap
	 * @return void
	 */
	function __get($property) {		
		$rObject = new ReflectionObject($this);
		$properties = array();
		foreach ($rObject->getProperties() as $rProperty) {
			if ($rProperty->isPublic()) {
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
	 * Eigenschap instellen die (nog) niet bestaat. 
	 * Geeft een foutmeldinging, maar voegt vervolgens de waarde toe aan het object.
	 *
	 * @param string $property naam van de eigenschap
	 * @param mixed $value     Waarde van de property
	 * @return void
	 */
	function __set($property, $value) {
		Object::__get($property); // Via __get de foutmelding genereren
		$this->$property = $value; // De eigenschap toevoegen. PHP's default gedrag,
	}

	/**
	 * Er wordt een methode aangeroepen die niet bestaat
	 *
	 * @param $method naam van de methode die wordt aangeroepen
	 * @param $arguments Argumenten van de aangeroepen methode
	 * @return void
	 */
	function __call($method, $arguments) {
		$rObject = new ReflectionObject($this);
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
		error('Method: "'.$method.'" doesn\'t exist in a "'.get_class($this).'" object.', $methodsText);
	}

	/**
	 * Het object wordt als string gebruikt.
	 *
	 * @return string
	 */
	function __toString() {
		notice('Object: "'.get_class($this).'" is used as string');
		return "Object(".get_class($this).")";
	}
}
?>
