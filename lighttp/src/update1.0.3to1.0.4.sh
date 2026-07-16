#!/bin/bash
# ======================================================
# Lighttp v1.0.4 - 升级脚本
# 功能：创建独立 Header/Footer，统一链接管理
# 运行：bash update-v1.0.4.sh
# ======================================================

set -e

echo "=========================================="
echo "  Lighttp v1.0.4 - 升级脚本"
echo "  功能：独立 Header/Footer + 统一链接"
echo "=========================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 检查是否在项目根目录
if [ ! -d "public" ] || [ ! -d "app/controllers" ]; then
    echo -e "${RED}错误：请在 Lighttp 项目根目录下运行此脚本${NC}"
    echo "当前目录：$(pwd)"
    exit 1
fi

# ======================================================
# 1. 创建 views/partials 目录
# ======================================================
echo -e "${YELLOW}[1/5] 创建 views/partials 目录...${NC}"
mkdir -p app/views/partials

# ======================================================
# 2. 创建独立 Footer
# ======================================================
echo -e "${YELLOW}[2/5] 创建 app/views/partials/footer.php...${NC}"
cat > app/views/partials/footer.php << 'EOF'
<!-- Lighttp v1.0.4 - Footer Partial -->
<footer class="site-footer">
    <div class="container">
        <span class="footer-brand">Lighttp</span>
        <span class="footer-copy">&copy; <?php echo date('Y'); ?> All rights reserved.</span>
        <span class="footer-dev">Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></span>
    </div>
</footer>
EOF

# ======================================================
# 3. 创建独立 Header
# ======================================================
echo -e "${YELLOW}[3/5] 创建 app/views/partials/header.php...${NC}"
cat > app/views/partials/header.php << 'EOF'
<!-- Lighttp v1.0.4 - Header Partial -->
<header class="site-header">
    <div class="container">
        <a href="/" class="logo">Lighttp</a>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <ul class="nav-links" id="navLinks">
            <li><a href="/">Home</a></li>
            <li><a href="/admin">Admin</a></li>
        </ul>
    </div>
</header>
EOF

# ======================================================
# 4. 创建独立 JS 头（含菜单逻辑）
# ======================================================
echo -e "${YELLOW}[4/5] 更新 public/js/app.js...${NC}"
cat > public/js/app.js << 'EOF'
/* Lighttp v1.0.4 - Global JavaScript */
"use strict";

document.addEventListener('DOMContentLoaded', function() {

    // Mobile menu toggle
    var toggle = document.getElementById('menuToggle');
    var nav = document.getElementById('navLinks');

    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            toggle.classList.toggle('active');
            nav.classList.toggle('open');
        });

        nav.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                toggle.classList.remove('active');
                nav.classList.remove('open');
            });
        });
    }

    // Close mobile menu on outside click
    document.addEventListener('click', function(e) {
        if (nav && nav.classList.contains('open')) {
            var isClickInside = nav.contains(e.target) || toggle.contains(e.target);
            if (!isClickInside) {
                toggle.classList.remove('active');
                nav.classList.remove('open');
            }
        }
    });

    // Article content preview (admin)
    var previewBtn = document.getElementById('previewBtn');
    var editor = document.getElementById('editor');
    var preview = document.getElementById('preview');

    if (previewBtn && editor && preview) {
        previewBtn.addEventListener('click', function() {
            preview.innerHTML = editor.value;
            preview.style.display = 'block';
        });
    }

    // Delete confirmation
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

});
EOF

# ======================================================
# 5. 备份原控制器
# ======================================================
echo -e "${YELLOW}[5/5] 备份并更新控制器...${NC}"
BACKUP_DIR="app/controllers/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp app/controllers/*.php "$BACKUP_DIR/" 2>/dev/null || true
echo -e "${GREEN}备份已保存至: $BACKUP_DIR${NC}"

# ======================================================
# 6. 更新 HomeController.php
# ======================================================
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
                'site_name' => 'My CMS',
                'site_description' => 'A lightweight CMS built with PHP + MySQL + Redis'
            ];
            if ($cache) {
                $cache->set($cacheKey, $data, 300);
            }
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['site_name']); ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <div class="admin-bar">
            <a href="/admin/articles">Manage Articles</a>
            <a href="/admin/article/create">New Article</a>
            <a href="/admin/cache/clear">Clear Cache</a>
            <span class="status-badge success">MySQL</span>
            <span class="status-badge success">Redis</span>
        </div>

        <h2>Latest Articles</h2>

        <?php if (empty($data['articles'])): ?>
            <p>No articles yet. <a href="/admin/article/create">Create your first article</a></p>
        <?php else: ?>
            <?php foreach ($data['articles'] as $article):
                $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                $excerpt = mb_substr(strip_tags($excerpt), 0, 150) . '...';
            ?>
            <div class="article-card">
                <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h2>
                <div class="meta">
                    <span><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span>
                    <span><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></span>
                    <span><?php echo $article['views'] ?? 0; ?> views</span>
                    <?php if ($article['is_top']): ?><span>[Top]</span><?php endif; ?>
                    <?php if ($article['is_recommend']): ?><span>[Recommend]</span><?php endif; ?>
                </div>
                <p class="excerpt"><?php echo $excerpt; ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 7. 更新 ArticleController.php
