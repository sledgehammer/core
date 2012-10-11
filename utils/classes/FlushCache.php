<?php
/**
 * FlushCache
 * @package Core
 */
namespace Sledgehammer;
/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/ folder.
 */
class FlushCache extends Util {

	function __construct() {
		parent::__construct('Flush cache');
	}

	function generateContent() {
		$script = realpath(dirname(__FILE__).'/../flush_cache.php');
		$output = shell_exec('php '.$script);
		$output .= '<br />';
		$output .= DevUtilsWebsite::suExec('php '.escapeshellarg($script));
		if (function_exists('apc_clear_cache')) {
			apc_clear_cache();
			apc_clear_cache('user');
			apc_clear_cache('opcode');
		}
		return Alert::success('<h4>Flush cache</h4><br />'. $output);
	}
}

?>
