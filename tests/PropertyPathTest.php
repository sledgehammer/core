<?php

namespace SledgehammerTests\Core;

use Sledgehammer\Core\Debug\Autoloader;
use Sledgehammer\Core\PropertyPath;
use Sledgehammer\Core\PropertyPath_Tester;

class PropertyPathTest extends TestCase
{
    public function test_tokenizer()
    {
        Autoloader::instance()->exposePrivates('Sledgehammer\Core\PropertyPath', 'Sledgehammer\Core\PropertyPath_Tester');

        $string = PropertyPath::T_STRING;
        $dot = PropertyPath::T_DOT;
        $arrow = PropertyPath::T_ARROW;
        $bracketOpen = PropertyPath::T_BRACKET_OPEN;
        $bracketClose = PropertyPath::T_BRACKET_CLOSE;
        $parentheses = PropertyPath::T_PARENTHESES;
        $optional = PropertyPath::T_OPTIONAL;

        $this->assertSame(PropertyPath_Tester::tokenize('any'), array(array($string, 'any')));
        $this->assertSame(PropertyPath_Tester::tokenize('any?'), array(array($string, 'any'), array($optional, '?')));
        $this->assertSame(PropertyPath_Tester::tokenize('any1.any2'), array(array($string, 'any1'), array($dot, '.'), array($string, 'any2')));
        $this->assertSame(PropertyPath_Tester::tokenize('any->property[element]->method()'), array(
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

    public function test_parse()
    {
        $any = PropertyPath::TYPE_ANY;
        $element = PropertyPath::TYPE_ELEMENT;
        $property = PropertyPath::TYPE_PROPERTY;

        $this->assertSame(PropertyPath::parse('any'), array(array($any, 'any')));
        $this->assertSame(PropertyPath::parse('any1.any2'), array(array($any, 'any1'), array($any, 'any2')));
        $this->assertSame(PropertyPath::parse('any?'), array(array(PropertyPath::TYPE_OPTIONAL, 'any')));

        $this->assertSame(PropertyPath::parse('[element]'), array(array($element, 'element')));
        $this->assertSame(PropertyPath::parse('any[element]'), array(array($any, 'any'), array($element, 'element')));
        $this->assertSame(PropertyPath::parse('[element1][element2]'), array(array($element, 'element1'), array($element, 'element2')));
        $this->assertSame(PropertyPath::parse('[element?]'), array(array(PropertyPath::TYPE_OPTIONAL_ELEMENT, 'element')));

        $this->assertSame(PropertyPath::parse('->property'), array(array($property, 'property')));
        $this->assertSame(PropertyPath::parse('any->property'), array(array($any, 'any'), array($property, 'property')));
        $this->assertSame(PropertyPath::parse('->property1->property2'), array(array($property, 'property1'), array($property, 'property2')));
        $this->assertSame(PropertyPath::parse('->property?'), array(array(PropertyPath::TYPE_OPTIONAL_PROPERTY, 'property')));

        $this->assertSame(PropertyPath::parse('[element]->property'), array(array($element, 'element'), array($property, 'property')));
        $this->assertSame(PropertyPath::parse('any[element]->property'), array(array($any, 'any'), array($element, 'element'), array($property, 'property')));
        $this->assertSame(PropertyPath::parse('[element]->property.any'), array(array($element, 'element'), array($property, 'property'), array($any, 'any')));
        $this->assertSame(PropertyPath::parse('->property[element]'), array(array($property, 'property'), array($element, 'element')));
        $this->assertSame(PropertyPath::parse('any->property[element]'), array(array($any, 'any'), array($property, 'property'), array($element, 'element')));
        $this->assertSame(PropertyPath::parse('->property[element].any'), array(array($property, 'property'), array($element, 'element'), array($any, 'any')));
        $this->assertSame(PropertyPath::parse(123), array(array($any, '123')), 'Allow integer paths');
    }

    public function test_assemble()
    {
        $path = '[element]->property';
        $this->assertSame(PropertyPath::assemble(PropertyPath::parse($path)), $path);
        $path = 'any[element]->property';
        $this->assertSame(PropertyPath::assemble(PropertyPath::parse($path)), $path);
        $path = '->property[element].any';
        $this->assertSame(PropertyPath::assemble(PropertyPath::parse($path)), $path);
    }

    public function test_parser_warning_empty_path()
    {
        $this->expectException('\PHPUnit\Framework\Error\Notice', 'Path is empty');
        $this->assertSame(PropertyPath::parse(''), []);
    }

    public function test_parser_warning_invalid_start()
    {
        $this->expectException('\PHPUnit\Framework\Error\Notice', 'Invalid "." in the path');
        $this->assertSame(PropertyPath::parse('.any'), array(array(PropertyPath::TYPE_ANY, 'any')));
    }

    public function test_parser_warning_invalid_chain()
    {
        $this->expectException('\PHPUnit\Framework\Error\Notice', 'Invalid chain, expecting a ".", "->" or "[" before "any"');
        $this->assertSame(PropertyPath::parse('[element]any'), array(array(PropertyPath::TYPE_ELEMENT, 'element'), array(PropertyPath::TYPE_ANY, 'any')));
    }

    public function test_parser_warning_invalid_arrow()
    {
        $this->expectException('\PHPUnit\Framework\Error\Notice', 'Invalid "->" in path, expecting an identifier after an "->"');
        $this->assertSame(PropertyPath::parse('->->property'), array(array(PropertyPath::TYPE_PROPERTY, 'property')));
    }

    public function test_parser_warning_unmatched_brackets()
    {
        $this->expectException('\PHPUnit\Framework\Error\Notice', 'Unmatched brackets, missing a "]" in path after "element"');
        $this->assertSame(PropertyPath::parse('[element'), array(array(PropertyPath::TYPE_ANY, '[element')));
    }

    public function test_PropertyPath_get()
    {
        $array = array('id' => '1');
        $object = (object) array('id' => '2');

        $this->assertSame(PropertyPath::get('.', 'me'), 'me', 'Path "." should return the input');
        // autodetect type
        $this->assertSame(PropertyPath::get('id', $array), '1', 'Path "id" should work on arrays');
        $this->assertSame(PropertyPath::get('id', $object), '2', 'Path "id" should also work on objects');
        // force array element
        $this->assertSame(PropertyPath::get('[id]', $array), '1', 'Path "[id]" should work on arrays');
        // force object property
        $this->assertSame(PropertyPath::get('->id', $object), '2', 'Path "->id" should work on objects');
        $object->property = array('id' => '3');
        $this->assertSame(PropertyPath::get('property[id]', $object), '3', 'Path "property[id]" should work on objects');
        $this->assertSame(PropertyPath::get('->property[id]', $object), '3', 'Path "->property[id]" should work on objects');
        $object->object = (object) array('id' => '4');
        $this->assertSame(PropertyPath::get('object->id', $object), '4', 'Path "object->id" should work on objects in objects');
        $this->assertSame(PropertyPath::get('->object->id', $object), '4', 'Path "->object->id" should work on objects in objects');
        $object->property['element'] = (object) array('id' => '5');
        $this->assertSame(PropertyPath::get('property[element]->id', $object), '5');
        $this->assertSame(PropertyPath::get('->property[element]->id', $object), '5');
        $array['object'] = (object) array('id' => 6);
        $this->assertSame(PropertyPath::get('object->id', $array), 6);
        $this->assertSame(PropertyPath::get('[object]->id', $array), 6);
        // optional
        $this->assertSame(PropertyPath::get('id?', $array), '1', 'Path "id?" should work on arrays');
        $this->assertSame(PropertyPath::get('id?', $object), '2', 'Path "id?" should work on objects');
        $this->assertSame(PropertyPath::get('undefined?', $array), null, 'Path "id?" should work on arrays');
        $this->assertSame(PropertyPath::get('undefined?', $object), null, 'Path "id?" should work on objects');

        $this->assertSame(PropertyPath::get('[id?]', $array), '1', 'Path "->id?" should work on arrays');
        $this->assertSame(PropertyPath::get('->id?', $object), '2', 'Path "->id?" should work on objects');

        //      $this->assertSame(PropertyPath::get($array, 'undefined?'), null, 'Path "id?" should work on arrays');
        //      $this->assertSame(PropertyPath::get($object, 'undefined?'), null, 'Path "id?" should work on objects');
        // @todo Add UnitTest for method notation "getFilename()"

        $sequence = array(
            array('id' => 1),
            array('id' => 3),
            array('id' => 5),
        );
        $this->assertSame(PropertyPath::get('[*].id', $sequence), array(1, 3, 5));

        // @todo Should optional selector still generate notices on unexpected types?
        // PropertyPath::get('->id?', $array);
        // PropertyPath::get('[id?]', $object);
    }
    public function test_PropertyPath_get_unexpectedArray()
    {
        $this->expectNotice();
        $this->expectNoticeMessageMatches('/Unexpected type: array, expecting an object/');
        $array = ['key' => 123];
        PropertyPath::get('->key', $array); // 'Path "->key" should NOT work on arrays'
    }

    public function test_PropertyPath_get_unexpectedObject()
    {
        $object = (object) ['prop' => 456];
        $this->expectNotice();
        $this->expectNoticeMessageMatches('/Unexpected type: object, expecting an array/');
        PropertyPath::get('[id]', $object); // Path "[id]" should NOT work on objects
    }

    public function test_PropertyPath_set()
    {
        $array = array('id' => '1');
        $object = (object) array('id' => '2');
        PropertyPath::set('id', 3, $array);
        $this->assertSame($array['id'], 3);
        PropertyPath::set('id', 4, $object);
        $this->assertSame($object->id, 4);
        PropertyPath::set('->id', 5, $object);
        $this->assertSame($object->id, 5);
        PropertyPath::set('[id]', 6, $array);
        $this->assertSame($array['id'], 6);
        $array['object'] = (object) array('id' => 7);
        PropertyPath::set('object->id', 8, $array);
        $this->assertSame($array['object']->id, 8);
        PropertyPath::set('[object]->id', 9, $array);
        $this->assertSame($array['object']->id, 9);
        $array['element'] = array('id' => 1);
        PropertyPath::set('element[id]', 10, $array);
        $this->assertSame($array['element']['id'], 10);
    }

    public function test_compile()
    {
        $closure = PropertyPath::compile('id');
        $item = array('id' => 8);
        $this->assertTrue(\Sledgehammer\is_closure($closure), 'compile() should return a closure');
        $this->assertSame(8, $closure($item));
    }

    public function test_map()
    {
        $source = array(
            'deep' => array(
                'nested' => 1,
            ),
            'value' => 2,
        );
        $target = [];
        $mapping = array(
            'dn' => 'deep.nested',
            'meta[value]' => 'value',
        );
        PropertyPath::map($source, $target, $mapping);
        $this->assertSame(array('dn' => 1, 'meta' => array('value' => 2)), $target, '');
    }
}
