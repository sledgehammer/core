<?php
/**
 * Logger
 */
namespace Sledgehammer;
/**
 * Logging and reporting.
 *
 * Features:
 * - Automaticly adds a statusbar entry.
 * - Automatic truncating of very large messages.
 * - Limits for the detailed log
 * - Customizable columns and renderer.
 * - Ability to add a backtrace per log message.
 */
class Logger extends Object {

	/**
	 * Total number of "actions" (may exceed the $limit)
	 * @var int
	 */
	public $count = 0;

	/**
	 * Total elapsed time it took to execute all "actions" (in seconds)
	 * Collects all 'duration' values in the $meta array.
	 * @var float
	 */
	public $totalDuration = 0.0;

	/**
	 * Maximum number of "actions" that will be logged. (-1: Unlimited)
	 * @var int
	 */
	public $limit = -1;

	/**
	 * Start the numbering at .. (is 0 for databases to compensate for the connect "query")
	 * @var int
	 */
	public $start = 1;

	/**
	 * Columnnames for the popup log table.
	 * @var array
	 */
	public $columns;

	/**
	 * Plural description of the entry type. Example: "requests", "queries", etc
	 * @var string
	 */
	public $plural = 'entries';

	/**
	 * Singular  description of the entry type: Example: "request", "query", etc
	 * @var string
	 */
	public $singular = 'entry';

	/**
	 * Callback for rendering the log entry <td>'s
	 * @var Closure|array|string
	 */
	public $renderer = 'Sledgehammer\Logger::renderEntry';

	/**
	 * Add N filename and linenumber traces to the log.
	 * @var int
	 */
	public $backtrace = 0;

	/**
	 * Only log the first 50KiB of a long log entry.
	 * @var int
	 */
	public $characterLimit = 51200;

	/**
	 * @var array
	 */
	public $entries = array();

	/**
	 * The logger instances that are accessible by getLogger() and the statusbar() functions.
	 * @var array
	 */
	static $instances = array();

	/**
	 * Constructor
	 * @param array $options
	 */
	function __construct($options = array()) {
		$identifier =(isset($options['identifier'])) ? $options['identifier'] : 'Log';
		if (isset(self::$instances[$identifier])) {
			$suffix = 2;
			while (isset(self::$instances[$identifier.'('.$suffix.')'])) {
				// also exists, check again.
				$suffix++;
			}
			$identifier = $identifier.'('.$suffix.')';
		}
		self::$instances[$identifier] = $this;
		unset($options['identifier']);
		foreach ($options as $property => $value) {
			$this->$property = $value;
		}
		if (isset($options['plural']) && empty($options['singular'])) {
			if (class_exists('Sledgehammer\Inflector')) {
				$this->singular = Inflector::singularize($this->plural);
			} else {
				$this->singular = $this->plural;
			}
		}
	}

	/**
	 * Add an entry to the log.
	 *
	 * @param string $entry
	 * @param array $meta array(
	 *   'duration' => (optional) the elapsed time for this "action"
	 * )
	 * @return void
	 */
	function append($entry, $meta = array()) {
		$this->count++;
		if (isset($meta['duration'])) {
			$this->totalDuration += $meta['duration'];
		}
		if ($this->limit !== -1 && $this->count > $this->limit) {
			return; // Limit reached
		}
		$length = strlen($entry);
		if ($length > $this->characterLimit) {
			$entry = substr($entry, 0, $this->characterLimit);
			$meta['truncated'] = ($length - $this->characterLimit);
		}
		if ($this->backtrace !== 0) {
			$meta['backtrace'] = $this->collectBacktrace();
		}
		$this->entries[] = array($entry, $meta);
	}

