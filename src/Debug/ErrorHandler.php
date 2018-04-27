<?php

namespace Sledgehammer\Core\Debug;

use Exception;
use Sledgehammer\Core\Framework;
use Sledgehammer\Core\InfoException;
use Sledgehammer\Core\Base;
use Throwable;

/**
 * Improved errorhandling for php notices, warnings, errors and uncaught exceptions.
 *
 * html - Nice report with full backtrace.
 * email - Send report as e-mail
 * debugR - Report error from inside a XHR request.
 * log - Add record the the error_log
 * cli - similar to normal error output
 */
class ErrorHandler extends Base
{
    use \Sledgehammer\Core\Singleton;

    /**
     * Schrijf de fout ook weg naar het php errorlog (/var/log/httpd/error.log?).
     *
     * @var bool
     */
    public $log = true;

    /**
     * echo de foutmelding zonder extras' met een timestamp, (php cli.php > error.log).
     *
     * @var bool
     */
    public $cli = false;

    /**
     * echo de foutmelding en extra informatie met html opmaak.
     *
     * @var bool
     */
    public $html = false;

    /**
     * Allow the ErrorHandler to set a "500 Internal Server Error".
     *
     * @var bool
     */
    public $headers = false;

    /**
     * Email de foutmelding naar dit emailadres.
     *
     * @var string
     */
    public $email = false;

    /**
     * Send the errormessage as DebugR header (if headers aren't already sent).
     *
     * @var bool
     */
    public $debugR = false;

    // Limiet aan het aantal email dat de ErrorHandler verstuurd.
    // Bij waardes zoals false ("", 0, null) is er GEEN limiet

    /**
     * Het aantal fouten dat gemaild gedurende 1 php script.
     *
     * @var int|"NO_LIMIT"
     */
    public $emails_per_request = 'NO_LIMIT';

    /**
     * Het aantal fouten dat gemaild mag worden per minuut.
     *
     * @var int|"NO_LIMIT"
     */
    public $emails_per_minute = 'NO_LIMIT';

    /**
     * Het aantal fouten dat gemaild mag worden per dag.
     *
     * @var int|"NO_LIMIT"
     */
    public $emails_per_day = 'NO_LIMIT';

    /**
     * Limit the amount of errors with a full backtrace. Setting this value too high may cause browser crashes. (The limit is measured per error-type).
     *
     * @var int
     */
    public $detail_limit = 50;

    /**
     * Keep track of the $detail_limit's.
     *
     * @var array
     */
    private $limits = [];

