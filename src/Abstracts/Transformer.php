<?php


namespace Sparkle\Abstracts;

use think\helper\Str;

abstract class Transformer
{
    private string $type;

    public function __construct(string $type = '')
    {
        $this->type = $type;
    }

    protected function is($type)
    {
        return $this->type === $type;
    }


    public final function doTransform($item)
    {
        if (empty($this->type)) {
            return $this->transform($item);
        }
        $type = Str::studly($this->type);
        $method = 'transform' . $type;
        if (!method_exists($this, $method)) {
            $method = 'transform';
        }
        return $this->{$method}($item);
    }

    public function transform($item)
    {
        throw new \RuntimeException('transform not implemented');
    }


    public function __get($name)
    {
        return $this->__call($name, null);
    }

    public function __call($name, $arguments)
    {
        if (strlen($name) <= 2 || substr($name, 0, 2) !== 'is') {
            throw new \RuntimeException('method not found');
        }

        $name = substr($name, 2);
        $name = Str::snake($name);

        return $this->is($name);
    }
}
