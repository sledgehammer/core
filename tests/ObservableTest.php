<?php
/**
 * ObervableTests
 *
 */
namespace Sledgehammer;

class ObservableTest extends TestCase {

	function test_button() {
		$button = new TestButton();
		// Test hasEvent
		$this->assertTrue($button->hasEvent('click'));
		$this->assertFalse($button->hasEvent('won_world_cup'));

//		$this->expectError('Event: "won_word_cup" not registered');
//		$button->trigger('won_word_cup', $this);

		// Test onClick method
		$button->trigger('click', $this);
		$this->assertEquals($button->lastClickedBy, 'Sledgehammer\ObservableTest');
		// Test custom event via property
		$tempvar = false;
		$button->onClick = function ($sender) use (&$tempvar){
				$tempvar = 'custom event';
			};
		$button->click();
		$this->assertEquals($tempvar, 'custom event');
		$this->assertEquals($button->lastClickedBy, 'Sledgehammer\TestButton');

		$tempvar = 'reset';
		$tempvar2 = false;
		$button->onClick = function ($sender) use (&$tempvar2) {
				$tempvar2 = 'custom event2'; // modify clicked not data.
			};

		$button->click();
		$this->assertEquals($tempvar2, 'custom event2');
		$this->assertEquals($tempvar, 'reset', 'The first "custom event" is overwitten. and no longer gets triggered');

		// Test custom event via addListener
		$tempvar3 = false;
		$button->addListener('click', function ($sender) use (&$tempvar3){
				$tempvar3 = 'custom event3';
			});
		$tempvar2 = 'reset';
		$button->trigger('click', $button);
		$this->assertEquals($tempvar2, 'custom event2');
		$this->assertEquals($tempvar3, 'custom event3');
	}

	function test_kvo() {
		$button = new TestButton();
		$this->assertEquals($button->title, 'Button1');
		$this->assertTrue($this->property_exists($button, 'title'), 'The title propertty is a normal property'); // When no events are bound to a property change
		$eventArguments = false;
		$button->addListener('change:title', function ($button, $new, $old) use (&$eventArguments) {
			$eventArguments = func_get_args();
		});
		$this->assertFalse($this->property_exists($button, 'title'), 'The title is now a virtual property');
		$this->assertEquals($button->title, 'Button1', 'The property should still have its value');

		$button->title = 'Click me';
		$this->assertEquals(array(
			$button,
			'Click me',
			'Button1'
		), $eventArguments);
		$this->assertEquals($button->title, 'Click me', 'The property changed to the new value');

		$changeLog = array();
		// Monitor all changes
		$button->onChange = function ($button, $field, $new, $old) use (&$changeLog) {
			$changeLog[$field][] = $new;
		};
		$button->clicked = 10;
		$button->click();
		$this->assertEquals(array(
			'clicked' => array(
				10,
				11,
			),
			'lastClickedBy' => array(
				'Sledgehammer\TestButton',
			),
		), $changeLog);
	}

	private function property_exists($object, $property) {
		if (property_exists($object, $property)) {
			// When property is unset (and doesn't exist) property_exists() still returns true
			$properties = get_object_vars($object);
			return array_key_exists($property, $properties);
		}
		return false;
	}

}

?>
