<?php
/**
 * Verzorgt versiebeheer
 * Maakt gebruik van de subversion shell commando's
 *
 * @package Core
 */

class VersionControl extends Object {

	/**
	 * Vraag het versienummer op
	 *
	 */
	static function get_version($path) {
		if (file_exists($path.'application/settings/versions.ini')) {
			$version_ini_path = $path.'application/settings/versions.ini';
		} elseif (file_exists($path.'settings/versions.ini')) {
			$version_ini_path = $path.'settings/versions.ini';
		} else {
			return 'versions.ini not found';
		}
		$revision = VersionControl::get_revision($path);
		if ($revision == 'Unable to determine revision') {
			return 'Unknown';
		}
		$local_modification = strpos($revision, 'M');
		$revision = (int) $revision;

		$ini_file = parse_ini_file($version_ini_path, true);
		if (!isset($ini_file[$revision])) {
			for ($i = $revision; $i > 0; $i--) {
				if (isset($ini_file[$i])) {
					return 'Development r'.$revision.'. Previous version: '.$ini_file[$i]['version'].' r'.$i;
				}
			}
			if ($i == 0) {
				return 'Invalid versions.ini';
			}
			return '[Development/Unstable] revision '.$revision;
		}
		$version_info = $ini_file[$revision];
		$version = $version_info['version'];
	 	$version .= $local_modification ? ' +Local modifications' : '';
		return $version;
	}

	/**
	 * Vraag het revisie nummer op van de laatste wijziging
	 */
	static function get_revision($path) {
		$exec_path = VersionControl::get_exec_path();
		$revisions = shell_exec($exec_path.'svnversion -c -n "'.$path.'"'); // Vraag de ge-committe revisie nummers op
		if ($revisions == '' || $revisions == 'exported') {
			return 'Unable to determine revision';
		}
		$revision = substr($revisions, strpos($revisions, ':') + 1);
		return $revision;
	}

	/**
	 *
	 */
	static private function get_exec_path() {
		return '';
		/*
		if (file_exists('/opt/local/bin/svnversion')) { // Hack zodat de MacPort versie van svn gebruikt wordt
			return '/opt/local/bin/';
		}
		if (file_exists('c:/wamp/bin/svn/bin')) { // Hack zodat in windows versie van svn gebruikt wordt
			return 'c:/wamp/bin/svn/bin/';
		}*/
	}
}
?>
