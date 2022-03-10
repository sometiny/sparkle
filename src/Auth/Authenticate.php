<?php


namespace Sparkle\Auth;


use Sparkle\Auth\Jwt\JwtToken;
use Sparkle\Http\HttpException;

class Authenticate
{

    public static function auth($credentials, $class)
    {
        $password = $credentials['password'];
        unset($credentials['password']);
        if (!$password) {
            throw new HttpException(400, 'password required');
        }

        $authenticatable = $class::where($credentials)->find();
        if (!$authenticatable) {
            throw new HttpException(400, 'credentials invalid');
        }
        if (password_verify($password, $authenticatable->getAuthPassword())) {
            throw new HttpException(403, 'password invalid');
        }

        try {
            return JwtToken::create($authenticatable);
        } catch (\Exception $e) {
            throw new HttpException(500, $e->getMessage());
        }
    }

    public static function verify($token, $class)
    {
        try {
            $payload = JwtToken::verify($token);
            return (new $class())->getAuthenticatable($payload);
        } catch (\Exception $e) {
            throw new HttpException(401, 'access-token invalid');
        }
    }
}
