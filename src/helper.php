<?php

use Sparkle\Http\JsonResponse;
use Sparkle\Http\Response;
use Sparkle\Http\ViewResponse;

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
     * @param array $contents
     * @param int $statusCode
     * @return Response
     */
    function json(array $contents, int $statusCode = 200)
    {
        return new JsonResponse($contents, $statusCode);
    }


    /**
     * @param string $view
     * @param array $data
     * @param int $statusCode
     * @return ViewResponse
     */
    function view(string $view, array $data = [], int $statusCode = 200)
    {
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
