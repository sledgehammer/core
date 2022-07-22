<?php

namespace Sledgehammer;

use Closure;
use Throwable;
use DirectoryIterator;
use Exception;
use ReflectionObject;
use Sledgehammer\Core\Cache;
use Sledgehammer\Core\Collection;
use Sledgehammer\Core\Database\Connection;
use Sledgehammer\Core\Database\Sql;
use Sledgehammer\Core\Debug\DebugR;
use Sledgehammer\Core\Debug\Dump;
use Sledgehammer\Core\Debug\ErrorHandler;
use Sledgehammer\Core\Debug\Logger;
use Sledgehammer\Core\Framework;
use Sledgehammer\Core\InfoException;
use Sledgehammer\Core\PropertyPath;
use Sledgehammer\Core\Text;
use stdClass;
use Traversable;
use Iterator;

/**
 * Sledgehammer functions.
 *
 * Adds global functions that are missing in php.
 */

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
    if (!class_exists('Sledgehammer\Core\Debug\Dump', false)) {
        if (!class_exists('Sledgehammer\Core\Base', false)) {
            include __DIR__ . '/Base.php';
        }
        include __DIR__ . '/Debug/Dump.php';
    }
    if ($export) {
        ob_start();
    } elseif (headers_sent() === false && class_exists('Sledgehammer\Core\Framework', false)) {
        // Force Content-Type to text/html.
        header('Content-Type: text/html; charset=' . strtolower(Framework::$charset));
    }
    $dump = new Dump($variable);
    $dump->render();
    if ($export) {
        return ob_get_clean();
    }
    ob_flush();
}

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
    ErrorHandler::report(E_USER_ERROR, $message, $information, true);
    exit(1); // Stop script execution with error 1
}

/**
 * Report a warning.
 *
 * @param string $message     The warning
 * @param mixed  $information [optional] Additional information
 */
function warning($message, $information = null)
{
    ErrorHandler::report(E_USER_WARNING, $message, $information, true);
}

/**
 * Report a notice.
 *
 * @param string $message     The notice
 * @param mixed  $information [optional] Additional information
 */
function notice($message, $information = null)
{
    ErrorHandler::report(E_USER_NOTICE, $message, $information, true);
}

/**
 * Report deprecated functionality.
 *
 * @param string $message     The message
 * @param mixed  $information [optional] Additional information
 */
function deprecated($message = 'This functionality will no longer be supported in upcomming releases', $information = null)
{
    ErrorHandler::report(E_USER_DEPRECATED, $message, $information, true);
}

/**
 * Report an exception.
 *
 * @param Exception|Throwable $throwable
 */
function report_exception($throwable)
{
    if ($throwable instanceof Exception || $throwable instanceof Throwable) {
        ErrorHandler::report($throwable);
    } else {
        \Sledgehammer\notice('Parameter $throwable should be a Throwable (Exception)', $throwable);
    }
}

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
    if (func_num_args() != 0) { //
        DebugR::dump($variable);
    }

    return new DebugR();
}

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

/**
 * Return the value of the array element or return null if element doesn't exist. (Prevents "Undefined index" notices).
 *
 * Example 1:
 *   if (\Sledgehammer\array_value($_GET, 'foo') == 'bar') {
 * instead of
 *   if (isset($_GET['foo']) && $_GET['foo'] == 'bar') {
 *
 * Example 2:
 *   if (\Sledgehammer\array_value($_GET, 'foo', 'bar') == 'baz') {
 * instead of
 *   if (isset($_GET['foo']) && isset($_GET['foo']['bar']) && $_GET['foo']['bar'] == 'baz') {
 *
 * @param array  $array
 * @param string $key
 * @param string ...
 *
 * @return mixed
 */
function array_value($array, $key)
{
    if (isset($array) === false) {
        return;
    }
    $container = $array;
    foreach (func_get_args() as $i => $key) {
        if ($i === 0) {
            $container = $array;
            continue;
        }
        if (is_string($key) === false && is_int($key) === false) {
            \Sledgehammer\notice('Unexpected type: "' . gettype($key) . '" for parameter $key, expecting an int or string');

            return;
        }
        if (is_array($container) == false) {
            return;
        }
        if (array_key_exists($key, $container) == false) {
            return;
        }
        $container = $container[$key];
    }

    return $container;
}

/**
 * Test of een array een assoc array is. (Als een key NIET van het type integer zijn).
 *
 * @param array $array
 *
 * @return bool
 */
function is_assoc($array)
{
    foreach ($array as $key => $null) {
        if (!is_int($key)) {
            return true;
        }
    }

    return false;
}

/**
 * Test of een array een indexed array is. (Als de keys liniair zijn opgebouwd).
 *
 * @param array $array
 *
 * @return bool
 */
function is_indexed($array)
{
    if (!is_array($array)) {
        if (!(is_object($array) && in_array('Iterator', class_implements($array)))) {
            \Sledgehammer\notice('Unexpected ' . gettype($array) . ', expecting array (or Iterator)');

            return false;
        }
    }
    $index = 0;
    foreach ($array as $key => $null) {
        if ($key !== $index) {
            return false;
        }
        ++$index;
    }

    return true;
}

/**
 * Checks if a variable is a closure.
 *
 * @param mixed $variable
 *
 * @return bool
 */
function is_closure($variable)
{
    return is_object($variable) && is_callable($variable);
}

/**
 * Prepend/moves the value to the beginning of the array using the specified $key.
 *
 * @param array  $array
 * @param string $key
 * @param mixed  $value
 */
function array_key_unshift(&$array, $key, $value = null)
{
    unset($array[$key]);
    $reversed = array_reverse($array, true);
    $reversed[$key] = $value;
    $array = array_reverse($reversed, true);
}

/**
 * Detect mimetype based on file-extention.
 *
 * @param string $filename
 * @param bool   $allow_unknown_types Bij False zal er een foutmelding gegenereerd worden als het bestandstype onbekend is.
 * @param string $default             De mimetype die wordt geretourneerd als er geen mimetype bekend is.
 *
 * @return string Content-Type
 */
