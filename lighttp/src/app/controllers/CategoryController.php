<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Category;
use App\models\Article;

class CategoryController extends BaseController
{
    public function index(string $slug): string
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBySlug($slug);

        if (!$category) {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Category not found.</p><a href="/">Back to home</a></div></body></html>';
        }

        $articleModel = new Article();
        $articles = $articleModel->getByCategory($category['id']);

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($category['name']) . ' - Category</title>
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

    <main class="container" style="padding-top:32px;">
        <h1>' . htmlspecialchars($category['name']) . '</h1>
        <p>' . htmlspecialchars($category['description'] ?? '') . '</p>';

        if (empty($articles)) {
            $html .= '<p>No articles in this category.</p>';
        } else {
            $html .= '<div style="margin-top:24px;">';
            foreach ($articles as $article) {
                $html .= '<div class="article-card">
                    <h2><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h2>
                    <div class="meta"><span>' . date('Y-m-d', strtotime($article['created_at'])) . '</span><span>' . ($article['views'] ?? 0) . ' views</span></div>
                </div>';
            }
            $html .= '</div>';
        }

        $html .= '<a href="/" class="btn btn-sm" style="margin-top:16px;">Back to home</a>
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
