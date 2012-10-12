<?php
/**
 * empty_application.php
 */
namespace Sledgehammer;
/**
 * Generate a application skeleton, to quickstart an application
 */
include(dirname(__FILE__).'/../bootstrap.php');

/**
 * A variation of file_put_contents that won't override existing files.
 *
 * @param string $filename
 * @param string $data
 * @return boolean
 */
function file_not_exist_put_contents($filename, $data) {
	if (file_exists($filename)) {
		return false;
	}
	return file_put_contents($filename, $data);
}
// Create a writable tmp folder.
mkdirs(PATH.'tmp');
chmod(PATH.'tmp', 0777);
file_not_exist_put_contents(PATH.'tmp/.gitignore', "*\n!.gitignore");

// @todo detect mvc

// Create the public folder.
mkdirs(PATH.'public');
file_not_exist_put_contents(PATH.'public/.htaccess', <<<END
Allow from all
# Redirect everything to rewrite.php except existing files.
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^.*$            rewrite.php?%{QUERY_STRING}
END
);

file_not_exist_put_contents(PATH.'public/rewrite.php', <<<END
<?php
/**
 * rewrite.php
 */
define('Sledgehammer\STARTED', microtime(true));
include(dirname(__FILE__).'/../vendor/sledgehammer/core/render_public_folders.php');
require(dirname(__FILE__).'/../vendor/autoload.php');

\$app = new Sledgehammer\App();
\$app->handleRequest();
?>
END
);

// Create default app
mkdirs(PATH.'app/classes');
file_not_exist_put_contents(PATH.'app/classes/App.php', <<<END
<?php
/**
 * Example App
 */
namespace Sledgehammer;
class App extends Website {

	/**
	 * Public methods are accessable as file and must return a View object.
	 * "/index.html"
	 * @return View
	 */
	function index() {
		return new Nav(array(
			'Welcome',
			WEBROOT.'example/item1.html' => 'Item 1',
			WEBROOT.'service.json' => 'Item 2',
		), array(
			'class' => 'nav nav-list'
		));
	}

	/**
	 * Public methods with the "_folder" suffix are accesable as folder.
	 * "/example/*"
	 * @param string \$file
	 * @return View
	 */
	function example_folder(\$file) {
		return new Alert('This is page: '.\$file);
	}

	function service() {
		return new Json(array('success' => true));
	}

	protected function wrapContent(\$view) {
		\$headers = array(
			'title' => 'Sledgehammer App',
			'css' => WEBROOT.'mvc/css/bootstrap.css',
		);
		return new Template('layout.php', array('content' => \$view), \$headers);
	}
}

?>
END
);

mkdirs(PATH.'app/templates');
file_not_exist_put_contents(PATH.'app/templates/layout.php', <<<END
<?php
/**
 * Example Template
 */
?>
<div class="navbar">
	<div class="navbar-inner">
		<a class="brand" href="<?php echo Sledgehammer\WEBROOT; ?>index.html">My App</a>
	</div>
</div>
<div class="container">
<?php render(\$content); ?>
</div>
END
);

file_not_exist_put_contents(PATH.'app/database.ini', <<<END
[development]
default = mysql://root:root@localhost/my_database
END
);
mkdirs(PATH.'app/public/css');
mkdirs(PATH.'app/public/js');
mkdirs(PATH.'app/public/img');

?>
