#!/bin/bash
# ======================================================
# Lighttp v1.0.5 - bcrypt 成本因子升级脚本
# 功能：将 bcrypt 成本因子从 10 提升到 12
# 运行：bash update-bcrypt-cost.sh
# ======================================================

set -e

echo "=========================================="
echo "  Lighttp v1.0.5 - bcrypt 成本升级"
echo "  功能：成本因子 10 -> 12"
echo "=========================================="
echo ""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ ! -d "app/models" ] || [ ! -d "app/config" ]; then
    echo -e "${RED}错误：请在 Lighttp 项目根目录下运行此脚本${NC}"
    exit 1
fi

# ======================================================
# 1. 备份原文件
# ======================================================
echo -e "${YELLOW}[1/5] 备份原文件...${NC}"
BACKUP_DIR="app/backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp app/config/config.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/models/User.php "$BACKUP_DIR/" 2>/dev/null || true
cp app/controllers/AuthController.php "$BACKUP_DIR/" 2>/dev/null || true
echo -e "${GREEN}备份已保存至: $BACKUP_DIR${NC}"

# ======================================================
# 2. 更新 app/config/config.php
# ======================================================
echo -e "${YELLOW}[2/5] 更新 app/config/config.php...${NC}"

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
    ],
];
EOF

# ======================================================
# 3. 更新 app/models/User.php
# ======================================================
echo -e "${YELLOW}[3/5] 更新 app/models/User.php...${NC}"

cat > app/models/User.php << 'EOF'
<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class User
{
    private string $table = 'users';

    private function getBcryptCost(): int
    {
        $config = Application::getInstance()->getConfig();
        return $config['security']['bcrypt_cost'] ?? 12;
    }

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
        $cost = $this->getBcryptCost();
        $options = ['cost' => $cost];
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);
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
                $cost = $this->getBcryptCost();
                $options = ['cost' => $cost];
                $value = password_hash($value, PASSWORD_BCRYPT, $options);
            }
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        return $db->update("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }

    public function needsRehash(string $hashed): bool
    {
        $cost = $this->getBcryptCost();
        return password_needs_rehash($hashed, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public function rehashPassword(int $id, string $password): bool
    {
        return $this->update($id, ['password' => $password]);
    }
}
EOF

# ======================================================
# 4. 更新 app/controllers/AuthController.php
# ======================================================
echo -e "${YELLOW}[4/5] 更新 app/controllers/AuthController.php...${NC}"

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

            // 检查是否需要重哈希（升级到更高成本因子）
            if ($userModel->needsRehash($user['password'])) {
                $userModel->rehashPassword($user['id'], $password);
                // 重新获取用户数据
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
# 5. 创建密码迁移检查工具
# ======================================================
echo -e "${YELLOW}[5/5] 创建工具脚本 migrate-passwords.php...${NC}"

cat > migrate-passwords.php << 'EOF'
<?php
/**
 * 密码迁移检查工具
 * 检查现有用户密码是否需要升级到新的 bcrypt 成本因子
 * 运行方式：php migrate-passwords.php
 */
declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

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
use App\models\User;

echo "========================================\n";
echo "  Lighttp - 密码迁移检查工具\n";
echo "  检查用户密码是否需要升级\n";
echo "========================================\n\n";

$app = Application::getInstance();
$db = $app->getDb();

if (!$db) {
    die("数据库连接失败！\n");
}

$userModel = new User();
$users = $db->query("SELECT id, username, password FROM users");

if (empty($users)) {
    echo "没有用户需要检查。\n";
    exit(0);
}

$needUpgrade = 0;
$alreadyUpgraded = 0;

echo "用户密码状态：\n";
echo "----------------------------------------\n";

foreach ($users as $user) {
    if ($userModel->needsRehash($user['password'])) {
        echo "  [需要升级] 用户 '{$user['username']}' (ID: {$user['id']})\n";
        echo "              → 下次登录时自动升级\n";
        $needUpgrade++;
    } else {
        echo "  [已是最新] 用户 '{$user['username']}' (ID: {$user['id']})\n";
        $alreadyUpgraded++;
    }
}

echo "\n----------------------------------------\n";
echo "统计：\n";
echo "  - 已升级到成本因子12: $alreadyUpgraded\n";
echo "  - 需要登录时自动升级: $needUpgrade\n";
echo "\n";
echo "✅ 检查完成！\n";
echo "📌 需要升级的用户在下次登录时会自动完成升级。\n";
EOF

# ======================================================
# 完成
# ======================================================
echo ""
echo -e "${GREEN}=========================================="
echo "  ✅ Lighttp v1.0.5 升级完成！"
echo "==========================================${NC}"
echo ""
echo "📁 已更新的文件："
echo "  - app/config/config.php (新增 security.bcrypt_cost = 12)"
echo "  - app/models/User.php (支持成本因子配置 + 重哈希检查)"
echo "  - app/controllers/AuthController.php (登录时自动重哈希)"
echo ""
echo "📁 已创建的工具脚本："
echo "  - migrate-passwords.php (查看迁移状态)"
echo ""
echo -e "${GREEN}🔐 bcrypt 成本因子已升级到 12${NC}"
echo ""
echo "📌 说明："
echo "  - 新注册用户将使用成本因子 12"
echo "  - 现有用户登录时会自动升级密码哈希"
echo "  - 运行 php migrate-passwords.php 查看迁移状态"
echo ""
