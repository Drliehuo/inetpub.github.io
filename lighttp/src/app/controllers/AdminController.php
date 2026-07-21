<?php declare(strict_types=1);
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
    }
    private function getMenu(): array
    {
        $user = $this->getCurrentUser();
        $menu = [];
        $menu[] = ['url' => '/admin/profile', 'label' => 'Profile', 'icon' => '[P]'];
        if (in_array($user['role'], ['author', 'editor', 'admin'])) {
            $menu[] = ['url' => '/admin/articles', 'label' => 'Articles', 'icon' => '[A]'];
            $menu[] = ['url' => '/admin/article/create', 'label' => 'New Article', 'icon' => '[+]'];
        }
        if (in_array($user['role'], ['editor', 'admin'])) {
            $menu[] = ['url' => '/admin/categories', 'label' => 'Categories', 'icon' => '[C]'];
            $menu[] = ['url' => '/admin/users', 'label' => 'Users', 'icon' => '[U]'];
        }
        if ($user['role'] === 'admin') {
            $menu[] = ['url' => '/admin/settings', 'label' => 'Settings', 'icon' => '[S]'];
            $menu[] = ['url' => '/admin/cache/clear', 'label' => 'Clear Cache', 'icon' => '[X]'];
        }
        return $menu;
    }
