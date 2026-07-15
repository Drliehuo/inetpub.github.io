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
                'site_name' => '我的CMS系统',
                'site_description' => '基于PHP+MySQL+Redis的现代化CMS'
            ];
            
            if ($cache) {
                $cache->set($cacheKey, $data, 300);
            }
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($data['site_name']) . '</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
                header { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px 0; }
                .header-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
                .logo { font-size: 24px; font-weight: bold; color: #2c3e50; text-decoration: none; }
                nav a { color: #666; text-decoration: none; margin-left: 20px; transition: color 0.3s; }
                nav a:hover { color: #3498db; }
                .main { padding: 40px 0; }
                .article-card { background: #fff; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
                .article-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .article-card h2 { margin: 0 0 10px 0; }
                .article-card h2 a { color: #2c3e50; text-decoration: none; }
                .article-card h2 a:hover { color: #3498db; }
                .meta { color: #999; font-size: 14px; margin-bottom: 10px; }
                .meta span { margin-right: 15px; }
                .excerpt { color: #666; line-height: 1.6; }
                /* ===== 摘要样式限制，防止破坏首页布局 ===== */
                .excerpt img { max-width: 100%; max-height: 200px; object-fit: cover; border-radius: 4px; }
                .excerpt table { display: block; overflow-x: auto; }
                .excerpt blockquote { border-left: 3px solid #3498db; padding-left: 15px; margin: 10px 0; color: #555; }
                .excerpt pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
                .excerpt code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
                .admin-bar { background: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
                .admin-bar a { color: #3498db; text-decoration: none; margin-right: 15px; }
                .admin-bar a:hover { text-decoration: underline; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; background: #e8f5e9; color: #2e7d32; margin-left: 10px; }
                .status-badge.redis { background: #ffebee; color: #c62828; }
                footer { text-align: center; padding: 30px 0; color: #999; border-top: 1px solid #eee; margin-top: 40px; }
                /* ===== 摘要截断 ===== */
                .excerpt-truncate { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
            </style>
        </head>
        <body>
            <header>
                <div class="container header-inner">
                    <a href="/" class="logo">📝 ' . htmlspecialchars($data['site_name']) . '</a>
                    <nav>
                        <a href="/">首页</a>';

        foreach ($data['categories'] as $cat) {
            $html .= '<a href="/category/' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</a>';
        }

        if ($this->isLoggedIn()) {
            $html .= '<a href="/admin">管理</a>';
            $html .= '<a href="/logout">退出</a>';
        } else {
            $html .= '<a href="/login">登录</a>';
            $html .= '<a href="/register">注册</a>';
        }

        $html .= '</nav></div></header>

        <main class="main">
            <div class="container">
                <div class="admin-bar">
                    <a href="/admin/articles">📋 管理文章</a>
                    <a href="/admin/article/create">➕ 新建文章</a>
                    <a href="/admin/cache/clear">🗑️ 清空缓存</a>
                    <span class="status-badge">✅ MySQL已连接</span>
                    <span class="status-badge redis">⚡ Redis缓存已启用</span>
                </div>

                <h2 style="margin-bottom: 20px;">📖 最新文章</h2>';

        if (empty($data['articles'])) {
            $html .= '<p style="text-align:center;padding:40px 0;">暂无文章，<a href="/admin/article/create">创建第一篇</a></p>';
        } else {
            foreach ($data['articles'] as $article) {
                // ===== 关键修改：生成摘要（移除 htmlspecialchars） =====
                $excerpt = $article['excerpt'] ?? $article['content'] ?? '';
                $excerpt = mb_substr(strip_tags($excerpt), 0, 150) . '...';
                
                $html .= '<div class="article-card">
                    <h2><a href="/article/' . $article['id'] . '">' . htmlspecialchars($article['title']) . '</a></h2>
                    <div class="meta">
                        <span>📅 ' . date('Y-m-d', strtotime($article['created_at'])) . '</span>
                        <span>📂 ' . htmlspecialchars($article['category_name'] ?? '未分类') . '</span>
                        <span>👁️ ' . ($article['views'] ?? 0) . ' 次浏览</span>
                        ' . ($article['is_top'] ? '<span>⭐ 置顶</span>' : '') . '
                        ' . ($article['is_recommend'] ? '<span>🔥 推荐</span>' : '') . '
                    </div>
                    <p class="excerpt excerpt-truncate">' . $excerpt . '</p>
                </div>';
            }
        }

        $html .= '</div></main>
        <footer>
            <div class="container">
                <p>© ' . date('Y') . ' ' . htmlspecialchars($data['site_name']) . ' | Powered by PHP + MySQL + Redis</p>
            </div>
        </footer>
        </body>
        </html>';
        
        return $html;
    }
}