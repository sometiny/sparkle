<?php


namespace Sparkle\Auth;


use Sparkle\Auth\Jwt\JwtToken;
use Sparkle\Http\HttpException;

class Authenticate
{

    private static $users = [];

    /**
     * 设置登录状态
     * @param $authenticate
     * @param $class
     * @return void
     */
    public static function login($authenticate, $class = null){
        if(empty($class)) $class = get_class($authenticate);

        static::$users[$class] = $authenticate;
    }

    /**
     * @param $class
     * @return mixed|null
     */
    public static function user($class){
         return static::$users[$class] ?? null;
    }

    /**
     * @param $credentials
     * @param $class
     * @return mixed
     * @throws HttpException
     */
    public static function auth($credentials, $class)
    {
        $password = $credentials['password'];
        unset($credentials['password']);
        if (!$password) {
            throw new HttpException(400, 'password required');
        }

        $authenticate = $class::where($credentials)->find();
        if (!$authenticate) {
            throw new HttpException(400, 'credentials is invalid');
        }
        if (!password_verify($password, $authenticate->getAuthPassword())) {
            throw new HttpException(403, 'password is invalid');
        }
        return $authenticate;
    }

    /**
     * @param $token
     * @param $class
     * @return mixed
     * @throws HttpException
     */
    public static function verify($token, $class)
    {
        try {
            $payload = JwtToken::verify($token);
            return (new $class())->getAuthenticatable($payload);
        } catch (\Exception $e) {
            throw new HttpException(401, 'access_token is invalid');
        }
    }
}
