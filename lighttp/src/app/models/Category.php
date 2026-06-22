<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Category
{
    private string $table = 'categories';

    public function findAll(bool $showAll = false): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        $sql = "SELECT * FROM {$this->table}";
        if (!$showAll) {
            $sql .= " WHERE is_show = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $db->query($sql);
    }

    public function find(int $id): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE slug = ? AND is_show = 1", [$slug]);
    }

    public function create(string $name, string $slug, string $description = '', int $parentId = 0): int
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return 0;
        }
        return $db->execute(
            "INSERT INTO {$this->table} (name, slug, description, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$name, $slug, $description, $parentId]
        );
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
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        return $db->update("UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE id = ?", $params) > 0;
    }

    public function delete(int $id): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        return $db->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }
}
