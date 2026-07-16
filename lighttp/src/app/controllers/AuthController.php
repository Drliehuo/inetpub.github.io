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
