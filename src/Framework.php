<?php

namespace Sledgehammer\Core;

use Exception;
use function Sledgehammer\mkdirs;

/**
 * Framework.
 */

/**
 * Container voor Sledgehammer Framework functions
 * - Module detection and initialisation
 * - Language & locale initialisation.
 */
class Framework extends Base
{
    /**
     * Register UTF-8 as default charset.
     *
     * @var string
     */
    public static $charset = 'UTF-8';

    /**
     * The HTTP status codes.
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     *
     * @var array
     */
    public static $statusCodes = array(
        // 1xx Informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Unofficial
        102 => 'Processing',
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Unofficial
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // 3xx Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // 4xx Client Error
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Unofficial
        418 => 'I\'m a teapot',
        419 => 'Authentication Timeout',
        420 => 'Enhance Your Calm',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        424 => 'Method Failure',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'No Response',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        451 => 'Unavailable For Legal Reasons',
        451 => 'Redirect',
        494 => 'Request Header Too Large',
        495 => 'Cert Error',
        496 => 'No Cert',
        497 => 'HTTP to HTTPS',
        499 => 'Client Closed Request',
        // 5xx Server Error
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        // Unofficial
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        598 => 'Network read timeout error',
        599 => 'Network connect timeout error',
    );

    /**
     * @var string Directory for temporary files.
     */
    private static $tmp;


    /**
     * Returns directory path used for temporary files.
     */
    public static function tmp($directory = null)
    {
        if (!self::$tmp) {
            throw new InfoException("No tmp folder was configured.", "Call ".self::class.'::setTmp() first');
        }
        if ($directory === null) {
            return self::$tmp;
        }
        if (substr($directory, -1) !== '/') {
            $directory .= "/";
        }
        if (mkdirs(self::$tmp.$directory) === false) {
            throw new Exception('Could not create directory "'.$directory.'"');
        }
        return self::$tmp.$directory;
    }

    public static function setTmp($path)
    {
        if (substr($path, -1) !== '/') {
            $path .= "/";
        }
        if (is_dir($path) == false) {
            throw new Exception('Path "'.$path.'" is not a directory');
        }
        if (is_writable($path) === false) {
            throw new Exception('Path "'.$path.'" is not writable');
        }
        self::$tmp = $path;
    }
}
