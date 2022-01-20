<?php


namespace Sparkle\Http;


use Sparkle\View;

class ViewResponse extends Response
{

    private string $view;
    private array $data;

    public function __construct(string $view, array $data = [], int $statusCode = 200)
    {
        parent::__construct($statusCode);
        $this->view = $view;
        $this->data = $data;
    }

    public function send()
    {
        $this->setBody(View::get($this->view, $this->data));
        return parent::send();
    }
}
