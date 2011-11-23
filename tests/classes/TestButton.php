<?php
/**
 * TestButton, An class for the ObservableTests UnitTest
 *
 * @package Core
 */
namespace SledgeHammer;

class TestButton extends Observable {

	protected $events = array(
		"click" => array()
	);
	public $clicked = false;
	public $data = false;

	protected function onClick($sender) {
		$this->clicked = "Clicked by ".get_class($sender);
	}

}

?>
