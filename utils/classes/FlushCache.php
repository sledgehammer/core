<?php

/**
 * FlushCache.
 */

namespace Sledgehammer\Core;

/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/ folder.
 */
class FlushCache extends Util
{
    public function __construct()
    {
        parent::__construct('Flush cache');
    }

    public function generateContent()
    {
        $script = realpath(__DIR__.'/../flush_cache.php');
        $output = shell_exec('php '.$script);
        $output .= '<br />';
        $output .= DevUtilsWebsite::suExec('php '.escapeshellarg($script));
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
            apc_clear_cache('user');
            apc_clear_cache('opcode');
        }

        return Alert::success('<h4>Flush cache</h4><br />'.$output);
    }
}
