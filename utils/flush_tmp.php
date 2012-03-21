<?php
/**
 * Delete the contents of the $project/tmp/ or /tmp/sledgehammer-$hash/$user/ folder.
 *
 * @package Core
 */
namespace SledgeHammer;

include(dirname(__FILE__).'/../init.php');

$gitignoreFile = TMP_DIR.'.gitignore';
if (file_exists($gitignoreFile)) {
	$gitignoreContents = file_get_contents($gitignoreFile);
}
echo 'Deleting files in "'.TMP_DIR."\"\n";
$count = rmdir_contents(TMP_DIR, true);
if (isset($gitignoreContents)) {
	file_put_contents($gitignoreFile, $gitignoreContents);
}
echo '  '.$count." files removed\n";

?>