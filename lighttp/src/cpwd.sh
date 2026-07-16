cat > public/cpwd.php << 'EOF'
<?php
/**
 * 临时密码修改工具 - 支持 Web + CLI 双模式
 * 访问方式：http://你的域名/cpwd.php
 * 使用后请立即删除！
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
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

$app = Application::getInstance();
$db = $app->getDb();

if (!$db) {
    die("数据库连接失败！");
}

// ========== 处理 POST 请求（Web 模式） ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    $error = null;
    $success = null;

    if (empty($password) || empty($confirm)) {
        $error = "请输入密码";
    } elseif (strlen($password) < 6) {
        $error = "密码至少6位";
    } elseif ($password !== $confirm) {
        $error = "两次输入不一致";
    } else {
        $user = $db->queryOne("SELECT id, username FROM users WHERE username = ?", [$username]);
        if (!$user) {
            $error = "用户 '{$username}' 不存在";
        } else {
            $cost = 12;
            $options = ['cost' => $cost];
            $hashed = password_hash($password, PASSWORD_BCRYPT, $options);
            $db->update("UPDATE users SET password = ? WHERE id = ?", [$hashed, $user['id']]);
            $success = "✅ 密码修改成功！用户：{$user['username']}";
        }
    }
}

// ========== 显示表单 ==========
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码 - Lighttp</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .box { background: #fff; border: 3px solid #000; padding: 40px; max-width: 420px; width: 100%; }
        h1 { font-size: 1.3rem; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 20px; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 20px; color: #856404; }
        .warning strong { color: #000; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px 12px; border: 2px solid #ccc; font-size: 0.95rem; }
        input:focus { outline: none; border-color: #000; }
        .btn { width: 100%; padding: 12px; background: #000; color: #fff; border: none; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #333; }
        .msg-success { background: #d4edda; border: 2px solid #28a745; padding: 10px 14px; margin-bottom: 16px; color: #155724; }
        .msg-error { background: #f8d7da; border: 2px solid #dc3545; padding: 10px 14px; margin-bottom: 16px; color: #721c24; }
        .footer { margin-top: 16px; font-size: 0.8rem; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 12px; }
        .footer a { color: #000; }
        .delete-note { background: #f8d7da; border: 2px solid #dc3545; padding: 10px 14px; font-size: 0.85rem; margin-top: 16px; color: #721c24; text-align: center; }
        .delete-note code { background: #fff; padding: 2px 8px; border: 1px solid #dc3545; }
        .info { background: #e7f3ff; border: 2px solid #007bff; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 16px; color: #004085; }
    </style>
</head>
<body>
    <div class="box">
        <h1>修改管理员密码</h1>

        <div class="warning">
            <strong>安全警告</strong><br>
            此页面用于修改管理员密码，使用后请立即删除此文件！
        </div>

        <?php if (isset($success)): ?>
            <div class="msg-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="msg-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="change">

            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" value="admin">
            </div>

            <div class="form-group">
                <label for="password">新密码（至少6位）</label>
                <input type="password" id="password" name="password" placeholder="请输入新密码" required>
            </div>

            <div class="form-group">
                <label for="confirm">确认密码</label>
                <input type="password" id="confirm" name="confirm" placeholder="请再次输入新密码" required>
            </div>

            <button type="submit" class="btn">修改密码</button>
        </form>

        <div class="info">
            密码使用 <strong>bcrypt</strong> 加密，成本因子 <strong>12</strong>
        </div>

        <div class="delete-note">
            使用完毕后，请立即删除此文件：
        </div>

        <div class="footer">
            <a href="/admin">返回后台</a> &middot; <a href="/">返回首页</a>
        </div>
    </div>
</body>
</html>
<?php
// ========== CLI 模式支持 ==========
if (php_sapi_name() === 'cli') {
    echo "\n========================================\n";
    echo "  Lighttp - 密码修改工具 (CLI模式)\n";
    echo "========================================\n\n";

    echo "请输入用户名（默认 admin）: ";
    $username = trim(fgets(STDIN));
    $username = empty($username) ? 'admin' : $username;

    $user = $db->queryOne("SELECT id, username FROM users WHERE username = ?", [$username]);
    if (!$user) {
        echo "用户 '{$username}' 不存在！\n";
        exit(1);
    }

    echo "用户：{$user['username']} (ID: {$user['id']})\n";

    echo "请输入新密码（至少6位）: ";
    $newPassword = trim(fgets(STDIN));
    if (strlen($newPassword) < 6) {
        echo "密码至少6位！\n";
        exit(1);
    }

    echo "请再次输入新密码: ";
    $confirm = trim(fgets(STDIN));
    if ($newPassword !== $confirm) {
        echo "两次输入不一致！\n";
        exit(1);
    }

    $cost = 12;
    $options = ['cost' => $cost];
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, $options);
    $db->update("UPDATE users SET password = ? WHERE id = ?", [$hashed, $user['id']]);

    echo "\n";
    echo "========================================\n";
    echo "  ✅ 密码修改成功！\n";
    echo "========================================\n";
    echo "  用户：{$user['username']}\n";
    echo "  新密码：已设置\n";
    echo "  成本因子：12\n";
    echo "\n";
    echo "⚠️  请立即删除此文件：rm -f " . __FILE__ . "\n";
    exit(0);
}
EOF
