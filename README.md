# <img src="https://img.shields.io/badge/MI-ff6700?style=flat-square&logo=xiaomi&logoColor=white" alt="MI" /> MIUIROM — 小米官方ROM镜像收集站

<div align="center">

**[English](README.md)** · **[简体中文](README.md)**

</div>

---

## 📖 项目简介

**MIUIROM** 是一个小米官方 ROM 镜像收集系统，每日自动采集 **MIUI** 和 **HyperOS** 官方刷机包直链，提供清晰的多维度分类浏览与搜索功能。

本项目参考了 [roms.miuier.com](https://roms.miuier.com/zh-cn) 和 [MSDN 我告诉你](https://msdn.itellyou.cn/)，结合两者的设计理念——像 MSDN 一样简洁清晰地组织软件资源，像 miuier 一样专注 MIUI ROM 收录。

> **声明**: 本站所有 ROM 文件均来自小米官方服务器，未做任何修改。本站仅提供链接索引服务，与小米公司无关，为个人公益项目。

---

## ✨ 核心特性

| 特性 | 说明 |
|------|------|
| 🔄 **每日自动采集** | 定时从多个数据源获取最新 ROM 信息 |
| 📂 **多维分类** | 按设备、品牌、地区、系统、分支、刷机类型六维分类 |
| 🔗 **官方直链** | 所有下载链接均为小米官方服务器直链 |
| 🔍 **智能搜索** | 支持设备名称、代号、型号、版本号搜索 |
| 📊 **API 接口** | RESTful JSON API，支持第三方调用 |
| 🗄️ **SQLite 存储** | 零配置，开箱即用 |
| 🎨 **中国风设计** | 橙白配色，简洁大气 |

---

## 🏗️ 项目结构

```
MIUIROM/
├── index.php                 # 首页（最新ROM、统计、设备浏览）
├── config.php                # 全局配置文件
├── README.md                 # 项目说明文档
├── CODE_WIKI.md              # 代码技术文档
│
├── includes/                 # 核心模块
│   ├── Database.php          # SQLite 数据库操作类
│   ├── Utils.php             # 工具函数类（HTTP、版本解析、缓存等）
│   ├── DeviceList.php        # 设备列表管理类
│   └── Collector.php         # ROM 镜像采集器
│
├── pages/                    # 前端页面
│   ├── devices.php           # 机型列表页
│   ├── device.php            # 设备详情页（ROM列表）
│   ├── search.php            # 搜索页
│   ├── weekly.php            # 橙色星期五（开发版）
│   └── tools.php             # 刷机工具与教程
│
├── api/                      # API 接口
│   └── roms.php              # ROM 数据 RESTful API
│
├── cron/                     # 定时任务
│   └── collect.php           # 每日采集脚本
│
├── assets/                   # 静态资源
│   ├── css/
│   │   └── style.css         # 主样式表
│   └── js/
│       └── main.js           # 前端交互脚本
│
└── data/                     # 数据目录（自动生成）
    ├── miuirom.db            # SQLite 数据库
    ├── app.log               # 应用日志
    └── error.log             # 错误日志
```

---

## 🚀 快速开始

### 环境要求

| 依赖 | 最低版本 |
|------|---------|
| PHP | 7.2+ |
| PHP扩展 | `pdo_sqlite`, `curl`, `json`, `mbstring` |
| Web服务器 | Nginx / Apache |
| 数据库 | SQLite 3（无需额外安装） |

### 安装步骤

```bash
# 1. 克隆项目
git clone https://github.com/676077263/MIUIROM.git
cd MIUIROM

# 2. 创建数据目录并设置权限
mkdir -p data
chmod 755 data

# 3. 运行首次采集
php cron/collect.php

# 4. 配置 Web 服务器指向项目根目录
# Nginx 示例:
#   root /path/to/MIUIROM;
#   index index.php;
#   location / { try_files $uri $uri/ /index.php?$query_string; }

# 5. 配置定时任务 (每日凌晨2点采集)
crontab -e
# 添加:
# 0 2 * * * /usr/bin/php /path/to/MIUIROM/cron/collect.php >> /path/to/MIUIROM/data/cron.log 2>&1
```

### 使用 PHP 内置服务器测试

```bash
php -S 0.0.0.0:8080 -t /path/to/MIUIROM
# 浏览器访问: http://localhost:8080
```

---

## 📊 ROM 分类体系

ROM 按以下六个维度进行分类存储：

### 1. 品牌 (Brand)
- **Xiaomi** — 小米
- **Redmi** — 红米
- **POCO** — POCO

### 2. 地区 (Region)
| 代码 | 地区 | 说明 |
|------|------|------|
| CN | 中国 | 中文/英文，无谷歌服务 |
| MI | 全球 | 多语言，含谷歌服务 |
| EU | 欧洲 | EEA 法规适配 |
| IN | 印度 | 印度特供 |
| RU | 俄罗斯 | 俄罗斯版 |
| ID | 印尼 | 印尼版 |
| TW | 台湾 | 台湾版 |
| TR | 土耳其 | 土耳其版 |
| JP | 日本 | 日本版 |
| KR | 韩国 | 韩国版 |

### 3. 系统类型 (OS Type)
- **MIUI** — 小米经典安卓皮肤
- **HyperOS** — 小米澎湃 OS（新一代系统）

### 4. 分支 (Branch)
- **稳定版** (Stable) — 适合日常使用，稳定可靠
- **开发版** (Developer) — 橙色星期五周更，尝鲜体验

### 5. 刷机类型 (Flash Type)
- **卡刷包** (Recovery) — 通过系统更新功能刷入
- **线刷包** (Fastboot) — 通过电脑 MiFlash 工具刷入
- **OTA 包** (OTA) — 增量更新包

### 6. 设备类别 (Category)
- **手机** (Phone)
- **平板** (Tablet)
- **折叠屏** (Foldable)

---

## 🔌 API 接口

### 获取最新 ROM

```
GET /api/roms.php?latest=1&limit=20
```

### 按设备获取 ROM

```
GET /api/roms.php?device=cupid
```

### 搜索 ROM

```
GET /api/roms.php?search=Xiaomi 12
```

### 获取统计信息

```
GET /api/roms.php?stats=1
```

### 分页获取 ROM

```
GET /api/roms.php?page=1&region=CN&os_type=hyperos
```

### 响应格式

```json
{
  "success": true,
  "data": {
    "total": 100,
    "page": 1,
    "limit": 50,
    "pages": 2,
    "roms": [
      {
        "id": 1,
        "codename": "cupid",
        "version": "V14.0.9.0.TLCCNXM",
        "os_type": "miui",
        "android_version": "13",
        "region": "CN",
        "branch": "stable",
        "flash_type": "recovery",
        "file_name": "miui_CUPID_V14.0.9.0.TLCCNXM.zip",
        "file_size": 5368709120,
        "download_url": "https://bigota.d.miui.com/V14.0.9.0.TLCCNXM/miui_CUPID_V14.0.9.0.TLCCNXM.zip",
        "release_date": "2024-06-15",
        "model_name": "Xiaomi 12",
        "brand": "Xiaomi"
      }
    ]
  }
}
```

---

## 📡 ROM 采集策略

### 数据源

| 来源 | 方式 | 说明 |
|------|------|------|
| 小米 OTA API | HTTP POST | `update.miui.com` 官方接口，获取最新 ROM 信息 |
| GitHub JSON | HTTP GET | XiaomiFirmwareUpdater 项目的 ROM 追踪数据 |
| 服务器直链扫描 | HTTP HEAD | 直接检测小米 CDN 服务器上的 ROM 文件是否存在 |

### 小米官方 ROM 服务器

- `bigota.d.miui.com` — 最常用的 ROM 分发服务器
- `ultimateota.d.miui.com` — 最新 ROM 服务器
- `bn.d.miui.com` — 备用 ROM 服务器
- `cdnorg.d.miui.com` — CDN 加速节点

### 版本号命名规则

以 `V14.0.9.0.TLCCNXM` 为例：

```
V  14.0  .  9.0  .  T  .  LC  .  CN  .  XM
│  ─┬─     ─┬─     │     │      │      └─ 运营商锁定
│   │        │      │     │      └─ 地区代码 (CN=中国)
│   │        │      │     └─ 设备代号 (LC=cupid)
│   │        │      └─ Android版本 (T=13, S=12, U=14)
│   │        └─ 小版本号
│   └─ MIUI 大版本号
└─ 版本前缀 (V=MIUI, OS=HyperOS)
```

---

## 🛠️ 技术栈

| 层级 | 技术 |
|------|------|
| 后端语言 | PHP 7.2+ |
| 数据库 | SQLite 3 (PDO) |
| 前端 | 原生 HTML5 + CSS3 + JavaScript |
| HTTP 客户端 | cURL |
| 定时任务 | Crontab |
| 数据格式 | JSON / YAML |

---

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

---

## 📄 开源协议

本项目采用 MIT 协议开源。详见 [LICENSE](LICENSE) 文件。

---

## 🙏 致谢

- [XiaomiFirmwareUpdater](https://github.com/XiaomiFirmwareUpdater) — ROM 追踪数据源
- [roms.miuier.com](https://roms.miuier.com/zh-cn) — 设计参考
- [MSDN 我告诉你](https://msdn.itellyou.cn/) — 设计灵感
- [小米社区](https://web.vip.miui.com/) — 官方 ROM 信息

---

<div align="center">
    <sub>Made with ❤️ for the MIUI Community</sub>
</div>