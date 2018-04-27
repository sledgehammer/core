<?php

namespace SledgehammerTests\Core;

use PHPUnit\Framework\Error\Notice;
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

        $this->assertSame(PropertyPath_Tester::tokenize('any'), [[$string, 'any']]);
        $this->assertSame(PropertyPath_Tester::tokenize('any?'), [[$string, 'any'], [$optional, '?']]);
        $this->assertSame(PropertyPath_Tester::tokenize('any1.any2'), [[$string, 'any1'], [$dot, '.'], [$string, 'any2']]);
        $this->assertSame(PropertyPath_Tester::tokenize('any->property[element]->method()'), [
            [$string, 'any'],
            [$arrow, '->'],
            [$string, 'property'],
            [$bracketOpen, '['],
            [$string, 'element'],
            [$bracketClose, ']'],
            [$arrow, '->'],
            [$string, 'method'],
            [$parentheses, '()'],
        ]);
    }

    public function test_parse()
    {
        $any = PropertyPath::TYPE_ANY;
        $element = PropertyPath::TYPE_ELEMENT;
        $property = PropertyPath::TYPE_PROPERTY;

        $this->assertSame(PropertyPath::parse('any'), [[$any, 'any']]);
        $this->assertSame(PropertyPath::parse('any1.any2'), [[$any, 'any1'], [$any, 'any2']]);
        $this->assertSame(PropertyPath::parse('any?'), [[PropertyPath::TYPE_OPTIONAL, 'any']]);

        $this->assertSame(PropertyPath::parse('[element]'), [[$element, 'element']]);
        $this->assertSame(PropertyPath::parse('any[element]'), [[$any, 'any'], [$element, 'element']]);
        $this->assertSame(PropertyPath::parse('[element1][element2]'), [[$element, 'element1'], [$element, 'element2']]);
        $this->assertSame(PropertyPath::parse('[element?]'), [[PropertyPath::TYPE_OPTIONAL_ELEMENT, 'element']]);

        $this->assertSame(PropertyPath::parse('->property'), [[$property, 'property']]);
        $this->assertSame(PropertyPath::parse('any->property'), [[$any, 'any'], [$property, 'property']]);
        $this->assertSame(PropertyPath::parse('->property1->property2'), [[$property, 'property1'], [$property, 'property2']]);
        $this->assertSame(PropertyPath::parse('->property?'), [[PropertyPath::TYPE_OPTIONAL_PROPERTY, 'property']]);

        $this->assertSame(PropertyPath::parse('[element]->property'), [[$element, 'element'], [$property, 'property']]);
        $this->assertSame(PropertyPath::parse('any[element]->property'), [[$any, 'any'], [$element, 'element'], [$property, 'property']]);
        $this->assertSame(PropertyPath::parse('[element]->property.any'), [[$element, 'element'], [$property, 'property'], [$any, 'any']]);
        $this->assertSame(PropertyPath::parse('->property[element]'), [[$property, 'property'], [$element, 'element']]);
        $this->assertSame(PropertyPath::parse('any->property[element]'), [[$any, 'any'], [$property, 'property'], [$element, 'element']]);
        $this->assertSame(PropertyPath::parse('->property[element].any'), [[$property, 'property'], [$element, 'element'], [$any, 'any']]);
        $this->assertSame(PropertyPath::parse(123), [[$any, '123']], 'Allow integer paths');
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
        $this-> expectException('\PHPUnit\Framework\Error\Notice', 'Path is empty');
        $this->assertSame(PropertyPath::parse(''), []);
    }

    public function test_parser_warning_invalid_start()
    {
        $this-> expectException('\PHPUnit\Framework\Error\Notice', 'Invalid "." in the path');
        $this->assertSame(PropertyPath::parse('.any'), [[PropertyPath::TYPE_ANY, 'any']]);
    }

    public function test_parser_warning_invalid_chain()
    {
        $this-> expectException('\PHPUnit\Framework\Error\Notice', 'Invalid chain, expecting a ".", "->" or "[" before "any"');
        $this->assertSame(PropertyPath::parse('[element]any'), [[PropertyPath::TYPE_ELEMENT, 'element'], [PropertyPath::TYPE_ANY, 'any']]);
    }

    public function test_parser_warning_invalid_arrow()
    {
        $this-> expectException('\PHPUnit\Framework\Error\Notice', 'Invalid "->" in path, expecting an identifier after an "->"');
        $this->assertSame(PropertyPath::parse('->->property'), [[PropertyPath::TYPE_PROPERTY, 'property']]);
    }

    public function test_parser_warning_unmatched_brackets()
    {
        $this-> expectException('\PHPUnit\Framework\Error\Notice', 'Unmatched brackets, missing a "]" in path after "element"');
        $this->assertSame(PropertyPath::parse('[element'), [[PropertyPath::TYPE_ANY, '[element']]);
    }

    public function test_PropertyPath_get()
    {
        $array = ['id' => '1'];
        $object = (object) ['id' => '2'];

        $this->assertSame(PropertyPath::get('.', 'me'), 'me', 'Path "." should return the input');
        // autodetect type
        $this->assertSame(PropertyPath::get('id', $array), '1', 'Path "id" should work on arrays');
        $this->assertSame(PropertyPath::get('id', $object), '2', 'Path "id" should also work on objects');
        // force array element
        $this->assertSame(PropertyPath::get('[id]', $array), '1', 'Path "[id]" should work on arrays');
        // force object property
        $this->assertSame(PropertyPath::get('->id', $object), '2', 'Path "->id" should work on objects');
        $object->property = ['id' => '3'];
        $this->assertSame(PropertyPath::get('property[id]', $object), '3', 'Path "property[id]" should work on objects');
        $this->assertSame(PropertyPath::get('->property[id]', $object), '3', 'Path "->property[id]" should work on objects');
        $object->object = (object) ['id' => '4'];
        $this->assertSame(PropertyPath::get('object->id', $object), '4', 'Path "object->id" should work on objects in objects');
        $this->assertSame(PropertyPath::get('->object->id', $object), '4', 'Path "->object->id" should work on objects in objects');
        $object->property['element'] = (object) ['id' => '5'];
        $this->assertSame(PropertyPath::get('property[element]->id', $object), '5');
        $this->assertSame(PropertyPath::get('->property[element]->id', $object), '5');
        $array['object'] = (object) ['id' => 6];
        $this->assertSame(PropertyPath::get('object->id', $array), 6);
        $this->assertSame(PropertyPath::get('[object]->id', $array), 6);
        // optional
        $this->assertSame(PropertyPath::get('id?', $array), '1', 'Path "id?" should work on arrays');
        $this->assertSame(PropertyPath::get('id?', $object), '2', 'Path "id?" should work on objects');
        $this->assertSame(PropertyPath::get('undefined?', $array), null, 'Path "id?" should work on arrays');
        $this->assertSame(PropertyPath::get('undefined?', $object), null, 'Path "id?" should work on objects');

        $this->assertSame(PropertyPath::get('[id?]', $array), '1', 'Path "->id?" should work on arrays');
        $this->assertSame(PropertyPath::get('->id?', $object), '2', 'Path "->id?" should work on objects');

        //		$this->assertSame(PropertyPath::get($array, 'undefined?'), null, 'Path "id?" should work on arrays');
        //		$this->assertSame(PropertyPath::get($object, 'undefined?'), null, 'Path "id?" should work on objects');
        // @todo Add UnitTest for method notation "getFilename()"

        $sequence = [
            ['id' => 1],
            ['id' => 3],
            ['id' => 5],
        ];
        $this->assertSame(PropertyPath::get('[*].id', $sequence), [1, 3, 5]);

        Notice::$enabled = false;
        $error_log = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        ob_start();
        $this->assertSame(PropertyPath::get('->property->element', $object), null);
        $this->assertRegExp('/Unexpected type: array, expecting an object/', ob_get_clean());
        ob_start();
        $this->assertSame(PropertyPath::get('->id', $array), null, 'Path "->id" should NOT work on arrays');
        $this->assertRegExp('/Unexpected type: array, expecting an object/', ob_get_clean());
        ob_start();
        $this->assertSame(PropertyPath::get('[id]', $object), null, 'Path "[id]" should NOT work on objects');
        $this->assertRegExp('/Unexpected type: object, expecting an array/', ob_get_clean());

        //		PropertyPath::get('->id?', $array)
        //		PropertyPath::get('[id?]', $object)
        Notice::$enabled = true;
        ini_set('error_log', $error_log);
    }

    public function test_PropertyPath_set()
    {
        $array = ['id' => '1'];
        $object = (object) ['id' => '2'];
        PropertyPath::set('id', 3, $array);
        $this->assertSame($array['id'], 3);
        PropertyPath::set('id', 4, $object);
        $this->assertSame($object->id, 4);
        PropertyPath::set('->id', 5, $object);
        $this->assertSame($object->id, 5);
        PropertyPath::set('[id]', 6, $array);
        $this->assertSame($array['id'], 6);
        $array['object'] = (object) ['id' => 7];
        PropertyPath::set('object->id', 8, $array);
        $this->assertSame($array['object']->id, 8);
        PropertyPath::set('[object]->id', 9, $array);
        $this->assertSame($array['object']->id, 9);
        $array['element'] = ['id' => 1];
        PropertyPath::set('element[id]', 10, $array);
        $this->assertSame($array['element']['id'], 10);
    }

    public function test_compile()
    {
        $closure = PropertyPath::compile('id');
        $item = ['id' => 8];
        $this->assertTrue(\Sledgehammer\is_closure($closure), 'compile() should return a closure');
        $this->assertSame(8, $closure($item));
    }

    public function test_map()
    {
        $source = [
            'deep' => [
                'nested' => 1,
            ],
            'value' => 2,
        ];
        $target = [];
        $mapping = [
            'dn' => 'deep.nested',
            'meta[value]' => 'value',
        ];
        PropertyPath::map($source, $target, $mapping);
        $this->assertSame(['dn' => 1, 'meta' => ['value' => 2]], $target, '');
    }
}
