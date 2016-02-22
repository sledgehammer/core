<?php

namespace Sledgehammer\Core;

use Closure;
use Exception;
use ReflectionClass;

/**
 * Adds singleton behavior to a class.
 * 
 * Example: Get the default instance
 *   $db = Connection::instance(); 
 * 
 * Example: Set/overwrite the default instance
 *   Connection::$instances['default'] = new Connection('mysql://localhost'));
 * 
 * Example: Lazily configure the singleton via closure
 *   Connection::$instances['default'] = function () {
 *     return new Connection('mysql://localhost');
 *   };
 * 
 * Example: Lazily configure the singleton in the class
 *   class MyClass {
 *     use Singleton;
 *     protected static defaultInstance() {
 *       return new MyClass(['configured' => 'with defaults']);
 *     }
 *   } 
 */
trait Singleton
{
    /**
     * The instances that are accessible by class::instance()
     * [
     *   'id' => instance, // direct mapping to an instance
     *   'id2' => 'id' // indirect mapping to an instance
     *   'id3' => function () { return new Sington() }  // lazy creation of an instance.
     * ].
     *
     * @var static[]
     */
    public static $instances = [];

    /**
     * Get the singleton instance.
     *
     * @param string $identifier The identifier (string), 
     * @param static [$instance] New value for the identifier.
     *
     * @return static
     */
    public static function instance($identifier = 'default')
    {
        if ($identifier instanceof static) { // Given identfier already is an instance?
            return $identifier;
        }
        if (array_key_exists($identifier, static::$instances) === false) {
            if ($identifier === 'default') {
                $instance = static::defaultInstance();
                static::$instances['default'] = $instance;

                return $instance;
            }
            throw new InfoException(static::class.'::instances["'.$identifier.'"] is not configured', 'Available instances: '.\Sledgehammer\quoted_human_implode(' or ', array_keys(static::$instances)));
        }
        $connection = static::$instances[$identifier];
        if ($identifier instanceof static) {
            return $identifier;
        }
        if (is_string($connection)) { // A reference to another instance?
            return static::instance($connection);
        }
        if ($connection instanceof Closure) { // A closure which creates the instance?
            static::$instances[$identifier] = call_user_func($connection);

            return static::instance($identifier);
        }

        return $connection;
    }

    /**
     * Create a default instance.
     * Override in the (sub)class to create an instance with default parameters.
     *
     * @return static
     */
    protected static function defaultInstance()
    {
        $class = new ReflectionClass(static::class);
        $constructor = $class->getConstructor();
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isOptional() === false) {
                    throw new Exception(static::class.'::instances["default"] is not configured');
                }
            }
        }

        return new static();
    }
}
