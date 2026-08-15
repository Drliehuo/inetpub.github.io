<?php
$app = \App\core\Application::getInstance();
$isLoggedIn = $app->isLoggedIn();
$settingModel = new \App\models\Setting();
$siteName = $settingModel->get('site_name') ?? 'Lighttp';
$categoryModel = new \App\models\Category();
$categories = $categoryModel->findAll();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentUri = strtok($currentUri, '?');
$currentUri = rtrim($currentUri, '/') ?: '/';
$articleCategorySlug = null;
if (preg_match('#^/article/(\d+)$#', $currentUri, $matches)) {
    $articleId = (int)$matches[1];
    $db = $app->getDb();
    if ($db) {
        $article = $db->queryOne(
            "SELECT c.slug FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ?",
            [$articleId]
        );
        if ($article && !empty($article['slug'])) {
            $articleCategorySlug = $article['slug'];
        }
    }
}
// 获取搜索关键词（用于搜索框回显）
$searchKeyword = $_GET['q'] ?? '';
?>
<nav class="navbar navbar-fixed-top navbar-inverse">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($siteName); ?></a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li<?php echo ($currentUri === '/') ? ' class="active"' : ''; ?>><a href="/">首页</a></li>
                <?php foreach ($categories as $cat):
                $catUri = '/category/' . $cat['slug'];
                $isActive = ($currentUri === $catUri) || ($articleCategorySlug && $articleCategorySlug === $cat['slug']);
                ?>
                <li<?php echo $isActive ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($catUri); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <form class="navbar-form navbar-right" role="search" method="GET" action="/search">
                <div class="form-group">
                    <input type="text" class="form-control" name="q" placeholder="搜索文章..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                </div>
                <button type="submit" class="btn btn-default">搜索</button>
            </form>
            <ul class="nav navbar-nav navbar-right">
                <?php if ($isLoggedIn): ?>
                <li<?php echo (strpos($currentUri, '/admin') === 0) ? ' class="active"' : ''; ?>><a href="/admin">管理</a></li>
                <li><a href="/admin/profile">个人</a></li>
                <li><a href="/logout">退出</a></li>
                <?php else: ?>
                <li<?php echo ($currentUri === '/login') ? ' class="active"' : ''; ?>><a href="/login">登录</a></li>
                <li<?php echo ($currentUri === '/register') ? ' class="active"' : ''; ?>><a href="/register">注册</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>