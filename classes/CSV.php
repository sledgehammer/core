<?php
/**
 * CSV
 */
namespace Sledgehammer;
/**
 * Een generieke snelle manier om een csv bestand in te lezen.
 * Kan ook csv bestanden genereren met CSV::write()
 *
 * De eerste regel van het csv bestand kan gebruikt worden om de kolomnamen te defineren.
 *
 * @package Core
 */
class CSV extends Object implements \Iterator {

	/**
	 * Path van het csv bestand
	 * @var string
	 */
	private $filename;

	/**
	 * Bepaald welke kolommen er ingelezen worden, en hoe deze genoemt worden.
	 * @var array
	 */
	private $columns;

	/**
	 * Het karakter dat gebruik wordt om de velden te scheiden.
	 * @var string|char
	 */
	private $delimiter;

	/**
	 * Het karakter dat gebruikt wordt om tekst-velden te omsluiten (default: ")
	 * @var string|char
	 */
	private $enclosure;

	/**
	 * File handle for fgetcsv()
	 * @var resource
	 */
	private $fp;

	/**
	 * Veranderd een $row van een indexed array naar assoc array.
	 * @var array
	 */
	private $keys;

	/**
	 * Current row.
	 * @var array
	 */
	private $values;

	/**
	 * Current key/index.
	 * @var int|null
	 */
	private $index;

	/**
	 * De linebreak die gebruikt wordt bij een write()
	 * @var string
	 */
	private $eol = "\r\n";

	/**
	 * Constructor
	 *
	 * @param string $filename  Het path waar het csv bestand zich bevind
	 * @param array $columns  Hiermee geef je aan welke kolommen ingelezen moeten worden. $value = kolomnaam, $key = array_key
	 * @param char|null $delimiter  Delimiter, usually "," or ";". null: Auto-detect the  delimiter.
	 * @param char $enclosure  Karakter dat gebruikt word om tekst waarbinnen het scheidingsteken kan voorkomen te omsluiten
	 */
	function __construct($filename, $columns = null, $delimiter = null, $enclosure = '"') {
		$this->filename = $filename;
		$this->columns = $columns;
		$this->delimiter = $delimiter;
		$this->enclosure = $enclosure;
	}

	/**
	 * Autoclose the file handle
	 */
	function __destruct() {
		if ($this->fp) {
			fclose($this->fp); // Als het object niet meer nodig is, kan de file-pointer gesloten worden.
		}
	}

	/**
	 * Schrijf de waarden in de $iterator weg naar $filename die in de constuctor is meegegeven.
	 * D.m.v "php://output" kan het csv bestand direct naar de browser verstuurd worden.
	 *
	 * @param string $filename
	 * @param iterator $iterator Brongegevens voor de csv. Elementen worden bepaald d.m.v. de $keys in de $this->columns array.
	 * @param array|null $columns
	 * @param string|char $delimiter
	 * @param string|char $enclosure
	 * @return void
	 */
	static function write($filename, $iterator, $columns = null, $delimiter = ';', $enclosure = '"') {
		$fp = fopen($filename, 'w');
		if (!$fp) {
			throw new \Exception('Failed to open "'.$filename.'" for writing');
		}
		// De kolomnamen op de eerste csv regel zetten
		if ($columns === null) {
			// Detecteer colomnamen (gebruik de array keys)
			if (is_array($iterator)) {
				reset($iterator);
				$row = current($iterator);
			} else {
				$iterator->rewind();
				$row = $iterator->current();
			}
			$columnKeys = array_keys($row);
			$columns = $columnKeys;
		} elseif ($columns !== false) {
			$columnKeys = array_keys($columns);
		}
		fputcsv($fp, $columns, $delimiter, $enclosure);

		foreach ($iterator as $row) {
			$values = array();
			foreach ($columnKeys as $key) {
				$values[] = $row[$key];
			}
			fputcsv($fp, $values, $delimiter, $enclosure);
		}
		fclose($fp);
	}

	/**
	 * Het csv bestand (opnieuw) openen en de eerste rij inlezen als kolomnamen.
	 */
	function rewind() {
		$this->index = null;
		if ($this->fp) {
			fclose($this->fp);
		}
		$this->fp = fopen($this->filename, 'r');
		if (!$this->fp) {
			throw new \Exception('Couldn\'t open file "'.$this->filename.'"');
		}
		if ($this->delimiter === null) {
			$line = fgets($this->fp);
			$this->delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
			fseek($this->fp, 0); // rewind
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
					throw new \Exception('Column: "'.$column.'" not found. Available columns: "'.implode('", "', $keys).'"');
					return false;
				}
			}
		}
		$this->index = -1;
		$this->next();
	}

	/**
	 * De rij inlezen en naar de volgende regel gaan.
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void
	 */
	function next() {
		$this->values = array();
		$row = fgetcsv($this->fp, 0, $this->delimiter, $this->enclosure);
		if ($row) { // Is het einde (eof) nog niet bereikt?
			$this->index++;
			foreach ($this->keys as $index => $key) {
				if (isset($row[$index])) { // Is er voor deze kolom een waarde?
					$this->values[$key] = $row[$index];
				} else {
					$filename = (strpos($this->filename, PATH) === 0) ? substr($this->filename, strlen(PATH)) : $this->filename; // Waar mogelijk het PATH er van af halen
					notice('Row too short, missing column '.$index.'('.$this->columns[$key].') in '.$filename.' on line '.$this->index + 2, $row); // @todo Calculate line offset compared to the index ()
				}
			}
		} else {
			$this->index = null;
		}
	}

	/**
	 * Geeft aan of er nog records in het bestand zitten
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return bool
	 */
	function valid() {
		return !feof($this->fp); // Is het einde van het bestand NIET bereikt?
	}

	/**
	 * Huidige rij teruggeven.
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return array
	 */
	function current() {
		return $this->values;
	}

	/**
	 * Returns current linenumber. (starts at 1)
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return int
	 */
	function key() {
		return $this->index;
	}

}

?>
