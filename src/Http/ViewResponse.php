<?php


namespace Sparkle\Http;


use Sparkle\View;

class ViewResponse extends Response
{

    private \Illuminate\View\View $view;

    public function __construct(string $view, array $data = [], int $statusCode = 200)
    {
        parent::__construct($statusCode);

        $this->view = View::getViewInstance($view, $data);
    }

    public function send()
    {
        $this->setBody($this->view->render());
        return parent::send();
    }

    /**
     * @return \Illuminate\View\View
     */
    public function getView(): \Illuminate\View\View
    {
        return $this->view;
    }
}
