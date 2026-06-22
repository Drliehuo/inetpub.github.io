<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Setting
{
    private string $table = 'settings';

    public function get(string $key): ?string
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        $result = $db->queryOne("SELECT value FROM {$this->table} WHERE `key` = ?", [$key]);
        return $result ? $result['value'] : null;
    }

    public function set(string $key, string $value): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        $existing = $db->queryOne("SELECT id FROM {$this->table} WHERE `key` = ?", [$key]);
        if ($existing) {
            return $db->update("UPDATE {$this->table} SET `value` = ? WHERE `key` = ?", [$value, $key]) > 0;
        }
        return $db->execute("INSERT INTO {$this->table} (`key`, `value`, created_at) VALUES (?, ?, NOW())", [$key, $value]) > 0;
    }
}
