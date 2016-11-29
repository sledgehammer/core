<?php

namespace SledgehammerTests\Core;

use Exception;
use SledgehammerTests\Core\Fixtures\TestButton;

class EventEmitterTest extends TestCase
{
    public function testButton()
    {
        $button = new TestButton();
        // Test hasEvent
        $this->assertTrue($button->hasEvent('click'));
        $this->assertFalse($button->hasEvent('won_world_cup'));

        try {
            $button->trigger('won_word_cup', $this);
        } catch (Exception $e) {
            $this->assertSame('Event: "won_word_cup" not registered', $e->getMessage());
        }

        // Test onClick method
        $button->trigger('click', $this);
        $this->assertSame($button->lastClickedBy, self::class);
        // Test custom event via property
        $tempvar = false;
        $button->onClick = function ($sender) use (&$tempvar) {
            $tempvar = 'custom event';
        };
        $button->click();
        $this->assertSame($tempvar, 'custom event');
        $this->assertSame($button->lastClickedBy, 'SledgehammerTests\Core\Fixtures\TestButton');

        $tempvar = 'reset';
        $tempvar2 = false;
        $button->onClick = function ($sender) use (&$tempvar2) {
            $tempvar2 = 'custom event2'; // modify clicked not data.
        };

        $button->click();
        $this->assertSame($tempvar2, 'custom event2');
        $this->assertSame($tempvar, 'reset', 'The first "custom event" is overwitten. and no longer gets triggered');

        // Test custom event via on
        $tempvar3 = false;
        $listenerId = $button->on('click', function ($sender) use (&$tempvar3) {
            $tempvar3 = 'custom event3';
        });
        $tempvar2 = 'reset';
        $button->trigger('click', $button);
        $this->assertSame($tempvar2, 'custom event2');
        $this->assertSame($tempvar3, 'custom event3');
        $button->off('click', $listenerId);
        $tempvar3 = 'nothing happend';
        $button->trigger('click', $button);
        $this->assertSame($tempvar3, 'nothing happend');
    }

    public function test_kvo()
    {
        $button = new TestButton();
        $this->assertSame($button->title, 'Button1');
        $this->assertTrue($this->property_exists($button, 'title'), 'The title propertty is a normal property'); // When no events are bound to a property change
        $eventArguments = false;
        $button->on('change:title', function ($button, $new, $old) use (&$eventArguments) {
            $eventArguments = func_get_args();
        });
        $this->assertFalse($this->property_exists($button, 'title'), 'The title is now a virtual property');
        $this->assertSame($button->title, 'Button1', 'The property should still have its value');

        $button->title = 'Click me';
        $this->assertSame(array(
            $button,
            'Click me',
            'Button1',
                ), $eventArguments);
        $this->assertSame($button->title, 'Click me', 'The property changed to the new value');

        $changeLog = [];
        // Monitor all changes
        $button->onChange = function ($button, $field, $new, $old) use (&$changeLog) {
            $changeLog[$field][] = $new;
        };
        $button->clicked = 10;
        $button->click();
        $this->assertSame(array(
            'clicked' => array(10, 11),
            'lastClickedBy' => array('SledgehammerTests\Core\Fixtures\TestButton'),
                ), $changeLog);
    }

    public function test_invalid_on_parameters()
    {
        $button = new TestButton();
        try {
            $button->on('click', true);
            $this->fail('Invalid callback should throw an exception');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Invalid callback should throw an exception');
        }
        try {
            $button->on('world_domination', function () {

            });
            $this->fail('Non existing events should throw an exception');
        } catch (Exception $e) {
            $this->assertTrue(true, 'Non existing events should throw an exception');
        }
    }

    private function property_exists($object, $property)
    {
        if (property_exists($object, $property)) {
            // When property is unset (and doesn't exist) property_exists() still returns true
            $properties = get_object_vars($object);

            return array_key_exists($property, $properties);
        }

        return false;
    }
}
