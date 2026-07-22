<?php
/**
 * MIUIROM - 设备列表页
 * 
 * 支持按品牌、地区、类型等筛选设备
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';

Database::getInstance();
$devices = DeviceList::getAll();

$brand = $_GET['brand'] ?? '';
$category = $_GET['category'] ?? '';
$region = $_GET['region'] ?? '';

// 筛选
$filteredDevices = $devices;
if ($brand) {
    $filteredDevices = array_filter($filteredDevices, fn($d) => $d['brand'] === $brand);
}
if ($category) {
    $filteredDevices = array_filter($filteredDevices, fn($d) => $d['category'] === $category);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>机型列表 - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo">
                <span class="logo-icon">MI</span>
                <span class="logo-text">MIUIROM</span>
            </a>
            <div class="nav-links">
                <a href="/">首页</a>
                <a href="devices.php" class="active">机型列表</a>
                <a href="weekly.php">橙色星期五</a>
                <a href="tools.php">刷机工具</a>
                <a href="search.php">搜索</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1>机型列表</h1>
            <p>共 <?= count($filteredDevices) ?> 台设备</p>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">品牌:</span>
                <a href="devices.php" class="filter-item <?= !$brand ? 'active' : '' ?>">全部</a>
                <a href="devices.php?brand=Xiaomi" class="filter-item <?= $brand === 'Xiaomi' ? 'active' : '' ?>">Xiaomi</a>
                <a href="devices.php?brand=Redmi" class="filter-item <?= $brand === 'Redmi' ? 'active' : '' ?>">Redmi</a>
                <a href="devices.php?brand=POCO" class="filter-item <?= $brand === 'POCO' ? 'active' : '' ?>">POCO</a>
            </div>
            <div class="filter-group">
                <span class="filter-label">类别:</span>
                <a href="devices.php<?= $brand ? "?brand=$brand" : '' ?>" class="filter-item <?= !$category ? 'active' : '' ?>">全部</a>
                <a href="devices.php?<?= $brand ? "brand=$brand&" : '' ?>category=phone" class="filter-item <?= $category === 'phone' ? 'active' : '' ?>">手机</a>
                <a href="devices.php?<?= $brand ? "brand=$brand&" : '' ?>category=tablet" class="filter-item <?= $category === 'tablet' ? 'active' : '' ?>">平板</a>
                <a href="devices.php?<?= $brand ? "brand=$brand&" : '' ?>category=foldable" class="filter-item <?= $category === 'foldable' ? 'active' : '' ?>">折叠屏</a>
            </div>
        </div>

        <!-- 设备列表 -->
        <div class="device-grid large">
            <?php foreach ($filteredDevices as $dev): ?>
            <a href="device.php?codename=<?= urlencode($dev['codename']) ?>" class="device-card">
                <span class="device-brand"><?= Utils::h($dev['brand']) ?></span>
                <span class="device-name"><?= Utils::h($dev['model_name']) ?></span>
                <span class="device-codename"><?= Utils::h($dev['codename']) ?></span>
                <?php if (!empty($dev['model_number'])): ?>
                <span class="device-model"><?= Utils::h($dev['model_number']) ?></span>
                <?php endif; ?>
                <div class="device-tags">
                    <span class="tag tag-category"><?= Utils::h($dev['category']) ?></span>
                    <?php if ($dev['status'] === 'eol'): ?>
                    <span class="tag tag-eol">已停更</span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>MIUIROM &copy; <?= date('Y') ?></p>
        </div>
    </footer>
</body>
</html>