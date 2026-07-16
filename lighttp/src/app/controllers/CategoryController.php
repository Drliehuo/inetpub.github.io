<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Category;
use App\models\Article;

class CategoryController extends BaseController
{
    public function index(string $slug): string
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBySlug($slug);

        if (!$category) {
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Category not found.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        $articleModel = new Article();
        $articles = $articleModel->getByCategory($category['id']);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Category</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <h1><?php echo htmlspecialchars($category['name']); ?></h1>
        <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>

        <?php if (empty($articles)): ?>
            <p>No articles in this category.</p>
        <?php else: ?>
            <div style="margin-top:24px;">
            <?php foreach ($articles as $article): ?>
                <div class="article-card">
                    <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h2>
                    <div class="meta"><span><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span><span><?php echo $article['views'] ?? 0; ?> views</span></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="/" class="btn btn-sm" style="margin-top:16px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
