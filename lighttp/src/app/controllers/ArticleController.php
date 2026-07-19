<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;
use App\models\Setting;

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
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"><link rel="stylesheet" href="/css/admin.css"></head>
<body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Article not found.</p><a href="/" class="btn">Back to home</a></div></body>
</html>
<?php
            return ob_get_clean();
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';

        // 优先显示昵称，无昵称则显示用户名
        $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
        $authorInitial = $authorDisplay ? strtoupper(mb_substr($authorDisplay, 0, 1)) : '?';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['meta_title'] ?? $article['title']); ?> · <?php echo htmlspecialchars($siteName); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? ''); ?>">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:20px;">
        <article class="ms-article-detail">
            <header class="header">
                <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                <div class="meta">
                    <span class="item"><span class="icon">📅</span> <?php echo date('F j, Y', strtotime($article['published_at'] ?? $article['created_at'])); ?></span>
                    <?php if (!empty($article['category_name'])): ?>
                    <span class="item"><span class="icon">📂</span> <a href="/category/<?php echo htmlspecialchars($article['category_slug'] ?? ''); ?>" class="category"><?php echo htmlspecialchars($article['category_name']); ?></a></span>
                    <?php endif; ?>
                    <span class="item"><span class="icon">👤</span> <?php echo htmlspecialchars($authorDisplay); ?></span>
                    <span class="item"><span class="icon">👁️</span> <?php echo $article['views'] ?? 0; ?> views</span>
                    <?php if ($article['is_top'] ?? 0): ?>
                    <span class="item" style="color:var(--ms-blue);font-weight:600;">⭐ Top</span>
                    <?php endif; ?>
                    <?php if ($article['is_recommend'] ?? 0): ?>
                    <span class="item" style="color:#2da44e;font-weight:600;">🔥 Recommend</span>
                    <?php endif; ?>
                    <span class="item" style="margin-left:auto;font-size:0.75rem;color:var(--ms-gray-500);"><?php echo round(mb_strlen(strip_tags($article['content'] ?? '')) / 200) . ' min read'; ?></span>
                </div>
            </header>

            <?php if (!empty($article['excerpt'])): ?>
            <div style="padding:12px 16px;background:var(--ms-gray-50);border:1px solid var(--ms-gray-200);border-radius:var(--ms-radius);margin-bottom:24px;color:var(--ms-gray-700);font-size:0.95rem;border-left:4px solid var(--ms-blue);">
                <?php echo htmlspecialchars($article['excerpt']); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($authorDisplay)): ?>
            <div class="ms-author">
                <div class="avatar"><?php echo $authorInitial; ?></div>
                <div class="info">
                    <div class="name"><?php echo htmlspecialchars($authorDisplay); ?></div>
                    <p class="bio">Published on <?php echo date('F j, Y', strtotime($article['published_at'] ?? $article['created_at'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="content">
                <?php echo $article['content'] ?? ''; ?>
            </div>

            <div class="footer-actions">
                <a href="/" class="btn">← Back to home</a>
                <?php if ($this->isLoggedIn()): ?>
                <a href="/admin/article/edit/<?php echo $article['id']; ?>" class="btn">✏️ Edit</a>
                <a href="/admin/articles" class="btn">📋 Manage</a>
                <?php endif; ?>
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