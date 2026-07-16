<?php
declare(strict_types=1);

namespace App\core;

class Application
{
    private static ?Application $instance = null;
    private array $config;
    private ?RedisCache $cache = null;
    private ?Database $db = null;
    private ?array $currentUser = null;

    private function __construct()
    {
        $this->config = require APP_PATH . '/config/config.php';
        $this->initCache();
        $this->initDatabase();
        $this->initSession();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initCache(): void
    {
        if ($this->config['cache']['enabled'] && class_exists('Redis')) {
            try {
                $redis = new \Redis();
                $redis->connect(
                    $this->config['cache']['host'],
                    (int)$this->config['cache']['port']
                );
                if (!empty($this->config['cache']['password'])) {
                    $redis->auth($this->config['cache']['password']);
                }
                $redis->select((int)$this->config['cache']['database']);
                $this->cache = new RedisCache($redis, $this->config['cache']['prefix'] ?? 'cms:');
            } catch (\Exception $e) {
                error_log('Redis连接失败: ' . $e->getMessage());
            }
        }
    }

    private function initDatabase(): void
    {
        try {
            $this->db = new Database($this->config['database']);
        } catch (\Exception $e) {
            error_log('数据库连接失败: ' . $e->getMessage());
            if ($this->config['app']['debug']) {
                die('数据库连接失败: ' . $e->getMessage());
            }
        }
    }

    private function initSession(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $sec = $this->config['security'];
            $cookiePrefix = $sec['cookie_prefix'] ?? 'lig_';

            // 会话名称：使用自定义前缀
            session_name($cookiePrefix . 'session');

            // Cookie 参数设置
            $lifetime = (int)($sec['cookie_lifetime'] ?? 86400);
            $path = (string)($sec['cookie_path'] ?? '/');
            $domain = (string)($sec['cookie_domain'] ?? '');
            $secure = (bool)($sec['cookie_secure'] ?? false);
            $httponly = (bool)($sec['cookie_httponly'] ?? true);
            $samesite = (string)($sec['cookie_samesite'] ?? 'Lax');

            // 设置会话 Cookie 参数（PHP 7.3+ 支持 SameSite）
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => $lifetime,
                    'path' => $path,
                    'domain' => $domain,
                    'secure' => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite,
                ]);
            } else {
                session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
            }

            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', ROOT_PATH . '/var/sessions');

            // ============================================================
            // 修复：ini_set() 第二个参数必须为字符串
            // ============================================================
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', $httponly ? '1' : '0');
            ini_set('session.cookie_secure', $secure ? '1' : '0');

            session_start();

            // 会话固定防护：重新生成ID
            if (!isset($_SESSION['_lig_created'])) {
                session_regenerate_id(true);
                $_SESSION['_lig_created'] = time();
            }
        }
    }

    public function getCache(): ?RedisCache
    {
        return $this->cache;
    }

    public function getDb(): ?Database
    {
        return $this->db;
    }

    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function setCurrentUser(?array $user): void
    {
        $this->currentUser = $user;
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
        } else {
            unset($_SESSION['user_id']);
        }
    }

    public function getCurrentUser(): ?array
    {
        if ($this->currentUser === null && isset($_SESSION['user_id'])) {
            $userModel = new \App\models\User();
            $this->currentUser = $userModel->find($_SESSION['user_id']);
        }
        return $this->currentUser;
    }

    public function isLoggedIn(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/') ?: '/';

        $cacheKey = 'page:' . md5($uri);
        $cache = $this->getCache();
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$this->isLoggedIn() && $cache && $cache->has($cacheKey)) {
            $cachedContent = $cache->get($cacheKey);
            if ($cachedContent) {
                echo $cachedContent;
                return;
            }
        }

        $routeFound = false;
        $routes = require APP_PATH . '/routes.php';

        foreach ($routes as $route => $handler) {
            $pattern = '#^' . preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_]+)', $route) . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $routeFound = true;
                $this->dispatch($handler, $matches);
                break;
            }
        }

        if (!$routeFound) {
            http_response_code(404);
            echo '<h1>404 - Page Not Found</h1>';
        }
    }

    private function dispatch(string $handler, array $params): void
    {
        list($controllerClass, $method) = explode('@', $handler);
        $controllerClass = 'App\\controllers\\' . $controllerClass;

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo "Controller not found: $controllerClass";
            return;
        }

        $controller = new $controllerClass();
        if (!method_exists($controller, $method)) {
            http_response_code(500);
            echo "Method not found: $method";
            return;
        }

        $content = $controller->$method(...$params);

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$this->isLoggedIn() && $this->getCache()) {
            $cacheKey = 'page:' . md5($_SERVER['REQUEST_URI'] ?? '/');
            $this->getCache()->set($cacheKey, $content, 60);
        }

        echo $content;
    }
}
