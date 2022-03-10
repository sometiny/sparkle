<?php

use Sparkle\Abstracts\Transformer;
use Sparkle\Http\JsonResponse;
use Sparkle\Http\Response;
use Sparkle\Http\ViewResponse;
use think\Collection;
use think\Model;
use think\Paginator;

if(!function_exists('response')) {

    /**
     * @param string $contents
     * @param int $statusCode
     * @return Response
     */
    function response(string $contents, int $statusCode = 200): Response
    {
        $response = new Response($statusCode);
        $response->setBody($contents);
        return $response;
    }

    /**
     * @param mixed $contents
     * @param int $statusCode
     * @param int $options
     * @return JsonResponse
     */
    function json( $contents, int $statusCode = 200, int $options = 256)
    {
        return (new JsonResponse($contents, $statusCode))->setOptions($options);
    }

    /**
     * @param mixed $data
     * @param Transformer|string $transformer
     * @param string $type
     * @return mixed
     */
    function transform($data, $transformer, $type = '')
    {
        if(is_string($transformer)){
            $transformer = new $transformer($type);
        }
        if ($data instanceof Model) {
            return $transformer->doTransform($data);
        }

        if ($data->count() === 0) return $data;

        return $data->map([$transformer, 'doTransform']);
    }

    /**
     * @param string $url
     * @param int $code
     * @return Response
     */
    function redirect(string $url, $code = 302){
        $response = new Response($code);
        $response->setHeader('Location', $url);
        return $response;
    }


    /**
     * @param string $view
     * @param array|\think\contract\Arrayable|\Illuminate\Contracts\Support\Arrayable $data
     * @param int $statusCode
     * @return ViewResponse
     */
    function view(string $view, $data = [], int $statusCode = 200)
    {
        if($data instanceof \think\contract\Arrayable
            || $data instanceof \Illuminate\Contracts\Support\Arrayable){
            $data = $data->toArray();
        }
        return new ViewResponse($view, $data, $statusCode);
    }

    /**
     * @return \Sparkle\Http\Request
     */
    function request()
    {
        return \Sparkle\Facades\Request::current();
    }

    /**
     * @return \Sparkle\Application
     */
    function app()
    {
        return \Sparkle\Application::current();
    }

    /**
     * @param null $name
     * @param null $default
     * @return \Sparkle\Env|mixed
     */
    function env($name = null, $default = null)
    {
        if($name !== null){
            return app()->env()->get($name, $default);
        }
        return app()->env();
    }

    /**
     * @param null $name
     * @param null $default
     * @return \Sparkle\Config|mixed
     */
    function config($name = null, $default = null)
    {
        if($name !== null){
            return app()->config()->get($name, $default);
        }
        return app()->config();
    }
}

if(!function_exists('dir_get_all_files')) {
    function dir_get_all_files($dir, &$result, $prefix = ''){

        $files = scandir($dir);

        foreach ($files as $file){
            if($file === '.' || $file === '..') continue;

            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
            if(is_dir($fullPath)){
                dir_get_all_files($fullPath, $result, $prefix . $file . DIRECTORY_SEPARATOR);
                continue;
            }

            $result[] = $prefix . $file;
        }

        return $result;
    }
    function dir_get_all_dirs($dir, &$result, $prefix = ''){

        $files = scandir($dir);

        foreach ($files as $file){
            if($file === '.' || $file === '..') continue;

            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
            $result[] = $prefix . $file;

            if(is_dir($fullPath)){
                dir_get_all_dirs($fullPath, $result, $prefix . $file . DIRECTORY_SEPARATOR);
                continue;
            }

        }

        return $result;
    }
}

if(!function_exists('base64_encode_url_safe')) {

    /**
     * @param $source
     * @return string
     */
    function base64_encode_url_safe($source)
    {
        return str_replace(['+', '/'], ['-', '_'], rtrim(base64_encode($source), '='));
    }

    /**
     * @param $source
     * @return string
     * @throws Exception
     */
    function base64_decode_url_safe($source)
    {
        $dest = str_replace(['-', '_'], ['+', '/'], $source);
        $length = strlen($dest);
        $mod = $length % 4;
        if ($mod === 0) return base64_decode($dest);
        if ($mod === 2) return base64_decode($dest . '==');
        if ($mod === 3) return base64_decode($dest . '=');

        throw new \Exception('Illegal base64url string');
    }
}
