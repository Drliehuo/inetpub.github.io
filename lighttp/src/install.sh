#!/bin/bash
# ======================================================
# CMS系统完整安装脚本（兼容PHP 7.4+ / 8.x + Redis + MySQL）
# 包含完整数据库表结构（12张表）
# ======================================================

echo "========================================"
echo "  正在生成完整CMS系统文件..."
echo "========================================"

# 创建目录结构
mkdir -p app/{controllers,models,core,config,views}
mkdir -p public/{css,js,uploads,images}
mkdir -p var/{logs,cache/redis,data,sessions}

# ======================================================
# 1. 生成入口文件
# ======================================================
cat > public/index.php << 'EOF'
<?php
/**
 * 入口文件
 * 兼容 PHP 7.4 - 8.x
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义根目录
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('CACHE_PATH', ROOT_PATH . '/var/cache');
define('VIEW_PATH', APP_PATH . '/views');

// 自动加载
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

// 引入路由
require APP_PATH . '/routes.php';

// 启动应用
$app = new App\core\Application();
$app->run();
EOF

# ======================================================
# 2. 生成核心 Application 类
# ======================================================
cat > app/core/Application.php << 'EOF'
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
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', ROOT_PATH . '/var/sessions');
            session_start();
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

    public function getConfig(string $key = null)
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

        // 页面缓存（仅对GET请求且用户未登录）
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

        // 页面缓存（仅对GET请求且用户未登录）
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$this->isLoggedIn() && $this->getCache()) {
            $cacheKey = 'page:' . md5($_SERVER['REQUEST_URI'] ?? '/');
            $this->getCache()->set($cacheKey, $content, 60);
        }

        echo $content;
    }
}
EOF

# ======================================================
# 3. 生成 Database 类
# ======================================================
cat > app/core/Database.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\core;

class Database
{
    private \PDO $pdo;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        
        $this->pdo = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
}
EOF

# ======================================================
# 4. 生成 RedisCache 类
# ======================================================
cat > app/core/RedisCache.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\core;

class RedisCache
{
    private \Redis $redis;
    private string $prefix;
    private int $defaultTtl;

    public function __construct(\Redis $redis, string $prefix = 'cms:', int $defaultTtl = 3600)
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    public function get(string $key)
    {
        $data = $this->redis->get($this->prefix . $key);
        if ($data === false) {
            return null;
        }
        return unserialize($data);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);
        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }
        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function has(string $key): bool
    {
        return (bool)$this->redis->exists($this->prefix . $key);
    }

    public function delete(string $key): bool
    {
        return (bool)$this->redis->del($this->prefix . $key);
    }

    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        if (empty($keys)) {
            return true;
        }
        return (bool)$this->redis->del($keys);
    }

    public function increment(string $key, int $step = 1): int
    {
        return $this->redis->incrBy($this->prefix . $key, $step);
    }

    public function decrement(string $key, int $step = 1): int
    {
        return $this->redis->decrBy($this->prefix . $key, $step);
    }
}
EOF

# ======================================================
# 5. 生成配置文件
# ======================================================
cat > app/config/config.php << 'EOF'
<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'cms_db',
        'username' => 'cms_user',
        'password' => 'your_password_here',
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
EOF

# ======================================================
# 6. 生成路由文件（完整版）
# ======================================================
cat > app/routes.php << 'EOF'
<?php
declare(strict_types=1);

return [
    // 前台路由
    '/' => 'HomeController@index',
    '/article/{id}' => 'ArticleController@show',
    '/category/{slug}' => 'CategoryController@index',
    '/tag/{slug}' => 'TagController@index',
    '/page/{slug}' => 'PageController@show',
    
    // 用户认证
    '/login' => 'AuthController@login',
    '/logout' => 'AuthController@logout',
    '/register' => 'AuthController@register',
    
    // 后台路由
    '/admin' => 'AdminController@dashboard',
    '/admin/articles' => 'AdminController@articles',
    '/admin/article/create' => 'AdminController@createArticle',
    '/admin/article/edit/{id}' => 'AdminController@editArticle',
    '/admin/article/delete/{id}' => 'AdminController@deleteArticle',
    '/admin/categories' => 'AdminController@categories',
    '/admin/category/create' => 'AdminController@createCategory',
    '/admin/category/edit/{id}' => 'AdminController@editCategory',
    '/admin/category/delete/{id}' => 'AdminController@deleteCategory',
    '/admin/users' => 'AdminController@users',
    '/admin/settings' => 'AdminController@settings',
    '/admin/cache/clear' => 'AdminController@clearCache',
];
EOF

# ======================================================
# 7. 生成 Models（完整版）
# ======================================================

# 7.1 Article Model
cat > app/models/Article.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Article
{
    private string $table = 'articles';

    public function getAll(array $filters = []): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        $sql = "SELECT a.*, c.name as category_name, u.username as author_name 
                FROM {$this->table} a 
                LEFT JOIN categories c ON a.category_id = c.id 
                LEFT JOIN users u ON a.author_id = u.id 
                WHERE a.status = 1";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        $sql .= " ORDER BY a.is_top DESC, a.published_at DESC";
        return $db->query($sql, $params);
    }

    public function getLatest(int $limit = 10): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query(
            "SELECT a.*, c.name as category_name, u.username as author_name 
            FROM {$this->table} a 
            LEFT JOIN categories c ON a.category_id = c.id 
            LEFT JOIN users u ON a.author_id = u.id 
            WHERE a.status = 1 
            ORDER BY a.is_top DESC, a.published_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function find(int $id): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        $article = $db->queryOne(
            "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name 
            FROM {$this->table} a 
            LEFT JOIN categories c ON a.category_id = c.id 
            LEFT JOIN users u ON a.author_id = u.id 
            WHERE a.id = ?",
            [$id]
        );
        
        if ($article) {
            // 增加浏览次数
            $db->update("UPDATE {$this->table} SET views = views + 1 WHERE id = ?", [$id]);
        }
        
        return $article;
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne(
            "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name 
            FROM {$this->table} a 
            LEFT JOIN categories c ON a.category_id = c.id 
            LEFT JOIN users u ON a.author_id = u.id 
            WHERE a.slug = ? AND a.status = 1",
            [$slug]
        );
    }

    public function create(array $data): int
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return 0;
        }
        $slug = $data['slug'] ?? $this->generateSlug($data['title']);
        return $db->execute(
            "INSERT INTO {$this->table} 
            (title, slug, content, excerpt, category_id, author_id, status, cover_image, 
             is_top, is_recommend, meta_title, meta_description, meta_keywords, published_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $data['title'],
                $slug,
                $data['content'],
                $data['excerpt'] ?? '',
                $data['category_id'] ?? null,
                $data['author_id'] ?? null,
                $data['status'] ?? 1,
                $data['cover_image'] ?? null,
                $data['is_top'] ?? 0,
                $data['is_recommend'] ?? 0,
                $data['meta_title'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at') {
                $sets[] = "$key = ?";
                $params[] = $value;
            }
        }
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        return $db->update($sql, $params) > 0;
    }

    public function delete(int $id): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        return $db->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $title), '-'));
        $db = Application::getInstance()->getDb();
        if ($db) {
            $existing = $db->queryOne("SELECT id FROM {$this->table} WHERE slug = ?", [$slug]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
        }
        return $slug;
    }

    public function getByCategory(int $categoryId, int $limit = 10): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query(
            "SELECT * FROM {$this->table} WHERE category_id = ? AND status = 1 
            ORDER BY created_at DESC LIMIT ?",
            [$categoryId, $limit]
        );
    }
}
EOF

# 7.2 Category Model
cat > app/models/Category.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Category
{
    private string $table = 'categories';

    public function findAll(bool $showAll = false): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        $sql = "SELECT * FROM {$this->table}";
        if (!$showAll) {
            $sql .= " WHERE is_show = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $db->query($sql);
    }

    public function find(int $id): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE slug = ? AND is_show = 1", [$slug]);
    }

    public function create(string $name, string $slug, string $description = '', int $parentId = 0): int
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return 0;
        }
        return $db->execute(
            "INSERT INTO {$this->table} (name, slug, description, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$name, $slug, $description, $parentId]
        );
    }

    public function update(int $id, array $data): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        return $db->update("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }

    public function delete(int $id): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        return $db->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }
}
EOF

# 7.3 User Model
cat > app/models/User.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class User
{
    private string $table = 'users';

    public function find(int $id): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE username = ?", [$username]);
    }

    public function findByEmail(string $email): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE email = ?", [$email]);
    }

    public function create(string $username, string $email, string $password, string $role = 'subscriber'): int
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return 0;
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        return $db->execute(
            "INSERT INTO {$this->table} (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$username, $email, $hashedPassword, $role]
        );
    }

    public function verifyPassword(string $password, string $hashed): bool
    {
        return password_verify($password, $hashed);
    }

    public function updateLoginInfo(int $id, string $ip): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        return $db->update(
            "UPDATE {$this->table} SET last_login_ip = ?, last_login_time = NOW(), login_count = login_count + 1 WHERE id = ?",
            [$ip, $id]
        ) > 0;
    }

    public function update(int $id, array $data): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $value = password_hash($value, PASSWORD_DEFAULT);
            }
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        return $db->update("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }
}
EOF

# 7.4 其他 Models（简化版）
cat > app/models/Tag.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Tag
{
    private string $table = 'tags';

    public function findAll(): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query("SELECT * FROM {$this->table} ORDER BY count DESC");
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE slug = ?", [$slug]);
    }
}
EOF

cat > app/models/Setting.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Setting
{
    private string $table = 'settings';

    public function get(string $key): ?string
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        $result = $db->queryOne("SELECT value FROM {$this->table} WHERE `key` = ?", [$key]);
        return $result ? $result['value'] : null;
    }

    public function set(string $key, string $value): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        $existing = $db->queryOne("SELECT id FROM {$this->table} WHERE `key` = ?", [$key]);
        if ($existing) {
            return $db->update("UPDATE {$this->table} SET `value` = ? WHERE `key` = ?", [$value, $key]) > 0;
        }
        return $db->execute("INSERT INTO {$this->table} (`key`, `value`, created_at) VALUES (?, ?, NOW())", [$key, $value]) > 0;
    }
}
EOF

# ======================================================
# 8. 生成 Controllers（完整版）
# ======================================================

# 8.1 BaseController
cat > app/controllers/BaseController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;

abstract class BaseController
{
    protected function getCache()
    {
        return Application::getInstance()->getCache();
    }

    protected function getDb()
    {
        return Application::getInstance()->getDb();
    }

    protected function getCurrentUser(): ?array
    {
        return Application::getInstance()->getCurrentUser();
    }

    protected function isLoggedIn(): bool
    {
        return Application::getInstance()->isLoggedIn();
    }

    protected function render(string $view, array $data = []): string
    {
        $viewFile = VIEW_PATH . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            return '<p>View not found: ' . $view . '</p>';
        }
        extract($data);
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
EOF

# 8.2 HomeController
cat > app/controllers/HomeController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;
use App\models\Article;
use App\models\Category;

class HomeController extends BaseController
{
    public function index(): string
    {
        $cache = Application::getInstance()->getCache();
        $cacheKey = 'home_data';
        $data = $cache && $cache->has($cacheKey) ? $cache->get($cacheKey) : null;

        if ($data === null) {
            $articleModel = new Article();
            $categoryModel = new Category();
            
            $data = [
                'articles' => $articleModel->getLatest(10),
                'categories' => $categoryModel->findAll(),
                'site_name' => '我的CMS系统',
                'site_description' => '基于PHP+MySQL+Redis的现代化CMS'
            ];
            
            if ($cache) {
                $cache->set($cacheKey, $data, 300);
            }
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($data['site_name']) . '</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
                header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px 0; }
                .header-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
                .logo { font-size: 24px; font-weight: bold; color: #2c3e50; text-decoration: none; }
                nav a { color: #666; text-decoration: none; margin-left: 20px; transition: color 0.3s; }
                nav a:hover { color: #3498db; }
                .main { padding: 40px 0; }
                .article-card { background: #fff; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
                .article-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .article-card h2 { margin: 0 0 10px 0; }
                .article-card h2 a { color: #2c3e50; text-decoration: none; }
                .article-card h2 a:hover { color: #3498db; }
                .meta { color: #999; font-size: 14px; margin-bottom: 10px; }
                .meta span { margin-right: 15px; }
                .excerpt { color: #666; line-height: 1.6; }
                .admin-bar { background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
                .admin-bar a { color: #3498db; text-decoration: none; margin-right: 15px; }
                .admin-bar a:hover { text-decoration: underline; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: #e8f5e9; color: #2e7d32; margin-left: 10px; }
                .status-badge.redis { background: #ffebee; color: #c62828; }
                footer { text-align: center; padding: 30px 0; color: #999; border-top: 1px solid #eee; margin-top: 40px; }
            </style>
        </head>
        <body>
            <header>
                <div class="container header-inner">
                    <a href="/" class="logo">📝 ' . htmlspecialchars($data['site_name']) . '</a>
                    <nav>
                        <a href="/">首页</a>';

        foreach ($data['categories'] as $cat) {
            $html .= '<a href="/category/' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</a>';
        }

        if ($this->isLoggedIn()) {
            $html .= '<a href="/admin">管理</a>';
            $html .= '<a href="/logout">退出</a>';
        } else {
            $html .= '<a href="/login">登录</a>';
            $html .= '<a href="/register">注册</a>';
        }

        $html .= '</nav></div></header>

        <main class="main">
            <div class="container">
                <div class="admin-bar">
                    <a href="/admin/articles">📋 管理文章</a>
                    <a href="/admin/article/create">➕ 新建文章</a>
                    <a href="/admin/cache/clear">🗑️ 清空缓存</a>
                    <span class="status-badge">✅ MySQL已连接</span>
                    <span class="status-badge redis">⚡ Redis缓存已启用</span>
                </div>

                <h2 style="margin-bottom: 20px;">📖 最新文章</h2>';

        if (empty($data['articles'])) {
            $html .= '<p style="text-align:center;padding:40px 0;">暂无文章，<a href="/admin/article/create">创建第一篇</a></p>';
        } else {
            foreach ($data['articles'] as $article) {
                $html .= '<div class="article-card">
                    <h2><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h2>
                    <div class="meta">
                        <span>📅 ' . date('Y-m-d', strtotime($article['created_at'])) . '</span>
                        <span>📂 ' . htmlspecialchars($article['category_name'] ?? '未分类') . '</span>
                        <span>👁️ ' . ($article['views'] ?? 0) . ' 次浏览</span>
                        ' . ($article['is_top'] ? '<span>⭐ 置顶</span>' : '') . '
                        ' . ($article['is_recommend'] ? '<span>🔥 推荐</span>' : '') . '
                    </div>
                    <p class="excerpt">' . htmlspecialchars(mb_substr($article['excerpt'] ?? $article['content'] ?? '', 0, 150)) . '...</p>
                </div>';
            }
        }

        $html .= '</div></main>
        <footer>
            <div class="container">
                <p>© ' . date('Y') . ' ' . htmlspecialchars($data['site_name']) . ' | Powered by PHP + MySQL + Redis</p>
            </div>
        </footer>
        </body>
        </html>';
        
        return $html;
    }
}
EOF

# 8.3 AuthController（用户认证）
cat > app/controllers/AuthController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\User;
use App\core\Application;

class AuthController extends BaseController
{
    public function login(): string
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->renderLogin('请填写完整信息');
            }

            $userModel = new User();
            $user = $userModel->findByUsername($username);
            
            if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
                return $this->renderLogin('用户名或密码错误');
            }

            if ($user['status'] != 1) {
                return $this->renderLogin('账号已被禁用');
            }

            Application::getInstance()->setCurrentUser($user);
            $userModel->updateLoginInfo($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
            
            $this->redirect('/admin');
        }

        return $this->renderLogin();
    }

    private function renderLogin(string $error = ''): string
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>登录</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .login-box { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
                h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 5px; color: #666; font-weight: bold; }
                input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                button { width: 100%; padding: 12px; background: #3498db; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
                button:hover { background: #2980b9; }
                .error { color: #e74c3c; text-align: center; margin-bottom: 15px; padding: 10px; background: #fde8e8; border-radius: 4px; }
                .links { text-align: center; margin-top: 20px; }
                .links a { color: #3498db; text-decoration: none; }
                .links a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h1>🔐 登录</h1>
                ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
                <form method="POST">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>密码</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit">登录</button>
                </form>
                <div class="links">
                    <a href="/">← 返回首页</a>
                </div>
            </div>
        </body>
        </html>';
    }

    public function logout(): void
    {
        Application::getInstance()->setCurrentUser(null);
        session_destroy();
        $this->redirect('/');
    }

    public function register(): string
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (strlen($username) < 3) {
                return $this->renderRegister('用户名至少3个字符');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderRegister('邮箱格式不正确');
            }
            if (strlen($password) < 6) {
                return $this->renderRegister('密码至少6个字符');
            }
            if ($password !== $passwordConfirm) {
                return $this->renderRegister('两次密码输入不一致');
            }

            $userModel = new User();
            if ($userModel->findByUsername($username)) {
                return $this->renderRegister('用户名已被使用');
            }
            if ($userModel->findByEmail($email)) {
                return $this->renderRegister('邮箱已被注册');
            }

            $userModel->create($username, $email, $password);
            $this->redirect('/login');
        }

        return $this->renderRegister();
    }

    private function renderRegister(string $error = ''): string
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>注册</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
                .register-box { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
                h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 5px; color: #666; font-weight: bold; }
                input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                button { width: 100%; padding: 12px; background: #27ae60; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
                button:hover { background: #229954; }
                .error { color: #e74c3c; text-align: center; margin-bottom: 15px; padding: 10px; background: #fde8e8; border-radius: 4px; }
                .links { text-align: center; margin-top: 20px; }
                .links a { color: #3498db; text-decoration: none; margin: 0 10px; }
                .links a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="register-box">
                <h1>📝 注册</h1>
                ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
                <form method="POST">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>密码（至少6位）</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>确认密码</label>
                        <input type="password" name="password_confirm" required>
                    </div>
                    <button type="submit">注册</button>
                </form>
                <div class="links">
                    <a href="/login">已有账号？登录</a>
                    <a href="/">← 返回首页</a>
                </div>
            </div>
        </body>
        </html>';
    }
}
EOF

# 8.4 AdminController（后台管理 - 完整版）
cat > app/controllers/AdminController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;
use App\models\Category;
use App\models\User;
use App\models\Setting;
use App\core\Application;

class AdminController extends BaseController
{
    private function checkAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
        }
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['admin', 'editor'])) {
            $this->redirect('/');
        }
    }

    public function dashboard(): string
    {
        $this->checkAuth();
        
        $db = $this->getDb();
        $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
        $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
        $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
        $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");
        
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>管理后台</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
                header { background: #2c3e50; color: #fff; padding: 15px 0; }
                header .container { display: flex; justify-content: space-between; align-items: center; }
                header a { color: #fff; text-decoration: none; margin-left: 15px; }
                .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
                .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
                .stat-card .number { font-size: 32px; font-weight: bold; color: #2c3e50; }
                .stat-card .label { color: #999; margin-top: 5px; }
                .nav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 30px 0; }
                .nav-card { background: #fff; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-decoration: none; color: #2c3e50; transition: transform 0.2s; }
                .nav-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .nav-card .icon { font-size: 36px; display: block; margin-bottom: 10px; }
                .nav-card .name { font-weight: bold; }
            </style>
        </head>
        <body>
            <header>
                <div class="container">
                    <span style="font-weight:bold;">📋 管理后台</span>
                    <div>
                        <span>👤 ' . htmlspecialchars($this->getCurrentUser()['username'] ?? '') . '</span>
                        <a href="/">首页</a>
                        <a href="/logout">退出</a>
                    </div>
                </div>
            </header>
            <div class="container">
                <div class="stats">
                    <div class="stat-card">
                        <div class="number">' . ($articleCount['count'] ?? 0) . '</div>
                        <div class="label">📄 文章</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">' . ($categoryCount['count'] ?? 0) . '</div>
                        <div class="label">📂 分类</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">' . ($commentCount['count'] ?? 0) . '</div>
                        <div class="label">💬 评论</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">' . ($userCount['count'] ?? 0) . '</div>
                        <div class="label">👤 用户</div>
                    </div>
                </div>
                <div class="nav-grid">
                    <a href="/admin/articles" class="nav-card"><span class="icon">📄</span><span class="name">文章管理</span></a>
                    <a href="/admin/categories" class="nav-card"><span class="icon">📂</span><span class="name">分类管理</span></a>
                    <a href="/admin/users" class="nav-card"><span class="icon">👤</span><span class="name">用户管理</span></a>
                    <a href="/admin/settings" class="nav-card"><span class="icon">⚙️</span><span class="name">系统设置</span></a>
                    <a href="/admin/cache/clear" class="nav-card" onclick="return confirm(\'确定要清空所有缓存吗？\')"><span class="icon">🗑️</span><span class="name">清空缓存</span></a>
                </div>
            </div>
        </body>
        </html>';
    }

    public function articles(): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articles = $articleModel->getAll();

        $html = $this->renderAdminLayout('文章管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>📄 文章管理</h2>
            <a href="/admin/article/create" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">➕ 新建文章</a>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">标题</th>
                    <th style="padding:12px;text-align:left;">分类</th>
                    <th style="padding:12px;text-align:left;">状态</th>
                    <th style="padding:12px;text-align:left;">发布时间</th>
                    <th style="padding:12px;text-align:left;">操作</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($articles)) {
            $html .= '<tr><td colspan="6" style="text-align:center;padding:20px;">暂无文章</td></tr>';
        } else {
            $statusMap = [0 => '草稿', 1 => '已发布', 2 => '待审核'];
            foreach ($articles as $article) {
                $status = $statusMap[$article['status']] ?? '未知';
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $article['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($article['title']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($article['category_name'] ?? '未分类') . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $status . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">
                        <a href="/article/' . $article['id'] . '" style="color:#3498db;text-decoration:none;margin-right:10px;">查看</a>
                        <a href="/admin/article/edit/' . $article['id'] . '" style="color:#f39c12;text-decoration:none;margin-right:10px;">编辑</a>
                        <a href="/admin/article/delete/' . $article['id'] . '" style="color:#e74c3c;text-decoration:none;" onclick="return confirm(\'确定删除吗？\')">删除</a>
                    </td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function createArticle(): string
    {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'author_id' => $this->getCurrentUser()['id'] ?? null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('标题和内容不能为空');
            }

            $articleModel = new Article();
            $id = $articleModel->create($data);
            
            if ($id) {
                $cache = $this->getCache();
                if ($cache) {
                    $cache->delete('home_data');
                    $cache->delete('page:' . md5('/'));
                }
                $this->redirect('/admin/articles');
            }
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories);
    }

    private function renderArticleForm(string $error = '', array $categories = [], ?array $article = null): string
    {
        $isEdit = $article !== null;
        $statusOptions = [
            0 => '草稿',
            1 => '已发布',
            2 => '待审核'
        ];

        $html = $this->renderAdminLayout($isEdit ? '编辑文章' : '新建文章', '
        ' . ($error ? '<div style="background:#fde8e8;color:#e74c3c;padding:10px;border-radius:4px;margin-bottom:20px;">' . htmlspecialchars($error) . '</div>' : '') . '
        <form method="POST" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">标题 *</label>
                <input type="text" name="title" value="' . ($article ? htmlspecialchars($article['title']) : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;" required>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">摘要</label>
                <input type="text" name="excerpt" value="' . ($article ? htmlspecialchars($article['excerpt'] ?? '') : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">分类</label>
                <select name="category_id" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <option value="">无分类</option>');

        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        $html .= '</select></div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">内容 *</label>
                <textarea name="content" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;min-height:300px;" required>' . ($article ? htmlspecialchars($article['content'] ?? '') : '') . '</textarea>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:15px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">状态</label>
                    <select name="status" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">');

        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $html .= '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
        }

        $html .= '</select></div>
                <div style="display:flex;align-items:center;padding-top:25px;">
                    <label style="margin-right:15px;">
                        <input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> 置顶
                    </label>
                    <label>
                        <input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend' ? 'checked' : '']) . '> 推荐
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">' . ($isEdit ? '更新文章' : '发布文章') . '</button>
                <a href="/admin/articles" style="background:#95a5a6;color:#fff;padding:12px 30px;border-radius:4px;text-decoration:none;">返回</a>
            </div>
        </form>');
        return $html;
    }

    public function editArticle(string $id): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        
        if (!$article) {
            return $this->renderAdminLayout('错误', '<p style="text-align:center;padding:40px;">文章不存在</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('标题和内容不能为空', [], $article);
            }

            $articleModel->update((int)$id, $data);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
                $cache->delete('page:' . md5('/'));
                $cache->delete('page:' . md5('/article/' . $id));
            }
            
            $this->redirect('/admin/articles');
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories, $article);
    }

    public function deleteArticle(string $id): void
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articleModel->delete((int)$id);
        
        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
            $cache->delete('page:' . md5('/'));
            $cache->delete('page:' . md5('/article/' . $id));
        }
        
        $this->redirect('/admin/articles');
    }

    public function categories(): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categories = $categoryModel->findAll(true);

        $html = $this->renderAdminLayout('分类管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>📂 分类管理</h2>
            <a href="/admin/category/create" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">➕ 新建分类</a>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">名称</th>
                    <th style="padding:12px;text-align:left;">别名</th>
                    <th style="padding:12px;text-align:left;">描述</th>
                    <th style="padding:12px;text-align:left;">操作</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($categories)) {
            $html .= '<tr><td colspan="5" style="text-align:center;padding:20px;">暂无分类</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $cat['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['name']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['slug']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">
                        <a href="/admin/category/edit/' . $cat['id'] . '" style="color:#f39c12;text-decoration:none;margin-right:10px;">编辑</a>
                        <a href="/admin/category/delete/' . $cat['id'] . '" style="color:#e74c3c;text-decoration:none;" onclick="return confirm(\'确定删除吗？\')">删除</a>
                    </td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function createCategory(): string
    {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                return $this->renderAdminLayout('新建分类', '<div style="color:#e74c3c;margin-bottom:15px;">分类名称不能为空</div>' . $this->getCategoryForm());
            }

            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name), '-'));
            }

            $categoryModel = new Category();
            $categoryModel->create($name, $slug, $description);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
            
            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('新建分类', $this->getCategoryForm());
    }

    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '
        <form method="POST" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);max-width:600px;">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">分类名称 *</label>
                <input type="text" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;" required>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">URL别名</label>
                <input type="text" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                <small style="color:#999;">留空将自动生成</small>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">描述</label>
                <textarea name="description" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;min-height:80px;">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">' . ($isEdit ? '更新分类' : '创建分类') . '</button>
                <a href="/admin/categories" style="background:#95a5a6;color:#fff;padding:12px 30px;border-radius:4px;text-decoration:none;">返回</a>
            </div>
        </form>';
    }

    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);
        
        if (!$category) {
            return $this->renderAdminLayout('错误', '<p style="text-align:center;padding:40px;">分类不存在</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            if (empty($data['name'])) {
                return $this->renderAdminLayout('编辑分类', '<div style="color:#e74c3c;margin-bottom:15px;">分类名称不能为空</div>' . $this->getCategoryForm($category));
            }

            if (empty($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $data['name']), '-'));
            }

            $categoryModel->update((int)$id, $data);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
            
            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('编辑分类', $this->getCategoryForm($category));
    }

    public function deleteCategory(string $id): void
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categoryModel->delete((int)$id);
        
        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
        }
        
        $this->redirect('/admin/categories');
    }

    public function users(): string
    {
        $this->checkAuth();
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");

        $html = $this->renderAdminLayout('用户管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>👤 用户管理</h2>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">用户名</th>
                    <th style="padding:12px;text-align:left;">邮箱</th>
                    <th style="padding:12px;text-align:left;">角色</th>
                    <th style="padding:12px;text-align:left;">状态</th>
                    <th style="padding:12px;text-align:left;">注册时间</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($users)) {
            $html .= '<tr><td colspan="6" style="text-align:center;padding:20px;">暂无用户</td></tr>';
        } else {
            $roleMap = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'subscriber' => '订阅者'];
            $statusMap = [0 => '禁用', 1 => '启用', 2 => '待验证'];
            foreach ($users as $user) {
                $role = $roleMap[$user['role']] ?? $user['role'];
                $status = $statusMap[$user['status']] ?? $user['status'];
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $user['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($user['username']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($user['email']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $role . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $status . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . date('Y-m-d', strtotime($user['created_at'])) . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function settings(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] !== 'admin') {
            $this->redirect('/admin');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settingModel = new Setting();
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit') {
                    $settingModel->set($key, trim($value));
                }
            }
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? '我的CMS系统';
        $siteDesc = $settingModel->get('site_description') ?? '基于PHP+MySQL+Redis的CMS';
        $perPage = $settingModel->get('per_page') ?? 10;

        $html = $this->renderAdminLayout('系统设置', '
        <div style="max-width:600px;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h2 style="margin-bottom:20px;">⚙️ 系统设置</h2>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">网站名称</label>
                    <input type="text" name="site_name" value="' . htmlspecialchars($siteName) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">网站描述</label>
                    <input type="text" name="site_description" value="' . htmlspecialchars($siteDesc) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">每页数量</label>
                    <input type="number" name="per_page" value="' . htmlspecialchars($perPage) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">保存设置</button>
            </form>
        </div>');
        return $html;
    }

    public function clearCache(): void
    {
        $this->checkAuth();
        $cache = $this->getCache();
        if ($cache) {
            $cache->clear();
        }
        $this->redirect('/admin');
    }

    private function renderAdminLayout(string $title, string $content): string
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($title) . ' - 管理后台</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
                header { background: #2c3e50; color: #fff; padding: 15px 0; }
                header .container { display: flex; justify-content: space-between; align-items: center; }
                header a { color: #fff; text-decoration: none; margin-left: 15px; }
                header a:hover { text-decoration: underline; }
                .content { margin-top: 20px; }
            </style>
        </head>
        <body>
            <header>
                <div class="container">
                    <span style="font-weight:bold;">📋 管理后台</span>
                    <div>
                        <a href="/admin">仪表盘</a>
                        <a href="/">首页</a>
                        <a href="/logout">退出</a>
                    </div>
                </div>
            </header>
            <div class="container">
                <div class="content">' . $content . '</div>
            </div>
        </body>
        </html>';
    }
}
EOF

# 8.5 ArticleController
cat > app/controllers/ArticleController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;

class ArticleController extends BaseController
{
    public function show(string $id): string
    {
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        
        if (!$article) {
            return '<h1 style="text-align:center;padding:40px;">文章不存在</h1>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($article['meta_title'] ?? $article['title']) . '</title>
            <meta name="description" content="' . htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? '') . '">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.8; color: #333; }
                .article h1 { margin-top: 0; font-size: 28px; }
                .meta { color: #999; font-size: 14px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .meta span { margin-right: 15px; }
                .content { font-size: 16px; }
                .content pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
                .content code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 14px; }
                .back { display: inline-block; margin-top: 30px; color: #3498db; text-decoration: none; }
                .back:hover { text-decoration: underline; }
                .admin-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
                .admin-links a { margin-right: 15px; color: #666; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="article">
                <h1>' . htmlspecialchars($article['title']) . '</h1>
                <div class="meta">
                    <span>📅 ' . date('Y-m-d H:i', strtotime($article['published_at'] ?? $article['created_at'])) . '</span>
                    <span>📂 ' . htmlspecialchars($article['category_name'] ?? '未分类') . '</span>
                    <span>👤 ' . htmlspecialchars($article['author_name'] ?? '未知') . '</span>
                    <span>👁️ ' . ($article['views'] ?? 0) . ' 次浏览</span>
                </div>
                <div class="content">' . nl2br(htmlspecialchars($article['content'] ?? '')) . '</div>
                <a href="/" class="back">← 返回首页</a>
                <div class="admin-links">
                    <a href="/admin/article/edit/' . $article['id'] . '">✏️ 编辑</a>
                    <a href="/admin/articles">📋 管理</a>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
EOF

# 8.6 CategoryController
cat > app/controllers/CategoryController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Category;
use App\models\Article;

class CategoryController extends BaseController
{
    public function index(string $slug): string
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBySlug($slug);
        
        if (!$category) {
            return '<h1 style="text-align:center;padding:40px;">分类不存在</h1>';
        }

        $articleModel = new Article();
        $articles = $articleModel->getByCategory($category['id']);

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($category['name']) . ' - 分类</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                .article-list { margin-top: 20px; }
                .article-item { padding: 15px 0; border-bottom: 1px solid #eee; }
                .article-item h3 { margin: 0 0 5px 0; }
                .article-item h3 a { color: #333; text-decoration: none; }
                .article-item h3 a:hover { color: #3498db; }
                .article-item .meta { color: #999; font-size: 14px; }
                .back { display: inline-block; margin-top: 20px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>📂 ' . htmlspecialchars($category['name']) . '</h1>
            <p>' . htmlspecialchars($category['description'] ?? '') . '</p>
            <div class="article-list">';

        if (empty($articles)) {
            $html .= '<p>该分类下暂无文章</p>';
        } else {
            foreach ($articles as $article) {
                $html .= '<div class="article-item">
                    <h3><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h3>
                    <div class="meta">' . date('Y-m-d', strtotime($article['created_at'])) . ' | ' . ($article['views'] ?? 0) . ' 次浏览</div>
                </div>';
            }
        }

        $html .= '</div>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
        
        return $html;
    }
}
EOF

# 8.7 TagController
cat > app/controllers/TagController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Tag;

class TagController extends BaseController
{
    public function index(string $slug): string
    {
        $tagModel = new Tag();
        $tag = $tagModel->findBySlug($slug);
        
        if (!$tag) {
            return '<h1 style="text-align:center;padding:40px;">标签不存在</h1>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($tag['name']) . ' - 标签</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                .back { display: inline-block; margin-top: 20px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>🏷️ ' . htmlspecialchars($tag['name']) . '</h1>
            <p>标签下有 ' . ($tag['count'] ?? 0) . ' 篇文章</p>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
    }
}
EOF

# 8.8 PageController
cat > app/controllers/PageController.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;

class PageController extends BaseController
{
    public function show(string $slug): string
    {
        $db = $this->getDb();
        if (!$db) {
            return '<h1 style="text-align:center;padding:40px;">数据库连接失败</h1>';
        }
        
        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND is_show = 1", [$slug]);
        
        if (!$page) {
            return '<h1 style="text-align:center;padding:40px;">页面不存在</h1>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($page['meta_title'] ?? $page['title']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.8; }
                .back { display: inline-block; margin-top: 30px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($page['title']) . '</h1>
            <div>' . nl2br(htmlspecialchars($page['content'] ?? '')) . '</div>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
    }
}
EOF

# ======================================================
# 9. 生成 .htaccess
# ======================================================
cat > public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# 安全设置
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# 禁止访问敏感文件
<FilesMatch "^(config|database|install)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF

# ======================================================
# 10. 生成 Nginx 配置
# ======================================================
cat > nginx-config-example.conf << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/cms/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }
}
EOF

# ======================================================
# 11. 生成安装脚本（完整版）
# ======================================================
cat > install.php << 'EOF'
<?php
/**
 * CMS系统完整安装脚本
 * 自动创建所有数据表和初始数据
 */
declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

// 自动加载
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

use App\core\Application;

echo "========================================\n";
echo "  CMS系统完整安装程序\n";
echo "  兼容: MySQL 5.7+ / MariaDB 10.3+\n";
echo "========================================\n\n";

// 检查配置文件
if (!file_exists(APP_PATH . '/config/config.php')) {
    die("❌ 配置文件不存在！\n请复制 app/config/config.example.php 为 config.php 并配置数据库\n");
}

// 初始化应用
$app = Application::getInstance();

// 检查数据库连接
$db = $app->getDb();
if (!$db) {
    die("❌ 数据库连接失败！\n请检查 app/config/config.php 中的数据库配置\n");
}

echo "✅ 数据库连接成功\n\n";

// 获取所有SQL语句
$sqlFile = ROOT_PATH . '/install.sql';
if (!file_exists($sqlFile)) {
    die("❌ install.sql 文件不存在！\n");
}

$sqlContent = file_get_contents($sqlFile);

// 按分号分割SQL语句
$statements = array_filter(array_map('trim', explode(';', $sqlContent)));

echo "📋 正在执行SQL安装脚本...\n";
$count = 0;
$errors = [];

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    try {
        $db->getPdo()->exec($statement);
        $count++;
        echo "  ✅ 执行成功\n";
    } catch (Exception $e) {
        // 忽略 "表已存在" 等错误
        if (strpos($e->getMessage(), 'already exists') === false) {
            $errors[] = $e->getMessage();
        }
    }
}