function mimetype($filename, $allow_unknown_types = false, $default = 'application/octet-stream')
{
    $extension = \Sledgehammer\file_extension($filename);
    if ($extension === null) {
        $mimetype = null;
    } else {
        // extension => Content-Type
        $mimetypes = array(
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'xml' => 'text/xml',
            'swf' => 'application/x-shockwave-flash',
            'wsdl' => 'text/xml',
            'xsd' => 'text/xml',
            'txt' => 'text/plain',
            'ini' => 'text/plain',
            // Fonts
            'ttf' => 'application/x-font-ttf',
            'otf' => 'application/x-font-opentype',
            'woff' => 'application/font-woff',
            'woff2' => 'application/font-woff2',
            'eot' => 'application/vnd.ms-fontobject',
            // Images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon', //image/vnd.microsoft.icon
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',
            // Audio/Video
            'mp3' => 'audio/mpeg',
            'flv' => 'video/x-flv',
            'mpg' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'avi' => 'video/msvideo',
            'wmv' => 'video/x-ms-video',
            'mp4' => 'video/mp4',
            'mkv' => 'video/x-matroska',
            '3gp' => 'video/3gpp',
            'wav' => 'audio/wav',
            'mid' => 'audio/mid',
            // Adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            // Microsoft Office
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            // OpenOffice
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
        $mimetype = \Sledgehammer\value($mimetypes[strtolower($extension)]);
    }
    if ($mimetype !== null) {
        return $mimetype;
    }
    static $globalMimetypes = null;
    if ($globalMimetypes === null) {
        // Import global mime.types.
        $globalMimetypes = [];
        foreach (array('/etc/mime.types', '/etc/apache2/mime.types') as $mimeFile) {
            if (is_readable($mimeFile)) {
                foreach (file($mimeFile) as $line) {
                    if (substr($line, 0, 1) == '#') {  // skip comments
                        continue;
                    }
                    $extensions = preg_split('/[\s\t]+/', rtrim($line));
                    $mime = array_shift($extensions);
                    foreach ($extensions as $ext) {
                        $globalMimetypes[$ext] = $mime;
                    }
                }
                break;
            }
        }
    }
    $mimetype = \Sledgehammer\value($globalMimetypes[strtolower($extension)]);
    if ($mimetype === null) {
        if (!$allow_unknown_types) {
            trigger_error('Unknown mime type for :"' . $extension . '", E_USER_WARNING');
        }

        return $default;
    }

    return $mimetype;
}

/**
 * implode(), maar dan zodat deze leesbaar is voor mensen.
 * Bv: echo human_implode(' of ', array('appel', 'peer', 'banaan')); wordt 'appel, peer of banaan'.
 *
 * @param string $glueLast deze wordt gebruikt tussen de laaste en het eennalaatste element. bv: ' en ' of ' of '
 * @param array  $array
 * @param string $glue
 *
 * @return string
 */
function human_implode($glueLast, $array, $glue = ', ')
{
    if (!$array || !count($array)) { // sanity check
        return '';
    }
    $last = array_pop($array); // get last element
    if (!count($array)) { // if it was the only element - return it
        return $last;
    }

    return implode($glue, $array) . $glueLast . $last;
}

/**
 * human_implode(), but with quotes (") around the values.
 *
 * @param string $glueLast
 * @param string $array
 * @param string $glue
 * @param string $quote
 *
 * @return string
 */
function quoted_human_implode($glueLast, $array, $glue = ', ', $quote = '"')
{
    if (count($array) == 0) {
        return '';
    }

    return $quote . \Sledgehammer\human_implode($quote . $glueLast . $quote, $array, $quote . $glue . $quote) . $quote;
}

/**
 * implode(), but with quotes (") around the values.
 *
 * @param string $glue
 * @param array $array
 * @param string $quote
 *
 * @return string
 */
function quoted_implode($glue, $array, $quote = '"')
{
    if (count($array) == 0) {
        return '';
    }

    return $quote . implode($quote . $glue . $quote, $array) . $quote;
}

/**
 * Een element uit een array opvragen. Kan ook een subelement opvragen "element[subelement1][subelement2]"
 * Retourneert true als de waarde is gevonden. [abc] wordt ['abc'], maar [1] wordt geen ['1'].
 *
 * @param array  $array
 * @param string $identifier
 * @param mixed  $value      value of the element
 *
 * @return bool
 */
function extract_element($array, $identifier, &$value)
{
    if (isset($array[$identifier])) { // Bestaat het element 'gewoon' in de array?
        $value = $array[$identifier];

        return true;
    }
    $bracket_position = strpos($identifier, '[');
    if ($bracket_position === false) { // Gaat het NIET om een array?
        return false;
    } elseif (strpos($identifier, '[]')) {
        \Sledgehammer\notice('Het identifier bevat een ongeldige combinatie van blokhaken: []', $identifier);

        return false;
    }
    preg_match_all('/\\[[^[]*\\]/', $identifier, $keys); // Deze reguliere exp. splits alle van alle subelementen af in een array. element[subelement1][subelement2] wordt array("[subelement1]", "[subelement2]")
    $identifier = substr($identifier, 0, $bracket_position);
    $php_variabele = '$array["' . addslashes($identifier) . '"]';
    foreach ($keys[0] as $key) {
        $php_code = 'if (gettype(@' . $php_variabele . ") == 'array') {\n\treturn false;\n}\nreturn true;";
        if (eval($php_code)) {
            return false;
        }
        if (preg_match('/[a-zA-Z]+/', $key)) {
            $key = '["' . addslashes(substr($key, 1, -1)) . '"]';
        }
        $php_variabele .= $key;
    }
    $php_code = 'if (isset(' . $php_variabele . "))\n{\n\t\$value = " . $php_variabele . ";\n\t\$return = true;\n}\nelse\n{\n\t\$return = false;\n}";
    $return = null;
    eval($php_code);

    return $return;
}

/**
 * Extract and return the logical operator of a $conditions array.
 *
 * @param array $conditions
 *
 * @return string|false operator 'AND' or 'OR' or false when no operator was found.
 */
function extract_logical_operator($conditions)
{
    if (is_array($conditions) === false) {
        return false;
    }
    reset($conditions);
    if (key($conditions) !== 0) {
        return false;
    }
    $operators = array('AND', 'OR');
    $operator = current($conditions);
    if (in_array($operator, $operators)) {
        return $operator;
    }

    return false;
}

/**
 * Check if two variables are equal with minimal type-coercion.
 *
 * equals((float) 1.000, (int) 1) == true
 * equals("1.1", 1.1) == true
 * equals("abc", "ABC") == false
 * equals(1, true) == true
 * equals(2, true) == false
 *
 * @param mixed $var1
 * @param mixed $var2
 *
 * @return bool
 */
function equals($var1, $var2)
{
    if ($var1 === $var2) { // Strict match?
        return true;
    }
    // Numbers
    // numbers with the same value to be equal.
    // 1 matches 1, "1" & "1.0"
    if (is_numeric($var1) && is_numeric($var2) && (string) $var1 === (string) $var2) {
        return true;
    }
    // Booleans
    // true matches true, "1" and 1
    // false matches false, "0" and 0 (not "" or null)
    if (is_bool($var1) && is_numeric($var2) || is_bool($var2) && is_numeric($var1)) {
        $int1 = intval($var1);
        if ($int1 === intval($var2) && ($int1 === 1 || $int1 === 0)) {
            return true;
        }

        return false;
    }

    return false;
}

/**
 * Compare two values with the given operator.
 * For '==' the values are compared with equals().
 *
 * compare("1",'==', 1) == true
 * compare("abc", '==', "ABC") == true
 * compare("0", '==', 0) == true
 * compare("", '==', 0) == false
 *
 * @see CoreFunctionTest for more examples
 *
 * @param mixed  $value
 * @param string $operator
 * @param mixed  $expectation
 *
 * @return bool
 */
function compare($value, $operator, $expectation)
{
    switch ($operator) {
        case '==':
            return \Sledgehammer\equals($value, $expectation);
        case '!=':
            return !\Sledgehammer\equals($value, $expectation);
        case '<':
            return $value < $expectation;
        case '>':
            return $value > $expectation;
        case '<=':
            return $value <= $expectation;
        case '>=':
            return $value >= $expectation;
        case 'IN':
            foreach ($expectation as $val) {
                if (\Sledgehammer\equals($value, $val)) {
                    return true;
                }
            }

            return false;

        case 'LIKE':
            static $patternCache = [];
            $pattern = \Sledgehammer\array_value($patternCache, $expectation);
            if ($pattern === null) {
                // Build the regular expression.
                $pattern = '';
                for ($i = 0; $i < strlen($expectation); ++$i) {
                    $char = $expectation[$i];
                    if ($char === '%') {
                        $pattern .= '.*';
                    } elseif ($char === '_') {
                        $pattern .= '.{1}';
                    } elseif ($char === '\\') {
                        ++$i;
                        $nextChar = @$expectation[$i];
                        if ($nextChar === null) { // last character?
                            $pattern .= preg_quote($char, '/');
                        } elseif (in_array($nextChar, array('%', '_', '\\'))) { // The \ is used as an escape?
                            $pattern .= preg_quote($nextChar, '/');
                        } else {
                            $pattern .= preg_quote($char . $nextChar, '/');
                        }
                    } else {
                        $pattern .= preg_quote($char, '/');
                    }
                }
                $pattern = '/^' . $pattern . '$/';
                $patternCache[$expectation] = $pattern;
            }

            return preg_match($pattern, $value) !== 0;

        case 'NOT IN':
            return \Sledgehammer\compare($value, 'IN', $expectation) == false;
        case 'NOT LIKE':
            return \Sledgehammer\compare($value, 'LIKE', $expectation) == false;
    }
    throw new Exception('Invalid operator: "' . $operator . '" use ' . \Sledgehammer\quoted_human_implode(' or ', explode('|', \Sledgehammer\COMPARE_OPERATORS)));
}

/**
 * Vergelijk de eigenschappen van 2 objecten met de equals functie.
 *
 * @param stdClass $object1
 * @param stdClass $object2
 * @param array    $properties De eigenschappen die vergeleken moeten worden
 *
 * @return bool True als de eigenschappen gelijk zijn aan elkaar
 */
function equal_properties($object1, $object2, $properties)
{
    foreach ($properties as $property) {
        if (\Sledgehammer\equals($object1->$property, $object2->$property) == false) { // Zijn de eigenschappen verschillend?
            return false;
        }
    }

    return true; // Er zijn geen verschillen gevonden
}

/**
 * Werkt als get_object_vars() maar i.p.v. de waardes op te vragen worden deze ingesteld.
 *
 * @param stdClass $object             Het (doel) object waar de eigenschappen worden aangepast
 * @param array    $values             Een assoc array met als key de eigenschap. bv: array('id' => 1)
 * @param bool     $check_for_property Bij false zal de functie alle array-elementen proberen in het object te zetten, Bij true zullen alleen bestaande elementen ingesteld worden
 *
 * @return mixed the $object
 */
function set_object_vars($object, $values, $check_for_property = false)
{
    if ($check_for_property) {
        foreach ($values as $property => $value) {
            if (property_exists($object, $property)) {
                $object->$property = $value;
            }
        }
    } else {
        foreach ($values as $property => $value) {
            $object->$property = $value;
        }
    }

    return $object;
}

/**
 * Werkt net als get_class_methods, maar de array bevat alleen de publieke functies.
 *
 * @param string|object De naam van de class of het object zelf.
 *
 * @return array
 */
function get_public_methods($class)
{
    return get_class_methods($class);
}

/**
 * Get the properties that are publicly accessable.
 *
 * @param string|object $class
 *
 * @return array
 */
function get_public_vars($class)
{
    if (is_string($class)) {
        return get_class_vars($class);
    } else {
        return get_object_vars($class);
    }
}

/**
 * Use Reflection to extract all properties.
 *
 * @param object $object
 *
 * @return array array(
 *               'public' => array(  // Public properties
 *               $property => $value,
 *               ),
 *               'protected' => [], // protected properties
 *               'private' => [], // private properties
 *               )
 */
function reflect_properties($object)
{
    $reflection = new ReflectionObject($object);
    $values = get_object_vars($object);
    $properties = array(
        'public' => [],
        'protected' => [],
        'private' => [],
    );
    foreach ($reflection->getProperties() as $property) {
        if ($property->isPublic()) {
            if (array_key_exists($property->name, $values) === false) {
                continue; // skip properties that are unset()
            }
            $scope = 'public';
        } elseif ($property->isProtected()) {
            $scope = 'protected';
        } elseif ($property->isPrivate()) {
            $scope = 'private';
        }
        $property->setAccessible(true);
        $properties[$scope][$property->name] = $property->getValue($object);
    }

    return $properties;
}

/**
 * Helper function for the showing the existing properties in an errorreport.
 *
 * @param array $scopedProperties
 *
 * @return string
 */
function build_properties_hint($scopedProperties)
{
    $hint = '';
    foreach ($scopedProperties as $scope => $properties) {
        if (count($properties)) {
            $hint .= '<div style="margin-top: 7px">' . $scope . '</b></div>';
            foreach ($properties as $property => $value) {
                $type = gettype($value);
                if ($type === 'object') {
                    $type = get_class($value);
                } else {
                    $type = strtolower($type);
                }
                $hint .= '&nbsp;&nbsp;' . \Sledgehammer\syntax_highlight($property, 'attribute') . ' ' . \Sledgehammer\syntax_highlight(':' . $type, 'comment') . '<br />';
            }
        }
    }

    return $hint; //
}

/**
 * Call a static method on a specific class without Late Static Binding.
 * Using "call_user_func" will bind the get_called_class() to subclass when the method is inside the parent class.
 *
 * @param string $class
 * @param string $staticMethod
 *                             param ...
 */
function call_static_func($class, $staticMethod)
{
    if (is_object($class)) {
        $class = get_class($class);
    }
    $arguments = func_get_args();
    array_shift($arguments);
    array_shift($arguments);

    return call_user_func_array(array($class, $staticMethod), $arguments);
}

/**
 * Een redirect naar een andere pagina.
 * Werkt indien mogelijk via de HTTP header en anders via Javascript of een META refresh tag.
 *
 * @param string $url
 * @param bool   $permanently Bij true wordt ook de "301 Moved Permanently" header verstuurd
 *
 * @return exit()
 */
function redirect($url, $permanently = false)
{
    if (headers_sent()) {
        // Javascript fallback

        echo '<script type="text/javascript">window.location=' . json_encode((string) $url) . ';</script>';
        echo '<noscript>';
        // Meta refresh fallback
        echo '<meta http-equiv="refresh" content="0; url=' . htmlentities($url, ENT_QUOTES) . '">';
        // Show a link
        echo 'Redirecting to <a href="', htmlspecialchars($url, ENT_QUOTES, 'ISO-8859-15'), '">', htmlspecialchars($url, ENT_QUOTES, 'ISO-8859-15'), '</a>';
        echo '</noscript>';
    } else {
        if ($permanently) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 302 Found');
        }
        header('Location: ' . $url);
    }
    exit();
}

