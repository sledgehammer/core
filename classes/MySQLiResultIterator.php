<?php
/**
 * Een database resultset die met een foreach afgehandeld kan worden
 *
 * Gebruik:
 *  for($Iterator->rewind(); $Iterator->valid(); $Iterator->next()) {
 *  	$key = $Iterator->key();
 *  	$value = $Iterator->current();
 *  }
 *  foreach($Iterator as $key => $value) {
 *  }
 * @todo: Ook de interfaces Seekable en mischien zelfs ArrayAcces implementeren.
 *
 * @package Core
 */
namespace SledgeHammer;
class MySQLiResultIterator extends Object implements \Iterator, \Countable{

	public
		$key, // [string|NULL] De kolomnaam die moet doorgaan als key in een foreach.
		$value, // [string|NULL] De kolomnaam die moet doorgaan als value in een foreach
		$Result; // [mysqli_result] Een database result object 

	private 
		$row, // [array|false] De waardes van de huidige rij
		$valid = false, // Houdt bij of de Iterator nog geldig is, (bij 'false' stopt de foreach).
		$counter, // [int] teller die bijhoud in welke rij de Iterator zich bevind.
		$rewind_result = false; // [bool] true: Geeft aan dat er een data_seek(0) nodig is bij de aanroep van rewind()

	/**
	 * @param mysqli_result $Result Een object waarmee de result van een query doorlopen kan worden
	 */
	function __construct($Result, $key = NULL, $value = NULL) {
		$this->Result = $Result;
		$this->key = $key;
		$this->value = $value;
		if (is_array($key)) {
			warning('Datatype: "'.gettype($key).'" is not allowed for $key, expecting NULL or string');
		}
	}

	function __destruct() {
		$this->Result->free(); // Het geheugen dat door de resultset verbruikt wordt weer vrijgeven.
	}

	/**
	 * Terug naar de eerste rij
	 *
	 * @return void
	 */
	function rewind() {
		if ($this->rewind_result) { // Staat de Result niet meer op rij 0?
			if (!$this->Result->data_seek(0)) {
				$this->valid = false;
				return;
			}
		}
		$this->valid = true;
		$this->counter = -1; // Omdat direct de eerste rij wordt ingelezen met next() de counter op -1 instellen, na de next() zal deze op 0 staan.
		$this->next(); // Eerste rij inladen.
		$this->rewind_result = true;
	}

	/**
	 * Huidige waarde opvragen, dit kan een elkele waarde zijn of de hele rij afhankelijk van de $this->value instelling.
	 *
	 * @return mixed
	 */
	function current() {
		if ($this->value === NULL) {
			return $this->row;
		} else {
			return $this->row[$this->value];
		}
	}

	/**
	 * De volgende rij opvragen
	 *
	 * @return void
	 */
	function next() {
		if (!$this->row = $this->Result->fetch_assoc()) {
			$this->valid = false;
		}
		$this->counter++;
	}

	/**
	 * De sleutel/ interne pointer opvragen.
	 *
	 * @return mixed
	 */
	function key() {
		if ($this->key === NULL) {
			return $this->counter; 
		} else {
			return $this->row[$this->key];
		}
	}

	/**
	 *  Geeft aan of er nog verder geitereerd kan worden. false zodra de laatste rij is bereikt
	 *  @return bool
	 */
	function valid() {
		return $this->valid;
	}

	/**
	 * Vraag het aantal rijen op
	 *
	 * @return int
	 */
	function count() {
		return $this->Result->num_rows;
	}
}
?>
