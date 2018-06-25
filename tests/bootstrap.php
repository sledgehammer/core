<?php
use Sledgehammer\Core\Framework;
use function Sledgehammer\mkdirs;

$paths =[
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(dirname(dirname(__DIR__))).'/autoload.php'
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        include_once($path);
        break;
    }
}

$uid = posix_geteuid();
$info = posix_getpwuid($uid);
$tmp = sys_get_temp_dir().'/sledgehammer_'.$info['name'].'_'.md5(__DIR__);
mkdirs($tmp);
Framework::setTmp($tmp);
