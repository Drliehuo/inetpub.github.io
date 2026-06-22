<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'cms_db',
        'username' => 'cms_db',
        'password' => ']p3ZKkpDN(-T-NNE',
        'charset' => 'utf8mb4',
    ],
    'cache' => [
        'enabled' => true,
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
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
];
