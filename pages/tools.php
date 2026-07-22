<?php
/**
 * MIUIROM - 刷机工具页
 */
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>刷机工具 - <?= SITE_NAME ?></title>
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
                <a href="tools.php" class="active">刷机工具</a>
                <a href="search.php">搜索</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1>刷机工具</h1>
            <p>小米官方刷机工具及教程</p>
        </div>

        <section class="section">
            <h2>刷机方式对比</h2>
            <div class="rom-table-wrapper">
                <table class="rom-table">
                    <thead>
                        <tr>
                            <th>方式</th>
                            <th>适用场景</th>
                            <th>需要解锁</th>
                            <th>需要电脑</th>
                            <th>难度</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>卡刷 (Recovery)</strong></td>
                            <td>同版本升级、跨版本升级</td>
                            <td>否</td>
                            <td>否</td>
                            <td>简单</td>
                        </tr>
                        <tr>
                            <td><strong>线刷 (Fastboot)</strong></td>
                            <td>降级、救砖、跨地区刷机</td>
                            <td>是</td>
                            <td>是</td>
                            <td>中等</td>
                        </tr>
                        <tr>
                            <td><strong>OTA增量</strong></td>
                            <td>小版本升级</td>
                            <td>否</td>
                            <td>否</td>
                            <td>简单</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h2>官方工具下载</h2>
            <div class="tool-grid">
                <div class="tool-card">
                    <h3>Mi Flash Tool</h3>
                    <p>小米官方线刷工具，用于Fastboot模式刷入完整ROM包。</p>
                    <p class="tool-note">适用于Windows系统</p>
                    <a href="https://miuirom.xiaomi.com/rom/u1106245679/6.5.224.28/miflash_unlock-en-6.5.224.28.zip" class="btn btn-primary" target="_blank" rel="nofollow">下载</a>
                </div>
                <div class="tool-card">
                    <h3>Mi Unlock Tool</h3>
                    <p>小米官方Bootloader解锁工具，线刷前必须先解锁。</p>
                    <p class="tool-note">解锁会清除所有数据，请先备份</p>
                    <a href="https://en.miui.com/unlock/download_en.html" class="btn btn-primary" target="_blank" rel="nofollow">前往下载</a>
                </div>
                <div class="tool-card">
                    <h3>ADB & Fastboot</h3>
                    <p>Android调试桥和Fastboot命令行工具，刷机必备。</p>
                    <p class="tool-note">跨平台: Windows / macOS / Linux</p>
                    <a href="https://developer.android.com/studio/releases/platform-tools" class="btn btn-primary" target="_blank" rel="nofollow">下载</a>
                </div>
            </div>
        </section>

        <section class="section">
            <h2>刷机教程</h2>
            <div class="tutorial-list">
                <div class="tutorial-item">
                    <h3>卡刷教程 (Recovery ROM)</h3>
                    <ol>
                        <li>下载对应机型的 Recovery 卡刷包 (.zip)</li>
                        <li>将ROM包传入手机存储根目录</li>
                        <li>进入 设置 &gt; 我的设备 &gt; MIUI版本</li>
                        <li>连续点击 MIUI Logo 直到出现"系统更新扩展功能已开启"</li>
                        <li>点击右上角三点菜单 &gt; 手动选择安装包</li>
                        <li>选择下载的ROM包，等待系统校验并重启</li>
                    </ol>
                </div>
                <div class="tutorial-item">
                    <h3>线刷教程 (Fastboot ROM)</h3>
                    <ol>
                        <li>备份手机所有重要数据</li>
                        <li>解锁Bootloader（使用Mi Unlock工具）</li>
                        <li>下载对应机型的 Fastboot 线刷包 (.tgz)</li>
                        <li>解压线刷包到电脑</li>
                        <li>手机进入Fastboot模式（关机后按住音量下+电源键）</li>
                        <li>连接电脑，打开Mi Flash Tool</li>
                        <li>选择解压后的ROM目录，点击刷机</li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="section info-section">
            <h2>注意事项</h2>
            <ul class="warning-list">
                <li>刷机前请确保电量充足（建议 > 50%）</li>
                <li>刷机前务必备份重要数据</li>
                <li>请使用原装或质量可靠的USB数据线</li>
                <li>确保下载的ROM与您的设备型号和地区完全匹配</li>
                <li>线刷会清除所有数据（包括内部存储），请提前备份</li>
                <li>跨版本降级必须使用线刷方式</li>
                <li>解锁Bootloader后设备安全性降低，部分功能可能不可用</li>
                <li>刷机有风险，请谨慎操作</li>
            </ul>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>MIUIROM &copy; <?= date('Y') ?></p>
        </div>
    </footer>
</body>
</html>