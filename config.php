<?php
/**
 * MIUIROM - 小米官方ROM镜像收集站
 * 全局配置文件
 * 
 * @package  MIUIROM
 * @version  1.0.0
 * @requires PHP 7.2+
 */

define('MIUIROM_VERSION', '1.0.0');
define('MIUIROM_ROOT', __DIR__);
define('MIUIROM_DATA', MIUIROM_ROOT . '/data');
define('MIUIROM_DB', MIUIROM_DATA . '/miuirom.db');

// 数据库配置
define('DB_DSN', 'sqlite:' . MIUIROM_DB);

// 站点配置
define('SITE_NAME', 'MIUIROM');
define('SITE_DESC', '小米官方ROM镜像收集站 - 提供MIUI/HyperOS官方刷机包直链下载');
define('SITE_URL', 'https://miuirom.example.com');
define('SITE_LANG', 'zh-CN');

// 小米官方服务器地址
define('XIAOMI_OTA_HOSTS', [
    'bigota.d.miui.com',
    'ultimateota.d.miui.com',
    'bn.d.miui.com',
    'cdnorg.d.miui.com',
]);

// 小米官方API端点
define('XIAOMI_API_ROM_CHECK', 'https://update.miui.com/updates/v1/fullromcheck.php');
define('XIAOMI_FIRMWARE_UPDATER_JSON', 'https://raw.githubusercontent.com/XiaomiFirmwareUpdater/miui-updates-tracker/master/data/latest.yml');

// 镜像收集策略
define('COLLECT_SOURCES', [
    'xiaomi_api'  => false, // 小米官方OTA API (已下线，返回404)
    'github_json' => true,  // GitHub ROM追踪JSON
    'direct_scan' => false, // 直接扫描服务器（谨慎使用）
]);

// 地域代码映射
define('REGION_MAP', [
    'CN' => ['name' => '中国', 'flag' => 'cn'],
    'MI' => ['name' => '全球', 'flag' => 'global'],
    'EU' => ['name' => '欧洲', 'flag' => 'eu'],
    'IN' => ['name' => '印度', 'flag' => 'in'],
    'RU' => ['name' => '俄罗斯', 'flag' => 'ru'],
    'ID' => ['name' => '印尼', 'flag' => 'id'],
    'TW' => ['name' => '台湾', 'flag' => 'tw'],
    'TR' => ['name' => '土耳其', 'flag' => 'tr'],
    'JP' => ['name' => '日本', 'flag' => 'jp'],
    'KR' => ['name' => '韩国', 'flag' => 'kr'],
]);

// 刷机类型
define('FLASH_TYPES', [
    'recovery' => '卡刷包 (Recovery)',
    'fastboot' => '线刷包 (Fastboot)',
    'ota'      => 'OTA增量包',
]);

// 分支类型
define('BRANCH_TYPES', [
    'stable'    => '稳定版',
    'developer' => '开发版',
    'weekly'    => '橙色星期五',
]);

// 系统类型
define('OS_TYPES', [
    'miui'    => 'MIUI',
    'hyperos' => 'HyperOS',
]);

// 缓存时间（秒）
define('CACHE_TTL_DEVICES', 86400);      // 设备列表缓存24小时
define('CACHE_TTL_ROMS', 3600);          // ROM列表缓存1小时

// 请求超时（秒）
define('HTTP_TIMEOUT', 120);

// 分页
define('PAGE_SIZE', 50);

// 时区
date_default_timezone_set('Asia/Shanghai');

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', MIUIROM_DATA . '/error.log');
