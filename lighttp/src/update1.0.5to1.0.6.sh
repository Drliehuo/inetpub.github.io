#!/bin/bash
# ======================================================
# Lighttp v1.0.6 - Cookie安全与CSRF防护升级脚本 (修复版)
# 修复：ini_set() 参数类型错误
# 运行：bash update-v1.0.6-fixed.sh
# ======================================================

set -e

echo "=========================================="
echo "  Lighttp v1.0.6 - Cookie安全与CSRF防护"
echo "  修复版 - 2026-07-16"
echo "=========================================="
echo ""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ ! -d "app/core" ] || [ ! -d "app/controllers" ]; then
    echo -e "${RED}错误：请在 Lighttp 项目根目录下运行此脚本${NC}"
    exit 1
fi

# ======================================================
# 1. 备份原文件
# ======================================================
echo -e "${YELLOW}[1/6] 备份原文件...${NC}"
BACKUP_DIR="app/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp app/core/Application.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/controllers/BaseController.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/controllers/AuthController.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/controllers/AdminController.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/config/config.php "$BACKUP_DIR/" 2>/dev/null || true
echo -e "${GREEN}备份已保存至: $BACKUP_DIR${NC}"

# ======================================================
# 2. 更新 app/config/config.php
# ======================================================
echo -e "${YELLOW}[2/6] 更新 app/config/config.php...${NC}"

cat > app/config/config.php << 'EOF'
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
EOF

# ======================================================
# 3. 更新 app/core/Application.php (修复 ini_set 类型错误)
# ======================================================
echo -e "${YELLOW}[3/6] 更新 app/core/Application.php...${NC}"

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
EOF

# ======================================================
# 4. 更新 app/controllers/BaseController.php
# ======================================================
echo -e "${YELLOW}[4/6] 更新 app/controllers/BaseController.php...${NC}"

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

    /**
     * 生成 CSRF Token
     */
    protected function csrfToken(): string
    {
        $config = Application::getInstance()->getConfig();
        $tokenName = $config['security']['csrf_token_name'] ?? 'lig_csrf_token';
        $lifetime = $config['security']['csrf_token_lifetime'] ?? 3600;

        if (!isset($_SESSION[$tokenName]) || !isset($_SESSION[$tokenName . '_time'])) {
            $_SESSION[$tokenName] = bin2hex(random_bytes(32));
            $_SESSION[$tokenName . '_time'] = time();
        }

        // 检查 Token 是否过期
        if (time() - $_SESSION[$tokenName . '_time'] > $lifetime) {
            $_SESSION[$tokenName] = bin2hex(random_bytes(32));
            $_SESSION[$tokenName . '_time'] = time();
        }

        return $_SESSION[$tokenName];
    }

    /**
     * 验证 CSRF Token
     */
    protected function validateCsrf(string $token): bool
    {
        $config = Application::getInstance()->getConfig();
        $tokenName = $config['security']['csrf_token_name'] ?? 'lig_csrf_token';

        if (!isset($_SESSION[$tokenName])) {
            return false;
        }

        return hash_equals($_SESSION[$tokenName], $token);
    }

    /**
     * 生成 CSRF 隐藏字段 HTML
     */
    protected function csrfField(): string
    {
        $token = $this->csrfToken();
        return '<input type="hidden" name="lig_csrf_token" value="' . $token . '">';
    }

    /**
     * 验证 POST 请求的 CSRF Token（自动验证）
     * 如果验证失败，返回 false 并设置 403 状态码
     */
    protected function verifyCsrf(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }

        $token = $_POST['lig_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($token) || !$this->validateCsrf($token)) {
            http_response_code(403);
            return false;
        }

        return true;
    }
}
EOF

