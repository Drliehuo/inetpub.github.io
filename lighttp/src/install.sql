-- ======================================================
-- CMS系统完整数据库安装脚本（修复所有索引长度问题）
-- 兼容 MySQL 5.6/5.7 (InnoDB 767字节限制)
-- ======================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ======================================================
-- 1. 文章表 (articles)
-- ======================================================
DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '文章ID',
    `title` VARCHAR(255) NOT NULL COMMENT '文章标题',
    `slug` VARCHAR(190) NOT NULL COMMENT 'URL别名（SEO友好）',
    `content` LONGTEXT NOT NULL COMMENT '文章内容',
    `excerpt` VARCHAR(500) DEFAULT NULL COMMENT '文章摘要',
    `category_id` INT(11) DEFAULT NULL COMMENT '分类ID',
    `author_id` INT(11) DEFAULT NULL COMMENT '作者ID',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0-草稿, 1-已发布, 2-待审核',
    `views` INT(11) NOT NULL DEFAULT 0 COMMENT '浏览次数',
    `is_top` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否置顶: 0-否, 1-是',
    `is_recommend` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否推荐: 0-否, 1-是',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图片',
    `tags` VARCHAR(255) DEFAULT NULL COMMENT '标签（逗号分隔）',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO标题',
    `meta_description` VARCHAR(500) DEFAULT NULL COMMENT 'SEO描述',
    `meta_keywords` VARCHAR(255) DEFAULT NULL COMMENT 'SEO关键词',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `published_at` DATETIME DEFAULT NULL COMMENT '发布时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_author_id` (`author_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_published_at` (`published_at`),
    KEY `idx_views` (`views`),
    KEY `idx_is_top` (`is_top`),
    KEY `idx_is_recommend` (`is_recommend`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表';

-- ======================================================
-- 2. 分类表 (categories)
-- ======================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '分类ID',
    `name` VARCHAR(100) NOT NULL COMMENT '分类名称',
    `slug` VARCHAR(190) NOT NULL COMMENT 'URL别名',
    `description` VARCHAR(500) DEFAULT NULL COMMENT '分类描述',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父级分类ID (0表示顶级)',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '分类封面图',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';

-- ======================================================
-- 3. 用户表 (users)
-- ======================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（哈希）',
    `salt` VARCHAR(32) DEFAULT NULL COMMENT '密码盐值',
    `nickname` VARCHAR(100) DEFAULT NULL COMMENT '昵称',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    `role` ENUM('admin','editor','author','subscriber') NOT NULL DEFAULT 'subscriber' COMMENT '角色',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0-禁用, 1-启用, 2-待验证',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
    `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `login_count` INT(11) NOT NULL DEFAULT 0 COMMENT '登录次数',
    `remember_token` VARCHAR(100) DEFAULT NULL COMMENT '记住我Token',
    `email_verified_at` DATETIME DEFAULT NULL COMMENT '邮箱验证时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ======================================================
-- 4. 评论表 (comments)
-- ======================================================
DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '评论ID',
    `article_id` INT(11) NOT NULL COMMENT '文章ID',
    `user_id` INT(11) DEFAULT NULL COMMENT '用户ID（匿名评论可为NULL）',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父评论ID (0表示顶级)',
    `content` TEXT NOT NULL COMMENT '评论内容',
    `author_name` VARCHAR(100) DEFAULT NULL COMMENT '匿名评论者姓名',
    `author_email` VARCHAR(100) DEFAULT NULL COMMENT '匿名评论者邮箱',
    `author_url` VARCHAR(255) DEFAULT NULL COMMENT '匿名评论者网站',
    `author_ip` VARCHAR(45) DEFAULT NULL COMMENT '评论者IP',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT '用户代理',
    `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '状态: 0-待审核, 1-通过, 2-垃圾评论, 3-已删除',
    `like_count` INT(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_article_id` (`article_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';

-- ======================================================
-- 5. 页面表 (pages)
-- ======================================================
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '页面ID',
    `title` VARCHAR(255) NOT NULL COMMENT '页面标题',
    `slug` VARCHAR(190) NOT NULL COMMENT 'URL别名',
    `content` LONGTEXT NOT NULL COMMENT '页面内容',
    `excerpt` VARCHAR(500) DEFAULT NULL COMMENT '页面摘要',
    `template` VARCHAR(100) DEFAULT 'default' COMMENT '页面模板',
    `parent_id` INT(11) DEFAULT 0 COMMENT '父页面ID (0表示顶级)',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `is_nav` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否导航菜单: 0-否, 1-是',
    `meta_title` VARCHAR(255) DEFAULT NULL COMMENT 'SEO标题',
    `meta_description` VARCHAR(500) DEFAULT NULL COMMENT 'SEO描述',
    `meta_keywords` VARCHAR(255) DEFAULT NULL COMMENT 'SEO关键词',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`),
    KEY `idx_is_nav` (`is_nav`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面表';

-- ======================================================
-- 6. 友情链接表 (links)
-- ======================================================
DROP TABLE IF EXISTS `links`;
CREATE TABLE `links` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '链接ID',
    `name` VARCHAR(100) NOT NULL COMMENT '链接名称',
    `url` VARCHAR(255) NOT NULL COMMENT '链接地址',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '链接描述',
    `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Logo图片',
    `target` ENUM('_blank','_self','_parent','_top') NOT NULL DEFAULT '_blank' COMMENT '打开方式',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_show` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否显示: 0-隐藏, 1-显示',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_sort_order` (`sort_order`),
    KEY `idx_is_show` (`is_show`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='友情链接表';

-- ======================================================
-- 7. 系统配置表 (settings)
-- ======================================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '配置ID',
    `key` VARCHAR(190) NOT NULL COMMENT '配置键名',  -- 改为190
    `value` TEXT DEFAULT NULL COMMENT '配置值',
    `group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT '配置分组',
    `type` ENUM('text','textarea','number','boolean','select','image','file') NOT NULL DEFAULT 'text' COMMENT '配置类型',
    `label` VARCHAR(100) NOT NULL COMMENT '显示标签',
    `description` VARCHAR(500) DEFAULT NULL COMMENT '配置说明',
    `options` TEXT DEFAULT NULL COMMENT '选项值（JSON格式）',
    `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序序号',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统配置: 0-否, 1-是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_group` (`group`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ======================================================
-- 8. 缓存管理表 (cache) - 修复 key 字段索引
-- ======================================================
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '缓存ID',
    `key` VARCHAR(190) NOT NULL COMMENT '缓存键',  -- 改为190
    `value` LONGTEXT NOT NULL COMMENT '缓存值（序列化）',
    `expire_at` DATETIME NOT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='缓存表（备用）';

-- ======================================================
-- 9. 操作日志表 (logs)
-- ======================================================
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
    `id` BIGINT(20) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
    `user_id` INT(11) DEFAULT NULL COMMENT '操作用户ID',
    `username` VARCHAR(50) DEFAULT NULL COMMENT '操作用户名',
    `ip` VARCHAR(45) NOT NULL COMMENT '操作IP',
    `url` VARCHAR(500) NOT NULL COMMENT '操作URL',
    `method` VARCHAR(10) NOT NULL COMMENT '请求方法',
    `action` VARCHAR(100) NOT NULL COMMENT '操作动作',
    `data` TEXT DEFAULT NULL COMMENT '操作数据（JSON格式）',
    `user_agent` VARCHAR(255) DEFAULT NULL COMMENT '用户代理',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- ======================================================
-- 10. 会话表 (sessions) - id 字段本来就是 VARCHAR(128) 没问题
-- ======================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(128) NOT NULL COMMENT '会话ID',
    `data` LONGTEXT NOT NULL COMMENT '会话数据',
    `expire_at` INT(11) NOT NULL COMMENT '过期时间戳',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话表';

-- ======================================================
-- 11. 标签表 (tags)
-- ======================================================
DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '标签ID',
    `name` VARCHAR(50) NOT NULL COMMENT '标签名称',
    `slug` VARCHAR(190) NOT NULL COMMENT 'URL别名',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '标签描述',
    `count` INT(11) NOT NULL DEFAULT 0 COMMENT '文章数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='标签表';

-- ======================================================
-- 12. 文章-标签关联表 (article_tags)
-- ======================================================
DROP TABLE IF EXISTS `article_tags`;
CREATE TABLE `article_tags` (
    `article_id` INT(11) NOT NULL COMMENT '文章ID',
    `tag_id` INT(11) NOT NULL COMMENT '标签ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`article_id`, `tag_id`),
    KEY `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章标签关联表';

-- ======================================================
-- 插入初始数据
-- ======================================================

-- 插入默认管理员用户
-- 密码: admin123
INSERT INTO `users` (`username`, `email`, `password`, `nickname`, `role`, `status`, `created_at`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'admin', 1, NOW());

-- 插入初始分类数据
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_show`, `created_at`) VALUES
('技术', 'tech', '技术类文章，包括编程、架构、数据库等', 1, 1, NOW()),
('生活', 'life', '生活感悟、旅行、美食等', 2, 1, NOW()),
('随笔', 'note', '随手写下的想法和笔记', 3, 1, NOW()),
('产品', 'product', '产品设计、用户体验、项目管理', 4, 1, NOW()),
('设计', 'design', 'UI设计、视觉设计、设计思维', 5, 1, NOW());

-- 插入示例文章
INSERT INTO `articles` (`title`, `slug`, `content`, `excerpt`, `category_id`, `author_id`, `status`, `views`, `is_top`, `is_recommend`, `created_at`, `published_at`) VALUES
('欢迎使用CMS系统', 'welcome-to-cms', '## 欢迎使用我们的CMS系统！\n\n这是一个基于 **PHP + MySQL + Redis** 构建的现代化内容管理系统。\n\n### 主要特性\n\n- 🚀 **高性能**：采用Redis缓存，页面加载速度提升10倍\n- 🔒 **安全可靠**：PDO预处理防止SQL注入，密码哈希加密\n- 📱 **响应式设计**：完美适配PC、平板、手机\n- 🔍 **SEO友好**：支持自定义URL、Meta标签、站点地图\n- 📊 **数据统计**：文章浏览统计、用户行为分析\n\n### 快速上手\n\n1. 登录后台管理\n2. 创建分类和文章\n3. 配置网站基本设置\n4. 开始发布内容！\n\n祝您使用愉快！ 🎉', '欢迎使用基于PHP+MySQL+Redis的现代化CMS系统，包含完整的前后台功能。', 1, 1, 1, 125, 1, 1, NOW(), NOW()),
('Redis缓存性能优化指南', 'redis-cache-optimization', '## Redis缓存性能优化指南\n\nRedis 是一个高性能的键值对数据库，常用于缓存层。\n\n### 缓存策略\n\n#### 1. 页面缓存\n将完整的HTML页面缓存到Redis中，减少数据库查询。\n\n#### 2. 数据缓存\n缓存常用的查询结果，如文章列表、分类数据等。\n\n#### 3. 会话缓存\n使用Redis存储用户会话，支持分布式部署。\n\n### 缓存失效策略\n\n- **LRU算法**：自动淘汰最少使用的缓存\n- **TTL设置**：根据数据更新频率设置过期时间\n- **主动失效**：数据更新时主动清除相关缓存\n\n### 性能数据\n\n启用Redis缓存后：\n- 页面响应时间：**从200ms降至20ms**\n- 数据库查询：**减少90%**\n- 并发处理能力：**提升5倍**\n\n> 💡 推荐阅读：[Redis官方文档](https://redis.io/documentation)', '深入探讨Redis缓存策略，包括页面缓存、数据缓存和会话缓存的最佳实践。', 1, 1, 1, 89, 0, 1, NOW(), NOW()),
('PHP 8 新特性全面解析', 'php8-new-features', '## PHP 8 新特性全面解析\n\nPHP 8 带来了许多激动人心的新特性，让开发更高效、代码更优雅。\n\n### 主要新特性\n\n#### 1. JIT（即时编译）\nJIT 可以显著提升CPU密集型应用的性能。\n\n```php\n// JIT 在 PHP 8 中默认启用\n// 在 php.ini 中配置：\n// opcache.jit = tracing\n// opcache.jit_buffer_size = 100M\n```\n\n#### 2. 命名参数（Named Arguments）\n不用再记住参数的顺序了！\n\n```php\nfunction createUser($name, $email, $role = "user") {\n    // ...\n}\n\n// 使用命名参数\ncreateUser(\n    name: "张三",\n    email: "zhangsan@example.com",\n    role: "admin"\n);\n```\n\n#### 3. 属性（Attributes）\n类似其他语言的注解（Annotations）。\n\n```php\n#[Route("/api/users", methods: ["GET"])]\nclass UserController {\n    // ...\n}\n```\n\n#### 4. 匹配表达式（Match Expression）\n比 switch 更强大、更安全。\n\n```php\n$result = match($status) {\n    200 => "OK",\n    404 => "Not Found",\n    500 => "Server Error",\n    default => "Unknown"\n};\n```\n\n### 性能对比\n\n| 版本 | 响应时间 | 内存占用 |\n|------|---------|---------|\n| PHP 7.4 | 100ms | 8MB |\n| PHP 8.0 | 80ms (-20%) | 7MB (-12%) |\n| PHP 8.1 | 65ms (-35%) | 6.5MB (-18%) |\n| PHP 8.2 | 60ms (-40%) | 6MB (-25%) |\n\n🚀 **升级建议**：生产环境建议升级到 PHP 8.1 或 8.2', '全面解析PHP 8的JIT编译、命名参数、属性等新特性，含性能对比数据。', 1, 1, 1, 210, 0, 0, NOW(), NOW()),
('打造高效开发环境', 'efficient-dev-environment', '## 打造高效开发环境\n\n一个优秀的开发环境可以提升团队效率50%以上。\n\n### 必备工具\n\n#### 编辑器\n- **VS Code**：免费、插件丰富\n- **PHPStorm**：专业PHP IDE\n- **Sublime Text**：轻量快速\n\n#### 版本控制\n- **Git** + **GitHub/GitLab**\n- 使用分支策略：feature -> develop -> main\n\n#### 本地开发环境\n- **Docker**：容器化，环境一致性\n- **XAMPP/MAMP**：快速部署\n- **Laravel Valet**：Mac开发利器\n\n### 调试工具\n\n- **Xdebug**：PHP调试器\n- **Postman**：API测试\n- **MySQL Workbench**：数据库管理\n\n### 代码质量\n\n- **PHPStan**：静态代码分析\n- **PHP_CodeSniffer**：代码规范检查\n- **PHPUnit**：单元测试', '如何配置高效的PHP开发环境，包含编辑器、调试工具、CI/CD等完整方案。', 1, 1, 1, 67, 0, 0, NOW(), NOW()),
('PHP设计模式实战', 'php-design-patterns', '## PHP设计模式实战\n\n设计模式是软件开发中的重要经验总结。\n\n### 常用设计模式\n\n#### 1. 单例模式（Singleton）\n确保一个类只有一个实例。\n\n```php\nclass Database {\n    private static $instance = null;\n    private $connection;\n    \n    private function __construct() {\n        // 私有构造函数\n    }\n    \n    public static function getInstance() {\n        if (self::$instance === null) {\n            self::$instance = new self();\n        }\n        return self::$instance;\n    }\n}\n```\n\n#### 2. 工厂模式（Factory）\n创建对象的接口。\n\n```php\ninterface Logger {\n    public function log($message);\n}\n\nclass FileLogger implements Logger {\n    public function log($message) {\n        file_put_contents("log.txt", $message);\n    }\n}\n\nclass LoggerFactory {\n    public static function create($type) {\n        switch($type) {\n            case "file":\n                return new FileLogger();\n            case "database":\n                return new DatabaseLogger();\n            default:\n                throw new Exception("Unknown logger type");\n        }\n    }\n}\n```\n\n### 设计原则\n\n- **SOLID原则**：单一职责、开闭、里氏替换、接口隔离、依赖倒置\n- **DRY**：不要重复自己\n- **KISS**：保持简单', 'PHP设计模式实战教程，包含单例、工厂、观察者等模式的代码示例。', 1, 1, 1, 156, 0, 1, NOW(), NOW()),
('MySQL性能优化技巧', 'mysql-performance-optimization', '## MySQL性能优化技巧\n\nMySQL是PHP应用最常用的数据库。\n\n### 索引优化\n\n#### 选择合适的索引类型\n- **B-Tree索引**：最常用，适用于等值和范围查询\n- **全文索引**：用于文本搜索\n- **哈希索引**：内存表快速查询\n\n```sql\n-- 创建复合索引\nCREATE INDEX idx_category_status ON articles(category_id, status);\n\n-- 使用覆盖索引\nSELECT id, title FROM articles WHERE category_id = 1;\n```\n\n### 查询优化\n\n#### 避免SELECT *\n只选择需要的字段。\n\n```sql\n-- 不推荐\nSELECT * FROM articles;\n\n-- 推荐\nSELECT id, title, created_at FROM articles;\n```\n\n#### 使用EXPLAIN分析\n```sql\nEXPLAIN SELECT * FROM articles WHERE category_id = 1;\n```\n\n### 配置优化\n\n```ini\n# my.cnf 关键配置\ninnodb_buffer_pool_size = 1G\nquery_cache_size = 128M\nmax_connections = 500\ninnodb_log_file_size = 256M\n```\n\n### 监控工具\n\n- **MySQL Slow Query Log**\n- **Percona Toolkit**\n- **phpMyAdmin** 状态监控\n\n> 📊 优化后效果：查询速度提升 **80%**', 'MySQL性能优化实战，包括索引优化、查询优化和服务器配置优化。', 1, 1, 1, 98, 0, 0, NOW(), NOW());

-- 插入友情链接
INSERT INTO `links` (`name`, `url`, `description`, `sort_order`, `is_show`, `created_at`) VALUES
('PHP官方', 'https://php.net', 'PHP官方文档和资源', 1, 1, NOW()),
('MySQL官方', 'https://mysql.com', 'MySQL数据库官方', 2, 1, NOW()),
('Redis官方', 'https://redis.io', 'Redis缓存数据库官方', 3, 1, NOW()),
('GitHub', 'https://github.com', '全球最大的代码托管平台', 4, 1, NOW());

-- 插入系统配置
INSERT INTO `settings` (`key`, `value`, `group`, `type`, `label`, `description`, `sort_order`, `is_system`) VALUES
('site_name', '我的CMS系统', 'general', 'text', '网站名称', '网站的显示名称', 1, 1),
('site_description', '基于PHP+MySQL+Redis的CMS系统', 'general', 'text', '网站描述', '网站简短描述', 2, 1),
('site_keywords', 'CMS,PHP,MySQL,Redis,内容管理', 'general', 'text', '网站关键词', 'SEO关键词', 3, 1),
('site_logo', '', 'general', 'image', 'Logo', '网站Logo图片', 4, 1),
('site_footer', '© 2026 我的CMS系统 | Powered by PHP', 'general', 'textarea', '页脚信息', '网站底部版权信息', 5, 1),
('admin_email', 'admin@example.com', 'general', 'text', '管理员邮箱', '系统通知邮箱', 6, 1),
('per_page', '10', 'general', 'number', '每页数量', '列表页每页显示数量', 7, 1);

-- 重置自增起始值
ALTER TABLE `articles` AUTO_INCREMENT = 1000;
ALTER TABLE `categories` AUTO_INCREMENT = 1000;
ALTER TABLE `users` AUTO_INCREMENT = 1000;
ALTER TABLE `comments` AUTO_INCREMENT = 1000;
ALTER TABLE `pages` AUTO_INCREMENT = 1000;
ALTER TABLE `links` AUTO_INCREMENT = 1000;
ALTER TABLE `settings` AUTO_INCREMENT = 1000;

SET FOREIGN_KEY_CHECKS = 1;

-- ======================================================
-- 安装完成！
-- 默认管理员账号: admin
-- 默认管理员密码: admin123
-- ======================================================