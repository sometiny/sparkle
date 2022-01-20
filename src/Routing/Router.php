<?php


namespace Sparkle\Routing;


use Jazor\NotSupportedException;
use Sparkle\Facades\Request;
use Sparkle\Http\Response;

/**
 * Class Router
 * @package Sparkle\Routing
 * @method static Route get(string $path, string|array|\Closure $action)
 * @method static Route post(string $path, string|array|\Closure $action)
 * @method static Route put(string $path, string|array|\Closure $action)
 * @method static Route delete(string $path, string|array|\Closure $action)
 * @method static Route head(string $path, string|array|\Closure $action)
 * @method static Route options(string $path, string|array|\Closure $action)
 */

class Router
{
    private static array $routes = [
        'GET' => [],
        'HEAD' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'ANY' => [],
    ];
    private static array $static_routes = [
        'GET' => [],
        'HEAD' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'ANY' => [],
    ];

    private static array $groupStack = [];

    private static Route $current;


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
        $group = self::getGroup();

        $group = $group ? $group->makeGroup($path, $options) : new Group($path, $options);
        self::$groupStack[] = $group;
        $callback();
        array_pop(self::$groupStack);
    }

    private static function addRoute($method, $path, $action)
    {
        $group = self::getGroup();

        $path = $group ? $group->makePath($path) : $path;

        $action = $group ? $group->makeAction($action) : $action;

        if (strpos($path, '{') === false) {
            $route = new Route($method, $path, $action, $group);
            self::$static_routes[$method][$path] = $route;
            return $route;
        }
        $regexp = self::compilePath2RegExp($path, $params);
        $route = new Route($method, $path, $action, $group);
        $route->setParamNames($params);
        self::$routes[$method][$regexp] = $route;
        return $route;
    }

    /**
     * @param $path
     * @param $paramNames
     * @return string
     */
    public static function compilePath2RegExp($path, &$paramNames)
    {
        $parts = explode('/', $path);

        $result = [];
        $paramNames = [];
        foreach ($parts as $part) {
            $success = preg_match('/^{(\w+)(\?)?}$/', $part, $match, PREG_UNMATCHED_AS_NULL);
            if ($success) {
                $result[] = sprintf('%s\/(?<%s>[^\/]+)%s', $match[2] ? '(?:' : '', $match[1], $match[2] ? ')?' : '');
                $paramNames[] = $match[1];
                continue;
            }
            if (strpos($part, '{') !== false) {

                $result[] = self::compileComplexPart($part, $paramNames);
                continue;
            }
            $result[] = '\/' . $part;
        }

        return '/' . implode('', $result) . '/';
    }

    private static function compileComplexPart($part, &$paramNames)
    {
        $index = 0;
        $length = strlen($part);

        $result = ['\/'];
        while ($index < $length) {
            $success = preg_match('/{(\w+)}/', $part, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $index);
            if (!$success) {
                break;
            }
            $findIndex = $match[0][1];
            if ($findIndex > $index) {
                $result[] = self::getValidRegExpExpression(substr($part, $index, $findIndex - $index));
            }
            $index = $findIndex + strlen($match[0][0]);

            $paramName = $match[1][0];
            $result[] = sprintf('(?<%s>\w+?)', $paramName);
            $paramNames[] = $paramName;
        }
        if ($index < $length) {
            $result[] = self::getValidRegExpExpression(substr($part, $index));
        }

        return implode('', $result);
    }

    private static function getValidRegExpExpression($source){

        return str_replace(['.', '-'], ['\.', '\-'], $source);
    }

    /**
     * @return Route
     */
    public static function current(): Route
    {
        return self::$current;
    }

    private static function match()
    {
        $req = request();
        $path = $req->path();
        $method = $req->method();
        if (!isset(self::$routes[$method]) &&
            !isset(self::$static_routes[$method])) {
            return null;
        }

        $routes = array_merge(self::$static_routes[$method], self::$static_routes['ANY']);

        foreach ($routes as $key => $route) {
            if ($key === $path) {
                return $route;
            }
        }
        $routes = array_merge(self::$routes[$method], self::$routes['ANY']);

        foreach ($routes as $key => $route) {
            if (!preg_match($key, $path, $match)) continue;

            $paramNames = $route->getParamNames();
            $params = [];
            foreach ($paramNames as $name) {
                $params[$name] = $match[$name] ?? null;
            }
            $req->setParams($params);
            return $route;
        }

        return null;

    }

    public static function dispatch()
    {

        /**
         * @var Route $route
         */
        $route = self::match();
        if ($route === null) {
            return new Response(404, '404 Not Found');
        }
        $req = \request();
        if(!$route->checkConditions($req->getParams())){
            return new Response(404, '404 Not Found');
        }
        self::$current = $route;
        return $route->execute(\request());
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
        if(!in_array($name, ['head', 'get', 'post', 'put', 'delete', 'options', 'any'])) throw new NotSupportedException();
        return self::addRoute(strtoupper($name), ...$arguments);
    }
}
