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
            return '<h1 style="text-align:center;padding:40px;">数据库连接失败</h1>';
        }
        
        $page = $db->queryOne("SELECT * FROM pages WHERE slug = ? AND is_show = 1", [$slug]);
        
        if (!$page) {
            return '<h1 style="text-align:center;padding:40px;">页面不存在</h1>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($page['meta_title'] ?? $page['title']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.8; }
                .back { display: inline-block; margin-top: 30px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($page['title']) . '</h1>
            <div>' . nl2br(htmlspecialchars($page['content'] ?? '')) . '</div>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
    }
}
