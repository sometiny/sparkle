<?php


namespace Sparkle;


class Paginator extends \think\Paginator
{

    public function toArray(): array
    {
        try {
            $total = $this->total();
        } catch (\DomainException $e) {
            $total = null;
        }

        return [
            'total' => $total,
            'limit' => $this->listRows(),
            'current' => $this->currentPage(),
            'pages' => $this->lastPage,
            'rows' => $this->items->toArray(),
        ];
    }

    public function render()
    {
        // TODO: Implement render() method.
    }
}
