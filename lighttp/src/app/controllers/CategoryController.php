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
            return '<h1 style="text-align:center;padding:40px;">分类不存在</h1>';
        }

        $articleModel = new Article();
        $articles = $articleModel->getByCategory($category['id']);

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($category['name']) . ' - 分类</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
                .article-list { margin-top: 20px; }
                .article-item { padding: 15px 0; border-bottom: 1px solid #eee; }
                .article-item h3 { margin: 0 0 5px 0; }
                .article-item h3 a { color: #333; text-decoration: none; }
                .article-item h3 a:hover { color: #3498db; }
                .article-item .meta { color: #999; font-size: 14px; }
                .back { display: inline-block; margin-top: 20px; color: #3498db; text-decoration: none; }
            </style>
        </head>
        <body>
            <h1>📂 ' . htmlspecialchars($category['name']) . '</h1>
            <p>' . htmlspecialchars($category['description'] ?? '') . '</p>
            <div class="article-list">';

        if (empty($articles)) {
            $html .= '<p>该分类下暂无文章</p>';
        } else {
            foreach ($articles as $article) {
                $html .= '<div class="article-item">
                    <h3><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h3>
                    <div class="meta">' . date('Y-m-d', strtotime($article['created_at'])) . ' | ' . ($article['views'] ?? 0) . ' 次浏览</div>
                </div>';
            }
        }

        $html .= '</div>
            <a href="/" class="back">← 返回首页</a>
        </body>
        </html>';
        
        return $html;
    }
}
