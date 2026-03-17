<?php

use Sledgehammer\Core\Framework;
use function Sledgehammer\mkdirs;

$paths = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(dirname(dirname(__DIR__))) . '/autoload.php'
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        include_once($path);
        break;
    }
}

$uid = posix_geteuid();
$info = posix_getpwuid($uid);
$tmp = sys_get_temp_dir() . '/sledgehammer_' . $info['name'] . '_' . md5(__DIR__);
mkdirs($tmp);
Framework::setTmp($tmp);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});