/**
 * Filter a variable using a callable.
 *
 * Usages:
 *
 * // Filter via a global function
 * $encoded = filter($rawData, 'urlencode');
 *
 * // Filter via an existing class
 * $db = new PDO('sqlite::memory:');
 * $quoted = filter($rawData, array($db, 'quote'));
 *
 * // Filter via a class with an __invoke() method.
 * $slug = filter($title, new SlugFilter());
 *
 * // Filter via a closure.
 * $filter = function ($data) { return substr($data, 0, 10); };
 * $truncated = filter($myData, $filter);
 *
 * @param mixed    $value  Input
 * @param callable $filter The filter
 *
 * @return mixed Output
 */
function filter($value, $filter)
{
    if (is_callable($filter) === false) {
        throw new InfoException('The $filter parameter isn\'t a valid callable', $filter);
    }

    return call_user_func($filter, $value);
}

/**
 * Validate a variable using a callable.
 *
 * @param mixed    $value     Input
 * @param callable $validator The filter
 *
 * @return bool Output
 */
function is_valid($value, $validator)
{
    if (is_callable($validator) === false) {
        throw new InfoException('The $validator parameter isn\'t a valid callable', $validator);
    }

    return call_user_func($validator, $value);
}

/**
 * Het path creeren
 * Zal alle map-namen die in het path genoemd worden proberen te maken.
 * Zal geen foutmelding geven als het path al bestaat.
 *
 * @param string $path De map die gemaakt moet worden
 *
 * @return bool
 */
