<?php


namespace Sparkle\Http;

/**
 * Class Request
 * @package Sparkle\Http
 */
class Request
{

    private static ?Request $current = null;
    private string $path = '/';

    private array $query = [];
    private string $method = 'GET';
    private array $params = [];
    private array $post = [];
    private array $files = [];
    private $server;
    private $cookie;
    private $session;

    private int $step = 0;

    public function __construct($method, $path, $get, $post, $files, $server, $cookie, $session)
    {
        $this->method = $method;
        $this->query = $get;
        $this->path = $path;
        $this->post = $post;
        $this->server = $server;
        $this->cookie = $cookie;
        $this->session = $session;
        $this->files = $files;
    }

    public static function capture()
    {
        unset($_SERVER['PATH']);

        if(!isset($_SERVER['PATH_INFO']) || empty($_SERVER['PATH_INFO'])){
            unset($_SERVER['PATH_INFO']);
        }


        $url = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $idx = strpos($url, '?');
        $path = $url;
        if ($idx > 0) {
            $path = substr($url, 0, $idx);
            $queryString = substr($url, $idx + 1);
        }
        parse_str($queryString, $query);
        if (!$query) $query = $_GET;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $contentType = self::getContentType($_SERVER['CONTENT_TYPE'] ?? null);

        $input = [];

        $files = [];

        switch ($contentType) {
            case 'application/json':
                $inputRaw = file_get_contents('php://input');
                $input = json_decode($inputRaw, true);
                break;
            case 'application/x-www-form-urlencoded':
                $inputRaw = file_get_contents('php://input');
                parse_str($inputRaw, $input);
                break;
            case 'multipart/form-data':
                $files = self::getFiles($_FILES);
                break;
        }

        $req = new Request($method, $path, $query, $input, $files, $_SERVER, $_COOKIE, $_SESSION ?? null);
        self::$current = $req;
        return $req;
    }

    private static function getFiles($rawFiles){
        $files = [];
        foreach ($rawFiles as $fieldName => $file){
            $name = $file['name'];
            $files[$fieldName] = [];
            if(is_array($name)){
                foreach ($name as $key => $value){
                    $files[$fieldName][] = new File([
                        'name' => $value,
                        'type' => $file['type'][$key],
                        'tmp_name' => $file['tmp_name'][$key],
                        'error' => $file['error'][$key],
                        'size' => $file['size'][$key],
                    ]);
                }
                continue;
            }
            $files[$fieldName][] = new File($file);
        }
        return $files;
    }
    private static function getContentType($contentType){
        if($contentType){
            $idx = strpos($contentType, ';');
            if($idx !== false){
                $contentType = substr($contentType, 0, $idx);
            }
        }
        return $contentType;
    }

    /**
     * @return Request|null
     */
    public static function current(): ?Request
    {
        if (self::$current === null) {
            return self::capture();
        }
        return self::$current;
    }

    public function files($name = null)
    {
        return $name ? $this->files[$name] ?? null : $this->files;
    }
    public function ip()
    {
        return $this->server['REMOTE_ADDR'] ?? null;
    }
    public function port()
    {
        return $this->server['REMOTE_PORT'] ?? null;
    }

    public function server($name = null, $default = null)
    {
        return $name ? $this->server[$name] ?? $default : $this->server;
    }

    public function post($name = null, $default = null)
    {
        return $name ? $this->post[$name] ?? $default : $this->post;
    }

    public function query($name = null, $default = null)
    {
        return $name ? $this->query[$name] ?? $default : $this->query;
    }

    public function param($name = null, $default = null){
        return $name ? $this->params[$name] ?? $default : $this->params;
    }

    public function input($name, $default = null){
        return $this->query[$name]
            ?? $this->post[$name]
            ?? $this->params[$name]
            ?? $this->files[$name]
            ?? $default;
    }

    public function accept($except = null)
    {
        $accept = $this->server('HTTP_ACCEPT', '');
        if($except !== null){
            return strpos($accept, $except) !== false;
        }
        return $accept;
    }
    public function acceptJson(){
        return $this->accept('text/json') ||  $this->accept('application/json');
    }

    public function acceptLanguage()
    {
        return $this->server('HTTP_ACCEPT_LANGUAGE');
    }

    public function acceptEncoding()
    {
        return $this->server('HTTP_ACCEPT_ENCODING');
    }

    public function userAgent()
    {
        return $this->server('HTTP_USER_AGENT');
    }

    public function host()
    {
        return $this->server('HTTP_HOST');
    }

    /**
     * @return false|mixed|string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @return mixed|string
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @return int
     */
    public function getStep(): int
    {
        return $this->step;
    }

    /**
     * @param int $step
     */
    public function setStep(int $step): void
    {
        $this->step = $step;
    }
}
