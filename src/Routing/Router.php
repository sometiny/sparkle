<?php


namespace Sparkle\Routing;


use Jazor\NotSupportedException;
use Sparkle\Application;
use Sparkle\Facades\Request;
use Sparkle\Http\Response;
use Sparkle\Pipeline;
use think\helper\Str;

/**
 * Class Router
 * @package Sparkle\Routing
 * @method static Route get(string|array $path, string|array|\Closure $action, $options = null)
 * @method static Route post(string|array $path, string|array|\Closure $action, $options = null)
 * @method static Route put(string|array $path, string|array|\Closure $action, $options = null)
 * @method static Route delete(string|array $path, string|array|\Closure $action, $options = null)
 * @method static Route head(string|array $path, string|array|\Closure $action, $options = null)
 * @method static Route options(string|array $path, string|array|\Closure $action, $options = null)
 */

class Router
{
    private static ?RouteStore $defaultStore = null;

    private static array $hosts = [];

    public const ACTION_LIST = 1;
    public const ACTION_SAVE = 2;
    public const ACTION_SHOW = 4;
    public const ACTION_DELETE = 8;
    public const ACTION_UPDATE = 16;

    private static array $groupStack = [];

    private static Route $current;

    public static function host(string $hostName, \Closure $callback)
    {
        static::group(['host' => $hostName], $callback);
    }

    /**
     * @param $path
     * @param null $options
     * @param null $callback
     * @throws \Exception
     */
    public static function group($path, $options = null, $callback = null)
    {
        if ($path instanceof \Closure) {
            $callback = $path;
            $path = null;
            $options = [];
        } else if ($options instanceof \Closure) {
            $callback = $options;
            if (is_string($path)) {
                $options = [];
            } else {
                $options = $path;
                $path = null;
            }
        }
        if (!is_array($options)) {
            throw new \Exception('need array option');
        }
        if (!($callback instanceof \Closure)) {
            throw new \Exception('need Closure');
        }

        if(isset($options['host'])) $options['host'] = strtolower($options['host']);

        $group = self::getGroup();

        $group = $group ? $group->makeGroup($path, $options) : new Group($path, $options);
        self::$groupStack[] = $group;
        $callback();
        array_pop(self::$groupStack);
    }

