<?php
/**
 * MIUIROM - 定时采集脚本
 * 
 * 通过crontab定时执行，每日自动采集最新的MIUI/HyperOS官方ROM镜像。
 * 
 * 运行方式:
 *   php cron/collect.php
 * 
 * Crontab配置示例 (每天凌晨2点和下午2点执行):
 *   0 2,14 * * * /usr/bin/php /path/to/MIUIROM/cron/collect.php >> /path/to/MIUIROM/data/cron.log 2>&1
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Utils.php';
require_once __DIR__ . '/../includes/DeviceList.php';
require_once __DIR__ . '/../includes/Collector.php';

// 确保数据目录存在
if (!is_dir(MIUIROM_DATA)) {
    mkdir(MIUIROM_DATA, 0755, true);
}

echo "╔══════════════════════════════════════════════╗\n";
echo "║       MIUIROM - ROM镜像定时采集任务          ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

echo "[" . date('Y-m-d H:i:s') . "] 开始执行采集任务...\n\n";

try {
    // 初始化数据库
    Database::getInstance();
    
    // 导入设备列表
    $imported = DeviceList::importBuiltinDevices();
    echo "设备导入: {$imported} 台\n\n";
    
    // 执行采集
    $collector = new Collector();
    $result = $collector->collect();
    
    echo "采集完成!\n";
    echo "  发现ROM: {$result['roms_found']} 个\n";
    echo "  新增ROM: {$result['roms_new']} 个\n";
    echo "  耗时: {$result['duration_ms']} ms\n";
    
    // 统计信息
    $stats = DeviceList::getStats();
    echo "\n当前数据库统计:\n";
    echo "  设备总数: {$stats['total_devices']} 台\n";
    echo "  ROM总数: {$stats['total_roms']} 个\n";
    echo "  总大小: {$stats['total_size']}\n";
    echo "  最后更新: {$stats['latest_update']}\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    Utils::log("采集任务失败: " . $e->getMessage(), 'ERROR');
    exit(1);
}

echo "\n[" . date('Y-m-d H:i:s') . "] 采集任务完成\n";
