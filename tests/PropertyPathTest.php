<?php
/**
 * PropertyPathTests
 */
namespace Sledgehammer;
class PropertyPathTest extends TestCase {

	function test_tokenizer() {
		Framework::$autoLoader->exposePrivates('Sledgehammer\PropertyPath', 'Sledgehammer\PropertyPathTester');

		$string = PropertyPath::T_STRING;
		$dot = PropertyPath::T_DOT;
		$arrow = PropertyPath::T_ARROW;
		$bracketOpen = PropertyPath::T_BRACKET_OPEN;
		$bracketClose = PropertyPath::T_BRACKET_CLOSE;
		$parentheses = PropertyPath::T_PARENTHESES;
		$optional = PropertyPath::T_OPTIONAL;

		$this->assertEquals(PropertyPathTester::tokenize('any'), array(array($string, 'any')));
		$this->assertEquals(PropertyPathTester::tokenize('any?'), array(array($string, 'any'), array($optional, '?')));
		$this->assertEquals(PropertyPathTester::tokenize('any1.any2'), array(array($string, 'any1'), array($dot, '.'), array($string, 'any2')));
		$this->assertEquals(PropertyPathTester::tokenize('any->property[element]->method()'), array(
			array($string, 'any'),
			array($arrow, '->'),
			array($string, 'property'),
			array($bracketOpen, '['),
			array($string, 'element'),
			array($bracketClose, ']'),
			array($arrow, '->'),
			array($string, 'method'),
			array($parentheses, '()'),
		));
	}

	function test_compile() {
		$any = PropertyPath::TYPE_ANY;
		$element = PropertyPath::TYPE_ELEMENT;
		$property = PropertyPath::TYPE_PROPERTY;

		$this->assertEquals(PropertyPath::compile('any'), array(array($any, 'any')));
		$this->assertEquals(PropertyPath::compile('any1.any2'), array(array($any, 'any1'), array($any, 'any2')));
		$this->assertEquals(PropertyPath::compile('any?'), array(array(PropertyPath::TYPE_OPTIONAL, 'any')));

		$this->assertEquals(PropertyPath::compile('[element]'), array(array($element, 'element')));
		$this->assertEquals(PropertyPath::compile('any[element]'), array(array($any, 'any'), array($element, 'element')));
		$this->assertEquals(PropertyPath::compile('[element1][element2]'), array(array($element, 'element1'), array($element, 'element2')));
		$this->assertEquals(PropertyPath::compile('[element?]'), array(array(PropertyPath::TYPE_OPTIONAL_ELEMENT, 'element')));

		$this->assertEquals(PropertyPath::compile('->property'), array(array($property, 'property')));
		$this->assertEquals(PropertyPath::compile('any->property'), array(array($any, 'any'), array($property, 'property')));
		$this->assertEquals(PropertyPath::compile('->property1->property2'), array(array($property, 'property1'), array($property, 'property2')));
		$this->assertEquals(PropertyPath::compile('->property?'), array(array(PropertyPath::TYPE_OPTIONAL_PROPERTY, 'property')));

		$this->assertEquals(PropertyPath::compile('[element]->property'), array(array($element, 'element'), array($property, 'property')));
		$this->assertEquals(PropertyPath::compile('any[element]->property'), array(array($any, 'any'), array($element, 'element'), array($property, 'property')));
		$this->assertEquals(PropertyPath::compile('[element]->property.any'), array(array($element, 'element'), array($property, 'property'), array($any, 'any')));
		$this->assertEquals(PropertyPath::compile('->property[element]'), array(array($property, 'property'), array($element, 'element')));
		$this->assertEquals(PropertyPath::compile('any->property[element]'), array(array($any, 'any'), array($property, 'property'), array($element, 'element')));
		$this->assertEquals(PropertyPath::compile('->property[element].any'), array(array($property, 'property'), array($element, 'element'), array($any, 'any')));
		$this->assertEquals(PropertyPath::compile(123), array(array($any, '123')), 'Allow integer paths');
	}

	function test_assemble() {
		$path = '[element]->property';
		$this->assertEquals(PropertyPath::assemble(PropertyPath::compile($path)), $path);
		$path = 'any[element]->property';
		$this->assertEquals(PropertyPath::assemble(PropertyPath::compile($path)), $path);
		$path = '->property[element].any';
		$this->assertEquals(PropertyPath::assemble(PropertyPath::compile($path)), $path);
	}

