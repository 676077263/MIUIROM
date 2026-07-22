<?php
/**
 * MIUIROM - 设备详情页
 * 
 * 展示单个设备的所有可用ROM，支持按地区、版本、分支、刷机类型筛选
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';

Database::getInstance();

$codename = $_GET['codename'] ?? '';
if (empty($codename)) {
    header('Location: devices.php');
    exit;
}

$device = DeviceList::getByCodename($codename);
if (!$device) {
    http_response_code(404);
    echo "设备不存在";
    exit;
}

$filters = [];
if (!empty($_GET['region'])) $filters['region'] = $_GET['region'];
if (!empty($_GET['os_type'])) $filters['os_type'] = $_GET['os_type'];
if (!empty($_GET['branch'])) $filters['branch'] = $_GET['branch'];
if (!empty($_GET['flash_type'])) $filters['flash_type'] = $_GET['flash_type'];

$roms = DeviceList::getRoms($codename, $filters);

// 获取该设备的地区列表
$deviceRegions = Database::query(
    "SELECT DISTINCT region, COUNT(*) as cnt FROM roms WHERE codename = ? AND is_active = 1 GROUP BY region ORDER BY cnt DESC",
    [$codename]
);

// 按地区分组ROM
$groupedRoms = [];
foreach ($roms as $rom) {
    $groupedRoms[$rom['region']][] = $rom;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Utils::h($device['model_name']) ?> ROM下载 - <?= SITE_NAME ?></title>
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
                <a href="search.php">搜索</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <!-- 设备信息 -->
        <div class="device-header">
            <div class="breadcrumb">
                <a href="/">首页</a> &raquo;
                <a href="devices.php">机型列表</a> &raquo;
                <span><?= Utils::h($device['model_name']) ?></span>
            </div>
            <div class="device-info">
                <h1><?= Utils::h($device['model_name']) ?></h1>
                <div class="device-meta">
                    <span class="meta-item">品牌: <strong><?= Utils::h($device['brand']) ?></strong></span>
                    <span class="meta-item">代号: <code><?= Utils::h($device['codename']) ?></code></span>
                    <?php if (!empty($device['model_number'])): ?>
                    <span class="meta-item">型号: <strong><?= Utils::h($device['model_number']) ?></strong></span>
                    <?php endif; ?>
                    <span class="meta-item">类别: <?= Utils::h($device['category']) ?></span>
                    <?php if ($device['status'] === 'eol'): ?>
                    <span class="badge badge-eol">已停止支持</span>
                    <?php endif; ?>
                </div>
                <p class="device-total">共找到 <strong><?= count($roms) ?></strong> 个ROM</p>
            </div>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">地区:</span>
                <a href="device.php?codename=<?= urlencode($codename) ?>" class="filter-item <?= empty($_GET['region']) ? 'active' : '' ?>">全部</a>
                <?php foreach ($deviceRegions as $dr): ?>
                <a href="device.php?codename=<?= urlencode($codename) ?>&region=<?= urlencode($dr['region']) ?>" class="filter-item <?= ($_GET['region'] ?? '') === $dr['region'] ? 'active' : '' ?>">
                    <?= Utils::getRegionName($dr['region']) ?> (<?= $dr['cnt'] ?>)
                </a>
                <?php endforeach; ?>
            </div>
            <div class="filter-group">
                <span class="filter-label">系统:</span>
                <a href="device.php?codename=<?= urlencode($codename) ?><?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?>" class="filter-item <?= empty($_GET['os_type']) ? 'active' : '' ?>">全部</a>
                <a href="device.php?codename=<?= urlencode($codename) ?>&os_type=hyperos<?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?>" class="filter-item <?= ($_GET['os_type'] ?? '') === 'hyperos' ? 'active' : '' ?>">HyperOS</a>
                <a href="device.php?codename=<?= urlencode($codename) ?>&os_type=miui<?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?>" class="filter-item <?= ($_GET['os_type'] ?? '') === 'miui' ? 'active' : '' ?>">MIUI</a>
            </div>
            <div class="filter-group">
                <span class="filter-label">分支:</span>
                <a href="device.php?codename=<?= urlencode($codename) ?><?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?><?= !empty($_GET['os_type']) ? '&os_type=' . urlencode($_GET['os_type']) : '' ?>" class="filter-item <?= empty($_GET['branch']) ? 'active' : '' ?>">全部</a>
                <a href="device.php?codename=<?= urlencode($codename) ?>&branch=stable<?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?><?= !empty($_GET['os_type']) ? '&os_type=' . urlencode($_GET['os_type']) : '' ?>" class="filter-item <?= ($_GET['branch'] ?? '') === 'stable' ? 'active' : '' ?>">稳定版</a>
                <a href="device.php?codename=<?= urlencode($codename) ?>&branch=developer<?= !empty($_GET['region']) ? '&region=' . urlencode($_GET['region']) : '' ?><?= !empty($_GET['os_type']) ? '&os_type=' . urlencode($_GET['os_type']) : '' ?>" class="filter-item <?= ($_GET['branch'] ?? '') === 'developer' ? 'active' : '' ?>">开发版</a>
            </div>
        </div>

        <!-- ROM列表 -->
        <?php if (empty($groupedRoms)): ?>
        <div class="empty-state">
            <p>该设备暂无ROM数据</p>
        </div>
        <?php else: ?>
        <?php foreach ($groupedRoms as $reg => $regionRoms): ?>
        <section class="section rom-section">
            <h3 class="rom-region-title">
                <?= Utils::getRegionName($reg) ?> ROM
                <span class="rom-count">(<?= count($regionRoms) ?> 个)</span>
            </h3>
            <div class="rom-table-wrapper">
                <table class="rom-table">
                    <thead>
                        <tr>
                            <th>版本</th>
                            <th>系统</th>
                            <th>Android</th>
                            <th>分支</th>
                            <th>类型</th>
                            <th>大小</th>
                            <th>发布日期</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regionRoms as $rom): ?>
                        <tr>
                            <td><code class="version"><?= Utils::h($rom['version']) ?></code></td>
                            <td><span class="badge badge-<?= $rom['os_type'] ?>"><?= Utils::getOsTypeName($rom['os_type']) ?></span></td>
                            <td><?= Utils::h($rom['android_version']) ?></td>
                            <td><?= Utils::getBranchName($rom['branch']) ?></td>
                            <td><span class="badge badge-flash"><?= Utils::getFlashTypeName($rom['flash_type']) ?></span></td>
                            <td><?= Utils::formatSize($rom['file_size']) ?></td>
                            <td><?= Utils::h($rom['release_date']) ?></td>
                            <td>
                                <a href="<?= Utils::h($rom['download_url']) ?>" class="btn btn-sm btn-download" target="_blank" rel="nofollow">下载</a>
                                <?php if (!empty($rom['md5_checksum'])): ?>
                                <button class="btn btn-sm btn-md5" title="MD5: <?= Utils::h($rom['md5_checksum']) ?>">MD5</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>MIUIROM &copy; <?= date('Y') ?></p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>