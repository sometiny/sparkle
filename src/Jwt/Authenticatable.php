<?php


namespace Sparkle\Jwt;


interface Authenticatable
{

    public function getPayload();

    public function getRole();

    public function getAuthIdentifier();

    public function getAuthIdentifierName();

    public function getAuthPassword();

}
