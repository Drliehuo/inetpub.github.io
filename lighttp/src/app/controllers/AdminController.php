<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\Article;
use App\models\Category;
use App\models\User;
use App\models\Setting;
use App\core\Application;

class AdminController extends BaseController
{
    private function checkAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
        }
        $user = $this->getCurrentUser();
        if (!in_array($user['role'], ['admin', 'editor'])) {
            $this->redirect('/');
        }
    }

    public function dashboard(): string
    {
        $this->checkAuth();
        
        $db = $this->getDb();
        $articleCount = $db->queryOne("SELECT COUNT(*) as count FROM articles WHERE status = 1");
        $userCount = $db->queryOne("SELECT COUNT(*) as count FROM users");
        $commentCount = $db->queryOne("SELECT COUNT(*) as count FROM comments WHERE status = 1");
        $categoryCount = $db->queryOne("SELECT COUNT(*) as count FROM categories");
        
        return $this->renderAdminLayout('仪表盘', '
        <h2 style="margin-bottom:20px;">📊 数据概览</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;">
            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);text-align:center;">
                <div style="font-size:32px;font-weight:bold;color:#2c3e50;">' . ($articleCount['count'] ?? 0) . '</div>
                <div style="color:#999;">📄 文章</div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);text-align:center;">
                <div style="font-size:32px;font-weight:bold;color:#2c3e50;">' . ($categoryCount['count'] ?? 0) . '</div>
                <div style="color:#999;">📂 分类</div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);text-align:center;">
                <div style="font-size:32px;font-weight:bold;color:#2c3e50;">' . ($commentCount['count'] ?? 0) . '</div>
                <div style="color:#999;">💬 评论</div>
            </div>
            <div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);text-align:center;">
                <div style="font-size:32px;font-weight:bold;color:#2c3e50;">' . ($userCount['count'] ?? 0) . '</div>
                <div style="color:#999;">👤 用户</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
            <a href="/admin/articles" style="background:#fff;padding:30px;border-radius:8px;text-align:center;text-decoration:none;color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <span style="font-size:36px;display:block;">📄</span>
                <span style="font-weight:bold;">文章管理</span>
            </a>
            <a href="/admin/categories" style="background:#fff;padding:30px;border-radius:8px;text-align:center;text-decoration:none;color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <span style="font-size:36px;display:block;">📂</span>
                <span style="font-weight:bold;">分类管理</span>
            </a>
            <a href="/admin/users" style="background:#fff;padding:30px;border-radius:8px;text-align:center;text-decoration:none;color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <span style="font-size:36px;display:block;">👤</span>
                <span style="font-weight:bold;">用户管理</span>
            </a>
            <a href="/admin/settings" style="background:#fff;padding:30px;border-radius:8px;text-align:center;text-decoration:none;color:#2c3e50;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <span style="font-size:36px;display:block;">⚙️</span>
                <span style="font-weight:bold;">系统设置</span>
            </a>
        </div>');
    }

    public function articles(): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articles = $articleModel->getAll();

        $html = $this->renderAdminLayout('文章管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>📄 文章管理</h2>
            <a href="/admin/article/create" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">➕ 新建文章</a>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">标题</th>
                    <th style="padding:12px;text-align:left;">分类</th>
                    <th style="padding:12px;text-align:left;">状态</th>
                    <th style="padding:12px;text-align:left;">发布时间</th>
                    <th style="padding:12px;text-align:left;">操作</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($articles)) {
            $html .= '<tr><td colspan="6" style="text-align:center;padding:20px;">暂无文章</td></tr>';
        } else {
            $statusMap = [0 => '草稿', 1 => '已发布', 2 => '待审核'];
            foreach ($articles as $article) {
                $status = $statusMap[$article['status']] ?? '未知';
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $article['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($article['title']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($article['category_name'] ?? '未分类') . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $status . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . date('Y-m-d', strtotime($article['created_at'])) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">
                        <a href="/article/' . $article['id'] . '" style="color:#3498db;text-decoration:none;margin-right:10px;">查看</a>
                        <a href="/admin/article/edit/' . $article['id'] . '" style="color:#f39c12;text-decoration:none;margin-right:10px;">编辑</a>
                        <a href="/admin/article/delete/' . $article['id'] . '" style="color:#e74c3c;text-decoration:none;" onclick="return confirm(\'确定删除吗？\')">删除</a>
                    </td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function createArticle(): string
    {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'author_id' => $this->getCurrentUser()['id'] ?? null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('标题和内容不能为空');
            }

            $articleModel = new Article();
            $id = $articleModel->create($data);
            
            if ($id) {
                $cache = $this->getCache();
                if ($cache) {
                    $cache->delete('home_data');
                    $cache->delete('page:' . md5('/'));
                }
                $this->redirect('/admin/articles');
            }
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories);
    }

    private function renderArticleForm(string $error = '', array $categories = [], ?array $article = null): string
    {
        $isEdit = $article !== null;
        $statusOptions = [0 => '草稿', 1 => '已发布', 2 => '待审核'];

        // ===== 关键修改：后台 textarea 直接显示原始内容（不转义） =====
        $content = $article ? ($article['content'] ?? '') : '';
        $title = $article ? ($article['title'] ?? '') : '';
        $excerpt = $article ? ($article['excerpt'] ?? '') : '';

        $html = '
        ' . ($error ? '<div style="background:#fde8e8;color:#e74c3c;padding:10px;border-radius:4px;margin-bottom:20px;">' . htmlspecialchars($error) . '</div>' : '') . '
        <form method="POST" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">标题 *</label>
                <input type="text" name="title" value="' . htmlspecialchars($title) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;" required>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">摘要</label>
                <input type="text" name="excerpt" value="' . htmlspecialchars($excerpt) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">分类</label>
                <select name="category_id" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                    <option value="">无分类</option>';

        foreach ($categories as $cat) {
            $selected = ($article && $article['category_id'] == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        $html .= '</select></div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">内容 *</label>
                <div style="margin-bottom:8px;">
                    <span style="font-size:12px;color:#999;">💡 支持 HTML 标签：&lt;h1&gt;、&lt;p&gt;、&lt;a&gt;、&lt;img&gt;、&lt;ul&gt;、&lt;ol&gt;、&lt;table&gt;、&lt;pre&gt;、&lt;code&gt; 等</span>
                    <button type="button" onclick="previewContent()" style="margin-left:10px;background:#6c757d;color:#fff;padding:4px 16px;border:none;border-radius:4px;cursor:pointer;font-size:13px;">👁️ 预览</button>
                </div>
                <textarea name="content" id="editor" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;min-height:300px;font-family:monospace;" required>' . $content . '</textarea>
                <div id="preview" style="display:none;border:1px solid #ddd;padding:15px;margin-top:10px;border-radius:4px;background:#fff;max-height:400px;overflow-y:auto;"></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:15px;">
                <div>
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">状态</label>
                    <select name="status" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">';

        foreach ($statusOptions as $k => $v) {
            $selected = ($article && $article['status'] == $k) ? 'selected' : '';
            $html .= '<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
        }

        $html .= '</select></div>
                <div style="display:flex;align-items:center;padding-top:25px;">
                    <label style="margin-right:15px;">
                        <input type="checkbox" name="is_top" ' . ($article && $article['is_top'] ? 'checked' : '') . '> 置顶
                    </label>
                    <label>
                        <input type="checkbox" name="is_recommend" ' . ($article && $article['is_recommend'] ? 'checked' : '') . '> 推荐
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">' . ($isEdit ? '更新文章' : '发布文章') . '</button>
                <a href="/admin/articles" style="background:#95a5a6;color:#fff;padding:12px 30px;border-radius:4px;text-decoration:none;">返回</a>
            </div>
        </form>

        <script>
        function previewContent() {
            var content = document.getElementById("editor").value;
            var preview = document.getElementById("preview");
            preview.innerHTML = content;
            preview.style.display = "block";
        }
        </script>';

        return $this->renderAdminLayout($isEdit ? '编辑文章' : '新建文章', $html);
    }

    public function editArticle(string $id): string
    {
        $this->checkAuth();
        $articleModel = new Article();
        $article = $articleModel->find((int)$id);
        
        if (!$article) {
            return $this->renderAdminLayout('错误', '<p style="text-align:center;padding:40px;">文章不存在</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'status' => (int)($_POST['status'] ?? 1),
                'is_top' => isset($_POST['is_top']) ? 1 : 0,
                'is_recommend' => isset($_POST['is_recommend']) ? 1 : 0,
            ];

            if (empty($data['title']) || empty($data['content'])) {
                return $this->renderArticleForm('标题和内容不能为空', [], $article);
            }

            $articleModel->update((int)$id, $data);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
                $cache->delete('page:' . md5('/'));
                $cache->delete('page:' . md5('/article/' . $id));
            }
            
            $this->redirect('/admin/articles');
        }

        $categoryModel = new Category();
        $categories = $categoryModel->findAll();
        return $this->renderArticleForm('', $categories, $article);
    }

    public function deleteArticle(string $id): void
    {
        $this->checkAuth();
        $articleModel = new Article();
        $articleModel->delete((int)$id);
        
        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
            $cache->delete('page:' . md5('/'));
            $cache->delete('page:' . md5('/article/' . $id));
        }
        
        $this->redirect('/admin/articles');
    }

    public function categories(): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categories = $categoryModel->findAll(true);

        $html = $this->renderAdminLayout('分类管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>📂 分类管理</h2>
            <a href="/admin/category/create" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;">➕ 新建分类</a>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">名称</th>
                    <th style="padding:12px;text-align:left;">别名</th>
                    <th style="padding:12px;text-align:left;">描述</th>
                    <th style="padding:12px;text-align:left;">操作</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($categories)) {
            $html .= '<tr><td colspan="5" style="text-align:center;padding:20px;">暂无分类</td></tr>';
        } else {
            foreach ($categories as $cat) {
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $cat['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['name']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['slug']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($cat['description'] ?? '') . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">
                        <a href="/admin/category/edit/' . $cat['id'] . '" style="color:#f39c12;text-decoration:none;margin-right:10px;">编辑</a>
                        <a href="/admin/category/delete/' . $cat['id'] . '" style="color:#e74c3c;text-decoration:none;" onclick="return confirm(\'确定删除吗？\')">删除</a>
                    </td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function createCategory(): string
    {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                return $this->renderAdminLayout('新建分类', '<div style="color:#e74c3c;margin-bottom:15px;">分类名称不能为空</div>' . $this->getCategoryForm());
            }

            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name), '-'));
            }

            $categoryModel = new Category();
            $categoryModel->create($name, $slug, $description);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
            
            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('新建分类', $this->getCategoryForm());
    }

    private function getCategoryForm(?array $category = null): string
    {
        $isEdit = $category !== null;
        return '
        <form method="POST" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);max-width:600px;">
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">分类名称 *</label>
                <input type="text" name="name" value="' . ($category ? htmlspecialchars($category['name'] ?? '') : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;" required>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">URL别名</label>
                <input type="text" name="slug" value="' . ($category ? htmlspecialchars($category['slug'] ?? '') : '') . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                <small style="color:#999;">留空将自动生成</small>
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block;margin-bottom:5px;font-weight:bold;">描述</label>
                <textarea name="description" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;min-height:80px;">' . ($category ? htmlspecialchars($category['description'] ?? '') : '') . '</textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">' . ($isEdit ? '更新分类' : '创建分类') . '</button>
                <a href="/admin/categories" style="background:#95a5a6;color:#fff;padding:12px 30px;border-radius:4px;text-decoration:none;">返回</a>
            </div>
        </form>';
    }

    public function editCategory(string $id): string
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $category = $categoryModel->find((int)$id);
        
        if (!$category) {
            return $this->renderAdminLayout('错误', '<p style="text-align:center;padding:40px;">分类不存在</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
            ];

            if (empty($data['name'])) {
                return $this->renderAdminLayout('编辑分类', '<div style="color:#e74c3c;margin-bottom:15px;">分类名称不能为空</div>' . $this->getCategoryForm($category));
            }

            if (empty($data['slug'])) {
                $data['slug'] = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $data['name']), '-'));
            }

            $categoryModel->update((int)$id, $data);
            
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
            
            $this->redirect('/admin/categories');
        }

        return $this->renderAdminLayout('编辑分类', $this->getCategoryForm($category));
    }

    public function deleteCategory(string $id): void
    {
        $this->checkAuth();
        $categoryModel = new Category();
        $categoryModel->delete((int)$id);
        
        $cache = $this->getCache();
        if ($cache) {
            $cache->delete('home_data');
        }
        
        $this->redirect('/admin/categories');
    }

    public function users(): string
    {
        $this->checkAuth();
        $db = $this->getDb();
        $users = $db->query("SELECT id, username, email, nickname, role, status, created_at, last_login_time FROM users ORDER BY id DESC");

        $html = $this->renderAdminLayout('用户管理', '
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>👤 用户管理</h2>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#f8f9fa;">
                    <th style="padding:12px;text-align:left;">ID</th>
                    <th style="padding:12px;text-align:left;">用户名</th>
                    <th style="padding:12px;text-align:left;">邮箱</th>
                    <th style="padding:12px;text-align:left;">角色</th>
                    <th style="padding:12px;text-align:left;">状态</th>
                    <th style="padding:12px;text-align:left;">注册时间</th>
                </tr>
            </thead>
            <tbody>');

        if (empty($users)) {
            $html .= '<tr><td colspan="6" style="text-align:center;padding:20px;">暂无用户</td></tr>';
        } else {
            $roleMap = ['admin' => '管理员', 'editor' => '编辑', 'author' => '作者', 'subscriber' => '订阅者'];
            $statusMap = [0 => '禁用', 1 => '启用', 2 => '待验证'];
            foreach ($users as $user) {
                $role = $roleMap[$user['role']] ?? $user['role'];
                $status = $statusMap[$user['status']] ?? $user['status'];
                $html .= '<tr>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $user['id'] . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($user['username']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . htmlspecialchars($user['email']) . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $role . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . $status . '</td>
                    <td style="padding:12px;border-top:1px solid #eee;">' . date('Y-m-d', strtotime($user['created_at'])) . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function settings(): string
    {
        $this->checkAuth();
        $user = $this->getCurrentUser();
        if ($user['role'] !== 'admin') {
            $this->redirect('/admin');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settingModel = new Setting();
            foreach ($_POST as $key => $value) {
                if ($key !== 'submit') {
                    $settingModel->set($key, trim($value));
                }
            }
            $cache = $this->getCache();
            if ($cache) {
                $cache->delete('home_data');
            }
        }

        $settingModel = new Setting();
        $siteName = $settingModel->get('site_name') ?? '我的CMS系统';
        $siteDesc = $settingModel->get('site_description') ?? '基于PHP+MySQL+Redis的CMS';
        $perPage = $settingModel->get('per_page') ?? 10;

        $html = $this->renderAdminLayout('系统设置', '
        <div style="max-width:600px;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">
            <h2 style="margin-bottom:20px;">⚙️ 系统设置</h2>
            <form method="POST">
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">网站名称</label>
                    <input type="text" name="site_name" value="' . htmlspecialchars($siteName) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">网站描述</label>
                    <input type="text" name="site_description" value="' . htmlspecialchars($siteDesc) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:bold;">每页数量</label>
                    <input type="number" name="per_page" value="' . htmlspecialchars($perPage) . '" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                </div>
                <button type="submit" style="background:#3498db;color:#fff;padding:12px 30px;border:none;border-radius:4px;cursor:pointer;">保存设置</button>
            </form>
        </div>');
        return $html;
    }

    public function clearCache(): void
    {
        $this->checkAuth();
        $cache = $this->getCache();
        if ($cache) {
            $cache->clear();
        }
        $this->redirect('/admin');
    }

    private function renderAdminLayout(string $title, string $content): string
    {
        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($title) . ' - 管理后台</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: #f5f7fa; }
                .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
                header { background: #2c3e50; color: #fff; padding: 15px 0; }
                header .container { display: flex; justify-content: space-between; align-items: center; }
                header a { color: #fff; text-decoration: none; margin-left: 15px; }
                header a:hover { text-decoration: underline; }
                .content { margin-top: 20px; }
            </style>
        </head>
        <body>
            <header>
                <div class="container">
                    <span style="font-weight:bold;">📋 管理后台</span>
                    <div>
                        <a href="/admin">仪表盘</a>
                        <a href="/">首页</a>
                        <a href="/logout">退出</a>
                    </div>
                </div>
            </header>
            <div class="container">
                <div class="content">' . $content . '</div>
            </div>
        </body>
        </html>';
    }
}
