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
    $menu[] = ['url' => '/admin/profile', 'label' => '个人资料', 'icon' => 'user'];
    if (in_array($user['role'], ['author', 'editor', 'admin'])) {
        $menu[] = ['url' => '/admin/articles', 'label' => '文章管理', 'icon' => 'file'];      // ✅ 已有图标
        $menu[] = ['url' => '/admin/article/create', 'label' => '新建文章', 'icon' => 'plus'];
    }
    if (in_array($user['role'], ['editor', 'admin'])) {
        $menu[] = ['url' => '/admin/categories', 'label' => '分类管理', 'icon' => 'folder-open'];
        $menu[] = ['url' => '/admin/users', 'label' => '用户管理', 'icon' => 'user'];            // ✅ 已有图标
    }
    if ($user['role'] === 'admin') {
        $menu[] = ['url' => '/admin/settings', 'label' => '系统设置', 'icon' => 'cog'];
        $menu[] = ['url' => '/admin/cache/clear', 'label' => '清空缓存', 'icon' => 'trash'];
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
                    <a href="/admin" class="brand"><?php echo htmlspecialchars($siteName); ?> 管理后台</a>
                </div>
                <div class="col-xs-6 col-sm-8 text-right">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['username'] ?? ''); ?></span>
                        <span class="user-role">(<?php echo htmlspecialchars($user['role'] ?? ''); ?>)</span>
                        <span class="hidden-xs">|</span>
                        <a href="/">首页</a>
                        <a href="/admin/profile">个人资料</a>
                        <a href="/logout">退出</a>
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
    $content = '<div class="page-title">仪表盘</div>
    <div class="alert alert-info">
        欢迎回来，<strong>' . htmlspecialchars($user['username']) . '</strong>
        <span class="text-muted">(角色: ' . htmlspecialchars($user['role']) . ')</span>
    </div>';
    if (in_array($user['role'], ['admin', 'editor'])) {
        $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
        $draftCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 0");
        $pendingCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 2");
        $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
        $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
        $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");
        $content .= '<div class="stats-grid">
            <div class="stat-card primary"><span class="number">' . ($articleCount['count'] ?? 0) . '</span><span class="label">已发布</span></div>
            <div class="stat-card"><span class="number">' . ($draftCount['count'] ?? 0) . '</span><span class="label">草稿</span></div>
            <div class="stat-card"><span class="number">' . ($pendingCount['count'] ?? 0) . '</span><span class="label">待审核</span></div>
            <div class="stat-card"><span class="number">' . ($categoryCount['count'] ?? 0) . '</span><span class="label">分类</span></div>
            <div class="stat-card"><span class="number">' . ($commentCount['count'] ?? 0) . '</span><span class="label">评论</span></div>
            <div class="stat-card"><span class="number">' . ($userCount['count'] ?? 0) . '</span><span class="label">用户</span></div>
        </div>';
    } elseif ($user['role'] === 'author') {
        $myArticles = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 1", [$user['id']]);
        $myDrafts = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 0", [$user['id']]);
        $myPending = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE author_id = ? AND status = 2", [$user['id']]);
        $content .= '<div class="stats-grid">
            <div class="stat-card primary"><span class="number">' . ($myArticles['count'] ?? 0) . '</span><span class="label">我的已发布</span></div>
            <div class="stat-card"><span class="number">' . ($myDrafts['count'] ?? 0) . '</span><span class="label">我的草稿</span></div>
            <div class="stat-card"><span class="number">' . ($myPending['count'] ?? 0) . '</span><span class="label">我的待审</span></div>
        </div>';
    } else {
        $content .= '<div class="alert alert-warning">您的账号当前为订阅者角色，请联系管理员获取更多权限。</div>';
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
        <span class="name">个人资料</span>
    </a>';
    $content .= '</div>';
    return $this->renderAdminLayout('仪表盘', $content);
}
public function articles(): string
{
    $this->checkAuth();
    $user = $this->getCurrentUser();
    if ($user['role'] === 'subscriber') {
        return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限查看文章。</div>');
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
            <span class="page-title" style="border-bottom:none;padding-bottom:0;margin-bottom:0;">文章管理</span>
        </div>
        <div class="col-xs-12 col-sm-6 text-right">
            <div class="btn-group btn-group-sm">
                <a href="/admin/articles" class="btn btn-sm ' . ($statusFilter === '' ? 'btn-primary' : 'btn-default') . '">全部</a>
                <a href="/admin/articles?status=published" class="btn btn-sm ' . ($statusFilter === 'published' ? 'btn-primary' : 'btn-default') . '">已发布</a>
                <a href="/admin/articles?status=draft" class="btn btn-sm ' . ($statusFilter === 'draft' ? 'btn-primary' : 'btn-default') . '">草稿</a>
                <a href="/admin/articles?status=pending" class="btn btn-sm ' . ($statusFilter === 'pending' ? 'btn-primary' : 'btn-default') . '">待审核</a>
            </div>
            <a href="/admin/article/create" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span> 新建</a>
        </div>
    </div>';
    $content = $filterHtml . '
    <div class="table-wrap">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>分类</th>
                <th>作者</th>
                <th>状态</th>
                <th>推荐</th>
                <th>日期</th>
                <th width="180">操作</th>
            </tr>
        </thead>
        <tbody>';
    if (empty($articles)) {
        $content .= '<tr><td colspan="8" style="text-align:center;padding:24px;color:#999;">暂无文章</td></tr>';
    } else {
        $statusMap = [0 => '草稿', 1 => '已发布', 2 => '待审核'];
        foreach ($articles as $article) {
            $status = $statusMap[$article['status']] ?? '未知';
            $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? '未知';
            $isOwn = ($user['id'] === $article['author_id']);
            $canEdit = in_array($user['role'], ['admin', 'editor']) || $isOwn;
            $statusClass = strtolower($status);
            $recommendBadge = $article['is_recommend'] ? '<span class="label label-success">推荐</span>' : '<span class="text-muted">—</span>';
            $topBadge = $article['is_top'] ? '<span class="label label-primary">置顶</span>' : '';
            $content .= '<tr>
                <td>' . $article['id'] . '</td>
                <td>' . htmlspecialchars($article['title']) . ' ' . $topBadge . '</td>
                <td>' . htmlspecialchars($article['category_name'] ?? '未分类') . '</td>
                <td>' . htmlspecialchars($authorDisplay) . ($isOwn ? ' <span class="text-muted small">(您)</span>' : '') . '</td>
                <td><span class="status-badge ' . $statusClass . '">' . $status . '</span></td>
                <td>' . $recommendBadge . '</td>
                <td>' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                <td>
                    <div class="btn-group btn-group-xs">
                        <a href="/article/' . $article['id'] . '" class="btn btn-default">查看</a>
                        ' . ($canEdit ? '<a href="/admin/article/edit/' . $article['id'] . '" class="btn btn-primary">编辑</a>' : '<span class="btn btn-default disabled">编辑</span>') . '
                        ' . ($canEdit ? '<a href="/admin/article/delete/' . $article['id'] . '" class="btn btn-danger" onclick="return confirm(\'确定删除该文章吗？\')">删除</a>' : '<span class="btn btn-default disabled">删除</span>') . '
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
    return $this->renderAdminLayout('文章管理', $content);
}
    public function createArticle(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限创建文章。</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('错误', '<div class="alert alert-danger">CSRF 令牌验证失败</div>');
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
                return $this->renderArticleForm('标题和内容不能为空', []);
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
        $statusOptions = [0 => '草稿', 1 => '已发布', 2 => '待审核'];
        $content = $article ? ($article['content'] ?? '') : '';
        $title = $article ? ($article['title'] ?? '') : '';
        $excerpt = $article ? ($article['excerpt'] ?? '') : '';
        $isAuthor = ($user['role'] === 'author');
        $html = '<div class="page-title">' . ($isEdit ? '编辑文章' : '新建文章') . '</div>';
        if ($error) {
            $html .= '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>';
        }
        $html .= '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="title">标题 *</label>
                    <input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($title) . '" required>
                </div>
                <div class="form-group">
                    <label for="excerpt">摘要</label>
                    <input type="text" class="form-control" id="excerpt" name="excerpt" value="' . htmlspecialchars($excerpt) . '">
                </div>
                <div class="form-group">
                    <label for="category_id">分类</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">无分类</option>';
        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }
        $html .= '</select></div>
                <div class="form-group">
                    <label for="editor">内容 *</label>
                    <div style="margin-bottom:8px;">
                        <span class="text-muted small">支持 HTML 标签：h1, p, a, img, ul, ol, table, pre, code</span>
                        <button type="button" id="previewBtn" class="btn btn-default btn-sm" style="margin-left:8px;">预览</button>
                    </div>
                    <textarea class="form-control" id="editor" name="content" rows="12" required>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</textarea>
                    <div id="preview" style="display:none;border:1px solid #ddd;padding:16px;margin-top:8px;background:#fff;max-height:400px;overflow-y:auto;"></div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="status">状态</label>
                            <select class="form-control" id="status" name="status" ' . ($isAuthor && !$isEdit ? 'disabled' : '') . '>';
        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $disabled = ($isAuthor && !$isEdit && $k !== 2) ? 'disabled' : '';
            $html .= '<option value="' . $k . '" ' . $selected . ' ' . $disabled . '>' . $v . '</option>';
        }
        $html .= '</select>';
        if ($isAuthor && !$isEdit) {
            $html .= '<span class="help-block">作者角色：新建文章自动设为待审核状态。</span>';
        }
        if ($isEdit && $isAuthor && $article && $article['status'] == 2) {
            $html .= '<span class="help-block">此文章正在审核中，您可以编辑但无法发布。</span>';
        }
        $html .= '</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group" style="padding-top:25px;">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> 置顶</label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend'] ? 'checked' : '') . '> 推荐</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '发布') . '</button>
                    <a href="/admin/articles" class="btn btn-default">取消</a>
                </div>
            </form>
        </div>';
        return $this->renderAdminLayout($isEdit ? '编辑文章' : '新建文章', $html);
    }
    public function editArticle(string $id): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] === 'subscriber') {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限编辑文章。</div>');
        }
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        if (!$article) {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">文章不存在。</div>');
        }
        if ($user['role'] === 'author' && $user['id'] !== $article['author_id']) {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限编辑此文章。</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('错误', '<div class="alert alert-danger">CSRF 令牌验证失败</div>');
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
                return $this->renderArticleForm('标题和内容不能为空', [], $article);
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
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限管理分类。</div>');
        }
        $categoryModel = new Category();
        $categories = $categoryModel->findAll(true);
        $content = '<div class="row" style="margin-bottom:16px;">
            <div class="col-xs-12 col-sm-6">
                <span class="page-title" style="border-bottom:none;padding-bottom:0;margin-bottom:0;">分类管理</span>
            </div>
            <div class="col-xs-12 col-sm-6 text-right">
                <a href="/admin/category/create" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-plus"></span> 新建</a>
            </div>
        </div>
        <div class="table-wrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>别名</th>
                    <th>描述</th>
                    <th width="150">操作</th>
                </tr>
            </thead>
            <tbody>';
        if (empty($categories)) {
            $content .= '<tr><td colspan="5" style="text-align:center;padding:24px;color:#999;">暂无分类</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $content .= '<tr>
                    <td>' . $cat['id'] . '</td>
                    <td>' . htmlspecialchars($cat['name']) . '</td>
                    <td>' . htmlspecialchars($cat['slug']) . '</td>
                    <td>' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td>
                        <div class="btn-group btn-group-xs">
                            <a href="/admin/category/edit/' . $cat['id'] . '" class="btn btn-primary">编辑</a>
                            <a href="/admin/category/delete/' . $cat['id'] . '" class="btn btn-danger" onclick="return confirm(\'确定删除该分类吗？\')">删除</a>
                        </div>
                    </td>
                </tr>';
            }
        }
        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('分类管理', $content);
    }
    public function createCategory(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限创建分类。</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('错误', '<div class="alert alert-danger">CSRF 令牌验证失败</div>');
            }
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');
            if (empty($name)) {
                return $this->renderAdminLayout('新建分类', '<div class="alert alert-danger">分类名称不能为空</div>' . $this->getCategoryForm());
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
        return $this->renderAdminLayout('新建分类', $this->getCategoryForm());
    }
    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '<div class="page-title">' . ($isEdit ? '编辑分类' : '新建分类') . '</div>
        <div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="name">名称 *</label>
                    <input type="text" class="form-control" id="name" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" required>
                </div>
                <div class="form-group">
                    <label for="slug">别名</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '">
                    <span class="help-block">留空则自动生成</span>
                </div>
                <div class="form-group">
                    <label for="description">描述</label>
                    <textarea class="form-control" id="description" name="description" rows="3">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">' . ($isEdit ? '更新' : '创建') . '</button>
                    <a href="/admin/categories" class="btn btn-default">取消</a>
                </div>
            </form>
        </div>';
    }
    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['editor', 'admin'])) {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限编辑分类。</div>');
        }
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);
        if (!$category) {
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">分类不存在。</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('错误', '<div class="alert alert-danger">CSRF 令牌验证失败</div>');
            }
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];
            if (empty($data['name'])) {
                return $this->renderAdminLayout('编辑分类', '<div class="alert alert-danger">分类名称不能为空</div>' . $this->getCategoryForm($category));
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
        return $this->renderAdminLayout('编辑分类', $this->getCategoryForm($category));
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
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限查看用户。</div>');
        }
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");
        $content = '<div class="page-title">用户管理</div>
        <div class="table-wrap">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>昵称</th>
                    <th>角色</th>
                    <th>状态</th>
                    <th>注册时间</th>
                </tr>
            </thead>
            <tbody>';
        if (empty($users)) {
            $content .= '<tr><td colspan="7" style="text-align:center;padding:24px;color:#999;">暂无用户</td></tr>';
        } else {
            $roleMap = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'subscriber' => '订阅者'];
            $statusMap = [0 => '已禁用', 1 => '启用', 2 => '待验证'];
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
                    $content .= '<form method="POST" action="/admin/user/role" style="display:inline;" onsubmit="return confirm(\'确定要修改用户 ' . htmlspecialchars($u['username']) . ' 的角色吗？\')">
                        ' . $this->csrfField() . '
                        <input type="hidden" name="user_id" value="' . $u['id'] . '">
                        <select name="role" class="form-control input-sm" style="display:inline-block;width:auto;" onchange="this.form.submit()">
                            <option value="subscriber"' . ($u['role'] === 'subscriber' ? ' selected' : '') . '>订阅者</option>
                            <option value="author"' . ($u['role'] === 'author' ? ' selected' : '') . '>作者</option>
                            <option value="editor"' . ($u['role'] === 'editor' ? ' selected' : '') . '>编辑</option>
                            <option value="admin"' . ($u['role'] === 'admin' ? ' selected' : '') . '>管理员</option>
                        </select>
                    </form>';
                } else {
                    $content .= $role . ($u['id'] == $user['id'] ? ' <span class="text-muted small">(您)</span>' : '');
                }
                $content .= '</td>
                    <td>' . $status . '</td>
                    <td>' . date('Y-m-d', strtotime($u['created_at'])) . '</td>
                </tr>';
            }
        }
        $content .= '</tbody></table></div>';
        return $this->renderAdminLayout('用户管理', $content);
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
            return $this->renderAdminLayout('错误', '<div class="alert alert-danger">您没有权限访问系统设置。</div>');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->verifyCsrf()) {
                return $this->renderAdminLayout('错误', '<div class="alert alert-danger">CSRF 令牌验证失败</div>');
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
        $siteName = $settingModel->get('site_name') ?? '我的 CMS';
        $siteDesc = $settingModel->get('site_description') ?? '基于 PHP + MySQL + Redis 的轻量级 CMS';
        $siteFooter = $settingModel->get('site_footer') ?? '保留所有权利。';
        $perPage = $settingModel->get('per_page') ?? 10;
        $content = '<div class="page-title">系统设置</div>';
        if (isset($success)) {
            $content .= '<div class="alert alert-success">设置已保存！</div>';
        }
        $content .= '<div class="admin-form">
            <form method="POST">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="site_name">网站名称</label>
                    <input type="text" class="form-control" id="site_name" name="site_name" value="' . htmlspecialchars($siteName) . '">
                </div>
                <div class="form-group">
                    <label for="site_description">网站描述</label>
                    <input type="text" class="form-control" id="site_description" name="site_description" value="' . htmlspecialchars($siteDesc) . '">
                </div>
                <div class="form-group">
                    <label for="site_footer">页脚文字 <span class="text-muted small">（"Powered by Lighttp" 会自动追加）</span></label>
                    <input type="text" class="form-control" id="site_footer" name="site_footer" value="' . htmlspecialchars($siteFooter) . '">
                    <span class="help-block">此文字显示在 "Powered by Lighttp" 之前。</span>
                </div>
                <div class="form-group">
                    <label for="per_page">每页文章数</label>
                    <input type="number" class="form-control" id="per_page" name="per_page" value="' . htmlspecialchars($perPage) . '">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>';
        return $this->renderAdminLayout('系统设置', $content);
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
        $content = '<div class="page-title">个人资料</div>
        <div class="panel panel-default">
            <div class="panel-body">
                <p><strong>用户名：</strong>' . htmlspecialchars($user['username']) . '</p>
                <p><strong>昵称：</strong>' . htmlspecialchars($user['nickname'] ?? '-') . '</p>
                <p><strong>角色：</strong>' . htmlspecialchars($user['role']) . '</p>
                <p><strong>注册时间：</strong>' . date('Y-m-d', strtotime($user['created_at'])) . '</p>
                <p><strong>最后登录：</strong>' . ($user['last_login_time'] ? date('Y-m-d H:i', strtotime($user['last_login_time'])) : '从未登录') . '</p>
            </div>
        </div>
        <h3 style="margin-top:30px;border-bottom:1px solid #e7e7e7;padding-bottom:8px;">更新邮箱与昵称</h3>
        <div class="admin-form">
            <form method="POST" action="/admin/profile/update" id="profileForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="email">邮箱 *</label>
                    <input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '" required>
                </div>
                <div class="form-group">
                    <label for="nickname">昵称</label>
                    <input type="text" class="form-control" id="nickname" name="nickname" value="' . htmlspecialchars($user['nickname'] ?? '') . '" placeholder="显示名称（可选）">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">更新资料</button>
                </div>
            </form>
        </div>
        <h3 style="margin-top:30px;border-bottom:1px solid #e7e7e7;padding-bottom:8px;">修改密码</h3>
        <div class="admin-form">
            <form method="POST" action="/admin/profile/password" id="passwordForm">
                ' . $this->csrfField() . '
                <div class="form-group">
                    <label for="current_password">当前密码 *</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">新密码（至少 6 位）*</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码 *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">修改密码</button>
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
        return $this->renderAdminLayout('个人资料', $content);
    }
    public function updateProfile(): void
    {
        $this->checkAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/profile');
            return;
        }
        if (!$this->verifyCsrf()) {
            $_SESSION['profile_message'] = 'CSRF 令牌验证失败';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $user = $this->getCurrentUser();
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['profile_message'] = '邮箱地址无效';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel = new User();
        $existing = $userModel->findByEmail($email);
        if ($existing && $existing['id'] != $user['id']) {
            $_SESSION['profile_message'] = '该邮箱已被其他账号使用';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel->updateProfile($user['id'], $email, $nickname);
        Application::getInstance()->setCurrentUser($userModel->find($user['id']));
        $_SESSION['profile_message'] = '资料更新成功';
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
            $_SESSION['profile_message'] = 'CSRF 令牌验证失败';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $user = $this->getCurrentUser();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['profile_message'] = '所有密码字段均为必填';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        if (strlen($newPassword) < 6) {
            $_SESSION['profile_message'] = '新密码至少 6 位';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        if ($newPassword !== $confirmPassword) {
            $_SESSION['profile_message'] = '两次输入的新密码不一致';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel = new User();
        $dbUser = $userModel->find($user['id']);
        if (!$userModel->verifyPassword($currentPassword, $dbUser['password'])) {
            $_SESSION['profile_message'] = '当前密码错误';
            $_SESSION['profile_message_type'] = 'error';
            $this->redirect('/admin/profile');
            return;
        }
        $userModel->updatePassword($user['id'], $newPassword);
        $_SESSION['profile_message'] = '密码修改成功';
        $_SESSION['profile_message_type'] = 'success';
        $this->redirect('/admin/profile');
    }
}