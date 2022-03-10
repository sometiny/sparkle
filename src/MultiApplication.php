<?php


namespace Sparkle;


use Jazor\Path;
use Sparkle\Http\Response;

class MultiApplication
{
    private static array $apps = [];
    private static array $wildcard_apps = [];
    private static ?string $location = null;


    public static function group($host, $domain, $app, $className = 'App'){
        if(!is_array($host)) $host = [$host];
        foreach ($host as $h){
            self::register($h . '.' . $domain, $app, $className);
        }
    }

    public static function register($host, $app, $className = 'App')
    {

        if (is_array($host)) {
            foreach ($host as $h) {
                self::register($h, $app, $className);
            }
            return;
        }

        if(!empty(self::$location)){
            $app = Path::format(self::$location) . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'app.php';
        }

        if ($host[0] === '/' || strpos($host, '*') !== false) {
            self::$wildcard_apps[self::compile('.' . $host)] = [$app, $className];
            return;
        }
        self::$apps[$host] = [$app, $className];
    }

    private static function compile($host){
        return '/^' . str_replace(['.', '*'], ['\\.', '([^\\.]+)'], $host) . '$/';
    }

    private static function findWildcardApp($host){
        foreach (self::$wildcard_apps as $exp => $file){
            if(preg_match($exp, $host)) return $file;
        }
        return null;
    }

    public static function getApp($host = null){
        $host = $host ?? $_SERVER['HTTP_HOST'];

        $entry = self::$apps[$host] ?? self::findWildcardApp('.' . $host) ?? null;

        list($file, $className) = $entry;

        if(empty($file) || !is_file($file)){
            $response = new Response(404);
            $response->setBody('can not find app');
            $response->send();
            return false;
        }

        include_once $file;
        return new $className();
    }

    /**
     * @param string|null $location
     */
    public static function setLocation(?string $location): void
    {
        self::$location = $location;
    }
}
