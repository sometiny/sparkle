<?php


namespace Sparkle\Routing;


class Group
{

    private ?string $path;
    private array $options;

    public function __construct(?string $path, array $options)
    {

        $this->path = $path;
        $this->options = $options;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function makePath($subPath)
    {
        return $this->path ? rtrim($this->path, '/') . '/' . $subPath : $subPath;
    }
    public function makeGroup($path, $options)
    {
        $path = $this->path ? rtrim($this->path, '/') . '/' . $path : $path;
        $options = array_merge($this->options, $options);
        return new Group($path, $options);
    }

    public function makeAction($action){

        if (is_string($action) && $action[0] !== '\\' && isset($this->options['namespace'])) {
            $action = rtrim($this->options['namespace'], '\\') . '\\' . $action;
        } else if (is_array($action) && $action[0][0] !== '\\' && isset($this->options['namespace'])) {
            $action[0] = rtrim($this->options['namespace'], '\\') . '\\' . $action[0];
        }
        return $action;
    }
}
