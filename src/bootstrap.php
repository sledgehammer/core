<?php

/**
 * Bootstrap the Sledgehammer Framework.
 */
use Sledgehammer\Core\Debug\DebugR;

if (defined('Sledgehammer\INITIALIZED')) {
    return;
}
/*
 * 1. Define the Sledgehammer\* Constants
 */
if (!defined('Sledgehammer\STARTED')) {
    // Timestamp (in microseconds) for when the script started.
    define('Sledgehammer\STARTED', microtime(true));
}
// Directory of the vendor.
if (!defined('Sledgehammer\VENDOR_DIR')) {
    if (file_exists(dirname(__DIR__).'/vendor')) { // local composer install
        define('Sledgehammer\VENDOR_DIR', dirname(__DIR__).'/vendor'.DIRECTORY_SEPARATOR);
    } else {
        define('Sledgehammer\VENDOR_DIR', dirname(dirname(dirname(dirname(__DIR__)))).'/vendor'.DIRECTORY_SEPARATOR);
    }
}
// // Directory of the project.
define('Sledgehammer\PATH', dirname(\Sledgehammer\VENDOR_DIR).DIRECTORY_SEPARATOR);
// Regex for supported operators for the compare() function
define('Sledgehammer\COMPARE_OPERATORS', '==|!=|<|<=|>|>=|IN|NOT IN|LIKE|NOT LIKE');

/**
 * 2. Declare public functions.
 */
require_once __DIR__.'/functions.php'; // Namespaced functions
require_once __DIR__.'/helpers.php'; // Global functions (but not guaranteed)

/*
 * 3. Set DebugR statusbar header.
 */
if (headers_sent() === false && isset($_SERVER['HTTP_DEBUGR'])) {
    DebugR::send('sledgehammer-statusbar', 'no statusbar data.', true);
}
/*
 * Timestamp (in microseconds) for when the Sledgehammer Framework was initialized.
 */
define('Sledgehammer\INITIALIZED', microtime(true));
