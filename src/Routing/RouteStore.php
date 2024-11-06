<?php


namespace Sparkle\Routing;


use Jazor\NotSupportedException;
use Sparkle\Application;
use Sparkle\Facades\Request;
use Sparkle\Http\Response;
use Sparkle\Pipeline;
use think\helper\Str;

class RouteStore
{
    private array $routes = [
        'GET' => [],
        'HEAD' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'ANY' => [],
    ];
    private array $static_routes = [
        'GET' => [],
        'HEAD' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'OPTIONS' => [],
        'ANY' => [],
    ];

    public function getRoutes()
    {
        return ['statics' => self::$static_routes, 'routes' => self::$routes];
    }


    public function store(string $method, string $path, Route $route, bool $static = false)
    {
        if($static) {
            $this->static_routes[$method][$path] = $route;
            return;
        }
        $this->routes[$method][$path] = $route;
    }
    public function match(\Sparkle\Http\Request $req)
    {
        $path = $req->path();
        $path = rtrim($path, '/');
        $method = $req->method();

        $registeredRoutes = $this->routes;
        $registeredStaticRoutes = $this->static_routes;

        if (!isset($registeredRoutes[$method]) &&
            !isset($registeredStaticRoutes[$method])) {
            return null;
        }

        if(empty($path)) $path = '/';

        $staticRoutes = array_merge($registeredStaticRoutes[$method], $registeredStaticRoutes['ANY']);

        if(isset($staticRoutes[$path])) return $staticRoutes[$path];

        $routes = array_merge($registeredRoutes[$method], $registeredRoutes['ANY']);

        /**
         * @var $route Route
         */
        foreach ($routes as $key => $route) {
            if (!preg_match($key, $path, $match)) continue;

            $paramNames = $route->getParamNames();
            if(empty($paramNames)){
                $req->setParams($match);
                return $route;
            }
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
}
