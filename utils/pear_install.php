<?php
/**
 * Install a PEAR package into the /application/pear/ folder
 *
 * @package Core
 */
namespace Sledgehammer;
require (dirname(__FILE__).'/../bootstrap.php');
Framework::$errorHandler->html = false;
Framework::$errorHandler->cli = true;
Framework::$autoLoader->importFolder(dirname(__FILE__).'/classes');
if ($argc < 2) {
	echo "  Usage: php ".$argv[0]." [channel] [channel/]package[-version] ...\n ";
	echo "  Examples:\n";
	echo "    php ".$argv[0]." pear.phpunit.de/PHPUnit\n";
	echo "    php ".$argv[0]." PhpDocumentor\n";
	echo "    php ".$argv[0]." pear.doctrine-project.org DoctrineORM CouchDB-alpha DoctrineCommon-2.1.2\n";
	echo "\n";
	exit(1);
}
$targets = array(
	'php' => PATH.'vendor/pear/php',
	'data' => PATH.'vendor/pear/data',
	'script' => PATH.'vendor/pear/script',
	'bin' => PATH.'vendor/pear/bin',
	'doc' => PATH.'vendor/pear/docs',
	'www' => APPLICATION_DIR.'vendor/pear/www'
//	'test' => ? // Skip tests
//	'src' => ?,
//	'ext' => ?,
//	'extsrc' => ?,
);
$pear = new PearInstaller($targets);
$pear->on('channelAdded', function ($sender, $domain, $channel) {
			echo 'Channel "'.$domain.'" loaded. ('.count($channel['packages'])." packages)\n";
		});
$pear->on('installed', function ($sender, $package, $version) {
			echo '  '.$package.' ['.$version."] installed.\n";
		});

unset($argv[0]);
foreach ($argv as $arg) {
	if (preg_match('/^((?P<channel>[^\/]+)\/){0,1}(?P<package>[^\/-]+){1}(\-(?P<version>[0-9\.]+|alpha|beta|stable)){0,1}$/', $arg, $matches)) {//|-stable|-alpha|-beta
		if ($matches['channel'] == '' && empty($matches['version']) && preg_match('/^([a-z0-9]+\.)+[a-z]{2,4}$/i', $arg)) { // "pear.php.net"
			$pear->addChannel($arg);
		} else {
			$options = array();
			if (empty($matches['channel']) == false) {
				$options['channel'] = $matches['channel'];
			}
			if (isset($matches['version'])) {
				$options['version'] = $matches['version'];
			}
			$pear->install($matches['package'], $options);
		}
	} else {
		notice('Unable to determine package-name in "'.$arg.'"'); // A package name containing a "/" or -" ?
	}
}
?>
