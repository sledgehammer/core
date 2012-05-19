<?php
namespace SledgeHammer;
/**
 * SledgeHammerException, an exception with additonal information for the ErrorHander
 *
 * Named "InfoException" instead of "\SledgeHammer\Exception" to prevent catch issues.
 *  @see: http://onehackoranother.com/logfile/2009/01/php-5-3-exception-gotcha for details
 *
 * @package Core
 */
class InfoException extends \Exception {

	private $information;

	/**
	 * @param string $message
	 * @param mixed $information
	 * @param int $code
	 * @param Exception $previous (optional)
	 */
	public function __construct($message, $information, $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->information = $information;
	}

	function getInformation() {
		return $this->information;
	}
}

?>
