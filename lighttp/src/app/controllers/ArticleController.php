<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;

class ArticleController extends BaseController
{
    public function show(string $id): string
    {
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);

        if (!$article) {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Article not found.</p><a href="/">Back to home</a></div></body></html>';
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($article['meta_title'] ?? $article['title']) . '</title>
    <meta name="description" content="' . htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? '') . '">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="/" class="logo">Lighttp</a>
            <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <ul class="nav-links" id="navLinks">
                <li><a href="/">Home</a></li>
                <li><a href="/admin">Admin</a></li>
            </ul>
        </div>
    </header>

    <main class="container" style="padding-top:32px;max-width:800px;">
        <article>
            <h1>' . htmlspecialchars($article['title']) . '</h1>
            <div class="meta" style="color:var(--gray-500);font-size:0.875rem;margin-bottom:24px;border-bottom:1px solid var(--gray-200);padding-bottom:16px;">
                <span>' . date('Y-m-d H:i', strtotime($article['published_at'] ?? $article['created_at'])) . '</span>
                <span>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</span>
                <span>' . htmlspecialchars($article['author_name'] ?? 'Unknown') . '</span>
                <span>' . ($article['views'] ?? 0) . ' views</span>
            </div>
            <div class="article-content">' . ($article['content'] ?? '') . '</div>
            <div style="margin-top:32px;padding-top:16px;border-top:2px solid var(--gray-200);">
                <a href="/" class="btn btn-sm">Back to home</a>
                <a href="/admin/article/edit/' . $article['id'] . '" class="btn btn-sm">Edit</a>
                <a href="/admin/articles" class="btn btn-sm">Manage</a>
            </div>
        </article>
    </main>

    <footer class="site-footer">
        <div class="container">
            <span class="footer-brand">Lighttp</span>
            <span class="footer-copy">&copy; ' . date('Y') . ' All rights reserved.</span>
            <span class="footer-dev">Powered by <a href="https://github.com/Drliehuo/inetpub.github.io/tree/main/lighttp/src">Lighttp</a></span>
        </div>
    </footer>

    <script src="/js/app.js"></script>
</body>
</html>';

        return $html;
    }
}
