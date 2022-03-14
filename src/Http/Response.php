<?php


namespace Sparkle\Http;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Jazor\Http\Headers;
use Jazor\WebFarm\HttpStatus;

class Response extends Headers
{

    private int $statusCode;
    private $body = null;

    /**
     * Response constructor.
     * @param int $statusCode
     * @param null $body
     */
    public function __construct(int $statusCode = 200, $body = null)
    {
        $this->setHeader('Content-Type', 'text/html');
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    protected function sendHeader(){
        $header = HttpStatus::getStatusHeader($this->statusCode);
        header($header);

        $headers = $this->getAllHeadersArray();
        foreach ($headers as $header){
            header($header);
        }
        if(defined('SPARKLE_START')){
            header('Sparkle-Time-Taken: ' . intval((microtime(true) - SPARKLE_START) * 1000));
        }
    }

    public function send(){
        $this->sendHeader();
        if(empty($this->body)) return;
        echo $this->body;
    }

    /**
     * @param null $body
     */
    public function setBody($body): void
    {
        $this->body = $body;
    }

    /**
     * @return null
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int|int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
