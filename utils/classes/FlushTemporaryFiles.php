<?php
/**
 * 
 * 
 */
namespace SledgeHammer;

class FlushTemporaryFiles extends Util {

	function __construct() {
		parent::__construct('Flush temporary files');
	}

	function generateContent() {
		$tmpFolder = $this->paths['project'].'tmp/';
		$count = rmdir_contents($tmpFolder, true);
		return new MessageBox('done', 'Flushing tmp/ complete', 'Deleting files in "'.$tmpFolder.'"<br /><br /><b>'.$count.' files removed</b>');

	}
}
?>
