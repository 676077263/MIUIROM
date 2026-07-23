<?php
/**
 * MIUIROM - ROM数据API接口
 * 
 * 提供RESTful风格的API接口，返回JSON格式的ROM数据。
 * 支持多种查询参数实现灵活的数据筛选。
 * 
 * 端点:
 *   GET /api/roms.php              - 获取ROM列表
 *   GET /api/roms.php?device=xxx   - 获取指定设备ROM
 *   GET /api/roms.php?latest=1     - 获取最新ROM
 *   GET /api/roms.php?search=xxx   - 搜索ROM
 *   GET /api/roms.php?stats=1      - 获取统计信息
 * 
 * 查询参数:
 *   device     - 设备代号 (如: cupid, marble)
 *   region     - 地区代码 (CN, MI, EU, IN, RU, etc.)
 *   os_type    - 系统类型 (miui, hyperos)
 *   branch     - 分支 (stable, developer, weekly)
 *   flash_type - 刷机类型 (recovery, fastboot, ota)
 *   latest     - 是否只获取最新版本 (1/0)
 *   search     - 搜索关键词
 *   page       - 页码 (从1开始)
 *   limit      - 每页数量 (默认50)
 *   stats      - 获取统计信息 (1/0)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=300');

// 初始化数据库
Database::getInstance();

// 获取统计信息
if (isset($_GET['stats'])) {
    $stats = DeviceList::getStats();
    $regionStats = DeviceList::getRegionStats();
    $branchStats = DeviceList::getBranchStats();
    
    echo json_encode([
        'success' => true,
        'data'    => [
            'stats'   => $stats,
            'regions' => $regionStats,
            'branches'=> $branchStats,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 搜索
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $keyword = trim($_GET['search']);
    $results = DeviceList::search($keyword);
    
    echo json_encode([
        'success' => true,
        'data'    => [
            'keyword' => $keyword,
            'count'   => count($results),
            'roms'    => $results,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 获取最新ROM
if (isset($_GET['latest'])) {
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $filters = [];
    
    if (!empty($_GET['region'])) $filters['region'] = $_GET['region'];
    if (!empty($_GET['os_type'])) $filters['os_type'] = $_GET['os_type'];
    if (!empty($_GET['branch'])) $filters['branch'] = $_GET['branch'];
    
    $roms = DeviceList::getLatestRoms($limit, $filters);
    
    echo json_encode([
        'success' => true,
        'data'    => [
            'count' => count($roms),
            'roms'  => $roms,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 获取指定设备ROM
if (isset($_GET['device']) && !empty($_GET['device'])) {
    $codename = trim($_GET['device']);
    $filters = [];
    
    if (!empty($_GET['region'])) $filters['region'] = $_GET['region'];
    if (!empty($_GET['os_type'])) $filters['os_type'] = $_GET['os_type'];
    if (!empty($_GET['branch'])) $filters['branch'] = $_GET['branch'];
    if (!empty($_GET['flash_type'])) $filters['flash_type'] = $_GET['flash_type'];
    
    $device = DeviceList::getByCodename($codename);
    $roms = DeviceList::getRoms($codename, $filters);
    
    echo json_encode([
        'success' => true,
        'data'    => [
            'device' => $device,
            'count'  => count($roms),
            'roms'   => $roms,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 获取所有ROM列表（分页）
$page  = max((int)($_GET['page'] ?? 1), 1);
$limit = min((int)($_GET['limit'] ?? PAGE_SIZE), 100);
$offset = ($page - 1) * $limit;

$filters = [];
$where = "WHERE r.is_active = 1";
$params = [];

if (!empty($_GET['region'])) {
    $where .= " AND r.region = ?";
    $params[] = $_GET['region'];
}
if (!empty($_GET['os_type'])) {
    $where .= " AND r.os_type = ?";
    $params[] = $_GET['os_type'];
}
if (!empty($_GET['branch'])) {
    $where .= " AND r.branch = ?";
    $params[] = $_GET['branch'];
}
if (!empty($_GET['flash_type'])) {
    $where .= " AND r.flash_type = ?";
    $params[] = $_GET['flash_type'];
}

$totalParams = $params;
$total = Database::scalar(
    "SELECT COUNT(*) FROM roms r {$where}",
    $totalParams
);

$params[] = $limit;
$params[] = $offset;

$roms = Database::query(
    "SELECT r.*, d.model_name, d.brand, d.category 
     FROM roms r 
     LEFT JOIN devices d ON r.device_id = d.id 
     {$where} 
     ORDER BY r.release_date DESC 
     LIMIT ? OFFSET ?",
    $params
);

echo json_encode([
    'success' => true,
    'data'    => [
        'total'    => (int) $total,
        'page'     => $page,
        'limit'    => $limit,
        'pages'    => ceil($total / $limit),
        'roms'     => $roms,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
