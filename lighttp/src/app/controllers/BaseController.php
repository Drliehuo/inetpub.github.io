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
