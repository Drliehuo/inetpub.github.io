<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;
use App\models\Category;
use App\models\User;
use App\models\Setting;
use App\core\Application;

class AdminController extends BaseController
{
    private function checkAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
        }
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['admin', 'editor'])) {
            $this->redirect('/');
        }
    }

    private function renderAdminLayout(string $title, string $content): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Admin</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="brand"><a href="/admin">Lighttp Admin</a></div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($this->getCurrentUser()['username'] ?? ''); ?></span>
                <a href="/">Home</a>
                <a href="/logout">Logout</a>
            </div>
        </div>
    </header>
    <main class="admin-content">
        <div class="container">
            <?php echo $content; ?>
        </div>
    </main>
    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }

    public function dashboard(): string
    {
        $this->checkAuth();
        $db = $this->getDb();
        $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
        $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
        $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
        $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");

        $content = '<div class="page-title">Dashboard</div>
        <div class="stats-grid">
            <div class="stat-card"><span class="number">' . ($articleCount['count'] ?? 0) . '</span><span class="label">Articles</span></div>
            <div class="stat-card"><span class="number">' . ($categoryCount['count'] ?? 0) . '</span><span class="label">Categories</span></div>
            <div class="stat-card"><span class="number">' . ($commentCount['count'] ?? 0) . '</span><span class="label">Comments</span></div>
            <div class="stat-card"><span class="number">' . ($userCount['count'] ?? 0) . '</span><span class="label">Users</span></div>
        </div>
        <div class="admin-grid">
            <a href="/admin/articles" class="admin-card"><span class="icon">[A]</span><span class="name">Articles</span></a>
            <a href="/admin/categories" class="admin-card"><span class="icon">[C]</span><span class="name">Categories</span></a>
            <a href="/admin/users" class="admin-card"><span class="icon">[U]</span><span class="name">Users</span></a>
            <a href="/admin/settings" class="admin-card"><span class="icon">[S]</span><span class="name">Settings</span></a>
            <a href="/admin/cache/clear" class="admin-card" onclick="return confirm(\'Clear all cache?\')"><span class="icon">[X]</span><span class="name">Clear Cache</span></a>
        </div>';

        return $this->renderAdminLayout('Dashboard', $content);
    }

    public function articles(): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articles = $articleModel->getAll();

        $content = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Articles</span>
            <a href="/admin/article/create" class="btn btn-primary btn-sm">+ New</a>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>';

        if (empty($articles)) {
            $content .= '<tr><td colspan="6" style="text-align:center;padding:24px;">No articles</td></tr>';
        } else {
            $statusMap = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
            foreach ($articles as $article) {
                $content .= '<tr>
                    <td>' . $article['id'] . '</td>
                    <td>' . htmlspecialchars($article['title']) . '</td>
                    <td>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</td>
                    <td>' . ($statusMap[$article['status']] ?? 'Unknown') . '</td>
                    <td>' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td>
                        <a href="/article/' . $article['id'] . '">View</a>
                        <a href="/admin/article/edit/' . $article['id'] . '">Edit</a>
                        <a href="/admin/article/delete/' . $article['id'] . '" onclick="return confirm(\'Delete this article?\')">Delete</a>
                    </td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Articles', $content);
    }

    public function createArticle(): string
    {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'author_id' => $this->getCurrentUser()['id'] ?? null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('Title and content are required');
            }

            $articleModel = new Article();
            $id = $articleModel->create($data);

            if ($id) {
                $cache = $this->getCache();
                if ($cache) {
                    $cache->delete('home_data');
                    $cache->delete('page:' . md5('/'));
                }
                $this->redirect('/admin/articles');
            }
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories);
    }

    private function renderArticleForm(string $error = '', array $categories = [], ?array $article = null): string
    {
        $isEdit = $article !== null;
        $statusOptions = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];

        // 直接从数据库读取原始内容，不做任何解码
        $content = '';
        $title = '';
        $excerpt = '';
        if ($article !== null) {
            $content = $article['content'] ?? '';
            $title = $article['title'] ?? '';
            $excerpt = $article['excerpt'] ?? '';
        }

        $html = '<div class="admin-form">
            <span class="page-title">' . ($isEdit ? 'Edit Article' : 'New Article') . '</span>
            ' . ($error ? '<div style="color:#c00;border:2px solid #c00;padding:8px;margin:12px 0;">' . htmlspecialchars($error) . '</div>' : '') . '
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="' . htmlspecialchars($title) . '" required>
                </div>
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <input type="text" id="excerpt" name="excerpt" value="' . htmlspecialchars($excerpt) . '">
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">None</option>';

        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        $html .= '</select></div>
                <div class="form-group">
                    <label for="editor">Content *</label>
                    <div style="margin-bottom:8px;">
                        <span style="font-size:0.75rem;color:var(--gray-500);">Supports HTML: h1, p, a, img, ul, ol, table, pre, code</span>
                        <button type="button" id="previewBtn" class="btn btn-sm" style="margin-left:8px;">Preview</button>
                    </div>
                    <textarea id="editor" name="content" required>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</textarea>
                    <div id="preview" style="display:none;border:2px solid var(--gray-200);padding:16px;margin-top:8px;background:var(--white);max-height:400px;overflow-y:auto;"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">';

        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $html .= '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
        }

        $html .= '</select></div>
                    <div style="display:flex;align-items:center;gap:16px;padding-top:8px;">
                        <label><input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> Top</label>
                        <label><input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend'] ? 'checked' : '') . '> Recommend</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Publish') . '</button>
                    <a href="/admin/articles" class="btn">Cancel</a>
                </div>
            </form>
        </div>';

        return $this->renderAdminLayout($isEdit ? 'Edit Article' : 'New Article', $html);
    }

    public function editArticle(string $id): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);

        if (!$article) {
            return $this->renderAdminLayout('Error', '<p>Article not found.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('Title and content are required', [], $article);
            }

            $articleModel->update((int)$id, $data);

            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
                $cache->delete('page:' . md5('/'));
                $cache->delete('page:' . md5('/article/' . $id));
            }

            $this->redirect('/admin/articles');
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories, $article);
    }

    public function deleteArticle(string $id): void
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articleModel->delete((int)$id);

        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
            $cache->delete('page:' . md5('/'));
            $cache->delete('page:' . md5('/article/' . $id));
        }

        $this->redirect('/admin/articles');
    }

    public function categories(): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categories = $categoryModel->findAll(true);

        $content = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Categories</span>
            <a href="/admin/category/create" class="btn btn-primary btn-sm">+ New</a>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody>';

        if (empty($categories)) {
            $content .= '<tr><td colspan="5" style="text-align:center;padding:24px;">No categories</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $content .= '<tr>
                    <td>' . $cat['id'] . '</td>
                    <td>' . htmlspecialchars($cat['name']) . '</td>
                    <td>' . htmlspecialchars($cat['slug']) . '</td>
                    <td>' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td>
                        <a href="/admin/category/edit/' . $cat['id'] . '">Edit</a>
                        <a href="/admin/category/delete/' . $cat['id'] . '" onclick="return confirm(\'Delete this category?\')">Delete</a>
                    </td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Categories', $content);
    }

    public function createCategory(): string
    {
        $this->checkAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                return $this->renderAdminLayout('New Category', '<div style="color:#c00;margin-bottom:12px;">Name is required</div>' . $this->getCategoryForm());
            }

            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name), '-'));
            }

            $categoryModel = new Category();
            $categoryModel->create($name, $slug, $description);

            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }

            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('New Category', $this->getCategoryForm());
    }

    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '">
                    <span style="font-size:0.75rem;color:var(--gray-500);">Leave blank to auto-generate</span>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Create') . '</button>
                    <a href="/admin/categories" class="btn">Cancel</a>
                </div>
            </form>
        </div>';
    }

    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);

        if (!$category) {
            return $this->renderAdminLayout('Error', '<p>Category not found.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            if (empty($data['name'])) {
                return $this->renderAdminLayout('Edit Category', '<div style="color:#c00;margin-bottom:12px;">Name is required</div>' . $this->getCategoryForm($category));
            }

            if (empty($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $data['name']), '-'));
            }

            $categoryModel->update((int)$id, $data);

            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }

            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('Edit Category', $this->getCategoryForm($category));
    }

    public function deleteCategory(string $id): void
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categoryModel->delete((int)$id);

        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
        }

        $this->redirect('/admin/categories');
    }

    public function users(): string
    {
        $this->checkAuth();
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");

        $content = '<span class="page-title">Users</span>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Registered</th></tr></thead>
            <tbody>';

        if (empty($users)) {
            $content .= '<tr><td colspan="6" style="text-align:center;padding:24px;">No users</td></tr>';
        } else {
            $roleMap = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author', 'subscriber' => 'Subscriber'];
            $statusMap = [0 => 'Disabled', 1 => 'Active', 2 => 'Pending'];
            foreach ($users as $user) {
                $content .= '<tr>
                    <td>' . $user['id'] . '</td>
                    <td>' . htmlspecialchars($user['username']) . '</td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                    <td>' . ($roleMap[$user['role']] ?? $user['role']) . '</td>
                    <td>' . ($statusMap[$user['status']] ?? $user['status']) . '</td>
                    <td>' . date('Y-m-d', strtotime($user['created_at'])) . '</td>
                </tr>';
            }
        }

        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Users', $content);
    }

    public function settings(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] !== 'admin') {
            $this->redirect('/admin');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF 验证
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }

            $settingModel = new Setting();
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit' && $key !== 'lig_csrf_token') {
                    $settingModel->set($key, trim($value));
                }
            }
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $perPage = $settingModel->get('per_page') ?? 10;

        $content = '<div class="admin-form">
            <span class="page-title">Settings</span>
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="' . htmlspecialchars($siteName) . '">
                </div>
                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <input type="text" id="site_description" name="site_description" value="' . htmlspecialchars($siteDesc) . '">
                </div>
                <div class="form-group">
                    <label for="per_page">Articles per Page</label>
                    <input type="number" id="per_page" name="per_page" value="' . htmlspecialchars($perPage) . '">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>';

        return $this->renderAdminLayout('Settings', $content);
    }

    public function clearCache(): void
    {
        $this->checkAuth();
        $cache = $this->getCache();
        if ($cache) {
            $cache->clear();
        }
        $this->redirect('/admin');
    }
}