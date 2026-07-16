-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-07-16 16:29:37
-- 服务器版本： 5.6.50-log
-- PHP 版本： 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `p_inetpub_cn`
--

-- --------------------------------------------------------

--
-- 表的结构 `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL COMMENT '文章ID',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文章标题',
  `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL别名（SEO友好）',
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文章内容',
  `excerpt` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文章摘要',
  `category_id` int(11) DEFAULT NULL COMMENT '分类ID',
  `author_id` int(11) DEFAULT NULL COMMENT '作者ID',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态: 0-草稿, 1-已发布, 2-待审核',
  `views` int(11) NOT NULL DEFAULT '0' COMMENT '浏览次数',
  `is_top` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否置顶: 0-否, 1-是',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐: 0-否, 1-是',
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '封面图片',
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标签（逗号分隔）',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO标题',
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO描述',
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO关键词',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `published_at` datetime DEFAULT NULL COMMENT '发布时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';

--
-- 转存表中的数据 `articles`
--

INSERT INTO `articles` (`id`, `title`, `slug`, `content`, `excerpt`, `category_id`, `author_id`, `status`, `views`, `is_top`, `is_recommend`, `cover_image`, `tags`, `meta_title`, `meta_description`, `meta_keywords`, `created_at`, `updated_at`, `published_at`) VALUES
(1, '欢迎使用CMS系统', 'welcome-to-cms', '<h2>欢迎使用我们的CMS系统！</h2>\r\n<p>这是一个基于 <strong>PHP + MySQL + Redis</strong> 构建的现代化内容管理系统。</p>\r\n\r\n<h3>主要特性</h3>\r\n<ul>\r\n  <li>🚀 <strong>高性能</strong>：采用Redis缓存，页面加载速度提升10倍</li>\r\n  <li>🔒 <strong>安全可靠</strong>：PDO预处理防止SQL注入，密码哈希加密</li>\r\n  <li>📱 <strong>响应式设计</strong>：完美适配PC、平板、手机</li>\r\n  <li>🔍 <strong>SEO友好</strong>：支持自定义URL、Meta标签、站点地图</li>\r\n  <li>📊 <strong>数据统计</strong>：文章浏览统计、用户行为分析</li>\r\n</ul>\r\n\r\n<h3>快速上手</h3>\r\n<ol>\r\n  <li>登录后台管理</li>\r\n  <li>创建分类和文章</li>\r\n  <li>配置网站基本设置</li>\r\n  <li>开始发布内容！</li>\r\n</ol>\r\n\r\n<p>祝您使用愉快！ 🎉</p>', '欢迎使用基于PHP+MySQL+Redis的现代化CMS系统，包含完整的前后台功能。', 1, 1, 1, 129, 1, 1, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:19:25', '2026-07-16 15:45:19'),
(2, 'Redis缓存性能优化指南', 'redis-cache-optimization', '<h2>Redis缓存性能优化指南</h2>\r\n<p>Redis 是一个高性能的键值对数据库，常用于缓存层。</p>\r\n\r\n<h3>缓存策略</h3>\r\n\r\n<h4>1. 页面缓存</h4>\r\n<p>将完整的HTML页面缓存到Redis中，减少数据库查询。</p>\r\n\r\n<h4>2. 数据缓存</h4>\r\n<p>缓存常用的查询结果，如文章列表、分类数据等。</p>\r\n\r\n<h4>3. 会话缓存</h4>\r\n<p>使用Redis存储用户会话，支持分布式部署。</p>\r\n\r\n<h3>缓存失效策略</h3>\r\n<ul>\r\n  <li><strong>LRU算法</strong>：自动淘汰最少使用的缓存</li>\r\n  <li><strong>TTL设置</strong>：根据数据更新频率设置过期时间</li>\r\n  <li><strong>主动失效</strong>：数据更新时主动清除相关缓存</li>\r\n</ul>\r\n\r\n<h3>性能数据</h3>\r\n<p>启用Redis缓存后：</p>\r\n<ul>\r\n  <li>页面响应时间：<strong>从200ms降至20ms</strong></li>\r\n  <li>数据库查询：<strong>减少90%</strong></li>\r\n  <li>并发处理能力：<strong>提升5倍</strong></li>\r\n</ul>\r\n\r\n<blockquote>\r\n  <p>💡 推荐阅读：<a href=\"https://redis.io/documentation\">Redis官方文档</a></p>\r\n</blockquote>', '深入探讨Redis缓存策略，包括页面缓存、数据缓存和会话缓存的最佳实践。', 1, 1, 1, 94, 0, 1, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:19:31', '2026-07-16 15:45:19'),
(3, 'PHP 8 新特性全面解析', 'php8-new-features', '<h2>PHP 8 新特性全面解析</h2>\r\n<p>PHP 8 带来了许多激动人心的新特性，让开发更高效、代码更优雅。</p>\r\n\r\n<h3>主要新特性</h3>\r\n\r\n<h4>1. JIT（即时编译）</h4>\r\n<p>JIT 可以显著提升CPU密集型应用的性能。</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\n// JIT 在 PHP 8 中默认启用\r\n// 在 php.ini 中配置：\r\n// opcache.jit = tracing\r\n// opcache.jit_buffer_size = 100M</code></pre>\r\n\r\n<h4>2. 命名参数（Named Arguments）</h4>\r\n<p>不用再记住参数的顺序了！</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\nfunction createUser($name, $email, $role = \"user\") {\r\n    // ...\r\n}\r\n\r\n// 使用命名参数\r\ncreateUser(\r\n    name: \"张三\",\r\n    email: \"zhangsan@example.com\",\r\n    role: \"admin\"\r\n);</code></pre>\r\n\r\n<h4>3. 属性（Attributes）</h4>\r\n<p>类似其他语言的注解（Annotations）。</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\n#[Route(\"/api/users\", methods: [\"GET\"])]\r\nclass UserController {\r\n    // ...\r\n}</code></pre>\r\n\r\n<h4>4. 匹配表达式（Match Expression）</h4>\r\n<p>比 switch 更强大、更安全。</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\n$result = match($status) {\r\n    200 => \"OK\",\r\n    404 => \"Not Found\",\r\n    500 => \"Server Error\",\r\n    default => \"Unknown\"\r\n};</code></pre>\r\n\r\n<h3>性能对比</h3>\r\n<table>\r\n  <thead>\r\n    <tr>\r\n      <th>版本</th>\r\n      <th>响应时间</th>\r\n      <th>内存占用</th>\r\n    </tr>\r\n  </thead>\r\n  <tbody>\r\n    <tr>\r\n      <td>PHP 7.4</td>\r\n      <td>100ms</td>\r\n      <td>8MB</td>\r\n    </tr>\r\n    <tr>\r\n      <td>PHP 8.0</td>\r\n      <td>80ms (-20%)</td>\r\n      <td>7MB (-12%)</td>\r\n    </tr>\r\n    <tr>\r\n      <td>PHP 8.1</td>\r\n      <td>65ms (-35%)</td>\r\n      <td>6.5MB (-18%)</td>\r\n    </tr>\r\n    <tr>\r\n      <td>PHP 8.2</td>\r\n      <td>60ms (-40%)</td>\r\n      <td>6MB (-25%)</td>\r\n    </tr>\r\n  </tbody>\r\n</table>\r\n\r\n<p>🚀 <strong>升级建议</strong>：生产环境建议升级到 PHP 8.1 或 8.2</p>', '全面解析PHP 8的JIT编译、命名参数、属性等新特性，含性能对比数据。', 1, 1, 1, 213, 0, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:11:49', '2026-07-16 15:45:19'),
(4, '打造高效开发环境', 'efficient-dev-environment', '<h2>打造高效开发环境</h2>\r\n<p>一个优秀的开发环境可以提升团队效率50%以上。</p>\r\n\r\n<h3>必备工具</h3>\r\n\r\n<h4>编辑器</h4>\r\n<ul>\r\n  <li><strong>VS Code</strong>：免费、插件丰富</li>\r\n  <li><strong>PHPStorm</strong>：专业PHP IDE</li>\r\n  <li><strong>Sublime Text</strong>：轻量快速</li>\r\n</ul>\r\n\r\n<h4>版本控制</h4>\r\n<ul>\r\n  <li><strong>Git</strong> + <strong>GitHub/GitLab</strong></li>\r\n  <li>使用分支策略：feature -&gt; develop -&gt; main</li>\r\n</ul>\r\n\r\n<h4>本地开发环境</h4>\r\n<ul>\r\n  <li><strong>Docker</strong>：容器化，环境一致性</li>\r\n  <li><strong>XAMPP/MAMP</strong>：快速部署</li>\r\n  <li><strong>Laravel Valet</strong>：Mac开发利器</li>\r\n</ul>\r\n\r\n<h3>调试工具</h3>\r\n<ul>\r\n  <li><strong>Xdebug</strong>：PHP调试器</li>\r\n  <li><strong>Postman</strong>：API测试</li>\r\n  <li><strong>MySQL Workbench</strong>：数据库管理</li>\r\n</ul>\r\n\r\n<h3>代码质量</h3>\r\n<ul>\r\n  <li><strong>PHPStan</strong>：静态代码分析</li>\r\n  <li><strong>PHP_CodeSniffer</strong>：代码规范检查</li>\r\n  <li><strong>PHPUnit</strong>：单元测试</li>\r\n</ul>', '如何配置高效的PHP开发环境，包含编辑器、调试工具、CI/CD等完整方案。', 1, 1, 1, 70, 0, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:13:03', '2026-07-16 15:45:19'),
(5, 'PHP设计模式实战', 'php-design-patterns', '<h2>PHP设计模式实战</h2>\r\n<p>设计模式是软件开发中的重要经验总结。</p>\r\n\r\n<h3>常用设计模式</h3>\r\n\r\n<h4>1. 单例模式（Singleton）</h4>\r\n<p>确保一个类只有一个实例。</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\nclass Database {\r\n    private static $instance = null;\r\n    private $connection;\r\n    \r\n    private function __construct() {\r\n        // 私有构造函数\r\n    }\r\n    \r\n    public static function getInstance() {\r\n        if (self::$instance === null) {\r\n            self::$instance = new self();\r\n        }\r\n        return self::$instance;\r\n    }\r\n}</code></pre>\r\n\r\n<h4>2. 工厂模式（Factory）</h4>\r\n<p>创建对象的接口。</p>\r\n<pre><code class=\"language-php\">&lt;?php\r\ninterface Logger {\r\n    public function log($message);\r\n}\r\n\r\nclass FileLogger implements Logger {\r\n    public function log($message) {\r\n        file_put_contents(\"log.txt\", $message);\r\n    }\r\n}\r\n\r\nclass LoggerFactory {\r\n    public static function create($type) {\r\n        switch($type) {\r\n            case \"file\":\r\n                return new FileLogger();\r\n            case \"database\":\r\n                return new DatabaseLogger();\r\n            default:\r\n                throw new Exception(\"Unknown logger type\");\r\n        }\r\n    }\r\n}</code></pre>\r\n\r\n<h3>设计原则</h3>\r\n<ul>\r\n  <li><strong>SOLID原则</strong>：单一职责、开闭、里氏替换、接口隔离、依赖倒置</li>\r\n  <li><strong>DRY</strong>：不要重复自己</li>\r\n  <li><strong>KISS</strong>：保持简单</li>\r\n</ul>', 'PHP设计模式实战教程，包含单例、工厂、观察者等模式的代码示例。', 1, 1, 1, 160, 0, 1, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:10:50', '2026-07-16 15:45:19'),
(6, 'MySQL性能优化技巧', 'mysql-performance-optimization', '<h2>MySQL性能优化技巧</h2>\r\n<p>MySQL是PHP应用最常用的数据库。</p>\r\n\r\n<h3>索引优化</h3>\r\n<h4>选择合适的索引类型</h4>\r\n<ul>\r\n  <li><strong>B-Tree索引</strong>：最常用，适用于等值和范围查询</li>\r\n  <li><strong>全文索引</strong>：用于文本搜索</li>\r\n  <li><strong>哈希索引</strong>：内存表快速查询</li>\r\n</ul>\r\n\r\n<pre><code class=\"language-sql\">-- 创建复合索引\r\nCREATE INDEX idx_category_status ON articles(category_id, status);\r\n\r\n-- 使用覆盖索引\r\nSELECT id, title FROM articles WHERE category_id = 1;</code></pre>\r\n\r\n<h3>查询优化</h3>\r\n<h4>避免SELECT *</h4>\r\n<p>只选择需要的字段。</p>\r\n\r\n<pre><code class=\"language-sql\">-- 不推荐\r\nSELECT * FROM articles;\r\n\r\n-- 推荐\r\nSELECT id, title, created_at FROM articles;</code></pre>\r\n\r\n<h4>使用EXPLAIN分析</h4>\r\n<pre><code class=\"language-sql\">EXPLAIN SELECT * FROM articles WHERE category_id = 1;</code></pre>\r\n\r\n<h3>配置优化</h3>\r\n<pre><code class=\"language-ini\"># my.cnf 关键配置\r\ninnodb_buffer_pool_size = 1G\r\nquery_cache_size = 128M\r\nmax_connections = 500\r\ninnodb_log_file_size = 256M</code></pre>\r\n\r\n<h3>监控工具</h3>\r\n<ul>\r\n  <li><strong>MySQL Slow Query Log</strong></li>\r\n  <li><strong>Percona Toolkit</strong></li>\r\n  <li><strong>phpMyAdmin</strong> 状态监控</li>\r\n</ul>\r\n\r\n<blockquote>\r\n  <p>📊 优化后效果：查询速度提升 <strong>80%</strong></p>\r\n</blockquote>', 'MySQL性能优化实战，包括索引优化、查询优化和服务器配置优化。', 1, 1, 1, 106, 0, 0, NULL, NULL, NULL, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 16:24:18', '2026-07-16 15:45:19');

-- --------------------------------------------------------

--
-- 表的结构 `article_tags`
--

CREATE TABLE `article_tags` (
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `tag_id` int(11) NOT NULL COMMENT '标签ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章标签关联表';

-- --------------------------------------------------------

--
-- 表的结构 `cache`
--

CREATE TABLE `cache` (
  `id` int(11) NOT NULL COMMENT '缓存ID',
  `key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '缓存键',
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '缓存值（序列化）',
  `expire_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='缓存表（备用）';

-- --------------------------------------------------------

--
-- 表的结构 `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL COMMENT '分类ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名称',
  `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL别名',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分类描述',
  `parent_id` int(11) DEFAULT '0' COMMENT '父级分类ID (0表示顶级)',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序序号',
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分类封面图',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示: 0-隐藏, 1-显示',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';

--
-- 转存表中的数据 `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `parent_id`, `sort_order`, `cover_image`, `is_show`, `created_at`, `updated_at`) VALUES
(1, '技术', 'tech', '技术类文章，包括编程、架构、数据库等', 0, 1, NULL, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(2, '生活', 'life', '生活感悟、旅行、美食等', 0, 2, NULL, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(3, '随笔', 'note', '随手写下的想法和笔记', 0, 3, NULL, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(4, '产品', 'product', '产品设计、用户体验、项目管理', 0, 4, NULL, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(5, '设计', 'design', 'UI设计、视觉设计、设计思维', 0, 5, NULL, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19');

-- --------------------------------------------------------

--
-- 表的结构 `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL COMMENT '评论ID',
  `article_id` int(11) NOT NULL COMMENT '文章ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID（匿名评论可为NULL）',
  `parent_id` int(11) DEFAULT '0' COMMENT '父评论ID (0表示顶级)',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '评论内容',
  `author_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '匿名评论者姓名',
  `author_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '匿名评论者邮箱',
  `author_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '匿名评论者网站',
  `author_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '评论者IP',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态: 0-待审核, 1-通过, 2-垃圾评论, 3-已删除',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';

-- --------------------------------------------------------

--
-- 表的结构 `links`
--

CREATE TABLE `links` (
  `id` int(11) NOT NULL COMMENT '链接ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链接名称',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链接地址',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '链接描述',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Logo图片',
  `target` enum('_blank','_self','_parent','_top') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_blank' COMMENT '打开方式',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序序号',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示: 0-隐藏, 1-显示',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='友情链接表';

--
-- 转存表中的数据 `links`
--

INSERT INTO `links` (`id`, `name`, `url`, `description`, `logo`, `target`, `sort_order`, `is_show`, `created_at`, `updated_at`) VALUES
(1, 'PHP官方', 'https://php.net', 'PHP官方文档和资源', NULL, '_blank', 1, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(2, 'MySQL官方', 'https://mysql.com', 'MySQL数据库官方', NULL, '_blank', 2, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(3, 'Redis官方', 'https://redis.io', 'Redis缓存数据库官方', NULL, '_blank', 3, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(4, 'GitHub', 'https://github.com', '全球最大的代码托管平台', NULL, '_blank', 4, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19');

-- --------------------------------------------------------

--
-- 表的结构 `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL COMMENT '日志ID',
  `user_id` int(11) DEFAULT NULL COMMENT '操作用户ID',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作用户名',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作IP',
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作URL',
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '请求方法',
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作动作',
  `data` text COLLATE utf8mb4_unicode_ci COMMENT '操作数据（JSON格式）',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- --------------------------------------------------------

--
-- 表的结构 `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL COMMENT '页面ID',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '页面标题',
  `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL别名',
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '页面内容',
  `excerpt` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '页面摘要',
  `template` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'default' COMMENT '页面模板',
  `parent_id` int(11) DEFAULT '0' COMMENT '父页面ID (0表示顶级)',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序序号',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示: 0-隐藏, 1-显示',
  `is_nav` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否导航菜单: 0-否, 1-是',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO标题',
  `meta_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO描述',
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SEO关键词',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面表';

-- --------------------------------------------------------

--
-- 表的结构 `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '会话ID',
  `data` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '会话数据',
  `expire_at` int(11) NOT NULL COMMENT '过期时间戳',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话表';

-- --------------------------------------------------------

--
-- 表的结构 `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL COMMENT '配置ID',
  `key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键名',
  `value` text COLLATE utf8mb4_unicode_ci COMMENT '配置值',
  `group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT '配置分组',
  `type` enum('text','textarea','number','boolean','select','image','file') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT '配置类型',
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '显示标签',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '配置说明',
  `options` text COLLATE utf8mb4_unicode_ci COMMENT '选项值（JSON格式）',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序序号',
  `is_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否系统配置: 0-否, 1-是',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

--
-- 转存表中的数据 `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `type`, `label`, `description`, `options`, `sort_order`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'site_name', '我的CMS系统', 'general', 'text', '网站名称', '网站的显示名称', NULL, 1, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(2, 'site_description', '基于PHP+MySQL+Redis的CMS系统', 'general', 'text', '网站描述', '网站简短描述', NULL, 2, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(3, 'site_keywords', 'CMS,PHP,MySQL,Redis,内容管理', 'general', 'text', '网站关键词', 'SEO关键词', NULL, 3, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(4, 'site_logo', '', 'general', 'image', 'Logo', '网站Logo图片', NULL, 4, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(5, 'site_footer', '© 2026 我的CMS系统 | Powered by PHP', 'general', 'textarea', '页脚信息', '网站底部版权信息', NULL, 5, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(6, 'admin_email', 'admin@example.com', 'general', 'text', '管理员邮箱', '系统通知邮箱', NULL, 6, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19'),
(7, 'per_page', '10', 'general', 'number', '每页数量', '列表页每页显示数量', NULL, 7, 1, '2026-07-16 15:45:19', '2026-07-16 15:45:19');

-- --------------------------------------------------------

--
-- 表的结构 `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL COMMENT '标签ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '标签名称',
  `slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL别名',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标签描述',
  `count` int(11) NOT NULL DEFAULT '0' COMMENT '文章数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT '用户ID',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '邮箱',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码（哈希）',
  `salt` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '密码盐值',
  `nickname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像URL',
  `role` enum('admin','editor','author','subscriber') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subscriber' COMMENT '角色',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态: 0-禁用, 1-启用, 2-待验证',
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int(11) NOT NULL DEFAULT '0' COMMENT '登录次数',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '记住我Token',
  `email_verified_at` datetime DEFAULT NULL COMMENT '邮箱验证时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

--
-- 转存表中的数据 `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `salt`, `nickname`, `avatar`, `role`, `status`, `last_login_ip`, `last_login_time`, `login_count`, `remember_token`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '管理员', NULL, 'admin', 1, '112.116.104.197', '2026-07-16 15:53:49', 1, NULL, NULL, '2026-07-16 15:45:19', '2026-07-16 15:53:49');

--
-- 转储表的索引
--

--
-- 表的索引 `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_slug` (`slug`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_author_id` (`author_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_published_at` (`published_at`),
  ADD KEY `idx_views` (`views`),
  ADD KEY `idx_is_top` (`is_top`),
  ADD KEY `idx_is_recommend` (`is_recommend`);

--
-- 表的索引 `article_tags`
--
ALTER TABLE `article_tags`
  ADD PRIMARY KEY (`article_id`,`tag_id`),
  ADD KEY `idx_tag_id` (`tag_id`);

--
-- 表的索引 `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_key` (`key`),
  ADD KEY `idx_expire_at` (`expire_at`);

--
-- 表的索引 `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_is_show` (`is_show`);

--
-- 表的索引 `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_article_id` (`article_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `links`
--
ALTER TABLE `links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_is_show` (`is_show`);

--
-- 表的索引 `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip` (`ip`);

--
-- 表的索引 `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_is_show` (`is_show`),
  ADD KEY `idx_is_nav` (`is_nav`);

--
-- 表的索引 `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expire_at` (`expire_at`);

--
-- 表的索引 `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_key` (`key`),
  ADD KEY `idx_group` (`group`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- 表的索引 `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_name` (`name`),
  ADD UNIQUE KEY `uk_slug` (`slug`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '文章ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `cache`
--
ALTER TABLE `cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '缓存ID';

--
-- 使用表AUTO_INCREMENT `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '评论ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `links`
--
ALTER TABLE `links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '链接ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '日志ID';

--
-- 使用表AUTO_INCREMENT `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '页面ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '配置ID', AUTO_INCREMENT=1000;

--
-- 使用表AUTO_INCREMENT `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '标签ID';

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID', AUTO_INCREMENT=1000;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
