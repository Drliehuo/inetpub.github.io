<?php
declare(strict_types=1);
namespace App\models;
use App\core\Application;
class Article
{
    private string $table = 'articles';
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
    /**
     * 从文章内容中自动提取关键词
     * 优先提取中文词组，至少2个中文词，最多6个关键词
     */
    public function extractKeywords(string $content, int $minChinese = 2, int $maxTotal = 6): array
    {
        // 1. 去除 HTML 标签
        $text = strip_tags($content);
        // 2. 按句子分割（中文句号、问号、感叹号、分号）
        $sentences = preg_split('/[。！？；\n\r]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        // 3. 从每个句子中提取关键词
        $chineseWords = [];
        $englishWords = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }
            // 提取中文词组（连续的中文字符，2-6个字）
            preg_match_all('/[\x{4e00}-\x{9fa5}]{2,6}/u', $sentence, $chineseMatches);
            if (!empty($chineseMatches[0])) {
                foreach ($chineseMatches[0] as $word) {
                    $word = trim($word);
                    if (mb_strlen($word) >= 2 && !in_array($word, $chineseWords)) {
                        $chineseWords[] = $word;
                    }
                }
            }
            // 提取英文单词（长度 >= 3）
            preg_match_all('/[a-zA-Z]{3,}/', $sentence, $englishMatches);
            if (!empty($englishMatches[0])) {
                foreach ($englishMatches[0] as $word) {
                    $word = strtolower(trim($word));
                    if (strlen($word) >= 3 && !in_array($word, $englishWords)) {
                        $englishWords[] = $word;
                    }
                }
            }
        }
        // 4. 合并关键词：先中文，后英文
        $keywords = $chineseWords;
        // 如果中文词少于 minChinese（2个），用英文词补齐
        if (count($keywords) < $minChinese) {
            $needed = $minChinese - count($keywords);
            $englishSupplement = array_slice($englishWords, 0, $needed);
            $keywords = array_merge($keywords, $englishSupplement);
        }
        // 5. 如果总数仍少于 minChinese，从所有词中补充
        if (count($keywords) < $minChinese) {
            preg_match_all('/[\x{4e00}-\x{9fa5}a-zA-Z]{2,}/u', $text, $allMatches);
            if (!empty($allMatches[0])) {
                foreach ($allMatches[0] as $word) {
                    if (count($keywords) >= $maxTotal) {
                        break;
                    }
                    $word = trim($word);
                    if (mb_strlen($word) >= 2 && !in_array($word, $keywords)) {
                        $keywords[] = $word;
                    }
                }
            }
        }
        // 6. 限制总数不超过 maxTotal（6个）
        $keywords = array_slice($keywords, 0, $maxTotal);
        // 7. 如果还是空，使用默认关键词
        if (empty($keywords)) {
            return ['内容', '文章'];
        }
        return $keywords;
    }
    /**
     * 获取文章的关键词（优先使用用户设置的，否则自动提取）
     * 返回格式：用英文逗号分隔的字符串
     */
    public function getMetaKeywords(array $article): string
    {
        // 如果用户已设置关键词，直接返回
        if (!empty($article['meta_keywords'])) {
            return $article['meta_keywords'];
        }
        // 否则从内容中自动提取
        $content = $article['content'] ?? '';
        if (empty($content)) {
            return '内容, 文章, 阅读';
        }
        $keywords = $this->extractKeywords($content, 2, 6);
        return implode(', ', $keywords);
    }
}