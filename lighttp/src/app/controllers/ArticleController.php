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

        // 检查文章是否存在且包含必要字段
        if (!$article || !isset($article['title']) || empty($article['title'])) {
            ob_start();
            ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - 文章不存在</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header_bootstrap.php'; ?>
    <div class="container" style="padding-top:80px;text-align:center;">
        <h1>404</h1>
        <p>文章不存在或已被删除</p>
        <a href="/" class="btn btn-default">返回首页</a>
    </div>
    <script src="/npm/jquery@1.12.4/dist/jquery.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
<?php
            return ob_get_clean();
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? '未知作者';
        $articleTitle = $article['title'] ?? '';
        $articleContent = $article['content'] ?? '';
        $articleExcerpt = $article['excerpt'] ?? '';
        $articleViews = $article['views'] ?? 0;
        $articleCategoryName = $article['category_name'] ?? '';
        $articleCategorySlug = $article['category_slug'] ?? '';
        $articleIsTop = $article['is_top'] ?? 0;
        $articleIsRecommend = $article['is_recommend'] ?? 0;
        $articleMetaTitle = $article['meta_title'] ?? '';
        $articleMetaDescription = $article['meta_description'] ?? '';
        $articlePublishedAt = $article['published_at'] ?? $article['created_at'] ?? '';

        ob_start();
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($articleMetaDescription ?: $articleExcerpt); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($authorDisplay); ?>">
    <title><?php echo htmlspecialchars($articleMetaTitle ?: $articleTitle); ?> · <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="/css/lighttp-bootstrap.css">
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
    <style>
        body { padding-top: 70px; }
        .article-content { font-size: 16px; line-height: 1.8; }
        .article-content img { max-width: 100%; height: auto; }
        .article-content pre { background: #f5f5f5; padding: 15px; border: none; border-radius: 4px; overflow-x: auto; }
        .article-content blockquote { border-left: 4px solid #337ab7; padding-left: 15px; color: #666; }
        .article-content table { width: 100%; border-collapse: collapse; }
        .article-content table th,
        .article-content table td { border: 1px solid #ddd; padding: 8px; }
        .article-content table th { background: #f5f5f5; }
        .meta { color: #999; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .meta span { margin-right: 15px; }
        .footer-actions { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; }
        .footer-actions .btn { margin-right: 8px; }
    </style>
</head>
<body>
    <?php include APP_PATH . '/views/partials/header_bootstrap.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <article>
                    <h1><?php echo htmlspecialchars($articleTitle); ?></h1>
                    <div class="meta">
                        <span>📅 <?php echo date('Y-m-d H:i', strtotime($articlePublishedAt ?: 'now')); ?></span>
                        <?php if (!empty($articleCategoryName)): ?>
                        <span>📂 <a href="/category/<?php echo htmlspecialchars($articleCategorySlug); ?>"><?php echo htmlspecialchars($articleCategoryName); ?></a></span>
                        <?php endif; ?>
                        <span>👤 <?php echo htmlspecialchars($authorDisplay); ?></span>
                        <span>👁️ <?php echo $articleViews; ?> views</span>
                        <?php if ($articleIsTop): ?>
                        <span class="label label-primary">置顶</span>
                        <?php endif; ?>
                        <?php if ($articleIsRecommend): ?>
                        <span class="label label-success">推荐</span>
                        <?php endif; ?>
                        <span class="pull-right"><?php echo round(mb_strlen(strip_tags($articleContent)) / 200) . ' min read'; ?></span>
                    </div>
                    <?php if (!empty($articleExcerpt)): ?>
                    <div class="well well-sm">
                        <?php echo htmlspecialchars($articleExcerpt); ?>
                    </div>
                    <?php endif; ?>
                    <div class="article-content">
                        <?php echo $articleContent; ?>
                    </div>
                    <div class="footer-actions">
                        <a href="/" class="btn btn-default">← 返回首页</a>
                        <?php if ($this->isLoggedIn()): ?>
                        <a href="/admin/article/edit/<?php echo $article['id']; ?>" class="btn btn-primary">✏️ 编辑</a>
                        <a href="/admin/articles" class="btn btn-default">📋 管理</a>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        </div>
        <hr>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> · Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></p>
        </footer>
    </div>

    <script src="/npm/jquery@1.12.4/dist/jquery.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}