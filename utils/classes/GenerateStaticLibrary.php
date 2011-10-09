<?php
/**
 * Run the generate_static_library.php script from DevUtils.
 *
 * @package Core
 */
namespace SledgeHammer;
class GenerateStaticLibrary extends Util {

	function __construct() {
		parent::__construct('Optimize AutoLoader');
	}

	function generateContent() {
		$warningMessage = 'This will generate a <b>AutoLoader.db.php</b> file which contains the file-location for al detected classes and interfaces.<br />';
		$warningMessage .= 'Changes in the classes folders will no longer be detected by the AutoLoader!<br />';
		$warningMessage .= 'You\'ll need to rerun this script after those changes.';
		$dialog = new DialogBox('warning', 'Optimize AutoLoader', $warningMessage, array('continue' => array('icon' => WEBROOT.'icons/accept.png', 'label' => 'Continue')));
		$answer = $dialog->import($error);
		if ($answer == 'continue') {
			if (!$this->isWritable()) {
				return new MessageBox('error', 'Generating failed', 'Make sure the webuser is allowed to write "'.$this->paths['project'].'AutoLoader.db.php"');
			}
			$util = new UtilScript('generate_static_library.php', 'Generate AutoLoader database');
			return $util->generateContent();
		}
		return $dialog;
	}

	private function isWritable() {
		$dbFile = $this->paths['project'].'AutoLoader.db.php';
		if (file_exists($dbFile)) {
			return is_writable($dbFile);
		}
		return is_writable(dirname($dbFile));
	}
}
?>
