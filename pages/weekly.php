<?php
/**
 * MIUIROM - 橙色星期五（开发版/周更ROM）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';

Database::getInstance();

$weeklyRoms = Database::query(
    "SELECT r.*, d.model_name, d.brand, d.category 
     FROM roms r 
     LEFT JOIN devices d ON r.device_id = d.id 
     WHERE r.is_active = 1 AND (r.branch = 'developer' OR r.branch = 'weekly')
     ORDER BY r.release_date DESC 
     LIMIT 100"
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>橙色星期五 - <?= SITE_NAME ?></title>
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
                <a href="devices.php">机型列表</a>
                <a href="weekly.php" class="active">橙色星期五</a>
                <a href="tools.php">刷机工具</a>
                <a href="search.php">搜索</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1>橙色星期五</h1>
            <p>MIUI / HyperOS 开发版公测ROM，每周五更新</p>
        </div>

        <div class="info-banner">
            <strong>提示:</strong> 开发版ROM面向尝鲜用户，可能存在不稳定因素。刷入前请备份数据。稳定使用请选择稳定版。
        </div>

        <?php if (empty($weeklyRoms)): ?>
        <div class="empty-state">
            <p>暂无开发版ROM数据</p>
        </div>
        <?php else: ?>
        <div class="rom-table-wrapper">
            <table class="rom-table">
                <thead>
                    <tr>
                        <th>设备</th>
                        <th>版本</th>
                        <th>系统</th>
                        <th>地区</th>
                        <th>类型</th>
                        <th>大小</th>
                        <th>发布日期</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeklyRoms as $rom): ?>
                    <tr>
                        <td>
                            <a href="device.php?codename=<?= urlencode($rom['codename']) ?>" class="device-link">
                                <strong><?= Utils::h($rom['model_name'] ?? $rom['codename']) ?></strong>
                                <small class="codename">(<?= Utils::h($rom['codename']) ?>)</small>
                            </a>
                        </td>
                        <td><code class="version"><?= Utils::h($rom['version']) ?></code></td>
                        <td><span class="badge badge-<?= $rom['os_type'] ?>"><?= Utils::getOsTypeName($rom['os_type']) ?></span></td>
                        <td><?= Utils::getRegionName($rom['region']) ?></td>
                        <td><span class="badge badge-flash"><?= Utils::getFlashTypeName($rom['flash_type']) ?></span></td>
                        <td><?= Utils::formatSize($rom['file_size']) ?></td>
                        <td><?= Utils::h($rom['release_date']) ?></td>
                        <td>
                            <a href="<?= Utils::h($rom['download_url']) ?>" class="btn btn-sm btn-download" target="_blank" rel="nofollow">下载</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>MIUIROM &copy; <?= date('Y') ?></p>
        </div>
    </footer>
</body>
</html>