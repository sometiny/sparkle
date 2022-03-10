<?php


namespace Sparkle\Facades;


use Jazor\Console;

/**
 * Class Request
 * @package Sparkle\Facades
 * @method static server($name = null)
 * @method static ip()
 * @method static path()
 * @method static method()
 */
class Request extends Facade
{
    public static function current(){
        return \Sparkle\Http\Request::current();
    }
    protected static function createInstance($accessor)
    {
        return self::current();
    }

    protected static function getClass()
    {
        return \Sparkle\Http\Request::class;
    }
}
