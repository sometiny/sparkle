<?php


namespace Sparkle;


class Pipeline
{
    private array $queue = [];

    private string $via = '';

    /**
     * Pipeline constructor.
     * @param $queue
     */
    public function __construct($queue)
    {
        $this->through(...func_get_args());
    }

    private function through($queue)
    {
        $this->queue = is_array($queue) ? $queue : func_get_args();
    }

    public function via($value)
    {
        $this->via = $value;
        return $this;
    }

    /**
     * @param $context
     * @param $destination
     * @return mixed
     * @throws \Exception
     */
    public function pipe($context, $destination)
    {
        if (empty($this->queue)) {
            return $destination($context);
        }

        $next = function ($value) use ($destination) {
            return $this->pipe($value, $destination);
        };
        $item = array_shift($this->queue);
        if (is_callable($item)) return $item($context, $next);

        if (is_object($item)) return $item->{$this->via}($context, $next);

        if (is_string($item)) {
            [$name, $parameters] = array_pad(explode(':', $item, 2), 2, []);
            if (is_string($parameters)) {
                $parameters = explode(',', $parameters);
            }
            return (new $name())->{$this->via}($context, $next, ...$parameters);
        }

        if (is_array($item)) {
            $parameters = array_slice($item, 1);
            return call_user_func($item[0], [$context, $next, ...$parameters]);
        }
        throw new \Exception('unsupported pipe');
    }
}
