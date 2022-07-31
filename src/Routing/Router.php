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

    private const ACTION_LIST = 1;
    private const ACTION_SAVE = 2;
    private const ACTION_SHOW = 4;
    private const ACTION_DELETE = 8;

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
        if ($path != '/' && $path[strlen($path) - 1] == '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }

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
        $path = trim($path, '/');
        $parts = explode('/', $path);

        $result = [];
        $paramNames = [];
        foreach ($parts as $part) {
            $success = preg_match('/^{(\w+)(\?)?}$/', $part, $match, PREG_UNMATCHED_AS_NULL);
            if ($success) {
                $result[] = sprintf('%s\/(?<%s>[^\/]+)%s', $match[2] ? '(?:' : '', $match[1], $match[2] ? ')?' : '');
                $paramNames[] = ['name' => $match[1], 'required' => empty($match[2])];
                continue;
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
        $path = $req->path();
        $path = rtrim($path, '/');
        $method = $req->method();
        if (!isset(self::$routes[$method]) &&
            !isset(self::$static_routes[$method])) {
            return null;
        }


        $staticRoutes = array_merge(self::$static_routes[$method], self::$static_routes['ANY']);

        foreach ($staticRoutes as $key => $route) {
            if ($key === $path) {
                return $route;
            }
        }
        $routes = array_merge(self::$routes[$method], self::$routes['ANY']);

        foreach ($routes as $key => $route) {
            if (!preg_match($key, $path, $match)) continue;

            $paramNames = $route->getParamNames();
            $params = [];
            foreach ($paramNames as $param) {
                $params[$param['name']] = $match[$param['name']] ?? null;
            }
            if (!$route->checkConditions($params)) continue;

            $req->setParams($params);
            return $route;
        }

        while (true) {
            $pattern = $path . '/*';
            if (isset($staticRoutes[$pattern])) return $staticRoutes[$pattern];
            $lastIndex = strrpos($path, '/');
            if ($lastIndex === false) break;
            $path = substr($path, 0, $lastIndex);
        }
        if (isset($staticRoutes['*'])) return $staticRoutes['*'];

        return null;

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
        return self::addRoute(strtoupper($name), ...$arguments);
    }

    public static function getRegisteredRoutes()
    {
        return ['statics' => self::$static_routes, 'routes' => self::$routes];
    }

    public static function mixin($name, $allowedAction = 15)
    {
        $controllerName = Str::studly($name) . 'Controller';
        if( $allowedAction & static::ACTION_LIST ) Router::get($name, $controllerName . '@index');
        if( $allowedAction & static::ACTION_SAVE ) Router::post($name . '/{id?}', $controllerName . '@save')->where('id', '[0-9]+');
        if( $allowedAction & static::ACTION_SHOW ) Router::get($name . '/{id}', $controllerName . '@show')->where('id', '[0-9]+');
        if( $allowedAction & static::ACTION_DELETE ) Router::delete($name . '/{id}', $controllerName . '@delete')->where('id', '[0-9]+');
    }
}
