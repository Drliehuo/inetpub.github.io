<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'p_inetpub_cn',
        'username' => 'p_inetpub_cn',
        'password' => ']p3ZKkpDN(-T-NNE',
        'charset' => 'utf8mb4',
    ],
    'cache' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '123456',
        'database' => 0,
        'prefix' => 'cms:',
        'default_ttl' => 3600,
    ],
    'app' => [
        'name' => 'My CMS',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
        'per_page' => 10,
    ],
    'security' => [
        'bcrypt_cost' => 12,
        'cookie_prefix' => 'lig_',
        'cookie_httponly' => true,
        'cookie_secure' => false,
        'cookie_samesite' => 'Lax',
        'cookie_domain' => '',
        'cookie_path' => '/',
        'cookie_lifetime' => 86400,
        'csrf_token_name' => 'lig_csrf_token',
        'csrf_token_lifetime' => 3600,
    ],
];
