<?php
/**
 * ObervableTests
 *
 */
namespace SledgeHammer;

class ObservableTests extends \UnitTestCase {

	function test_button() {
		$button = new TestButton();
		// Test hasEvent
		$this->assertTrue($button->hasEvent('click'));
		$this->assertFalse($button->hasEvent('won_world_cup'));

		$this->expectError('Event: "won_word_cup" not registered');
		$button->fire('won_word_cup', $this);

		// Test onClick method
		$button->fire('click', $this);
		$this->assertEqual($button->clicked, 'Clicked by SledgeHammer\ObservableTests');
		// Test custom event via property
		$button->onClick = function ($sender) {
				$sender->data = 'custom event';
			};
		$button->fire('click', $button);
		$this->assertEqual($button->data, 'custom event');
		$this->assertEqual($button->clicked, 'Clicked by SledgeHammer\TestButton');

		$button->onClick = function ($sender) {
				$sender->clicked = 'custom event2'; // modify clicked not data.
			};
		$button->data = 'reset';
		$button->fire('click', $button);
		$this->assertEqual($button->clicked, 'custom event2');
		$this->assertEqual($button->data, 'reset', 'The first "custom event" is overwitten. and no longer gets triggered');

		// Test custom event via addListener
		$button->addListener('click', function ($sender) {
				$sender->data = 'custom event3';
			});
		$button->clicked = false;
		$button->fire('click', $button);
		$this->assertEqual($button->clicked, 'custom event2');
		$this->assertEqual($button->data, 'custom event3');
	}

}

?>
