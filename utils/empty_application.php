<?php
/**
 * Genereer de mappenstructuur voor een standaard sledgehammer application
 *
 */
namespace Sledgehammer;
include(dirname(__FILE__).'/../init.php');

function file_not_exist_put_contents($filename, $data) {
	if (file_exists($filename)) {
		return false;
	}
	return file_put_contents($filename, $data);
}
mkdirs(PATH.'application/classes');
mkdirs(PATH.'application/public');
mkdirs(PATH.'application/settings');
mkdirs(PATH.'tmp');
chmod(PATH.'tmp', 0777);
file_not_exist_put_contents(PATH.'tmp/.gitignore', "*\n!.gitignore");
mkdirs(PATH.'public');

$htaccess ="Allow from all
# Alles, behalve bestaande bestanden naar de rewrite.php doorsluisen
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^.*$            rewrite.php?%{QUERY_STRING}";
file_not_exist_put_contents(PATH.'public/.htaccess', $htaccess);

$rewrite = "<?php
/**
 *
 */
include(dirname(__FILE__).'/../sledgehammer/core/render_public_folders.php');
require(dirname(__FILE__).'/../sledgehammer/core/bootstrap.php'); // Het framework initializeren

\$website = new MyWebsite();
\$website->handleRequest();
?>";

file_not_exist_put_contents(PATH.'public/rewrite.php', $rewrite);


?>
