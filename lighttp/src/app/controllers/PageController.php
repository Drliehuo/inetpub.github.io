<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;

class PageController extends BaseController
{
    public function show(string $slug): string
    {
        $db = $this->getDb();
        if (!$db) {
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>Error</h1><p>Database connection failed.</p><a href="/">Back to home</a></div></body></html>
<?php
            return ob_get_clean();
        }

        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND is_show = 1", [$slug]);

        if (!$page) {
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Page not found.</p><a href="/">Back to home</a></div></body></html>
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
    <title><?php echo htmlspecialchars($page['meta_title'] ?? $page['title']); ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;max-width:800px;">
        <h1><?php echo htmlspecialchars($page['title']); ?></h1>
        <div class="article-content"><?php echo $page['content'] ?? ''; ?></div>
        <a href="/" class="btn btn-sm" style="margin-top:24px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
