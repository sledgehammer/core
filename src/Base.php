<?php

namespace Sledgehammer\Core;

use ReflectionObject;

/**
 * A generic php superclass.
 *
 * Improved error reporting:
 *   When accessing non-existing properties show a list show a list of available properties.
 *   When calling a non-existing method show a list show a list of available methods.
 *
 * Changes compared to PHP's stdClass behavior:
 *   Generates a warning when setting a non-yet-existing property (instead of silently adding the property)
 *   Throws an Exception when calling a non-existing method (instead of a fatal error)
 *   Generates a notice when the object is used as a string (instead of throwing an exception)
 */
abstract class Base
{
    /**
     * Report that $property doesn't exist.
     *
     * @param string $property
     */
    public function __get($property)
    {
        \Sledgehammer\warning('Property "'.$property.'" doesn\'t exist in a '.get_class($this).' object', \Sledgehammer\build_properties_hint(\Sledgehammer\reflect_properties($this)));
    }

    /**
     * Report that $property doesn't exist and set the property to the given $value.
     *
     * @param string $property
     * @param mixed  $value
     */
    public function __set($property, $value)
    {
        \Sledgehammer\warning('Property "'.$property.'" doesn\'t exist in a '.get_class($this).' object', \Sledgehammer\build_properties_hint(\Sledgehammer\reflect_properties($this)));
        $this->$property = $value; // Add the property to the object. (PHP's default behavior)
    }

    /**
     * Report that the $method doesn't exist.
     *
     * @param string $method
     * @param array  $arguments
     */
    public function __call($method, $arguments)
    {
        $rObject = new ReflectionObject($this);
        $methods = [];
        foreach ($rObject->getMethods() as $rMethod) {
            if (in_array($rMethod->name, ['__get', '__set', '__call', '__toString'])) {
                continue;
            }
            if ($rMethod->isPublic()) {
                $scope = 'public';
            } elseif ($rMethod->isProtected()) {
                $scope = 'protected';
            } elseif ($rMethod->isPrivate()) {
                $scope = 'private';
            }
            $parameters = [];
            foreach ($rMethod->getParameters() as $rParam) {
                $param = '$'.$rParam->name;
                if ($rParam->isDefaultValueAvailable()) {
                    $param .= ' = '.\Sledgehammer\syntax_highlight($rParam->getDefaultValue());
                }
                $parameters[] = $param;
            }
            $methods[$scope][] = \Sledgehammer\syntax_highlight($rMethod->name, 'method').'('.implode(', ', $parameters).')';
        }
        $methodsText = '';
        $glue = '<br />&nbsp;&nbsp;';
        foreach ($methods as $scope => $mds) {
            $methodsText .= '<b>'.$scope.' methods</b>'.$glue.implode($glue, $mds).'<br /><br />';
        }
        throw new InfoException('Method: "'.$method.'" doesn\'t exist in a "'.get_class($this).'" object.', $methodsText);
    }

    /**
     * The object is used as an string.
     *
     * @return string
     */
    public function __toString()
    {
        \Sledgehammer\notice('Object: "'.get_class($this).'" is used as string');

        return 'Object('.get_class($this).')';
    }
}
