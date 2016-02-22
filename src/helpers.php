<?php
/*
 * Create shortcuts to Sledgehammer namespaced functions.
 *
 * Too prevent fatal "Cannot redeclare" errors, the functions are only defined if they aren't already defined.
 */

use Sledgehammer\Core\Debug\ErrorHandler;

if (function_exists('dump') === false) {

    /**
     * Dumps information about a variable, like var_dump() but with improved syntax and coloring.
     *
     * @param mixed $variable
     * @param bool  $export
     *
     * @return string|void
     */
    function dump($variable, $export = false)
    {
        return Sledgehammer\dump($variable, $export);
    }
}

if (function_exists('debugr') === false) {

    /**
     * Shorthand for sending DebugR messages.
     *   debugr()->log($var); instead of \Sledgehammer\DebugR::log($var);
     *   debugr($var); instead of \Sledgehammer\DebugR::dump($var).
     *
     * @param mixed $variable
     *
     * @return \Sledgehammer\DebugR
     */
    function debugr($variable = null)
    {
        return Sledgehammer\debugr($variable);
    }
}

if (function_exists('value') === false) {

    /**
     * Return the value of a variable or return null if the valiable not exist. (Prevents "Undefined variable" notices)
     * WARNING! As a side-effect non existing variables are set to null.
     * If you pass array element to `value($var['index'])` and $var didn't exist, an array is created: array('index' => null)
     * Use \Sledgehammer\array_value() which doesn't have this side effect for array.
     *
     * Example:
     *   if (value($_GET['foo']) == 'bar') {
     * instead of
     *   if (isset($_GET['foo']) && $_GET['foo'] == 'bar') {
     *
     * @param mixed $variable
     *
     * @return mixed
     */
    function value(&$variable)
    {
        if (isset($variable)) {
            return $variable;
        }
    }
}

if (function_exists('array_value') === false) {

    /**
     * Return the value of the array element or return null if the element doesn't exist. (Prevents "Undefined index" notices).
     *
     * Example:
     *   if (\Sledgehammer\array_value($_GET, 'foo') == 'bar') {
     * instead of
     *   if (isset($_GET['foo']) && $_GET['foo'] == 'bar') {
     *
     * @param array  $array
     * @param string $key
     *
     * @return mixed
     */
    function array_value($array, $key)
    {
        return call_user_func_array('Sledgehammer\array_value', func_get_args());
    }
}

if (function_exists('error') === false) {

    /**
     * Report a fatal error (and stop executing).
     *
     * It's preferred to throw Exceptions, which allows the calling code to react to the error.
     *
     * @param string $message     The error
     * @param mixed  $information [optional] Additional information
     */
    function error($message, $information = null)
    {
        Sledgehammer\error($message, $information);
    }
}

if (function_exists('warning') === false) {

    /**
     * Report a warning.
     *
     * @param string $message     The warning
     * @param mixed  $information [optional] Additional information
     */
    function warning($message, $information = null)
    {
        ErrorHandler::instance()->report(E_USER_WARNING, $message, $information, true);
    }
}

if (function_exists('notice') === false) {

    /**
     * Report a notice.
     *
     * @param string $message     The notice
     * @param mixed  $information [optional] Additional information
     */
    function notice($message, $information = null)
    {
        ErrorHandler::instance()->report(E_USER_NOTICE, $message, $information, true);
    }
}

if (function_exists('deprecated') === false) {

    /**
     * Report deprecated functionality.
     *
     * @param string $message     The message
     * @param mixed  $information [optional] Additional information
     */
    function deprecated($message = 'This functionality will no longer be supported in upcomming releases', $information = null)
    {
        ErrorHandler::instance()->report(E_USER_DEPRECATED, $message, $information, true);
    }
}

if (function_exists('report_exception') === false) {

    /**
     * Report an exception.
     *
     * @param Exception $exception
     */
    function report_exception($exception)
    {
        Sledgehammer\report_exception($exception);
    }
}
