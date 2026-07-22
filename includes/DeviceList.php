<?php
/**
 * MIUIROM - 设备列表管理类
 * 
 * 管理小米/红米/POCO设备信息，包括设备代号、型号名称、品牌等。
 * 支持从数据库查询和本地JSON文件加载设备数据。
 */

class DeviceList
{
    /** @var array 设备列表缓存 */
    private static $devices = null;

    /**
     * 获取所有设备列表
     * 
     * @return array
     */
    public static function getAll(): array
    {
        if (self::$devices !== null) {
            return self::$devices;
        }
        
        // 先从数据库加载
        $dbDevices = Database::query("SELECT * FROM devices ORDER BY brand, model_name");
        
        if (!empty($dbDevices)) {
            self::$devices = $dbDevices;
            return self::$devices;
        }
        
        // 数据库为空则从内置列表加载
        self::$devices = self::getBuiltinDevices();
        return self::$devices;
    }

    /**
     * 根据代号获取设备
     */
    public static function getByCodename(string $codename): ?array
    {
        return Database::queryOne(
            "SELECT * FROM devices WHERE codename = ?",
            [$codename]
        );
    }

    /**
     * 获取设备的所有ROM列表
     */
    public static function getRoms(string $codename, array $filters = []): array
    {
        $sql = "SELECT r.*, d.model_name, d.brand, d.category 
                FROM roms r 
                LEFT JOIN devices d ON r.device_id = d.id 
                WHERE r.codename = ? AND r.is_active = 1";
        $params = [$codename];
        
        if (!empty($filters['region'])) {
            $sql .= " AND r.region = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['os_type'])) {
            $sql .= " AND r.os_type = ?";
            $params[] = $filters['os_type'];
        }
        if (!empty($filters['branch'])) {
            $sql .= " AND r.branch = ?";
            $params[] = $filters['branch'];
        }
        if (!empty($filters['flash_type'])) {
            $sql .= " AND r.flash_type = ?";
            $params[] = $filters['flash_type'];
        }
        
        $sql .= " ORDER BY r.release_date DESC, r.version DESC";
        
        return Database::query($sql, $params);
    }

    /**
     * 获取最新ROM列表
     * 
     * @param  int   $limit   数量
     * @param  array $filters 过滤条件
     * @return array
     */
    public static function getLatestRoms(int $limit = 20, array $filters = []): array
    {
        $sql = "SELECT r.*, d.model_name, d.brand, d.category 
                FROM roms r 
                LEFT JOIN devices d ON r.device_id = d.id 
                WHERE r.is_active = 1";
        $params = [];
        
        if (!empty($filters['region'])) {
            $sql .= " AND r.region = ?";
            $params[] = $filters['region'];
        }
        if (!empty($filters['os_type'])) {
            $sql .= " AND r.os_type = ?";
            $params[] = $filters['os_type'];
        }
        if (!empty($filters['branch'])) {
            $sql .= " AND r.branch = ?";
            $params[] = $filters['branch'];
        }
        
        $sql .= " ORDER BY r.release_date DESC, r.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return Database::query($sql, $params);
    }

    /**
     * 搜索ROM
     * 
     * @param  string $keyword 搜索关键词
     * @param  int    $limit   结果数量
     * @return array
     */
    public static function search(string $keyword, int $limit = 100): array
    {
        $kw = '%' . $keyword . '%';
        $sql = "SELECT r.*, d.model_name, d.brand, d.category 
                FROM roms r 
                LEFT JOIN devices d ON r.device_id = d.id 
                WHERE r.is_active = 1 
                  AND (d.model_name LIKE ? OR d.codename LIKE ? OR r.version LIKE ? 
                       OR d.model_number LIKE ? OR r.file_name LIKE ?)
                ORDER BY r.release_date DESC 
                LIMIT ?";
        
        return Database::query($sql, [$kw, $kw, $kw, $kw, $kw, $limit]);
    }

