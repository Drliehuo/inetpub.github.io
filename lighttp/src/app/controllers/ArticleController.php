<?php
declare(strict_types=1);
namespace App\controllers;
use App\models\Article;
use App\models\Category;
use App\models\Setting;
class ArticleController extends BaseController
{
    public function show(string $id): string
    {
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
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
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
    <link rel="stylesheet" href="/css/lighttp-bootstrap.css">
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
    <script src="/examples/offcanvas/offcanvas.js"></script>
</body>
</html>
<?php
            return ob_get_clean();
        }
        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        $siteDesc = $settingModel->get('site_description') ?? 'Modern content management system';
        $siteKeywords = $settingModel->get('site_keywords') ?? 'CMS, PHP, MySQL, Redis, 内容管理';
        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
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
        // 获取文章关键词（用户设置 or 自动提取）
        $articleMetaKeywords = $articleModel->getMetaKeywords($article);
        // 如果文章没有独立关键词，使用全局关键词作为补充
        if (empty($articleMetaKeywords)) {
            $articleMetaKeywords = $siteKeywords;
        }
        $articleId = $article['id'] ?? 0;
        ob_start();
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($articleMetaDescription ?: $articleExcerpt); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($articleMetaKeywords); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($authorDisplay); ?>">
    <title><?php echo htmlspecialchars($articleMetaTitle ?: $articleTitle); ?> · <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="stylesheet" href="/css/lighttp-bootstrap.css">
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header_bootstrap.php'; ?>
    <div class="container">
        <div class="row row-offcanvas row-offcanvas-right">
            <div class="col-xs-12 col-sm-9">
                <p class="pull-right visible-xs">
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="offcanvas">菜单</button>
                </p>
                <div class="jumbotron">
                    <h1><?php echo htmlspecialchars($articleTitle); ?></h1>
                    <p><?php echo htmlspecialchars($articleExcerpt ?: '阅读全文'); ?></p>
                    <p class="text-muted small" style="margin-top:12px;font-size:14px;">
                        <span class="glyphicon glyphicon-calendar"></span> <?php echo date('Y-m-d H:i', strtotime($articlePublishedAt ?: 'now')); ?>
                        &nbsp;·&nbsp; <span class="glyphicon glyphicon-folder-open"></span> <a href="/category/<?php echo htmlspecialchars($articleCategorySlug); ?>"><?php echo htmlspecialchars($articleCategoryName ?: '未分类'); ?></a>
                        &nbsp;·&nbsp; <span class="glyphicon glyphicon-user"></span> <?php echo htmlspecialchars($authorDisplay); ?>
                        &nbsp;·&nbsp; <span class="glyphicon glyphicon-eye-open"></span> <?php echo $articleViews; ?> views
                        <?php if ($articleIsTop): ?><span class="label label-primary">置顶</span><?php endif; ?>
                        <?php if ($articleIsRecommend): ?><span class="label label-success">推荐</span><?php endif; ?>
                        <span class="pull-right"><?php echo round(mb_strlen(strip_tags($articleContent)) / 200) . ' min read'; ?></span>
                    </p>
                </div>
                <div class="article-content">
                    <?php echo $articleContent; ?>
                </div>
                <div class="footer-actions" style="margin-top:30px;padding-top:15px;border-top:1px solid #eee;">
                    <a href="/" class="btn btn-default"><span class="glyphicon glyphicon-arrow-left"></span> 返回首页</a>
                    <?php if ($this->isLoggedIn()): ?>
                    <a href="/admin/article/edit/<?php echo $articleId; ?>" class="btn btn-primary"><span class="glyphicon glyphicon-edit"></span> 编辑</a>
                    <a href="/admin/articles" class="btn btn-default"><span class="glyphicon glyphicon-list"></span> 管理</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3 sidebar-offcanvas" id="sidebar">
                <?php include APP_PATH . '/views/partials/sidebar.php'; ?>
            </div>
        </div>
        <hr>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?> · Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></p>
        </footer>
    </div>
    <script src="/npm/jquery@1.12.4/dist/jquery.min.js"></script>
    <script src="/bootstrap/js/bootstrap.min.js"></script>
    <script src="/examples/offcanvas/offcanvas.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}