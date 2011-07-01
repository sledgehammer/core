<?php
namespace _Test {
	interface MyInterface extends \Iterator, OtherInterface {
		
	}
	
	class Test extends SledgeHammer\Object implements \Iterator, MyInterface, \Countable {
		
	}
}