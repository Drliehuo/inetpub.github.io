<?php
declare(strict_types=1);

namespace App\models;

use App\core\Application;

class Article
{
    private string $table = 'articles';
    
public function search(string $keyword, int $page = 1, int $perPage = 10): array
{
    $db = Application::getInstance()->getDb();
    if (!$db) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0, 'keyword' => $keyword];
    }
    $offset = ($page - 1) * $perPage;
    $searchTerm = '%' . $keyword . '%';
    $where = "WHERE a.status = 1 AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $countSql = "SELECT COUNT(*) as total FROM {$this->table} a {$where}";
    $totalResult = $db->queryOne($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    $dataSql = "SELECT a.*, c.name as category_name, u.username as author_name, COALESCE(u.nickname, u.username) as author_display FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id {$where} ORDER BY a.is_top DESC, a.published_at DESC LIMIT ? OFFSET ?";
    $dataParams = $params;
    $dataParams[] = $perPage;
    $dataParams[] = $offset;
    $data = $db->query($dataSql, $dataParams);
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage),
        'keyword' => $keyword
    ];
}

    public function getAll(?int $status = null): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        $sql = "SELECT a.*, c.name as category_name, u.username as author_name, COALESCE(u.nickname, u.username) as author_display FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id WHERE 1=1";
        $params = [];
        if ($status !== null) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        } else {
            $sql .= " AND a.status = 1";
        }
        $sql .= " ORDER BY a.is_top DESC, a.published_at DESC";
        return $db->query($sql, $params);
    }

    public function getPaginated(?int $status = null, int $page = 1, int $perPage = 10, ?int $authorId = null): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0];
        }
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = "WHERE a.status " . ($status !== null ? "= ?" : "= 1");
        if ($status !== null) {
            $params[] = $status;
        }
        if ($authorId !== null) {
            $where .= " AND a.author_id = ?";
            $params[] = $authorId;
        }
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} a {$where}";
        $totalResult = $db->queryOne($countSql, $params);
        $total = $totalResult['total'] ?? 0;
        $dataSql = "SELECT a.*, c.name as category_name, u.username as author_name, COALESCE(u.nickname, u.username) as author_display FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id {$where} ORDER BY a.is_top DESC, a.published_at DESC LIMIT ? OFFSET ?";
        $dataParams = $params;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;
        $data = $db->query($dataSql, $dataParams);
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    public function getByCategoryPaginated(int $categoryId, int $page = 1, int $perPage = 10): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage, 'totalPages' => 0];
        }
        $offset = ($page - 1) * $perPage;
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} WHERE category_id = ? AND status = 1";
        $totalResult = $db->queryOne($countSql, [$categoryId]);
        $total = $totalResult['total'] ?? 0;
        $dataSql = "SELECT * FROM {$this->table} WHERE category_id = ? AND status = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $data = $db->query($dataSql, [$categoryId, $perPage, $offset]);
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    public function getLatest(int $limit = 10): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query(
            "SELECT a.*, c.name as category_name, u.username as author_name, COALESCE(u.nickname, u.username) as author_display FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id WHERE a.status = 1 ORDER BY a.is_top DESC, a.published_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function find(int $id): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        $article = $db->queryOne(
            "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name, COALESCE(u.nickname, u.username) as author_display FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id WHERE a.id = ?",
            [$id]
        );
        if ($article) {
            $db->update("UPDATE {$this->table} SET views = views + 1 WHERE id = ?", [$id]);
        }
        return $article;
    }

    public function findBySlug(string $slug): ?array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return null;
        }
        return $db->queryOne(
            "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name FROM {$this->table} a LEFT JOIN categories c ON a.category_id = c.id LEFT JOIN users u ON a.author_id = u.id WHERE a.slug = ? AND a.status = 1",
            [$slug]
        );
    }

    public function create(array $data): int
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return 0;
        }
        $slug = $data['slug'] ?? $this->generateSlug($data['title']);
        return $db->execute(
            "INSERT INTO {$this->table} (title, slug, content, excerpt, category_id, author_id, status, cover_image, is_top, is_recommend, meta_title, meta_description, meta_keywords, published_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $data['title'],
                $slug,
                $data['content'],
                $data['excerpt'] ?? '',
                $data['category_id'] ?? null,
                $data['author_id'] ?? null,
                $data['status'] ?? 1,
                $data['cover_image'] ?? null,
                $data['is_top'] ?? 0,
                $data['is_recommend'] ?? 0,
                $data['meta_title'] ?? null,
                $data['meta_description'] ?? null,
                $data['meta_keywords'] ?? null
            ]
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
            if ($key !== 'id' && $key !== 'created_at') {
                $sets[] = "$key = ?";
                $params[] = $value;
            }
        }
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        return $db->update($sql, $params) > 0;
    }

    public function delete(int $id): bool
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return false;
        }
        return $db->delete("DELETE FROM {$this->table} WHERE id = ?", [$id]) > 0;
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $title), '-'));
        $db = Application::getInstance()->getDb();
        if ($db) {
            $existing = $db->queryOne("SELECT id FROM {$this->table} WHERE slug = ?", [$slug]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
        }
        return $slug;
    }

    public function getByCategory(int $categoryId, int $limit = 10): array
    {
        $db = Application::getInstance()->getDb();
        if (!$db) {
            return [];
        }
        return $db->query(
            "SELECT * FROM {$this->table} WHERE category_id = ? AND status = 1 ORDER BY created_at DESC LIMIT ?",
            [$categoryId, $limit]
        );
    }
}