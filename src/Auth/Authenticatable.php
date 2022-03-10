<?php


namespace Sparkle\Auth;


interface Authenticatable
{

    public function getPayload();
    
    public function getAuthenticatable($payload);

    public function getRole();

    public function getAuthIdentifier();

    public function getAuthIdentifierName();

    public function getAuthPassword();

}
