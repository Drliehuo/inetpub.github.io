<?php declare(strict_types=1);
$app = \App\core\Application::getInstance();
$isLoggedIn = $app->isLoggedIn();

// 直接从数据库读取，移除独立缓存
$settingModel = new \App\models\Setting();
$siteName = $settingModel->get('site_name') ?? 'Lighttp';
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
<script>
(function() {
    var header = document.querySelector('.site-header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, { passive: true });
    }
})();
</script>