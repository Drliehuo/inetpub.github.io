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
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"><link rel="stylesheet" href="/css/admin.css"></head>
<body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Category not found.</p><a href="/" class="btn">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;

        $settingModel = new Setting();
        $perPage = (int)($settingModel->get('per_page') ?? 10);

        $articleModel = new Article();
        $result = $articleModel->getByCategoryPaginated($category['id'], $page, $perPage);

        // 获取所有分类（用于导航）
        $allCategories = $categoryModel->findAll();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> · Category</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:24px;">
        <!-- 分类头部 -->
        <div class="category-header">
            <div class="category-header-left">
                <span class="category-badge">Category</span>
                <h1><?php echo htmlspecialchars($category['name']); ?></h1>
                <?php if (!empty($category['description'])): ?>
                <p class="category-desc"><?php echo htmlspecialchars($category['description']); ?></p>
                <?php endif; ?>
                <div class="category-meta">
                    <span><?php echo $result['total']; ?> articles</span>
                    <?php if ($result['total'] > 0): ?>
                    <span>·</span>
                    <span>Page <?php echo $page; ?> of <?php echo $result['totalPages']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="category-header-right">
                <a href="/" class="btn btn-sm">← All posts</a>
            </div>
        </div>

        <!-- 分类导航 -->
        <?php if (!empty($allCategories)): ?>
        <div class="category-nav">
            <?php foreach ($allCategories as $cat): ?>
                <?php if ($cat['id'] == $category['id']): ?>
                <span class="category-nav-item active"><?php echo htmlspecialchars($cat['name']); ?></span>
                <?php else: ?>
                <a href="/category/<?php echo htmlspecialchars($cat['slug']); ?>" class="category-nav-item"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 文章列表 -->
        <?php if (empty($result['data'])): ?>
            <div class="category-empty">
                <p>No articles in this category yet.</p>
                <a href="/" class="btn btn-sm">Browse other categories</a>
            </div>
        <?php else: ?>
            <div class="ms-article-list category-article-list">
            <?php foreach ($result['data'] as $article): ?>
                <div class="ms-article-item">
                    <h3><a href="/article/<?php echo $article['id']; ?>"><?php echo htmlspecialchars($article['title']); ?></a></h3>
                    <div class="meta">
                        <span><?php echo date('F j, Y', strtotime($article['created_at'])); ?></span>
                        <span>·</span>
                        <span><?php echo $article['views'] ?? 0; ?> views</span>
                        <?php if ($article['is_top']): ?>
                        <span>·</span>
                        <span style="color:var(--ms-blue);font-weight:500;">Top</span>
                        <?php endif; ?>
                        <?php if ($article['is_recommend']): ?>
                        <span>·</span>
                        <span style="color:#2da44e;font-weight:500;">Recommend</span>
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
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>
    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}