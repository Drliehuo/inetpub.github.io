<?php
declare(strict_types=1);
namespace App\controllers;
use App\core\Application;
use App\models\Article;
use App\models\Category;
use App\models\Setting;
class SearchController extends BaseController
{
    public function index(): string
    {
        $keyword = trim($_GET['q'] ?? '');
        if (empty($keyword)) {
            $this->redirect('/');
        }
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $settingModel = new Setting();
        $perPage = (int)($settingModel->get('per_page') ?? 10);
        $cache = Application::getInstance()->getCache();
        $cacheKey = $cache ? $cache->key('search', md5($keyword . '_page_' . $page)) : 'cms:search:' . md5($keyword . '_page_' . $page);
        $cachedBody = $cache && $cache->hasWithPrefix($cacheKey) ? $cache->getWithPrefix($cacheKey) : null;
        $articleModel = new Article();
        $categoryModel = new Category();
        $result = $articleModel->search($keyword, $page, $perPage);
        $categories = $categoryModel->findAll();
        $siteName = $settingModel->get('site_name') ?? 'Lighttp';
        $siteDesc = $settingModel->get('site_description') ?? 'Modern content management system';
        $data = [
            'articles' => $result['data'],
            'categories' => $categories,
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages'],
            'site_name' => $siteName,
            'site_description' => $siteDesc,
            'keyword' => $keyword
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
    <meta name="description" content="搜索 <?php echo htmlspecialchars($data['keyword']); ?> - <?php echo htmlspecialchars($data['site_name']); ?>">
    <meta name="robots" content="noindex, follow">
    <title>搜索: <?php echo htmlspecialchars($data['keyword']); ?> · <?php echo htmlspecialchars($data['site_name']); ?></title>
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
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/"><?php echo htmlspecialchars($data['site_name']); ?></a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li><a href="/">首页</a></li>
                <?php foreach ($data['categories'] as $cat): ?>
                <li><a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <form class="navbar-form navbar-right" role="search" method="GET" action="/search">
                <div class="form-group">
                    <input type="text" class="form-control" name="q" placeholder="搜索文章..." value="<?php echo htmlspecialchars($data['keyword'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-default">搜索</button>
            </form>
            <ul class="nav navbar-nav navbar-right">
                <?php if ($this->isLoggedIn()): ?>
                <li><a href="/admin">管理</a></li>
                <li><a href="/admin/profile">个人</a></li>
                <li><a href="/logout">退出</a></li>
                <?php else: ?>
                <li><a href="/login">登录</a></li>
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
                <button type="button" class="btn btn-primary btn-xs" data-toggle="offcanvas">菜单</button>
            </p>
            <div class="jumbotron">
                <h1>搜索结果</h1>
                <p>找到 <strong><?php echo $data['total']; ?></strong> 篇与 <strong>"<?php echo htmlspecialchars($data['keyword']); ?>"</strong> 相关的文章</p>
                <?php if ($data['total'] > 0): ?>
                <p class="text-muted small" style="margin-top:12px;font-size:14px;">
                    <span class="glyphicon glyphicon-file"></span> 共 <?php echo $data['total']; ?> 篇文章
                    &nbsp;·&nbsp; 第 <?php echo $data['page']; ?> 页 / 共 <?php echo $data['totalPages']; ?> 页
                </p>
                <?php endif; ?>
            </div>
            <?php if (empty($data['articles'])): ?>
            <div style="text-align:center;padding:60px 0;color:#999;">
                <p style="font-size:18px;">😅 没有找到与 <strong>"<?php echo htmlspecialchars($data['keyword']); ?>"</strong> 相关的文章</p>
                <p style="font-size:14px;">建议：检查关键词是否有误，或尝试使用更通用的关键词</p>
                <a href="/" class="btn btn-default" style="margin-top:16px;">返回首页</a>
            </div>
            <?php else: ?>
            <div class="row article-grid">
                <?php foreach ($data['articles'] as $article):
                    $title = htmlspecialchars($article['title']);
                    $title = $this->highlightKeyword($title, $data['keyword']);
                    if (mb_strlen(strip_tags($title)) > 30) {
                        $title = mb_substr(strip_tags($title), 0, 30) . '...';
                    }
                    $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                    $excerpt = strip_tags($excerpt);
                    $excerpt = $this->highlightKeyword($excerpt, $data['keyword']);
                    if (mb_strlen(strip_tags($excerpt)) > 60) {
                        $excerpt = mb_substr(strip_tags($excerpt), 0, 60) . '...';
                    }
                    $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
                ?>
                <div class="col-xs-6 col-lg-4">
                    <div class="article-card">
                        <h2><a href="/article/<?php echo $article['id']; ?>"><?php echo $title; ?></a></h2>
                        <p class="text-muted small meta">
                            <?php echo date('Y-m-d', strtotime($article['created_at'])); ?>
                            · <?php echo htmlspecialchars($article['category_name'] ?? '未分类'); ?>
                            · <?php echo htmlspecialchars($authorDisplay); ?>
                            · <?php echo $article['views'] ?? 0; ?> views
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
            $baseUrl = '/search?q=' . urlencode($data['keyword']) . '&';
            $total = $data['total'];
            $perPage = $data['perPage'];
            $currentPage = $data['page'];
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
    private function highlightKeyword(string $text, string $keyword): string
    {
        if (empty($keyword) || empty($text)) {
            return htmlspecialchars($text);
        }
        $keyword = preg_quote($keyword, '/');
        $text = htmlspecialchars($text);
        return preg_replace('/(' . $keyword . ')/iu', '<span style="background:#ffeb3b;padding:1px 4px;border-radius:2px;">$1</span>', $text);
    }
}