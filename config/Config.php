<?php
/**
 * 中央配置文件系统
 */

class Config {
    private static $instance = null;
    private $config = [];
    private $configFile;
    
    // 支持的存储类型
    const STORAGE_JSON = 'json';
    const STORAGE_MYSQL = 'mysql';
    
    private function __construct() {
        // 配置直接写在PHP文件里，不使用config.json
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        // 直接使用MySQL配置（不再读取config.json）
        $this->config = [
            'storage_type' => 'mysql',
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'webmonitor',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4'
            ],
            'system' => [
                'timezone' => 'Asia/Shanghai',
                'debug' => false,
                'cache_enabled' => true
            ]
        ];
        
        // 从环境变量覆盖（如果有）
        $this->loadFromEnv();
    }
    
    /**
     * 从环境变量加载配置
     */
    private function loadFromEnv() {
        $envVars = [
            'DB_HOST' => ['database', 'host'],
            'DB_PORT' => ['database', 'port'],
            'DB_DATABASE' => ['database', 'database'],
            'DB_USERNAME' => ['database', 'username'],
            'DB_PASSWORD' => ['database', 'password'],
            'STORAGE_TYPE' => ['storage_type']
        ];
        
        foreach ($envVars as $envVar => $configPath) {
            $value = getenv($envVar);
            if ($value !== false) {
                $this->setConfigValue($configPath, $value);
            }
        }
    }
    
    /**
     * 设置配置值
     */
    private function setConfigValue($path, $value) {
        if (is_string($path)) {
            $this->config[$path] = $value;
        } elseif (is_array($path) && count($path) === 2) {
            $this->config[$path[0]][$path[1]] = $value;
        }
    }
    
    /**
     * 获取配置
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * 设置配置
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
        $this->saveConfig();
    }
    
    /**
     * 保存配置到文件
     */
    public function saveConfig() {
        // 确保目录存在
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 保存配置
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
        
        // 创建存储类型标志文件
        $storageType = $this->get('storage_type', self::STORAGE_JSON);
        $storageFile = __DIR__ . '/../data/storage_type';
        file_put_contents($storageFile, $storageType);
    }
    
    /**
     * 获取当前存储类型
     */
    public function getStorageType() {
        return $this->get('storage_type', self::STORAGE_JSON);
    }
    
    /**
     * 设置存储类型
     */
    public function setStorageType($type) {
        if (!in_array($type, [self::STORAGE_JSON, self::STORAGE_MYSQL])) {
            throw new InvalidArgumentException('不支持的存储类型: ' . $type);
        }
        
        $this->set('storage_type', $type);
        
        // 创建/删除标志文件
        $mysqlFlagFile = __DIR__ . '/../data/use_mysql';
        if ($type === self::STORAGE_MYSQL) {
            file_put_contents($mysqlFlagFile, '1');
        } else {
            @unlink($mysqlFlagFile);
        }
    }
    
    /**
     * 获取数据库配置
     */
    public function getDatabaseConfig() {
        return $this->get('database', []);
    }
    
    /**
     * 更新数据库配置
     */
    public function updateDatabaseConfig($config) {
        $required = ['host', 'database', 'username', 'password'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException('缺少必需的数据库配置: ' . $key);
            }
        }
        
        $this->set('database', array_merge($this->getDatabaseConfig(), $config));
    }
    
    /**
     * 测试数据库连接
     */
    public function testDatabaseConnection() {
        $dbConfig = $this->getDatabaseConfig();
        
        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $conn = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // 获取MySQL版本
            $version = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            // 测试简单查询
            $stmt = $conn->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => '数据库连接成功',
                'version' => $version,
                'config' => [
                    'host' => $dbConfig['host'],
                    'port' => $dbConfig['port'],
                    'database' => $dbConfig['database'],
                    'username' => $dbConfig['username']
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '数据库连接失败: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * 备份当前配置
     */
    public function backup() {
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/config_backup_' . date('Ymd_His') . '.json';
        copy($this->configFile, $backupFile);
        
        return $backupFile;
    }
    
    /**
     * 恢复备份
     */
    public function restore($backupFile) {
        if (!file_exists($backupFile)) {
            throw new RuntimeException('备份文件不存在: ' . $backupFile);
        }
        
        $backupData = json_decode(file_get_contents($backupFile), true);
        if (!$backupData) {
            throw new RuntimeException('备份文件格式错误');
        }
        
        // 备份当前配置
        $currentBackup = $this->backup();
        
        // 恢复备份
        $this->config = array_merge($this->config, $backupData);
        $this->saveConfig();
        
        return [
            'success' => true,
            'message' => '配置恢复成功',
            'backup_file' => $backupFile,
            'current_backup' => $currentBackup
        ];
    }
    
    /**
     * 导出配置
     */
    public function export() {
        return [
            'config' => $this->config,
            'file_path' => $this->configFile,
            'storage_type' => $this->getStorageType(),
            'database_config' => $this->getDatabaseConfig(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 初始化系统配置
     */
    public function initializeSystem() {
        // 设置时区
        date_default_timezone_set($this->get('system.timezone', 'Asia/Shanghai'));
        
        // 错误报告
        if ($this->get('system.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        
        // 会话设置
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // 输出缓冲
        if (!ob_get_level()) {
            ob_start();
        }
    }
}