echo "\n✅ 数据库安装完成！共执行 {$count} 条SQL语句\n";

if (!empty($errors)) {
    echo "\n⚠️ 警告：以下SQL执行失败（可能已存在）:\n";
    foreach ($errors as $error) {
        echo "  - " . substr($error, 0, 100) . "\n";
    }
}

echo "\n========================================\n";
echo "  🎉 安装完成！\n";
echo "========================================\n\n";
echo "🔑 登录信息:\n";
echo "  - 用户名: admin\n";
echo "  - 密码: admin123\n\n";
echo "🌐 访问地址:\n";
echo "  - 前台: http://你的域名/\n";
echo "  - 后台: http://你的域名/admin\n\n";
echo "⚠️  安全提示:\n";
echo "  1. 请立即修改管理员密码\n";
echo "  2. 生产环境请关闭调试模式 (config.php中的debug改为false)\n";
echo "  3. 建议删除 install.php 和 install.sql 文件\n\n";
EOF

# ======================================================
# 12. 生成完整的SQL安装文件（对应你提供的版本）
# ======================================================
cat > install.sql << 'EOF'
-- ======================================================
-- CMS系统完整数据库安装脚本
-- 兼容 MySQL 5.7+ / MariaDB 10.3+
-- 字符集: utf8mb4 (支持emoji和完整Unicode)
-- ======================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ======================================================
-- 1. 文章表 (articles)
-- ======================================================
DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '文章ID',
    `title` VARCHAR(255) NOT NULL COMMENT '文章标题',
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL别名（SEO友好）',
    `content` LONGTEXT NOT NULL COMMENT '文章内容',
    `excerpt` VARCHAR(500) DEFAULT NULL COMMENT '文章摘要',
    `category_id` INT(11) DEFAULT NULL COMMENT '分类ID',
    `author_id` INT(11) DEFAULT NULL COMMENT '作者ID',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0-草稿, 1-已发布, 2-待审核',
    `views` INT(11) NOT NULL DEFAULT 0 COMMENT '浏览次数',
    `is_top` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否置顶: 0-否, 1-是',
    `is_recommend` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否推荐: 0-否, 1-是',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图片',
    `tags` VARCHAR(255) DEFAULT NULL COMMENT '标签（逗号分隔）',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO标题',
    `meta_description` VARCHAR(500) DEFAULT NULL COMMENT 'SEO描述',
    `meta_keywords` VARCHAR(255) DEFAULT NULL COMMENT 'SEO关键词',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `published_at` DATETIME DEFAULT NULL COMMENT '发布时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_author_id` (`author_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_published_at` (`published_at`),
    KEY `idx_views` (`views`),
    KEY `idx_is_top` (`is_top`),
    KEY `idx_is_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';

-- ======================================================
-- 2. 分类表 (categories)
-- ======================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
    `name` VARCHAR(100) NOT NULL COMMENT '分类名称',
    `slug` VARCHAR(100) NOT NULL COMMENT 'URL别名',
    `description` VARCHAR(500) DEFAULT NULL COMMENT '分类描述',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父级分类ID (0表示顶级)',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '分类封面图',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';

-- ======================================================
-- 3. 用户表 (users)
-- ======================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（哈希）',
    `salt` VARCHAR(32) DEFAULT NULL COMMENT '密码盐值',
    `nickname` VARCHAR(100) DEFAULT NULL COMMENT '昵称',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    `role` ENUM('admin','editor','author','subscriber') NOT NULL DEFAULT 'subscriber' COMMENT '角色',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0-禁用, 1-启用, 2-待验证',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `login_count` INT(11) NOT NULL DEFAULT 0 COMMENT '登录次数',
    `remember_token` VARCHAR(100) DEFAULT NULL COMMENT '记住我Token',
    `email_verified_at` DATETIME DEFAULT NULL COMMENT '邮箱验证时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ======================================================
-- 4. 评论表 (comments)
-- ======================================================
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '评论ID',
    `article_id` INT(11) NOT NULL COMMENT '文章ID',
    `user_id` INT(11) DEFAULT NULL COMMENT '用户ID（匿名评论可为NULL）',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父评论ID (0表示顶级)',
    `content` TEXT NOT NULL COMMENT '评论内容',
    `author_name` VARCHAR(100) DEFAULT NULL COMMENT '匿名评论者姓名',
    `author_email` VARCHAR(100) DEFAULT NULL COMMENT '匿名评论者邮箱',
    `author_url` VARCHAR(255) DEFAULT NULL COMMENT '匿名评论者网站',
    `author_ip` VARCHAR(45) DEFAULT NULL COMMENT '评论者IP',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT '用户代理',
    `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '状态: 0-待审核, 1-通过, 2-垃圾评论, 3-已删除',
    `like_count` INT(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';

-- ======================================================
-- 5. 页面表 (pages)
-- ======================================================
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '页面ID',
    `title` VARCHAR(255) NOT NULL COMMENT '页面标题',
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL别名',
    `content` LONGTEXT NOT NULL COMMENT '页面内容',
    `excerpt` VARCHAR(500) DEFAULT NULL COMMENT '页面摘要',
    `template` VARCHAR(100) DEFAULT 'default' COMMENT '页面模板',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父页面ID (0表示顶级)',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `is_nav` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否导航菜单: 0-否, 1-是',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO标题',
    `meta_description` VARCHAR(500) DEFAULT NULL COMMENT 'SEO描述',
    `meta_keywords` VARCHAR(255) DEFAULT NULL COMMENT 'SEO关键词',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`),
    KEY `idx_is_nav` (`is_nav`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面表';

-- ======================================================
-- 6. 友情链接表 (links)
-- ======================================================
DROP TABLE IF EXISTS `links`;
CREATE TABLE `links` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '链接ID',
    `name` VARCHAR(100) NOT NULL COMMENT '链接名称',
    `url` VARCHAR(255) NOT NULL COMMENT '链接地址',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '链接描述',
    `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Logo图片',
    `target` ENUM('_blank','_self','_parent','_top') NOT NULL DEFAULT '_blank' COMMENT '打开方式',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='友情链接表';

-- ======================================================
-- 7. 系统配置表 (settings)
-- ======================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '配置ID',
    `key` VARCHAR(100) NOT NULL COMMENT '配置键名',
    `value` TEXT DEFAULT NULL COMMENT '配置值',
    `group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '配置分组',
    `type` ENUM('text','textarea','number','boolean','select','image','file') NOT NULL DEFAULT 'text' COMMENT '配置类型',
    `label` VARCHAR(100) NOT NULL COMMENT '显示标签',
    `description` VARCHAR(500) DEFAULT NULL COMMENT '配置说明',
    `options` TEXT DEFAULT NULL COMMENT '选项值（JSON格式）',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统配置: 0-否, 1-是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_group` (`group`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ======================================================
-- 8. 缓存管理表 (cache)
-- ======================================================
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '缓存ID',
    `key` VARCHAR(255) NOT NULL COMMENT '缓存键',
    `value` LONGTEXT NOT NULL COMMENT '缓存值（序列化）',
    `expire_at` DATETIME NOT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='缓存表（备用）';

-- ======================================================
-- 9. 操作日志表 (logs)
-- ======================================================
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
    `user_id` INT(11) DEFAULT NULL COMMENT '操作用户ID',
    `username` VARCHAR(50) DEFAULT NULL COMMENT '操作用户名',
    `ip` VARCHAR(45) NOT NULL COMMENT '操作IP',
    `url` VARCHAR(500) NOT NULL COMMENT '操作URL',
    `method` VARCHAR(10) NOT NULL COMMENT '请求方法',
    `action` VARCHAR(100) NOT NULL COMMENT '操作动作',
    `data` TEXT DEFAULT NULL COMMENT '操作数据（JSON格式）',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT '用户代理',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ======================================================
-- 10. 会话表 (sessions) - 用于自定义会话存储
-- ======================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(128) NOT NULL COMMENT '会话ID',
    `data` LONGTEXT NOT NULL COMMENT '会话数据',
    `expire_at` INT(11) NOT NULL COMMENT '过期时间戳',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话表';

-- ======================================================
-- 11. 标签表 (tags)
-- ======================================================
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '标签ID',
    `name` VARCHAR(50) NOT NULL COMMENT '标签名称',
    `slug` VARCHAR(50) NOT NULL COMMENT 'URL别名',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '标签描述',
    `count` INT(11) NOT NULL DEFAULT 0 COMMENT '文章数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';

-- ======================================================
-- 12. 文章-标签关联表 (article_tags)
-- ======================================================
DROP TABLE IF EXISTS `article_tags`;
CREATE TABLE `article_tags` (
    `article_id` INT(11) NOT NULL COMMENT '文章ID',
    `tag_id` INT(11) NOT NULL COMMENT '标签ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`article_id`, `tag_id`),
    KEY `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章标签关联表';

-- ======================================================
-- 插入初始数据
-- ======================================================

-- 插入默认管理员用户
-- 密码: admin123
INSERT INTO `users` (`username`, `email`, `password`, `nickname`, `role`, `status`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'admin', 1, NOW());

-- 插入初始分类数据
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_show`, `created_at`) VALUES
('技术', 'tech', '技术类文章，包括编程、架构、数据库等', 1, 1, NOW()),
('生活', 'life', '生活感悟、旅行、美食等', 2, 1, NOW()),
('随笔', 'note', '随手写下的想法和笔记', 3, 1, NOW()),
('产品', 'product', '产品设计、用户体验、项目管理', 4, 1, NOW()),
('设计', 'design', 'UI设计、视觉设计、设计思维', 5, 1, NOW());

-- 插入示例文章
INSERT INTO `articles` (`title`, `slug`, `content`, `excerpt`, `category_id`, `author_id`, `status`, `views`, `is_top`, `is_recommend`, `created_at`, `published_at`) VALUES
('欢迎使用CMS系统', 'welcome-to-cms', '## 欢迎使用我们的CMS系统！\n\n这是一个基于 **PHP + MySQL + Redis** 构建的现代化内容管理系统。\n\n### 主要特性\n\n- 🚀 **高性能**：采用Redis缓存，页面加载速度提升10倍\n- 🔒 **安全可靠**：PDO预处理防止SQL注入，密码哈希加密\n- 📱 **响应式设计**：完美适配PC、平板、手机\n- 🔍 **SEO友好**：支持自定义URL、Meta标签、站点地图\n- 📊 **数据统计**：文章浏览统计、用户行为分析\n\n### 快速上手\n\n1. 登录后台管理\n2. 创建分类和文章\n3. 配置网站基本设置\n4. 开始发布内容！\n\n祝您使用愉快！ 🎉', '欢迎使用基于PHP+MySQL+Redis的现代化CMS系统，包含完整的前后台功能。', 1, 1, 1, 125, 1, 1, NOW(), NOW()),
('Redis缓存性能优化指南', 'redis-cache-optimization', '## Redis缓存性能优化指南\n\nRedis 是一个高性能的键值对数据库，常用于缓存层。\n\n### 缓存策略\n\n#### 1. 页面缓存\n将完整的HTML页面缓存到Redis中，减少数据库查询。\n\n#### 2. 数据缓存\n缓存常用的查询结果，如文章列表、分类数据等。\n\n#### 3. 会话缓存\n使用Redis存储用户会话，支持分布式部署。\n\n### 缓存失效策略\n\n- **LRU算法**：自动淘汰最少使用的缓存\n- **TTL设置**：根据数据更新频率设置过期时间\n- **主动失效**：数据更新时主动清除相关缓存\n\n### 性能数据\n\n启用Redis缓存后：\n- 页面响应时间：**从200ms降至20ms**\n- 数据库查询：**减少90%**\n- 并发处理能力：**提升5倍**\n\n> 💡 推荐阅读：[Redis官方文档](https://redis.io/documentation)', '深入探讨Redis缓存策略，包括页面缓存、数据缓存和会话缓存的最佳实践。', 1, 1, 1, 89, 0, 1, NOW(), NOW()),
('PHP 8 新特性全面解析', 'php8-new-features', '## PHP 8 新特性全面解析\n\nPHP 8 带来了许多激动人心的新特性，让开发更高效、代码更优雅。\n\n### 主要新特性\n\n#### 1. JIT（即时编译）\nJIT 可以显著提升CPU密集型应用的性能。\n\n```php\n// JIT 在 PHP 8 中默认启用\n// 在 php.ini 中配置：\n// opcache.jit = tracing\n// opcache.jit_buffer_size = 100M\n```\n\n#### 2. 命名参数（Named Arguments）\n不用再记住参数的顺序了！\n\n```php\nfunction createUser($name, $email, $role = 'user') {\n    // ...\n}\n\n// 使用命名参数\ncreateUser(\n    name: \"张三\",\n    email: \"zhangsan@example.com\",\n    role: \"admin\"\n);\n```\n\n#### 3. 属性（Attributes）\n类似其他语言的注解（Annotations）。\n\n```php\n#[Route(\"/api/users\", methods: [\"GET\"])]\nclass UserController {\n    // ...\n}\n```\n\n#### 4. 匹配表达式（Match Expression）\n比 switch 更强大、更安全。\n\n```php\n$result = match($status) {\n    200 => \"OK\",\n    404 => \"Not Found\",\n    500 => \"Server Error\",\n    default => \"Unknown\"\n};\n```\n\n### 性能对比\n\n| 版本 | 响应时间 | 内存占用 |\n|------|---------|---------|\n| PHP 7.4 | 100ms | 8MB |\n| PHP 8.0 | 80ms (-20%) | 7MB (-12%) |\n| PHP 8.1 | 65ms (-35%) | 6.5MB (-18%) |\n| PHP 8.2 | 60ms (-40%) | 6MB (-25%) |\n\n🚀 **升级建议**：生产环境建议升级到 PHP 8.1 或 8.2', '全面解析PHP 8的JIT编译、命名参数、属性等新特性，含性能对比数据。', 1, 1, 1, 210, 0, 0, NOW(), NOW()),
('打造高效开发环境', 'efficient-dev-environment', '## 打造高效开发环境\n\n一个优秀的开发环境可以提升团队效率50%以上。\n\n### 必备工具\n\n#### 编辑器\n- **VS Code**：免费、插件丰富\n- **PHPStorm**：专业PHP IDE\n- **Sublime Text**：轻量快速\n\n#### 版本控制\n- **Git** + **GitHub/GitLab**\n- 使用分支策略：feature -> develop -> main\n\n#### 本地开发环境\n- **Docker**：容器化，环境一致性\n- **XAMPP/MAMP**：快速部署\n- **Laravel Valet**：Mac开发利器\n\n### 调试工具\n\n- **Xdebug**：PHP调试器\n- **Postman**：API测试\n- **MySQL Workbench**：数据库管理\n\n### 代码质量\n\n- **PHPStan**：静态代码分析\n- **PHP_CodeSniffer**：代码规范检查\n- **PHPUnit**：单元测试', '如何配置高效的PHP开发环境，包含编辑器、调试工具、CI/CD等完整方案。', 1, 1, 1, 67, 0, 0, NOW(), NOW()),
('PHP设计模式实战', 'php-design-patterns', '## PHP设计模式实战\n\n设计模式是软件开发中的重要经验总结。\n\n### 常用设计模式\n\n#### 1. 单例模式（Singleton）\n确保一个类只有一个实例。\n\n```php\nclass Database {\n    private static $instance = null;\n    private $connection;\n    \n    private function __construct() {\n        // 私有构造函数\n    }\n    \n    public static function getInstance() {\n        if (self::$instance === null) {\n            self::$instance = new self();\n        }\n        return self::$instance;\n    }\n}\n```\n\n#### 2. 工厂模式（Factory）\n创建对象的接口。\n\n```php\ninterface Logger {\n    public function log($message);\n}\n\nclass FileLogger implements Logger {\n    public function log($message) {\n        file_put_contents(\"log.txt\", $message);\n    }\n}\n\nclass LoggerFactory {\n    public static function create($type) {\n        switch($type) {\n            case \"file\":\n                return new FileLogger();\n            case \"database\":\n                return new DatabaseLogger();\n            default:\n                throw new Exception(\"Unknown logger type\");\n        }\n    }\n}\n```\n\n### 设计原则\n\n- **SOLID原则**：单一职责、开闭、里氏替换、接口隔离、依赖倒置\n- **DRY**：不要重复自己\n- **KISS**：保持简单', 'PHP设计模式实战教程，包含单例、工厂、观察者等模式的代码示例。', 1, 1, 1, 156, 0, 1, NOW(), NOW()),
('MySQL性能优化技巧', 'mysql-performance-optimization', '## MySQL性能优化技巧\n\nMySQL是PHP应用最常用的数据库。\n\n### 索引优化\n\n#### 选择合适的索引类型\n- **B-Tree索引**：最常用，适用于等值和范围查询\n- **全文索引**：用于文本搜索\n- **哈希索引**：内存表快速查询\n\n```sql\n-- 创建复合索引\nCREATE INDEX idx_category_status ON articles(category_id, status);\n\n-- 使用覆盖索引\nSELECT id, title FROM articles WHERE category_id = 1;\n```\n\n### 查询优化\n\n#### 避免SELECT *\n只选择需要的字段。\n\n```sql\n-- ❌ 不推荐\nSELECT * FROM articles;\n\n-- ✅ 推荐\nSELECT id, title, created_at FROM articles;\n```\n\n#### 使用EXPLAIN分析\n```sql\nEXPLAIN SELECT * FROM articles WHERE category_id = 1;\n```\n\n### 配置优化\n\n```ini\n# my.cnf 关键配置\ninnodb_buffer_pool_size = 1G\nquery_cache_size = 128M\nmax_connections = 500\ninnodb_log_file_size = 256M\n```\n\n### 监控工具\n\n- **MySQL Slow Query Log**\n- **Percona Toolkit**\n- **phpMyAdmin** 状态监控\n\n> 📊 优化后效果：查询速度提升 **80%**', 'MySQL性能优化实战，包括索引优化、查询优化和服务器配置优化。', 1, 1, 1, 98, 0, 0, NOW(), NOW());

-- 插入友情链接
INSERT INTO `links` (`name`, `url`, `description`, `sort_order`, `is_show`, `created_at`) VALUES
('PHP官方', 'https://php.net', 'PHP官方文档和资源', 1, 1, NOW()),
('MySQL官方', 'https://mysql.com', 'MySQL数据库官方', 2, 1, NOW()),
('Redis官方', 'https://redis.io', 'Redis缓存数据库官方', 3, 1, NOW()),
('GitHub', 'https://github.com', '全球最大的代码托管平台', 4, 1, NOW());

-- 插入系统配置
INSERT INTO `settings` (`key`, `value`, `group`, `type`, `label`, `description`, `sort_order`, `is_system`) VALUES
('site_name', '我的CMS系统', 'general', 'text', '网站名称', '网站的显示名称', 1, 1),
('site_description', '基于PHP+MySQL+Redis的CMS系统', 'general', 'text', '网站描述', '网站简短描述', 2, 1),
('site_keywords', 'CMS,PHP,MySQL,Redis,内容管理', 'general', 'text', '网站关键词', 'SEO关键词', 3, 1),
('site_logo', '', 'general', 'image', 'Logo', '网站Logo图片', 4, 1),
('site_footer', '© 2026 我的CMS系统 | Powered by PHP', 'general', 'textarea', '页脚信息', '网站底部版权信息', 5, 1),
('admin_email', 'admin@example.com', 'general', 'text', '管理员邮箱', '系统通知邮箱', 6, 1),
('per_page', '10', 'general', 'number', '每页数量', '列表页每页显示数量', 7, 1);

-- 重置自增起始值
ALTER TABLE `articles` AUTO_INCREMENT = 1000;
ALTER TABLE `categories` AUTO_INCREMENT = 1000;
ALTER TABLE `users` AUTO_INCREMENT = 1000;
ALTER TABLE `comments` AUTO_INCREMENT = 1000;
ALTER TABLE `pages` AUTO_INCREMENT = 1000;
ALTER TABLE `links` AUTO_INCREMENT = 1000;
ALTER TABLE `settings` AUTO_INCREMENT = 1000;

SET FOREIGN_KEY_CHECKS = 1;

-- ======================================================
-- 安装完成！
-- 默认管理员账号: admin
-- 默认管理员密码: admin123
-- ======================================================
EOF

# ======================================================
# 13. 设置权限
# ======================================================
chmod -R 755 app public var
chmod 755 install.php install.sql 2>/dev/null || true

# 创建会话目录
mkdir -p var/sessions
chmod 755 var/sessions

echo ""
echo "========================================"
echo "  ✅ CMS系统生成完成（完整版）！"
echo "========================================"
echo ""
echo "📁 目录结构:"
echo "  ├── app/"
echo "  │   ├── core/          (核心类)"
echo "  │   ├── controllers/   (控制器 - 完整版)"
echo "  │   ├── models/        (数据模型 - 完整版)"
echo "  │   ├── config/        (配置文件)"
echo "  │   └── routes.php     (路由定义)"
echo "  ├── public/            (Web根目录)"
echo "  ├── var/               (缓存/会话/日志)"
echo "  ├── install.php        (PHP安装脚本)"
echo "  ├── install.sql        (SQL安装脚本)"
echo "  └── nginx-config-example.conf"
echo ""
echo "📊 数据库表: 12张 (articles, categories, users, comments, pages, links, settings, cache, logs, sessions, tags, article_tags)"
echo ""
echo "🔧 安装步骤:"
echo "1. 创建MySQL数据库 (例如: cms_db)"
echo "2. 编辑 app/config/config.php 配置数据库连接"
echo "3. 执行安装:"
echo "   - 方式A: php install.php (自动导入SQL)"
echo "   - 方式B: 在phpMyAdmin中导入 install.sql"
echo "4. 将网站根目录指向 public/ 文件夹"
echo "5. 确保 Redis 服务已启动"
echo ""
echo "🔑 默认管理员: admin / admin123"
echo "🔗 管理入口: /admin"
echo ""
echo "⚠️  安全提示:"
echo "  1. 请立即修改管理员密码"
echo "  2. 生产环境请关闭调试模式 (config.php中debug改为false)"
echo "  3. 建议删除 install.php 和 install.sql"
echo ""