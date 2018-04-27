<?php

namespace SledgehammerTests\Core\Fixtures;

use Sledgehammer\Core\EventEmitter;
use Sledgehammer\Core\Base;

/**
 * TestButton, An class for testing an EventEmitter.
 *
 */
class TestButton extends Base
{
    use EventEmitter;

    protected $events = [
        'click' => [],
    ];
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
