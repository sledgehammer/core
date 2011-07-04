<?php
/**
 * Delete the contents of the $project/tmp/ folder.
 * 
 * @package Core
 */
namespace SledgeHammer;
class FlushTemporaryFiles extends Util {

	function __construct() {
		parent::__construct('Flush temporary files');
	}

	function generateContent() {
		$gitignoreFile = $this->paths['project'].'tmp/.gitignore';
		if (file_exists($gitignoreFile)) {
			$gitignoreContents = file_get_contents($gitignoreFile);
		}
		$tmpFolder = $this->paths['project'].'tmp/';
		$count = rmdir_contents($tmpFolder, true);
		if (isset($gitignore)) {
			file_put_contents($gitignoreFile, $gitignoreContents);
		}
		return new MessageBox('done', 'Flushing tmp/ complete', 'Deleting files in "'.$tmpFolder.'"<br /><br /><b>'.$count.' files removed</b>');

	}
}
?>
