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
            return '<h1 style="text-align:center;padding:40px;">标签不存在</h1>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($tag['name']) . ' - 标签</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                .back { display: inline-block; margin-top: 20px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>🏷️ ' . htmlspecialchars($tag['name']) . '</h1>
            <p>标签下有 ' . ($tag['count'] ?? 0) . ' 篇文章</p>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
    }
}
