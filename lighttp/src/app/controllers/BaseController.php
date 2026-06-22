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

    protected function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
