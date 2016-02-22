<?php

/**
 * Bootstrap the Sledgehammer Framework.
 */
use Sledgehammer\Core\Debug\DebugR;

/*
 * 1. Set DebugR statusbar header.
 */
if (headers_sent() === false) {
    DebugR::send('sledgehammer-statusbar', 'no statusbar data.', true);
}
/*
 * 2. Define the Sledgehammer\* Constants
 */
if (!defined('Sledgehammer\STARTED')) {
    // Timestamp (in microseconds) for when the script started.
    define('Sledgehammer\STARTED', microtime(true));
}
// Detect the Environment
if (defined('Sledgehammer\ENVIRONMENT') === false) {
    if (getenv('APP_ENV')) { // Laravel
        $__ENV = getenv('APP_ENV');
    } elseif (getenv('APPLICATION_ENV')) { // Zend
        $__ENV = getenv('APPLICATION_ENV');
    } else {
        $__ENV = 'production';
    }
    define('Sledgehammer\ENVIRONMENT', $__ENV);
    unset($__ENV);
}
// Configure the "?debug=1" to use another $_GET variable.
if (!defined('Sledgehammer\DEBUG_VAR')) {
    define('Sledgehammer\DEBUG_VAR', 'debug');
}
// Directory of the vendor.
if (!defined('Sledgehammer\VENDOR_DIR')) {
    if (file_exists(dirname(__DIR__).'/vendor')) { // local composer install
        define('Sledgehammer\VENDOR_DIR', dirname(__DIR__).'/vendor'.DIRECTORY_SEPARATOR);
    } else {
        define('Sledgehammer\VENDOR_DIR', dirname(dirname(dirname(__DIR__))).'/vendor'.DIRECTORY_SEPARATOR);
    }
}
// Directory of the project.
define('Sledgehammer\PATH',  dirname(\Sledgehammer\VENDOR_DIR).DIRECTORY_SEPARATOR);

// Detect & create a writable tmp folder
if (defined('Sledgehammer\TMP_DIR') === false) {
    $__TMP_DIR = \Sledgehammer\PATH.'tmp'.DIRECTORY_SEPARATOR;
    if (is_dir($__TMP_DIR) && is_writable($__TMP_DIR)) { // The project has a "tmp" folder?
        $__TMP_DIR .= 'sledgehammer';
    } else {
        $__TMP_DIR = \Sledgehammer\PATH.'storage'.DIRECTORY_SEPARATOR;
        if (is_dir($__TMP_DIR) && is_writable($__TMP_DIR)) { // The project has a laravel storage folder?
            $__TMP_DIR .= 'sledgehammer';
        } else { // Use the global /tmp
            $__TMP_DIR = '/tmp/sledgehammer_'.md5(\Sledgehammer\PATH);
        }
    }
    if (function_exists('posix_getpwuid')) {
        $__posix_getpwuid = posix_getpwuid(posix_geteuid());
        $__TMP_DIR .= '_'.@$__posix_getpwuid['name'].DIRECTORY_SEPARATOR;
        unset($__posix_getpwuid);
    } else {
        $__TMP_DIR .= DIRECTORY_SEPARATOR;
    }
    define('Sledgehammer\TMP_DIR', $__TMP_DIR);
    unset($__TMP_DIR);
}
// Case insensitive "natural order" sorting method for natcasesort() in Collection->orderBy()
define('Sledgehammer\SORT_NATURAL_CI', -2);
// Regex for supported operators for the compare() function
define('Sledgehammer\COMPARE_OPERATORS', '==|!=|<|<=|>|>=|IN|NOT IN|LIKE|NOT LIKE');

/**
 * 3. Declare public functions.
 */
require_once __DIR__.'/functions.php'; // Namespaced functions
require_once __DIR__.'/helpers.php'; // Global functions (but not guaranteed)

\Sledgehammer\mkdirs(\Sledgehammer\TMP_DIR);

/*
 * Configure and enable the AutoLoader
 */
require_once __DIR__.'/Core/Object.php';
require_once __DIR__.'/Core/Debug/Autoloader.php';
spl_autoload_register('Sledgehammer\Core\Debug\AutoLoader::lazyRegister');

/*
 * Timestamp (in microseconds) for when the Sledgehammer Framework was initialized.
 */
define('Sledgehammer\INITIALIZED', microtime(true));
