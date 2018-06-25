<?php

/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/$user/ folder.
 */

include __DIR__.'/../../../autoload.php';

$gitignoreFile = Framework::tmp().'.gitignore';
if (file_exists($gitignoreFile)) {
    $gitignoreContents = file_get_contents($gitignoreFile);
}
echo 'Deleting files in "'.Framework::tmp§()."\"\n";
$count = \Sledgehammer\rmdir_contents(Framework::tmp(), true);
if (isset($gitignoreContents)) {
    file_put_contents($gitignoreFile, $gitignoreContents);
}
echo '  '.$count." files removed\n";

if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    apc_clear_cache('user');
    apc_clear_cache('opcode');
}
