<?php
/**
 * WHOIS域名监控服务
 * 低频检测：每12/24小时执行一次（保护WHOIS接口）
 */

require_once __DIR__ . '/WhoisChecker.php';

class WhoisMonitorService {
    
    /**
     * 检查是否需要执行WHOIS检测
     * @param PDO $conn 数据库连接
     * @param int $websiteId 网站ID
     * @param int $intervalHours 检测间隔（小时）
     * @return bool
     */
    public static function shouldCheck($conn, $websiteId, $intervalHours = 24) {
        $stmt = $conn->prepare("SELECT last_whois_check FROM websites WHERE id = ?");
        $stmt->execute([$websiteId]);
        $row = $stmt->fetch();
        
        if (!$row || empty($row['last_whois_check'])) {
            return true; // 从未检测过
        }
        
        $lastCheck = strtotime($row['last_whois_check']);
        $nextCheck = $lastCheck + ($intervalHours * 3600);
        
        return time() >= $nextCheck;
    }
    
    /**
     * 执行WHOIS检测（批量）
     * @param PDO $conn 数据库连接
     * @param array $websites 网站列表
     * @return array 检测结果
     */
    public static function checkBatch($conn, $websites) {
        $results = [];
        $total = count($websites);
        $idx = 0;
        
        foreach ($websites as $website) {
            $idx++;
            $siteId = $website['id'];
            $domain = $website['host'] ?? $website['url'];
            
            // 执行WHOIS检测
            $whoisResult = WhoisChecker::check($domain);
            
            $result = [
                'site_id' => $siteId,
                'domain' => $domain,
                'status' => $whoisResult['status'],
                'days' => $whoisResult['days'],
                'expire_date' => $whoisResult['expire_date'],
                'source' => $whoisResult['source'],
                'message' => $whoisResult['message'] ?? null
            ];
            
            $results[$siteId] = $result;
            
            // 更新数据库
            self::updateDatabase($conn, $siteId, $result);
            
            // 依次查询，每个域名间隔2秒（最后一个不等）
            if ($idx < $total) {
                usleep(2000000);
            }
        }
        
        return $results;
    }
    
    /**
     * 更新数据库
     */
    private static function updateDatabase($conn, $siteId, $result) {
        try {
            if ($result['status'] === 'success') {
                $stmt = $conn->prepare("
                    UPDATE websites 
                    SET whois_days = ?, 
                        whois_expire_date = ?, 
                        last_whois_check = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $result['days'],
                    $result['expire_date'],
                    $siteId
                ]);
            } else {
                // 检测失败，只更新时间
                $stmt = $conn->prepare("
                    UPDATE websites 
                    SET last_whois_check = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$siteId]);
            }
        } catch (PDOException $e) {
            error_log("WHOIS数据库更新失败: " . $e->getMessage());
        }
    }
    
    /**
     * 检查告警并发送
     * @param PDO $conn 数据库连接
     * @param array $results WHOIS检测结果
     */
    public static function checkAlerts($conn, $results) {
        // 获取告警配置
        $settings = self::loadSettings($conn);
        $warningDays = $settings['whois_warning_days'] ?? 30;
        $cooldownHours = $settings['whois_alert_cooldown_hours'] ?? 168;
        
        $alerts = [];
        
        foreach ($results as $result) {
            if ($result['status'] !== 'success') {
                continue;
            }
            
            $days = $result['days'];
            $siteId = $result['site_id'];
            
            // 检查是否达到告警阈值
            if ($days > 0 && $days <= $warningDays) {
                // 检查冷却时间
                if (self::canAlert($conn, $siteId, $cooldownHours)) {
                    $alerts[] = $result;
                    
                    // 更新告警时间
                    self::updateAlertTime($conn, $siteId);
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * 检查是否可以发送告警（冷却时间）
     */
    private static function canAlert($conn, $siteId, $cooldownHours) {
        $stmt = $conn->prepare("SELECT last_whois_alert FROM websites WHERE id = ?");
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        
        if (!$row || empty($row['last_whois_alert'])) {
            return true; // 从未告警过
        }
        
        $lastAlert = strtotime($row['last_whois_alert']);
        $nextAlert = $lastAlert + ($cooldownHours * 3600);
        
        return time() >= $nextAlert;
    }
    
    /**
     * 更新告警时间
     */
    private static function updateAlertTime($conn, $siteId) {
        try {
            $stmt = $conn->prepare("
                UPDATE websites 
                SET last_whois_alert = NOW(),
                    whois_alert_count = whois_alert_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$siteId]);
        } catch (PDOException $e) {
            error_log("更新WHOIS告警时间失败: " . $e->getMessage());
        }
    }
    
    /**
     * 加载设置
     */
    private static function loadSettings($conn) {
        $stmt = $conn->query("
            SELECT setting_name, setting_value 
            FROM alert_settings 
            WHERE setting_name LIKE 'whois_%'
        ");
        
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    /**
     * 获取需要检测的网站列表
     */
    public static function getWebsitesToCheck($conn, $intervalHours = 24) {
        $sql = "
            SELECT id, name, url, host, last_whois_check 
            FROM websites 
            WHERE enabled = 1 
            AND (
                last_whois_check IS NULL 
                OR last_whois_check < DATE_SUB(NOW(), INTERVAL ? HOUR)
            )
            ORDER BY last_whois_check ASC
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$intervalHours]);
        
        return $stmt->fetchAll();
    }
}