    /**
     * 获取统计信息
     */
    public static function getStats(): array
    {
        $totalDevices = Database::scalar("SELECT COUNT(*) FROM devices");
        $totalRoms    = Database::scalar("SELECT COUNT(*) FROM roms WHERE is_active = 1");
        $totalSize    = Database::scalar("SELECT COALESCE(SUM(file_size), 0) FROM roms WHERE is_active = 1");
        $latestUpdate = Database::scalar("SELECT MAX(created_at) FROM roms WHERE is_active = 1");
        $regions      = Database::query("SELECT region, COUNT(*) AS cnt FROM roms WHERE is_active = 1 GROUP BY region ORDER BY cnt DESC");
        $osTypes      = Database::query("SELECT os_type, COUNT(*) AS cnt FROM roms WHERE is_active = 1 GROUP BY os_type ORDER BY cnt DESC");
        
        return [
            'total_devices'  => (int) $totalDevices,
            'total_roms'     => (int) $totalRoms,
            'total_size'     => Utils::formatSize((int) $totalSize),
            'latest_update'  => $latestUpdate ?: '暂无数据',
            'regions'        => $regions,
            'os_types'       => $osTypes,
        ];
    }

    /**
     * 获取所有地域的ROM统计
     */
    public static function getRegionStats(): array
    {
        return Database::query(
            "SELECT region, COUNT(*) AS cnt, COUNT(DISTINCT codename) AS devices 
             FROM roms WHERE is_active = 1 
             GROUP BY region ORDER BY cnt DESC"
        );
    }

    /**
     * 获取ROM各分支统计
     */
    public static function getBranchStats(): array
    {
        return Database::query(
            "SELECT branch, COUNT(*) AS cnt 
             FROM roms WHERE is_active = 1 
             GROUP BY branch ORDER BY cnt DESC"
        );
    }

