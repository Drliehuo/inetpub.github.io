<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;
use App\models\Article;
use App\models\Category;

class HomeController extends BaseController
{
    public function index(): string
    {
        $cache = Application::getInstance()->getCache();
        $cacheKey = 'home_data';
        $data = $cache && $cache->has($cacheKey) ? $cache->get($cacheKey) : null;

        if ($data === null) {
            $articleModel = new Article();
            $categoryModel = new Category();
            $data = [
                'articles' => $articleModel->getLatest(10),
                'categories' => $categoryModel->findAll(),
                'site_name' => 'My CMS',
                'site_description' => 'A lightweight CMS built with PHP + MySQL + Redis'
            ];
            if ($cache) {
                $cache->set($cacheKey, $data, 300);
            }
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['site_name']); ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <div class="admin-bar">
            <a href="/admin/articles">Manage Articles</a>
            <a href="/admin/article/create">New Article</a>
            <a href="/admin/cache/clear">Clear Cache</a>
            <span class="status-badge success">MySQL</span>
            <span class="status-badge success">Redis</span>
        </div>

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
        <?php endif; ?>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
