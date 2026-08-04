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
            if (!$this->verifyCsrf()) {
                return $this->renderLogin('CSRF 令牌验证失败');
            }
            $login = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (empty($login) || empty($password)) {
                return $this->renderLogin('请填写完整信息');
            }
            $userModel = new User();
            $user = $userModel->findByUsername($login);
            if (!$user) {
                $user = $userModel->findByEmail($login);
            }
            if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
                return $this->renderLogin('用户名或密码错误');
            }
            if ($userModel->needsRehash($user['password'])) {
                $userModel->rehashPassword($user['id'], $password);
                $user = $userModel->find($user['id']);
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
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>登录</h1>
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo $this->csrfField(); ?>
            <div class="form-group">
                <label for="username">用户名或邮箱</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit">登 录</button>
        </form>
        <div class="auth-links">
            <a href="/register">还没有账号？立即注册</a> | <a href="/">返回首页</a>
        </div>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }
    public function logout(): void
    {
        $config = Application::getInstance()->getConfig();
        $tokenName = $config['security']['csrf_token_name'] ?? 'lig_csrf_token';
        unset($_SESSION[$tokenName]);
        unset($_SESSION[$tokenName . '_time']);
        Application::getInstance()->setCurrentUser(null);
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
            if (!$this->verifyCsrf()) {
                return $this->renderRegister('CSRF 令牌验证失败');
            }
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            if (strlen($username) < 3) {
                return $this->renderRegister('用户名至少 3 个字符');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderRegister('邮箱格式不正确');
            }
            if (strlen($password) < 6) {
                return $this->renderRegister('密码至少 6 个字符');
            }
            if ($password !== $passwordConfirm) {
                return $this->renderRegister('两次密码输入不一致');
            }
            $userModel = new User();
            if ($userModel->findByUsername($username)) {
                return $this->renderRegister('用户名已被占用');
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
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>注册</h1>
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <?php echo $this->csrfField(); ?>
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">密码（至少 6 位）</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">确认密码</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="btn-submit">注 册</button>
        </form>
        <div class="auth-links">
            <a href="/login">已有账号？去登录</a> | <a href="/">返回首页</a>
        </div>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }
}