function mkdirs($path)
{
    if (is_dir($path)) {
        return true;
    }
    $parent = dirname($path);
    if ($parent == $path) { // Is er geen niveau hoger?
        \Sledgehammer\warning('Unable to create path "' . $path . '"'); // Ongeldig $path bv null of ""
    } elseif (\Sledgehammer\mkdirs($parent)) { // Maak (waneer nodig) de boverliggende deze map aan.
        return mkdir($path); //  Maakt de map aan.
    }

    return false;
}

/**
 * De map, inclusief alle bestanden en submappen in het $path verwijderen.
 *
 * @throws Exception on failure
 *
 * @param string $path
 * @param bool   $allowFailures
 *
 * @return int Het aantal verwijderde bestanden
 */
function rmdir_recursive($path, $allowFailures = false)
{
    $counter = 0;
    $dir = new DirectoryIterator($path);
    foreach ($dir as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir()) { // is het een map?
            $counter += \Sledgehammer\rmdir_recursive($entry->getPathname() . '/', $allowFailures);
            continue;
        }
        if (unlink($entry->getPathname()) == false && $allowFailures == false) {
            throw new Exception('Failed to delete "' . $entry->getPathname() . '"');
        }
        ++$counter;
    }
    if (rmdir($path) == false && $allowFailures == false) {
        throw new Exception('Failed to delete directory "' . $path . '"');
    }

    return $counter;
}

/**
 * Delete the contents of the folder, but not the folder itself.
 *
 * @throws Exception on failure
 *
 * @param string $path
 * @param bool   $allowFailures
 *
 * @return int Het aantal verwijderde bestanden
 */
function rmdir_contents($path, $allowFailures = false)
{
    $counter = 0;
    $dir = new DirectoryIterator($path);
    foreach ($dir as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir()) { // is het een map?
            $counter += \Sledgehammer\rmdir_recursive($entry->getPathname(), $allowFailures);
        } else {
            if (unlink($entry->getPathname()) == false && $allowFailures == false) {
                throw new Exception('Failed to delete "' . $entry->getPathname() . '"');
            }
            ++$counter;
        }
    }

    return $counter;
}

/**
 * Een bestand verwijderen, met extra controle dat het bestand zich in de $basepath map bevind.
 *
 * @throws Exception
 *
 * @param string $filepath
 * @param string $basepath
 * @param bool   $recursive
 */
function safe_unlink($filepath, $basepath, $recursive = false)
{
    if (in_array(substr($basepath, -1), array('/', '\\'))) { // Heeft de $basepath een trailing slash?
        $basepath = substr($basepath, 0, -1);
    }
    if (strlen($basepath) < 4) { // Minimal "/tmp"
        throw new Exception('$basepath "' . $basepath . '" is too short');
    }
    //  Controleer of het path niet buiten de basepath ligt.
    $realpath = realpath(dirname($filepath));
    if ($realpath == false) {
        throw new Exception('Invalid folder: "' . dirname($filepath) . '"'); // Kon het path niet omvormen naar een bestaande map.
    }
    $filepath = $realpath . '/' . basename($filepath);  // Nette $filepath
    if (substr($filepath, 0, strlen($basepath)) != $basepath) { // Hack poging?
        throw new Exception('Ongeldige bestandsnaam "' . $filepath . '"');
    }
    if (!file_exists($filepath)) {
        throw new Exception('File "' . $filepath . '" not found');
    }
    if ($recursive) {
        \Sledgehammer\rmdir_recursive($filepath);

        return;
    }
    if (unlink($filepath) == false) {
        throw new Exception('Failed to delete "' . $filepath . '"');
    }
}

/**
 * Get the timestamp for the latest change in the directory.
 *
 * @param string             $path
 * @param array|string|false $extensions Only check timestamp for files that match one of the extensions in the array or match against a regex.
 * @param int                $count      out Set to the number of files checked. Used to detect deleted files.
 *
 * @return int
 */
function mtime_folders($path, $extensions = null, &$count = null)
{
    $max_ts = filemtime($path); // Vraag de mtime op van de map
    if ($max_ts === false) { // Bestaat het $path niet?
        return false;
    }
    if (substr($path, -1) != '/') {
        $path .= '/';
    }
    if (is_array($extensions)) {
        // Convert $extensions array into a regex.
        $regex = [];
        foreach ($extensions as $extension) {
            $regex[] = preg_quote($extension, '/');
        }
        $regex = '/\.(' . implode('|', $extensions) . ')$/';
    } else {
        $regex = $extensions;
    }
    if ($regex) {
        $max_ts = false; // Don't count the folder changes when extensions are given.
    }
    // Controleer of een van de bestanden of submappen een nieuwere mtime heeft.
    $dir = opendir($path);
    if ($dir) {
        $count = 0;
        while (($filename = readdir($dir)) !== false) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $filepath = $path . $filename;
            if (is_dir($filepath)) {
                $ts = \Sledgehammer\mtime_folders($filepath . '/', $regex, $subcount);
                $count += $subcount;
            } else {
                if ($regex && preg_match($regex, $filename) === 0) { // Does't match any of the extensions?
                    continue;
                }
                $ts = filemtime($filepath);
                ++$count;
            }
            if ($ts > $max_ts) { // Heeft de submap een nieuwere timestamp?
                $max_ts = $ts;
            }
        }
        closedir($dir);
    }

    return $max_ts;
}

/**
 * Een map inclusief bestanden en submappen copieren (Als de doelmap al bestaat, worden deze overschreven/aangevuld)
 * De doelmap wordt NIET eerst verwijderd.
 *
 * @param string $source      De bronmap
 * @param string $destination De doelmap
 * @param array  $exclude     Een array met bestand en/of mapnamen die niet gekopieerd zullen.
 *
 * @return int
 */
