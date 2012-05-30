<?php
/**
 * DatabaseSessionHandler
 */
namespace Sledgehammer;
/**
 * Sessie gegevens 'centraal' opslaan in een database
 * Hierdoor kan een Loadbalancer in 'round robin' mode draaien.
 * @package Core
 */
class DatabaseSessionHandler extends Object {

	/**
	 * De tabel waar de sessie gegevens in worden opgeslagen. (Zal standaard de session_name() als tabelnaam gebruiken)
	 * @var string $table
	 */
	private $table;

	/**
	 * The database link where the session is stored.
	 * @var string $dbLink
	 */
	private $dbLink = 'default';

	/**
	 * Contructor
	 * @param array $options  Hiermee kun je de "table" en "dbLink" overschrijven
	 */
	function __construct($options = array()) {
		$availableOptions = array('table', 'dbLink');
		foreach ($options as $property => $value) {
			if (in_array($property, $availableOptions)) {
				$this->$property = $value;
			} else {
				notice('Invalid option "'.$property.'"', 'Use: '.human_implode(' or ', $availableOptions));
			}
		}
		if ($this->table === NULL) {
			$this->table = session_name();
		}
	}

	/**
	 * Set the session handler to this instance.
	 */
	function init() {
		session_set_save_handler(array($this, 'noop'), array($this, 'noop'), array($this, 'read'), array($this, 'write'), array($this, 'destroy'), array($this, 'cleanup'));
		register_shutdown_function('session_write_close');
	}

	/**
	 * Callback for $open & $close.
	 * @return boolean
	 */
	function noop() {
		return true;
	}

	/**
	 * Load session-data based for the given $id.
	 * @param string $id session_id
	 * @return string|boolean
	 */
	function read($id) {
		$db = getDatabase($this->dbLink);
		try {
			$gegevens = $db->fetch_value('SELECT session_data FROM '.$db->quoteIdentifier($this->table).' WHERE id = '.$db->quote($id), true);
		} catch (\Exception $e) {
			report_exception($e);
			$gegevens = false;
		}
		if ($gegevens == false) {
			if ($db->errno == 1146) { // Table doesnt exist?
				$db->query('CREATE TABLE '.$db->quoteIdentifier($this->table).' (id varchar(32) NOT NULL, last_used INT(10) UNSIGNED, session_data TEXT, PRIMARY KEY (id));'); // Sessie tabel maken
			}
			return '';
		}
		return $gegevens;
	}

	/**
	 * Write session-data for the given $id
	 *
	 * @param string $id
	 * @param string $gegevens
	 * @return boolean
	 */
	function write($id, $gegevens) {
		try {
			$db = getDatabase($this->dbLink);
			$result = $db->query('REPLACE INTO '.$db->quoteIdentifier($this->table).' VALUES ('.$db->quote($id).', "'.time().'", '.$db->quote($gegevens).')');
			if ($result) {
				return true;
			}
		} catch (\Exception $e) {
			report_exception($e);
		}
		return false;
	}

	/**
	 * Destroy the session-data for the given $id
	 *
	 * @param string $id
	 * @return boolean
	 */
	function destroy($id) {
		$db = getDatabase($this->dbLink);
		if ($db->query('DELETE FROM '.$db->quoteIdentifier($this->table).' WHERE id = '.$db->quote($id))) {
			return true;
		}
		return false;
	}

	/**
	 * Garbage collection
	 * @param int $maxlifetime
	 */
	function cleanup($maxlifetime) {
		$db = getDatabase($this->dbLink);
		$verouderd = time() - $maxlifetime;
		if ($db->query('DELETE FROM '.$db->quoteIdentifier($this->table).' WHERE last_used < '.$db->quote($verouderd))) {
			return true;
		}
		return false;
	}

}

?>