private function renderAdminLayout(string $title, string $content): string
{
    $user = $this->getCurrentUser();
    $menu = $this->getMenu();
    // 从数据库获取网站名称
    $settingModel = new \App\models\Setting();
    $siteName = $settingModel->get('site_name') ?? 'Lighttp';
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="brand"><a href="/admin"><?php echo htmlspecialchars($siteName); ?> Admin</a></div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                <span style="color:var(--gray-500);font-size:0.75rem;margin-left:4px;">(<?php echo htmlspecialchars($user['role'] ?? ''); ?>)</span>
                <a href="/">Home</a>
                <a href="/admin/profile">Profile</a>
                <a href="/logout">Logout</a>
            </div>
        </div>
    </header>
    <main class="admin-content">
        <div class="container">
            <nav class="admin-nav" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;padding:12px 0;border-bottom:2px solid var(--gray-200);">
                <?php foreach ($menu as $item): ?>
                    <a href="<?php echo $item['url']; ?>" class="btn btn-sm" style="<?php echo (strpos($_SERVER['REQUEST_URI'], $item['url']) === 0) ? 'background:var(--black);color:var(--white);' : ''; ?>">
                        <?php echo $item['icon']; ?> <?php echo $item['label']; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
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
        $user = $this->getCurrentUser();
        $db = $this->getDb();
        $content = '<div class="page-title">Dashboard</div>
        <div style="margin-bottom:16px;padding:12px 16px;background:var(--gray-25);border:2px solid var(--gray-200);font-size:0.875rem;color:var(--gray-700);">
            Welcome, <strong>' . htmlspecialchars($user['username']) . '</strong>
            <span style="color:var(--gray-500);">(Role: ' . htmlspecialchars($user['role']) . ')</span>
        </div>';
        if (in_array($user['role'], ['admin', 'editor'])) {
            $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
            $draftCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 0");
            $pendingCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 2");
            $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
            $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
            $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");
            $content .= '<div class="stats-grid">
                <div class="stat-card"><span class="number">' . ($articleCount['count'] ?? 0) . '</span><span class="label">Published</span></div>
                <div class="stat-card"><span class="number">' . ($draftCount['count'] ?? 0) . '</span><span class="label">Drafts</span></div>
                <div class="stat-card"><span class="number">' . ($pendingCount['count'] ?? 0) . '</span><span class="label">Pending</span></div>
                <div class="stat-card"><span class="number">' . ($categoryCount['count'] ?? 0) . '</span><span class="label">Categories</span></div>
                <div class="stat-card"><span class="number">' . ($commentCount['count'] ?? 0) . '</span><span class="label">Comments</span></div>
                <div class="stat-card"><span class="number">' . ($userCount['count'] ?? 0) . '</span><span class="label">Users</span></div>
            </div>';
        } elseif ($user['role'] === 'author') {
            $myArticles = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 1", [$user['id']]);
            $myDrafts = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 0", [$user['id']]);
            $myPending = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 2", [$user['id']]);
            $content .= '<div class="stats-grid">
                <div class="stat-card"><span class="number">' . ($myArticles['count'] ?? 0) . '</span><span class="label">My Published</span></div>
                <div class="stat-card"><span class="number">' . ($myDrafts['count'] ?? 0) . '</span><span class="label">My Drafts</span></div>
                <div class="stat-card"><span class="number">' . ($myPending['count'] ?? 0) . '</span><span class="label">My Pending</span></div>
            </div>';
        } else {
            $content .= '<p style="color:var(--gray-500);">Your account is currently in subscriber role. Please contact administrator for more permissions.</p>';
        }
        $content .= '<div class="admin-grid">';
        $menu = $this->getMenu();
        foreach ($menu as $item) {
            if ($item['url'] !== '/admin/profile') {
                $content .= '<a href="' . $item['url'] . '" class="admin-card"><span class="icon">' . $item['icon'] . '</span><span class="name">' . $item['label'] . '</span></a>';
            }
        }
        $content .= '<a href="/admin/profile" class="admin-card"><span class="icon">[P]</span><span class="name">Profile</span></a>';
        $content .= '</div>';
        return $this->renderAdminLayout('Dashboard', $content);
    }
    public function articles(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to view articles.</p>');
        }
        $statusFilter = $_GET['status'] ?? '';
        $allowedStatus = ['draft', 'pending', 'published'];
        $filter = null;
        if (in_array($statusFilter, $allowedStatus)) {
            $statusMap = ['draft' => 0, 'pending' => 2, 'published' => 1];
            $filter = $statusMap[$statusFilter];
        }
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $settingModel = new Setting();
        $perPage = (int)($settingModel->get('per_page') ?? 10);
        $articleModel = new Article();
        $authorId = null;
        if ($user['role'] === 'author') {
            $authorId = $user['id'];
        }
        $result = $articleModel->getPaginated($filter, $page, $perPage, $authorId);
        $articles = $result['data'];
        $total = $result['total'];
        $totalPages = $result['totalPages'];
        $filterHtml = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Articles</span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <select id="statusFilter" onchange="window.location.href=this.value ? \'/admin/articles?status=\'+this.value : \'/admin/articles\'" style="padding:8px 12px;border:2px solid var(--gray-300);background:var(--white);font-size:0.875rem;">
                    <option value="">All Status</option>
                    <option value="published"' . ($statusFilter === 'published' ? ' selected' : '') . '>Published</option>
                    <option value="draft"' . ($statusFilter === 'draft' ? ' selected' : '') . '>Draft</option>
                    <option value="pending"' . ($statusFilter === 'pending' ? ' selected' : '') . '>Pending</option>
                </select>
                <a href="/admin/article/create" class="btn btn-primary btn-sm">+ New</a>
            </div>
        </div>';
        $content = $filterHtml . '
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>';
        if (empty($articles)) {
            $content .= '<tr><td colspan="7" style="text-align:center;padding:24px;">No articles found</td></tr>';
        } else {
            $statusMap = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
            foreach ($articles as $article) {
                $status = $statusMap[$article['status']] ?? 'Unknown';
                $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
                $isOwn = ($user['id'] === $article['author_id']);
                $canEdit = in_array($user['role'], ['admin', 'editor']) || $isOwn;
                $content .= '<tr>
                    <td>' . $article['id'] . '</td>
                    <td>' . htmlspecialchars($article['title']) . '</td>
                    <td>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</td>
                    <td>' . htmlspecialchars($authorDisplay) . ($isOwn ? ' <span style="color:var(--ms-gray-500);font-size:0.75rem;">(you)</span>' : '') . '</td>
                    <td><span class="status-badge status-' . strtolower($status) . '">' . $status . '</span></td>
                    <td>' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td>
                        <a href="/article/' . $article['id'] . '">View</a>
                        ' . ($canEdit ? '<a href="/admin/article/edit/' . $article['id'] . '">Edit</a>' : '<span style="color:var(--gray-300);">Edit</span>') . '
                        ' . ($canEdit ? '<a href="/admin/article/delete/' . $article['id'] . '" onclick="return confirm(\'Delete this article?\')">Delete</a>' : '<span style="color:var(--gray-300);">Delete</span>') . '
                    </td>
                </tr>';
            }
        }
        $content .= '</tbody></table></div>';
        if ($totalPages > 1) {
            $baseUrl = '/admin/articles?' . (isset($_GET['status']) ? 'status=' . $_GET['status'] . '&' : '');
            $total = $result['total'];
            $perPage = $result['perPage'];
            $currentPage = $page;
            ob_start();
            include APP_PATH . '/views/partials/pagination.php';
            $content .= ob_get_clean();
        }
        return $this->renderAdminLayout('Articles', $content);
    }
    public function createArticle(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to create articles.</p>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }
            $status = (int)($_POST['status'] ?? 1);
            if ($user['role'] === 'author') {
                $status = 2;
            }
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'author_id' => $user['id'] ?? null,
                'status' => $status,
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
        $user = $this->getCurrentUser();
        $statusOptions = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
        $content = $article ? ($article['content'] ?? '') : '';
        $title = $article ? ($article['title'] ?? '') : '';
        $excerpt = $article ? ($article['excerpt'] ?? '') : '';
        $isAuthor = ($user['role'] === 'author');
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
                        <select id="status" name="status" ' . ($isAuthor && !$isEdit ? 'disabled' : '') . '>';
        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $disabled = ($isAuthor && !$isEdit && $k !== 2) ? 'disabled' : '';
            $html .= '<option value="' . $k . '" ' . $selected . ' ' . $disabled . '>' . $v . '</option>';
        }
        $html .= '</select>';
        if ($isAuthor && !$isEdit) {
            $html .= '<span style="font-size:0.75rem;color:var(--gray-500);display:block;margin-top:4px;">Author role: new articles are automatically set to Pending.</span>';
        }
        if ($isEdit && $isAuthor && $article && $article['status'] == 2) {
            $html .= '<span style="font-size:0.75rem;color:var(--gray-500);display:block;margin-top:4px;">This article is pending review. You can edit it but cannot publish it.</span>';
        }
        $html .= '</div>
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
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to edit articles.</p>');
        }
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        if (!$article) {
            return $this->renderAdminLayout('Error', '<p>Article not found.</p>');
        }
        if ($user['role'] === 'author' && $user['id'] !== $article['author_id']) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to edit this article.</p>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
            }
            $status = (int)($_POST['status'] ?? 1);
            if ($user['role'] === 'author') {
                $currentStatus = $article['status'];
                if ($status === 1) {
                    if ($currentStatus === 2 || $currentStatus === 0) {
                        $status = $currentStatus === 2 ? 2 : 0;
                    }
                }
            }
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'status' => $status,
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
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            $this->redirect('/admin');
            return;
        }
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        if (!$article) {
            $this->redirect('/admin/articles');
            return;
        }
        if ($user['role'] === 'author' && $user['id'] !== $article['author_id']) {
            $this->redirect('/admin/articles');
            return;
        }
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
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to manage categories.</p>');
        }
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
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to create categories.</p>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to edit categories.</p>');
        }
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);
        if (!$category) {
            return $this->renderAdminLayout('Error', '<p>Category not found.</p>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            $this->redirect('/admin');
            return;
        }
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
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to view users.</p>');
        }
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");
        $content = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <span class="page-title">Users</span>';
        if ($user['role'] === 'admin') {
            $content .= '<span style="font-size:0.75rem;color:var(--gray-500);">Click role to change</span>';
        }
        $content .= '</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Nickname</th><th>Role</th><th>Status</th><th>Registered</th></tr></thead>
            <tbody>';
        if (empty($users)) {
            $content .= '<tr><td colspan="7" style="text-align:center;padding:24px;">No users</td></tr>';
        } else {
            $roleMap = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author', 'subscriber' => 'Subscriber'];
            $statusMap = [0 => 'Disabled', 1 => 'Active', 2 => 'Pending'];
            foreach ($users as $u) {
                $role = $roleMap[$u['role']] ?? $u['role'];
                $status = $statusMap[$u['status']] ?? $u['status'];
                $content .= '<tr>
                    <td>' . $u['id'] . '</td>
                    <td>' . htmlspecialchars($u['username']) . '</td>
                    <td>' . htmlspecialchars($u['email']) . '</td>
                    <td>' . htmlspecialchars($u['nickname'] ?? '-') . '</td>
                    <td>';
                if ($user['role'] === 'admin' && $u['id'] != $user['id']) {
                    $content .= '<form method="POST" action="/admin/user/role" style="display:inline;" onsubmit="return confirm(\'Change role for user ' . htmlspecialchars($u['username']) . '?\')">
                        ' . $this->csrfField() . '
                        <input type="hidden" name="user_id" value="' . $u['id'] . '">
                        <select name="role" onchange="this.form.submit()" style="padding:2px 6px;border:2px solid var(--gray-300);background:var(--white);font-size:0.813rem;">
                            <option value="subscriber"' . ($u['role'] === 'subscriber' ? ' selected' : '') . '>Subscriber</option>
                            <option value="author"' . ($u['role'] === 'author' ? ' selected' : '') . '>Author</option>
                            <option value="editor"' . ($u['role'] === 'editor' ? ' selected' : '') . '>Editor</option>
                            <option value="admin"' . ($u['role'] === 'admin' ? ' selected' : '') . '>Admin</option>
                        </select>
                    </form>';
                } else {
                    $content .= $role . ($u['id'] == $user['id'] ? ' <span style="color:var(--gray-500);font-size:0.75rem;">(you)</span>' : '');
                }
                $content .= '</td>
                    <td>' . $status . '</td>
                    <td>' . date('Y-m-d', strtotime($u['created_at'])) . '</td>
                </tr>';
            }
        }
        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('Users', $content);
    }
    public function updateUserRole(): void
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] !== 'admin') {
            $this->redirect('/admin/users');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/users');
            return;
        }
        if (!$this->verifyCsrf()) {
            $this->redirect('/admin/users');
            return;
        }
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        $allowedRoles = ['subscriber', 'author', 'editor', 'admin'];
        if (!$targetId || !in_array($newRole, $allowedRoles)) {
            $this->redirect('/admin/users');
            return;
        }
        // 不能修改自己的角色
        if ($targetId == $user['id']) {
            $this->redirect('/admin/users');
            return;
        }
        $userModel = new User();
        $targetUser = $userModel->find($targetId);
        if (!$targetUser) {
            $this->redirect('/admin/users');
            return;
        }
        // 不能将 Admin 降级（防止意外）
        if ($targetUser['role'] === 'admin' && $newRole !== 'admin') {
            $this->redirect('/admin/users');
            return;
        }
        $userModel->update($targetId, ['role' => $newRole]);
        $this->redirect('/admin/users');
    }
    public function settings(): string
    {
    $this->checkAuth();
    $user = $this->getCurrentUser();
    if ($user['role'] !== 'admin') {
        return $this->renderAdminLayout('Error', '<p style="color:#c00;">You do not have permission to access settings.</p>');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$this->verifyCsrf()) {
            return $this->renderAdminLayout('Error', '<p style="color:#c00;">CSRF token validation failed</p>');
        }
        $settingModel = new Setting();
        $excludeKeys = ['submit', 'lig_csrf_token'];
        foreach ($_POST as $key => $value) {
            if (!in_array($key, $excludeKeys)) {
                $settingModel->set($key, trim($value));
            }
        }
        $cache = $this->getCache();
        if ($cache) {
            // AVD-007 修复：清除首页和页面缓存
            $cache->clearModule('home');
            $cache->clearModule('page');
        }
        }
        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $siteFooter = $settingModel->get('site_footer') ?? 'All rights reserved.';
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
                    <label for="site_footer">Footer Text <span style="color:var(--gray-500);font-weight:400;">(Powered by Lighttp is automatically appended)</span></label>
                    <input type="text" id="site_footer" name="site_footer" value="' . htmlspecialchars($siteFooter) . '">
                    <small style="color:var(--gray-500);font-size:0.75rem;">This text appears before "Powered by Lighttp" in the footer.</small>
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
    $user = $this->getCurrentUser();
    if ($user['role'] !== 'admin') {
        $this->redirect('/admin');
        return;
    }
    $cache = $this->getCache();
    if ($cache) {
        // AVD-007 修复：使用统一方法清除所有缓存
        $cache->clear();
        // 或者只清除页面和首页缓存（保留其他）
        // $cache->clearModule('page');
        // $cache->clearModule('home');
    }
    $db = $this->getDb();
    if ($db) {
        $db->execute("TRUNCATE TABLE cache");
    }
    $this->redirect('/admin');
}
    public function profile(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        $content = '<div class="admin-form">
            <span class="page-title">My Profile</span>
            <div style="margin-bottom:24px;padding:12px 16px;background:var(--gray-25);border:2px solid var(--gray-200);">
                <p><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</p>
                <p><strong>Role:</strong> ' . htmlspecialchars($user['role']) . '</p>
                <p><strong>Joined:</strong> ' . date('Y-m-d', strtotime($user['created_at'])) . '</p>
                <p><strong>Last Login:</strong> ' . ($user['last_login_time'] ? date('Y-m-d H:i', strtotime($user['last_login_time'])) : 'Never') . '</p>
            </div>
            <h3 style="margin:24px 0 12px;border-bottom:2px solid var(--gray-200);padding-bottom:8px;">Update Email &amp; Nickname</h3>
            <form method="POST" action="/admin/profile/update" id="profileForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '" required>
                </div>
                <div class="form-group">
                    <label for="nickname">Nickname</label>
                    <input type="text" id="nickname" name="nickname" value="' . htmlspecialchars($user['nickname'] ?? '') . '" placeholder="Display name (optional)">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
            <h3 style="margin:32px 0 12px;border-bottom:2px solid var(--gray-200);padding-bottom:8px;">Change Password</h3>
            <form method="POST" action="/admin/profile/password" id="passwordForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password (min 6 chars) *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>';
        if (isset($_SESSION['profile_message'])) {
            $msg = $_SESSION['profile_message'];
            $msgType = $_SESSION['profile_message_type'] ?? 'success';
            $color = $msgType === 'success' ? '#155724' : '#721c24';
            $bg = $msgType === 'success' ? '#d4edda' : '#f8d7da';
            $border = $msgType === 'success' ? '#28a745' : '#dc3545';
            $content = '<div style="background:' . $bg . ';border:2px solid ' . $border . ';padding:12px 16px;margin-bottom:16px;color:' . $color . ';">' . htmlspecialchars($msg) . '</div>' . $content;
            unset($_SESSION['profile_message']);
            unset($_SESSION['profile_message_type']);
        }
        return $this->renderAdminLayout('Profile', $content);
    }
    public function updateProfile(): void
    {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/profile');
            return;
        }
        if (!$this->verifyCsrf()) {
            $_SESSION['profile_message'] = 'CSRF token validation failed';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $user = $this->getCurrentUser();
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_message'] = 'Invalid email address';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel = new User();
        $existing = $userModel->findByEmail($email);
        if ($existing && $existing['id'] != $user['id']) {
            $_SESSION['profile_message'] = 'Email already in use by another account';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel->updateProfile($user['id'], $email, $nickname);
        Application::getInstance()->setCurrentUser($userModel->find($user['id']));
        $_SESSION['profile_message'] = 'Profile updated successfully';
        $_SESSION['profile_message_type'] = 'success';
        $this->redirect('/admin/profile');
    }
    public function updatePassword(): void
    {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/profile');
            return;
        }
        if (!$this->verifyCsrf()) {
            $_SESSION['profile_message'] = 'CSRF token validation failed';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $user = $this->getCurrentUser();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['profile_message'] = 'All password fields are required';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        if (strlen($newPassword) < 6) {
            $_SESSION['profile_message'] = 'New password must be at least 6 characters';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        if ($newPassword !== $confirmPassword) {
            $_SESSION['profile_message'] = 'New passwords do not match';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel = new User();
        $dbUser = $userModel->find($user['id']);
        if (!$userModel->verifyPassword($currentPassword, $dbUser['password'])) {
            $_SESSION['profile_message'] = 'Current password is incorrect';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel->updatePassword($user['id'], $newPassword);
        $_SESSION['profile_message'] = 'Password changed successfully';
        $_SESSION['profile_message_type'] = 'success';
        $this->redirect('/admin/profile');
    }
}