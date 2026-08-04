<?php
declare(strict_types=1);
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
        $cacheKey = $cache ? $cache->key('home', 'page_' . $page) : 'cms:home:page_' . $page;
        $cachedBody = $cache && $cache->hasWithPrefix($cacheKey) ? $cache->getWithPrefix($cacheKey) : null;
        $articleModel = new Article();
        $categoryModel = new Category();
        $result = $articleModel->getPaginated(null, $page, $perPage);
        $categories = $categoryModel->findAll();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        $siteDesc = $settingModel->get('site_description') ?? 'Modern content management system';
        $siteFooter = $settingModel->get('site_footer') ?? '';
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
                $cache->setWithPrefix($cacheKey, $cachedBody, 300);
            }
        }
        $head = $this->renderHead($data);
        $header = $this->renderHeader($data);
        $footer = $this->renderFooter($data);
        return $head . $header . $cachedBody . $footer;
    }
    private function renderHead(array $data): string
    {
        ob_start();
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($data['site_description'] ?? ''); ?>">
    <meta name="author" content="Lighttp">
    <title><?php echo htmlspecialchars($data['site_name']); ?></title>
    <link rel="stylesheet" href="/css/lighttp-bootstrap.css">
    <link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/examples/offcanvas/offcanvas.css">
</head>
<body>
<?php
        return ob_get_clean();
    }
    private function renderHeader(array $data): string
    {
        ob_start();
        ?>
<nav class="navbar navbar-fixed-top navbar-inverse">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">菜单</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($data['site_name']); ?></a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="/">首页</a></li>
                <?php foreach ($data['categories'] as $cat): ?>
                <li><a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <?php if ($this->isLoggedIn()): ?>
                <li><a href="/admin">Admin</a></li>
                <li><a href="/admin/profile">Profile</a></li>
                <li><a href="/logout">退出</a></li>
                <?php else: ?>
                <li><a href="/login">登陆</a></li>
                <li><a href="/register">注册</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php
        return ob_get_clean();
    }
private function renderBody(array $data): string
{
    ob_start();
    ?>
<div class="container">
    <div class="row row-offcanvas row-offcanvas-right">
        <div class="col-xs-12 col-sm-9">
            <p class="pull-right visible-xs">
                <button type="button" class="btn btn-primary btn-xs" data-toggle="offcanvas">Toggle nav</button>
            </p>
            <div class="jumbotron">
                <h1><?php echo htmlspecialchars($data['site_name']); ?></h1>
                <p><?php echo htmlspecialchars($data['site_description'] ?? 'Modern content management system built for speed, security, and developer happiness.'); ?></p>
                <?php if (!$this->isLoggedIn()): ?>
                <p><a class="btn btn-primary btn-lg" href="/register" role="button">Get Started &raquo;</a></p>
                <?php else: ?>
                <p><a class="btn btn-primary btn-lg" href="/admin" role="button">Dashboard &raquo;</a></p>
                <?php endif; ?>
            </div>
            <div class="row article-grid">
                <?php if (empty($data['articles'])): ?>
                <div class="col-xs-12">
                    <div class="alert alert-info">No articles yet. <a href="/admin/article/create">Create your first article</a></div>
                </div>
                <?php else: ?>
                <?php foreach ($data['articles'] as $article):
                    // 标题截断：最多30个字符
                    $title = htmlspecialchars($article['title']);
                    if (mb_strlen($title) > 30) {
                        $title = mb_substr($title, 0, 30) . '...';
                    }
                    // 描述截断：最多60个字符
                    $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                    $excerpt = strip_tags($excerpt);
                    if (mb_strlen($excerpt) > 60) {
                        $excerpt = mb_substr($excerpt, 0, 60) . '...';
                    }
                    $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
                ?>
                <div class="col-xs-6 col-lg-4">
                    <div class="article-card">
                        <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo $title; ?></a></h2>
                        <p class="text-muted small meta">
                            <?php echo date('Y-m-d', strtotime($article['created_at'])); ?>
                            · <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                            · <?php echo htmlspecialchars($authorDisplay); ?>
                            · <?php echo $article['views'] ?? 0; ?> views
                            <?php if ($article['is_top']): ?><span class="label label-primary">Top</span><?php endif; ?>
                            <?php if ($article['is_recommend']): ?><span class="label label-success">Recommend</span><?php endif; ?>
                        </p>
                        <p class="excerpt"><?php echo $excerpt; ?></p>
                        <p><a class="btn btn-default" href="/article/<?php echo $article['id']; ?>" role="button">View details &raquo;</a></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php
            $baseUrl = '?';
            $total = $data['total'];
            $perPage = $data['perPage'];
            $currentPage = $data['page'];
            include APP_PATH . '/views/partials/pagination.php';
            ?>
        </div>
        <div class="col-xs-6 col-sm-3 sidebar-offcanvas" id="sidebar">
            <div class="list-group">
                <a href="/" class="list-group-item active">All posts</a>
                <?php foreach ($data['categories'] as $cat): ?>
                <a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>" class="list-group-item">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ($this->isLoggedIn()): ?>
            <div class="panel panel-default">
                <div class="panel-heading">Quick Actions</div>
                <div class="list-group">
                    <a href="/admin/article/create" class="list-group-item">+ New article</a>
                    <a href="/admin" class="list-group-item">Dashboard</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <hr>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($data['site_name']); ?> · Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></p>
    </footer>
</div>
<?php
    return ob_get_clean();
}
    private function renderFooter(array $data): string
    {
        ob_start();
        ?>
<script src="/npm/jquery@1.12.4/dist/jquery.min.js"></script>
<script src="/bootstrap/js/bootstrap.min.js"></script>
<script src="/examples/offcanvas/offcanvas.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}