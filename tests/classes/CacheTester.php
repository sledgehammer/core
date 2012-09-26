<?php
namespace Sledgehammer;
/**
 * TestButton, An class for testing an Observable
 *
 * @see ObservableTest
 *
 * @package Core
 */
class CacheTester extends Cache {

	public function __construct($identifier, $backend) {
		parent::__construct($identifier, $backend);
	}

}

?>
