<?php
/**
 *
 */
namespace Sledgehammer;
return  array(
	'flush_cache.html' => new FlushCache(),
	'generate_AutoLoader.db.php' => new GenerateStaticAutoLoader(),
	'populate_Docroot.html' => new UtilScript('populate_DocumentRoot.php', 'Generate static public/ folder'),
);
?>
