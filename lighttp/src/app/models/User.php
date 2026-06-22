<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class User
{
    private string $table = 'users';

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
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
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
                $value = password_hash($value, PASSWORD_DEFAULT);
            }
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        return $db->update("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }
}
