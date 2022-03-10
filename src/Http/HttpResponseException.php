<?php


namespace Sparkle\Http;


use Throwable;

class HttpResponseException extends \Exception
{

    private Response $response;

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
    public function __construct(Response $response, Throwable $previous = null)
    {
        $this->response = $response;
        parent::__construct('', 0, $previous);
    }
}
