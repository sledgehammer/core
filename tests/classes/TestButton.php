<?php
namespace Sledgehammer;
/**
 * TestButton, An class for testing an Observable
 *
 * @see ObservableTest
 *
 * @package Core
 */
class TestButton extends Observable {

	protected $events = array(
		"click" => array()
	);
	public $clicked = 0;
	public $lastClickedBy = null;
	public $title = 'Button1';

	public function click() {
		$this->trigger('click', $this);
	}

	protected function onClick($sender) {
		$this->clicked++;
		$this->lastClickedBy = get_class($sender);
	}

}

?>
