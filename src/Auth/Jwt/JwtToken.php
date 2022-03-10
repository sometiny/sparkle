<?php


namespace Sparkle\Auth\Jwt;


use Sparkle\Auth\Authenticatable;

class JwtToken
{
    /***
     * @param Authenticatable $user
     * @return string
     * @throws \Exception
     */
    public static function create(Authenticatable $user)
    {
        $hash = env('JWT_HASH');
        $expires = env('JWT_EXPIRES');
        $bind_by = env('JWT_BIND_BY');

        if (!$expires) {
            $expires = 86400;
        }
        if (!$hash) {
            throw new \Exception('no jwt hash');
        }

        $hash = base64_decode($hash);

        $header = json_encode(["typ" => "JWT", "alg" => "HS256"]);

        $payload = array(
            'identity' => $user->getAuthIdentifier(),
            'payload' => $user->getPayload(),
            'role' => $user->getRole(),
            'expired_at' => strtotime('+' . $expires . 'seconds')
        );

        if (!empty($bind_by)) {
            $bind_value = request()->server($bind_by);
            $payload['bind'] = $bind_value;
        }
        $payload = json_encode($payload, 256);

        $signature = hash_hmac('sha256', $payload, $hash, true);

        $header = base64_encode_url_safe($header);
        $payload = base64_encode_url_safe($payload);
        $signature = base64_encode_url_safe($signature);

        return $header . '.' . $payload . '.' . $signature;
    }

    /***
     * @param $token
     * @return array
     * @throws \Exception
     */
    public static function verify($token)
    {
        $hash = env('JWT_HASH');
        $bind_by = env('JWT_BIND_BY');

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('invalid token');
        }
        if (!$hash) {
            throw new \Exception('no jwt hash');
        }

        $hash = base64_decode($hash);

        $header = base64_decode_url_safe($parts[0]);
        $payload = base64_decode_url_safe($parts[1]);
        $signature = $parts[2];


        $payloadSignature = hash_hmac('sha256', $payload, $hash, true);
        $payloadSignature = base64_encode_url_safe($payloadSignature);

        if ($signature != $payloadSignature) {
            throw new \Exception('invalid token signature');
        }

        $payload = json_decode($payload, true);

        if ($payload['expired_at'] < time()) {
            throw new \Exception('token has been expired');
        }
        if (!isset($payload['bind'])) return $payload;

        $bind_value = request()->server($bind_by);
        if ($bind_value != $payload['bind']) {
            throw new \Exception('token has been stolen');
        }

        return $payload;
    }
}
