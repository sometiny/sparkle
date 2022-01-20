<?php


namespace Sparkle\Http;


use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class JsonResponse extends Response
{

    private $content = null;
    private $options = 256;

    public function __construct($content, int $statusCode = 200)
    {
        parent::__construct($statusCode);
        $this->setHeader('Content-Type', 'application/json');
        $this->content = $content;
    }

    public function send()
    {
        $body = $this->content;

        if ($body instanceof \JsonSerializable) {
            $body = $body->jsonSerialize();
        } else if ($body instanceof Arrayable) {
            $body = json_encode($body->toArray(), $this->options);
        }

        if ($body instanceof Jsonable) {
            $body = $body->toJson();
        } else {
            $body = json_encode($body, $this->options);
        }

        parent::sendHeader();

        echo $body;
    }

    /**
     * @param int $options
     */
    public function setOptions(int $options): void
    {
        $this->options = $options;
    }
}
