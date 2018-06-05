<?php

namespace Sledgehammer\Core;

class Environment extends Base
{
    private static $environment;
    private static $tmpdir;

    /**
     *  Detect the Environment
     */
    public static function detected()
    {
        if (self::$environment) {
            return self::$environment;
        }
        $environment = false;
        if (getenv('APP_ENV')) { // Laravel (and Dotenv already ran)
            $environment = getenv('APP_ENV');
        } elseif (file_exists(__DIR__.'/../../../../.env')) { // Laravel but Dotenv didn't ran yet
            $environment = @parse_ini_file(__DIR__.'/../../../../.env');
            if (is_array($environment) && isset($environment['APP_ENV'])) {
                $environment['APP_ENV'];
            } else {
                $environment = false;
            }
        }
        if (!$environment) {
            if (getenv('APPLICATION_ENV')) { // Zend
                $environment = getenv('APPLICATION_ENV');
            } else {
                $environment = 'production';
            }
        }
        self::$environment = $environment;
        return $environment;
    }

    /**
     * Detect & create a writable tmp folder
     */
    public static function tmpdir()
    {
        if (self::$tmpdir) {
            return self::$tmpdir;
        }
        $tmpdir = \Sledgehammer\PATH.'tmp'.DIRECTORY_SEPARATOR;
        if (is_dir($tmpdir) && is_writable($tmpdir)) { // The project has a "tmp" folder?
            $tmpdir .= 'sledgehammer';
        } else {
            $tmpdir = \Sledgehammer\PATH.'storage'.DIRECTORY_SEPARATOR;
            if (is_dir($tmpdir) && is_writable($tmpdir)) { // The project has a laravel storage folder?
                $tmpdir .= 'sledgehammer';
            } else { // Use the global /tmp
                $tmpdir = '/tmp/sledgehammer_'.md5(\Sledgehammer\PATH);
            }
        }
        if (function_exists('posix_getpwuid')) {
            $__posix_getpwuid = posix_getpwuid(posix_geteuid());
            $tmpdir .= '_'.@$__posix_getpwuid['name'].DIRECTORY_SEPARATOR;
            unset($__posix_getpwuid);
        } else {
            $tmpdir .= DIRECTORY_SEPARATOR;
        }
        \Sledgehammer\mkdirs($tmpdir);
        self::$tmpdir = $tmpdir;
        return $tmpdir;
    }

// // Configure the "?debug=1" to use another $_GET variable.
// if (!defined('Sledgehammer\DEBUG_VAR')) {
//     define('Sledgehammer\DEBUG_VAR', 'debug');
// }
}
