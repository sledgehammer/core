<?php
/**
 * Verzorgt een meganisme om dynamische eigenschappen aan een object toe te voegen.
 *
 * @package Core
 */

abstract class DynamicObject extends Object {

	protected
		$_DynamicProperties = array(); // Dynamische eigenschappen (werkt met behulp van update_property($property)) 	

	/**
	 * De subclassen zal deze functie moeten overschrijven om gebruik te maken van de dynamische eigenschappen.
	 * In deze functie zul je de $_DynamicProperties[$property] moeten instellen op de waarde die als $DynamicObject->$property wilt gebruiken.
	 * 
	 * @param string $property Naam van de eigenschap die bijgewerkt moet worden
	 * @return void
	 */
	abstract protected function update_property($property);
	// Voorbeeld implementatie: 
	// if ($property == "adres") {
	//   $this->_DynamicProperties[$property] = $this->straatnaam.' '.$this->huisnummer;
	// }

	/**
	 * Een onbekende eigenschap opvragen.
	 *
	 * @return mixed
	 */
	function __get($property) {
		if (array_key_exists($property, $this->_DynamicProperties)) { // Gaat het om een dynamische eigenschap?
			$this->update_property($property);
			return $this->_DynamicProperties[$property];
		}
		return parent::__get($property);
	}

	/**
	 * Een onbekende eigenschap instellen.
	 * 
	 * @return void
	 */
	function __set($property, $value) {
		if (array_key_exists($property, $this->_DynamicProperties)) { // Gaat het om een dynamische eigenschap?
			$this->_DynamicProperties[$property] = $value;
		} else {
			parent::__set($property, $value);
		}
	}

	/**
	 * Opvragen of de dynamische eigenschap bestaat.
	 * Wordt gebruikt door de isset() en empty() functies.
	 *
	 * @return bool
	 */
	function __isset($property) {
		if (array_key_exists($property, $this->_DynamicProperties)) { // Staat de eigenschap in de dynamische eigenschappen array?
			$this->update_property($property);
			return isset($this->_DynamicProperties[$property]);
		}
		return false;
	}
}
?>
