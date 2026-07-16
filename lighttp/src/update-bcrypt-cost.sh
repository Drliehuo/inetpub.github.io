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
# 1. 更新 app/config/config.php
# ======================================================
echo -e "${YELLOW}[1/4] 更新 app/config/config.php...${NC}"

# 检查是否已有 security 配置
if grep -q "'security'" app/config/config.php; then
    echo -e "${GREEN}已有 security 配置，跳过添加${NC}"
else
    # 在 app 配置后面添加 security 配置
    sed -i "/'app' => \[/,/],/ {
        /],/ a\\
    'security' => [\\
        'bcrypt_cost' => 12,\\
    ],
    }" app/config/config.php
fi

# ======================================================
# 2. 更新 app/models/User.php
# ======================================================
echo -e "${YELLOW}[2/4] 更新 app/models/User.php...${NC}"

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
        $cost = $this->getBcryptCost();
        $options = ['cost' => $cost];
        $newHash = password_hash($password, PASSWORD_BCRYPT, $options);
        return $this->update($id, ['password' => $password]);
    }
}
EOF

# ======================================================
# 3. 更新 app/controllers/AuthController.php（登录时自动重哈希）
# ======================================================
echo -e "${YELLOW}[3/4] 更新 app/controllers/AuthController.php...${NC}"

# 提取登录验证部分，添加自动重哈希逻辑
sed -i '/if (!$user || !$userModel->verifyPassword/{
    s/if (!$user || !$userModel->verifyPassword.*$/if (!$user || !$userModel->verifyPassword($password, $user['\''password'\''])) {/
    a\
                return $this->renderLogin('\''Invalid username or password'\'');
            }\
\
            // 检查是否需要重哈希（升级到更高成本因子）\
            if ($userModel->needsRehash($user['\''password'\''])) {\
                $userModel->rehashPassword($user['\''id'\''], $password);\
                // 重新获取用户数据\
                $user = $userModel->find($user['\''id'\'']);\
            }
}' app/controllers/AuthController.php

# ======================================================
# 4. 创建密码重哈希工具脚本（可选，用于批量升级现有用户密码）
# ======================================================
echo -e "${YELLOW}[4/4] 创建工具脚本 migrate-passwords.php...${NC}"

cat > migrate-passwords.php << 'EOF'
<?php
/**
 * 密码迁移脚本 - 将现有用户密码升级到新的 bcrypt 成本因子
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
echo "  Lighttp - 密码迁移工具\n";
echo "  将用户密码升级到新的 bcrypt 成本因子\n";
echo "========================================\n\n";

$app = Application::getInstance();
$db = $app->getDb();

if (!$db) {
    die("数据库连接失败！\n");
}

$userModel = new User();
$users = $db->query("SELECT id, username, password FROM users");

if (empty($users)) {
    echo "没有用户需要迁移。\n";
    exit(0);
}

$migrated = 0;
$skipped = 0;

foreach ($users as $user) {
    if ($userModel->needsRehash($user['password'])) {
        // 注意：这里无法获取原始密码，需要用户在登录时自动重哈希
        echo "  - 用户 '{$user['username']}' (ID: {$user['id']}) 需要迁移\n";
        echo "    提示：用户在下次登录时将自动升级密码哈希\n";
        $skipped++;
    } else {
        echo "  - 用户 '{$user['username']}' (ID: {$user['id']}) 已是最新\n";
        $migrated++;
    }
}

echo "\n----------------------------------------\n";
echo "统计：\n";
echo "  - 已是最新: $migrated\n";
echo "  - 需要登录时迁移: $skipped\n";
echo "\n";
echo "✅ 密码迁移准备完成！\n";
echo "📌 用户下次登录时会自动升级密码哈希。\n";
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