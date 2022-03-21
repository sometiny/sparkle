<?php


namespace Sparkle;


use Illuminate\Support\Str;
use Jazor\Path;
use Sparkle\Http\HttpException;
use Sparkle\Http\HttpResponseException;
use Sparkle\Http\JsonResponse;
use Sparkle\Http\Request;
use Sparkle\Http\Response;
use Sparkle\Logs\File;
use Sparkle\Routing\Router;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\facade\Db;
use think\Paginator;

class Application
{

    private static Application $current;
    /**
     * @var false|string
     */
    private string $appPath;
    private Config $config;
    private Env $env;

    private array $autoload = [];
    private array $middleware = [];

    /**
     * Application constructor.
     * @param null $appPath
     * @throws \Exception
     */
    public function __construct($appPath = null)
    {
        defined('DS') or define('DS', DIRECTORY_SEPARATOR);

        if ($appPath === null) $appPath = realpath('../App');
        if (!is_dir($appPath)) throw new \Exception('app dir not found');
        set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext)
        {
            if (0 === error_reporting()) return false;
            throw new \RuntimeException($errstr . "\r\nFile: " . $errfile . "\r\nLine: " . $errline, 0);
        });

        $this->appPath = Path::format($appPath);

        $this->env = new Env($this->envPath());

        $this->config = (new Config())->scanDirectory($this->configPath());


        Db::setConfig($this->config->get('db', null));
        Db::setLog(new File($this->logsPath('db')));

        Paginator::currentPageResolver(function ($var){
            return intval(\request()->query($var, 1));
        });

        Paginator::maker(function ($items, int $listRows, int $currentPage = 1, int $total = null, bool $simple = false, array $options = []){
            return new \Sparkle\Paginator($items, $listRows, $currentPage, $total, $simple, $options);
        });

        View::setCachePath($this->viewCachePath());
        View::addViewLocation($this->viewDefaultPath());
        View::register();

        $this->registerAutoload();
        self::$current = $this;
    }

    /**
     * @param $prefix
     * @param $path
     */
    protected function autoload($prefix, $path){
        if(isset($this->autoload[$prefix])){
            $this->autoload[$prefix][] = $path;
            return;
        }
        $this->autoload[$prefix] = [$path];
    }

    /**
     * @param $name
     * @param $class
     */
    protected function registerMiddleware($name, $class){
        $this->middleware[$name] = $class;
    }

    protected function registerAutoload(){
        if(empty($this->autoload)) return;

        spl_autoload_register(array($this, 'loadClass'));

    }

    public function getMiddleware($middleware){
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);
        if(!is_array($parameters)){
            $parameters = [$parameters];
        }
        $name = $this->middleware[$name] ?? $name;

        return function ($req, $next) use ($name, $parameters){
            return (new $name())->handle($req, $next, ...$parameters);
        };
    }

    private function loadClass($class){

        $values = $this->autoload;

        foreach ($values as $prefix => $path){
            foreach ($path as $p){
                if(Str::startsWith($class, $prefix)){
                    $file = Path::combine($p, substr($class, strlen($prefix)) . '.php');
                    if(is_file($file)){
                        include $file;
                        return true;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @return Application
     */
    public static function current(): Application
    {
        return self::$current;
    }

    /**
     * @param null|string $sub
     * @return string
     */
    public function path($sub = null)
    {
        if ($sub === null) return $this->appPath;
        return $this->appPath . DIRECTORY_SEPARATOR . ltrim(Path::format($sub), DIRECTORY_SEPARATOR);
    }

    public function envPath(){
        return $this->path('.env');
    }

    public function configPath($sub = null)
    {
        return $this->path('configs' . ($sub ? DIRECTORY_SEPARATOR . $sub : ''));
    }

    public function cachePath($sub = null)
    {
        return $this->path('caches' . ($sub ? DIRECTORY_SEPARATOR . $sub : ''));
    }

    public function viewsPath($sub = null)
    {
        return $this->path('views' . ($sub ? DIRECTORY_SEPARATOR . $sub : ''));
    }

    public function logsPath($sub = null)
    {
        return $this->path('logs' . ($sub ? DIRECTORY_SEPARATOR . $sub : ''));
    }

    public function viewCachePath(){
        return $this->path('caches/views');
    }

    public function viewDefaultPath(){
        return $this->path('views/default');
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        try {
            $req = Request::capture();


            $this->routing();

            $response = Router::dispatch($this, $req);

            if ($response === null) return;

            if (is_numeric($response)) {
                $response = new Response($response);
            } else if (is_string($response)) {
                $response = new Response(200, $response);
            } else if (
                is_array($response)
                || $response instanceof Jsonable
                || $response instanceof \Illuminate\Contracts\Support\Jsonable
                || $response instanceof \JsonSerializable
                || $response instanceof Arrayable
                || $response instanceof \Illuminate\Contracts\Support\Arrayable
            ) {
                $response = new JsonResponse($response, 200);
            }

            if ($response instanceof Response) {
                $response->send();
                return;
            }
            $response = new Response(500);
            $response->setBody('unknown response');
            $response->send();
        }catch (HttpException $e){
            $response = $this->getResponse($req, new Response($e->getStatusCode(), $e->getMessage()));
            $response->send();
        }catch (HttpResponseException $e){
            $e->getResponse()->send();
        }catch (\Throwable $e){
            $response = new Response(500);
            if(env('APP_DEBUG') === true) {
                $response->setBody('<pre>' . (string)$e . '</pre>');
            }else{
                $response->setBody('<pre>' . $e->getMessage() . '</pre>');
            }
            $this->getResponse($req, $response)->send();
        } finally {
            spl_autoload_unregister(array($this, 'loadClass'));
        }
    }
    private function getResponse(Request $req, Response $response){
        if($req->accept('application/json') && !($response instanceof JsonResponse)){
            return new JsonResponse([
                'message' => $response->getBody()
            ], $response->getStatusCode());
        }
        return $response;
    }

    protected function getRoutesPath($path){
        return $path;
    }

    private function routing(){
        $path = $this->getRoutesPath($this->path('routes/routes.php'));
        if(is_string($path)) $path = (array)$path;

        foreach ($path as $p){
            if(!is_file($p)) continue;
            include_once $p;
        }
    }

    /**
     * @return Config
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @return Env
     */
    public function env(): Env
    {
        return $this->env;
    }
}
