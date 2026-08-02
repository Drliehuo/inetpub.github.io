<?php
$app = \App\core\Application::getInstance();
$isLoggedIn = $app->isLoggedIn();
$settingModel = new \App\models\Setting();
$siteName = $settingModel->get('site_name') ?? 'Lighttp';
$categoryModel = new \App\models\Category();
$categories = $categoryModel->findAll();

// 获取当前 URI，用于判断 active 状态
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentUri = strtok($currentUri, '?');
$currentUri = rtrim($currentUri, '/') ?: '/';

// 判断当前是否在文章页，如果是则获取文章的分类 slug
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
                <?php foreach ($categories as $cat): ?>
                <?php
                $catUri = '/category/' . $cat['slug'];
                // 判断当前分类是否应该高亮：分类页匹配 或 文章页所属分类匹配
                $isActive = ($currentUri === $catUri) || ($articleCategorySlug && $articleCategorySlug === $cat['slug']);
                ?>
                <li<?php echo $isActive ? ' class="active"' : ''; ?>><a href="<?php echo htmlspecialchars($catUri); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
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