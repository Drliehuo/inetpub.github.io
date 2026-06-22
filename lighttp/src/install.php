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