    private static function addRoute($method, $path, $action, $options = null)
    {
        $isRegex = $path[0] === '#' || !empty($options) && isset($options['regexp']) && $options['regexp'] === true;

        $group = self::getGroup();

        $action = $group ? $group->makeAction($action) : $action;

        if($isRegex){

            $route = new Route($method, $path, $action, $group);
            self::storeRoute($method, $path, $route);
            return $route;
        }

        if($path === '*') $path = '{*}';

        $path = $group ? $group->makePath($path) : $path;
        if ($path != '/' && $path[strlen($path) - 1] == '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }

        if (strpos($path, '{') === false) {
            $route = new Route($method, $path, $action, $group);
            self::storeRoute($method, $path, $route, true);
            return $route;
        }
        $regexp = self::compilePath2RegExp($path, $params);
        $route = new Route($method, $path, $action, $group);
        $route->setParamNames($params);
        self::storeRoute($method, $regexp, $route);
        return $route;
    }

    private static function storeRoute(string $method, string $path, Route $route, bool $static = false)
    {

        $group = $route->getGroup();
        $groupHost = $group ? $group->getOptions()['host'] ?? '' : '';

        if(!empty($groupHost)){
            if(!isset(static::$hosts[$groupHost])) static::$hosts[$groupHost] = new RouteStore();
            static::$hosts[$groupHost]->store($method, $path, $route, $static);
            return;
        }

        if(static::$defaultStore === null) static::$defaultStore = new RouteStore();
        static::$defaultStore->store($method, $path, $route, $static);
    }

    /**
     * @param $path
     * @param $paramNames
     * @return string
     */
    public static function compilePath2RegExp($path, &$paramNames)
    {
        $path = trim($path, '/');
        $parts = explode('/', $path);

        $result = [];
        $paramNames = [];
        foreach ($parts as $part) {
            $success = preg_match('/^{([\w\-]+)(\?)?}$/', $part, $match, PREG_UNMATCHED_AS_NULL);
            if ($success) {
                $result[] = sprintf('%s\/(?<%s>[^\/]+)%s', $match[2] ? '(?:' : '', $match[1], $match[2] ? ')?' : '');
                $paramNames[] = ['name' => $match[1], 'required' => empty($match[2])];
                continue;
            }
            if($part === '{*}'){
                $result[] = '(\/(.*))?';
                break;
            }
            if (strpos($part, '{') !== false) {

                $result[] = self::compileComplexPart($part, $paramNames);
                continue;
            }
            $result[] = '\/' . $part;
        }

        return '/^' . implode('', $result) . '$/';
    }

    private static function compileComplexPart($part, &$paramNames)
    {
        $index = 0;
        $length = strlen($part);

        $result = ['\/'];
        while ($index < $length) {
            $success = preg_match('/{([\w\-]+)}/', $part, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $index);
            if (!$success) {
                break;
            }
            $findIndex = $match[0][1];
            if ($findIndex > $index) {
                $result[] = self::getValidRegExpExpression(substr($part, $index, $findIndex - $index));
            }
            $index = $findIndex + strlen($match[0][0]);

            $paramName = $match[1][0];
            $result[] = sprintf('(?<%s>[\w\-]+?)', $paramName);
            $paramNames[] = ['name' => $paramName, 'required' => true];;
        }
        if ($index < $length) {
            $result[] = self::getValidRegExpExpression(substr($part, $index));
        }

        return implode('', $result);
    }

    private static function getValidRegExpExpression($source)
    {

        return str_replace(['.', '-'], ['\.', '\-'], $source);
    }

    /**
     * @return Route
     */
    public static function current(): Route
    {
        return self::$current;
    }

    private static function match(\Sparkle\Http\Request $req)
    {
        $hostName = $req->hostName();

        /**
         * 严格匹配
         */
        if(isset(static::$hosts[$hostName])){
            $route = static::$hosts[$hostName]->match($req);
            if($route !== null) return $route;
        }

        /**
         * 通配符匹配
         */
        $wildcardHostName = preg_replace('#^([\w\-]+?)\.#', '*.', $hostName);
        if($wildcardHostName !== $hostName) {
            if(isset(static::$hosts[$wildcardHostName])){
                $route = static::$hosts[$wildcardHostName]->match($req);
                if($route !== null) return $route;
            }
        }

        /**
         * 尾部匹配
         */
        $hostName = '.' . $hostName;
        foreach (static::$hosts as $key => $store){
            if($key[0] !== '.' || !Str::endsWith($hostName, $key)) continue;

            $route = $store->match($req);
            if($route !== null) return $route;
            break;
        }

        /**
         * 默认匹配
         */
        if(static::$defaultStore === null) return null;

        return static::$defaultStore->match($req);
    }

    public static function dispatch(Application $app, \Sparkle\Http\Request $req)
    {

        /**
         * @var Route $route
         */
        $route = self::match($req);
        if ($route === null) {
            return new Response(404, '404 Not Found');
        }
        self::$current = $route;
        $pipe = new Pipeline($route->getMiddleware());
        $pipe->via('handle');

        return $pipe->pipe($req, function ($req) use ($route) {
            return $route->execute($req);
        });
    }

    /**
     * @return Group|null
     */
    private static function getGroup()
    {
        $group = end(self::$groupStack);
        return $group === false ? null : $group;
    }

    public static function __callStatic($name, $arguments)
    {
        if (!in_array($name, ['head', 'get', 'post', 'put', 'delete', 'options', 'any'])) throw new NotSupportedException();

        if (count($arguments) > 0 && is_array($arguments[0])) {
            $paths = $arguments[0];
            foreach ($paths as $path) {
                $arguments[0] = $path;
                self::addRoute(strtoupper($name), ...$arguments);
            }
            return null;
        }
        return self::addRoute(strtoupper($name), ...$arguments);
    }

    public static function mixin($name, $allowedAction = 15)
    {
        $controllerName = Str::studly($name) . 'Controller';
        if( $allowedAction & static::ACTION_LIST ) Router::get($name, $controllerName . '@index');
        if( $allowedAction & static::ACTION_SAVE ) Router::post($name . '/{id?}', $controllerName . '@save')->where('id', '[0-9]+');
        if( $allowedAction & static::ACTION_SHOW ) Router::get($name . '/{id}', $controllerName . '@show')->where('id', '[0-9]+');
        if( $allowedAction & static::ACTION_DELETE ) Router::delete($name . '/{id}', $controllerName . '@delete')->where('id', '[0-9]+');
    }

    /**
     * 生成路由组
     * @param $name
     * @param null $path
     * @param int $allowedAction
     * @param string $whereId
     * @return void
     */
    public static function mixin2($name, $path = null, $allowedAction = 31, $whereId = '[0-9]+')
    {
        $controllerName = Str::studly($name) . 'Controller';
        if(empty($path)) $path = $name;
        if( $allowedAction & static::ACTION_LIST ) Router::get($path, $controllerName . '@index');
        if( $allowedAction & static::ACTION_SAVE ) Router::post($path, $controllerName . '@create');
        if( $allowedAction & static::ACTION_UPDATE ) Router::put($path . '/{id}', $controllerName . '@update')->where('id', $whereId);
        if( $allowedAction & static::ACTION_SHOW ) Router::get($path . '/{id}', $controllerName . '@show')->where('id', $whereId);
        if( $allowedAction & static::ACTION_DELETE ) Router::delete($path . '/{id}', $controllerName . '@destroy')->where('id', $whereId);
    }
}
