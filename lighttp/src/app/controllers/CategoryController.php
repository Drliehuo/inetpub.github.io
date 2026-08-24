<?php
declare(strict_types=1);
namespace App\controllers;
use App\models\Category;
use App\models\Article;
use App\models\Setting;
class CategoryController extends BaseController
{
    public function index(string $slug): string
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBySlug($slug);
        if (!$category) {
            ob_start();
            ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - 分类不存在</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
    <link rel="stylesheet" href="/css/lighttp-bootstrap.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header_bootstrap.php'; ?>
    <div class="container" style="padding-top:80px;text-align:center;">
        <h1>404</h1>
        <p>分类不存在</p>
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
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        $siteKeywords = $settingModel->get('site_keywords') ?? 'CMS, PHP, MySQL, Redis';
        $categoryKeywords = $category['name'] . ', ' . $siteKeywords;
        $siteDesc = $settingModel->get('site_description') ?? 'Modern content management system';
        $perPage = (int)($settingModel->get('per_page') ?? 10);
        $articleModel = new Article();
        $result = $articleModel->getByCategoryPaginated($category['id'], $page, $perPage);
        $allCategories = $categoryModel->findAll();
        ob_start();
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($category['description'] ?? ''); ?>">
    <title><?php echo htmlspecialchars($category['name']); ?> · <?php echo htmlspecialchars($siteName); ?></title>
    <meta name="keywords" content="<?php echo htmlspecialchars($categoryKeywords); ?>">
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
                    <h1><?php echo htmlspecialchars($category['name']); ?></h1>
                    <p><?php echo htmlspecialchars($category['description'] ?? '暂无描述'); ?></p>
                    <p class="text-muted small" style="margin-top:12px;font-size:14px;">
                        <span class="glyphicon glyphicon-file"></span> 共 <?php echo $result['total']; ?> 篇文章
                        <?php if ($result['total'] > 0): ?>
                        &nbsp;·&nbsp; 第 <?php echo $page; ?> 页 / 共 <?php echo $result['totalPages']; ?> 页
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (empty($result['data'])): ?>
                <div class="category-empty" style="text-align:center;padding:60px 0;color:#999;">
                    <p style="font-size:16px;">该分类下暂无文章</p>
                    <a href="/" class="btn btn-default">浏览其他分类</a>
                </div>
                <?php else: ?>
                <div class="row article-grid">
                    <?php foreach ($result['data'] as $article):
                        $title = htmlspecialchars($article['title']);
                        if (mb_strlen($title) > 30) {
                            $title = mb_substr($title, 0, 30) . '...';
                        }
                        $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                        $excerpt = strip_tags($excerpt);
                        if (mb_strlen($excerpt) > 60) {
                            $excerpt = mb_substr($excerpt, 0, 60) . '...';
                        }
                    ?>
                    <div class="col-xs-6 col-lg-4">
                        <div class="article-card">
                            <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo $title; ?></a></h2>
                            <p class="text-muted small meta">
                                <?php echo date('Y-m-d', strtotime($article['created_at'])); ?>
                                · <?php echo $article['views'] ?? 0; ?> 浏览
                                <?php if ($article['is_top']): ?><span class="label label-primary">置顶</span><?php endif; ?>
                                <?php if ($article['is_recommend']): ?><span class="label label-success">推荐</span><?php endif; ?>
                            </p>
                            <p class="excerpt"><?php echo $excerpt; ?></p>
                            <p><a class="btn btn-default" href="/article/<?php echo $article['id']; ?>" role="button">查看详情 &raquo;</a></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $baseUrl = '/category/' . $slug . '?';
                $total = $result['total'];
                $perPage = $result['perPage'];
                $currentPage = $result['page'];
                include APP_PATH . '/views/partials/pagination.php';
                ?>
                <?php endif; ?>
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