<?php
/**
 * MIUIROM - ROM镜像采集器
 * 
 * 负责从多个数据源采集小米官方ROM镜像链接，支持:
 * 1. 小米官方OTA API (update.miui.com)
 * 2. GitHub ROM追踪JSON数据 (XiaomiFirmwareUpdater)
 * 3. 已知ROM服务器直链扫描
 * 
 * 采集到的ROM数据经解析后存入SQLite数据库，按设备、地域、版本、分支、
 * 刷机类型、系统类型等多维度分类存储。
 */

class Collector
{
    /** @var int 新发现的ROM数量 */
    private $newCount = 0;
    
    /** @var int 总发现的ROM数量 */
    private $totalCount = 0;

    /**
     * 执行完整采集流程
     * 
     * @return array 采集结果统计
     */
    public function collect(): array
    {
        $startTime = microtime(true);
        Utils::log('=== ROM采集开始 ===');
        
        // 确保设备列表已导入
        $imported = DeviceList::importBuiltinDevices();
        if ($imported > 0) {
            Utils::log("导入设备: {$imported} 台");
        }
        
        // 方案1: 从GitHub ROM追踪器获取JSON数据
        if (COLLECT_SOURCES['github_json']) {
            $this->collectFromGithubJson();
        }
        
        // 方案2: 调用小米官方OTA API
        if (COLLECT_SOURCES['xiaomi_api']) {
            $this->collectFromXiaomiApi();
        }
        
        // 方案3: 直接扫描已知服务器
        if (COLLECT_SOURCES['direct_scan']) {
            $this->collectFromDirectScan();
        }
        
        $duration = (int)((microtime(true) - $startTime) * 1000);
        
        Utils::log("采集完成 - 发现: {$this->totalCount}, 新增: {$this->newCount}, 耗时: {$duration}ms");
        
        return [
            'roms_found' => $this->totalCount,
            'roms_new'   => $this->newCount,
            'duration_ms'=> $duration,
        ];
    }

    /**
     * 从GitHub ROM追踪器获取数据
     * 
     * XiaomiFirmwareUpdater项目维护了最新的ROM JSON/YAML数据，
     * 包含完整的设备、版本、下载链接等信息。
     */
    private function collectFromGithubJson(): void
    {
        Utils::log('从GitHub JSON采集ROM数据...');
        
        $urls = [
            'https://raw.githubusercontent.com/XiaomiFirmwareUpdater/miui-updates-tracker/master/data/latest.yml',
            'https://raw.githubusercontent.com/XiaomiFirmwareUpdater/xiaomifirmwareupdater.github.io/master/data/devices.json',
        ];
        
        foreach ($urls as $url) {
            $data = Utils::httpGet($url);
            if (!$data) {
                Utils::log("GitHub JSON获取失败: {$url}", 'WARN');
                continue;
            }
            
            // 尝试解析YAML
            $roms = $this->parseYamlData($data);
            if (!empty($roms)) {
                $this->saveRoms($roms, 'github_json');
            }
        }
    }