function copydir($source, $destination, $exclude = [])
{
    if (!is_dir($destination) && !\Sledgehammer\mkdirs($destination)) {
        return false;
    }
    $count = 0;
    $dir = new DirectoryIterator($source);
    foreach ($dir as $entry) {
        if ($entry->isDot() || in_array($entry->getFilename(), $exclude)) {
            continue;
        } elseif ($entry->isFile()) {
            if (copy($entry->getPathname(), $destination . '/' . $entry->getFilename())) {
                ++$count;
            } else {
                break;
            }
        } elseif ($entry->isDir()) {
            $count += copydir($entry->getPathname(), $destination . '/' . $entry->getFilename(), $exclude);
        } else {
            \Sledgehammer\notice('Unsupported filetype');
        }
    }

    return $count;
}

/**
 * Returns a html snippet with the variable in a predefined color per type.
 * Used in Dump & ErrorHandler.
 *
 * @param mixed  $variable
 * @param string $datatype   Skip autodetection/force a type
 * @param int    $titleLimit maximum length for the title attribute (which contains the contents of the array|object)
 *
 * @return string
 */
function syntax_highlight($variable, $datatype = null, $titleLimit = 256)
{
    if (class_exists(Framework::class, false) === false) {
        require_once __DIR__ . '/Framework.php';
    }
    if ($datatype === null) {
        $datatype = gettype($variable);
    }
    switch ($datatype) {
        case 'boolean':
            $color = 'symbol';
            $label = $variable ? 'true' : 'false';
            break;

        case 'integer':
        case 'double':
            $color = 'number';
            $label = $variable;
            break;

        case 'string':
            $color = 'string';
            $label = '&#39;' . str_replace("\n", '<br />', str_replace(' ', '&nbsp;', htmlspecialchars($variable, ENT_COMPAT, Framework::$charset))) . '&#39;';
            break;

        case 'array':
            $color = 'method';
            $label = 'array(' . count($variable) . ')';
            break;

        case 'object':
            $color = 'class';
            $label = get_class($variable);
            break;

        case 'resource':
            $color = 'resource';
            $label = $variable;
            break;

        case 'NULL':
            $color = 'symbol';
            $label = 'null';
            break;

        case 'unknown type':
            $color = 'resource';
            $label = $variable;
            break;

            // al geconverteerde datatypes
        case 'symbol':
        case 'number':
        case 'comment':
        case 'class':
        case 'attribute':
        case 'method':
        case 'operator':
        case 'foreground': // (, [, etc
            $color = $datatype;
            $label = $variable;
            break;

        default:
            \Sledgehammer\notice('Datatype: "' . $datatype . '" is unknown');
            break;
    }
    // Based on the Tomorrow theme.
    // @link https://github.com/ChrisKempson/Tomorrow-Theme
    $colorCodes = array(
        // #ffffff Background
        // #efefef Current Line
        // #d6d6d6 Selection
        'foreground' => '#4d4d4c', // Lightblack
        'string' => '#718c00', // Green
        'number' => '#f5871f', // Orange
        'operator' => '#3e999f', // Aqua
        'symbol' => '#f5871f', // Orange
        'resource' => '#eab700', // Yellow
        'method' => '#4271ae', // Blue
        'class' => '#8959a8', // Purple (instead of yellow to improve readability on the yellow error background)
        'attribute' => '#c82829', // Red
        'variable' => '#c82829', // Red
        'comment' => '#8e908c', // Gray
    );
    $html = '<span style="color:' . $colorCodes[$color] . '"';
    if (($datatype === 'object' || $datatype === 'array') && $titleLimit > 0) { // Built title attribute?
        $title = partial_var_export($variable, $titleLimit, 4);
        $html .= ' title="' . str_replace(array("\n"), array('&#10;'), htmlentities($title, ENT_COMPAT, Framework::$charset)) . '"';
    }

    return $html . '>' . $label . '</span>';
}

/**
 * Returns human-readable information about a object or array variable.
 * Used in syntax_hightlight() title (Shows the contents of an object/array on hover).
 *
 * @param array|stdClass $variable
 * @param int            $maxLenght
 * @param int|false      $maxDepth
 * @param int            $depth
 *
 * @return string
 */
function partial_var_export($variable, $maxLenght, $maxDepth = false, $depth = 0)
{
    $end = [];
    $title = '';
    $hellip = html_entity_decode('&hellip;', ENT_NOQUOTES, Framework::$charset); // aka the '...' character
    if (is_array($variable)) {
        $title = 'array(';
        $end = ')';
        $elements = $variable;
    } else {
        $title = get_class($variable) . '{';
        $end = '}';
        $elements = get_object_vars($variable);
    }
    if (count($elements) == 0) {
        return $title . $end;
    }
    $indent = str_repeat('  ', $depth + 1);
    if ($depth !== 0) {
        $end = substr($indent, 2) . $end;
    }
    $title .= "\n";
    foreach ($elements as $key => $value) {
        if (strlen($title) > $maxLenght) {
            $title .= $indent . $hellip . "\n";
            break;
        }
        if (is_array($variable)) {
            $title .= $indent;
            if (is_string($key)) {
                $title .= "'" . $key . "'";
            } else {
                $title .= $key;
            }
            $title .= ' => ';
        } else {
            $title .= $indent . $key . ' = ';
        }
        if (is_string($value)) {
            $charactersLeft = $maxLenght - strlen($title);
            if (strlen($value) > $charactersLeft) {
                if ($charactersLeft < 10) {
                    $charactersLeft = 8;
                }
                $title .= "'" . substr($value, 0, $charactersLeft) . $hellip . "\n";
            } else {
                $title .= "'" . $value . "'\n";
            }
        } elseif (is_array($value)) {
            if ($maxDepth !== false && $depth == $maxDepth) {
                $title .= 'array(' . count($value) . "),\n";
            } else {
                $title .= partial_var_export($value, $maxLenght - strlen($title), $maxDepth, $depth + 1) . ",\n";
            }
        } elseif (is_object($value)) {
            $title .= get_class($value) . ",\n";
        } else {
            $title .= $value . ',' . "\n";
        }
    }

    return $title . $end;
}

/**
 * Zet een float om naar een leesbare (parse)tijdnotatie
 * 61.23 => '1:01.230'.
 *
 * @param float $seconds
 * @param int   $precision
 *
 * @return string
 */
function format_parsetime($seconds, $precision = 3)
{
    if ($seconds < 60) { // Duurde het genereren korter dan 1 minuut?
        return number_format($seconds, $precision);
    } else {
        $minutes = floor($seconds / 60);
        $miliseconds = fmod($seconds, 1);
        $seconds = str_pad($seconds % 60, 2, '0', STR_PAD_LEFT);

        return $minutes . ':' . $seconds . substr(number_format($miliseconds, $precision), 1);
    }
}

/**
 * Show debug and profiling information.
 * Contains parsetime, memory usage and (sql) log entries.
 */
