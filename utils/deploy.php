<?php
/**
 * Generic deploy script for deploying sledgehammer websites to different environments
 */
namespace Sledgehammer;
if ($argc < 2) {
	echo "Usage: php core/utils/".basename(__FILE__)." [environment]\n\n  environment: \"development\", \"production\", etc\n";
	exit(1);
}
define('ENVIRONMENT', 'development');
require_once(dirname(dirname(__FILE__)).'/bootstrap.php');

if (strlen(PATH) <= 4) { // De PATH constante controleren. strlen("PATH") == 4
	error("Invalid PATH");
}
/**
 * Run the cli command
 * Adds additional error reporting for non-zero exit values
 */
function shell_execute($command, $hideOutput = false) {
	echo '  '.$command."\n";
	if ($hideOutput) {
		exec($command, $output, $exitval);
	} else {
		passthru($command, $exitval);
	}
	if ($exitval !== 0) {
		error("Command \"".$command."\" failed\n");
	}
}

// @todo chmod 777 is niet de veiligste optie. Detectie apache user inbouwen. obv "ps httpd"?

// Rechten instellen van de tmp map
echo "Writeable tmp/ folder...\n";
mkdirs(PATH.'tmp/');
shell_execute('chmod a+wrX '.escapeshellarg(PATH.'tmp/'));
passthru('chmod -R a+wrX '.escapeshellarg(PATH.'tmp/')); // bestanden binnen de tmp kunnen al van de webuser zijn. Ookal gaat dit fout, moet dit script verdergaan.
echo "  done.\n\n";

$environment = $argv[1];
if ($environment == 'development') {
	// De overige opties zijn niet voor development geschikt
	return;
}

// Wis de cli parameters zodat deze niet gebruikt worden voor de onderstaande scripts
$argc = 1;
unset($argv[1]);

// Optimize the AutoLoader
require (dirname(__FILE__).'/generate_static_autoloader.php');

// Bestanden & mappen verwijderen die niet in productie nodig zijn.
//require (dirname(__FILE__).'/cleanup_svn_export.php');

// Populate de public map
$populateSuccess = require (dirname(__FILE__).'/populate_DocumentRoot.php');

// Herstel cli parameters
//$argv[1] = $environment;
//$argc = 2;
/*
if ($argc < 2) {
	echo "Usage: php deploy.php [environment]\n\nAvailable environments:\n  ".implode("\n  ", $accepted_environments)."\n";
	exit(0);
}

// username en group van de achterhalen
echo "User information...\n";
if ($passwd_file = file('/etc/passwd')) {
	$users = array();
	foreach ($passwd_file as $line) {
		$users[] = preg_replace('/(:){1}.*$/','', $line);
	}
	$owner = false;
	foreach ($accepted_owners as $username) {
		if (in_array($username, $users)) {
			$owner = $username;
			break;
		}
	}
	if (!$owner) {
		echo "No acceptable owner found in /etc/passwd, expecting '".implode("' or '", $accepted_owners)."'\n";
		exit(1);
	}
} else {
		echo "Failed opening /etc/passwd\n";
		exit(1);
}

if ($group_file = file('/etc/group')) {
	$groups = array();
	foreach ($group_file as $line) {
		$groups[] = preg_replace('/(:){1}.*$/','', $line);
	}
	$group = false;
	foreach ($accepted_groups as $group_name) {
		if (in_array($group_name, $groups)) {
			$group = $group_name;
			break;
		}
	}
	if (!$group) {
		echo "No acceptable group found in /etc/group, expecting '".implode("' or '", $accepted_groups)."'\n";
		exit(1);
	}
} else {
		echo "Failed opening /etc/group\n";
		exit(1);
}
echo '  User  : '.$owner."\n";
echo '  Group : '.$group."\n";

// Rechten instellen van alle bestanden
echo "Modifing file-rights...\n";

shell_execute('chown -R '.$owner.':'.$group.' "'.PATH.'"'); // De eigenaar van alle bestanden op dit van de webgebruiker zetten
shell_execute('chmod -R u=rwX,g=rX,o-rwx "'.PATH.'"');  // User mag alles, group mag niet schrijven, other mag helemaal niks
shell_execute('chmod -R g+w "'.PATH.'tmp/"');  // De webgebruiker mag via zijn group wel "schrijven" in de tmp map
*/
?>
