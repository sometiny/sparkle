<?php
namespace Sparkle\Logs;

class File extends \Psr\Log\AbstractLogger
{

    private string $dir;

    public function __construct(string $dir)
    {
        if(!is_dir($dir)) mkdir($dir, 0777, true);
        $this->dir = $dir;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = array())
    {
        $data = sprintf("[%s][%s]%s\r\n", date('H:i:s'), $level, $message);
        file_put_contents(
            $this->dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log',
            $data,
            FILE_APPEND | LOCK_EX);
    }
}