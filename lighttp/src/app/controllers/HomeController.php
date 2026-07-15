<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Application;
use App\models\Article;
use App\models\Category;

class HomeController extends BaseController
{
    public function index(): string
    {
        $cache = Application::getInstance()->getCache();
        $cacheKey = 'home_data';
        $data = $cache && $cache->has($cacheKey) ? $cache->get($cacheKey) : null;

        if ($data === null) {
            $articleModel = new Article();
            $categoryModel = new Category();
            $data = [
                'articles' => $articleModel->getLatest(10),
                'categories' => $categoryModel->findAll(),
                'site_name' => 'My CMS',
                'site_description' => 'A lightweight CMS built with PHP + MySQL + Redis'
            ];
            if ($cache) {
                $cache->set($cacheKey, $data, 300);
            }
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($data['site_name']) . '</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="/" class="logo">' . htmlspecialchars($data['site_name']) . '</a>
            <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <ul class="nav-links" id="navLinks">';

        foreach ($data['categories'] as $cat) {
            $html .= '<li><a href="/category/' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</a></li>';
        }

        if ($this->isLoggedIn()) {
            $html .= '<li><a href="/admin">Admin</a></li>';
            $html .= '<li><a href="/logout" class="nav-cta">Logout</a></li>';
        } else {
            $html .= '<li><a href="/login">Login</a></li>';
            $html .= '<li><a href="/register" class="nav-cta">Register</a></li>';
        }

        $html .= '</ul></div></header>

        <main class="container" style="padding-top:32px;">
            <div class="admin-bar">
                <a href="/admin/articles">Manage Articles</a>
                <a href="/admin/article/create">New Article</a>
                <a href="/admin/cache/clear">Clear Cache</a>
                <span class="status-badge success">MySQL</span>
                <span class="status-badge success">Redis</span>
            </div>

            <h2>Latest Articles</h2>';

        if (empty($data['articles'])) {
            $html .= '<p>No articles yet. <a href="/admin/article/create">Create your first article</a></p>';
        } else {
            foreach ($data['articles'] as $article) {
                $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                $excerpt = mb_substr(strip_tags($excerpt), 0, 150) . '...';
                $html .= '<div class="article-card">
                    <h2><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h2>
                    <div class="meta">
                        <span>' . date('Y-m-d', strtotime($article['created_at'])) . '</span>
                        <span>' . htmlspecialchars($article['category_name'] ?? 'Uncategorized') . '</span>
                        <span>' . ($article['views'] ?? 0) . ' views</span>
                        ' . ($article['is_top'] ? '<span>[Top]</span>' : '') . '
                        ' . ($article['is_recommend'] ? '<span>[Recommend]</span>' : '') . '
                    </div>
                    <p class="excerpt">' . $excerpt . '</p>
                </div>';
            }
        }

        $html .= '</main>

        <footer class="site-footer">
            <div class="container">
                <span class="footer-brand">' . htmlspecialchars($data['site_name']) . '</span>
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