	function test_compile_warning_empty_path() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice', 'Path is empty');
		$this->assertEquals(PropertyPath::compile(''), array());
	}

	function test_compile_warning_invalid_start() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice', 'Invalid "." in the path');
		$this->assertEquals(PropertyPath::compile('.any'), array(array(PropertyPath::TYPE_ANY, 'any')));
	}

	function test_compile_warning_invalid_chain() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice', 'Invalid chain, expecting a ".", "->" or "[" before "any"');
		$this->assertEquals(PropertyPath::compile('[element]any'), array(array(PropertyPath::TYPE_ELEMENT, 'element'), array(PropertyPath::TYPE_ANY, 'any')));
	}

	function test_compile_warning_invalid_arrow() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice', 'Invalid "->" in path, expecting an identifier after an "->"');
		$this->assertEquals(PropertyPath::compile('->->property'), array(array(PropertyPath::TYPE_PROPERTY, 'property')));
	}

	function test_compile_warning_unmatched_brackets() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice', 'Unmatched brackets, missing a "]" in path after "element"');
		$this->assertEquals(PropertyPath::compile('[element'), array(array(PropertyPath::TYPE_ANY, '[element')));
	}

	function test_PropertyPath_get() {
		$array = array('id' => '1');
		$object = (object) array('id' => '2');

		$this->assertEquals(PropertyPath::get('.', 'me'), 'me', 'Path "." should return the input');
		// autodetect type
		$this->assertEquals(PropertyPath::get('id', $array), '1', 'Path "id" should work on arrays');
		$this->assertEquals(PropertyPath::get('id', $object), '2', 'Path "id" should also work on objects');
		// force array element
		$this->assertEquals(PropertyPath::get('[id]', $array), '1', 'Path "[id]" should work on arrays');
		// force object property
		$this->assertEquals(PropertyPath::get('->id', $object), '2', 'Path "->id" should work on objects');
		$object->property = array('id' => '3');
		$this->assertEquals(PropertyPath::get('property[id]', $object), '3', 'Path "property[id]" should work on objects');
		$this->assertEquals(PropertyPath::get('->property[id]', $object), '3', 'Path "->property[id]" should work on objects');
		$object->object = (object) array('id' => '4');
		$this->assertEquals(PropertyPath::get('object->id', $object), '4', 'Path "object->id" should work on objects in objects');
		$this->assertEquals(PropertyPath::get('->object->id', $object), '4', 'Path "->object->id" should work on objects in objects');
		$object->property['element'] = (object) array('id' => '5');
		$this->assertEquals(PropertyPath::get('property[element]->id', $object), '5');
		$this->assertEquals(PropertyPath::get('->property[element]->id', $object), '5');
		$array['object'] = (object) array('id' => 6);
		$this->assertEquals(PropertyPath::get('object->id', $array), 6);
		$this->assertEquals(PropertyPath::get('[object]->id', $array), 6);
		// optional
		$this->assertEquals(PropertyPath::get('id?', $array), '1', 'Path "id?" should work on arrays');
		$this->assertEquals(PropertyPath::get('id?', $object), '2', 'Path "id?" should work on objects');
		$this->assertEquals(PropertyPath::get('undefined?', $array), null, 'Path "id?" should work on arrays');
		$this->assertEquals(PropertyPath::get('undefined?', $object), null, 'Path "id?" should work on objects');

		$this->assertEquals(PropertyPath::get('[id?]', $array), '1', 'Path "->id?" should work on arrays');
		$this->assertEquals(PropertyPath::get('->id?', $object), '2', 'Path "->id?" should work on objects');

//		$this->assertEquals(PropertyPath::get($array, 'undefined?'), null, 'Path "id?" should work on arrays');
//		$this->assertEquals(PropertyPath::get($object, 'undefined?'), null, 'Path "id?" should work on objects');

		// @todo Add UnitTest for method notation "getFilename()"

		$sequence = array(
			array('id' => 1),
			array('id' => 3),
			array('id' => 5),
		);
		$this->assertEquals(PropertyPath::get('[*].id', $sequence), array(1, 3, 5));

		\PHPUnit_Framework_Error_Notice::$enabled = false;
		ob_start();
		$this->assertEquals(PropertyPath::get('->property->element', $object), null);
		$this->assertRegExp('/Unexpected type: array, expecting an object/', ob_get_clean());
		ob_start();
		$this->assertEquals(PropertyPath::get('->id', $array), null, 'Path "->id" should NOT work on arrays');
		$this->assertRegExp('/Unexpected type: array, expecting an object/', ob_get_clean());
		ob_start();
		$this->assertEquals(PropertyPath::get('[id]', $object), null, 'Path "[id]" should NOT work on objects');
		$this->assertRegExp('/Unexpected type: object, expecting an array/', ob_get_clean());

//		PropertyPath::get('->id?', $array)
//		PropertyPath::get('[id?]', $object)
		\PHPUnit_Framework_Error_Notice::$enabled = true;
	}

	function test_PropertyPath_set() {
		$array = array('id' => '1');
		$object = (object) array('id' => '2');
		PropertyPath::set('id', 3, $array);
		$this->assertEquals($array['id'], 3);
		PropertyPath::set('id', 4, $object);
		$this->assertEquals($object->id, 4);
		PropertyPath::set('->id', 5, $object);
		$this->assertEquals($object->id, 5);
		PropertyPath::set('[id]', 6, $array);
		$this->assertEquals($array['id'], 6);
		$array['object'] = (object) array('id' => 7);
		PropertyPath::set('object->id', 8, $array);
		$this->assertEquals($array['object']->id, 8);
		PropertyPath::set('[object]->id', 9, $array);
		$this->assertEquals($array['object']->id, 9);
		$array['element'] = array('id' => 1);
		PropertyPath::set('element[id]', 10, $array);
		$this->assertEquals($array['element']['id'], 10);
	}

}

?>
