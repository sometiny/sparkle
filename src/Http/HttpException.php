<?php


namespace Sparkle\Http;


use think\helper\Str;
use Throwable;

class HttpException extends \Exception
{

    private int $statusCode;

    /**
     * @return Response
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
    public function __construct(int $statusCode, string $message, Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, 0, $previous);
    }
}
