# MIUIROM — 项目代码技术文档 (Code Wiki)

> **版本**: 1.0.0  
> **更新日期**: 2026-07-22  
> **适用范围**: MIUIROM 全部代码模块  

---

## 目录

1. [项目概述](#1-项目概述)
2. [整体架构](#2-整体架构)
3. [目录结构详解](#3-目录结构详解)
4. [核心模块说明](#4-核心模块说明)
   - [4.1 config.php — 全局配置](#41-configphp--全局配置)
   - [4.2 Database.php — 数据库层](#42-databasephp--数据库层)
   - [4.3 Utils.php — 工具函数](#43-utilsphp--工具函数)
   - [4.4 DeviceList.php — 设备管理](#44-devicelistphp--设备管理)
   - [4.5 Collector.php — ROM采集器](#45-collectorphp--rom采集器)
5. [数据库设计](#5-数据库设计)
6. [API 接口文档](#6-api-接口文档)
7. [前端页面说明](#7-前端页面说明)
8. [定时任务](#8-定时任务)
9. [ROM采集技术方案](#9-rom采集技术方案)
10. [部署与运维](#10-部署与运维)
11. [依赖关系图](#11-依赖关系图)
12. [数据流与调用链](#12-数据流与调用链)

---

## 1. 项目概述

### 1.1 项目定位

MIUIROM 是一个基于 PHP 7.2+ 的 MIUI/HyperOS 官方 ROM 镜像链接收集系统，采用 SQLite 作为数据存储，通过定时任务自动采集小米官方服务器上的 ROM 信息，并提供 Web 前端浏览和 API 查询接口。

### 1.2 设计理念

本项目参考了两个知名网站的设计：

| 参考网站 | 借鉴要点 |
|----------|---------|
| [roms.miuier.com](https://roms.miuier.com/zh-cn) | ROM 分类方式、设备列表组织、开发版专区 |
| [MSDN 我告诉你](https://msdn.itellyou.cn/) | 简洁清晰的目录式资源组织、中国风排版 |

### 1.3 技术选型理由

| 选型 | 理由 |
|------|------|
| PHP 7.2+ | 广泛部署、共享主机兼容、无需编译 |
| SQLite | 零配置、文件级数据库、适合中小规模数据 |
| 原生 HTML/CSS/JS | 无前端构建依赖、加载快速 |
| cURL | PHP 内置 HTTP 客户端，稳定可靠 |
| Crontab | Linux 标准定时任务，无需额外守护进程 |

---

## 2. 整体架构

### 2.1 架构图

```
┌─────────────────────────────────────────────────────────────┐
│                      用户浏览器                               │
└─────────────┬───────────────────────────────┬───────────────┘
              │                               │
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────────┐
│   Web 前端 (PHP 页面)    │     │    API 接口 (JSON)           │
│  ┌───────────────────┐  │     │  ┌─────────────────────────┐ │
│  │ index.php         │  │     │  │ api/roms.php             │ │
│  │ pages/devices.php │  │     │  │  - GET ?device=xxx       │ │
│  │ pages/device.php  │  │     │  │  - GET ?latest=1         │ │
│  │ pages/search.php  │  │     │  │  - GET ?search=xxx       │ │
│  │ pages/weekly.php  │  │     │  │  - GET ?stats=1          │ │
│  │ pages/tools.php   │  │     │  └─────────────────────────┘ │
│  └───────────────────┘  │     └─────────────────────────────┘
└─────────────┬───────────┘
              │
              ▼
┌─────────────────────────────────────────────────────────────┐
│                     业务逻辑层                                │
│  ┌───────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │ DeviceList.php │  │ Collector.php │  │   Utils.php      │  │
│  │ 设备列表管理    │  │ ROM采集引擎   │  │  HTTP/缓存/版本   │  │
│  └───────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────┬───────────┬───────────────┬───────────────────┘
              │           │               │
              ▼           ▼               ▼
┌─────────────────────────────────────────────────────────────┐
│                   数据访问层 (Database.php)                    │
│                   PDO + SQLite3 WAL 模式                      │
└─────────────┬───────────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────────────────┐
│              SQLite 数据库 (data/miuirom.db)                  │
│  ┌──────────┐    ┌──────────┐    ┌──────────────┐           │
│  │ devices  │    │  roms    │    │ collect_log  │           │
│  └──────────┘    └──────────┘    └──────────────┘           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    定时任务层 (Crontab)                        │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  cron/collect.php  (每日凌晨2点执行)                    │   │
│  │  → Collector → Xiaomi API / GitHub JSON / 服务器扫描   │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 分层说明

| 层次 | 职责 | 文件 |
|------|------|------|
| 展示层 | 渲染 HTML 页面 / JSON API 响应 | `index.php`, `pages/*.php`, `api/roms.php` |
| 业务层 | 设备管理、ROM 采集、版本解析 | `DeviceList.php`, `Collector.php`, `Utils.php` |
| 数据层 | 数据库连接、CRUD、事务管理 | `Database.php` |
| 存储层 | 持久化存储 | SQLite 数据库文件 |
| 调度层 | 定时采集任务 | `cron/collect.php` + Crontab |

---

## 3. 目录结构详解

```
MIUIROM/
│
├── index.php                 # 首页入口
│   - 展示最新 ROM 更新列表（前12条）
│   - 展示站点统计：设备数、ROM数、地区数、数据总量
│   - 按品牌分组展示设备卡片
│   - 按地区展示 ROM 分布
│   - 使用说明区块
│
├── config.php                # 全局配置文件
│   - 定义所有常量：数据库、API、站点、分类映射
│   - 时区设置、错误报告配置
│
├── includes/                 # 核心业务模块
│   ├── Database.php          # 数据库操作类
│   ├── Utils.php             # 通用工具函数
│   ├── DeviceList.php        # 设备列表管理
│   └── Collector.php         # ROM 采集器
│
├── pages/                    # 子页面
│   ├── devices.php           # 机型列表（支持品牌/类别筛选）
│   ├── device.php            # 单设备详情（ROM 按地区分组展示）
│   ├── search.php            # 搜索页（关键词搜索 ROM）
│   ├── weekly.php            # 橙色星期五（开发版周更 ROM）
│   └── tools.php             # 刷机工具与教程
│
├── api/                      # RESTful API
│   └── roms.php              # ROM 数据接口
│
├── cron/                     # 定时任务
│   └── collect.php           # 每日采集入口脚本
│
├── assets/                   # 静态资源
│   ├── css/style.css         # 完整样式表（小米橙配色）
│   └── js/main.js            # 前端交互（MD5复制、行点击）
│
└── data/                     # 运行时数据（自动生成）
    ├── miuirom.db            # SQLite 数据库主文件
    ├── miuirom.db-wal        # WAL 日志文件
    ├── miuirom.db-shm        # WAL 共享内存文件
    ├── app.log               # 应用日志
    ├── error.log             # PHP 错误日志
    └── cache_*.json          # 文件缓存
```

---

## 4. 核心模块说明

### 4.1 config.php — 全局配置

**文件路径**: [config.php](config.php)  
**职责**: 定义项目所有全局常量，是整个系统的配置中心。

**主要常量定义**:

| 常量 | 类型 | 说明 |
|------|------|------|
| `MIUIROM_VERSION` | string | 项目版本号 |
| `MIUIROM_ROOT` | string | 项目根目录绝对路径 |
| `MIUIROM_DATA` | string | 数据目录路径 |
| `MIUIROM_DB` | string | SQLite 数据库文件路径 |
| `DB_DSN` | string | PDO 连接 DSN |
| `SITE_NAME` | string | 站点名称 |
| `XIAOMI_OTA_HOSTS` | array | 小米官方 ROM 服务器列表 |
| `XIAOMI_API_ROM_CHECK` | string | 小米 OTA 检查 API 端点 |
| `COLLECT_SOURCES` | array | 采集策略开关 |
| `REGION_MAP` | array | 地区代码 → 中文名称映射 |
| `FLASH_TYPES` | array | 刷机类型映射 |
| `BRANCH_TYPES` | array | 分支类型映射 |
| `OS_TYPES` | array | 系统类型映射 |
| `CACHE_TTL_DEVICES` | int | 设备列表缓存时间（秒） |
| `CACHE_TTL_ROMS` | int | ROM 列表缓存时间（秒） |
| `HTTP_TIMEOUT` | int | HTTP 请求超时（秒） |
| `PAGE_SIZE` | int | 分页大小 |

**地区代码映射表** (`REGION_MAP`):

```
CN → 中国    MI → 全球    EU → 欧洲    IN → 印度    RU → 俄罗斯
ID → 印尼    TW → 台湾    TR → 土耳其  JP → 日本    KR → 韩国
```

---

### 4.2 Database.php — 数据库层

**文件路径**: [includes/Database.php](includes/Database.php)  
**职责**: 封装 SQLite 数据库操作，提供单例连接、表结构初始化、CRUD 操作和事务支持。

**核心设计**:

```
┌──────────────────────────────────────┐
│           Database (静态类)           │
├──────────────────────────────────────┤
│ - $instance: PDO (单例)              │
├──────────────────────────────────────┤
│ + getInstance(): PDO                 │  ← 获取连接（首次自动初始化）
│ + initSchema(): void                 │  ← 创建表结构和索引
│ + query(sql, params): array          │  ← 查询多行
│ + queryOne(sql, params): ?array      │  ← 查询单行
│ + execute(sql, params): int          │  ← 执行写操作
│ + insert(sql, params): int           │  ← 插入并返回ID
│ + scalar(sql, params): mixed         │  ← 获取单值
│ + beginTransaction(): void           │  ← 开启事务
│ + commit(): void                     │  ← 提交事务
│ + rollback(): void                   │  ← 回滚事务
└──────────────────────────────────────┘
```

**关键实现细节**:

1. **单例模式**: 通过 `getInstance()` 确保全局只有一个 PDO 连接
2. **WAL 模式**: 使用 `PRAGMA journal_mode=WAL` 提升并发读写性能
3. **外键约束**: 使用 `PRAGMA foreign_keys=ON` 保证数据完整性
4. **忙等待**: 使用 `PRAGMA busy_timeout=5000` 避免数据库锁定错误
5. **自动建表**: 首次连接检测到数据库文件不存在时自动调用 `initSchema()`

**数据库表结构**:

见 [第5章 数据库设计](#5-数据库设计)

**事务使用示例**:

```php
Database::beginTransaction();
try {
    Database::execute("INSERT INTO devices ...");
    Database::execute("INSERT INTO roms ...");
    Database::commit();
} catch (Exception $e) {
    Database::rollback();
}
```

---

### 4.3 Utils.php — 工具函数

**文件路径**: [includes/Utils.php](includes/Utils.php)  
**职责**: 提供 HTTP 请求、版本解析、文件缓存、格式化等通用工具函数。

**方法列表**:

| 方法 | 参数 | 返回值 | 说明 |
|------|------|--------|------|
| `httpGet($url, $headers)` | URL, 请求头 | string\|false | HTTP GET 请求 |
| `httpPost($url, $data, $headers)` | URL, POST数据, 请求头 | string\|false | HTTP POST 请求 |
| `parseVersion($version)` | 版本号字符串 | array | 解析 MIUI/HyperOS 版本号 |
| `formatSize($bytes, $decimals)` | 字节数, 小数位 | string | 格式化文件大小 |
| `cacheGet($key, $ttl)` | 键, 过期时间 | mixed\|null | 读取文件缓存 |
| `cacheSet($key, $data)` | 键, 数据 | void | 写入文件缓存 |
| `getRomFileName($codename, $version, $flashType)` | 代号, 版本, 刷机类型 | string | 生成 ROM 文件名 |
| `buildXiaomiDownloadUrl($version, $fileName)` | 版本, 文件名 | string | 构建小米下载链接 |
| `checkUrlExists($url)` | URL | bool | 检测 URL 是否有效 |
| `getRegionName($code)` | 地区代码 | string | 获取地区中文名 |
| `getBranchName($code)` | 分支代码 | string | 获取分支中文名 |
| `getFlashTypeName($code)` | 刷机类型代码 | string | 获取刷机类型中文名 |
| `getOsTypeName($code)` | 系统类型代码 | string | 获取系统类型中文名 |
| `h($str)` | 字符串 | string | HTML 实体转义 |
| `log($message, $level)` | 消息, 级别 | void | 写入日志 |

**`parseVersion()` 版本解析详解**:

该方法是理解 MIUI/HyperOS 命名的关键函数。以 `V14.0.9.0.TLCCNXM` 为例：

```
输入: "V14.0.9.0.TLCCNXM"

输出: {
    os_type:         "miui",           // V开头=MIUI, OS开头=HyperOS
    miui_version:    "V14.0.9.0",     // MIUI主版本号
    android_version: "13",             // T=Android 13
    android_letter:  "T",             // Android版本号首字母
    device_code:     "lc",            // 设备代号 (cupid的代码)
    region_code:     "CN",            // 中国版
    carrier:         "XM"             // 无运营商锁定
}
```

**Android 版本号字母映射**:

```
K=4.4  L=5.0/5.1  M=6.0  N=7.0/7.1  O=8.0/8.1  P=9
Q=10   R=11        S=12   T=13        U=14        V=15  W=16
```

**文件缓存机制**:

```
缓存键 → MD5 → data/cache_{md5}.json
过期检查: filemtime() 与当前时间比较
```

---

### 4.4 DeviceList.php — 设备管理

**文件路径**: [includes/DeviceList.php](includes/DeviceList.php)  
**职责**: 管理小米/红米/POCO 设备信息，提供设备查询、ROM 检索、搜索、统计等功能。

**方法列表**:

| 方法 | 说明 |
|------|------|
| `getAll()` | 获取所有设备列表（数据库 → 内置列表降级） |
| `getByCodename($codename)` | 根据代号获取单个设备 |
| `getRoms($codename, $filters)` | 获取指定设备的 ROM 列表（支持多维度筛选） |
| `getLatestRoms($limit, $filters)` | 获取最新 ROM 列表 |
| `search($keyword, $limit)` | 搜索 ROM（设备名/代号/型号/版本号） |
| `getStats()` | 获取统计信息（设备数/ROM数/总大小/地区分布） |
| `getRegionStats()` | 获取各地区 ROM 统计 |
| `getBranchStats()` | 获取各分支 ROM 统计 |
| `importBuiltinDevices()` | 将内置设备列表导入数据库 |

**内置设备列表**:

系统内置了约 100 台精选设备信息，涵盖：

| 系列 | 设备数量 | 示例 |
|------|---------|------|
| 小米数字系列 | ~30 台 | Xiaomi 9~15、11/12/13/14/15 系列 |
| 小米 Civi 系列 | 3 台 | Civi、Civi 1S、Civi 3 |
| 小米 MIX 系列 | 4 台 | MIX Fold、MIX 4、MIX Fold 2/3 |
| 小米平板系列 | 8 台 | Pad 5、Pad 6、Pad 6 Pro 等 |
| Redmi Note 系列 | ~25 台 | Note 8~14 系列 |
| Redmi K 系列 | 9 台 | K40、K60、K70、K80 系列 |
| POCO 系列 | 12 台 | F1~F7、X3~X7 系列 |

**ROM 筛选参数**:

```php
$filters = [
    'region'     => 'CN',       // 地区代码
    'os_type'    => 'hyperos',  // 系统类型
    'branch'     => 'stable',   // 分支
    'flash_type' => 'recovery', // 刷机类型
];
```

---

### 4.5 Collector.php — ROM 采集器

**文件路径**: [includes/Collector.php](includes/Collector.php)  
**职责**: 从多个数据源自动采集 ROM 信息，解析并存入数据库。这是整个系统的核心引擎。

**采集流程**:

```
┌──────────────────────────────────────────────────────────┐
│                   Collector::collect()                    │
├──────────────────────────────────────────────────────────┤
│  1. 导入设备列表 (DeviceList::importBuiltinDevices)       │
│  2. 从 GitHub JSON 采集 (collectFromGithubJson)           │
│  3. 从小米 API 采集 (collectFromXiaomiApi)                │
│  4. 从服务器直接扫描 (collectFromDirectScan)               │
│  5. 记录采集日志 (collect_log 表)                          │
└──────────────────────────────────────────────────────────┘
```

**三种采集策略详解**:

#### 策略 1: 小米官方 OTA API

```
POST https://update.miui.com/updates/v1/fullromcheck.php

请求体:
  d=cupid          → 设备代号
  b=F              → 分支 (F=稳定版, X=开发版)
  r=CN             → 地区
  v=V0.0.0.0.DEV   → 当前版本
  a=15             → Android版本
  is_need_head=1   → 是否返回头部信息
  locale=zh_CN     → 语言

响应 (JSON):
  {
    "latest_version": {
      "version": "V14.0.9.0.TLCCNXM",
      "android": "13",
      "download": "https://bigota.d.miui.com/...",
      "filesize": 5368709120,
      "md5": "abc123...",
      "release_date": "2024-06-15",
      "changelog": "更新日志..."
    }
  }
```

#### 策略 2: GitHub ROM 追踪 JSON

```
GET https://raw.githubusercontent.com/XiaomiFirmwareUpdater/
    miui-updates-tracker/master/data/latest.yml

YAML 格式 → 解析为 ROM 数据数组
```

#### 策略 3: 服务器直链扫描

```
HEAD https://bigota.d.miui.com/{version}/{filename}
→ 检查 HTTP 200 响应确认文件存在
```

**去重机制**:

ROM 通过 `(codename, version, region, flash_type)` 四元组作为唯一标识：
- 已存在 → 更新下载链接和元数据
- 不存在 → 插入新记录

**采集日志**:

每次采集后自动记录到 `collect_log` 表：

```
source       | status  | roms_found | roms_new | duration_ms | created_at
-------------|---------|------------|----------|-------------|-------------------
github_json  | success | 150        | 23       | 3200        | 2026-07-22 02:00:05
xiaomi_api   | success | 200        | 45       | 8500        | 2026-07-22 02:00:14
```

---

## 5. 数据库设计

### 5.1 ER 图

```
┌──────────────┐         ┌──────────────────┐
│   devices    │ 1    N  │      roms        │
│──────────────│─────────│──────────────────│
│ id (PK)      │         │ id (PK)          │
│ codename (U) │────────→│ device_id (FK)   │
│ model_name   │         │ codename         │
│ brand        │         │ version          │
│ model_number │         │ os_type          │
│ category     │         │ android_version  │
│ status       │         │ region           │
│ created_at   │         │ branch           │
│ updated_at   │         │ flash_type       │
└──────────────┘         │ file_name        │
                         │ file_size        │
                         │ md5_checksum     │
                         │ download_url     │
                         │ mirror_url       │
                         │ release_date     │
                         │ changelog        │
                         │ is_active        │
                         │ created_at       │
                         └──────────────────┘

┌──────────────────┐
│   collect_log    │
│──────────────────│
│ id (PK)          │
│ source           │
│ status           │
│ roms_found       │
│ roms_new         │
│ error_message    │
│ duration_ms      │
│ created_at       │
└──────────────────┘
```

### 5.2 表详细定义

#### devices 表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | INTEGER | PRIMARY KEY, AUTOINCREMENT | 自增主键 |
| codename | TEXT | NOT NULL, UNIQUE | 设备代号 (如: cupid) |
| model_name | TEXT | NOT NULL | 型号名称 (如: Xiaomi 12) |
| brand | TEXT | NOT NULL, DEFAULT 'Xiaomi' | 品牌: Xiaomi/Redmi/POCO |
| model_number | TEXT | DEFAULT '' | 型号编号 (如: 2201123C) |
| category | TEXT | DEFAULT 'phone' | 类别: phone/tablet/foldable |
| soc | TEXT | DEFAULT '' | 处理器型号 |
| status | TEXT | DEFAULT 'active' | 支持状态: active/eol |
| created_at | TEXT | DEFAULT datetime('now','localtime') | 创建时间 |
| updated_at | TEXT | DEFAULT datetime('now','localtime') | 更新时间 |

#### roms 表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | INTEGER | PRIMARY KEY, AUTOINCREMENT | 自增主键 |
| device_id | INTEGER | NOT NULL, FK→devices.id | 关联设备 |
| codename | TEXT | NOT NULL | 设备代号 |
| version | TEXT | NOT NULL | 版本号 (如: V14.0.9.0.TLCCNXM) |
| os_type | TEXT | NOT NULL, DEFAULT 'miui' | 系统类型: miui/hyperos |
| android_version | TEXT | DEFAULT '' | Android 版本 |
| region | TEXT | NOT NULL, DEFAULT 'CN' | 地区代码 |
| branch | TEXT | NOT NULL, DEFAULT 'stable' | 分支: stable/developer/weekly |
| flash_type | TEXT | NOT NULL, DEFAULT 'recovery' | 刷机类型: recovery/fastboot/ota |
| file_name | TEXT | NOT NULL | 文件名 |
| file_size | INTEGER | DEFAULT 0 | 文件大小 (字节) |
| md5_checksum | TEXT | DEFAULT '' | MD5 校验值 |
| download_url | TEXT | NOT NULL | 下载直链 |
| mirror_url | TEXT | DEFAULT '' | 镜像地址 |
| release_date | TEXT | DEFAULT '' | 发布日期 |
| changelog | TEXT | DEFAULT '' | 更新日志 |
| is_active | INTEGER | DEFAULT 1 | 是否有效 |
| created_at | TEXT | DEFAULT datetime('now','localtime') | 创建时间 |

#### collect_log 表

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | INTEGER | PRIMARY KEY, AUTOINCREMENT | 自增主键 |
| source | TEXT | NOT NULL | 数据来源 |
| status | TEXT | DEFAULT 'success' | 状态: success/failed |
| roms_found | INTEGER | DEFAULT 0 | 发现的 ROM 总数 |
| roms_new | INTEGER | DEFAULT 0 | 新发现的 ROM 数 |
| error_message | TEXT | DEFAULT '' | 错误信息 |
| duration_ms | INTEGER | DEFAULT 0 | 执行耗时 (毫秒) |
| created_at | TEXT | DEFAULT datetime('now','localtime') | 创建时间 |

### 5.3 索引设计

| 索引名 | 字段 | 类型 | 用途 |
|--------|------|------|------|
| idx_roms_codename | codename | 普通索引 | 按设备代号查询 |
| idx_roms_version | version | 普通索引 | 按版本号查询 |
| idx_roms_region | region | 普通索引 | 按地区筛选 |
| idx_roms_os_type | os_type | 普通索引 | 按系统类型筛选 |
| idx_roms_branch | branch | 普通索引 | 按分支筛选 |
| idx_roms_flash_type | flash_type | 普通索引 | 按刷机类型筛选 |
| idx_roms_device_id | device_id | 普通索引 | 按设备ID关联查询 |
| idx_roms_release_date | release_date | 普通索引 | 按日期排序 |
| idx_roms_unique | (codename, version, region, flash_type) | 唯一索引 | 去重保证 |

---

## 6. API 接口文档

### 6.1 通用说明

- **基础路径**: `/api/roms.php`
- **请求方法**: GET
- **响应格式**: JSON
- **字符编码**: UTF-8
- **CORS**: 允许所有来源

### 6.2 接口列表

#### 6.2.1 获取统计信息

```
GET /api/roms.php?stats=1

响应:
{
  "success": true,
  "data": {
    "stats": {
      "total_devices": 100,
      "total_roms": 5000,
      "total_size": "15.32 GB",
      "latest_update": "2026-07-22 02:00:05",
      "regions": [...],
      "os_types": [...]
    },
    "regions": [
      {"region": "CN", "cnt": 2000, "devices": 80},
      {"region": "MI", "cnt": 1500, "devices": 70}
    ],
    "branches": [
      {"branch": "stable", "cnt": 4000},
      {"branch": "developer", "cnt": 1000}
    ]
  }
}
```

#### 6.2.2 搜索 ROM

```
GET /api/roms.php?search=Xiaomi 12

参数: search (必填) - 搜索关键词

响应:
{
  "success": true,
  "data": {
    "keyword": "Xiaomi 12",
    "count": 25,
    "roms": [...]
  }
}
```

#### 6.2.3 获取最新 ROM

```
GET /api/roms.php?latest=1&limit=20&region=CN&os_type=hyperos

参数:
  latest (必填) - 1
  limit  (可选) - 数量, 默认20, 最大100
  region (可选) - 地区代码
  os_type (可选) - 系统类型
  branch (可选) - 分支

响应:
{
  "success": true,
  "data": {
    "count": 20,
    "roms": [...]
  }
}
```

#### 6.2.4 按设备获取 ROM

```
GET /api/roms.php?device=cupid&region=CN&branch=stable

参数:
  device (必填)     - 设备代号
  region (可选)     - 地区代码
  os_type (可选)    - 系统类型
  branch (可选)     - 分支
  flash_type (可选) - 刷机类型

响应:
{
  "success": true,
  "data": {
    "device": {"id": 1, "codename": "cupid", "model_name": "Xiaomi 12", ...},
    "count": 10,
    "roms": [...]
  }
}
```

#### 6.2.5 分页获取 ROM

```
GET /api/roms.php?page=1&limit=50&region=CN

参数:
  page  (可选) - 页码, 默认1
  limit (可选) - 每页数量, 默认50, 最大100
  region (可选)     - 地区代码
  os_type (可选)    - 系统类型
  branch (可选)     - 分支
  flash_type (可选) - 刷机类型

响应:
{
  "success": true,
  "data": {
    "total": 500,
    "page": 1,
    "limit": 50,
    "pages": 10,
    "roms": [...]
  }
}
```

---

## 7. 前端页面说明

### 7.1 页面列表

| 页面 | 路径 | 功能 |
|------|------|------|
| 首页 | [index.php](index.php) | 最新 ROM、统计、品牌设备浏览、地区分布 |
| 机型列表 | [pages/devices.php](pages/devices.php) | 所有设备列表，支持品牌/类别筛选 |
| 设备详情 | [pages/device.php](pages/device.php) | 单设备 ROM 列表，按地区分组，支持多维筛选 |
| 搜索 | [pages/search.php](pages/search.php) | 关键词搜索 ROM |
| 橙色星期五 | [pages/weekly.php](pages/weekly.php) | 开发版周更 ROM 专区 |
| 刷机工具 | [pages/tools.php](pages/tools.php) | 刷机工具下载与教程 |

### 7.2 设计风格

- **配色方案**: 小米橙 (#ff6700) 为主色调，白色背景，灰色辅助
- **布局**: 居中容器 (max-width: 1200px)，响应式适配移动端
- **字体**: 系统默认中文字体栈 (PingFang SC, Microsoft YaHei, Hiragino Sans GB)
- **组件**: 卡片式设备列表、表格式 ROM 列表、徽章式标签

### 7.3 前端交互

| 功能 | 实现 | 文件 |
|------|------|------|
| MD5 复制 | 点击 MD5 按钮自动复制校验值到剪贴板 | [assets/js/main.js](assets/js/main.js) |
| 表格行点击 | 点击 ROM 表格行跳转到设备详情页 | [assets/js/main.js](assets/js/main.js) |
| 响应式布局 | CSS 媒体查询适配 768px 以下移动端 | [assets/css/style.css](assets/css/style.css) |

---

## 8. 定时任务

### 8.1 采集脚本

**文件**: [cron/collect.php](cron/collect.php)

**执行流程**:

```
1. 初始化数据库连接
2. 导入内置设备列表
3. 创建 Collector 实例
4. 执行采集 (collectFromGithubJson → collectFromXiaomiApi → collectFromDirectScan)
5. 输出统计信息
6. 记录执行日志
```

### 8.2 Crontab 配置

```bash
# 每天凌晨2点执行采集
0 2 * * * /usr/bin/php /path/to/MIUIROM/cron/collect.php >> /path/to/MIUIROM/data/cron.log 2>&1

# 每天下午2点补充采集
0 14 * * * /usr/bin/php /path/to/MIUIROM/cron/collect.php >> /path/to/MIUIROM/data/cron.log 2>&1
```

### 8.3 日志输出示例

```
╔══════════════════════════════════════════════╗
║       MIUIROM - ROM镜像定时采集任务          ║
╚══════════════════════════════════════════════╝

[2026-07-22 02:00:00] 开始执行采集任务...

设备导入: 0 台

采集完成!
  发现ROM: 350 个
  新增ROM: 45 个
  耗时: 12500 ms

当前数据库统计:
  设备总数: 100 台
  ROM总数: 5000 个
  总大小: 15.32 GB
  最后更新: 2026-07-22 02:00:12

[2026-07-22 02:00:13] 采集任务完成
```

---

## 9. ROM 采集技术方案

### 9.1 数据源分析

小米 ROM 分发体系由以下组件构成：

| 组件 | 地址 | 用途 |
|------|------|------|
| OTA 检查 API | `update.miui.com/updates/v1/fullromcheck.php` | 查询最新 ROM 信息 |
| 主分发服务器 | `bigota.d.miui.com` | 存储和分发 ROM 文件 |
| 新分发服务器 | `ultimateota.d.miui.com` | 最新 ROM 优先存储 |
| 备用服务器 | `bn.d.miui.com` | 备用 ROM 存储 |
| CDN 加速 | `cdnorg.d.miui.com` | 全球 CDN 加速 |
| Azure CDN | `cdn-ota.azureedge.net` | 微软 Azure 加速节点 |

### 9.2 ROM 链接构建规则

```
URL 模板: https://{server}/{version}/{filename}

示例:
  https://bigota.d.miui.com/V14.0.9.0.TLCCNXM/miui_CUPID_V14.0.9.0.TLCCNXM_abc123_13.0.zip

文件名规则:
  Recovery: miui_{CODENAME}_{VERSION}_{MD5_HASH}_{ANDROID}.zip
  Fastboot: {codename}_images_{VERSION}_{DATE}_{ANDROID}_cn_{MD5}.tgz
  OTA:      miui-blockota-{codename}-{from_version}-{to_version}-{md5}.zip
```

### 9.3 采集流程图

```
                        ┌─────────────┐
                        │  开始采集    │
                        └──────┬──────┘
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                ▼
    ┌─────────────┐  ┌─────────────┐  ┌──────────────┐
    │ GitHub JSON │  │ 小米 OTA API │  │ 服务器直链扫描 │
    │ (YAML解析)  │  │ (POST请求)  │  │ (HEAD请求)   │
    └──────┬──────┘  └──────┬──────┘  └──────┬───────┘
           │                │                │
           └────────────────┼────────────────┘
                            ▼
                   ┌────────────────┐
                   │  解析 ROM 数据  │
                   │  (版本号/地区/  │
                   │   类型/大小等)   │
                   └───────┬────────┘
                           │
                           ▼
                   ┌────────────────┐
                   │  去重检查       │
                   │  (codename +   │
                   │   version +    │
                   │   region +     │
                   │   flash_type)  │
                   └───────┬────────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │ 已存在   │  │ 不存在   │  │ 跳过    │
        │→ 更新    │  │→ 插入    │  │         │
        └────┬────┘  └────┬────┘  └─────────┘
             │            │
             └─────┬──────┘
                   ▼
          ┌────────────────┐
          │  记录采集日志    │
          └────────────────┘
```

---

## 10. 部署与运维

### 10.1 环境要求

| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| PHP | 7.2 | 8.1+ |
| SQLite | 3.8 | 3.35+ |
| PHP 扩展 | `pdo_sqlite`, `curl`, `json`, `mbstring` | — |
| Web 服务器 | Nginx 1.18 / Apache 2.4 | Nginx 1.24 |
| 操作系统 | Linux | Ubuntu 22.04 / Debian 12 |

### 10.2 Nginx 配置示例

```nginx
server {
    listen 80;
    server_name miuirom.example.com;
    root /var/www/MIUIROM;
    index index.php;

    # 安全限制
    location ~ /(data|cron|includes) {
        deny all;
        return 403;
    }

    # 静态资源缓存
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 伪静态
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### 10.3 安全检查清单

- [x] 数据库文件 (`data/miuirom.db`) 放在 Web 不可访问目录
- [x] PHP 错误显示已关闭 (`display_errors = 0`)
- [x] 用户输入全部经过 `htmlspecialchars()` 转义
- [x] 所有 SQL 查询使用 PDO 预处理语句防注入
- [x] 采集脚本限制请求频率 (`usleep`)
- [x] 配置文件仅定义常量，不包含敏感信息

### 10.4 日常维护

```bash
# 手动执行采集
php cron/collect.php

# 查看采集日志
tail -f data/cron.log

# 查看应用日志
tail -f data/app.log

# 查看数据库统计
sqlite3 data/miuirom.db "SELECT COUNT(*) FROM roms;"
sqlite3 data/miuirom.db "SELECT region, COUNT(*) FROM roms GROUP BY region;"

# 清理过期缓存
find data/ -name "cache_*.json" -mtime +7 -delete
```

---

## 11. 依赖关系图

### 11.1 文件依赖关系

```
index.php
├── config.php
├── includes/Database.php
├── includes/Utils.php
└── includes/DeviceList.php
    └── includes/Database.php

pages/devices.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
└── ../includes/DeviceList.php

pages/device.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
└── ../includes/DeviceList.php

pages/search.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
└── ../includes/DeviceList.php

pages/weekly.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
└── ../includes/DeviceList.php

pages/tools.php
└── ../config.php

api/roms.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
└── ../includes/DeviceList.php

cron/collect.php
├── ../config.php
├── ../includes/Database.php
├── ../includes/Utils.php
├── ../includes/DeviceList.php
└── ../includes/Collector.php
    ├── (includes/Utils.php)
    └── (includes/DeviceList.php)
```

### 11.2 类依赖关系

```
┌─────────┐     ┌──────────────┐
│  Utils  │◄────│  Collector   │
└─────────┘     └──────┬───────┘
                       │
              ┌────────┴────────┐
              ▼                 ▼
       ┌────────────┐   ┌──────────────┐
       │  DeviceList │   │   Database   │
       └──────┬──────┘   └──────────────┘
              │                 ▲
              └─────────┬───────┘
                        │
                  ┌─────┴──────┐
                  │  config.php │
                  └────────────┘
```

---

## 12. 数据流与调用链

### 12.1 用户浏览流程

```
用户浏览器
  │
  ├─ GET / (index.php)
  │   ├─ Database::getInstance() → 初始化连接
  │   ├─ DeviceList::getStats() → 查询统计
  │   ├─ DeviceList::getLatestRoms(12) → 查询最新 ROM
  │   ├─ DeviceList::getAll() → 获取设备列表
  │   └─ 渲染 HTML → 返回浏览器
  │
  ├─ GET /pages/devices.php?brand=Xiaomi
  │   ├─ DeviceList::getAll() → 全部设备
  │   ├─ array_filter() → 按品牌筛选
  │   └─ 渲染 HTML
  │
  └─ GET /pages/device.php?codename=cupid
      ├─ DeviceList::getByCodename('cupid') → 设备信息
      ├─ DeviceList::getRoms('cupid', $filters) → ROM列表
      └─ 渲染 HTML (按地区分组)
```

### 12.2 API 调用流程

```
第三方应用
  │
  └─ GET /api/roms.php?device=cupid
      ├─ Database::getInstance()
      ├─ DeviceList::getByCodename('cupid')
      ├─ DeviceList::getRoms('cupid', [])
      └─ json_encode() → 返回 JSON
```

### 12.3 定时采集流程

```
Crontab
  │
  └─ php cron/collect.php
      ├─ Database::getInstance() → 初始化数据库
      ├─ DeviceList::importBuiltinDevices() → 导入设备
      ├─ new Collector()
      │   └─ collect()
      │       ├─ collectFromGithubJson()
      │       │   ├─ Utils::httpGet() → 获取 YAML
      │       │   ├─ parseYamlData() → 解析
      │       │   └─ saveRoms() → 批量写入数据库
      │       │       ├─ Database::beginTransaction()
      │       │       ├─ ensureDevice() → 设备查找/创建
      │       │       ├─ 去重检查 (查询已存在)
      │       │       ├─ INSERT/UPDATE → 写入ROM
      │       │       ├─ Database::commit()
      │       │       └─ INSERT collect_log → 记录日志
      │       │
      │       ├─ collectFromXiaomiApi()
      │       │   ├─ 遍历所有设备
      │       │   │   └─ queryXiaomiApi($codename, 'F')
      │       │   │       └─ Utils::httpPost() → 调用小米API
      │       │   └─ saveRoms() → 同上
      │       │
      │       └─ collectFromDirectScan() (条件执行)
      │           ├─ 遍历设备
      │           │   └─ Utils::checkUrlExists() → HEAD请求
      │           └─ saveRoms() → 同上
      │
      └─ 输出结果 → 写入 cron.log
```

---

## 附录

### A. 小米 ROM 版本号字母含义速查

| 字母 | 含义 | 示例 |
|------|------|------|
| V | 版本前缀 (MIUI) | V14.0.9.0 |
| OS | 版本前缀 (HyperOS) | OS1.0.3.0 |
| CN | 中国 | ...CNXM |
| MI | 全球 | ...MIXM |
| EU | 欧洲 | ...EUXM |
| IN | 印度 | ...INXM |
| RU | 俄罗斯 | ...RUXM |
| ID | 印尼 | ...IDXM |
| TW | 台湾 | ...TWXM |
| TR | 土耳其 | ...TRXM |
| JP | 日本 | ...JPXM |
| XM | 无运营商锁定 | ...XM |

### B. 常见问题

**Q: 为什么数据库里没有 ROM 数据？**  
A: 首次部署后需要运行 `php cron/collect.php` 进行首次采集。

**Q: 采集脚本报错怎么办？**  
A: 检查 `data/error.log` 和 `data/app.log` 查看详细错误。常见原因：网络不通、PHP curl 扩展未安装、小米 API 限流。

**Q: 如何添加新设备？**  
A: 编辑 `DeviceList.php` 中的 `getBuiltinDevices()` 方法，添加新设备信息后重新运行采集脚本。

**Q: 数据库文件太大怎么办？**  
A: 可以定期清理旧版本的 ROM 记录，或将 `is_active` 设为 0 来停用旧记录。

---

> **文档维护**: 本文档随项目代码同步更新，如有疑问请提交 Issue。