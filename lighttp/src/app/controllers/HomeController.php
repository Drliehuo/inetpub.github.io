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
        $cacheKey = 'ms_home_' . $page;
        $cachedBody = $cache && $cache->has($cacheKey) ? $cache->get($cacheKey) : null;

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
                $cache->set($cacheKey, $cachedBody, 300);
            }
        }

        $head = $this->renderHead($data);
        $header = $this->renderHeader();
        $footer = $this->renderFooter($data);

        return $head . $header . $cachedBody . $footer;
    }

    private function renderHead(array $data): string
    {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['site_name']); ?> — Modern CMS</title>
    <meta name="description" content="<?php echo htmlspecialchars($data['site_description'] ?? ''); ?>">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<?php
        return ob_get_clean();
    }

    private function renderHeader(): string
    {
        ob_start();
        include APP_PATH . '/views/partials/header.php';
        return ob_get_clean();
    }

    private function renderBody(array $data): string
    {
        ob_start();
        ?>
<section class="ms-hero">
    <div class="container">
        <div class="content">
            <span class="badge">v1.1.0 · Fluent Design</span>
            <h1>Build better<br><span class="highlight">digital experiences</span></h1>
            <p><?php echo htmlspecialchars($data['site_description'] ?? 'Modern content management system built for speed, security, and developer happiness.'); ?></p>
            <div class="actions">
                <a href="/register" class="btn btn-primary">Get Started</a>
                <a href="#features" class="btn">Learn More</a>
            </div>
        </div>
        <div class="stats">
            <div><span class="num">&lt;100ms</span><span class="label">Response</span></div>
            <div><span class="num">12</span><span class="label">Tables</span></div>
            <div><span class="num">100%</span><span class="label">Open Source</span></div>
        </div>
    </div>
</section>

<section class="ms-section" id="features">
    <div class="container">
        <div class="section-header">
            <span class="label">Features</span>
            <h2>Everything you need to succeed</h2>
            <p>Modern tools for modern content creators and developers.</p>
        </div>
        <div class="ms-grid">
            <div class="ms-card"><span class="icon">⚡</span><h3>Lightning Fast</h3><p>Redis-powered caching delivers sub-100ms page loads with 90% fewer database queries.</p></div>
            <div class="ms-card"><span class="icon">🔒</span><h3>Secure by Default</h3><p>PDO prepared statements, bcrypt hashing, CSRF protection — security built in from day one.</p></div>
            <div class="ms-card"><span class="icon">📝</span><h3>Content Management</h3><p>Full article, category, and page management with SEO-friendly URLs and meta tags.</p></div>
            <div class="ms-card"><span class="icon">👥</span><h3>User Management</h3><p>Role-based access control with admin, editor, author, and subscriber roles.</p></div>
            <div class="ms-card"><span class="icon">🎨</span><h3>Fluent Design</h3><p>Clean, modern interface with Microsoft Fluent design language for a polished experience.</p></div>
            <div class="ms-card"><span class="icon">🌐</span><h3>Open Source</h3><p>MIT licensed — use it, modify it, and contribute back to the community.</p></div>
        </div>
    </div>
</section>

<section class="ms-section">
    <div class="container">
        <div class="section-header">
            <span class="label">Latest Articles</span>
            <h2>Recent posts</h2>
        </div>

        <?php if (!empty($data['categories'])): ?>
        <div class="home-category-nav">
            <span class="home-category-label">Browse:</span>
            <?php foreach ($data['categories'] as $cat): ?>
            <a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>" class="home-category-chip">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($data['articles'])): ?>
            <p style="color:var(--ms-gray-500);">No articles yet. <a href="/admin/article/create">Create your first article</a></p>
        <?php else: ?>
            <div class="ms-article-list">
            <?php foreach ($data['articles'] as $article):
                $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                $excerpt = mb_substr(strip_tags($excerpt), 0, 120) . '...';
                $authorDisplay = $article['author_display'] ?? $article['author_name'] ?? 'Unknown';
            ?>
                <div class="ms-article-item">
                    <h3><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h3>
                    <div class="meta">
                        <span><?php echo date('F j, Y', strtotime($article['created_at'])); ?></span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?></span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars($authorDisplay); ?></span>
                        <span>·</span>
                        <span><?php echo $article['views'] ?? 0; ?> views</span>
                    </div>
                    <p class="excerpt"><?php echo $excerpt; ?></p>
                    <?php if ($article['is_top'] || $article['is_recommend']): ?>
                    <div class="labels">
                        <?php if ($article['is_top']): ?><span class="top">Top</span><?php endif; ?>
                        <?php if ($article['is_recommend']): ?><span>Recommend</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <?php
            $baseUrl = '?';
            $total = $data['total'];
            $perPage = $data['perPage'];
            $currentPage = $data['page'];
            include APP_PATH . '/views/partials/pagination.php';
            ?>
        <?php endif; ?>
    </div>
</section>
<?php
        return ob_get_clean();
    }

    private function renderFooter(array $data): string
    {
        ob_start();
        ?>
<footer class="site-footer">
    <div class="container">
        <span class="footer-brand"><?php echo htmlspecialchars($data['site_name']); ?></span>
        <span class="footer-copy"><?php echo htmlspecialchars($data['site_footer'] ?? ''); ?></span>
        <span class="footer-dev">Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></span>
    </div>
</footer>
<script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}