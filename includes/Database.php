<?php
/**
 * MIUIROM - 数据库操作类 (SQLite)
 * 
 * 负责SQLite数据库的初始化、表结构创建、以及基础的CRUD操作。
 * 使用PDO扩展实现，支持事务和预处理语句。
 */

class Database
{
    /** @var PDO|null PDO数据库连接实例 */
    private static $instance = null;

    /**
     * 获取数据库单例实例
     * 
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbDir = dirname(DB_DSN);
            $dbFile = MIUIROM_DB;
            
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $isNew = !file_exists($dbFile);
            
            self::$instance = new PDO(DB_DSN, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            
            // 启用WAL模式提升并发性能
            self::$instance->exec('PRAGMA journal_mode=WAL');
            self::$instance->exec('PRAGMA foreign_keys=ON');
            self::$instance->exec('PRAGMA busy_timeout=5000');
            
            if ($isNew) {
                self::initSchema();
            }
        }
        
        return self::$instance;
    }

    /**
     * 初始化数据库表结构
     * 
     * 创建devices、roms、collect_log三张核心表
     */
    public static function initSchema(): void
    {
        $db = self::getInstance();
        
        // 设备表
        $db->exec("
            CREATE TABLE IF NOT EXISTS devices (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                codename        TEXT    NOT NULL UNIQUE,       -- 设备代号 (如: cupid, marble)
                model_name      TEXT    NOT NULL,              -- 型号名称 (如: Xiaomi 12)
                brand           TEXT    NOT NULL DEFAULT 'Xiaomi',  -- 品牌: Xiaomi/Redmi/POCO
                model_number    TEXT    DEFAULT '',             -- 型号编号 (如: 2201123C)
                category        TEXT    DEFAULT 'phone',        -- 类别: phone/tablet/foldable
                soc             TEXT    DEFAULT '',             -- 处理器
                status          TEXT    DEFAULT 'active',       -- 支持状态: active/eol
                created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            )
        ");
        
        // ROM表
        $db->exec("
            CREATE TABLE IF NOT EXISTS roms (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                device_id       INTEGER NOT NULL,              -- 关联设备ID
                codename        TEXT    NOT NULL,              -- 设备代号
                version         TEXT    NOT NULL,              -- 版本号 (如: V14.0.9.0.TLCCNXM)
                os_type         TEXT    NOT NULL DEFAULT 'miui', -- 系统类型: miui/hyperos
                android_version TEXT    NOT NULL DEFAULT '',    -- Android版本
                region          TEXT    NOT NULL DEFAULT 'CN',  -- 地区代码
                branch          TEXT    NOT NULL DEFAULT 'stable', -- 分支: stable/developer/weekly
                flash_type      TEXT    NOT NULL DEFAULT 'recovery', -- 刷机类型: recovery/fastboot/ota
                file_name       TEXT    NOT NULL,              -- 文件名
                file_size       INTEGER DEFAULT 0,             -- 文件大小(字节)
                md5_checksum    TEXT    DEFAULT '',             -- MD5校验值
                download_url    TEXT    NOT NULL,              -- 下载直链
                mirror_url      TEXT    DEFAULT '',             -- 镜像下载地址
                release_date    TEXT    DEFAULT '',             -- 发布日期
                changelog       TEXT    DEFAULT '',             -- 更新日志
                is_active       INTEGER DEFAULT 1,             -- 是否有效
                created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
            )
        ");
        
        // 采集日志表
        $db->exec("
            CREATE TABLE IF NOT EXISTS collect_log (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                source          TEXT    NOT NULL,              -- 数据来源
                status          TEXT    NOT NULL DEFAULT 'success', -- 状态: success/failed
                roms_found      INTEGER DEFAULT 0,             -- 发现的ROM数量
                roms_new        INTEGER DEFAULT 0,             -- 新发现的ROM数量
                error_message   TEXT    DEFAULT '',             -- 错误信息
                duration_ms     INTEGER DEFAULT 0,             -- 执行耗时(毫秒)
                created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            )
        ");
        
        // 索引
        $db->exec("
            CREATE INDEX IF NOT EXISTS idx_roms_codename   ON roms(codename);
            CREATE INDEX IF NOT EXISTS idx_roms_version     ON roms(version);
            CREATE INDEX IF NOT EXISTS idx_roms_region      ON roms(region);
            CREATE INDEX IF NOT EXISTS idx_roms_os_type     ON roms(os_type);
            CREATE INDEX IF NOT EXISTS idx_roms_branch      ON roms(branch);
            CREATE INDEX IF NOT EXISTS idx_roms_flash_type  ON roms(flash_type);
            CREATE INDEX IF NOT EXISTS idx_roms_device_id   ON roms(device_id);
            CREATE INDEX IF NOT EXISTS idx_roms_release_date ON roms(release_date);
            CREATE UNIQUE INDEX IF NOT EXISTS idx_roms_unique ON roms(codename, version, region, flash_type);
        ");
    }

    /**
     * 执行查询并返回所有结果
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单行结果
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 执行INSERT/UPDATE/DELETE语句
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 插入数据并返回自增ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * 获取单值查询结果
     */
    public static function scalar(string $sql, array $params = [])
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * 开启事务
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * 提交事务
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * 回滚事务
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }
}