function statusbar($debugr = false)
{
    $divider = '<span class="statusbar-divider">, </span>';
    if (defined('Sledgehammer\STARTED')) {
        $now = microtime(true);
        echo '<span class="statusbar-tab"><span class="statusbar-parsetime">Time&nbsp;<b>', \Sledgehammer\format_parsetime($now - \Sledgehammer\STARTED), '</b>&nbsp;sec';
        if (defined('Sledgehammer\INITIALIZED')) {
            echo ' <span class="statusbar-popout">Init&nbsp;<b>', \Sledgehammer\format_parsetime(\Sledgehammer\INITIALIZED - \Sledgehammer\STARTED), '</b>&nbsp;sec';
            if (defined('Sledgehammer\GENERATED')) {
                echo $divider, 'Execute&nbsp;<b>', \Sledgehammer\format_parsetime(GENERATED - \Sledgehammer\INITIALIZED), '</b>&nbsp;sec';
                echo $divider, 'Render&nbsp;<b>', \Sledgehammer\format_parsetime($now - GENERATED), '</b>&nbsp;sec';
            }
            echo '</span>';
        }
        echo '</span></span>';
    }
    if (function_exists('memory_get_usage')) { // Geheugenverbruik in MiB tonen
        echo $divider, 'Memory&nbsp;<b title="Current memory usage">', number_format(memory_get_usage() / 1048576, 2), '</b>';
        if (function_exists('memory_get_peak_usage')) {
            echo '<span style="margin: 0 1px;">/</span><b title="Peak memory usage">', number_format(memory_get_peak_usage() / 1048576, 2), '</b>';
        }
        echo '&nbsp;MiB';
    }
    if ($debugr) {
        echo '<span class="statusbar-divider">, </span><span id="statusbar-debugr" class="statusbar-tab"><a href="https://bfanger.nl/debugr/" target="_blank">DebugR</a></span>';
    }
    if (class_exists(Logger::class, false) && count(Logger::$instances) > 0) {
        foreach (Logger::$instances as $name => $logger) {
            if ($logger->count !== 0) {
                echo $divider;
                $logger->statusbar($name);
            } elseif (count($logger->entries) > 0 && $logger->totalDuration > 0.05) {
                echo $divider;
                $logger->statusbar($name);
            }
        }
    }
}

/**
 * Zet alle iterators binnen $data om naar arrays.
 * Doet zijn best om $source niet te veranderen, maar is afhankelijk van __clone
 * (private en protected eigenschappen worden niet omgezet).
 *
 * @param mixed $data
 *
 * @return mixed
 */
function iterators_to_arrays($data)
{
    if (!is_object($data) && !is_array($data)) { // Is het een primitief type?
        return $data; // niks om om te zetten.
    }
    if (is_object($data)) {
        if ($data instanceof Iterator) { // Is dit een iterator?
            $array = iterator_to_array($data); // Iterator omzetten naar een array.
            return iterators_to_arrays($array); /// Alle elementen (mogelijk) omzetten
        } else {
            $object = clone $data;
            foreach ($data as $property => $value) {
                $object->$property = iterators_to_arrays($value);
            }

            return $object;
        }
    }
    // dan is het een array.
    $array = [];
    foreach ($data as $key => $value) {
        $array[$key] = iterators_to_arrays($value); // Alle elementen (mogelijk) omzetten
    }

    return $array;
}

/**
 * Geeft de keys van een iterator in een array. Net als array_key(), maar dan voor iterators.
 *
 * @see array_keys()
 *
 * @param Iterator|array $iterator
 *
 * @return array
 */
function iterator_keys($iterator)
{
    if (is_array($iterator)) {
        return array_keys($iterator);
    }
    if (func_num_args() > 1) {
        throw new Exception('$search_value not implemented');
    }
    if (!is_object($iterator) || !($iterator instanceof Iterator)) {
        \Sledgehammer\warning('The first argument should be an Iterator object');
    }
    $keys = [];
    for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
        $keys[] = $iterator->key();
    }

    return $keys;
}

/**
 * Hiermee kun je de timeout van het script verlengen.
 * Bij $relative op true zal de timeout aanvuld worden zodat het script nog minimaal n seconden mag draaien.
 * (De reeds ingestelde timeout zal nooit ingekort worden)
 * Bij $relative op false (absolute) zullen de $seconden bij de huidige timeout worden opgeteld.
 *
 * @param int $seconds
 * @param int $relative
 */
function extend_time_limit($seconds, $relative = false)
{
    $currentLimit = ini_get('max_execution_time');
    if ($currentLimit == 0) {
        return;
    }
    if ($relative) {
        $elapsed = ceil(microtime(true) - START);
        if (($elapsed + $seconds) > $currentLimit) { // Is de berekende timeout groter dan de huidige?
            set_time_limit($elapsed + $seconds);
        }
    } else {
        set_time_limit($currentLimit + $seconds);
    }
}

/**
 * Prepends the $path to the include path.
 *
 * @param string $path
 */
function extend_include_path($path)
{
    if (in_array(substr($path, -1), array('/', '\\'))) {
        $path = substr($path, 0, -1);
    }
    $paths = array('.', $path);
    foreach (explode(PATH_SEPARATOR, get_include_path()) as $dir) {
        if ($dir !== '.' && $dir !== $path) {
            $paths[] = $dir;
        }
    }
    set_include_path(implode(PATH_SEPARATOR, $paths));
}

/**
 * Deze functie geeft de extentie van de bestandnaam terug.
 *
 * Voorbeelden:
 *   $filename          $extension  $file
 *   ".htaccess"         null       ".htaccess"
 *   "index.html"       "html"      "index"
 *   "game.PART001.rar" "rar"       "game.PART001"
 *
 * @param $filename De bestandsaam
 * @param $filename_without_extention  Deze reference word ingesteld met de bestandsnaam, maar dan zonder extensie
 *
 * @return string De extensie
 */
function file_extension($filename, &$filename_without_extention = null)
{
    if (preg_match('/^([.]*.+)\.([^.]+)$/', $filename, $parts)) {
        $filename_without_extention = $parts[1];

        return $parts[2];
    }
    $filename_without_extention = $filename;

    return; // Deze $filename heeft geen extensie
}

/**
 * Retrieve browser and OS info.
 *
 * @param null|string $part Het deel van de info welke gevraagd wordt("name", "version" of "os"), bij null krijg je een array met alle gegevens
 *
 * @return string|array array (
 *                      'name'=> $browser,
 *                      'version'=> $version,
 *                      'os'=> $os,
 *                      );
 */
