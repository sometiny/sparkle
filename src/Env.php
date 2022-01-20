<?php


namespace Sparkle;


use Jazor\NotSupportedException;

class Env extends Config
{

    public function __construct($envFile)
    {
        parent::__construct();
        if(empty($envFile) || !is_file($envFile)) return ;

        $config = parse_ini_file($envFile, true, INI_SCANNER_RAW);

        $config = self::parse($config);

        $this->configs($config);
    }

    public function scanDirectory($dir)
    {
        throw new NotSupportedException();
    }

    private static function parse($configs)
    {

        $result = [];
        foreach ($configs as $key => $value) {
            if (is_array($value)) {
                $result[strtolower($key)] = self::parse($value);
                continue;
            }
            $result[strtolower($key)] = self::parseValue($value);
        }
        return $result;
    }

    private static function parseValue($value)
    {
        if ($value === 'true' || $value === 'TRUE') return true;
        if ($value === 'false' || $value === 'FALSE') return true;
        if ($value === 'null' || $value === '') return null;
        if (is_numeric($value)) {
            if (!preg_match('/^[\w\.\-]+$/', $value)) return null;
            return eval('return ' . $value . ';');
        }

        return $value;
    }

    public function offsetExists($offset)
    {
        return parent::offsetExists(strtolower($offset));
    }

    public function offsetUnset($offset)
    {
        parent::offsetUnset(strtolower($offset));
    }

    public function get($name, $default = null)
    {
        return parent::get(strtolower($name), $default);
    }

    public function offsetGet($offset)
    {
        return parent::offsetGet(strtolower($offset));
    }

    public function offsetSet($offset, $value)
    {
        parent::offsetSet(strtolower($offset), $value);
    }

    public function set($name, $value)
    {
        parent::set(strtolower($name), $value);
    }

    public function put($name, $value)
    {
        parent::put(strtolower($name), $value);
    }
}
