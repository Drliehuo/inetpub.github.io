<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('CACHE_PATH', ROOT_PATH . '/var/cache');
define('VIEW_PATH', APP_PATH . '/views');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require APP_PATH . '/routes.php';

$app = App\core\Application::getInstance();
$app->run();
