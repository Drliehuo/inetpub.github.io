<!-- Lighttp v1.0.9 - Footer Partial -->
<?php
$settingModel = new App\models\Setting();
$siteFooter = $settingModel->get('site_footer') ?? '';
$siteName = $settingModel->get('site_name') ?? 'Lighttp';
?>
<footer class="site-footer">
    <div class="container">
        <span class="footer-brand"><?php echo htmlspecialchars($siteName); ?></span>
        <span class="footer-copy"><?php echo htmlspecialchars($siteFooter); ?></span>
        <span class="footer-dev">Powered by <a href="https://www.inetpub.cn/lighttp">Lighttp</a></span>
    </div>
</footer>