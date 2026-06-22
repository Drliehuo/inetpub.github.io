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
