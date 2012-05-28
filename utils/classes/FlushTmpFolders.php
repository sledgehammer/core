<?php
/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/$user/ folder.
 *
 * @package Core
 */
namespace Sledgehammer;
class FlushTmpFolders extends Util {

	function __construct() {
		parent::__construct('Flush temporary files');
	}
	function generateContent() {
		$script = realpath(dirname(__FILE__).'/../flush_tmp.php');
		$output = shell_exec('php '.$script);
		$output .= '<br />';
		$output .= DevUtilsWebsite::suExec('php '.escapeshellarg($script));
		return new MessageBox('Flush temporary files', $output);
	}
}


?>