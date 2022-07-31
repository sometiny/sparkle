<?php


namespace Sparkle\Logs;

use Psr\Log\LogLevel;

/**
 * Class Logger
 * @package Sparkle\Logs
 */
class Logger
{
    private static array $loggers = [];

    /**
     * @param $message
     * @param string $level
     * @param string $channel
     */
    public function log($message, $level = LogLevel::INFO, $channel = 'default')
    {
        if (isset(self::$loggers[$channel])) {
            $logger = self::$loggers[$channel];
        } else {
            $config = config('log');
            if (empty($config)) {
                $channelDir = APP_PATH . DS . 'logs';
            } else {
                $channelDir = $config['channels'][$channel]['path'];
            }
            $logger = new File($channelDir);
        }
        $logger->log($level, $message);
    }

    public function error($message)
    {
        $this->log($message, LogLevel::ERROR, 'default');
    }

    public function notice($message)
    {
        $this->log($message, LogLevel::NOTICE, 'default');
    }

    public function info($message)
    {
        $this->log($message, LogLevel::INFO, 'default');
    }
}
