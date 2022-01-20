<?php


namespace Sparkle;


use Jazor\Path;
use Sparkle\Http\Request;
use Sparkle\Http\Response;
use Sparkle\Logs\File;
use Sparkle\Routing\Router;
use think\facade\Db;

class Application
{

    private static Application $current;
    /**
     * @var false|string
     */
    private string $appPath;
    private Config $config;
    private Env $env;

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

        $this->appPath = Path::format($appPath);

        $this->env = new Env($this->path('.env'));

        $this->config = (new Config())->scanDirectory($this->configPath());


        Db::setConfig($this->config->get('db', null));
        Db::setLog(new File($this->logsPath('db')));


        self::$current = $this;
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

    /**
     * @throws \Exception
     */
    public function run()
    {
        $req = Request::capture();
        View::setCachePath($this->path('caches/views'));
        View::addViewLocation($this->path('views/default'));

        $this->routing();

        $response = Router::dispatch();

        if ($response === null) return;

        if (is_numeric($response)) {
            $response = new Response($response);
        } else if (is_string($response) || is_array($response)) {
            $response = new Response(200, $response);
        }

        if ($response instanceof Response) {
            $response->send();
            return;
        }
        $response = new Response(500);
        $response->setBody('unknown response');
        $response->send();
    }

    private function routing(){
        $path = $this->path('routes/routes.php');
        if(!is_file($path)) return;
        include_once $path;
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
