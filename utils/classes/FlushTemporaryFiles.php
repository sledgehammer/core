<?php
/**
 * 
 * 
 */
class FlushTemporaryFiles extends Util {

	function __construct() {
		parent::__construct('Flush temporary files');
	}

	function execute() {
		$tmpFolder = $this->paths['project'].'tmp/';
		$count = rmdir_contents($tmpFolder, array('.svn'));
		return new MessageBox('ok.gif', 'Flushing tmp/ complete', 'Deleting files in "'.$tmpFolder.'"<br /><br /><b>'.$count.' files removed</b>');

	}
}
?>
