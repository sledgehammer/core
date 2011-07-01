<?php
namespace _Test {
	use \ArrayAccess as ArAc;
	interface MyInterface extends \Iterator, OtherInterface {
		
	}
	
	class Test extends SledgeHammer\Object implements \Iterator, MyInterface, ArAc, \Countable {
		
	}
}