    /**
     * 内置设备列表（精选常用设备）
     * 数据来源: XiaomiFirmwareUpdater, miuirom.org
     * 此列表作为初始数据加载到数据库
     * 
     * @return array
     */
    private static function getBuiltinDevices(): array
    {
        return [
            // === 小米数字系列 ===
            ['codename' => 'cupid',     'model_name' => 'Xiaomi 12',              'brand' => 'Xiaomi', 'model_number' => '2201123C', 'category' => 'phone'],
            ['codename' => 'zeus',      'model_name' => 'Xiaomi 12 Pro',          'brand' => 'Xiaomi', 'model_number' => '2201122C', 'category' => 'phone'],
            ['codename' => 'psyche',    'model_name' => 'Xiaomi 12X',             'brand' => 'Xiaomi', 'model_number' => '2112123AC','category' => 'phone'],
            ['codename' => 'mayfly',    'model_name' => 'Xiaomi 12S',             'brand' => 'Xiaomi', 'model_number' => '2206123SC','category' => 'phone'],
            ['codename' => 'unicorn',   'model_name' => 'Xiaomi 12S Pro',         'brand' => 'Xiaomi', 'model_number' => '2206122SC','category' => 'phone'],
            ['codename' => 'thor',      'model_name' => 'Xiaomi 12S Ultra',       'brand' => 'Xiaomi', 'model_number' => '2203121C', 'category' => 'phone'],
            ['codename' => 'taoyao',    'model_name' => 'Xiaomi 12 Lite',         'brand' => 'Xiaomi', 'model_number' => '2203129G', 'category' => 'phone'],
            ['codename' => 'diting',    'model_name' => 'Xiaomi 12T Pro',         'brand' => 'Xiaomi', 'model_number' => '22081212UG','category' => 'phone'],
            ['codename' => 'plato',     'model_name' => 'Xiaomi 12T',             'brand' => 'Xiaomi', 'model_number' => '22071212AG','category' => 'phone'],
            ['codename' => 'fuxi',      'model_name' => 'Xiaomi 13',              'brand' => 'Xiaomi', 'model_number' => '2211133C', 'category' => 'phone'],
            ['codename' => 'nuwa',      'model_name' => 'Xiaomi 13 Pro',          'brand' => 'Xiaomi', 'model_number' => '2210132C', 'category' => 'phone'],
            ['codename' => 'ishtar',    'model_name' => 'Xiaomi 13 Ultra',        'brand' => 'Xiaomi', 'model_number' => '2304FPN6DC','category' => 'phone'],
            ['codename' => 'ziyi',      'model_name' => 'Xiaomi 13 Lite',         'brand' => 'Xiaomi', 'model_number' => '2210129SG','category' => 'phone'],
            ['codename' => 'houji',     'model_name' => 'Xiaomi 14',              'brand' => 'Xiaomi', 'model_number' => '23127PN0CC','category' => 'phone'],
            ['codename' => 'shennong',  'model_name' => 'Xiaomi 14 Pro',          'brand' => 'Xiaomi', 'model_number' => '23116PN5BC','category' => 'phone'],
            ['codename' => 'aurora',    'model_name' => 'Xiaomi 14 Ultra',        'brand' => 'Xiaomi', 'model_number' => '24031PN0DC','category' => 'phone'],
            ['codename' => 'dada',      'model_name' => 'Xiaomi 15',              'brand' => 'Xiaomi', 'model_number' => '24129PN74C','category' => 'phone'],
            ['codename' => 'haotian',   'model_name' => 'Xiaomi 15 Pro',          'brand' => 'Xiaomi', 'model_number' => '2410DPN6CC','category' => 'phone'],
            ['codename' => 'xuanyuan',  'model_name' => 'Xiaomi 15 Ultra',        'brand' => 'Xiaomi', 'model_number' => '25019PNF3C','category' => 'phone'],
            ['codename' => 'venus',     'model_name' => 'Xiaomi 11',              'brand' => 'Xiaomi', 'model_number' => 'M2011K2C',  'category' => 'phone'],
            ['codename' => 'star',      'model_name' => 'Xiaomi 11 Pro/Ultra',    'brand' => 'Xiaomi', 'model_number' => 'M2102K1C',  'category' => 'phone'],
            ['codename' => 'umi',       'model_name' => 'Xiaomi 10',              'brand' => 'Xiaomi', 'model_number' => 'M2001J2C',  'category' => 'phone'],
            ['codename' => 'cmi',       'model_name' => 'Xiaomi 10 Pro',          'brand' => 'Xiaomi', 'model_number' => 'M2001J1C',  'category' => 'phone'],
            ['codename' => 'renoir',    'model_name' => 'Xiaomi 11 Lite 5G NE',   'brand' => 'Xiaomi', 'model_number' => '2109119DG','category' => 'phone'],
            ['codename' => 'lisa',      'model_name' => 'Xiaomi 11 Lite 5G',      'brand' => 'Xiaomi', 'model_number' => '2109119DI','category' => 'phone'],
            ['codename' => 'courbet',   'model_name' => 'Xiaomi 11 Lite 4G',      'brand' => 'Xiaomi', 'model_number' => '2109119AG','category' => 'phone'],
            ['codename' => 'agate',     'model_name' => 'Xiaomi 11T',             'brand' => 'Xiaomi', 'model_number' => '21081111RG','category' => 'phone'],
            ['codename' => 'vili',      'model_name' => 'Xiaomi 11T Pro',         'brand' => 'Xiaomi', 'model_number' => '2107113SG','category' => 'phone'],
            ['codename' => 'cepheus',   'model_name' => 'Xiaomi 9',               'brand' => 'Xiaomi', 'model_number' => 'M1902F1G',  'category' => 'phone', 'status' => 'eol'],
            ['codename' => 'vangogh',   'model_name' => 'Xiaomi 10 Lite Zoom',    'brand' => 'Xiaomi', 'model_number' => 'M2002J9E',  'category' => 'phone'],
            ['codename' => 'thyme',     'model_name' => 'Xiaomi 10S',             'brand' => 'Xiaomi', 'model_number' => 'M2102J2SC', 'category' => 'phone'],
            ['codename' => 'toco',      'model_name' => 'Xiaomi Note 10 Lite',    'brand' => 'Xiaomi', 'model_number' => 'M2002F4LG', 'category' => 'phone'],
            ['codename' => 'tucana',    'model_name' => 'Xiaomi CC9 Pro',         'brand' => 'Xiaomi', 'model_number' => 'M1910F4E',  'category' => 'phone'],
            
            // === 小米Civi系列 ===
            ['codename' => 'mona',      'model_name' => 'Xiaomi Civi',            'brand' => 'Xiaomi', 'model_number' => '2109119BC','category' => 'phone'],
            ['codename' => 'zijin',     'model_name' => 'Xiaomi Civi 1S',         'brand' => 'Xiaomi', 'model_number' => '2204111AC','category' => 'phone'],
            ['codename' => 'yuechu',    'model_name' => 'Xiaomi Civi 3',          'brand' => 'Xiaomi', 'model_number' => '23046PNC9C','category' => 'phone'],
            
            // === 小米MIX系列 ===
            ['codename' => 'zizhan',    'model_name' => 'Xiaomi MIX Fold 2',      'brand' => 'Xiaomi', 'model_number' => '22061218C','category' => 'foldable'],
            ['codename' => 'babylon',   'model_name' => 'Xiaomi MIX Fold 3',      'brand' => 'Xiaomi', 'model_number' => '2308CPXD0C','category' => 'foldable'],
            ['codename' => 'cetus',     'model_name' => 'Xiaomi MIX Fold',        'brand' => 'Xiaomi', 'model_number' => 'M2011J18C', 'category' => 'foldable'],
            ['codename' => 'odin',      'model_name' => 'Xiaomi MIX 4',           'brand' => 'Xiaomi', 'model_number' => '2106118C',  'category' => 'phone'],
            
            // === 小米平板系列 ===
            ['codename' => 'nabu',      'model_name' => 'Xiaomi Pad 5',           'brand' => 'Xiaomi', 'model_number' => '21051182G','category' => 'tablet'],
            ['codename' => 'elish',     'model_name' => 'Xiaomi Pad 5 Pro WiFi',  'brand' => 'Xiaomi', 'model_number' => 'M2105K81AC','category' => 'tablet'],
            ['codename' => 'enuma',     'model_name' => 'Xiaomi Pad 5 Pro 5G',    'brand' => 'Xiaomi', 'model_number' => 'M2105K81C', 'category' => 'tablet'],
            ['codename' => 'dagu',      'model_name' => 'Xiaomi Pad 5 Pro 12.4',  'brand' => 'Xiaomi', 'model_number' => '22081281AC','category' => 'tablet'],
            ['codename' => 'pipa',      'model_name' => 'Xiaomi Pad 6',           'brand' => 'Xiaomi', 'model_number' => '23043RP34C','category' => 'tablet'],
            ['codename' => 'liuqin',    'model_name' => 'Xiaomi Pad 6 Pro',       'brand' => 'Xiaomi', 'model_number' => '23046RP50C','category' => 'tablet'],
            ['codename' => 'yudi',      'model_name' => 'Xiaomi Pad 6 Max 14',    'brand' => 'Xiaomi', 'model_number' => '23078RB5BC','category' => 'tablet'],
            ['codename' => 'sheng',     'model_name' => 'Xiaomi Pad 6S Pro 12.4', 'brand' => 'Xiaomi', 'model_number' => '24018RPACC','category' => 'tablet'],
            
            // === Redmi Note系列 ===
            ['codename' => 'ginkgo',    'model_name' => 'Redmi Note 8',           'brand' => 'Redmi',  'model_number' => 'M1908C3JG','category' => 'phone', 'status' => 'eol'],
            ['codename' => 'willow',    'model_name' => 'Redmi Note 8T',          'brand' => 'Redmi',  'model_number' => 'M1908C3XG','category' => 'phone', 'status' => 'eol'],
            ['codename' => 'begonia',   'model_name' => 'Redmi Note 8 Pro',       'brand' => 'Redmi',  'model_number' => 'M1906G7G', 'category' => 'phone', 'status' => 'eol'],
            ['codename' => 'joyeuse',   'model_name' => 'Redmi Note 9 Pro',       'brand' => 'Redmi',  'model_number' => 'M2003J6B2G','category' => 'phone'],
            ['codename' => 'gauguin',   'model_name' => 'Redmi Note 9 Pro 5G',    'brand' => 'Redmi',  'model_number' => 'M2007J17C','category' => 'phone'],
            ['codename' => 'camellia',  'model_name' => 'Redmi Note 10 5G',       'brand' => 'Redmi',  'model_number' => 'M2103K19G','category' => 'phone'],
            ['codename' => 'rosemary',  'model_name' => 'Redmi Note 10S',         'brand' => 'Redmi',  'model_number' => 'M2101K7BG','category' => 'phone'],
            ['codename' => 'sweet',     'model_name' => 'Redmi Note 10 Pro',      'brand' => 'Redmi',  'model_number' => 'M2101K6G', 'category' => 'phone'],
            ['codename' => 'selene',    'model_name' => 'Redmi 10',               'brand' => 'Redmi',  'model_number' => '21061119AG','category' => 'phone'],
            ['codename' => 'spes',      'model_name' => 'Redmi Note 11',          'brand' => 'Redmi',  'model_number' => '2201117TG','category' => 'phone'],
            ['codename' => 'veux',      'model_name' => 'Redmi Note 11 Pro 5G',   'brand' => 'Redmi',  'model_number' => '2201116SG','category' => 'phone'],
            ['codename' => 'viva',      'model_name' => 'Redmi Note 11 Pro 4G',   'brand' => 'Redmi',  'model_number' => '2201116TG','category' => 'phone'],
            ['codename' => 'pissarro',  'model_name' => 'Redmi Note 11 Pro+',     'brand' => 'Redmi',  'model_number' => '21091116UC','category' => 'phone'],
            ['codename' => 'xaga',      'model_name' => 'Redmi Note 11T Pro',     'brand' => 'Redmi',  'model_number' => '22041216C','category' => 'phone'],
            ['codename' => 'tapas',     'model_name' => 'Redmi Note 12 4G',       'brand' => 'Redmi',  'model_number' => '23021RAA2Y','category' => 'phone'],
            ['codename' => 'sunstone',  'model_name' => 'Redmi Note 12 5G',       'brand' => 'Redmi',  'model_number' => '22101317C','category' => 'phone'],
            ['codename' => 'ruby',      'model_name' => 'Redmi Note 12 Pro',      'brand' => 'Redmi',  'model_number' => '22101316C','category' => 'phone'],
            ['codename' => 'marble',    'model_name' => 'Redmi Note 12 Turbo',    'brand' => 'Redmi',  'model_number' => '23049RAD8C','category' => 'phone'],
            ['codename' => 'sapphire',  'model_name' => 'Redmi Note 13 4G',       'brand' => 'Redmi',  'model_number' => '23124RA7EO','category' => 'phone'],
            ['codename' => 'gold',      'model_name' => 'Redmi Note 13 5G',       'brand' => 'Redmi',  'model_number' => '2312DRAABC','category' => 'phone'],
            ['codename' => 'garnet',    'model_name' => 'Redmi Note 13 Pro 5G',   'brand' => 'Redmi',  'model_number' => '2312DRA50G','category' => 'phone'],
            ['codename' => 'zircon',    'model_name' => 'Redmi Note 13 Pro+',     'brand' => 'Redmi',  'model_number' => '23090RA98C','category' => 'phone'],
            ['codename' => 'amethyst',  'model_name' => 'Redmi Note 14 Pro 5G',   'brand' => 'Redmi',  'model_number' => '24094RAD4G','category' => 'phone'],
            
            // === Redmi K系列 ===
            ['codename' => 'alioth',    'model_name' => 'Redmi K40',              'brand' => 'Redmi',  'model_number' => 'M2012K11AC','category' => 'phone'],
            ['codename' => 'ares',      'model_name' => 'Redmi K40 Gaming',       'brand' => 'Redmi',  'model_number' => 'M2104K10AC','category' => 'phone'],
            ['codename' => 'mondrian',  'model_name' => 'Redmi K60',              'brand' => 'Redmi',  'model_number' => '23013RK75C','category' => 'phone'],
            ['codename' => 'socrates',  'model_name' => 'Redmi K60 Pro',          'brand' => 'Redmi',  'model_number' => '22127RK46C','category' => 'phone'],
            ['codename' => 'vermeer',   'model_name' => 'Redmi K70',              'brand' => 'Redmi',  'model_number' => '23113RKC6C','category' => 'phone'],
            ['codename' => 'manet',     'model_name' => 'Redmi K70 Pro',          'brand' => 'Redmi',  'model_number' => '23117RK66C','category' => 'phone'],
            ['codename' => 'duchamp',   'model_name' => 'Redmi K70E',             'brand' => 'Redmi',  'model_number' => '2311DRK48C','category' => 'phone'],
            ['codename' => 'rothko',    'model_name' => 'Redmi K80',              'brand' => 'Redmi',  'model_number' => '24122RKC7C','category' => 'phone'],
            ['codename' => 'miro',      'model_name' => 'Redmi K80 Pro',          'brand' => 'Redmi',  'model_number' => '24127RK2CC','category' => 'phone'],
            
            // === POCO系列 ===
            ['codename' => 'beryllium', 'model_name' => 'POCO F1',               'brand' => 'POCO',   'model_number' => 'M1805E10A','category' => 'phone', 'status' => 'eol'],
            ['codename' => 'vayu',      'model_name' => 'POCO X3 Pro',           'brand' => 'POCO',   'model_number' => 'M2102J20SG','category' => 'phone'],
            ['codename' => 'surya',     'model_name' => 'POCO X3 NFC',           'brand' => 'POCO',   'model_number' => 'M2007J20CG','category' => 'phone'],
            ['codename' => 'munch',     'model_name' => 'POCO F4',               'brand' => 'POCO',   'model_number' => '2201116PG','category' => 'phone'],
            ['codename' => 'marble',    'model_name' => 'POCO F5',               'brand' => 'POCO',   'model_number' => '23049PCD8G','category' => 'phone'],
            ['codename' => 'peridot',   'model_name' => 'POCO F6',               'brand' => 'POCO',   'model_number' => '24069PC21G','category' => 'phone'],
            ['codename' => 'redwood',   'model_name' => 'POCO X5 Pro 5G',        'brand' => 'POCO',   'model_number' => '22101320G','category' => 'phone'],
            ['codename' => 'garnet',    'model_name' => 'POCO X6 5G',            'brand' => 'POCO',   'model_number' => '23122PCD1G','category' => 'phone'],
            ['codename' => 'rodin',     'model_name' => 'POCO X7 Pro',           'brand' => 'POCO',   'model_number' => '2411DRN47G','category' => 'phone'],
            ['codename' => 'malachite', 'model_name' => 'POCO X7',               'brand' => 'POCO',   'model_number' => '2412DPC0AG','category' => 'phone'],
            ['codename' => 'onyx',      'model_name' => 'POCO F7',               'brand' => 'POCO',   'model_number' => '2505DRP06G','category' => 'phone'],
            ['codename' => 'zorn',      'model_name' => 'POCO F7 Pro',           'brand' => 'POCO',   'model_number' => '2505DRP06G','category' => 'phone'],
        ];
    }

    /**
     * 将内置设备列表导入数据库
     * 
     * @return int 导入的设备数量
     */
    public static function importBuiltinDevices(): int
    {
        $count = 0;
        $devices = self::getBuiltinDevices();
        
        Database::beginTransaction();
        try {
            foreach ($devices as $dev) {
                $existing = Database::queryOne(
                    "SELECT id FROM devices WHERE codename = ?",
                    [$dev['codename']]
                );
                
                if (!$existing) {
                    Database::execute(
                        "INSERT INTO devices (codename, model_name, brand, model_number, category, status) 
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $dev['codename'],
                            $dev['model_name'],
                            $dev['brand'],
                            $dev['model_number'],
                            $dev['category'],
                            $dev['status'] ?? 'active'
                        ]
                    );
                    $count++;
                }
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            error_log("Import devices failed: " . $e->getMessage());
        }
        
        return $count;
    }
}