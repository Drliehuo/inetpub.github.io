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
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>Error</h1><p>Database connection failed.</p><a href="/">Back to home</a></div></body></html>';
        }

        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND is_show = 1", [$slug]);

        if (!$page) {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>404</title><link rel="stylesheet" href="/css/style.css"></head><body><div class="container" style="padding-top:80px;text-align:center;"><h1>404</h1><p>Page not found.</p><a href="/">Back to home</a></div></body></html>';
        }

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($page['meta_title'] ?? $page['title']) . '</title>
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
        <h1>' . htmlspecialchars($page['title']) . '</h1>
        <div class="article-content">' . ($page['content'] ?? '') . '</div>
        <a href="/" class="btn btn-sm" style="margin-top:24px;">Back to home</a>
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