function browser($part = null)
{
    // browser
    $version = '';
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        $browser = 'php-' . php_sapi_name();
    } elseif (preg_match('/MSIE ([0-9]{1,2}.[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Microsoft Internet Explorer';
        $version = $match[1];
    } elseif (preg_match('/Trident\\/[0-9]{1,2}.[0-9]{1,2}; rv:([0-9]{1,2}.[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Microsoft Internet Explorer';
        $version = $match[1];
    } elseif (preg_match('/Opera\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Opera';
        $version = $match[1];
    } elseif (preg_match('/Safari\/([0-9]{3}.[0-9]{1})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Safari';
        $version = $match[1];
        if (preg_match('/Version\/([0-9.]+)/', $_SERVER['HTTP_USER_AGENT'], $match)) { // Is er naast het revisienummer ook een versie nummer?
            $version = $match[1]; // Gebruik het versie nummer
        }
    } elseif (preg_match('/Camino\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Camino';
        $version = $match[1];
    } elseif (preg_match('/Firefox\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Mozilla Firefox';
        $version = $match[1];
    } elseif (preg_match('/Mozilla\/([0-9].[0-9]{1,2})/', $_SERVER['HTTP_USER_AGENT'], $match)) {
        $browser = 'Mozilla';
        $version = $match[1];
    } else {
        $browser = $_SERVER['HTTP_USER_AGENT'];
    }
    // Operating system
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        $os = PHP_OS;
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Win')) {
        $os = 'Windows';
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone OS')) {
        $os = 'iPhone OS';
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'OS X')) {
        $os = 'Apple OS X';
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Mac')) {
        $os = 'Macintosh';
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Linux')) {
        $os = 'Linux';
    } elseif (strstr($_SERVER['HTTP_USER_AGENT'], 'Unix')) {
        $os = 'Unix';
    } else {
        $os = 'Overig';
    }
    $info = array(
        'name' => $browser,
        'version' => $version,
        'os' => $os,
    );
    if ($part === null) { // Return all info?
        return $info;
    }
    if (isset($info[$part])) {
        return $info[$part];
    }
    \Sledgehammer\notice('Unexpected part: "' . $part . '", expecting: "' . implode('", "', array_keys($info)) . '"');
}

/**
 * Vraagt het IP adres van de client op.
 *
 * Deze methode biedt MINDER beveiling dan de $_SERVER['REMOTE_ADDR'] en is makkelijk te spoofen,
 * Maar in een (reverse) proxy of load-balanced situatie geeft de $_SERVER['REMOTE_ADDR'] het de IP van de load-balancer.
 * En ben je dus afhankelijk van de HTTP_ vars.
 *
 * @return IP
 */
function getClientIp()
{
    $fields = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    $ips = [];
    foreach ($fields as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ips[] = trim($ip);
            }
        }
    }
    if (function_exists('filter_var') == false) { // IP validatie kan pas vanaf php 5.2
        return $ips[0]; // Gebruik dan de eerste.
    }
    $flags = FILTER_FLAG_NO_RES_RANGE; // Geen 127.0.0.1 of 169.254.x.x IPs
    if (\Sledgehammer\ENVIRONMENT != 'development') {
        $flags = $flags | FILTER_FLAG_NO_PRIV_RANGE; // 192.168.x.x niet toestaan. (Tenzij in development modes)
    }
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            \Sledgehammer\notice('Invalid IP: "' . $ip . '"');
        } else {
            return $ip;
        }
    }
    \Sledgehammer\warning('No valid client IP detected', array('IPs' => $ips));

    return $ips[0]; // Gebruik dan de eerste.
}

/**
 * De HTTP headers versturen.
 *
 * @param array $headers Example: array('Location' => 'http://example.com')
 */
function send_headers($headers)
{
    if (count($headers) == 0) { // sending nothing?
        return;
    }
    if (headers_sent($file, $line)) {
        if ($file == '' && $line == 0) {
            $location = '';
        } else {
            $location = ', output started in ' . $file . ' on line ' . $line;
        }
        if (class_exists('Sledgehammer\Debug\ErrorHandler', false)) {
            \Sledgehammer\notice('Couldn\'t sent header(s)' . $location, array('headers' => $headers));
        } else {
            trigger_error('Couldn\'t sent header(s) "' . \Sledgehammer\human_implode(' and ', $headers, '", "') . '"' . $location, E_USER_NOTICE);
        }

        return;
    }
    // Send headers.
    $notices = [];
    foreach ($headers as $header => $value) {
        if ($header == 'Status') { // and != fastcgi?
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $value);
        } elseif (is_numeric($header)) {
            $notices[] = 'Invalid HTTP header: "' . $header . ': ' . $value . '"';
        } else {
            header($header . ': ' . $value);
        }
    }
    foreach ($notices as $notice) {
        \Sledgehammer\notice($notice, 'Use $headers format: array("Content-Type" => "text/css")');
    }
}

/**
 * Sends the contents of the file including appropriate headers.
 * Zal na het succesvol versturen van het bestand het script stoppen "exit()".
 *
 * @param string $filename Filename including path.
 *
 * @throws Exception on Failure
 */
function render_file($filename)
{
    $last_modified = filemtime($filename);
    if ($last_modified === false) {
        throw new Exception('Modify date unknown');
    }
    if (array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)) {
        $if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
        if ($if_modified_since >= $last_modified) { // Is the Cached version the most recent?
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            exit();
        }
    }
    $headers = [];
    /*
      $resume_support = false; // @todo Bestanden in tmp/ geen resume_support geven.
      if ($resume_support) {
      $headers[] = 'Accept-Ranges: bytes';
      } */
    if (is_dir($filename)) {
        throw new Exception('Unable to render_file(). "' . $filename . '" is a folder');
    }
    $headers['Content-Type'] = mimetype($filename);
    $headers['Last-Modified'] = gmdate('r', $last_modified);
    $filesize = filesize($filename);
    if ($filesize === false) {
        throw new Exception('Filesize unknown');
    }
    if (empty($_SERVER['HTTP_RANGE'])) {
        $headers['Content-Length'] = $filesize; // @todo Detectie inbouwen voor bestanden groter dan 2GiB, deze geven fouten.
        \Sledgehammer\send_headers($headers);
        // Output buffers uitschakelen, anders zal readfile heb bestand in het geheugen inladen. (en mogelijk de memory_limit overschrijden)
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $success = readfile($filename); // Het gehele bestand sturen
        if ($success) {
            exit();
        }
        throw new Exception('readfile() failed');
    } else {
        // Het gehele bestand sturen (resume support is untested)
        $success = readfile($filename);
        if ($success) {
            exit();
        }
        throw new Exception('readfile() failed');
        // #########################################
        // Onderstaande CODE wordt nooit uitgevoerd.
        // Een gedeelte van het bestand sturen
        //check if http_range is sent by browser (or download manager)
        list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        $range = '';
        if ($size_unit == 'bytes') {
            //multiple ranges could be specified at the same time, but for simplicity only serve the first range
            //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
            list($range, $extra_ranges) = explode(',', $range_orig, 2);
        }
        // Figure out download piece from range (if set)
        list($seek_start, $seek_end) = explode('-', $range, 2);
    }

    // set start and end based on range (if set), else set defaults
    // also check for invalid ranges.
    $seek_end = (empty($seek_end)) ? ($filesize - 1) : min(abs(intval($seek_end)), ($filesize - 1));
    $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

    // Only send partial content header if downloading a piece of the file (IE workaround)
    if ($seek_start > 0 || $seek_end < ($filesize - 1)) {
        header('HTTP/1.1 206 Partial Content');
    }
    $headers[] = 'Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $filesize;
    $headers[] = 'Content-Length: ' . ($seek_end - $seek_start + 1);

    $fp = fopen($filename, 'rb');
    if (!$fp) {
        return false;
    }
    fseek($fp, $seek_start); // seek to start of missing part
    set_time_limit(0);
    \Sledgehammer\send_headers($headers);
    while (!feof($fp)) {
        echo fread($fp, 1024 * 16); // Verstuur in blokken van 16KiB
        flush();
    }

    return fclose($fp);
}

/**
 * Mappen en bestandsnaam in de url corrigeren
 * Geen " " maar "%20" enz.
 *
 * @param string $path
 *
 * @return string
 */
