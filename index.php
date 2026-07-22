<?php
/**
 * MIUIROM - 首页
 * 
 * 展示站点概览：最新ROM更新、统计信息、热门设备等
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Utils.php';
require_once __DIR__ . '/includes/DeviceList.php';

Database::getInstance();
$stats = DeviceList::getStats();
$latestRoms = DeviceList::getLatestRoms(12);
$devices = DeviceList::getAll();
$regionStats = DeviceList::getRegionStats();

// 品牌分组
$brands = ['Xiaomi' => [], 'Redmi' => [], 'POCO' => []];
foreach ($devices as $d) {
    $brands[$d['brand']][] = $d;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - 小米官方ROM镜像收集站</title>
    <meta name="description" content="<?= SITE_DESC ?>">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo">
                <span class="logo-icon">MI</span>
                <span class="logo-text">MIUIROM</span>
            </a>
            <div class="nav-links">
                <a href="/" class="active">首页</a>
                <a href="pages/devices.php">机型列表</a>
                <a href="pages/weekly.php">橙色星期五</a>
                <a href="pages/tools.php">刷机工具</a>
                <a href="pages/search.php">搜索</a>
            </div>
            <div class="nav-search">
                <form action="pages/search.php" method="GET">
                    <input type="text" name="q" placeholder="搜索设备或ROM..." class="search-input">
                    <button type="submit" class="search-btn">&#128269;</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- 头部横幅 -->
    <header class="hero">
        <div class="container">
            <h1>小米官方ROM镜像收集站</h1>
            <p class="hero-subtitle">提供 MIUI / HyperOS 官方刷机包直链下载，支持 Recovery 卡刷包、Fastboot 线刷包、OTA 增量包</p>
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['total_devices'] ?></span>
                    <span class="stat-label">台设备</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['total_roms'] ?></span>
                    <span class="stat-label">个ROM</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count($regionStats) ?></span>
                    <span class="stat-label">个地区</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $stats['total_size'] ?></span>
                    <span class="stat-label">数据总量</span>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- 最新ROM更新 -->
        <section class="section">
            <div class="section-header">
                <h2>最新ROM更新</h2>
                <a href="pages/devices.php" class="more-link">查看全部 &raquo;</a>
            </div>
            <?php if (!empty($latestRoms)): ?>
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
                        <?php foreach ($latestRoms as $rom): ?>
                        <tr>
                            <td>
                                <a href="pages/device.php?codename=<?= urlencode($rom['codename']) ?>" class="device-link">
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
            <?php else: ?>
            <div class="empty-state">
                <p>暂无ROM数据，请先运行采集脚本</p>
                <code>php cron/collect.php</code>
            </div>
            <?php endif; ?>
        </section>

        <!-- 地区分布 -->
        <section class="section">
            <div class="section-header">
                <h2>按地区浏览</h2>
            </div>
            <div class="region-grid">
                <?php foreach ($regionStats as $r): ?>
                <a href="pages/devices.php?region=<?= urlencode($r['region']) ?>" class="region-card">
                    <span class="region-name"><?= Utils::getRegionName($r['region']) ?></span>
                    <span class="region-count"><?= $r['cnt'] ?> 个ROM</span>
                    <span class="region-devices"><?= $r['devices'] ?> 台设备</span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 品牌设备列表 -->
        <?php foreach ($brands as $brand => $brandDevices): ?>
        <?php if (!empty($brandDevices)): ?>
        <section class="section">
            <div class="section-header">
                <h2><?= $brand ?> 设备</h2>
                <span class="device-count"><?= count($brandDevices) ?> 台</span>
            </div>
            <div class="device-grid">
                <?php foreach (array_slice($brandDevices, 0, 12) as $dev): ?>
                <a href="pages/device.php?codename=<?= urlencode($dev['codename']) ?>" class="device-card">
                    <span class="device-name"><?= Utils::h($dev['model_name']) ?></span>
                    <span class="device-codename"><?= Utils::h($dev['codename']) ?></span>
                    <?php if ($dev['status'] === 'eol'): ?>
                    <span class="badge badge-eol">已停更</span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if (count($brandDevices) > 12): ?>
            <div class="show-more">
                <a href="pages/devices.php?brand=<?= urlencode($brand) ?>" class="more-link">查看更多 <?= $brand ?> 设备 &raquo;</a>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- 使用说明 -->
        <section class="section info-section">
            <h2>使用说明</h2>
            <div class="info-grid">
                <div class="info-card">
                    <h3>查找设备</h3>
                    <p>在搜索框中输入设备名称或型号，或者浏览机型列表找到您的设备。</p>
                </div>
                <div class="info-card">
                    <h3>选择ROM</h3>
                    <p>根据您的需求选择稳定版或开发版，以及卡刷包(Recovery)或线刷包(Fastboot)。</p>
                </div>
                <div class="info-card">
                    <h3>下载刷入</h3>
                    <p>点击下载获取直链，使用对应工具刷入。卡刷包通过系统更新刷入，线刷包通过MiFlash工具刷入。</p>
                </div>
                <div class="info-card">
                    <h3>注意事项</h3>
                    <p>刷机有风险，操作前请备份数据。请确保下载的ROM与您的设备型号和地区完全匹配。</p>
                </div>
            </div>
        </section>
    </main>

    <!-- 页脚 -->
    <footer class="footer">
        <div class="container">
            <p>MIUIROM &copy; <?= date('Y') ?> - 小米官方ROM镜像收集站</p>
            <p class="footer-desc">本站所有ROM文件均来自小米官方服务器，未做任何修改。本站仅提供链接索引服务。</p>
            <p class="footer-desc">本站与小米公司无关，仅为个人公益项目。</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>