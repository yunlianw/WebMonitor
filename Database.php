<?php
/**
 * MySQL数据库连接类
 */

require_once __DIR__ . '/config/Config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        $this->config = Config::getInstance();
        
        try {
            $dbConfig = $this->config->getDatabaseConfig();
            
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            
            $this->connection = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            // 设置时区和优化设置
            $this->connection->exec("SET time_zone = '+08:00'");
            $this->connection->exec("SET sql_mode = ''");
            
        } catch (PDOException $e) {
            // 不直接die，让调用者处理
            throw new RuntimeException("数据库连接失败: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * 更新数据库配置（由用户提供）
     */
    public static function updateConfig($host, $database, $username, $password, $port = 3306) {
        $config = Config::getInstance();
        $config->updateDatabaseConfig([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4'
        ]);
        
        // 重置实例，下次连接使用新配置
        self::$instance = null;
    }
    
    /**
     * 测试数据库连接
     */
    public static function testConnection($host, $database, $username, $password, $port = 3306) {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $conn = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            // 测试查询
            $stmt = $conn->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => '数据库连接成功',
                'version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '数据库连接失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 执行SQL文件初始化数据库
     */
    public static function initializeDatabase($sqlFile) {
        try {
            $db = self::getInstance();
            $conn = $db->getConnection();
            
            // 读取SQL文件
            $sql = file_get_contents($sqlFile);
            
            // 分割SQL语句
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            // 执行每个语句
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $conn->exec($statement);
                }
            }
            
            return [
                'success' => true,
                'message' => '数据库初始化成功'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '数据库初始化失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 从JSON文件迁移数据到数据库
     */
    public static function migrateFromJson() {
        try {
            $db = self::getInstance();
            $conn = $db->getConnection();
            
            $configFile = __DIR__ . '/data/config.json';
            if (!file_exists($configFile)) {
                return ['success' => false, 'message' => 'JSON配置文件不存在'];
            }
            
            $jsonData = json_decode(file_get_contents($configFile), true);
            
            // 开始事务
            $conn->beginTransaction();
            
            // 1. 迁移用户数据
            if (!empty($jsonData['users'])) {
                foreach ($jsonData['users'] as $user) {
                    $stmt = $conn->prepare("
                        INSERT INTO users (username, password_hash, email, role) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            password_hash = VALUES(password_hash),
                            email = VALUES(email),
                            role = VALUES(role)
                    ");
                    $stmt->execute([
                        $user['username'],
                        $user['password'],
                        $user['email'] ?? '',
                        $user['role'] ?? 'admin'
                    ]);
                }
            }
            
            // 2. 迁移网站数据
            if (!empty($jsonData['websites'])) {
                foreach ($jsonData['websites'] as $website) {
                    $host = parse_url($website['url'], PHP_URL_HOST);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO websites (name, url, host, check_http, check_ssl, enabled, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            name = VALUES(name),
                            url = VALUES(url),
                            check_http = VALUES(check_http),
                            check_ssl = VALUES(check_ssl),
                            enabled = VALUES(enabled)
                    ");
                    
                    $stmt->execute([
                        $website['name'],
                        $website['url'],
                        $host,
                        $website['check_http'] ?? true,
                        $website['check_ssl'] ?? true,
                        $website['enabled'] ?? true,
                        $website['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                    
                    $websiteId = $conn->lastInsertId();
                    
                    // 如果有监控状态，也迁移
                    if (isset($website['last_status']) && $website['last_status'] !== 'unknown') {
                        $stmt = $conn->prepare("
                            INSERT INTO monitor_logs (website_id, check_type, http_status, http_message, ssl_status, ssl_message, checked_at)
                            VALUES (?, 'both', ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $websiteId,
                            $website['last_status'],
                            $website['last_status_text'] ?? '',
                            $website['ssl_status'] ?? 'unknown',
                            $website['ssl_status_text'] ?? '',
                            $website['last_check'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            
            // 3. 迁移邮件配置
            if (!empty($jsonData['email_config'])) {
                $emailConfig = $jsonData['email_config'];
                $toEmails = !empty($emailConfig['to_emails']) ? json_encode($emailConfig['to_emails']) : '[]';
                
                $stmt = $conn->prepare("
                    INSERT INTO email_config (enabled, smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, from_email, from_name, to_emails, last_test, test_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        enabled = VALUES(enabled),
                        smtp_host = VALUES(smtp_host),
                        smtp_port = VALUES(smtp_port),
                        smtp_secure = VALUES(smtp_secure),
                        smtp_username = VALUES(smtp_username),
                        smtp_password = VALUES(smtp_password),
                        from_email = VALUES(from_email),
                        from_name = VALUES(from_name),
                        to_emails = VALUES(to_emails),
                        last_test = VALUES(last_test),
                        test_status = VALUES(test_status)
                ");
                
                $stmt->execute([
                    $emailConfig['enabled'] ?? false,
                    $emailConfig['smtp_host'] ?? 'smtp.163.com',
                    $emailConfig['smtp_port'] ?? 465,
                    $emailConfig['smtp_secure'] ?? 'ssl',
                    $emailConfig['smtp_username'] ?? '',
                    $emailConfig['smtp_password'] ?? '',
                    $emailConfig['from_email'] ?? '',
                    $emailConfig['from_name'] ?? '网站监控系统',
                    $toEmails,
                    $emailConfig['last_test'] ?? null,
                    $emailConfig['test_status'] ?? '未测试'
                ]);
            }
            
            // 4. 迁移系统设置
            if (!empty($jsonData['settings'])) {
                $settings = $jsonData['settings'];
                
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (monitor_key, check_interval, ssl_warning_days, history_retention_days, timeout_seconds, last_check)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        monitor_key = VALUES(monitor_key),
                        check_interval = VALUES(check_interval),
                        ssl_warning_days = VALUES(ssl_warning_days),
                        history_retention_days = VALUES(history_retention_days),
                        timeout_seconds = VALUES(timeout_seconds),
                        last_check = VALUES(last_check)
                ");
                
                $stmt->execute([
                    $settings['monitor_key'] ?? bin2hex(random_bytes(16)),
                    $settings['check_interval'] ?? 60,
                    $settings['ssl_warning_days'] ?? 7,
                    $settings['history_retention_days'] ?? 30,
                    $settings['timeout_seconds'] ?? 10,
                    $jsonData['last_check'] ?? null
                ]);
            }
            
            // 提交事务
            $conn->commit();
            
            return [
                'success' => true,
                'message' => '数据迁移成功',
                'migrated' => [
                    'users' => count($jsonData['users'] ?? []),
                    'websites' => count($jsonData['websites'] ?? []),
                    'settings' => !empty($jsonData['settings']) ? 1 : 0
                ]
            ];
            
        } catch (PDOException $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            return [
                'success' => false,
                'message' => '数据迁移失败: ' . $e->getMessage()
            ];
        }
    }
}