# ======================================================
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
            ob_start();
            ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head>
<body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Article not found.</p><a href="/">Back to home</a></div></body>
</html>
<?php
            return ob_get_clean();
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['meta_title'] ?? $article['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? ''); ?>">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;max-width:800px;">
        <article>
            <h1><?php echo htmlspecialchars($article['title']); ?></h1>
            <div class="meta" style="color:var(--gray-500);font-size:0.875rem;margin-bottom:24px;border-bottom:1px solid var(--gray-200);padding-bottom:16px;">
                <span><?php echo date('Y-m-d H:i', strtotime($article['published_at'] ?? $article['created_at'])); ?></span>
                <span><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></span>
                <span><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></span>
                <span><?php echo $article['views'] ?? 0; ?> views</span>
            </div>
            <div class="article-content"><?php echo $article['content'] ?? ''; ?></div>
            <div style="margin-top:32px;padding-top:16px;border-top:2px solid var(--gray-200);">
                <a href="/" class="btn btn-sm">Back to home</a>
                <a href="/admin/article/edit/<?php echo $article['id']; ?>" class="btn btn-sm">Edit</a>
                <a href="/admin/articles" class="btn btn-sm">Manage</a>
            </div>
        </article>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 8. 更新 CategoryController.php
# ======================================================
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
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Category not found.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        $articleModel = new Article();
        $articles = $articleModel->getByCategory($category['id']);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Category</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <h1><?php echo htmlspecialchars($category['name']); ?></h1>
        <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>

        <?php if (empty($articles)): ?>
            <p>No articles in this category.</p>
        <?php else: ?>
            <div style="margin-top:24px;">
            <?php foreach ($articles as $article): ?>
                <div class="article-card">
                    <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h2>
                    <div class="meta"><span><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span><span><?php echo $article['views'] ?? 0; ?> views</span></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="/" class="btn btn-sm" style="margin-top:16px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 9. 更新 TagController.php
# ======================================================
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
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Tag not found.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tag['name']); ?> - Tag</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <h1>Tag: <?php echo htmlspecialchars($tag['name']); ?></h1>
        <p><?php echo $tag['count'] ?? 0; ?> articles with this tag.</p>
        <a href="/" class="btn btn-sm" style="margin-top:16px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 10. 更新 PageController.php
# ======================================================
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
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>Error</h1><p>Database connection failed.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND is_show = 1", [$slug]);

        if (!$page) {
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Page not found.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['meta_title'] ?? $page['title']); ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;max-width:800px;">
        <h1><?php echo htmlspecialchars($page['title']); ?></h1>
        <div class="article-content"><?php echo $page['content'] ?? ''; ?></div>
        <a href="/" class="btn btn-sm" style="margin-top:24px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
EOF

# ======================================================
# 11. 更新 AuthController.php
# ======================================================
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
                return $this->renderLogin('Please fill in all fields');
            }

            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
                return $this->renderLogin('Invalid username or password');
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
# 12. 更新 AdminController.php
# ======================================================
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
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $perPage = $settingModel->get('per_page') ?? 10;

        $content = '<div class="admin-form">
            <span class="page-title">Settings</span>
            <form method="POST">
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
echo "  ✅ Lighttp v1.0.4 升级完成！"
echo "==========================================${NC}"
echo ""
echo "📁 已创建的文件："
echo "  - app/views/partials/header.php"
echo "  - app/views/partials/footer.php"
echo ""
echo "📁 已更新的控制器："
echo "  - app/controllers/HomeController.php"
echo "  - app/controllers/ArticleController.php"
echo "  - app/controllers/CategoryController.php"
echo "  - app/controllers/TagController.php"
echo "  - app/controllers/PageController.php"
echo "  - app/controllers/AuthController.php"
echo "  - app/controllers/AdminController.php"
echo ""
echo "📁 备份位置：$BACKUP_DIR"
echo ""
echo -e "${GREEN}🎉 现在所有页面的 Header 和 Footer 已统一管理！${NC}"
echo -e "${GREEN}🔗 页脚链接：https://www.inetpub.cn/lighttp${NC}"
echo ""