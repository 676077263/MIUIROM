<?php
/**
 * MIUIROM - 搜索页面
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';

Database::getInstance();

$keyword = trim($_GET['q'] ?? '');
$results = [];
$searched = false;

if (!empty($keyword)) {
    $searched = true;
    $results = DeviceList::search($keyword);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索 - <?= SITE_NAME ?></title>
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
                <a href="weekly.php">橙色星期五</a>
                <a href="tools.php">刷机工具</a>
                <a href="search.php" class="active">搜索</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1>搜索ROM</h1>
        </div>

        <div class="search-box-large">
            <form action="search.php" method="GET">
                <input type="text" name="q" value="<?= Utils::h($keyword) ?>" placeholder="输入设备名称、型号、代号或版本号..." class="search-input-large">
                <button type="submit" class="btn btn-primary btn-large">搜索</button>
            </form>
            <p class="search-hint">支持搜索: 设备名称 (如 "Xiaomi 12")、设备代号 (如 "cupid")、型号 (如 "2201123C")、版本号 (如 "V14.0.9")</p>
        </div>

        <?php if ($searched): ?>
        <div class="search-results">
            <h2>搜索结果: "<?= Utils::h($keyword) ?>"</h2>
            <p>共找到 <?= count($results) ?> 个ROM</p>

            <?php if (empty($results)): ?>
            <div class="empty-state">
                <p>未找到匹配的ROM，请尝试其他关键词</p>
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
                            <th>日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $rom): ?>
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