<?php
/**
 *
 */
namespace SledgeHammer;
return  array(
	'flush_tmp.html' => new FlushTemporaryFiles(),
	'generate_library.db.php' => new GenerateStaticLibrary(),
//	'populate_Docroot.html' => new UtilScript('populate_DocumentRoot.php', 'Generate static public/ folder'),
	'minify_Docroot.html' => new UtilScript('minify_DocumentRoot.php', 'Minify public javascript files'),
//	'compare_environments.html' => new CompareEnvironments,
);
?>
