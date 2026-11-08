<?php

namespace Config;

class Auth extends \CodeIgniter\Shield\Config\Auth
{
    public bool $allowRegistration = false;
    public bool $allowMagicLinkLogins = false;
    public array $actions = ['register' => null, 'login' => null];
    public array $sessionConfig = [
        'field' => 'user',
        'allowRemembering' => false,
        'rememberCookieName' => 'remember',
        'rememberLength' => 2592000,
    ];
}
