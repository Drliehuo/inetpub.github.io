<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Tag
{
    private string $table = 'tags';

    public function findAll(): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query("SELECT * FROM {$this->table} ORDER BY count DESC");
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne("SELECT * FROM {$this->table} WHERE slug = ?", [$slug]);
    }
}
