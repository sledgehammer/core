<?php
/**
 * InfoException
 * @package Core
 */
namespace SledgeHammer;
/**
 * An exception with additonal information for the ErrorHander.
 *
 * Named "InfoException" instead of "\SledgeHammer\Exception" to prevent catch issues.
 * See http://onehackoranother.com/logfile/2009/01/php-5-3-exception-gotcha for details
 */
class InfoException extends \Exception {

	private $information;

	/**
	 * Contructor
	 *
	 * @param string $message
	 * @param mixed $information
	 * @param int $code
	 * @param Exception $previous (optional)
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
