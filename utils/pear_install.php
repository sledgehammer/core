<?php
/**
 * Install a PEAR package into the /application/pear/ folder
 *
 * @package Core
 */
namespace SledgeHammer;
require (dirname(__FILE__).'/../../core/init_framework.php');
$ErrorHandler->html = false;
$ErrorHandler->cli = true;
if ($argc < 2) {
	echo "  Usage: php ".$argv[0]." channel/package [channel] [channel/package]\n ";
	echo "  Examples:\n";
	echo "    php ".$argv[0]." pear.phpunit.de/PHPUnit\n";
	echo "    php ".$argv[0]." PhpDocumentor\n";
	echo "    php ".$argv[0]." pear.doctrine-project.org Doctrine\n";
	echo "\n";
	exit(1);
}
$pear = new PearInstaller();
$pear->addListener('channelAdded', function ($sender, $domain, $channel) {
	echo 'Channel "'.$domain.'" loaded. ('.count($channel['packages'])." packages)\n";
});
$pear->addListener('installed', function ($sender, $package, $version) {
	echo '  '.$package.' ['.$version."] installed.\n";
});

unset($argv[0]);
foreach ($argv as $arg) {
	$package = explode('/', $arg);
	if (count($package) == 2) {
		$pear->install($package[1], array('channel' => $package[0]));
	} else {
		if (strpos($package[0], '.')) { // contains a domainname/channel?
			$pear->addChannel($package[0]);
		} else {
			$pear->install($package[0]);
		}
	 }
}
?>
