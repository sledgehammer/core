<?php
/**
 * Dummy file
 */
namespace _Test {
	use \ArrayAccess as ArAc;
	use SledgeHammer\GD;
	interface MyInterface extends \Iterator, OtherInterface {
		
	}
	
	class Test extends SledgeHammer\Object implements \Iterator, MyInterface, ArAc, \Countable, GD\Image {
		
	}
}