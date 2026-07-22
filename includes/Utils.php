<?php
/**
 * MIUIROM - 工具函数类
 * 
 * 提供HTTP请求、ROM版本解析、文件大小格式化、缓存等通用工具函数。
 */

class Utils
{
    /**
     * 发送HTTP GET请求
     * 
     * @param  string $url     请求URL
     * @param  array  $headers 自定义请求头
     * @return string|false    响应内容，失败返回false
     */
    public static function httpGet(string $url, array $headers = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'MIUIROM-Collector/1.0 (PHP)',
            CURLOPT_ENCODING       => 'gzip, deflate',
        ]);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("HTTP GET Error [{$url}]: {$error}");
            return false;
        }
        
        if ($httpCode >= 400) {
            error_log("HTTP GET {$httpCode} [{$url}]");
            return false;
        }
        
        return $response;
    }

    /**
     * 发送HTTP POST请求
     */
    public static function httpPost(string $url, array $data = [], array $headers = [])
    {
        $ch = curl_init();
        $defaultHeaders = ['Content-Type: application/x-www-form-urlencoded'];
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_TIMEOUT        => HTTP_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'MIUIROM-Collector/1.0 (PHP)',
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    /**
     * 解析MIUI/HyperOS版本号
     * 
     * 版本号命名规则说明:
     *   例: V14.0.9.0.TLCCNXM
     *   - V14.0: MIUI大版本号
     *   - 9.0: 小版本号
     *   - T: Android版本代号 (T=13, S=12, R=11, U=14, V=15, W=16)
     *   - LC: 设备代号
     *   - CN: 地区代码
     *   - XM: 运营商锁定标识
     * 
     *   例: OS1.0.3.0.UMPMIXM (HyperOS)
     *   - OS1.0: HyperOS版本
     *   - 3.0: 小版本号
     *   - U: Android版本 (U=14)
     *   - MP: 设备代号
     *   - MI: 地区 (Global)
     *   - XM: 运营商锁定
     * 
     * @param  string $version 版本号字符串
     * @return array           解析结果
     */
    public static function parseVersion(string $version): array
    {
        $result = [
            'os_type'         => 'miui',
            'miui_version'    => '',
            'android_version' => '',
            'android_letter'  => '',
            'device_code'     => '',
            'region_code'     => '',
            'carrier'         => '',
        ];
        
        // Android版本代号映射
        $androidLetters = [
            'K' => '4.4', 'L' => '5.0/5.1', 'M' => '6.0',
            'N' => '7.0/7.1', 'O' => '8.0/8.1', 'P' => '9',
            'Q' => '10', 'R' => '11', 'S' => '12',
            'T' => '13', 'U' => '14', 'V' => '15', 'W' => '16',
        ];
        
        // 判断是否为HyperOS
        if (preg_match('/^OS\d/', $version)) {
            $result['os_type'] = 'hyperos';
        }
        
        // 提取版本号主要部分
        if (preg_match('/^(V\d+\.\d+\.\d+\.\d+|OS\d+\.\d+\.\d+\.\d+)\.([A-Z])([A-Z]{2})([A-Z]{2})([A-Z]*)$/', $version, $m)) {
            $result['miui_version']    = $m[1];
            $result['android_letter']  = $m[2];
            $result['device_code']     = strtolower($m[3]);
            $result['region_code']     = $m[4];
            $result['carrier']         = $m[5] ?? '';
            
            if (isset($androidLetters[$m[2]])) {
                $result['android_version'] = $androidLetters[$m[2]];
            }
        }
        
        return $result;
    }

    /**
     * 格式化文件大小
     * 
     * @param  int    $bytes 字节数
     * @param  int    $decimals 小数位数
     * @return string
     */
    public static function formatSize(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }

    /**
     * 获取文件缓存
     * 
     * @param  string $key  缓存键
     * @param  int    $ttl  过期时间(秒)
     * @return mixed|null
     */
    public static function cacheGet(string $key, int $ttl = 3600)
    {
        $file = MIUIROM_DATA . '/cache_' . md5($key) . '.json';
        
        if (!file_exists($file)) return null;
        if (time() - filemtime($file) > $ttl) {
            @unlink($file);
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        return $data ?: null;
    }

    /**
     * 设置文件缓存
     * 
     * @param string $key  缓存键
     * @param mixed  $data 缓存数据
     */
    public static function cacheSet(string $key, $data): void
    {
        if (!is_dir(MIUIROM_DATA)) {
            mkdir(MIUIROM_DATA, 0755, true);
        }
        
        $file = MIUIROM_DATA . '/cache_' . md5($key) . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取ROM的下载文件名
     * 
     * @param  string $codename   设备代号
     * @param  string $version    版本号
     * @param  string $flashType  刷机类型
     * @return string
     */
    public static function getRomFileName(string $codename, string $version, string $flashType): string
    {
        $prefix = 'miui_';
        $codenameUpper = strtoupper($codename);
        $suffix = ($flashType === 'fastboot') ? '_fastboot' : '';
        
        return "{$prefix}{$codenameUpper}_{$version}{$suffix}.zip";
    }

    /**
     * 构建小米官方下载链接
     * 
     * @param  string $version  版本号
     * @param  string $fileName 文件名
     * @return string
     */
    public static function buildXiaomiDownloadUrl(string $version, string $fileName): string
    {
        // 使用bigota.d.miui.com作为默认下载服务器
        return "https://bigota.d.miui.com/{$version}/{$fileName}";
    }

    /**
     * 检查URL有效性
     * 
     * @param  string $url
     * @return bool
     */
    public static function checkUrlExists(string $url): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'MIUIROM-Validator/1.0',
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }

    /**
     * 获取地域名称
     */
    public static function getRegionName(string $code): string
    {
        return REGION_MAP[$code]['name'] ?? $code;
    }

    /**
     * 获取分支名称
     */
    public static function getBranchName(string $code): string
    {
        return BRANCH_TYPES[$code] ?? $code;
    }

    /**
     * 获取刷机类型名称
     */
    public static function getFlashTypeName(string $code): string
    {
        return FLASH_TYPES[$code] ?? $code;
    }

    /**
     * 获取系统类型名称
     */
    public static function getOsTypeName(string $code): string
    {
        return OS_TYPES[$code] ?? $code;
    }

    /**
     * 安全输出HTML
     */
    public static function h(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 记录日志
     */
    public static function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        $logFile = MIUIROM_DATA . '/app.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}