<?php
/**
 * 
 * 
 */
class GenerateStaticLibrary extends Util {

	function __construct() {
		parent::__construct('Optimize AutoLoader');
		if (!$this->isWritable()) {
			$this->icon = 'denied.png';
		}
	}

	function generateContent() {
		$warningMessage = 'This will generate a <b>autoloader.db.php</b> file which contains the file-location for al detected classes and interfaces.<br />';
		$warningMessage .= 'Changes in the classes folders will no longer be detected by the AutoLoader!<br />';
		$warningMessage .= 'You\'ll need to rerun this script after those changes.';
		$dialog = new DialogBox('warning', 'Optimize AutoLoader', $warningMessage, array('continue' => array('icon' => WEBROOT.'icons/accept.png', 'label' => 'Continue')));
		$answer = $dialog->import($error);
		if ($answer == 'continue') {
			if (!$this->isWritable()) {
				return new MessageBox('error', 'Generating failed', 'Make sure the webuser is allowed to write "'.$this->paths['project'].'library.db.php"');
			}
			$util = new UtilScript('generate_static_library.php', 'Generate Library db');
			return $util->execute();
		}
		return $dialog;
	}

	private function isWritable() {
		$dbFile = $this->paths['project'].'library.db.php';
		if (file_exists($dbFile)) {
			return is_writable($dbFile);
		}
		return is_writable(dirname($dbFile));
	}
}
?>
