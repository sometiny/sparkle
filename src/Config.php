<?php


namespace Sparkle;


use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * config manager
 * @package Sparkle
 */
class Config implements \ArrayAccess
{

    private array $configs = [];

    /**
     * Config constructor.
     * @param null $configs
     */
    public function __construct($configs = null)
    {
        if($configs !== null) $this->configs = $configs;
    }

    /**
     * load all config from directory
     * @param $dir
     * @return Config
     */
    public function scanDirectory($dir)
    {
        if (!is_dir($dir)) return $this;
        dir_get_all_files($dir, $files);
        foreach ($files as $file) {
            if (!Str::endsWith($file, '.php')) continue;
            $name = Str::before($file, '.php');
            $this->parseConfig($name, $dir . DIRECTORY_SEPARATOR . $file);
        }
        return $this;
    }

    /**
     * parse single config file
     * @param $name
     * @param $file
     */
    private function parseConfig($name, $file)
    {

        $conf = require_once $file;
        $paths = explode(DIRECTORY_SEPARATOR, $name);
        if (count($paths) === 1) {
            $this->configs[$name] = $conf;
            return;
        }
        $paths = array_reverse($paths);
        foreach ($paths as $path) {
            $conf = [$path => $conf];
        }
        $this->configs = array_merge($this->configs, $conf);
    }

    /**
     * fetch config value
     * @param $name
     * @param null $default
     * @return array|mixed
     */
    public function get($name, $default = null)
    {
        $names = explode('.', $name);
        $configs = $this->configs;
        foreach ($names as $name) {
            if (!is_array($configs) || !isset($configs[$name])) return $default;
            $configs = $configs[$name];
        }
        return $configs;
    }

    /**
     * set config value,  with value merge
     * @param string $name config name, dot(.) allowed
     * @param mixed $value config value
     */
    public function put($name, $value)
    {
        $paths = explode('.', $name);
        $paths = array_reverse($paths);
        $conf = $value;
        foreach ($paths as $path) {
            $conf = [$path => $conf];
        }
        $this->configs = array_merge_recursive($this->configs, $conf);
    }

    /**
     * set top level config value, with value override
     * @param string $name config name
     * @param mixed $value config value
     */
    public function set($name, $value)
    {
        $this->configs[$name] = $value;
    }

    public function offsetExists($offset)
    {
        $names = explode('.', $offset);
        $configs = $this->configs;
        foreach ($names as $name) {
            if (!is_array($configs) || !isset($configs[$name])) return false;
            $configs = $configs[$name];
        }
        return true;
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->put($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $names = explode('.', $offset);
        $configs = &$this->configs;

        foreach ($names as $index => $name) {
            if (!is_array($configs) || !isset($configs[$name])) return;
            if ($index === count($names) - 1) {
                unset($configs[$name]);
                return;
            }
            $configs = &$configs[$name];
        }
    }

    /**
     * @param null $values
     * @return array|void
     */
    public function configs($values = null)
    {
        if($values === null) return $this->configs;

        if(!is_array($values)) return;

        foreach ($values as $key => $value){
            $this->configs[$key] = $value;
        }
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }
}
