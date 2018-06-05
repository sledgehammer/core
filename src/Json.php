<?php

namespace Sledgehammer\Core;

use Exception;
use Sledgehammer\Core\Debug\DebugR;
use Sledgehammer\Core\Debug\ErrorHandler;

/**
 * Renders the data as Json
 * Compatible with MVC's the Document/View inferface.
 */
class Json extends Base
{
    /**
     * UTF-8 encoded data.
     *
     * @var mixed
     */
    private $data;

    /**
     * The (HTTP) headers, changes Content-Type to "application/json".
     *
     * @var array
     */
    public $headers;

    /**
     * Constructor.
     *
     * @param mixed  $data        The data to be sent as json.
     * @param array  $headers     The (HTTP) headers
     * @param string $dataCharset The encoding used in $data, use null for autodetection. Assume UTF-8 by default.
     */
    public function __construct($data, $headers = [], $dataCharset = 'UTF-8')
    {
        if (strtoupper($dataCharset) !== 'UTF-8') {
            $this->data = $this->convertToUTF8($data, $dataCharset);
        } else {
            $this->data = $data;
        }
        if (empty($headers['http']['Content-Type'])) {
            $headers['http']['Content-Type'] = 'application/json';
        }
        $this->headers = $headers;
    }

    /**
     * The (HTTP) headers.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Render the $data as json.
     */
    public function render()
    {
        echo self::encode($this->data);
    }

    /**
     * Render as a standalone document.
     *
     * @return true
     */
    public function isDocument()
    {
        return true;
    }

    /**
     * Return the $this->data as json formatted string.
     * Allow `echo $json;`.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            ob_start();
            $this->render();

            return ob_get_clean();
        } catch (Exception $e) {
            \Sledgehammer\report_exception($e);

            return '';
        }
    }

    /**
     * Returns a string containing the JSON representation of value.
     *
     * @param mixed $data
     * @param int   $options Optional bitmask, with flags: JSON_HEX_QUOT, JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, JSON_NUMERIC_CHECK, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT and/or JSON_UNESCAPED_UNICODE.
     *
     * @throws Exception
     *
     * @return string JSON formatted string
     */
    public static function encode($data, $options = null)
    {
        if ($options === null) {
            $options = JSON_UNESCAPED_SLASHES;
            // if (\Sledgehammer\ENVIRONMENT === 'development') {
            //     $options |= JSON_PRETTY_PRINT;
            // }
        }
        $json = json_encode($data, $options);
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            throw new Exception(self::errorMessage($error), $error);
        }

        return $json;
    }

    /**
     * Takes a JSON encoded string and converts it into a PHP variable.
     *
     * @param string $json    JSON formatted string
     * @param bool   $assoc
     * @param int    $depth
     * @param int    $options Optional bitmask, with flags: JSON_BIGINT_AS_STRING
     *
     * @throws Exception
     *
     * @return mixed data
     */
    public static function decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $data = json_decode($json, $assoc, $depth, $options);
        $error = json_last_error();
        if ($error !== JSON_ERROR_NONE) {
            throw new Exception(self::errorMessage($error), $error);
        }

        return $data;
    }

    /**
     * Reports the error/exception to the ErrorHandler and returns the error as Json object.
     * The javascript client should detect and report the error to the user:
     *   if (result.success !== true) { alert(result.error); }.
     *
     * @param string|Exception $error The error message or Exception
     * @param int              $http  The HTTP status code (defaults to 400 Bad Request)
     *
     * @return Json
     */
    public static function error($error, $http = 400)
    {
        // if (headers_sent() === false && DebugR::isEnabled()) {
        //     ErrorHandler::instance()->html = false;
        // }
        if ($error instanceof Exception) {
            \Sledgehammer\report_exception($error);
            $error = $error->getMessage();
        } else {
            \Sledgehammer\warning($error);
        }

        return new self([
            'success' => false,
            'error' => $error,
        ], [
            'http' => [
                'Status' => $http.' '.Framework::$statusCodes[$http],
                'Content-Type' => 'application/json'
                // 'Content-Type' => (ErrorHandler::instance()->html ? 'text/html;  charset=utf-8' : 'application/json'),
            ],
        ]);
    }

    /**
     * Short for "new Json(array('success' => true))".
     *
     * @param mixed $data [optional] The data payload
     *
     * @return Json
     */
    public static function success($data = null)
    {
        if ($data === null) {
            return new self(array(
                'success' => true,
            ));
        }

        return new self(array(
            'success' => true,
            'data' => $data,
        ));
    }

    /**
     * Return a data-structure where all string are UTF-8 encoded.
     *
     * @param mixed       $data    The non UTF-8 encoded data
     * @param string|null $charset The from_encoding, Use null for autodetection
     *
     * @return mixed UTF8 encoded data
     */
    private function convertToUTF8($data, $charset)
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', $charset);
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->convertToUTF8($value, $charset);
            }

            return $data;
        }
        // Is $data a integer, float, etc
        return $data;
    }

    /**
     * Translates a json error into a human readable error.
     *
     * @param int $errno JSON_ERROR_*
     *
     * @return string
     */
    private static function errorMessage($errno)
    {
        static $lookup = null;
        if ($lookup === null) {
            $lookup = [];
            $constants = get_defined_constants();
            foreach ($constants as $constant => $constant_value) {
                if (substr($constant, 0, 11) === 'JSON_ERROR_') {
                    $lookup[$constant_value] = $constant;
                }
            }
        }
        if (array_key_exists($errno, $lookup)) {
            $message = '['.$lookup[$errno].'] ';
        } else {
            $message = '['.$errno.'] ';
        }
        switch ($errno) {
            case JSON_ERROR_NONE:
                $message .= 'No errors';
                break;
            case JSON_ERROR_DEPTH:
                $message .= 'The maximum stack depth has been exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message .= 'Invalid or malformed JSON';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message .= 'Control character error, possibly incorrectly encoded';
                break;
            case JSON_ERROR_SYNTAX:
                $message .= 'Syntax error';
                break;
            case JSON_ERROR_UTF8:
                $message .= 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message = 'Unknown error';
        }

        return $message;
    }
}
