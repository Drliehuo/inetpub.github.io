<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;

class ArticleController extends BaseController
{
    public function show(string $id): string
    {
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);

        if (!$article) {
            ob_start();
            ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head>
<body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Article not found.</p><a href="/">Back to home</a></div></body>
</html>
<?php
            return ob_get_clean();
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['meta_title'] ?? $article['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? ''); ?>">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;max-width:800px;">
        <article>
            <h1><?php echo htmlspecialchars($article['title']); ?></h1>
            <div class="meta" style="color:var(--gray-500);font-size:0.875rem;margin-bottom:24px;border-bottom:1px solid var(--gray-200);padding-bottom:16px;">
                <span><?php echo date('Y-m-d H:i', strtotime($article['published_at'] ?? $article['created_at'])); ?></span>
                <span><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></span>
                <span><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></span>
                <span><?php echo $article['views'] ?? 0; ?> views</span>
            </div>
            <div class="article-content"><?php echo $article['content'] ?? ''; ?></div>
            <div style="margin-top:32px;padding-top:16px;border-top:2px solid var(--gray-200);">
                <a href="/" class="btn btn-sm">Back to home</a>
                <a href="/admin/article/edit/<?php echo $article['id']; ?>" class="btn btn-sm">Edit</a>
                <a href="/admin/articles" class="btn btn-sm">Manage</a>
            </div>
        </article>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
