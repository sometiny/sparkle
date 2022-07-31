<?php


namespace Sparkle\Facades;


use Jazor\Console;
use Psr\Log\LogLevel;

/**
 * Class Request
 * @package Sparkle\Facades
 * @method static log($message, $level = LogLevel::INFO, $channel = 'default')
 * @method static error($message)
 * @method static notice($message)
 * @method static info($message)
 * @see \Sparkle\Logs\Logger
 */
class Log extends Facade
{
    protected static function getClass()
    {
        return \Sparkle\Logs\Logger::class;
    }
}
