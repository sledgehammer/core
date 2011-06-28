<?php
/**
 * Sessie gegevens 'centraal' opslaan in een database
 * Hierdoor kan een Loadbalancer in 'round robin' mode draaien.
 *
 * @package Core
 */
namespace SledgeHammer;
class DatabaseSessionHandler extends Object{

	/**
	 * @var string $table De tabel waar de sessie gegevens in worden opgeslagen. (Zal standaard de session_name() als tabelnaam gebruiken)
	 */
	private $table;

	/**
	 * @var Database $dbLink
	 */
	private $dbLink = 'default';

	/**
	 *
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

	function init() {
		session_set_save_handler(array($this, 'connect'), array($this, 'close'), array($this, 'open'), array($this, 'write'), array($this, 'delete'), array($this, 'clean')); // sessies afhandelen via dit object
		register_shutdown_function('session_write_close');
	}

	function connect() {
		return true;
	}

	function open($id) {
		$db = getDatabase($this->dbLink);
		try {
			$gegevens = $db->fetch_value('SELECT session_data FROM '.$this->table.' WHERE id = '.$db->quote($id), true);
		} catch (Exception $e) {
			ErrorHandler::handle_exception($e);
			$gegevens = false;
		}
		if ($gegevens == false) {
			if ($db->errno == 1146) { // Table doesnt exist?
				$db->query('CREATE TABLE '.$this->table.' (id varchar(32) NOT NULL, last_used INT(10) UNSIGNED, session_data TEXT, PRIMARY KEY (id));'); // Sessie tabel maken
			}
			return '';
		}
		return $gegevens;
	}

	function write($id, $gegevens) {
		try {
			$db = getDatabase($this->dbLink);
			$result = $db->query('REPLACE INTO '.$this->table.' VALUES ('.$db->quote($id).', "'.time().'", '.$db->quote($gegevens).')');
			if ($result) {
				return true;
			}
		} catch (Exception $e) {
			ErrorHandler::handle_exception($e);
		}
		return false;
	}

	function delete($id) {
		$db = getDatabase($this->dbLink);
		if ($db->query('DELETE FROM '.$this->table.' WHERE id = '.$db->quote($id))) {
			return true;
		}
		return false;
	}

	/**
	 * Garbage collection
	 */
	function clean($maxlifetime) {
		$db = getDatabase($this->dbLink);
		$verouderd = time() - $maxlifetime;
		if ($db->query('DELETE FROM '.$this->table.' WHERE last_used < '.$db->quote($verouderd))) {
			return true;
		}
		return false;
	}

	function close() {
		//$db = getDatabase($this->dbLink);
		//$db->close();
		return true;
	}
}
?>
