<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'p_inetpub_cn',
        'username' => 'p_inetpub_cn',
        'password' => 'abcdefg',
        'charset' => 'utf8mb4',
    ],
    'cache' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => 'abcdefg',
        'database' => 0,
        'prefix' => 'cms:',
        'default_ttl' => 3600,
    ],
    'app' => [
        'name' => 'My CMS',
        'debug' => false,
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
        // CIDR 白名单：允许访问 /login 和 /register 的 IP 段
        // 格式：['192.168.1.0/24', '10.0.0.0/8', '127.0.0.1']
        'allowed_cidrs' => [
            '112.116.64.0/24',
            '127.0.0.1',           // 本地访问
            '192.168.0.0/16',      // 内网
            '10.0.0.0/8',          // 内网
            '172.16.0.0/12',       // 内网
        ],
    ],
];