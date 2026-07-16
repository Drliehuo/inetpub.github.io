<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Tag;

class TagController extends BaseController
{
    public function index(string $slug): string
    {
        $tagModel = new Tag();
        $tag = $tagModel->findBySlug($slug);

        if (!$tag) {
            ob_start();
            ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Tag not found.</p><a href="/">Back to home</a></div></body></html>
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
    <title><?php echo htmlspecialchars($tag['name']); ?> - Tag</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include APP_PATH . '/views/partials/header.php'; ?>

    <main class="container" style="padding-top:32px;">
        <h1>Tag: <?php echo htmlspecialchars($tag['name']); ?></h1>
        <p><?php echo $tag['count'] ?? 0; ?> articles with this tag.</p>
        <a href="/" class="btn btn-sm" style="margin-top:16px;">Back to home</a>
    </main>

    <?php include APP_PATH . '/views/partials/footer.php'; ?>

    <script src="/js/app.js"></script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
