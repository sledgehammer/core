<?php
/**
 * InfoException
 */
namespace Sledgehammer;
/**
 * An exception with additonal information for the ErrorHander.
 *
 * Named "InfoException" instead of "\Sledgehammer\Exception" to prevent catch issues.
 *
 * For details see:
 * @link http://onehackoranother.com/logfile/2009/01/php-5-3-exception-gotcha
 *
 * @package Core
 */
class InfoException extends \Exception {

	/**
	 * The additional information for the ErrorHandler.
	 * @var mixed
	 */
	private $information;

	/**
	 * Contructor
	 *
	 * @param string $message  The Exception message to throw.
	 * @param mixed $information  The additional information for the ErrorHandler
	 * @param int $code  The Exception code.
	 * @param Exception $previous  The previous exception used for the exception chaining.
	 */
	function __construct($message, $information, $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->information = $information;
	}

	/**
	 * Returns the additional error information.
	 *
	 * @return mixed
	 */
	function getInformation() {
		return $this->information;
	}

}

?>