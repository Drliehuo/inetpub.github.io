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
    <title>404</title>
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
</head>
<body>
    <div class="container" style="padding-top:80px;text-align:center;">
        <h1>404</h1>
        <p>分类不存在</p>
        <a href="/" class="btn btn-default">返回首页</a>
    </div>
</body>
</html>
<?php
            return ob_get_clean();
        }
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $settingModel = new Setting();
        $perPage = (int)($settingModel->get('per_page') ?? 10);
        $articleModel = new Article();
        $result = $articleModel->getByCategoryPaginated($category['id'], $page, $perPage);
        $allCategories = $categoryModel->findAll();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
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
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
    <style>
        body { padding-top: 70px; }
        .category-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .category-header h1 { margin-top: 0; }
        .category-header .text-muted { font-size: 14px; }
        .category-nav { margin-bottom: 20px; }
        .category-nav .btn-group { flex-wrap: wrap; }
        .category-nav .btn { margin-bottom: 4px; }
        .category-nav .btn.active { background-color: #337ab7; color: #fff; border-color: #2e6da4; }
        .category-empty { text-align: center; padding: 60px 0; color: #999; }
        .article-item { padding: 15px 0; border-bottom: 1px solid #f5f5f5; }
        .article-item:last-child { border-bottom: none; }
        .article-item h3 { margin-top: 0; margin-bottom: 4px; }
        .article-item h3 a { color: #333; }
        .article-item h3 a:hover { color: #337ab7; text-decoration: none; }
        .article-item .meta { color: #999; font-size: 13px; }
        .article-item .meta .label { font-size: 10px; }
        .article-item .excerpt { color: #666; margin-top: 4px; }
        footer { margin: 20px 0; color: #999; font-size: 0.85rem; }
        footer a { color: #337ab7; }
    </style>
</head>
<body>
    <?php include APP_PATH . '/views/partials/header_bootstrap.php'; ?>

    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <!-- 分类头部 -->
                <div class="category-header">
                    <h1><?php echo htmlspecialchars($category['name']); ?></h1>
                    <?php if (!empty($category['description'])): ?>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                    <?php endif; ?>
                    <span class="text-muted"><?php echo $result['total']; ?> 篇文章</span>
                    <?php if ($result['total'] > 0): ?>
                    <span class="text-muted">· 第 <?php echo $page; ?> 页 / 共 <?php echo $result['totalPages']; ?> 页</span>
                    <?php endif; ?>
                </div>

                <!-- 分类导航 -->
                <?php if (!empty($allCategories) && count($allCategories) > 1): ?>
                <div class="category-nav">
                    <div class="btn-group btn-group-sm" role="group">
                        <?php foreach ($allCategories as $cat): ?>
                            <?php if ($cat['id'] == $category['id']): ?>
                            <span class="btn btn-primary active"><?php echo htmlspecialchars($cat['name']); ?></span>
                            <?php else: ?>
                            <a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>" class="btn btn-default"><?php echo htmlspecialchars($cat['name']); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 文章列表 -->
                <?php if (empty($result['data'])): ?>
                <div class="category-empty">
                    <p>该分类下暂无文章</p>
                    <a href="/" class="btn btn-default">浏览其他分类</a>
                </div>
                <?php else: ?>
                <div class="article-list">
                    <?php foreach ($result['data'] as $article): ?>
                    <div class="article-item">
                        <h3><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h3>
                        <div class="meta">
                            <span><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></span>
                            <span>· <?php echo $article['views'] ?? 0; ?> 浏览</span>
                            <?php if ($article['is_top']): ?>
                            <span class="label label-primary">置顶</span>
                            <?php endif; ?>
                            <?php if ($article['is_recommend']): ?>
                            <span class="label label-success">推荐</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($article['excerpt'])): ?>
                        <p class="excerpt"><?php echo htmlspecialchars(mb_substr($article['excerpt'], 0, 140) . '...'); ?></p>
                        <?php endif; ?>
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

                <a href="/" class="btn btn-default">← 返回首页</a>
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