# ======================================================
# 5. 更新 app/controllers/AuthController.php
# ======================================================
echo -e "${YELLOW}[5/6] 更新 app/controllers/AuthController.php...${NC}"

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
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderLogin('CSRF token validation failed');
            }

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                return $this->renderLogin('Please fill in all fields');
            }

            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
                return $this->renderLogin('Invalid username or password');
            }

            // 检查是否需要重哈希（升级到更高成本因子）
            if ($userModel->needsRehash($user['password'])) {
                $userModel->rehashPassword($user['id'], $password);
                $user = $userModel->find($user['id']);
            }

            if ($user['status'] != 1) {
                return $this->renderLogin('Account is disabled');
            }

            Application::getInstance()->setCurrentUser($user);
            $userModel->updateLoginInfo($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');

            $this->redirect('/admin');
        }

        return $this->renderLogin();
    }

    private function renderLogin(string $error = ''): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Login</h1>
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo $this->csrfField(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">Login</button>
        </form>
        <div class="auth-links">
            <a href="/register">Register</a> | <a href="/">Back to home</a>
        </div>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }

    public function logout(): void
    {
        // 清除 CSRF Token
        $config = Application::getInstance()->getConfig();
        $tokenName = $config['security']['csrf_token_name'] ?? 'lig_csrf_token';
        unset($_SESSION[$tokenName]);
        unset($_SESSION[$tokenName . '_time']);

        Application::getInstance()->setCurrentUser(null);

        // 清除会话Cookie
        $sec = $config['security'];
        $cookiePrefix = $sec['cookie_prefix'] ?? 'lig_';
        $path = $sec['cookie_path'] ?? '/';
        $domain = $sec['cookie_domain'] ?? '';

        if (isset($_COOKIE[$cookiePrefix . 'session'])) {
            setcookie($cookiePrefix . 'session', '', time() - 3600, $path, $domain, false, true);
        }

        session_destroy();

        $this->redirect('/');
    }

    public function register(): string
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderRegister('CSRF token validation failed');
            }

            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (strlen($username) < 3) {
                return $this->renderRegister('Username must be at least 3 characters');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderRegister('Invalid email format');
            }
            if (strlen($password) < 6) {
                return $this->renderRegister('Password must be at least 6 characters');
            }
            if ($password !== $passwordConfirm) {
                return $this->renderRegister('Passwords do not match');
            }

            $userModel = new User();
            if ($userModel->findByUsername($username)) {
                return $this->renderRegister('Username already taken');
            }
            if ($userModel->findByEmail($email)) {
                return $this->renderRegister('Email already registered');
            }

            $userModel->create($username, $email, $password);
            $this->redirect('/login');
        }

        return $this->renderRegister();
    }

    private function renderRegister(string $error = ''): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Register</h1>
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo $this->csrfField(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password (min 6 chars)</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="btn-submit">Register</button>
        </form>
        <div class="auth-links">
            <a href="/login">Already have an account? Login</a> | <a href="/">Back to home</a>
        </div>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 6. 更新 app/controllers/AdminController.php (添加CSRF防护)
# ======================================================
echo -e "${YELLOW}[6/6] 更新 app/controllers/AdminController.php...${NC}"

# 由于 AdminController 文件较大，使用 cat 完整覆盖
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

    private function renderAdminLayout(string $title, string $content): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Admin</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="brand"><a href="/admin">Lighttp Admin</a></div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($this->getCurrentUser()['username'] ?? ''); ?></span>
                <a href="/">Home</a>
                <a href="/logout">Logout</a>
            </div>
        </div>
    </header>
    <main class="admin-content">
        <div class="container">
            <?php echo $content; ?>
        </div>
    </main>
    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }

    public function dashboard(): string
    {
        $this->checkAuth();
        $db = $this->getDb();
        $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
        $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
        $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
        $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");

        $content = '<div class="page-title">Dashboard</div>
        <div class="stats-grid">
            <div class="stat-card"><span class="number">' . ($articleCount['count'] ?? 0) . '</span><span class="label">Articles</span></div>
            <div class="stat-card"><span class="number">' . ($categoryCount['count'] ?? 0) . '</span><span class="label">Categories</span></div>
            <div class="stat-card"><span class="number">' . ($commentCount['count'] ?? 0) . '</span><span class="label">Comments</span></div>
            <div class="stat-card"><span class="number">' . ($userCount['count'] ?? 0) . '</span><span class="label">Users</span></div>
        </div>
        <div class="admin-grid">
            <a href="/admin/articles" class="admin-card"><span class="icon">[A]</span><span class="name">Articles</span></a>
            <a href="/admin/categories" class="admin-card"><span class="icon">[C]</span><span class="name">Categories</span></a>
            <a href="/admin/users" class="admin-card"><span class="icon">[U]</span><span class="name">Users</span></a>
            <a href="/admin/settings" class="admin-card"><span class="icon">[S]</span><span class="name">Settings</span></a>
            <a href="/admin/cache/clear" class="admin-card" onclick="return confirm(\'Clear all cache?\')"><span class="icon">[X]</span><span class="name">Clear Cache</span></a>
        </div>';

        return $this->renderAdminLayout('Dashboard', $content);
    }

    public function articles(): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articles = $articleModel->getAll();

        $content = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Articles</span>
            <a href="/admin/article/create" class="btn btn-primary btn-sm">+ New</a>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>';

        if (empty($articles)) {
            $content .= '<tr><td colspan="6" style="text-align:center;padding:24px;">No articles</td></tr>';
        } else {
            $statusMap = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
            foreach ($articles as $article) {
                $content .= '<tr>
                    <td>' . $article['id'] . '</td>
                    <td>' . htmlspecialchars($article['title']) . '</td>
                    <td>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</td>
                    <td>' . ($statusMap[$article['status']] ?? 'Unknown') . '</td>
                    <td>' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td>
                        <a href="/article/' . $article['id'] . '">View</a>
                        <a href="/admin/article/edit/' . $article['id'] . '">Edit</a>
                        <a href="/admin/article/delete/' . $article['id'] . '" onclick="return confirm(\'Delete this article?\')">Delete</a>
                    </td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Articles', $content);
    }

    public function createArticle(): string
    {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

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
                return $this->renderArticleForm('Title and content are required');
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
        $statusOptions = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
        $content = $article ? ($article['content'] ?? '') : '';
        $title = $article ? ($article['title'] ?? '') : '';
        $excerpt = $article ? ($article['excerpt'] ?? '') : '';

        $html = '<div class="admin-form">
            <span class="page-title">' . ($isEdit ? 'Edit Article' : 'New Article') . '</span>
            ' . ($error ? '<div style="color:#c00;border:2px solid #c00;padding:8px;margin:12px 0;">' . htmlspecialchars($error) . '</div>' : '') . '
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="' . htmlspecialchars($title) . '" required>
                </div>
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <input type="text" id="excerpt" name="excerpt" value="' . htmlspecialchars($excerpt) . '">
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">None</option>';

        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        $html .= '</select></div>
                <div class="form-group">
                    <label for="editor">Content *</label>
                    <div style="margin-bottom:8px;">
                        <span style="font-size:0.75rem;color:var(--gray-500);">Supports HTML: h1, p, a, img, ul, ol, table, pre, code</span>
                        <button type="button" id="previewBtn" class="btn btn-sm" style="margin-left:8px;">Preview</button>
                    </div>
                    <textarea id="editor" name="content" required>' . $content . '</textarea>
                    <div id="preview" style="display:none;border:2px solid var(--gray-200);padding:16px;margin-top:8px;background:var(--white);max-height:400px;overflow-y:auto;"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">';

        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $html .= '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
        }

        $html .= '</select></div>
                    <div style="display:flex;align-items:center;gap:16px;padding-top:8px;">
                        <label><input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> Top</label>
                        <label><input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend'] ? 'checked' : '') . '> Recommend</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Publish') . '</button>
                    <a href="/admin/articles" class="btn">Cancel</a>
                </div>
            </form>
        </div>';

        return $this->renderAdminLayout($isEdit ? 'Edit Article' : 'New Article', $html);
    }

    public function editArticle(string $id): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);

        if (!$article) {
            return $this->renderAdminLayout('Error', '<p>Article not found.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

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
                return $this->renderArticleForm('Title and content are required', [], $article);
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

        $content = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Categories</span>
            <a href="/admin/category/create" class="btn btn-primary btn-sm">+ New</a>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>';

        if (empty($categories)) {
            $content .= '<tr><td colspan="5" style="text-align:center;padding:24px;">No categories</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $content .= '<tr>
                    <td>' . $cat['id'] . '</td>
                    <td>' . htmlspecialchars($cat['name']) . '</td>
                    <td>' . htmlspecialchars($cat['slug']) . '</td>
                    <td>' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td>
                        <a href="/admin/category/edit/' . $cat['id'] . '">Edit</a>
                        <a href="/admin/category/delete/' . $cat['id'] . '" onclick="return confirm(\'Delete this category?\')">Delete</a>
                    </td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Categories', $content);
    }

    public function createCategory(): string
    {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                return $this->renderAdminLayout('New Category', '<div style="color:#c00;margin-bottom:12px;">Name is required</div>' . $this->getCategoryForm());
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

        return $this->renderAdminLayout('New Category', $this->getCategoryForm());
    }

    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '">
                    <span style="font-size:0.75rem;color:var(--gray-500);">Leave blank to auto-generate</span>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Create') . '</button>
                    <a href="/admin/categories" class="btn">Cancel</a>
                </div>
            </form>
        </div>';
    }

    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);

        if (!$category) {
            return $this->renderAdminLayout('Error', '<p>Category not found.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            if (empty($data['name'])) {
                return $this->renderAdminLayout('Edit Category', '<div style="color:#c00;margin-bottom:12px;">Name is required</div>' . $this->getCategoryForm($category));
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

        return $this->renderAdminLayout('Edit Category', $this->getCategoryForm($category));
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

        $content = '<span class="page-title">Users</span>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Registered</th></tr></thead>
            <tbody>';

        if (empty($users)) {
            $content .= '<tr><td colspan="6" style="text-align:center;padding:24px;">No users</td></tr>';
        } else {
            $roleMap = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author', 'subscriber' => 'Subscriber'];
            $statusMap = [0 => 'Disabled', 1 => 'Active', 2 => 'Pending'];
            foreach ($users as $user) {
                $content .= '<tr>
                    <td>' . $user['id'] . '</td>
                    <td>' . htmlspecialchars($user['username']) . '</td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                    <td>' . ($roleMap[$user['role']] ?? $user['role']) . '</td>
                    <td>' . ($statusMap[$user['status']] ?? $user['status']) . '</td>
                    <td>' . date('Y-m-d', strtotime($user['created_at'])) . '</td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Users', $content);
    }

    public function settings(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] !== 'admin') {
            $this->redirect('/admin');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $settingModel = new Setting();
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit' && $key !== 'lig_csrf_token') {
                    $settingModel->set($key, trim($value));
                }
            }
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $perPage = $settingModel->get('per_page') ?? 10;

        $content = '<div class="admin-form">
            <span class="page-title">Settings</span>
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="' . htmlspecialchars($siteName) . '">
                </div>
                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <input type="text" id="site_description" name="site_description" value="' . htmlspecialchars($siteDesc) . '">
                </div>
                <div class="form-group">
                    <label for="per_page">Articles per Page</label>
                    <input type="number" id="per_page" name="per_page" value="' . htmlspecialchars($perPage) . '">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>';

        return $this->renderAdminLayout('Settings', $content);
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
}
EOF

# ======================================================
# 完成
# ======================================================
echo ""
echo -e "${GREEN}=========================================="
echo "  ✅ Lighttp v1.0.6 升级完成！"
echo "=========================================="
echo ""
echo "📁 已更新的文件："
echo "  - app/config/config.php"
echo "  - app/core/Application.php (修复 ini_set 类型错误)"
echo "  - app/controllers/BaseController.php"
echo "  - app/controllers/AuthController.php"
echo "  - app/controllers/AdminController.php"
echo ""
echo -e "${GREEN}🔐 Cookie 安全配置：${NC}"
echo "  - 前缀: lig_"
echo "  - HttpOnly: On"
echo "  - SameSite: Lax"
echo "  - 会话固定防护: On"
echo ""
echo -e "${GREEN}🛡️ CSRF 防护：${NC}"
echo "  - Token 名称: lig_csrf_token"
echo "  - Token 有效期: 3600 秒"
echo "  - 所有 POST 表单已自动添加 CSRF 防护"
echo ""
echo "⚠️  生产环境建议："
echo "  1. 将 config.php 中 cookie_secure 设为 true (需 HTTPS)"
echo "  2. 将 config.php 中 debug 设为 false"
echo ""
