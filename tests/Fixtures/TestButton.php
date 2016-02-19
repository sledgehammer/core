<?php

namespace SledgehammerTests\Core\Fixtures;

use Sledgehammer\Core\Object;
use Sledgehammer\Core\Observable;
use SledgehammerTests\Core\ObservableTest;

/**
 * TestButton, An class for testing an Observable.
 *
 * @see ObservableTest
 */
class TestButton extends Object
{
    use Observable;
    protected $events = array(
        'click' => [],
    );
    public $clicked = 0;
    public $lastClickedBy = null;
    public $title = 'Button1';

    public function click()
    {
        $this->trigger('click', $this);
    }

    protected function onClick($sender)
    {
        ++$this->clicked;
        $this->lastClickedBy = get_class($sender);
    }
}
