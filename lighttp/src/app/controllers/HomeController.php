<?php declare(strict_types=1);
namespace App\controllers;
use App\core\Application;
use App\models\Article;
use App\models\Category;
use App\models\Setting;
class HomeController extends BaseController
{
    public function index(): string
    {
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $settingModel = new Setting();
        $perPage = (int)($settingModel->get('per_page') ?? 10);
        $cache = Application::getInstance()->getCache();
        $cacheKey = 'home_page_body_' . $page;
        $cachedBody = $cache && $cache->has($cacheKey) ? $cache->get($cacheKey) : null;
        $articleModel = new Article();
        $categoryModel = new Category();
        $result = $articleModel->getPaginated(null, $page, $perPage);
        $categories = $categoryModel->findAll();
        $siteName = $settingModel->get('site_name') ?? 'My CMS';
        $siteDesc = $settingModel->get('site_description') ?? 'A lightweight CMS built with PHP + MySQL + Redis';
        $siteFooter = $settingModel->get('site_footer') ?? 'All rights reserved.';
        $data = [
            'articles' => $result['data'],
            'categories' => $categories,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages'],
            'site_name' => $siteName,
            'site_description' => $siteDesc,
            'site_footer' => $siteFooter
        ];
        if ($cachedBody === null) {
            $cachedBody = $this->renderBody($data);
            if ($cache) {
                $cache->set($cacheKey, $cachedBody, 300);
            }
        }
        $head = $this->renderHead($data);
        $header = $this->renderHeader();
        $footer = $this->renderFooter($data);
        return $head . $header . $cachedBody . $footer;
    }
    private function renderHead(array $data): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['site_name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($data['site_description'] ?? ''); ?>">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<?php
        return ob_get_clean();
    }
    private function renderHeader(): string
    {
        ob_start();
        include APP_PATH . '/views/partials/header.php';
        return ob_get_clean();
    }
    private function renderBody(array $data): string
    {
        ob_start();
        ?>
<main class="container" style="padding-top:32px;">
    <h2>Latest Articles</h2>
    <?php if (empty($data['articles'])): ?>
        <p>No articles yet. <a href="/admin/article/create">Create your first article</a></p>
    <?php else: ?>
        <?php foreach ($data['articles'] as $article):
            $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
            $excerpt = mb_substr(strip_tags($excerpt), 0, 150) . '...';
        ?>
        <div class="article-card">
            <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h2>
            <div class="meta">
                <span><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span>
                <span><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></span>
                <span><?php echo $article['views'] ?? 0; ?> views</span>
                <?php if ($article['is_top']): ?><span>[Top]</span><?php endif; ?>
                <?php if ($article['is_recommend']): ?><span>[Recommend]</span><?php endif; ?>
            </div>
            <p class="excerpt"><?php echo $excerpt; ?></p>
        </div>
        <?php endforeach; ?>
        <?php
        $baseUrl = '?';
        $total = $data['total'];
        $perPage = $data['perPage'];
        $currentPage = $data['page'];
        include APP_PATH . '/views/partials/pagination.php';
        ?>
    <?php endif; ?>
</main>
<?php
        return ob_get_clean();
    }
    private function renderFooter(array $data): string
    {
        ob_start();
        ?>
<footer class="site-footer">
    <div class="container">
        <span class="footer-brand"><?php echo htmlspecialchars($data['site_name']); ?></span>
        <span class="footer-copy"><?php echo htmlspecialchars($data['site_footer'] ?? ''); ?></span>
        <span class="footer-dev">Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></span>
    </div>
</footer>
<script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}