    /**
     * 解析YAML格式的ROM数据
     */
    private function parseYamlData(string $yaml): array
    {
        $roms = [];
        $lines = explode("\n", $yaml);
        $current = [];
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            if (empty($line)) {
                if (!empty($current) && isset($current['codename']) && isset($current['version'])) {
                    $roms[] = $current;
                }
                $current = [];
                continue;
            }
            
            // 解析YAML键值对
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2], '"\' ');
                
                $fieldMap = [
                    'codename' => 'codename',
                    'version'  => 'version',
                    'android'  => 'android_version',
                    'branch'   => 'branch',
                    'region'   => 'region',
                    'link'     => 'download_url',
                    'filename' => 'file_name',
                    'date'     => 'release_date',
                    'size'     => 'file_size',
                    'md5'      => 'md5_checksum',
                    'type'     => 'flash_type',
                ];
                
                if (isset($fieldMap[$key])) {
                    $current[$fieldMap[$key]] = $value;
                }
            }
        }
        
        return $roms;
    }

    /**
     * 从小米官方OTA API采集数据
     * 
     * 小米官方提供OTA更新检查接口，通过POST请求可以获取特定设备的
     * 最新ROM信息和下载链接。
     * 
     * API端点: https://update.miui.com/updates/v1/fullromcheck.php
     * 
     * 请求参数:
     *   - d: 设备代号 (如: cupid)
     *   - b: 分支 (F=稳定版, X=开发版)
     *   - r: 地区 (CN/Global等)
     *   - v: 当前版本号
     *   - a: Android版本
     * 
     * 返回: JSON格式的ROM信息，包含下载链接
     */
    private function collectFromXiaomiApi(): void
    {
        Utils::log('从小米官方API采集ROM数据...');
        
        $devices = DeviceList::getAll();
        $roms = [];
        
        // 针对每个设备请求最新ROM
        foreach ($devices as $device) {
            $codename = $device['codename'];
            
            // 请求稳定版
            $apiResult = $this->queryXiaomiApi($codename, 'F');
            if ($apiResult) {
                $roms = array_merge($roms, $apiResult);
            }
            
            // 请求开发版
            $apiResult = $this->queryXiaomiApi($codename, 'X');
            if ($apiResult) {
                $roms = array_merge($roms, $apiResult);
            }
            
            // 避免请求过快
            usleep(100000); // 100ms延迟
        }
        
        if (!empty($roms)) {
            $this->saveRoms($roms, 'xiaomi_api');
        }
    }

    /**
     * 调用小米OTA API获取ROM信息
     * 
     * @param  string $codename 设备代号
     * @param  string $branch   分支 (F=稳定版, X=开发版)
     * @return array|null
     */
    private function queryXiaomiApi(string $codename, string $branch = 'F'): ?array
    {
        $apiUrl = XIAOMI_API_ROM_CHECK;
        
        $postData = [
            'd' => $codename,
            'b' => $branch,
            'r' => 'CN',
            'v' => 'V0.0.0.0.DEV',
            'a' => '15',
            'is_need_head' => '1',
            'locale' => 'zh_CN',
        ];
        
        $response = Utils::httpPost($apiUrl, $postData, [
            'User-Agent: Xiaomi-MIOTAV3/1.0',
            'Accept: application/json',
        ]);
        
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$data || empty($data['latest_version'])) {
            return null;
        }
        
        $roms = [];
        $latest = $data['latest_version'];
        
        // 解析下载链接
        if (!empty($latest['download'])) {
            $version = $latest['version'] ?? '';
            $parsed = Utils::parseVersion($version);
            
            $roms[] = [
                'codename'        => $codename,
                'version'         => $version,
                'os_type'         => $parsed['os_type'],
                'android_version' => $latest['android'] ?? $parsed['android_version'],
                'region'          => $latest['region'] ?? 'CN',
                'branch'          => ($branch === 'F') ? 'stable' : 'developer',
                'flash_type'      => 'recovery',
                'file_name'       => basename($latest['download']),
                'file_size'       => $latest['filesize'] ?? 0,
                'md5_checksum'    => $latest['md5'] ?? '',
                'download_url'    => $latest['download'],
                'release_date'    => $latest['release_date'] ?? date('Y-m-d'),
                'changelog'       => $latest['changelog'] ?? '',
            ];
        }
        
        return $roms;
    }

    /**
     * 直接从已知服务器扫描ROM
     * 
     * 通过构建已知的URL模式来检测ROM文件是否存在。
     * 此方法会产生大量HTTP请求，需谨慎使用。
     */
    private function collectFromDirectScan(): void
    {
        Utils::log('从服务器直接扫描ROM...', 'WARN');
        
        $devices = DeviceList::getAll();
        $roms = [];
        
        foreach ($devices as $device) {
            $codename = $device['codename'];
            $codenameUpper = strtoupper($codename);
            
            // 尝试常见的版本模式
            $versionPatterns = [
                "OS2.0.0.0.U{$codenameUpper}CNXM",
                "OS1.0.0.0.U{$codenameUpper}CNXM",
                "V14.0.0.0.T{$codenameUpper}CNXM",
                "V13.0.0.0.S{$codenameUpper}CNXM",
            ];
            
            foreach ($versionPatterns as $version) {
                $fileName = Utils::getRomFileName($codename, $version, 'recovery');
                $url = Utils::buildXiaomiDownloadUrl($version, $fileName);
                
                if (Utils::checkUrlExists($url)) {
                    $roms[] = [
                        'codename'     => $codename,
                        'version'      => $version,
                        'flash_type'   => 'recovery',
                        'file_name'    => $fileName,
                        'download_url' => $url,
                        'region'       => 'CN',
                        'branch'       => 'stable',
                    ];
                }
            }
        }
        
        if (!empty($roms)) {
            $this->saveRoms($roms, 'direct_scan');
        }
    }

    /**
     * 保存采集到的ROM数据到数据库
     * 
     * 使用事务批量写入，自动去重。
     * 
     * @param array  $roms   ROM数据数组
     * @param string $source 数据来源
     */
    private function saveRoms(array $roms, string $source): void
    {
        $this->totalCount += count($roms);
        $newCount = 0;
        
        Database::beginTransaction();
        try {
            foreach ($roms as $rom) {
                // 查找或创建设备
                $deviceId = $this->ensureDevice($rom);
                if (!$deviceId) continue;
                
                // 设置默认值
                $rom['codename']        = $rom['codename'] ?? '';
                $rom['version']         = $rom['version'] ?? '';
                $rom['os_type']         = $rom['os_type'] ?? 'miui';
                $rom['android_version'] = $rom['android_version'] ?? '';
                $rom['region']          = $rom['region'] ?? 'CN';
                $rom['branch']          = $rom['branch'] ?? 'stable';
                $rom['flash_type']      = $rom['flash_type'] ?? 'recovery';
                $rom['file_name']       = $rom['file_name'] ?? '';
                $rom['file_size']       = $rom['file_size'] ?? 0;
                $rom['md5_checksum']    = $rom['md5_checksum'] ?? '';
                $rom['download_url']    = $rom['download_url'] ?? '';
                $rom['release_date']    = $rom['release_date'] ?? '';
                $rom['changelog']       = $rom['changelog'] ?? '';
                
                if (empty($rom['codename']) || empty($rom['version']) || empty($rom['download_url'])) {
                    continue;
                }
                
                // 检查是否已存在
                $existing = Database::queryOne(
                    "SELECT id FROM roms WHERE codename = ? AND version = ? AND region = ? AND flash_type = ?",
                    [$rom['codename'], $rom['version'], $rom['region'], $rom['flash_type']]
                );
                
                if ($existing) {
                    // 更新已有记录
                    Database::execute(
                        "UPDATE roms SET download_url = ?, file_size = ?, md5_checksum = ?, 
                         release_date = ?, updated_at = datetime('now','localtime') 
                         WHERE id = ?",
                        [
                            $rom['download_url'],
                            $rom['file_size'],
                            $rom['md5_checksum'],
                            $rom['release_date'],
                            $existing['id']
                        ]
                    );
                } else {
                    // 插入新记录
                    Database::insert(
                        "INSERT INTO roms (device_id, codename, version, os_type, android_version, 
                         region, branch, flash_type, file_name, file_size, md5_checksum, 
                         download_url, release_date, changelog)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $deviceId,
                            $rom['codename'],
                            $rom['version'],
                            $rom['os_type'],
                            $rom['android_version'],
                            $rom['region'],
                            $rom['branch'],
                            $rom['flash_type'],
                            $rom['file_name'],
                            $rom['file_size'],
                            $rom['md5_checksum'],
                            $rom['download_url'],
                            $rom['release_date'],
                            $rom['changelog'],
                        ]
                    );
                    $newCount++;
                }
            }
            
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            Utils::log("ROM保存失败: " . $e->getMessage(), 'ERROR');
        }
        
        $this->newCount += $newCount;
        
        // 记录采集日志
        Database::insert(
            "INSERT INTO collect_log (source, status, roms_found, roms_new) VALUES (?, 'success', ?, ?)",
            [$source, count($roms), $newCount]
        );
        
        Utils::log("来源[{$source}]: 发现 " . count($roms) . " 个ROM, 新增 {$newCount} 个");
    }

    /**
     * 确保设备存在于数据库中
     * 
     * @param  array $rom ROM数据
     * @return int|null   设备ID
     */
    private function ensureDevice(array $rom): ?int
    {
        $codename = $rom['codename'] ?? '';
        if (empty($codename)) return null;
        
        $device = Database::queryOne(
            "SELECT id FROM devices WHERE codename = ?",
            [$codename]
        );
        
        if ($device) {
            return (int) $device['id'];
        }
        
        // 设备不存在时自动创建
        $parsed = Utils::parseVersion($rom['version'] ?? '');
        
        return Database::insert(
            "INSERT INTO devices (codename, model_name, brand, model_number, category) 
             VALUES (?, ?, ?, ?, 'phone')",
            [
                $codename,
                strtoupper($codename),
                'Xiaomi',
                '',
            ]
        );
    }
}