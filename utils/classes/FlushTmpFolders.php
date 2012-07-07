<?php
/**
 * FlushTmpFolders
 * @package Core
 */
namespace Sledgehammer;
/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/ folder.
 */
class FlushTmpFolders extends Util {

	function __construct() {
		parent::__construct('Flush temporary files');
	}

	function generateContent() {
		$script = realpath(dirname(__FILE__).'/../flush_tmp.php');
		$output = shell_exec('php '.$script);
		$output .= '<br />';
		$output .= DevUtilsWebsite::suExec('php '.escapeshellarg($script));
		return Alert::success('<h4>Flush temporary files</h4><br />'. $output);
	}
}

?>