    /**
     * Some error levels can't be caught or triggered directly, but could be retrieved with error_get_last()
     * error-type to title/color/icon mapping.
     *
     * @var array
     */
    private $error_types = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Error',
        E_CORE_WARNING => 'Warning',
        E_COMPILE_ERROR => 'Error',
        E_COMPILE_WARNING => 'Warning',
        E_USER_ERROR => 'Error',
        E_USER_WARNING => 'Warning',
        E_USER_NOTICE => 'Notice',
        E_STRICT => 'PHP5_Strict',
        E_RECOVERABLE_ERROR => 'RecoverableError',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'Deprecated',
        'EXCEPTION' => 'Exception',
    ];

    /**
     * Show at maximun 50 KiB per argument in the backtrace.
     *
     * @var int
     */
    private $maxStringLengthBacktrace = 51200;

    /**
     * Wordt gebruikt voor het bepalen van fouten tijdens de error reporting.
     *
     * @var bool
     */
    private $isProcessing = false;

    /**
     * Deze ErrorHandler instellen voor het afhandelen van de errormeldingen.
     */
    public static function enable()
    {
        $handler = self::instance();
        set_error_handler([$handler, 'errorCallback']);
        set_exception_handler([$handler, 'exceptionCallback']);
        register_shutdown_function([$handler, 'shutdownCallback']);
    }

    /**
     * Callback for handling PHP and trigger_error() errors.
     *
     * @param int         $type
     * @param string      $message
     * @param string|null $filename
     * @param int|null    $line
     * @param array|null  $context
     */
    public function errorCallback($type, $message, $filename = null, $line = null, $context = null)
    {
        $this->report($type, $message);
        if ($type == E_USER_ERROR || $type == E_RECOVERABLE_ERROR) {
            exit(1);
        }
    }

    /**
     * Callback voor exceptions die buiten een try/catch block ge-throw-t worden.
     *
     * @param Exception $exception
     */
    public function exceptionCallback($exception)
    {
        if (self::isThrowable($exception)) {
            if (count(debug_backtrace()) == 1) { // An uncaught exception? via the set_exception_handler()
                self::instance()->report($exception, '__UNCAUGHT_EXCEPTION__');
            } else {
                \Sledgehammer\notice('Only the set_exception_handler() should call ErrorHandler->exceptionCallback. use \Sledgehammer\report_exception()', 'Use the <b>report_exception</b>($exception) for reporting to the default Errorhandler.<br />Or call the ErrorHander->report($exception) to target a specific instance.');
                self::instance()->report($exception);
            }
        } else {
            self::report(E_USER_ERROR, 'Parameter $exception must be an Exception, instead of a '.gettype($exception));
        }
    }

    /**
     * Use the shutdown to handle fatal errors. E_ERROR.
     */
    public function shutdownCallback()
    {
        $error = error_get_last();
        if ($error !== null && $error['type'] === E_ERROR) {
            $this->report($error['type'], $error['message']);
        }
    }

    /**
     * Functie die wordt aangeroepen door de functies \Sledgehammer\notice() / \Sledgehammer\warning() / error() en de error handler functie.
     *
     * @param int|Exception $type
     * @param string        $message
     * @param mixed         $information
     * @param bool          $check_for_alternate_error_handler Controleert of de error door een andere error_handler moet worden afgehandeld (Bv: SimpleTestErrorHandler)
     */
    public function report($type, $message = '__EMPTY_ERROR_MESSAGE__', $information = null, $check_for_alternate_error_handler = false)
    {
        if (is_int($type)) {
            $errorLevel = error_reporting();
            if ($errorLevel !== ($errorLevel | $type)) { // Don't report this error?
                return;
            }
        }
        if (self::isThrowable($type)) {
            // @todo check the exception handler is this instance.
        } elseif ($check_for_alternate_error_handler) {
            $callback = set_error_handler([$this, 'errorCallback']);
            restore_error_handler();
            if (is_array($callback) == false || $callback[0] !== $this) {
                $conversion_table = [
                    E_ERROR => E_USER_ERROR,
                    E_WARNING => E_USER_WARNING,
                    E_NOTICE => E_USER_NOTICE,
                    E_DEPRECATED => E_USER_DEPRECATED,
                ];
                if (array_key_exists($type, $conversion_table)) {
                    $type = $conversion_table[$type]; // Voorkom "Invalid error type specified" bij trigger_error
                }
                trigger_error($message, $type); // De fout doorgeven aan de andere error handler
                return;
            }
        }
        $this->process($type, $message, $information);
    }

    /**
     * De error van html-opmaak voorzien.
     *
     * @param int|Exception $type
     * @param string        $message
     * @param mixed         $information
     */
    private function render($type, $message = null, $information = null)
    {
        echo "<!-- \"'> -->\n"; // break out of the tag/attribute
        if (self::isThrowable($type)) {
            $exception = $type;
            $type = ($message === '__UNCAUGHT_EXCEPTION__' ? E_ERROR : E_WARNING);
            if ($exception instanceof InfoException) {
                $information = $exception->getInformation();
            }
            $message = $exception->getMessage();
        } else {
            $exception = false;
        }
        switch ($type) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $offset = ' -52px';
                $label_color = '#3a77cd'; // blue
                $message_color = '#3a77cd';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $offset = ' -26px';
                $label_color = '#f89406'; // orange
                $message_color = '#d46d11';
                break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $offset = ' -78px';
                $label_color = '#999'; // gray
                $message_color = '#333';
                break;
            case E_STRICT:
                $offset = ' -104px';
                $label_color = '#777bb4'; // purple
                $message_color = '#50537f';
                break;
            default:
                $offset = '';
                $label_color = '#c94a48'; // red
                $message_color = '#c00';
        }

        // Determine if a full report should be rendered
        $showDetails = true;
        if ($this->detail_limit !== 'NO_LIMIT') {
            if (isset($this->limits['backtrace'][$type]) === false) {
                $this->limits['backtrace'][$type] = $this->detail_limit;
            }
            if ($this->limits['backtrace'][$type] === 0) {
                $showDetails = false;
            } else {
                --$this->limits['backtrace'][$type];
            }
        }
        $style = [
            'padding: 10px 15px 15px 15px',
            'background-color: #fcf8e3',
            'color: #333',
            'font: 12px/1.25 \'Helvetica Neue\', Helvetica, sans-serif',
            // reset styling
            'text-shadow: none',
            '-webkit-font-smoothing: antialiased',
            'font-smoothing: antialiased',
            'text-align: left',
            'overflow-x: auto',
            'white-space: normal',
        ];
        if ($showDetails === false) {
            $style[] = 'padding-top: 14px';
        }
        if (!$this->email) {
            $style[] = 'border: 1px solid #eeb; border-radius: 4px; margin: 15px 5px 18px 5px';
        }
        echo '<div style="', implode(';', $style), '">';
        if ($showDetails) {
            echo '<span style="display:inline-block; width: 26px; height: 26px; vertical-align: middle; margin: 0 6px 2px 0; background: url(\'https://rawgit.com/sledgehammer/core/master/public/img/ErrorHandler.png\')'.$offset.'"></span>';
        }
        echo '<span style="font-size:13px; text-shadow: 0 1px 0 #fff;color:', $message_color, '">';
        if (is_array($message)) {
            $message = 'Array';
        }

        if ($information === null && strpos($message, 'Missing argument ') === 0) {
            $information = $this->resolveFunction($message); // informatie tonen over welke parameters er verwacht worden.
        }
        $message_plain = $message;
        if (strpos($message, '<span style="color:') === false) { // Alleen html van de syntax_highlight functie toestaan, alle overige htmlkarakers escapen
            $message_plain = htmlspecialchars($message_plain); // Vertaal alle html karakters
        }
        if ($showDetails) {
            $label_style = '';
        } else {
            $label_style = [
                'padding: 1px 3px 2px',
                'font-size: 10px',
                'line-height: 15px',
                'text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25)',
                'color: #fff',
                'vertical-align: middle',
                'white-space: nowrap',
                'background-color: '.$label_color,
                'border-radius: 3px',
            ];
            $label_style = ' style="'.implode(';', $label_style).'"';
        }
        echo '<b'.$label_style.'>';
        if ($exception) {
            if ($type == E_ERROR) {
                echo 'Uncaught ';
            }
            echo get_class($exception);
        } else {
            echo $this->error_types[$type];
        }
        echo "</b>&nbsp;\n\n\t", $message_plain, "\n\n</span>";

        if ($showDetails || $this->email) {
            echo '<hr style="height: 1px; background: #eeb; border: 0;margin: 7px 0px 12px -1px;" />';
            if ($information !== null && !empty($information)) {
                echo "<b>Extra information</b><br />\n<span style='color:#007700'>";
                if (is_array($information)) {
                    $this->renderArray($information);
                } elseif (is_object($information)) {
                    echo \Sledgehammer\syntax_highlight($information), ":<br />\n";
                    $this->renderArray($information);
                } else {
                    echo $information, "<br />\n";
                }
                echo '</span>';
            }
            if ($this->email) { // email specifieke informatie blokken genereren?
                $this->renderBrowserInfo();
                $this->renderServerInfo();
            }
            $this->renderBacktrace();
            switch ($type) {
                case E_WARNING:
                case E_USER_ERROR:
                case E_USER_WARNING:
                    if (class_exists('Sledgehammer\Logger', false) && !empty(Logger::$instances)) {
                        echo '<br />';
                        foreach (Logger::$instances as $name => $logger) {
                            if ($logger->count === 0 && count($logger->entries) === 0) {
                                continue;
                            }
                            if ($this->email) {
                                echo '<b>', $name, '</b><br />';
                                $logger->render();
                            } else {
                                $logger->statusbar($name.': ');
                                echo "<br />\n";
                            }
                        }
                    }
                    break;
            }
        } else {
            $location = $this->resolveLocation();
            if ($location !== false) {
                echo '&nbsp;&nbsp; in <b>', $location['file'], '</b> on line <b>', $location['line'], '</b>';
            }
        }
        echo '</div>';
    }

    /**
     * De fout afhandelen volgens de afhandel opties (email, log, html).
     *
     * @param int    $type        E_USER_NOTICE, E_USER_ERROR, enz
     * @param string $message     De foutmelding
     * @param mixed  $information Extra informatie voor de fout. bv array('Tip' => 'Controleer ...')
     */
    private function process($type, $message, $information = null)
    {
        if ($this->isProcessing) {
            echo '<span style="color:red">[ErrorHandler failure]';
            if ($this->html && is_string($message)) { // show error?
                if ($message === '__UNCAUGHT_EXCEPTION__' && $this->isThrowable($type)) {
                    echo ' ', htmlentities($type->getMessage().' in '.$type->getFile().' on line '.$type->getLine());
                } else {
                    echo ' ', htmlentities($message);
                }
            }
            echo ' </span>';
            error_log('ErrorHandler failure: '.$message);

            return;
        }
        $this->isProcessing = true;
        if ($this->debugR) {
            $this->debugR = (headers_sent() === false); // Update debugr setting.
        }
        if ($this->log || $this->cli || $this->debugR) {
            if (self::isThrowable($type)) {
                $error_message = get_class($type).': '.$type->getMessage();
            } else {
                $error_message = $this->error_types[$type].': '.$message;
            }
            $location = $this->resolveLocation();
            if ($location) {
                $error_message .= ' in '.$location['file'].' on line '.$location['line'];
            }
            if ($this->log) {
                error_log($error_message);
            }
            if ($this->debugR) {
                if (class_exists('Sledgehammer\Core\Debug\DebugR', false) === false) {
                    require_once __DIR__.'/DebugR.php';
                }
                if (class_exists('Sledgehammer\Core\Json', false) === false) {
                    require_once __DIR__.'/../Json.php';
                }
                if (self::isThrowable($type) || in_array($type, [E_USER_ERROR, E_ERROR, 'EXCEPTION'])) {
                    DebugR::error($error_message);
                } else {
                    DebugR::warning($error_message);
                }
            }
        }
        // Limiet contoleren
        if ($this->email) { // Een foutrapport via email versturen?
            $limit = $this->importEmailLimit();
            if ($limit < 1) { // Is de limiet bereikt?
                $this->email = false; // Niet emailen
            }
        }
        $buffer = ($this->email || $this->debugR);
        if ($buffer) { // store the html of the error-report in a buffer
            ob_start();
        } elseif (!$this->html) { // No html version required?
            $this->isProcessing = false;

            return; // Processing is complete
        }
        self::render($type, $message, $information);
        if ($buffer) {
            $html = ob_get_clean();
            if ($this->debugR) {
                DebugR::html($html);
            }
            if ($this->email) { // email versturen?
                // Headers voor de email met HTML indeling
                $headers = "MIME-Version: 1.0\n";
                $headers .= 'Content-type: text/html; charset='.Framework::$charset."\n";
                $headers .= 'From: '.$this->fromEmail()."\n";

                if (self::isThrowable($type)) {
                    $subject = get_class($type).': '.$type->getMessage();
                    if ($message === '__UNCAUGHT_EXCEPTION__') {
                        $subject = ' Uncaught '.$subject;
                    }
                } else {
                    $subject = $this->error_types[$type].': '.$message;
                }

                if (function_exists('mail') && !mail($this->email, $subject, '<html><body style="background-color: #fcf8e3">'.$html."</body></html>\n", $headers)) {
                    error_log('The '.self::class.' was unable to email the report.');
                }
            }
            if ($this->html) {
                if ($this->headers && headers_sent() === false) {
                    if (function_exists('http_response_code')) { // PHP 5.4
                        if (http_response_code() === 200) {
                            http_response_code(500);
                        }
                    }
                    header('Content-Type: text/html; charset='.Framework::$charset);
                }
                echo $html; // buffer weergeven
                if (ob_get_level() === 0) {
                    flush();
                }
            }
        }

        if ($this->cli) {
            echo '[', date('Y-m-d H:i:s'), '] ', $error_message, "\n";
        }

        $this->isProcessing = false;
    }

    /**
     * Returns the filename and linenumber where the error was triggered.
     *
     * @return array|false array('file' => $filename, 'line' => $linenumber)
     */
    private function resolveLocation()
    {
        $backtrace = debug_backtrace();
        foreach ($backtrace as $call) {
            if (isset($call['file']) && $call['file'] !== __FILE__ && $call['function'] !== 'report') {
                return [
                    'file' => ((strpos($call['file'], \Sledgehammer\PATH) === 0) ? substr($call['file'], strlen(\Sledgehammer\PATH)) : $call['file']),
                    'line' => $call['line'],
                ];
            }
        }

        return false;
    }

    /**
     * De bestanden, regelnummers, functie en objectnamen waar de fout optrad weergeven.
     */
    private function renderBacktrace()
    {
        $backtrace = debug_backtrace();
        echo "<b>Backtrace</b><div>\n";
        // Pass 1:
        //   Backtrace binnen de errorhandler niet tonen
        //   Bij een Exception de $Exception->getTrace() tonen.
        //   De plaats van de errror extra benadrukken (door een extra witregel)
        $helperPath = dirname(dirname(__DIR__)).'/helpers.php';
        while ($call = next($backtrace)) {
            if (isset($call['object']) && $call['object'] === $this) {
                if ($call['function'] == 'errorCallback') { // Is de fout getriggerd door php
                    if (isset($call['file'])) { // Zit de fout niet in een functie?
                        $this->renderBacktraceCall($call, true); // Dan is de fout afkomsting van deze $call, maar verberg de ErrorHandler->errorCallback parameters
                        next($backtrace);
                        break;
                    }   // De fout komt uit een functie (bijvoorbeeld een Permission denied uit een fopen())
                        $call = next($backtrace); // Sla deze $call over deze bevat alleen de ErrorHandler->errorCallback() aanroep
                        $this->renderBacktraceCall($call);
                    next($backtrace);
                    break;
                }
                if ($call['function'] == 'shutdown_callback') {
                    $error = error_get_last();
                    $this->renderBacktraceCall($error);
                    next($backtrace);
                    break;
                }
                if ($call['function'] == 'report' && self::isThrowable($call['args'][0])) { // Gaat het om een Exception
                    $exception = $call['args'][0];
                    $reported = false;
                    $viaExceptionCallback = next($backtrace);
                    if (isset($viaExceptionCallback['function']) && $viaExceptionCallback['function'] === 'exceptionCallback') {
                        $caught = next($backtrace);
                        if ($caught !== false) { // backtrace after the callback?
                            $reported = true; // someone called exceptionCallback() directly
                        }
                    } else {
                        $reported = true;
                        if (isset($viaExceptionCallback['function']) && $viaExceptionCallback['function'] === 'report_exception' && empty($viaExceptionCallback['class'])) {
                            $call = $viaExceptionCallback; // skip the report_exception() call
                        }
                    }
                    echo 'Exception thrown ';
                    $trace = [
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ];
                    $this->renderBacktraceCall($trace, true);

                    $previous = $exception->getPrevious();
                    if ($reported || $previous !== null) {
                        echo '<span style="color:#aaa;margin-left:20px"'; //d5ac68
                        if ($previous !== null) {
                            echo 'title=', htmlentities($previous->getTraceAsString(), ENT_COMPAT, 'UTF-8').'">Previous ';
                            echo get_class($previous), '("'.$previous->getMessage(), '") was thrown';
                            $trace = [
                                'file' => $previous->getFile(),
                                'line' => $previous->getLine(),
                            ];
                            $this->renderBacktraceCall($trace, true);
                        } else {
                            echo '>';
                        }
                        if ($reported) {
                            echo 'Reported ';
                            $this->renderBacktraceCall($call, true);
                        }
                        echo '</span>';
                    }
                    $backtrace = $exception->getTrace(); // Show the trace of the exception
                    break;
                }
            } else {
                if (isset($call['file']) && $call['file'] === $helperPath) { // Forwarded via a helper?
                    $call = next($backtrace);
                }
                $this->renderBacktraceCall($call);
                next($backtrace);
                break;
            }
        }
        echo '<br />';
        // Pass 2: Alle elementen weergeven uit de $backtrace die nog niet behandeld zijn
        while ($call = current($backtrace)) {
            $this->renderBacktraceCall($call);
            next($backtrace);
        }
        echo '</div>';
    }

    /**
     * Render a call from the backtrace with syntax highlighting.
     *
     * @param array $call
     * @param bool  $location_only
     */
    private function renderBacktraceCall($call, $location_only = false)
    {
        if (!$location_only) {
            if (isset($call['object'])) {
                echo \Sledgehammer\syntax_highlight($call['object'], null, 512);
                echo \Sledgehammer\syntax_highlight($call['type'], 'operator');
            } elseif (isset($call['class'])) {
                echo \Sledgehammer\syntax_highlight($call['class'], 'class');
                echo \Sledgehammer\syntax_highlight($call['type'], 'operator');
            }
            if (isset($call['function'])) {
                echo \Sledgehammer\syntax_highlight($call['function'], 'method');
                $errorHandlerInvocations = ['errorCallback', 'trigger_error', 'error', 'warning', 'notice', 'deprecated', 'Sledgehammer\error', 'Sledgehammer\warning', 'Sledgehammer\notice', 'Sledgehammer\deprecated'];
                $databaseClasses = ['PDO', 'Sledgehammer\Database', 'mysqli']; // prevent showing/mailing passwords in the backtrace.
                $databaseFunctions = ['mysql_connect', 'mysql_pconnect', 'mysqli_connect', 'mysqli_pconnect'];
                if (in_array($call['function'], array_merge($errorHandlerInvocations, $databaseFunctions)) || (in_array(@$call['class'], $databaseClasses) && in_array($call['function'], ['connect', '__construct'])) || (in_array($call['function'], ['call_user_func', 'call_user_func_array']) && in_array($call['args'][0], $errorHandlerInvocations))) {
                    echo '(&hellip;)';
                } else {
                    echo '(';
                    if (isset($call['args'])) {
                        $first = true;
                        foreach ($call['args'] as $arg) {
                            if ($first) {
                                $first = false;
                            } else {
                                echo ', ';
                            }
                            if (is_string($arg) && strlen($arg) > $this->maxStringLengthBacktrace) {
                                $kib = round((strlen($arg) - $this->maxStringLengthBacktrace) / 1024);
                                $arg = substr($arg, 0, $this->maxStringLengthBacktrace);
                                echo \Sledgehammer\syntax_highlight($arg), '<span style="color:red;">&hellip;', $kib, '&nbsp;KiB&nbsp;truncated</span>';
                            } else {
                                echo \Sledgehammer\syntax_highlight($arg, null, 1024);
                            }
                        }
                    }
                    echo ')';
                }
            }
        }
        if (!empty($call['file'])) {
            if (strpos($call['file'], \Sledgehammer\PATH) === 0) {
                echo ' in&nbsp;<b title="', htmlentities($call['file']), '">', substr($call['file'], strlen(\Sledgehammer\PATH)), '</b>'; // De bestandnaam opvragen en filteren.
            } else {
                echo ' in&nbsp;<b>', $call['file'], '</b>'; // De bestandnaam opvragen en filteren.
            }
        }
        if (isset($call['line'])) {
            echo ' on&nbsp;line&nbsp;<b>', $call['line'], '</b>';
        }
        echo "<br />\n";
    }

    /**
     * Een functie die de juiste functie syntax laat zien. ipv de standaard 'argument x is missing' melding.
     *
     * @param string $message error message.
     */
    private function resolveFunction($message)
    {
        preg_match('/[0-9]+/', $message, $argument);
        $argument = $argument[0] - 1;
        $location = debug_backtrace();
        for ($i = 0; $i < count($location); ++$i) {
            if ($location[$i]['file'] != __FILE__) {
                $file = file($location[$i]['file']);
                $function = $file[$location[$i]['line'] - 1];
                break;
            }
        }
        $function = preg_replace("/^[ \t]*(public |private |protected )function |\n/", '', $function);

        return ['Function' => $function];
    }

    /**
     * Gegevens over de omgeving van de client.
     */
    private function renderBrowserInfo()
    {
        $browser = \Sledgehammer\browser();
        echo "<div>\n";
        echo "<b>Client information</b><br />\n";
        if (isset($_SERVER['REQUEST_URI'])) {
            $href = (\Sledgehammer\value($_SERVER['HTTPS']) == 'on') ? 'https' : 'http';
            $href .= '://'.$_SERVER['SERVER_NAME'];
            if ($_SERVER['SERVER_PORT'] != 80) {
                $href .= ':'.$_SERVER['SERVER_PORT'];
            }
            $href .= $_SERVER['REQUEST_URI'];
            echo '<b>URI:</b> <a href="'.$href.'">'.$_SERVER['REQUEST_URI']."</a><br />\n";
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            echo '<b>Referer:</b> '.$_SERVER['HTTP_REFERER']."<br />\n";
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            echo '<b>IP:</b> '.$_SERVER['REMOTE_ADDR']."<br />\n";
        }
        $browser = \Sledgehammer\browser();
        echo '<b>Browser:</b> '.$browser['name'].' '.$browser['version'].' for '.$browser['os'].' - <em>'.\Sledgehammer\syntax_highlight(@$_SERVER['HTTP_USER_AGENT'])."</em><br />\n";
        echo '<b>Cookie:</b> '.\Sledgehammer\syntax_highlight(@count($_COOKIE) != 0)."<br />\n";
        echo '</div>';
    }

    /**
     * Gegevens over de server.
     */
    private function renderServerInfo()
    {
        echo "<div>\n";
        echo "<b>Server information</b><br />\n";
        echo '<b>Hostname:</b> ', php_uname('n'), "<br />\n";
        echo '<b>Environment:</b> ', \Sledgehammer\ENVIRONMENT, "<br />\n";
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            echo '<b>Software:</b> ', $_SERVER['SERVER_SOFTWARE'], "<br />\n";
        }
        echo '</div>';
    }

    /**
     * Een array printen met syntax highlighting.
     *
     * @param array $array
     */
    private function renderArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value) && count($value) != 0) {
                echo '<b>'.$key.':</b> array('."<br />\n";
                if (\Sledgehammer\is_indexed($value)) {
                    foreach ($value as $value2) {
                        echo '&nbsp;&nbsp;', \Sledgehammer\syntax_highlight($value2), "<br />\n";
                    }
                } else {
                    foreach ($value as $key2 => $value2) {
                        echo '&nbsp;&nbsp;'.\Sledgehammer\syntax_highlight($key2).' => ', \Sledgehammer\syntax_highlight($value2, null, 2048), "<br />\n";
                    }
                }
                echo ")<br />\n";
            } else {
                echo '<b>'.$key.':</b> ', \Sledgehammer\syntax_highlight($value, null, 2048), "<br />\n";
            }
        }
    }

    /**
     * Opvragen hoeveel emails er nog verstuurd mogen worden.
     *
     * @return false|int Retourneert the limiet
     */
    private function importEmailLimit()
    {
        // Waardes uit de configuratie corrigeren
        $this->emails_per_day = $this->mergeLimit($this->emails_per_day);
        $this->emails_per_minute = $this->mergeLimit($this->emails_per_minute);
        $this->emails_per_request = $this->mergeLimit($this->emails_per_request);
        //
        if ($this->emails_per_request !== 'NO_LIMIT') {
            $limit = $this->emails_per_request;
            --$this->emails_per_request;
            if ($limit < 1) {
                $this->emails_per_request = 0;

                return 0;
            }
        }
        $filename = \Sledgehammer\TMP_DIR.'error_handler_email_limit.txt';
        if (!@file_exists($filename)) {
            error_log('File "'.$filename.'" doesn\'t exist (yet)');
            // nieuwe voorraad.
            $day_limit = $this->emails_per_day;
            $minute_limit = $this->emails_per_minute;
        } else { // het bestand bestaat.
            // Bestand inlezen.
            $fp = @fopen($filename, 'r');
            if ($fp === false) {
                error_log('Reading file "'.$filename.'" failed');

                return false; // Kon het bestand niet openen.
            }
            $datum = @rtrim(fgets($fp)); // datum inlezen.
            $day_limit = $this->mergeLimit(@rtrim(fgets($fp)), $this->emails_per_day);
            $tijdstip = @rtrim(fgets($fp)); // minuut inlezen
            $minute_limit = $this->mergeLimit(@rtrim(fgets($fp)), $this->emails_per_minute);
            @fclose($fp);

            if ($datum != @date('j-n')) { // is het een andere datum?
                $day_limit = $this->emails_per_day; // nieuwe dag voorraad.
                $minute_limit = $this->emails_per_minute;
                error_log('Resetting errorlimit');
            } elseif ($tijdstip != @date('H:i')) { // Is het een andere minuut
                $minute_limit = $this->emails_per_minute; // nieuwe minuut voorraad.
            }
        }
        $limit = 0; // Standaard instellen dat de limiet is bereikt.
        if ($day_limit === 'NO_LIMIT' && $minute_limit === 'NO_LIMIT') { // Is er helemaal geen limiet?
            $limit = 999;
        } elseif ($day_limit === 'NO_LIMIT') { // Er is geen limiet per dag?
            $limit = $minute_limit; // Gebruik de limit van de minuut
            --$minute_limit; // voorraad verminderen
        } elseif ($minute_limit === 'NO_LIMIT') { // Geen limit per minuut?
            $limit = $day_limit; // Gebruik de limit van de minuut
            --$day_limit; // voorraad verminderen
        } else { // Er is zowel een limiet per dag als per minuut
            $limit = ($day_limit < $minute_limit) ? $day_limit : $minute_limit; // limit wordt de laagste waarde
            // Voorraad verminderen
            --$day_limit;
            --$minute_limit;
        }
        // Is de limiet van 0 naar -1 veranderd, zet deze weer op 0
        $day_limit = $this->mergeLimit($day_limit);
        $minute_limit = $this->mergeLimit($minute_limit);

        // Wijzigingen opslaan
        $fp = @fopen($filename, 'w');
        if ($fp !== false) {
            $write = @fwrite($fp, @date('j-n')."\n".$day_limit."\n".@date('H:i')."\n".$minute_limit);
            if ($write == false) {
                error_log('Writing to file "'.$filename.'"  failed');

                return false; // Kon niet schrijven.
            }
            @fclose($fp);
        } else {
            error_log('Unable to open file "'.$filename.'" in write mode');

            return false; // Kon het bestand niet openen om te schrijven
        }

        return $limit;
    }

    /**
     * Zet een variable om naar een limiet (0 of groter of  "NO_LIMIT")
     * En corrigeert de waarde als een van de waardes NO_LIMIT.
     *
     * @param mixed      $limit           wordt omgezet naar een int of "NO_LIMIT"
     * @param int|string $configuredLimit ingestelde limiet
     */
    private function mergeLimit($limit, $configuredLimit = null)
    {
        $limit = ($limit === 'NO_LIMIT') ? 'NO_LIMIT' : (int) $limit;
        if ($configuredLimit !== null) {
            if ($limit === 'NO_LIMIT' && $configuredLimit !== 'NO_LIMIT') { // Als er in het error_handler_email_limit.txt "NO_LIMIT" staat, maar in de config 100, return dan 100
                return $configuredLimit;
            }
            if ($configuredLimit === 'NO_LIMIT') { // Als er NO_LIMIT in de config staat return dan NO_LIMIT ongeacht waarde uit error_handler_email_limit.txt
                return 'NO_LIMIT';
            }
            if ($limit > $configuredLimit) { // Als er in het error_handler_email_limit een groter getal staan dan in de config?
                return $configuredLimit; // return de config
            }
        }
        // Rond negative getallen af naar 0
        if (is_int($limit) && $limit < 0) {
            return 0;
        }

        return $limit;
    }

    /**
     * Vraag het from gedeelte van de te versturen email op.
     * Bv '"ErrorHandler (www.example.com)" <errorhandler@example.com>'
     * return string.
     */
    private function fromEmail()
    {
        $hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : php_uname('n');
        $regexDomain = '/[a-z0-9-]+(.[a-z]{2}){0,1}\.[a-z]{2,4}$/i';
        if (preg_match($regexDomain, $hostname, $match)) { // Zit er een domeinnaam in de hostname?
            $domain = $match[0];
        } elseif (preg_match($regexDomain, $this->email, $match)) { // de hostname was een ip, localhost, ofzo. Pak dan de domeinnaam van het "To:" adres
            $domain = $match[0];
        } else { // Er zit zelfs geen domeinnaam in het email-adres?
            $domain = 'localhost';
        }

        return '"ErrorHandler ('.$hostname.')" <errorhandler@'.$domain.'>';
    }

    /**
     * Return true for Exceptions & PHP7 Error and other objects that implement Throwable.
     *
     * @param Throwable $throwable
     */
    private static function isThrowable($throwable)
    {
        return $throwable instanceof Exception || $throwable instanceof Throwable;
    }

    protected static function defaultInstance()
    {
        if (class_exists('Sledgehammer\Core\Base', false) === false) {
            require_once __DIR__.'/../Object.php';
        }
        $errorHandler = new self();

        if (defined('Sledgehammer\ENVIRONMENT') === false || \Sledgehammer\ENVIRONMENT === 'development' || \Sledgehammer\ENVIRONMENT === 'phpunit') {
            ini_set('display_errors', true);
            $errorHandler->html = true;
            $errorHandler->debugR = true;
            $errorHandler->emails_per_request = 10;
        } else {
            $errorHandler->emails_per_request = 2;
            $errorHandler->emails_per_minute = 6;
            $errorHandler->emails_per_day = 25;
            $_email = isset($_SERVER['SERVER_ADMIN']) ? $_SERVER['SERVER_ADMIN'] : false;
            if (preg_match('/^.+@.+\..+$/', $_email) && !in_array($_email, ['you@example.com'])) { // Is het geen emailadres, of het standaard apache emailadres?
                $errorHandler->email = $_email;
            } elseif ($_email != '') { // Is het email niet leeg of false?
                error_log('Invalid $_SERVER["SERVER_ADMIN"]: "'.$_email.'", expecting an valid emailaddress');
            }
        }

        if (defined('Sledgehammer\ENVIRONMENT') && \Sledgehammer\DEBUG_VAR != false) { // Is the \Sledgehammer\DEBUG_VAR enabled?
            $overrideDebugOutput = null;
            if (isset($_GET[\Sledgehammer\DEBUG_VAR])) { // Is the \Sledgehammer\DEBUG_VAR present in the $_GET parameters?
                $overrideDebugOutput = $_GET[\Sledgehammer\DEBUG_VAR];
                switch ($overrideDebugOutput) {
                    case 'cookie':
                        setcookie(\Sledgehammer\DEBUG_VAR, true);
                        break;

                    case 'nocookie':
                        setcookie(\Sledgehammer\DEBUG_VAR, false, 0);
                        break;
                }
            } elseif (isset($_COOKIE[\Sledgehammer\DEBUG_VAR])) { // Is the \Sledgehammer\DEBUG_VAR present in the $_COOKIE?
                $overrideDebugOutput = $_COOKIE[\Sledgehammer\DEBUG_VAR];
            }
            if ($overrideDebugOutput !== null) {
                ini_set('display_errors', (bool) $overrideDebugOutput);
                $errorHandler->html = (bool) $overrideDebugOutput;
            }
        }
        if (php_sapi_name() === 'cli' && $errorHandler->html) {
            $errorHandler->html = false;
            $errorHandler->cli = true;
        }

        return $errorHandler;
    }
}
