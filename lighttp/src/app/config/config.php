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
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
        'per_page' => 10,
    ],
    'security' => [
        'bcrypt_cost' => 12,
    ],
];
