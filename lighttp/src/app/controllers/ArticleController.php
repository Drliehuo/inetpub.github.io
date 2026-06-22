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
            return '<h1 style="text-align:center;padding:40px;">文章不存在</h1>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($article['meta_title'] ?? $article['title']) . '</title>
            <meta name="description" content="' . htmlspecialchars($article['meta_description'] ?? $article['excerpt'] ?? '') . '">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.8; color: #333; }
                .article h1 { margin-top: 0; font-size: 28px; }
                .meta { color: #999; font-size: 14px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .meta span { margin-right: 15px; }
                .content { font-size: 16px; }
                .content pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
                .content code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 14px; }
                .back { display: inline-block; margin-top: 30px; color: #3498db; text-decoration: none; }
                .back:hover { text-decoration: underline; }
                .admin-links { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
                .admin-links a { margin-right: 15px; color: #666; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="article">
                <h1>' . htmlspecialchars($article['title']) . '</h1>
                <div class="meta">
                    <span>📅 ' . date('Y-m-d H:i', strtotime($article['published_at'] ?? $article['created_at'])) . '</span>
                    <span>📂 ' . htmlspecialchars($article['category_name'] ?? '未分类') . '</span>
                    <span>👤 ' . htmlspecialchars($article['author_name'] ?? '未知') . '</span>
                    <span>👁️ ' . ($article['views'] ?? 0) . ' 次浏览</span>
                </div>
                <div class="content">' . nl2br(htmlspecialchars($article['content'] ?? '')) . '</div>
                <a href="/" class="back">← 返回首页</a>
                <div class="admin-links">
                    <a href="/admin/article/edit/' . $article['id'] . '">✏️ 编辑</a>
                    <a href="/admin/articles">📋 管理</a>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
