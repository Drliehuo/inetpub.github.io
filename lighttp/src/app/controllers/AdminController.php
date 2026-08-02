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
    }
    private function getMenu(): array
    {
        $user = $this->getCurrentUser();
        $menu = [];
        $menu[] = ['url' => '/admin/profile', 'label' => 'Profile', 'icon' => 'user'];
        if (in_array($user['role'], ['author', 'editor', 'admin'])) {
            $menu[] = ['url' => '/admin/articles', 'label' => 'Articles', 'icon' => 'file-text'];
            $menu[] = ['url' => '/admin/article/create', 'label' => 'New Article', 'icon' => 'plus'];
        }
        if (in_array($user['role'], ['editor', 'admin'])) {
            $menu[] = ['url' => '/admin/categories', 'label' => 'Categories', 'icon' => 'folder-open'];
            $menu[] = ['url' => '/admin/users', 'label' => 'Users', 'icon' => 'users'];
        }
        if ($user['role'] === 'admin') {
            $menu[] = ['url' => '/admin/settings', 'label' => 'Settings', 'icon' => 'cog'];
            $menu[] = ['url' => '/admin/cache/clear', 'label' => 'Clear Cache', 'icon' => 'trash'];
        }
        return $menu;
    }
    private function renderAdminLayout(string $title, string $content): string
    {
        $user = $this->getCurrentUser();
        $menu = $this->getMenu();
        $settingModel = new \App\models\Setting();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        ob_start();
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?> · <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
    <style>
        body { padding-top: 70px; }
        .admin-header { background: #222; color: #fff; padding: 10px 0; position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .admin-header a { color: #9d9d9d; }
        .admin-header a:hover { color: #fff; text-decoration: none; }
        .admin-header .brand { color: #fff; font-size: 18px; font-weight: 600; }
        .admin-header .brand:hover { color: #fff; text-decoration: none; }
        .admin-header .user-info { color: #9d9d9d; }
        .admin-header .user-info .user-name { color: #fff; }
        .admin-header .user-info .user-role { color: #9d9d9d; font-size: 12px; }
        .admin-content { padding: 24px 0; }
        .page-title { font-size: 24px; font-weight: 600; border-bottom: 2px solid #337ab7; padding-bottom: 8px; margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #f9f9f9; border: 1px solid #e7e7e7; border-radius: 4px; padding: 20px 16px; text-align: center; }
        .stat-card .number { font-size: 28px; font-weight: 700; color: #333; display: block; }
        .stat-card .label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 4px; }
        .stat-card.primary { background: #337ab7; border-color: #2e6da4; }
        .stat-card.primary .number { color: #fff; }
        .stat-card.primary .label { color: rgba(255,255,255,0.7); }
        .admin-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 8px; }
        .admin-card { background: #fff; border: 1px solid #e7e7e7; border-radius: 4px; padding: 24px 16px; text-align: center; transition: all 0.15s ease; }
        .admin-card:hover { border-color: #337ab7; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-decoration: none; }
        .admin-card .icon { font-size: 28px; display: block; margin-bottom: 6px; color: #337ab7; }
        .admin-card .name { font-weight: 500; color: #333; }
        .admin-nav { margin-bottom: 20px; padding: 8px 0; border-bottom: 1px solid #e7e7e7; }
        .admin-nav .btn { margin-right: 4px; margin-bottom: 4px; }
        .admin-form { max-width: 700px; margin: 0 auto; }
        .admin-form .form-group { margin-bottom: 18px; }
        .admin-form .form-actions { display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap; }
        .table-wrap { overflow-x: auto; border: 1px solid #e7e7e7; border-radius: 4px; background: #fff; }
        .table-wrap table { margin-bottom: 0; }
        .alert { margin-bottom: 16px; }
        .status-badge { display: inline-block; padding: 2px 12px; font-size: 11px; font-weight: 600; border-radius: 3px; }
        .status-badge.published { background: #dff0d8; color: #3c763d; }
        .status-badge.draft { background: #fcf8e3; color: #8a6d3b; }
        .status-badge.pending { background: #fcf8e3; color: #8a6d3b; }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-card.primary { grid-column: span 2; }
            .admin-header .container { flex-direction: column; align-items: stretch; text-align: center; }
            .admin-header .user-info { justify-content: center; margin-top: 4px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .admin-grid { grid-template-columns: 1fr; }
            .stat-card.primary { grid-column: span 1; }
            .stat-card .number { font-size: 20px; }
            .admin-card { padding: 16px 12px; }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <div class="row">
                <div class="col-xs-6 col-sm-4">
                    <a href="/admin" class="brand"><?php echo htmlspecialchars($siteName); ?> Admin</a>
                </div>
                <div class="col-xs-6 col-sm-8 text-right">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                        <span class="user-role">(<?php echo htmlspecialchars($user['role'] ?? ''); ?>)</span>
                        <span class="hidden-xs">|</span>
                        <a href="/">Home</a>
                        <a href="/admin/profile">Profile</a>
                        <a href="/logout">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main class="admin-content">
        <div class="container">
            <nav class="admin-nav">
                <?php foreach ($menu as $item):
                    $isActive = (strpos($_SERVER['REQUEST_URI'], $item['url']) === 0);
                ?>
                <a href="<?php echo $item['url']; ?>" class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-default'; ?>">
                    <span class="glyphicon glyphicon-<?php echo $item['icon']; ?>"></span>
                    <?php echo $item['label']; ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <?php echo $content; ?>
        </div>
    </main>
    <script src="/npm/jquery@1.12.4/dist/jquery.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>
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
        <div class="alert alert-info">
            Welcome, <strong>' . htmlspecialchars($user['username']) . '</strong>
            <span class="text-muted">(Role: ' . htmlspecialchars($user['role']) . ')</span>
        </div>';
        if (in_array($user['role'], ['admin', 'editor'])) {
            $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
            $draftCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 0");
            $pendingCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 2");
            $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
            $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
            $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");
            $content .= '<div class="stats-grid">
                <div class="stat-card primary"><span class="number">' . ($articleCount['count'] ?? 0) . '</span><span class="label">Published</span></div>
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
                <div class="stat-card primary"><span class="number">' . ($myArticles['count'] ?? 0) . '</span><span class="label">My Published</span></div>
                <div class="stat-card"><span class="number">' . ($myDrafts['count'] ?? 0) . '</span><span class="label">My Drafts</span></div>
                <div class="stat-card"><span class="number">' . ($myPending['count'] ?? 0) . '</span><span class="label">My Pending</span></div>
            </div>';
        } else {
            $content .= '<div class="alert alert-warning">Your account is currently in subscriber role. Please contact administrator for more permissions.</div>';
        }
        $content .= '<div class="admin-grid">';
        $menu = $this->getMenu();
        foreach ($menu as $item) {
            if ($item['url'] !== '/admin/profile') {
                $content .= '<a href="' . $item['url'] . '" class="admin-card">
                    <span class="glyphicon glyphicon-' . $item['icon'] . ' icon"></span>
                    <span class="name">' . $item['label'] . '</span>
                </a>';
            }
        }
        $content .= '<a href="/admin/profile" class="admin-card">
            <span class="glyphicon glyphicon-user icon"></span>
            <span class="name">Profile</span>
        </a>';
        $content .= '</div>';
        return $this->renderAdminLayout('Dashboard', $content);
    }
    public function articles(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to view articles.</div>');
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
        $filterHtml = '<div class="row" style="margin-bottom:16px;">
            <div class="col-xs-12 col-sm-6">
                <span class="page-title" style="border-bottom:none;padding-bottom:0;margin-bottom:0;">Articles</span>
            </div>
            <div class="col-xs-12 col-sm-6 text-right">
                <div class="btn-group btn-group-sm">
                    <a href="/admin/articles" class="btn btn-sm ' . ($statusFilter === '' ? 'btn-primary' : 'btn-default') . '">All</a>
                    <a href="/admin/articles?status=published" class="btn btn-sm ' . ($statusFilter === 'published' ? 'btn-primary' : 'btn-default') . '">Published</a>
                    <a href="/admin/articles?status=draft" class="btn btn-sm ' . ($statusFilter === 'draft' ? 'btn-primary' : 'btn-default') . '">Draft</a>
                    <a href="/admin/articles?status=pending" class="btn btn-sm ' . ($statusFilter === 'pending' ? 'btn-primary' : 'btn-default') . '">Pending</a>
                </div>
                <a href="/admin/article/create" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span> New</a>
            </div>
        </div>';
        $content = $filterHtml . '
        <div class="table-wrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th width="180">Actions</th>
                </tr>
            </thead>
            <tbody>';
        if (empty($articles)) {
            $content .= '<tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">No articles found</td></tr>';
        } else {
            $statusMap = [0 => 'Draft', 1 => 'Published', 2 => 'Pending'];
            foreach ($articles as $article) {
                $status = $statusMap[$article['status']] ?? 'Unknown';
                $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
                $isOwn = ($user['id'] === $article['author_id']);
                $canEdit = in_array($user['role'], ['admin', 'editor']) || $isOwn;
                $statusClass = strtolower($status);
                $content .= '<tr>
                    <td>' . $article['id'] . '</td>
                    <td>' . htmlspecialchars($article['title']) . '</td>
                    <td>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</td>
                    <td>' . htmlspecialchars($authorDisplay) . ($isOwn ? ' <span class="text-muted small">(you)</span>' : '') . '</td>
                    <td><span class="status-badge ' . $statusClass . '">' . $status . '</span></td>
                    <td>' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td>
                        <div class="btn-group btn-group-xs">
                            <a href="/article/' . $article['id'] . '" class="btn btn-default">View</a>
                            ' . ($canEdit ? '<a href="/admin/article/edit/' . $article['id'] . '" class="btn btn-primary">Edit</a>' : '<span class="btn btn-default disabled">Edit</span>') . '
                            ' . ($canEdit ? '<a href="/admin/article/delete/' . $article['id'] . '" class="btn btn-danger" onclick="return confirm(\'Delete this article?\')">Delete</a>' : '<span class="btn btn-default disabled">Delete</span>') . '
                        </div>
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
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to create articles.</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<div class="alert alert-danger">CSRF token validation failed</div>');
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
                return $this->renderArticleForm('Title and content are required', []);
            }
            $articleModel = new Article();
            $id = $articleModel->create($data);
            if ($id) {
                $cache = $this->getCache();
                if ($cache) {
                    $cache->clearModule('home');
                    $cache->clearModule('page');
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
        $html = '<div class="page-title">' . ($isEdit ? 'Edit Article' : 'New Article') . '</div>';
        if ($error) {
            $html .= '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
        }
        $html .= '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($title) . '" required>
                </div>
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <input type="text" class="form-control" id="excerpt" name="excerpt" value="' . htmlspecialchars($excerpt) . '">
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">None</option>';
        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }
        $html .= '</select></div>
                <div class="form-group">
                    <label for="editor">Content *</label>
                    <div style="margin-bottom:8px;">
                        <span class="text-muted small">Supports HTML: h1, p, a, img, ul, ol, table, pre, code</span>
                        <button type="button" id="previewBtn" class="btn btn-default btn-sm" style="margin-left:8px;">Preview</button>
                    </div>
                    <textarea class="form-control" id="editor" name="content" rows="12" required>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</textarea>
                    <div id="preview" style="display:none;border:1px solid #ddd;padding:16px;margin-top:8px;background:#fff;max-height:400px;overflow-y:auto;"></div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" ' . ($isAuthor && !$isEdit ? 'disabled' : '') . '>';
        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $disabled = ($isAuthor && !$isEdit && $k !== 2) ? 'disabled' : '';
            $html .= '<option value="' . $k . '" ' . $selected . ' ' . $disabled . '>' . $v . '</option>';
        }
        $html .= '</select>';
        if ($isAuthor && !$isEdit) {
            $html .= '<span class="help-block">Author role: new articles are automatically set to Pending.</span>';
        }
        if ($isEdit && $isAuthor && $article && $article['status'] == 2) {
            $html .= '<span class="help-block">This article is pending review. You can edit it but cannot publish it.</span>';
        }
        $html .= '</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group" style="padding-top:25px;">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> Top</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend'] ? 'checked' : '') . '> Recommend</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Publish') . '</button>
                    <a href="/admin/articles" class="btn btn-default">Cancel</a>
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
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to edit articles.</div>');
        }
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        if (!$article) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">Article not found.</div>');
        }
        if ($user['role'] === 'author' && $user['id'] !== $article['author_id']) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to edit this article.</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<div class="alert alert-danger">CSRF token validation failed</div>');
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
                $cache->clearModule('home');
                $cache->clearModule('page');
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
            $cache->clearModule('home');
            $cache->clearModule('page');
        }
        $this->redirect('/admin/articles');
    }
    public function categories(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to manage categories.</div>');
        }
        $categoryModel = new Category();
        $categories = $categoryModel->findAll(true);
        $content = '<div class="row" style="margin-bottom:16px;">
            <div class="col-xs-12 col-sm-6">
                <span class="page-title" style="border-bottom:none;padding-bottom:0;margin-bottom:0;">Categories</span>
            </div>
            <div class="col-xs-12 col-sm-6 text-right">
                <a href="/admin/category/create" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span> New</a>
            </div>
        </div>
        <div class="table-wrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>';
        if (empty($categories)) {
            $content .= '<tr><td colspan="5" style="text-align:center;padding:24px;color:#999;">No categories</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $content .= '<tr>
                    <td>' . $cat['id'] . '</td>
                    <td>' . htmlspecialchars($cat['name']) . '</td>
                    <td>' . htmlspecialchars($cat['slug']) . '</td>
                    <td>' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td>
                        <div class="btn-group btn-group-xs">
                            <a href="/admin/category/edit/' . $cat['id'] . '" class="btn btn-primary">Edit</a>
                            <a href="/admin/category/delete/' . $cat['id'] . '" class="btn btn-danger" onclick="return confirm(\'Delete this category?\')">Delete</a>
                        </div>
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
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to create categories.</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<div class="alert alert-danger">CSRF token validation failed</div>');
            }
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (empty($name)) {
                return $this->renderAdminLayout('New Category', '<div class="alert alert-danger">Name is required</div>' . $this->getCategoryForm());
            }
            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name), '-'));
            }
            $categoryModel = new Category();
            $categoryModel->create($name, $slug, $description);
            $cache = $this->getCache();
            if ($cache) {
                $cache->clearModule('home');
            }
            $this->redirect('/admin/categories');
        }
        return $this->renderAdminLayout('New Category', $this->getCategoryForm());
    }
    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '<div class="page-title">' . ($isEdit ? 'Edit Category' : 'New Category') . '</div>
        <div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" class="form-control" id="name" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '">
                    <span class="help-block">Leave blank to auto-generate</span>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? 'Update' : 'Create') . '</button>
                    <a href="/admin/categories" class="btn btn-default">Cancel</a>
                </div>
            </form>
        </div>';
    }
    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to edit categories.</div>');
        }
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);
        if (!$category) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">Category not found.</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<div class="alert alert-danger">CSRF token validation failed</div>');
            }
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];
            if (empty($data['name'])) {
                return $this->renderAdminLayout('Edit Category', '<div class="alert alert-danger">Name is required</div>' . $this->getCategoryForm($category));
            }
            if (empty($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $data['name']), '-'));
            }
            $categoryModel->update((int)$id, $data);
            $cache = $this->getCache();
            if ($cache) {
                $cache->clearModule('home');
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
            $cache->clearModule('home');
        }
        $this->redirect('/admin/categories');
    }
    public function users(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to view users.</div>');
        }
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");
        $content = '<div class="page-title">Users</div>
        <div class="table-wrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Nickname</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>';
        if (empty($users)) {
            $content .= '<tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">No users</td></tr>';
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
                        <select name="role" class="form-control input-sm" style="display:inline-block;width:auto;" onchange="this.form.submit()">
                            <option value="subscriber"' . ($u['role'] === 'subscriber' ? ' selected' : '') . '>Subscriber</option>
                            <option value="author"' . ($u['role'] === 'author' ? ' selected' : '') . '>Author</option>
                            <option value="editor"' . ($u['role'] === 'editor' ? ' selected' : '') . '>Editor</option>
                            <option value="admin"' . ($u['role'] === 'admin' ? ' selected' : '') . '>Admin</option>
                        </select>
                    </form>';
                } else {
                    $content .= $role . ($u['id'] == $user['id'] ? ' <span class="text-muted small">(you)</span>' : '');
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
            return $this->renderAdminLayout('Error', '<div class="alert alert-danger">You do not have permission to access settings.</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('Error', '<div class="alert alert-danger">CSRF token validation failed</div>');
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
                $cache->clearModule('home');
                $cache->clearModule('page');
            }
            $success = true;
        }
        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $siteFooter = $settingModel->get('site_footer') ?? 'All rights reserved.';
        $perPage = $settingModel->get('per_page') ?? 10;
        $content = '<div class="page-title">Settings</div>';
        if (isset($success)) {
            $content .= '<div class="alert alert-success">Settings saved successfully!</div>';
        }
        $content .= '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input type="text" class="form-control" id="site_name" name="site_name" value="' . htmlspecialchars($siteName) . '">
                </div>
                <div class="form-group">
                    <label for="site_description">Site Description</label>
                    <input type="text" class="form-control" id="site_description" name="site_description" value="' . htmlspecialchars($siteDesc) . '">
                </div>
                <div class="form-group">
                    <label for="site_footer">Footer Text <span class="text-muted small">(Powered by Lighttp is automatically appended)</span></label>
                    <input type="text" class="form-control" id="site_footer" name="site_footer" value="' . htmlspecialchars($siteFooter) . '">
                    <span class="help-block">This text appears before "Powered by Lighttp" in the footer.</span>
                </div>
                <div class="form-group">
                    <label for="per_page">Articles per Page</label>
                    <input type="number" class="form-control" id="per_page" name="per_page" value="' . htmlspecialchars($perPage) . '">
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
            $cache->clear();
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
        $content = '<div class="page-title">My Profile</div>
        <div class="panel panel-default">
            <div class="panel-body">
                <p><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</p>
                <p><strong>Nickname:</strong> ' . htmlspecialchars($user['nickname'] ?? '-') . '</p>
                <p><strong>Role:</strong> ' . htmlspecialchars($user['role']) . '</p>
                <p><strong>Joined:</strong> ' . date('Y-m-d', strtotime($user['created_at'])) . '</p>
                <p><strong>Last Login:</strong> ' . ($user['last_login_time'] ? date('Y-m-d H:i', strtotime($user['last_login_time'])) : 'Never') . '</p>
            </div>
        </div>
        <h3 style="margin-top:30px;border-bottom:1px solid #e7e7e7;padding-bottom:8px;">Update Email &amp; Nickname</h3>
        <div class="admin-form">
            <form method="POST" action="/admin/profile/update" id="profileForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '" required>
                </div>
                <div class="form-group">
                    <label for="nickname">Nickname</label>
                    <input type="text" class="form-control" id="nickname" name="nickname" value="' . htmlspecialchars($user['nickname'] ?? '') . '" placeholder="Display name (optional)">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
        <h3 style="margin-top:30px;border-bottom:1px solid #e7e7e7;padding-bottom:8px;">Change Password</h3>
        <div class="admin-form">
            <form method="POST" action="/admin/profile/password" id="passwordForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password (min 6 chars) *</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>';
        if (isset($_SESSION['profile_message'])) {
            $msg = $_SESSION['profile_message'];
            $msgType = $_SESSION['profile_message_type'] ?? 'success';
            $alertClass = $msgType === 'success' ? 'alert-success' : 'alert-danger';
            $content = '<div class="alert ' . $alertClass . '">' . htmlspecialchars($msg) . '</div>' . $content;
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