function urlencode_path($path)
{
    $escaped_path = rawurlencode($path);

    return str_replace('%2F', '/', $escaped_path); // De "/" weer terugzetten
}

/**
 * Convert all applicable characters to numeric HTML/XML entities
 * Similar to htmlentities() but also converts "☂" (umbrella) to "&#9730;", including emoticons.
 *
 * @param string $string  UTF8 string
 * @param string $charset string: The charset of $string, defaults to Framework::$charset
 *
 * @return string
 */
function xmlentities($string, $charset = null)
{
    if ($charset === null) {
        $charset = Framework::$charset;
    }

    return mb_encode_numericentity($string, array(0x0080, 0xfffff, 0, 0xfffff), $charset);
}

/**
 * Genereer aan de hand van de $identifier een (meestal) uniek id.
 *
 * @param string $identifier
 *
 * @return int
 */
function sem_key($identifier)
{
    $md5 = md5($identifier);
    $key = 0;
    for ($i = 0; $i < 32; ++$i) {
        $key += ord($md5[$i]) * $i;
    }

    return $key;
}

/**
 * Write an ini file, compatible with parse_ini_file().
 *
 * @param string $filename
 * @param array  $array
 * @param string $comment  (optional)
 */
function write_ini_file($filename, $array, $comment = null)
{
    $ini = ($comment !== null) ? '; ' . $comment . "\n\n" : '';
    $usingSections = false;
    foreach ($array as $name => $value) {
        if (is_array($value)) {
            $usingSections = true;
        } else {
            $ini .= $name . ' = ' . $value . "\n"; // @todo Escape
        }
    }
    // Write [section] values
    if ($usingSections) {
        foreach ($array as $section => $values) {
            if (is_array($values)) {
                $ini .= "\n[" . $section . "]\n";
                foreach ($values as $name => $value) {
                    $ini .= $name . ' = ' . $value . "\n"; // @todo Escape
                }
            }
        }
    }

    return file_put_contents($filename, $ini);
}

/**
 * Return the output of a shell command that is run as another user.
 *
 * @param string $username
 * @param string $password
 * @param string $command    The command that will be executed.
 * @param int    $return_var If the parameter is set, the return status of the Unix command will be placed here.
 *
 * @return string The output of the command
 */
function su_exec($username, $password, $command, &$return_var = null)
{
    ob_start();
    $return_var = sudo($username, $password, $command);

    return rtrim(ob_get_clean(), "\n\r");
}

/**
 * Execute a shell command as another user.
 *
 * @see su_exec
 *
 * @param string $username
 * @param string $password
 * @param string $command  The command that will be executed.
 *
 * @return int The return status of the Unix command will be placed here.
 */
function sudo($username, $password, $command)
{
    $descriptorspec = array(
        0 => array('pipe', 'r'), // stdin
        1 => array('pipe', 'w'), // stdout
        2 => array('pipe', 'w'), // stderr
    );

    /* @var $process resource */
    $process = proc_open('expect', $descriptorspec, $pipes, null, null);

    if ($process === false) {
        \Sledgehammer\warning('Failed to run expect');

        return;
    }
    /* @var $stdin resource The input stream of the php process (write) */
    $stdin = $pipes[0];
    /* @var $stdout resource The output stream of the php process (read) */
    $stdout = $pipes[1];
    /* @var $stderr resource The error stream of the php process (read) */
    $stderr = $pipes[2];

    // Generate expect script
    fwrite($stdin, '
set env(PS1) "# "
spawn su ' . addslashes($username) . ' -c "' . addslashes($command) . '"
expect "Password:" { send "' . addslashes($password) . '\r" }
expect eof
catch wait result
exit [lindex $result 3]');
    fclose($stdin);
    $showOutput = false;
    while (!feof($stdout) && !feof($stderr)) {
        $read = array($stdout, $stderr);
        if (stream_select($read, $write, $except, 30)) {
            foreach ($read as $stream) {
                $output = fgets($stream);
                if ($showOutput) {
                    echo $output;
                }
                if ($output === "Password:\r\n") {
                    $showOutput = true;
                }
            }
        }
    }
    fclose($stdout);
    fclose($stderr);

    return proc_close($process);
}

/**
 * Shorthand for creating an SQL object and selecting columns.
 * Allows direct method-chaining:
 *   select('*')->from('table')->where('column = "value"');.
 *
 * @param array|string $columns
 *                              param string ...
 *
 * @return Sql
 */
function select($columns)
{
    if (func_num_args() > 1) {
        $columns = func_get_args();
    }
    $sql = new Sql();

    return $sql->select($columns);
}

/**
 * Shorthand for creating an Text object
 * Allows direct method-chaining:
 *   text('my text')->toUpper()->trim();.
 *
 * @param string       $text
 * @param string|array $charset string: The charset of $text; array: Autodetect encoding, example: array('ASCII', 'UTF-8', 'ISO-8859-15'); null: defaults to Framework::$charset
 *
 * @return Text (__toString is alway UTF-8 encoded).
 */
function text($text, $charset = null)
{
    return new Text($text, $charset);
}

/**
 * Shorthand for creating an Collection object
 * Allows direct method-chaining:
 *   collection($array)->select('name');.
 *
 * @param Traversable|array $traversable
 *
 * @return Collection
 */
function collection($traversable)
{
    return new Collection($traversable);
}

/**
 * Returns the cached value when valid cache entry was found. otherwise retrieves the value via the $closure, stores it in the cache and returns it.
 *
 * @param string           $path    The Protoerty path to the cache node in the caching graph.
 * @param string|int|array $options A string or int is interpreted as a 'expires' option.
 *                                  array(
 *                                  'max_age' => int|string // The entry must be newer than the $maxAge. Example: "-5min", "2012-01-01"
 *                                  'expires' => int|string, // A string is parsed via strtotime(). Examples: '+5min' or '2020-01-01' int's larger than 3600 (1 hour) are interpreted as unix timestamp expire date. And int's smaller or equal to 3600 are interpreted used as ttl.
 *                                  'forever' => bool, // Default false (When true no )
 *                                  'lock' => (bool) // Default true, Prevents a cache stampede (http://en.wikipedia.org/wiki/Cache_stampede)
 *                                  )
 * @param callable         $closure The method to retrieve/calculate the value.
 *
 * @return mixed
 */
function cache($path, $options, $closure)
{
    $cache = PropertyPath::get($path, Cache::rootNode());

    return $cache->value($options, $closure);
}

/**
 * Creates a version of the function that can only be called one time.
 * Repeated calls to the returned closure will have no effect, returning the value from the original call.
 *
 * @link http://underscorejs.org/#once
 *
 * @param callable $callback
 *
 * @return Closure
 */
function once($callback)
{
    if (is_callable($callback) == false) {
        throw new Exception('Unexpected value for $callback, expecting a callable');
    }
    $called = false;
    $retval = null;

    return function () use ($callback, &$called, &$retval) {
        if ($called) {
            return $retval;
        }
        $retval = call_user_func_array($callback, func_get_args());
        $called = true;

        return $retval;
    };
}

function getDatabase($link = 'default')
{
    \Sledgehammer\deprecated('\\Sledgehammer\\getDatabase() is deprecated in favor of Connection::instance()');

    return Connection::instance($link);
}
