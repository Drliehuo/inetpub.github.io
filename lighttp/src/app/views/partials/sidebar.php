<?php
$categoryModel = new \App\models\Category();
$categories = $categoryModel->findAll();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentUri = strtok($currentUri, '?');
$currentUri = rtrim($currentUri, '/') ?: '/';
$app = \App\core\Application::getInstance();
$isLoggedIn = $app->isLoggedIn();
$settingModel = new \App\models\Setting();
$siteName = $settingModel->get('site_name') ?? 'Lighttp';
?>
<div class="list-group">
    <a href="/" class="list-group-item<?php echo ($currentUri === '/') ? ' active' : ''; ?>">所有文章</a>
    <?php foreach ($categories as $cat):
        $catUri = '/category/' . $cat['slug'];
        $isActive = ($currentUri === $catUri);
    ?>
    <a href="<?php echo htmlspecialchars($catUri); ?>" class="list-group-item<?php echo $isActive ? ' active' : ''; ?>">
        <?php echo htmlspecialchars($cat['name']); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php if ($isLoggedIn): ?>
<div class="panel panel-default">
    <div class="panel-heading">快捷操作</div>
    <div class="list-group">
        <a href="/admin/article/create" class="list-group-item"><span class="glyphicon glyphicon-plus"></span> 新建文章</a>
        <a href="/admin" class="list-group-item"><span class="glyphicon glyphicon-dashboard"></span> 管理后台</a>
        <a href="/admin/profile" class="list-group-item"><span class="glyphicon glyphicon-user"></span> 个人资料</a>
    </div>
</div>
<?php else: ?>
<div class="panel panel-default">
    <div class="panel-heading">用户中心</div>
    <div class="list-group">
        <a href="/login" class="list-group-item"><span class="glyphicon glyphicon-log-in"></span> 登录</a>
        <a href="/register" class="list-group-item"><span class="glyphicon glyphicon-edit"></span> 注册</a>
    </div>
</div>
<?php endif; ?>