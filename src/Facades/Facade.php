<?php


namespace Sparkle\Facades;


use Jazor\Console;
use Jazor\NotImplementedException;

abstract class Facade
{
    private static $resolved = [];

    protected static function getClass()
    {
        throw new NotImplementedException();
    }
    protected static function instance($accessor, $instance = null){
        if($instance === null){
            self::$resolved[static::getClass()] = $accessor;
            return;
        }
        self::$resolved[$accessor] = $instance;
    }

    protected static function createInstance($accessor)
    {
        return new $accessor();
    }

    private static function getInstance($accessor)
    {
        if (isset(self::$resolved[$accessor])) {
            return self::$resolved[$accessor];
        }
        $instance = static::createInstance($accessor);
        self::$resolved[$accessor] = $instance;
        return $instance;
    }

    public static function __callStatic($name, $arguments)
    {
        $accessor = static::getClass();
        $instance = self::getInstance($accessor);
        return $instance->{$name}(...$arguments);
    }
}
