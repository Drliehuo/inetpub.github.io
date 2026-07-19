<?php declare(strict_types=1);
$app = \App\core\Application::getInstance();
$isLoggedIn = $app->isLoggedIn();
// 从数据库获取网站名称，缓存 60 秒
$cache = $app->getCache();
$siteName = 'Lighttp';
if ($cache && $cache->has('site_name')) {
    $siteName = $cache->get('site_name');
} else {
    $settingModel = new \App\models\Setting();
    $siteName = $settingModel->get('site_name') ?? 'Lighttp';
    if ($cache) {
        $cache->set('site_name', $siteName, 60);
    }
}
?>
<header class="site-header">
    <div class="container">
        <a href="/" class="logo"><?php echo htmlspecialchars($siteName); ?></a>
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <ul class="nav-links" id="navLinks">
            <li><a href="/">Home</a></li>
            <?php if ($isLoggedIn): ?>
                <li><a href="/admin">Admin</a></li>
                <li><a href="/admin/profile">Profile</a></li>
                <li><a href="/logout">Logout</a></li>
            <?php else: ?>
                <li><a href="/login">Login</a></li>
                <li><a href="/register" class="nav-cta">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>