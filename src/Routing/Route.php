<?php


namespace Sparkle\Routing;


use Jazor\Console;
use Sparkle\Http\HttpException;
use Sparkle\Http\Request;
use Sparkle\Http\Response;
use think\Model;

class Route
{

    private $method;
    private $path;
    private $action;
    private ?Group $group;
    private $name = null;

    private array $paramNames = [];

    private array $conditions = [];
    private array $middleware = [];

    public function __construct($method, $path, $action, ?Group $group)
    {
        $this->method = $method;
        $this->path = $path;
        $this->action = $action;
        $this->group = $group;
        if($group != null){
            $this->middleware($group->getMiddleware());
        }
    }

    public function middleware($middleware){
        $middleware = (array)$middleware;
        foreach ($middleware as $value){
            if(is_string($value)){
                $this->middleware[] = app()->getMiddleware($value);
                continue;
            }
            $this->middleware[] = $value;
        }
        return $this;
    }

    /**
     * @param $param
     * @param $condition
     * @return $this
     */
    public function where($param, $condition)
    {
        $this->conditions[$param] = $condition;
        return $this;
    }

    public function checkConditions($params){
        $paramNames = array_column($this->paramNames, 'required', 'name');
        foreach ($params as $key => $value){
            if(!isset($this->conditions[$key]) || ($value === null && !$paramNames[$key])) continue;
            if(!preg_match(sprintf('/%s/', $this->conditions[$key]), $value)){
                return false;
            }
        }
        return true;
    }

    public function execute(Request $req)
    {
        $action = $this->action;
        if ($action instanceof \Closure) {
            $params = self::getBindParams((new \ReflectionFunction($action))->getParameters(), $req);
            return $action(...$params);
        }
        if($action instanceof Response) return $action;
        $method = 'index';
        $controller = $action;
        if (is_array($action)) {
            $method = $action[1];
            $controller = $action[0];
        } else {
            $idx = strpos($action, '@');
            if ($idx !== false) {
                $method = ltrim(substr($action, $idx), '@');
                $controller = substr($action, 0, $idx);
            }
        }

        $instance = new $controller();
        if(!method_exists($instance, $method)){
            return new Response(404, 'Not Found');
        }

        $params = self::getBindParams((new \ReflectionClass($instance))->getMethod($method)->getParameters(), $req);

        if(method_exists($instance, '__beforeInvoke')){
            $instance->__beforeInvoke($method);
        }

        $response = $instance->{$method}(...$params);

        if(method_exists($instance, '__afterInvoke')){
            $instance->__afterInvoke($method, $response);
        }

        return $response;
    }

    /**
     * @param $parameters
     * @param Request $req
     * @return array
     * @throws \Exception
     */
    public static function getBindParams($parameters, Request $req)
    {

        $result = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            $value = $req->input($name);
            if ($type !== null) {
                $value = is_array($value) ? $value : self::getTypeValue($type, $value);
            }
            $result[] = $value;
        }
        return $result;
    }


    /**
     * @param \ReflectionNamedType $type
     * @param string|null $value
     * @return bool|float|int|Request|string|null
     * @throws \Exception
     */
    private static function getTypeValue(\ReflectionNamedType $type, ?string $value)
    {

        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            if (($value === null || $value === '') && !$type->allowsNull()) {
                throw new \Exception('null value for \'' . $typeName . '\' is not allowed');
            }
            switch ($typeName) {
                case 'int':
                    return ($value === null || $value === '') ? null : intval($value);
                case 'float':
                    return ($value === null || $value === '') ? null : floatval($value);
                case 'bool':
                    return ($value === null || $value === '') ? null
                        : !($value === 'false' || $value === '0' || $value === 'False' || $value === 'FALSE');
            }
            return $value;
        }
        if (!class_exists($typeName)) throw new \Exception('class \'' . $typeName . '\' not exists');
        if($typeName == Request::class) return \request();

        $parents = array_values(class_parents($typeName));

        if (in_array(Request::class, $parents)) return \request()->cloneTo($typeName);

        if(in_array(Model::class, $parents)) return self::fillModel($typeName, $value);



        return null;
    }

    private static function fillModel($typeName, $keyValue){

        /**
         * @var Model $model
         */
        $model = new $typeName();

        if($keyValue === null) return $model;


        $pk = $model->getPk();
        if(empty($pk)) throw new HttpException(404, 'Model \'' . $typeName . '\', pk not found');

        $pk = (array)$pk;

        $row = $model->newQuery()->where($pk[0], $keyValue)->find();
        if(!$row){
            throw new HttpException(404, 'Model \'' . $typeName . '\' not found');
        }
        $row->fill(\request()->input());
        return $row;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @return array
     */
    public function getParamNames(): array
    {
        return $this->paramNames;
    }

    /**
     * @param array $paramNames
     */
    public function setParamNames(array $paramNames): void
    {
        $this->paramNames = $paramNames;
    }

    /**
     * @param null $name
     * @return Route|string
     */
    public function name($name = null)
    {
        if($name === null) return $this->name;
        $this->name = $name;
        return $this;
    }

    /**
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
