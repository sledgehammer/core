<?php
/**
 * Een generieke snelle manier om een csv bestand in te lezen. 
 * Kan ook csv bestanden genereren met CSVIterator->write()
 *
 * De eerste regel van het csv bestand kan gebruikt worden om de kolomnamen te defineren.
 *
 * @package Core
 */
namespace SledgeHammer;
class CSVIterator extends Object implements \Iterator {

	private
		$filename, // path van het csv bestand
		$columns, // Bepaald welke kolommen er ingelezen worden, en hoe deze genoemt worden.
		$delimiter, // Het karakter dat gebruik wordt om de velden te scheiden
		$enclosure, // Het karakter dat gebruikt wordt om tekst-velden te omsluiten (default: ")

		$fp, // [resource] file pointer/handler
		$keys, // Veranderd een $row van een indexed array naar assoc array
		$values, // [array] huidige rij
		$line, // [int] huidige regelnummer (Begint bij 2 en komt overeen met de regelnummers in excel/openoffice)

		$eol = "\r\n"; // De linebreak die gebruikt wordt bij een write() 

	/**
	 * @param string $filename  Het path waar het csv bestand zich bevind
	 * @param array $columns  Hiermee geef je aan welke kolommen ingelezen moeten worden. $value = kolomnaam, $key = array_key  
	 * @param char $delimiter  Scheidingsteken, ';' als default omdat dit nederlandse excel standaard is.
	 * @param char $enclosure  Karakter dat gebruikt word om tekst waarbinnen het scheidingsteken kan voorkomen te omsluiten
	 */
	function __construct($filename = null, $columns = null, $delimiter = ';', $enclosure = '"') {
		$this->columns = $columns;
		$this->filename = $filename;
		$this->delimiter = $delimiter;
		$this->enclosure = $enclosure;
	}

	function __destruct() {
		if ($this->fp) {
			fclose($this->fp); // Als het object niet meer nodig is, kan de file-pointer gesloten worden.
		}
	}

	/**
	 * Schrijf de waarden in de $iterator weg naar $filename die in de constuctor is meegegeven.
	 * D.m.v "php://output" kan het csv bestand direct naar de browser verstuurd worden.
	 * 
	 * @param Iterator $iterator  Brongegevens voor de csv. Elementen worden bepaald d.m.v. de $keys in de $this->columns array.
	 * @return void
	 */
	function write($iterator) {
		if ($this->fp) {
			throw new \Exception('CSVIterator is in read-mode');
		}
		$this->fp =	fopen($this->filename, 'w');
		if (!$this->fp) {
			throw new \Exception('Failed to open "'.$this->filename.'" for writing');
		} 
		// De kolomnamen op de eerste csv regel zetten
		if ($this->columns !== null) {
			$this->fputcsv($this->columns);
			$columnKeys = array_keys($this->columns);
		} else {
			// Detecteer colomnamen (gebruik de array keys)
			if (is_array($iterator)) {
				reset($iterator);
				$row = current($iterator);
			} else {
				$iterator->rewind();
				$row = $iterator->current();
			}
			$columnKeys = array_keys($row);
			$this->fputcsv($columnKeys);
		}
		foreach ($iterator as $row) {
			$values = array();
			foreach ($columnKeys as $key) {
				$values[] = $row[$key];
			}
			$this->fputcsv($values);
		}
		fclose($this->fp);
		$this->fp = null;
	}

	/**
	 * Het csv bestand (opnieuw) openen en de eerste rij inlezen als kolomnamen.
	 */
	function rewind() {
		$this->line = null;
		if ($this->fp) {
			fclose($this->fp);
		}
		$this->fp = fopen($this->filename, 'r');
		if (!$this->fp) {
			throw new \Exception('Couldn\'t open file "'.$this->filename.'"');
		}
		// Kolommen controleren
		$keys = fgetcsv($this->fp, 0, $this->delimiter, $this->enclosure);
		$column_isset = array();
		foreach ($keys as $column) {
			if (isset($column_isset[$column])) {
				if ($this->columns === null || array_search($column, $this->columns) !== false) {
					throw new \Exception('Column "'.$column.'" is ambiguous');
				}
			} else {
				$column_isset[$column] = true;
			}
		}
		if ($this->columns === null) {
			$this->keys = $keys;
		} else {
			foreach ($this->columns as $key => $column) {
				$index = array_search($column, $keys);
				if ($index !== false) {
					$this->keys[$index] = $key;
				} else {
					throw new \Exception('Column: "'.$column.'" not found. Available columns: "'.implode('", "',$keys).'"');
					return false;
				}
			}
		}
		$this->line++;
		$this->next();
	}

	/**
	 * De rij inlezen en naar de volgende regel gaan.
	 *
	 * @return void
	 */
	function next() {
		$this->values = array();
		$row = fgetcsv($this->fp, 0, $this->delimiter, $this->enclosure);
		$this->line++;
		if ($row) { // Is het einde (eof) nog niet bereikt?
			foreach ($this->keys as $index => $key) {
				if (isset($row[$index])) { // Is er voor deze kolom een waarde? 
					$this->values[$key] = $row[$index];
				} else {
					$filename = (strpos($this->filename, PATH) === 0) ? substr($this->filename, strlen(PATH)) : $this->filename; // Waar mogelijk het PATH er van af halen
					notice('Row too short, missing column '.$index.'('.$this->columns[$key].') in '.$filename.' on line '.$this->line, $row);
				}
			}
		}
	}

	/**
	 * Geeft aan of er nog records in het bestand zitten
	 *
	 * @return bool
	 */
	function valid() {
		return !feof($this->fp); // Is het einde van het bestand NIET bereikt?
	}

	/**
	 * Huidige rij teruggeven.
	 *
	 * @return array
	 */
	function current() {
		return $this->values;
	}

	/**
	 * Regelnummer teruggeven.
	 *
	 * @return int
	 */
	function key() {
		return $this->line;
	}

	/**
	 * Een array omzetten naar een csv regel en wegschrijven naar de filepointer
	 * Tegenovergestelde van de fgetcsv
   *
	 * @param array $values
	 * @return void
	 */
	private function fputcsv($values) {
		$escapedValues = array();
		foreach ($values as $value) {
			$escapedValues[] = $this->quote($value);
		}
		fputs($this->fp, implode($this->delimiter, $escapedValues).$this->eol);
	}

	/**
	 * Een waarde escapen en omsluiten, Een PDO::quote voor CSV bestanden.
	 * @link php.net/manual/en/pdo.quote.php
	 *
	 * @param string $value
	 * @return string
	 */
	private function quote($value) {
		$stuffedValue = str_replace($this->enclosure, $this->enclosure.$this->enclosure, $value); // Een '"' vervangen door '""'
		return $this->enclosure.$stuffedValue.$this->enclosure;
	}
}
?>