	/**
	 * Render the log in a 1 line summary and the table in a popup.
	 *
	 * @param string $name
	 * @return void
	 */
	function statusbar($name) {
		if ($this->count === 0) {
			echo $name;
			return;
		}
		$popup = count($this->entries) > 0;
		if ($popup) {
			$id = 'logger_C'.$this->count.'_R'.uniqid(); // Generate unique ID
			echo '<div id="'.$id.'" class="statusbar-log" tabindex="-1" style="display:none;">';
			echo '<div class="statusbar-log-overlay" onclick="javascript:document.getElementById(\''.$id.'\').style.display=\'none\';"></div>';
			$this->render();
			echo '</div>';
			echo '<span class="statusbar-tab"><a href="#" onclick="document.getElementById(\''.$id.'\').style.display=\'block\';document.body.addEventListener(\'keyup\', function (e) { if(e.which == 27) {document.getElementById(\''.$id.'\').style.display=\'none\';}}, true); document.getElementById(\''.$id.'\').focus(); return false">';
		}
		echo $name, '&nbsp;<b>', $this->count, '</b>&nbsp;';
		if ($this->count === 1) {
			echo $this->singular;
		} else {
			echo $this->plural;
		}
		if ($this->totalDuration !== 0) {
			echo '&nbsp;in&nbsp;<b>', format_parsetime($this->totalDuration), '</b>&nbsp;sec';
		}
		if ($popup) {
			echo '</a></span>';
		}
	}

	/**
	 * Renders all logged entries in a <table>
	 * @return void
	 */
	function render() {
		echo '<table class="log-container">';
		echo '<thead class="log-header"><tr><th class="log-header-column logentry-number">Nr.</th>';
		if ($this->columns === null) {
			echo '<th class="log-header-column">', ucfirst($this->singular), '</th>';
		} else {
			foreach ($this->columns as $column) {
				echo '<th class="log-header-column">', $column, '</th>';
			}
		}
		if ($this->backtrace !== 0) {
			echo '<th class="log-header-column">Backtrace</th>';
		}
		echo '</tr></thead>';
		echo '<tbody class="log-entries">';
		$nr = $this->start;
		foreach ($this->entries as $entry) {
			echo '<tr class="logentry">';
			echo '<td class="logentry-number">', $nr, '</td>';
			$nr++;
			call_user_func($this->renderer, $entry[0], $entry[1]);
			if ($this->backtrace !== 0) {
				if (isset($entry[1]['backtrace'])) {
					$backtrace = $entry[1]['backtrace'];
					$call = array_shift($backtrace);
					$trace = ' in '.$call['file'].' on line <b">'.$call['line'].'</b>';
					$tooltip = '';
					foreach ($backtrace as $call) {
						$tooltip .= ' '.$call['file'].' on line '.$call['line']."\n";
					}
					echo ' <td class="log-backtrace" title="'.HTML::escape($tooltip).'">'.$trace.'</td>';
				} else {
					echo '<td></td>';
				}
			}
			echo "</tr>\n";
		}
		echo '</tbody>';
		echo '</table>';
		if ($this->count > count($this->entries)) {
			echo '<br /><spann class="logentry-alert">The other '.($this->count - count($this->entries)).' '.$this->plural.' are suppressed.</span>';
		}
	}

	/**
	 * Default renderer for a log entry (override with the $logger->renderer property).
	 * @param string $entry
	 */
	static function renderEntry($entry) {
		echo '<td>';
		if (is_array($entry)) {
			echo 'Array';
		} else {
			echo htmlspecialchars($entry, ENT_NOQUOTES, 'ISO-8859-15');
		}
		echo '</td>';
	}

	/**
	 * Trace the location where the log was called from.
	 *
	 * @return null|array
	 */
	private function collectBacktrace() {
		$depth = (int) $this->backtrace;
		if ($depth === 0) {
			return null;
		}
		$backtrace = debug_backtrace();
		$index = 0;
		foreach ($backtrace as $index => $call) {
			if ($call['file'] != __FILE__ && isset($call['function'])) { // Skip calls inside the Logger class.
				break;
			}
		}
		if (isset($call['function'])) {
			$index++; // Skip the method that calls the Logger->append()
		}
		$backtrace = array_slice($backtrace, $index);
		$trace = array();
		foreach ($backtrace as $call) {
			$depth--;
			if (isset($call['file']) && isset($call['line'])) {
				$trace[] = array(
					'file' => str_replace(PATH, '', $call['file']),
					'line' => $call['line']
				);
			}
		}
		return $trace;
	}

	function __wakeup() {
		foreach (self::$instances as $logger) {
			if ($logger === $this) {
				return; // Already connected
			}
		}
		// Reconnect
		$identifier = 'Log';
		if (isset(self::$instances[$identifier])) {
			$suffix = 2;
			while (isset(self::$instances[$identifier.'('.$suffix.')'])) {
				// also exists, check again.
				$suffix++;
			}
			$identifier = $identifier.'('.$suffix.')';
		}
		self::$instances[$identifier] = $this;
	}

}

?>
